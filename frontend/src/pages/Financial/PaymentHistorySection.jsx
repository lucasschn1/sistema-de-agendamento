import { useCallback, useEffect, useState } from 'react'
import { Table, Button, Spinner, Dropdown } from 'react-bootstrap'
import { getHistorySummary, getHistoryPayments, undoPayment } from '../../api/financial'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import ConfirmModal from '../../components/ConfirmModal'
import PaymentHistoryFilters from './PaymentHistoryFilters'

function formatCurrency(value) {
  return (value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function monthKey(offset = 0) {
  const now = new Date()
  const d = new Date(now.getFullYear(), now.getMonth() + offset, 1)
  return { year: d.getFullYear(), month: d.getMonth() + 1 }
}

function monthLabel(year, month) {
  const raw = new Date(`${year}-${String(month).padStart(2, '0')}-01T00:00:00`)
    .toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
  return raw.charAt(0).toUpperCase() + raw.slice(1)
}

export default function PaymentHistorySection({ onDataChanged }) {
  const { showToast } = useToast()

  const [monthOffset, setMonthOffset] = useState(0)
  const [filters, setFilters] = useState({})

  const [summary, setSummary] = useState(null)
  const [payments, setPayments] = useState([])
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const [undoTarget, setUndoTarget] = useState(null)
  const [undoReason, setUndoReason] = useState('Correção de lançamento')

  const { year, month } = monthKey(monthOffset)

  const load = useCallback(async (targetPage = 1) => {
    setLoading(true)
    setError('')
    try {
      const params = { year, month, page: targetPage, per_page: 20, ...filters }
      const [summaryData, paymentsData] = await Promise.all([
        getHistorySummary(params),
        getHistoryPayments(params),
      ])
      setSummary(summaryData)
      setPayments(paymentsData.data)
      setPage(paymentsData.page)
      setTotalPages(paymentsData.total_pages)
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [year, month, filters])

  useEffect(() => {
    load(1)
  }, [load])

  const handleUndo = async () => {
    await undoPayment(undoTarget.id, undoReason.trim())
    showToast('Pagamento desfeito com sucesso')
    load(page)
    onDataChanged?.()
  }

  return (
    <div>
      <div className="financial-history-toolbar mb-3">
        <Button size="sm" variant="outline-secondary" onClick={() => setMonthOffset((v) => v - 1)}>
          ← Mês anterior
        </Button>
        <span className="fw-bold">{monthLabel(year, month)}</span>
        <Button size="sm" variant="outline-secondary" onClick={() => setMonthOffset((v) => v + 1)}>
          Próximo mês →
        </Button>
        <Button size="sm" variant="outline-secondary" className="ms-auto" onClick={() => setMonthOffset(0)}>
          Mês atual
        </Button>
      </div>

      <div className="mb-3">
        <PaymentHistoryFilters filters={filters} onChange={setFilters} searching={loading} />
      </div>

      {error && <p className="text-danger">{error}</p>}

      <div className="financial-kpi-grid mb-4">
        <div className="stat-card">
          <div className="stat-card-label">Receita total</div>
          <div className="stat-card-value">{formatCurrency(summary?.total_revenue)}</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-label">Pagamentos</div>
          <div className="stat-card-value">{summary?.payment_count ?? 0}</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-label">Sessões pagas</div>
          <div className="stat-card-value">{summary?.session_count ?? 0}</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-label">Ticket médio</div>
          <div className="stat-card-value">{formatCurrency(summary?.average_ticket)}</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-label">Pacientes atendidos</div>
          <div className="stat-card-value">{summary?.patient_count ?? 0}</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-label">Profissionais</div>
          <div className="stat-card-value">{summary?.professional_count ?? 0}</div>
        </div>
      </div>

      <div className="financial-table-card">
        {loading ? (
          <div className="text-center py-4">
            <Spinner animation="border" size="sm" />
          </div>
        ) : payments.length === 0 ? (
          <p className="text-muted text-center py-3 mb-0">Nenhum pagamento registrado nesta competência.</p>
        ) : (
          <Table hover responsive className="mb-0">
            <thead>
              <tr>
                <th>Pago em</th>
                <th>Sessão</th>
                <th>Paciente</th>
                <th>Profissional</th>
                <th>Procedimento</th>
                <th>Método</th>
                <th>Valor</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {payments.map((apt) => (
                <tr key={apt.id}>
                  <td>{apt.payment_date ?? '—'}</td>
                  <td>{apt.formatted_start}</td>
                  <td>{apt.patient?.name}</td>
                  <td>{apt.professional?.name}</td>
                  <td>{apt.service?.name}</td>
                  <td>{apt.payment_method ?? '—'}</td>
                  <td>{apt.formatted_price}</td>
                  <td className="text-end">
                    <Dropdown align="end">
                      <Dropdown.Toggle size="sm" variant="outline-secondary" className="history-row-actions-toggle">
                        ⋯
                      </Dropdown.Toggle>
                      <Dropdown.Menu>
                        <Dropdown.Item
                          onClick={() => { setUndoTarget(apt); setUndoReason('Correção de lançamento') }}
                        >
                          Desfazer pagamento
                        </Dropdown.Item>
                      </Dropdown.Menu>
                    </Dropdown>
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </div>

      {totalPages > 1 && (
        <div className="d-flex align-items-center justify-content-end gap-2 mt-3">
          <Button size="sm" variant="outline-secondary" disabled={loading || page <= 1} onClick={() => load(page - 1)}>
            ← Anterior
          </Button>
          <span className="text-muted small">Página {page} de {totalPages}</span>
          <Button size="sm" variant="outline-secondary" disabled={loading || page >= totalPages} onClick={() => load(page + 1)}>
            Próxima →
          </Button>
        </div>
      )}

      <ConfirmModal
        show={!!undoTarget}
        title="Desfazer pagamento"
        variant="danger"
        confirmLabel="Desfazer"
        onClose={() => setUndoTarget(null)}
        onConfirm={handleUndo}
        message={
          <>
            <p>
              Isso removerá o registro de pagamento de <strong>{undoTarget?.patient?.name}</strong>{' '}
              ({undoTarget?.formatted_price}) e a sessão voltará para "Sessões aguardando registro de pagamento".
            </p>
            <label className="form-label mb-1">Motivo</label>
            <input
              type="text"
              className="form-control form-control-sm"
              value={undoReason}
              onChange={(e) => setUndoReason(e.target.value)}
            />
          </>
        }
      />
    </div>
  )
}
