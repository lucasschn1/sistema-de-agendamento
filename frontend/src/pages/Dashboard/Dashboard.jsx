import { useCallback, useEffect, useMemo, useState } from 'react'
import { Row, Col, Alert, Spinner } from 'react-bootstrap'
import { useAuth } from '../../context/AuthContext'
import { listAppointments } from '../../api/appointments'
import { listPatients } from '../../api/users'
import { getCurrentMonthSummary, getPendingPayments } from '../../api/financial'
import { parseApiError } from '../../utils/apiError'
import { statusLabel, statusClassName } from '../Appointments/statusMeta'
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

function todayKey() {
  const d = new Date()
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export default function Dashboard() {
  const { user, isAdmin } = useAuth()

  const [todayAppointments, setTodayAppointments] = useState([])
  const [summary, setSummary]           = useState(null)
  const [pendingCount, setPendingCount] = useState(0)
  const [birthdaysToday, setBirthdaysToday] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState('')

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
        const [summaryData, pendingData, patientsData] = await Promise.all([
          getCurrentMonthSummary(),
          getPendingPayments(),
          listPatients(true),
        ])
        setSummary(summaryData)
        setPendingCount(pendingData.length)

        const todayMonthDay = today.slice(5) // 'MM-DD'
        setBirthdaysToday(
          patientsData.filter((p) => p.birthdate && p.birthdate.slice(5) === todayMonthDay)
        )
      }
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

  const roleLabel = { admin: 'Administrador', professional: 'Profissional', patient: 'Paciente' }[user?.role] ?? user?.role

  return (
    <>
      <Row className="mb-4">
        <Col>
          <p className="text-muted">
            Bem-vindo, {user?.name} —{' '}
            <span className="badge bg-primary">{roleLabel}</span>
          </p>
        </Col>
      </Row>

      {error && (
        <Alert variant="danger" onClose={() => setError('')} dismissible>
          {error}
        </Alert>
      )}

      {!loading && birthdaysToday.length > 0 && (
        <div className="birthday-banner mb-4">
          🎂{' '}
          {birthdaysToday.length === 1
            ? `${birthdaysToday[0].name} faz aniversário hoje!`
            : `${birthdaysToday.length} pacientes fazem aniversário hoje: ${birthdaysToday.map((p) => p.name).join(', ')}`}
        </div>
      )}

      <Row className="g-3 mb-4">
        <Col xs={12} sm={6} lg={isAdmin() ? 3 : 4}>
          <div className="stat-card">
            <div className="stat-card-icon stat-card-icon-info"><StatIcons.Calendar /></div>
            <div className="stat-card-label">Agendamentos hoje</div>
            <div className="stat-card-value">
              {loading ? <Spinner animation="border" size="sm" /> : todayAppointments.length}
            </div>
          </div>
        </Col>

        <Col xs={12} sm={6} lg={isAdmin() ? 3 : 4}>
          <div className="stat-card">
            <div className="stat-card-icon stat-card-icon-success"><StatIcons.CheckCircle /></div>
            <div className="stat-card-label">Confirmados hoje</div>
            <div className="stat-card-value success">
              {loading ? <Spinner animation="border" size="sm" /> : confirmedToday}
            </div>
          </div>
        </Col>

        {isAdmin() && (
          <>
            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
                <div className="stat-card-icon stat-card-icon-warning"><StatIcons.Clock /></div>
                <div className="stat-card-label">Pagamentos pendentes</div>
                <div className="stat-card-value warning">
                  {loading ? <Spinner animation="border" size="sm" /> : pendingCount}
                </div>
              </div>
            </Col>

            <Col xs={12} sm={6} lg={3}>
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
              </div>
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
            {todayAppointments.map((apt) => (
              <div className={`appointment-card ${statusClassName(apt.status)}`} key={apt.id}>
                <div className="appointment-card-time">{apt.formatted_start}</div>
                <div className="appointment-card-info">
                  <p className="appointment-card-patient">{apt.patient?.name}</p>
                  <p className="appointment-card-detail">
                    {apt.service?.name} · {apt.professional?.name}
                  </p>
                </div>
                <span className={`appointment-card-status ${statusClassName(apt.status)}`}>
                  {statusLabel(apt.status)}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </>
  )
}
