import { useCallback, useEffect, useMemo, useState } from 'react'
import { Row, Col, Alert, Spinner } from 'react-bootstrap'
import { Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import {
  listAppointments,
  confirmAppointment,
  completeAppointment,
  markNoShow,
  deleteAppointment,
} from '../../api/appointments'
import { listPatients } from '../../api/users'
import { getCurrentMonthSummary, getPendingPayments, getSummaryByMonth } from '../../api/financial'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import AppointmentCard from '../Appointments/AppointmentCard'
import EditAppointmentModal from '../Appointments/EditAppointmentModal'
import ReasonModal from '../Appointments/ReasonModal'
import DeleteAppointmentModal from '../Appointments/DeleteAppointmentModal'
import AppointmentCardSkeleton from '../../components/AppointmentCardSkeleton'

// =============================================
// ÍCONES DOS STAT CARDS
// =============================================

const StatIcons = {
  Calendar: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="4" width="18" height="18" rx="2" />
      <path d="M16 2v4M8 2v4M3 10h18" />
    </svg>
  ),
  CheckCircle: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
      <polyline points="22 4 12 14.01 9 11.01" />
    </svg>
  ),
  Clock: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="10" />
      <polyline points="12 6 12 12 16 14" />
    </svg>
  ),
  Cash: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <rect x="2" y="6" width="20" height="12" rx="2" />
      <circle cx="12" cy="12" r="3" />
      <path d="M6 12h.01M18 12h.01" />
    </svg>
  ),
}

function RefreshIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" width={14} height={14}>
      <polyline points="23 4 23 10 17 10" />
      <polyline points="1 20 1 14 7 14" />
      <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
    </svg>
  )
}

// Saudação que muda com o horário (Notion/Linear fazem o mesmo)
function greetingFor(hour) {
  if (hour < 12) return 'Bom dia'
  if (hour < 18) return 'Boa tarde'
  return 'Boa noite'
}

// "Atualizado há X" — relativo a quando os dados foram carregados com sucesso
function timeAgoLabel(date, now) {
  if (!date) return ''
  const diffSec = Math.round((now - date) / 1000)
  if (diffSec < 30) return 'agora mesmo'
  if (diffSec < 60) return 'há menos de 1 min'
  const diffMin = Math.round(diffSec / 60)
  if (diffMin < 60) return `há ${diffMin} min`
  const diffH = Math.round(diffMin / 60)
  return `há ${diffH}h`
}

// Sparkline mínima embutida no card (estilo Stripe) — sem eixo/legenda,
// só a forma da tendência dos últimos meses
function Sparkline({ values }) {
  if (!values || values.length < 2) return null

  const w = 72
  const h = 22
  const max = Math.max(...values, 1)
  const min = Math.min(...values, 0)
  const range = max - min || 1

  const points = values
    .map((v, i) => {
      const x = (i / (values.length - 1)) * w
      const y = h - ((v - min) / range) * h
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')

  return (
    <svg className="stat-card-sparkline" viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none">
      <polyline points={points} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

function todayKey() {
  const d = new Date()
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

// Dias até a próxima ocorrência do aniversário (considera a virada de ano)
function daysUntilBirthday(birthdate, today) {
  const [, m, d] = birthdate.split('-').map(Number)
  let next = new Date(today.getFullYear(), m - 1, d)
  if (next < today) next = new Date(today.getFullYear() + 1, m - 1, d)
  return Math.round((next - today) / (1000 * 60 * 60 * 24))
}

// Últimos N meses (incluindo o atual), mais antigo primeiro — pra sparkline
function lastNMonths(n) {
  const now = new Date()
  const months = []
  for (let i = n - 1; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    months.push({ year: d.getFullYear(), month: d.getMonth() + 1 })
  }
  return months
}

// Selo de horário relativo pro próximo compromisso (estilo Apple/Google
// Calendar) — "Agora" se já começou e ainda não terminou, senão "em Xmin/h"
function relativeTimeLabel(apt, now) {
  const start = new Date(apt.start_time.replace(' ', 'T'))
  const end = new Date(apt.end_time.replace(' ', 'T'))

  if (now >= start && now < end) return 'Agora'

  const diffMin = Math.round((start - now) / 60000)
  if (diffMin <= 0) return null

  if (diffMin < 60) return `em ${diffMin} min`

  const h = Math.floor(diffMin / 60)
  const m = diffMin % 60
  return m > 0 ? `em ${h}h${m}min` : `em ${h}h`
}

export default function Dashboard() {
  const { user, isAdmin } = useAuth()
  const { showToast } = useToast()

  const [todayAppointments, setTodayAppointments] = useState([])
  const [summary, setSummary]           = useState(null)
  const [revenueTrend, setRevenueTrend] = useState([])
  const [pendingCount, setPendingCount] = useState(0)
  const [birthdaysToday, setBirthdaysToday] = useState([])
  const [birthdaysSoon, setBirthdaysSoon] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState('')
  const [lastUpdated, setLastUpdated] = useState(null)

  const [expandedId, setExpandedId] = useState(null)
  const [editingAppointment, setEditingAppointment] = useState(null)
  const [reasonModal, setReasonModal] = useState(null)
  const [deletingAppointment, setDeletingAppointment] = useState(null)

  // "Relógio" só pra recalcular o selo de horário relativo periodicamente
  const [now, setNow] = useState(() => new Date())
  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 30_000)
    return () => clearInterval(id)
  }, [])

  const load = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const today = todayKey()
      const params = isAdmin() ? { start: today, end: today } : {}
      const appointments = await listAppointments(params)

      setTodayAppointments(
        appointments
          .filter((apt) => apt.start_time.slice(0, 10) === today)
          .sort((a, b) => a.start_time.localeCompare(b.start_time))
      )

      if (isAdmin()) {
        const months = lastNMonths(6)
        const [summaryData, pendingData, patientsData, ...trendData] = await Promise.all([
          getCurrentMonthSummary(),
          getPendingPayments(),
          listPatients(true),
          ...months.map((m) => getSummaryByMonth(m.year, m.month)),
        ])
        setSummary(summaryData)
        setPendingCount(pendingData.length)
        setRevenueTrend(trendData.map((t) => t?.gross_revenue ?? 0))

        const now = new Date()
        const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate())
        const withBirthdate = patientsData.filter((p) => p.birthdate)

        setBirthdaysToday(withBirthdate.filter((p) => daysUntilBirthday(p.birthdate, todayStart) === 0))
        setBirthdaysSoon(
          withBirthdate
            .map((p) => ({ ...p, daysUntil: daysUntilBirthday(p.birthdate, todayStart) }))
            .filter((p) => p.daysUntil > 0 && p.daysUntil <= 7)
            .sort((a, b) => a.daysUntil - b.daysUntil)
        )
      }

      setLastUpdated(new Date())
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [isAdmin])

  useEffect(() => {
    load()
  }, [load])

  const confirmedToday = useMemo(
    () => todayAppointments.filter((apt) => apt.status === 'confirmed').length,
    [todayAppointments]
  )

  // Agrupa a agenda do dia em "em aberto" (ainda pede atenção) e "concluídos"
  // (já aconteceram) — estilo Linear, em vez de uma lista única misturada
  const openToday = useMemo(
    () => todayAppointments.filter((apt) => apt.status === 'scheduled' || apt.status === 'confirmed'),
    [todayAppointments]
  )
  const doneToday = useMemo(
    () => todayAppointments.filter((apt) => apt.status !== 'scheduled' && apt.status !== 'confirmed'),
    [todayAppointments]
  )
  const nextAppointmentId = openToday[0]?.id ?? null

  const roleLabel = { admin: 'Administrador', professional: 'Profissional', patient: 'Paciente' }[user?.role] ?? user?.role

  // ── Ações de status — mesmo padrão da tela de Agendamentos, reaproveitando
  // os mesmos componentes (AppointmentCard/modais) pra agir sem sair do Dashboard
  const runAction = async (fn, successMessage) => {
    setError('')
    try {
      await fn()
      if (successMessage) showToast(successMessage)
      await load()
    } catch (err) {
      showToast(parseApiError(err), 'danger')
    }
  }

  const handleConfirm  = (apt) => runAction(() => confirmAppointment(apt.id), 'Agendamento confirmado')
  const handleComplete = (apt) => runAction(() => completeAppointment(apt.id), 'Sessão marcada como realizada')

  const handleNoShow = (apt) => {
    setReasonModal({
      title: 'Marcar falta',
      required: false,
      onConfirm: (reason) => runAction(() => markNoShow(apt.id, reason), 'Marcado como falta'),
    })
  }

  const handleDelete = (apt) => setDeletingAppointment(apt)

  const confirmDelete = (scope) => runAction(
    () => deleteAppointment(deletingAppointment.id, scope),
    scope === 'all' ? 'Recorrência excluída' : scope === 'future' ? 'Agendamentos excluídos' : 'Agendamento excluído'
  )

  const toggleExpand = (id) => setExpandedId((current) => (current === id ? null : id))
  const collapseAndRun = (fn) => (apt) => { setExpandedId(null); fn(apt) }

  const handleEditCard = collapseAndRun(setEditingAppointment)
  const handleConfirmCard = collapseAndRun(handleConfirm)
  const handleCompleteCard = collapseAndRun(handleComplete)
  const handleNoShowCard = collapseAndRun(handleNoShow)
  const handleDeleteCard = collapseAndRun(handleDelete)

  const handleEdited = () => {
    showToast('Agendamento atualizado com sucesso')
    load()
  }

  const renderCard = (apt) => (
    <AppointmentCard
      key={apt.id}
      appointment={apt}
      isExpanded={expandedId === apt.id}
      onToggle={toggleExpand}
      onEdit={handleEditCard}
      onConfirm={handleConfirmCard}
      onComplete={handleCompleteCard}
      onNoShow={handleNoShowCard}
      onDelete={isAdmin() ? handleDeleteCard : undefined}
      timeBadge={apt.id === nextAppointmentId ? relativeTimeLabel(apt, now) : null}
    />
  )

  return (
    <>
      <Row className="mb-4 align-items-center">
        <Col>
          <p className="text-muted mb-0">
            {greetingFor(now.getHours())}, {user?.name} —{' '}
            <span className="badge bg-primary">{roleLabel}</span>
          </p>
        </Col>
        <Col xs="auto">
          <button
            type="button"
            className="dashboard-refresh-btn"
            onClick={load}
            disabled={loading}
            title="Atualizar"
          >
            <span className={loading ? 'is-spinning' : ''}><RefreshIcon /></span>
            {loading ? 'Atualizando...' : `Atualizado ${timeAgoLabel(lastUpdated, now)}`}
          </button>
        </Col>
      </Row>

      {error && (
        <Alert variant="danger" onClose={() => setError('')} dismissible>
          {error}
        </Alert>
      )}

      {!loading && (birthdaysToday.length > 0 || birthdaysSoon.length > 0) && (
        <div className="birthday-banner mb-4">
          {birthdaysToday.length > 0 && (
            <div>
              🎂{' '}
              {birthdaysToday.length === 1
                ? `${birthdaysToday[0].name} faz aniversário hoje!`
                : `${birthdaysToday.length} pacientes fazem aniversário hoje: ${birthdaysToday.map((p) => p.name).join(', ')}`}
            </div>
          )}
          {birthdaysSoon.length > 0 && (
            <div className="birthday-banner-soon">
              🎂 Aniversários nos próximos 7 dias: {birthdaysSoon.map((p) => `${p.name} (em ${p.daysUntil} dia${p.daysUntil > 1 ? 's' : ''})`).join(', ')}
            </div>
          )}
        </div>
      )}

      <Row className="g-3 mb-4">
        <Col xs={12} sm={6} lg={isAdmin() ? 3 : 4}>
          <Link to="/appointments" className="stat-card-link">
            <div className="stat-card">
              <div className="stat-card-icon stat-card-icon-info"><StatIcons.Calendar /></div>
              <div className="stat-card-label">Agendamentos hoje</div>
              <div className="stat-card-value">
                {loading ? <Spinner animation="border" size="sm" /> : todayAppointments.length}
              </div>
            </div>
          </Link>
        </Col>

        <Col xs={12} sm={6} lg={isAdmin() ? 3 : 4}>
          <Link to="/appointments" className="stat-card-link">
            <div className="stat-card">
              <div className="stat-card-icon stat-card-icon-success"><StatIcons.CheckCircle /></div>
              <div className="stat-card-label">Confirmados hoje</div>
              <div className="stat-card-value success">
                {loading ? <Spinner animation="border" size="sm" /> : confirmedToday}
              </div>
            </div>
          </Link>
        </Col>

        {isAdmin() && (
          <>
            <Col xs={12} sm={6} lg={3}>
              <Link to="/financial" className="stat-card-link">
                <div className="stat-card">
                  <div className="stat-card-icon stat-card-icon-warning"><StatIcons.Clock /></div>
                  <div className="stat-card-label">Pagamentos pendentes</div>
                  <div className="stat-card-value warning">
                    {loading ? <Spinner animation="border" size="sm" /> : pendingCount}
                  </div>
                </div>
              </Link>
            </Col>

            <Col xs={12} sm={6} lg={3}>
              <Link to="/financial" className="stat-card-link">
                <div className="stat-card">
                  <div className="stat-card-icon stat-card-icon-brand"><StatIcons.Cash /></div>
                  <div className="stat-card-label">Receita do mês</div>
                  <div className="stat-card-value">
                    {loading ? (
                      <Spinner animation="border" size="sm" />
                    ) : (
                      (summary?.gross_revenue ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
                    )}
                  </div>
                  {!loading && <Sparkline values={revenueTrend} />}
                </div>
              </Link>
            </Col>
          </>
        )}
      </Row>

      <h6 className="fw-bold mb-3">Agenda de hoje</h6>
      <div className="patients-table-card">
        {loading ? (
          <div className="appointments-day-list p-3">
            <AppointmentCardSkeleton />
            <AppointmentCardSkeleton />
          </div>
        ) : todayAppointments.length === 0 ? (
          <p className="text-muted text-center py-3 mb-0">Nenhum agendamento para hoje.</p>
        ) : (
          <div className="appointments-day-list p-3">
            {openToday.length > 0 && doneToday.length > 0 && (
              <div className="dashboard-agenda-subheader">Em aberto</div>
            )}
            {openToday.map(renderCard)}

            {doneToday.length > 0 && (
              <>
                {openToday.length > 0 && (
                  <div className="dashboard-agenda-subheader">Concluídos hoje</div>
                )}
                {doneToday.map(renderCard)}
              </>
            )}
          </div>
        )}
      </div>

      <EditAppointmentModal
        show={!!editingAppointment}
        appointment={editingAppointment}
        onClose={() => setEditingAppointment(null)}
        onSaved={handleEdited}
      />

      <ReasonModal
        show={!!reasonModal}
        title={reasonModal?.title}
        required={reasonModal?.required}
        onClose={() => setReasonModal(null)}
        onConfirm={(reason) => reasonModal?.onConfirm(reason)}
      />

      <DeleteAppointmentModal
        show={!!deletingAppointment}
        appointment={deletingAppointment}
        onClose={() => setDeletingAppointment(null)}
        onConfirm={confirmDelete}
      />
    </>
  )
}
