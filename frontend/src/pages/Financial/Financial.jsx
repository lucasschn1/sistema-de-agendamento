import { useCallback, useEffect, useState } from 'react'
import { Row, Col, Table, Button, Alert, Spinner } from 'react-bootstrap'
import {
  getCurrentMonthSummary,
  getTodaySummary,
  getPendingPayments,
  getSummaryByProfessionals,
  getSummaryByMonth,
  getRecentPayments,
} from '../../api/financial'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import HorizontalBars from '../../components/charts/HorizontalBars'
import VerticalBars from '../../components/charts/VerticalBars'
import StackedBar from '../../components/charts/StackedBar'
import PaymentModal from './PaymentModal'

const MONTH_LABELS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']

const PAYMENT_METHOD_LABELS = {
  'PIX': 'PIX',
  'Dinheiro': 'Dinheiro',
  'Cartão de Crédito': 'Cartão de Crédito',
  'Cartão de Débito': 'Cartão de Débito',
  'Transferência': 'Transferência',
}

function currentMonthRange() {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1)
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  const fmt = (d) => d.toISOString().slice(0, 10)
  return { start: fmt(start), end: fmt(end) }
}

function lastNMonths(n) {
  const now = new Date()
  const months = []
  for (let i = n - 1; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    months.push({ year: d.getFullYear(), month: d.getMonth() + 1, label: MONTH_LABELS[d.getMonth()] })
  }
  return months
}

export default function Financial() {
  const { showToast } = useToast()
  const [view, setView] = useState('overview') // 'overview' | 'charts'

  const [summary, setSummary] = useState(null)
  const [todaySummary, setTodaySummary] = useState(null)
  const [pending, setPending] = useState([])
  const [professionals, setProfessionals] = useState([])
  const [monthlyTrend, setMonthlyTrend] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState('')

  const [recentPayments, setRecentPayments] = useState([])
  const [recentPage, setRecentPage] = useState(1)
  const [recentTotalPages, setRecentTotalPages] = useState(1)
  const [recentLoading, setRecentLoading] = useState(true)

  const [payingAppointment, setPayingAppointment] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const { start, end } = currentMonthRange()
      const months = lastNMonths(6)

      const [summaryData, todayData, pendingData, professionalsData, ...trendData] = await Promise.all([
        getCurrentMonthSummary(),
        getTodaySummary(),
        getPendingPayments(),
        getSummaryByProfessionals(start, end),
        ...months.map((m) => getSummaryByMonth(m.year, m.month)),
      ])

      setSummary(summaryData)
      setTodaySummary(todayData)
      setPending(pendingData)
      setProfessionals(professionalsData)
      setMonthlyTrend(months.map((m, i) => ({ label: m.label, value: trendData[i]?.gross_revenue ?? 0 })))
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  const loadRecent = useCallback(async (page) => {
    setRecentLoading(true)
    try {
      const result = await getRecentPayments(page, 10)
      setRecentPayments(result.data)
      setRecentPage(result.page)
      setRecentTotalPages(result.total_pages)
    } catch (err) {
      showToast(parseApiError(err), 'danger')
    } finally {
      setRecentLoading(false)
    }
  }, [showToast])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    loadRecent(1)
  }, [loadRecent])

  const handlePaymentSaved = () => {
    showToast('Pagamento registrado com sucesso')
    load()
    loadRecent(recentPage)
  }

  const professionalsChart = professionals
    .map((p) => ({ label: p.professional_name, value: p.received }))
    .sort((a, b) => b.value - a.value)

  const paymentMethodChart = Object.entries(summary?.by_method ?? {})
    .map(([method, value]) => ({ label: PAYMENT_METHOD_LABELS[method] ?? method, value }))
    .sort((a, b) => b.value - a.value)

  const statusOthers = Math.max(
    (summary?.total_scheduled ?? 0)
      - (summary?.total_completed ?? 0)
      - (summary?.total_cancelled ?? 0)
      - (summary?.total_no_show ?? 0),
    0
  )
  const statusChart = [
    { label: 'Concluídas', value: summary?.total_completed ?? 0, color: 'var(--text-secondary)' },
    { label: 'Agendadas/Confirmadas', value: statusOthers, color: 'var(--color-info)' },
    { label: 'Canceladas', value: summary?.total_cancelled ?? 0, color: 'var(--color-danger)' },
    { label: 'Faltas', value: summary?.total_no_show ?? 0, color: 'var(--color-warning)' },
  ]

  return (
    <div className="financial-page">
      {error && (
        <Alert variant="danger" onClose={() => setError('')} dismissible>
          {error}
        </Alert>
      )}

      <div className="filter-chip-group mb-4">
        <button
          type="button"
          className={`filter-chip${view === 'overview' ? ' active' : ''}`}
          onClick={() => setView('overview')}
        >
          Visão geral
        </button>
        <button
          type="button"
          className={`filter-chip${view === 'charts' ? ' active' : ''}`}
          onClick={() => setView('charts')}
        >
          Análises
        </button>
      </div>

      {loading ? (
        <div className="text-center py-4">
          <Spinner animation="border" size="sm" />
        </div>
      ) : view === 'charts' ? (
        <div className="financial-charts-grid">
          <div className="chart-card">
            <div className="chart-card-title">Faturamento mensal</div>
            <div className="chart-card-subtitle">Receita bruta — últimos 6 meses</div>
            <VerticalBars items={monthlyTrend} valueFormatter={formatCurrencyShort} />
          </div>

          <div className="chart-card">
            <div className="chart-card-title">Recebido por profissional</div>
            <div className="chart-card-subtitle">Mês atual</div>
            {professionalsChart.length === 0 ? (
              <p className="chart-empty">Nenhuma sessão concluída neste mês.</p>
            ) : (
              <HorizontalBars items={professionalsChart} valueFormatter={formatCurrency} />
            )}
          </div>

          <div className="chart-card">
            <div className="chart-card-title">Receita por método de pagamento</div>
            <div className="chart-card-subtitle">Mês atual</div>
            {paymentMethodChart.length === 0 ? (
              <p className="chart-empty">Nenhum pagamento registrado neste mês.</p>
            ) : (
              <StackedBar items={paymentMethodChart} valueFormatter={formatCurrency} />
            )}
          </div>

          <div className="chart-card">
            <div className="chart-card-title">Situação das sessões</div>
            <div className="chart-card-subtitle">Mês atual</div>
            <HorizontalBars
              items={statusChart}
              colors={statusChart.map((s) => s.color)}
              valueFormatter={(v) => v}
            />
          </div>
        </div>
      ) : (
        <>
          {/* Resumo do mês atual + hoje */}
          <Row className="g-3 mb-4">
            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
                <div className="stat-card-label">Receita bruta (mês)</div>
                <div className="stat-card-value">{formatCurrency(summary?.gross_revenue)}</div>
              </div>
            </Col>
            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
                <div className="stat-card-label">Recebido (mês)</div>
                <div className="stat-card-value success">{formatCurrency(summary?.received)}</div>
              </div>
            </Col>
            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
                <div className="stat-card-label">Pendente (mês)</div>
                <div className="stat-card-value warning">{formatCurrency(summary?.pending)}</div>
              </div>
            </Col>
            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
                <div className="stat-card-label">Recebido hoje</div>
                <div className="stat-card-value success">{formatCurrency(todaySummary?.received)}</div>
                <div className="stat-card-desc">
                  {todaySummary?.total_completed ?? 0} sessão(ões) concluída(s)
                </div>
              </div>
            </Col>
          </Row>

          {/* Recebimentos por profissional */}
          <h6 className="mb-3 fw-bold">Recebimentos por profissional (mês atual)</h6>
          <div className="financial-table-card mb-4">
            {professionals.length === 0 ? (
              <p className="text-muted text-center py-3 mb-0">Nenhuma sessão concluída neste mês.</p>
            ) : (
              <Table hover responsive className="mb-0">
                <thead>
                  <tr>
                    <th>Profissional</th>
                    <th>Sessões concluídas</th>
                    <th>Recebido</th>
                    <th>Pendente</th>
                  </tr>
                </thead>
                <tbody>
                  {professionals.map((p) => (
                    <tr key={p.professional_id}>
                      <td>{p.professional_name}</td>
                      <td>{p.total_sessions}</td>
                      <td>{formatCurrency(p.received)}</td>
                      <td>{formatCurrency(p.pending)}</td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </div>

          {/* Pagamentos pendentes */}
          <h6 className="mb-3 fw-bold">Pagamentos pendentes</h6>
          <div className="financial-table-card mb-4">
            {pending.length === 0 ? (
              <p className="text-muted text-center py-3 mb-0">Nenhum pagamento pendente.</p>
            ) : (
              <Table hover responsive className="mb-0">
                <thead>
                  <tr>
                    <th>Data</th>
                    <th>Paciente</th>
                    <th>Procedimento</th>
                    <th>Valor</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {pending.map((apt) => (
                    <tr key={apt.id}>
                      <td>{apt.formatted_start}</td>
                      <td>{apt.patient?.name}</td>
                      <td>{apt.service?.name}</td>
                      <td>{apt.formatted_price}</td>
                      <td>
                        <Button size="sm" variant="primary" onClick={() => setPayingAppointment(apt)}>
                          Registrar pagamento
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </div>

          {/* Últimos pagamentos (paginado) */}
          <div className="d-flex align-items-center justify-content-between mb-3">
            <h6 className="fw-bold mb-0">Últimos pagamentos</h6>
            <div className="d-flex align-items-center gap-2">
              <Button
                size="sm"
                variant="outline-secondary"
                disabled={recentLoading || recentPage <= 1}
                onClick={() => loadRecent(recentPage - 1)}
              >
                ← Anterior
              </Button>
              <span className="text-muted small">
                Página {recentPage} de {recentTotalPages || 1}
              </span>
              <Button
                size="sm"
                variant="outline-secondary"
                disabled={recentLoading || recentPage >= recentTotalPages}
                onClick={() => loadRecent(recentPage + 1)}
              >
                Próxima →
              </Button>
            </div>
          </div>
          <div className="financial-table-card">
            {recentLoading ? (
              <div className="text-center py-4">
                <Spinner animation="border" size="sm" />
              </div>
            ) : recentPayments.length === 0 ? (
              <p className="text-muted text-center py-3 mb-0">Nenhum pagamento registrado ainda.</p>
            ) : (
              <Table hover responsive className="mb-0">
                <thead>
                  <tr>
                    <th>Pago em</th>
                    <th>Paciente</th>
                    <th>Profissional</th>
                    <th>Procedimento</th>
                    <th>Método</th>
                    <th>Valor</th>
                  </tr>
                </thead>
                <tbody>
                  {recentPayments.map((apt) => (
                    <tr key={apt.id}>
                      <td>{apt.payment_date ?? '—'}</td>
                      <td>{apt.patient?.name}</td>
                      <td>{apt.professional?.name}</td>
                      <td>{apt.service?.name}</td>
                      <td>{apt.payment_method ?? '—'}</td>
                      <td>{apt.formatted_price}</td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </div>
        </>
      )}

      <PaymentModal
        show={!!payingAppointment}
        appointment={payingAppointment}
        onClose={() => setPayingAppointment(null)}
        onSaved={handlePaymentSaved}
      />
    </div>
  )
}

function formatCurrency(value) {
  return (value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatCurrencyShort(value) {
  if (!value) return 'R$ 0'
  if (value >= 1000) return `R$ ${(value / 1000).toFixed(1).replace('.0', '')}k`
  return `R$ ${Math.round(value)}`
}
