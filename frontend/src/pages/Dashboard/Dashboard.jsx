import { useCallback, useEffect, useMemo, useState } from 'react'
import { Row, Col, Alert, Spinner } from 'react-bootstrap'
import { useAuth } from '../../context/AuthContext'
import { listAppointments } from '../../api/appointments'
import { getCurrentMonthSummary, getPendingPayments } from '../../api/financial'
import { parseApiError } from '../../utils/apiError'
import { statusLabel, statusClassName } from '../Appointments/statusMeta'

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
        const [summaryData, pendingData] = await Promise.all([
          getCurrentMonthSummary(),
          getPendingPayments(),
        ])
        setSummary(summaryData)
        setPendingCount(pendingData.length)
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

      <Row className="g-3 mb-4">
        <Col xs={12} sm={6} lg={isAdmin() ? 3 : 4}>
          <div className="stat-card">
            <div className="stat-card-label">Agendamentos hoje</div>
            <div className="stat-card-value">
              {loading ? <Spinner animation="border" size="sm" /> : todayAppointments.length}
            </div>
          </div>
        </Col>

        <Col xs={12} sm={6} lg={isAdmin() ? 3 : 4}>
          <div className="stat-card">
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
                <div className="stat-card-label">Pagamentos pendentes</div>
                <div className="stat-card-value warning">
                  {loading ? <Spinner animation="border" size="sm" /> : pendingCount}
                </div>
              </div>
            </Col>

            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
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
          <div className="text-center py-4">
            <Spinner animation="border" size="sm" />
          </div>
        ) : todayAppointments.length === 0 ? (
          <p className="text-muted text-center py-3 mb-0">Nenhum agendamento para hoje.</p>
        ) : (
          <div className="appointments-day-list p-3">
            {todayAppointments.map((apt) => (
              <div className="appointment-card" key={apt.id}>
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
