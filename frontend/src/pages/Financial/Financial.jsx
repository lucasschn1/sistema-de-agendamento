import { useCallback, useEffect, useState } from 'react'
import { Table, Alert, Spinner } from 'react-bootstrap'
import {
  getCurrentMonthSummary,
  getPendingPayments,
  getSummaryByProfessionals,
  getSummaryByMonth,
  getHistorySummary,
} from '../../api/financial'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import HorizontalBars from '../../components/charts/HorizontalBars'
import VerticalBars from '../../components/charts/VerticalBars'
import StackedBar from '../../components/charts/StackedBar'
import PaymentModal from './PaymentModal'
import PaymentFlowWidget from './PaymentFlowWidget'
import PendingSessionsTable from './PendingSessionsTable'
import PaymentHistorySection from './PaymentHistorySection'

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
  const [section, setSection] = useState('resumo') // 'resumo' | 'aguardando' | 'historico'

  const [summary, setSummary] = useState(null)
  const [monthTicket, setMonthTicket] = useState(null)
  const [pending, setPending] = useState([])
  const [professionals, setProfessionals] = useState([])
  const [monthlyTrend, setMonthlyTrend] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const [payingAppointment, setPayingAppointment] = useState(null)
  const [historyRefreshKey, setHistoryRefreshKey] = useState(0)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const { start, end } = currentMonthRange()
      const months = lastNMonths(6)
      const now = new Date()

      const [summaryData, pendingData, professionalsData, ticketData, ...trendData] = await Promise.all([
        getCurrentMonthSummary(),
        getPendingPayments(),
        getSummaryByProfessionals(start, end),
        getHistorySummary({ year: now.getFullYear(), month: now.getMonth() + 1 }),
        ...months.map((m) => getSummaryByMonth(m.year, m.month)),
      ])

      setSummary(summaryData)
      setPending(pendingData)
      setProfessionals(professionalsData)
      setMonthTicket(ticketData)
      setMonthlyTrend(months.map((m, i) => ({ label: m.label, value: trendData[i]?.gross_revenue ?? 0 })))
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const refreshAll = useCallback(() => {
    load()
    setHistoryRefreshKey((k) => k + 1)
  }, [load])

  const handlePaymentSaved = () => {
    showToast('Pagamento registrado com sucesso')
    refreshAll()
  }

  // Soma exatamente os mesmos agendamentos exibidos na tabela "Sessões
  // aguardando registro" — garante que o widget de fluxo financeiro nunca
  // divirja do total realmente mostrado nessa tabela (mesma fonte, sem query
  // separada)
  const pendingTotal = pending.reduce((sum, apt) => sum + Number(apt.price ?? 0), 0)

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
          className={`filter-chip${section === 'resumo' ? ' active' : ''}`}
          onClick={() => setSection('resumo')}
        >
          Resumo Financeiro
        </button>
        <button
          type="button"
          className={`filter-chip${section === 'aguardando' ? ' active' : ''}`}
          onClick={() => setSection('aguardando')}
        >
          Sessões aguardando registro{pending.length > 0 ? ` (${pending.length})` : ''}
        </button>
        <button
          type="button"
          className={`filter-chip${section === 'historico' ? ' active' : ''}`}
          onClick={() => setSection('historico')}
        >
          Histórico de Pagamentos
        </button>
      </div>

      {loading ? (
        <div className="text-center py-4">
          <Spinner animation="border" size="sm" />
        </div>
      ) : section === 'resumo' ? (
        <>
          <div className="financial-kpi-grid mb-4">
            <div className="stat-card">
              <div className="stat-card-label">Receita do mês</div>
              <div className="stat-card-value">{formatCurrency(summary?.gross_revenue)}</div>
            </div>
            <div className="stat-card">
              <div className="stat-card-label">Receita registrada hoje</div>
              <div className="stat-card-value success">{formatCurrency(summary?.received_today)}</div>
            </div>
            <div className="stat-card">
              <div className="stat-card-label">Total recebido</div>
              <div className="stat-card-value success">{formatCurrency(summary?.all_time_received)}</div>
            </div>
            <div className="stat-card">
              <div className="stat-card-label">Sessões aguardando registro</div>
              <div className="stat-card-value warning">{pending.length}</div>
            </div>
            <div className="stat-card">
              <div className="stat-card-label">Ticket médio (mês)</div>
              <div className="stat-card-value">{formatCurrency(monthTicket?.average_ticket)}</div>
            </div>
            <div className="stat-card">
              <div className="stat-card-label">Profissionais com recebimento</div>
              <div className="stat-card-value">{professionals.length}</div>
            </div>
          </div>

          <PaymentFlowWidget
            previsto={summary?.gross_revenue}
            recebido={summary?.received}
            aguardando={pendingTotal}
          />

          <h6 className="mb-3 fw-bold mt-4">Recebimentos por profissional (mês atual)</h6>
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
                    <th>Aguardando registro</th>
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
        </>
      ) : section === 'aguardando' ? (
        <>
          <h6 className="mb-3 fw-bold">Sessões aguardando registro de pagamento</h6>
          <p className="text-muted small mb-3">
            Atendimentos já realizados cujo pagamento ainda não foi registrado — não são cobranças vencidas.
          </p>
          <PendingSessionsTable sessions={pending} onRegisterPayment={setPayingAppointment} />
        </>
      ) : (
        <>
          <h6 className="mb-3 fw-bold">Histórico de Pagamentos</h6>
          <PaymentHistorySection key={historyRefreshKey} onDataChanged={refreshAll} />
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
