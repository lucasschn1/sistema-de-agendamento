import { useCallback, useEffect, useState } from 'react'
import { Button, Table, Alert, Form } from 'react-bootstrap'
import {
  listRentalRooms, deactivateRentalRoom, activateRentalRoom,
  listRentalInvoices,
} from '../../api/rentals'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import { usePersistedState } from '../../hooks/usePersistedState'
import TableSkeleton from '../../components/TableSkeleton'
import RentalRoomFormModal from './RentalRoomFormModal'
import RentalPayInvoiceModal from './RentalPayInvoiceModal'
import RoomScheduleTab from './RoomScheduleTab'

const VALID_VIEWS = ['schedule', 'rooms', 'invoices']

const INVOICE_STATUS_PILL = {
  pending: 'status-pill-pending',
  paid: 'status-pill-active',
  overdue: 'status-pill-overdue',
}

const INVOICE_STATUS_LABEL = {
  pending: 'Pendente',
  paid: 'Paga',
  overdue: 'Vencida',
}

export default function Rentals() {
  const { showToast } = useToast()
  const [view, setView] = usePersistedState('rentals:view', 'schedule')
  const activeView = VALID_VIEWS.includes(view) ? view : 'schedule'

  return (
    <div className="rentals-page">
      <div className="filter-chip-group mb-4">
        <button type="button" className={`filter-chip${activeView === 'schedule' ? ' active' : ''}`} onClick={() => setView('schedule')}>
          Agenda
        </button>
        <button type="button" className={`filter-chip${activeView === 'rooms' ? ' active' : ''}`} onClick={() => setView('rooms')}>
          Salas
        </button>
        <button type="button" className={`filter-chip${activeView === 'invoices' ? ' active' : ''}`} onClick={() => setView('invoices')}>
          Faturas
        </button>
      </div>

      {activeView === 'schedule' && <RoomScheduleTab showToast={showToast} />}
      {activeView === 'rooms' && <RoomsTab showToast={showToast} />}
      {activeView === 'invoices' && <InvoicesTab showToast={showToast} />}
    </div>
  )
}

// =============================================
// ABA: SALAS
// =============================================
function RoomsTab({ showToast }) {
  const [rooms, setRooms] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [showInactive, setShowInactive] = usePersistedState('rentals:rooms:showInactive', false)
  const [showModal, setShowModal] = useState(false)
  const [editingRoom, setEditingRoom] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setRooms(await listRentalRooms(!showInactive))
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [showInactive])

  useEffect(() => { load() }, [load])

  const handleToggleActive = async (room) => {
    try {
      if (room.active) {
        await deactivateRentalRoom(room.id)
        showToast('Sala desativada com sucesso')
      } else {
        await activateRentalRoom(room.id)
        showToast('Sala ativada com sucesso')
      }
      load()
    } catch (err) {
      showToast(parseApiError(err), 'danger')
    }
  }

  return (
    <>
      <div className="patients-toolbar">
        <Form.Check
          type="switch"
          id="show-inactive-rooms"
          label="Mostrar inativas"
          checked={showInactive}
          onChange={(e) => setShowInactive(e.target.checked)}
        />
        <Button variant="primary" onClick={() => { setEditingRoom(null); setShowModal(true) }}>
          + Nova sala
        </Button>
      </div>

      {error && <Alert variant="danger" onClose={() => setError('')} dismissible>{error}</Alert>}

      <div className="patients-table-card">
        {loading ? (
          <Table hover responsive className="mb-0">
            <thead><tr><th>Nome</th><th>Status</th><th>Ações</th></tr></thead>
            <TableSkeleton columns={3} />
          </Table>
        ) : rooms.length === 0 ? (
          <p className="text-muted text-center py-4 mb-0">Nenhuma sala cadastrada.</p>
        ) : (
          <Table hover responsive className="mb-0">
            <thead><tr><th>Nome</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
              {rooms.map((r) => (
                <tr key={r.id}>
                  <td className="table-row-name">{r.name}</td>
                  <td>
                    <span className={r.active ? 'status-pill-active' : 'status-pill-inactive'}>
                      {r.active ? 'Ativa' : 'Inativa'}
                    </span>
                  </td>
                  <td>
                    <div className="table-actions">
                      <Button size="sm" variant="outline-primary" onClick={() => { setEditingRoom(r); setShowModal(true) }}>
                        Editar
                      </Button>
                      <Button
                        size="sm"
                        variant={r.active ? 'outline-danger' : 'outline-success'}
                        onClick={() => handleToggleActive(r)}
                      >
                        {r.active ? 'Desativar' : 'Ativar'}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </div>

      <RentalRoomFormModal
        show={showModal}
        room={editingRoom}
        onClose={() => setShowModal(false)}
        onSaved={() => { showToast(editingRoom ? 'Sala atualizada' : 'Sala criada'); load() }}
      />
    </>
  )
}

// =============================================
// ABA: FATURAS
// =============================================
function monthKey(offset = 0) {
  const now = new Date()
  const d = new Date(now.getFullYear(), now.getMonth() + offset, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function formatCurrency(value) {
  return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function InvoicesTab({ showToast }) {
  const [invoices, setInvoices] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [paying, setPaying] = useState(null)
  const [monthOffset, setMonthOffset] = useState(0)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setInvoices(await listRentalInvoices())
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])

  const currentMonthKey = monthKey(monthOffset)
  const rawMonthLabel = new Date(`${currentMonthKey}-01T00:00:00`).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
  const monthLabel = rawMonthLabel.charAt(0).toUpperCase() + rawMonthLabel.slice(1)
  const monthInvoices = invoices.filter((i) => i.reference_month === currentMonthKey)

  const summary = Object.values(
    monthInvoices.reduce((acc, i) => {
      const key = i.tenant?.id ?? i.tenant_user_id ?? 'sem-profissional'
      if (!acc[key]) {
        acc[key] = { tenant: i.tenant, total: 0, count: 0, hasOverdue: false, hasPending: false }
      }
      acc[key].total += Number(i.amount)
      acc[key].count += 1
      if (i.status === 'overdue') acc[key].hasOverdue = true
      if (i.status === 'pending') acc[key].hasPending = true
      return acc
    }, {})
  ).sort((a, b) => b.total - a.total)

  const grandTotal = summary.reduce((sum, s) => sum + s.total, 0)

  return (
    <>
      <div className="patients-toolbar">
        <Button size="sm" variant="outline-secondary" onClick={() => setMonthOffset((v) => v - 1)}>← Mês anterior</Button>
        <span className="fw-bold">{monthLabel}</span>
        <Button size="sm" variant="outline-secondary" onClick={() => setMonthOffset((v) => v + 1)}>Próximo mês →</Button>
        <Button size="sm" variant="outline-secondary" className="ms-auto" onClick={() => setMonthOffset(0)}>Mês atual</Button>
      </div>

      {error && <Alert variant="danger" onClose={() => setError('')} dismissible>{error}</Alert>}

      <h6 className="rentals-section-title">Total a pagar por profissional</h6>
      <div className="patients-table-card mb-4">
        {loading ? (
          <Table hover responsive className="mb-0">
            <thead><tr><th>Profissional</th><th>Faturas</th><th>Total do mês</th><th>Status</th></tr></thead>
            <TableSkeleton columns={4} />
          </Table>
        ) : summary.length === 0 ? (
          <p className="text-muted text-center py-4 mb-0">Nenhuma fatura neste mês.</p>
        ) : (
          <Table hover responsive className="mb-0 rentals-summary-table">
            <thead><tr><th>Profissional</th><th>Faturas</th><th>Total do mês</th><th>Status</th></tr></thead>
            <tbody>
              {summary.map((s) => (
                <tr key={s.tenant?.id ?? s.tenant?.name}>
                  <td className="table-row-name">{s.tenant?.name ?? '—'}</td>
                  <td>{s.count}</td>
                  <td className="rentals-summary-total">{formatCurrency(s.total)}</td>
                  <td>
                    <span className={s.hasOverdue ? INVOICE_STATUS_PILL.overdue : s.hasPending ? INVOICE_STATUS_PILL.pending : INVOICE_STATUS_PILL.paid}>
                      {s.hasOverdue ? 'Com vencidas' : s.hasPending ? 'Pendente' : 'Tudo pago'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="rentals-summary-footer">
                <td colSpan={2}>Total geral do mês</td>
                <td className="rentals-summary-total">{formatCurrency(grandTotal)}</td>
                <td />
              </tr>
            </tfoot>
          </Table>
        )}
      </div>

      <h6 className="rentals-section-title">Detalhamento das faturas</h6>
      <div className="patients-table-card">
        {loading ? (
          <Table hover responsive className="mb-0">
            <thead><tr><th>Tipo</th><th>Profissional</th><th>Valor</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr></thead>
            <TableSkeleton columns={6} />
          </Table>
        ) : monthInvoices.length === 0 ? (
          <p className="text-muted text-center py-4 mb-0">Nenhuma fatura gerada neste mês.</p>
        ) : (
          <Table hover responsive className="mb-0">
            <thead><tr><th>Tipo</th><th>Profissional</th><th>Valor</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
              {monthInvoices.map((i) => (
                <tr key={i.id}>
                  <td>{i.type === 'period_advance' ? 'Fixo (antecipado)' : 'Avulso (mensal)'}</td>
                  <td>{i.tenant?.name}</td>
                  <td className="table-row-name">{i.formatted_amount}</td>
                  <td>{i.due_date}</td>
                  <td>
                    <span className={INVOICE_STATUS_PILL[i.status]}>
                      {INVOICE_STATUS_LABEL[i.status]}
                    </span>
                  </td>
                  <td>
                    {i.status !== 'paid' && (
                      <Button size="sm" variant="outline-primary" onClick={() => setPaying(i)}>
                        Marcar como paga
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </div>

      <RentalPayInvoiceModal
        show={!!paying}
        invoice={paying}
        onClose={() => setPaying(null)}
        onSaved={() => { showToast('Fatura marcada como paga'); load() }}
      />
    </>
  )
}
