import { useCallback, useEffect, useMemo, useState } from 'react'
import { Button, Alert } from 'react-bootstrap'
import { useAuth } from '../../context/AuthContext'
import {
  listAppointments,
  confirmAppointment,
  completeAppointment,
  markNoShow,
  deleteAppointment,
} from '../../api/appointments'
import { listProfessionals } from '../../api/users'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import { STATUS_META } from './statusMeta'
import MonthCalendar from './MonthCalendar'
import AppointmentCard from './AppointmentCard'
import WeekCalendar, { mondayOf } from './WeekCalendar'
import AppointmentCardSkeleton from '../../components/AppointmentCardSkeleton'
import AppointmentFormModal from './AppointmentFormModal'
import EditAppointmentModal from './EditAppointmentModal'
import ReasonModal from './ReasonModal'
import DeleteAppointmentModal from './DeleteAppointmentModal'

function toDateKey(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function formatSelectedDate(dateKey) {
  const [y, m, d] = dateKey.split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('pt-BR', {
    weekday: 'long',
    day:     'numeric',
    month:   'long',
  })
}

function PlusIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2.2} strokeLinecap="round" strokeLinejoin="round" width={16} height={16}>
      <line x1="12" y1="5" x2="12" y2="19" />
      <line x1="5" y1="12" x2="19" y2="12" />
    </svg>
  )
}

export default function Appointments() {
  const { isProfessional, isAdmin } = useAuth()
  const { showToast } = useToast()
  const today = useMemo(() => new Date(), [])

  const [year, setYear]   = useState(today.getFullYear())
  const [month, setMonth] = useState(today.getMonth())
  const [selectedDate, setSelectedDate] = useState(toDateKey(today))

  const [appointments, setAppointments] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState('')

  const [showCreateModal, setShowCreateModal] = useState(false)
  const [editingAppointment, setEditingAppointment] = useState(null)
  const [reasonModal, setReasonModal] = useState(null) // { title, required, action, appointment }
  const [deletingAppointment, setDeletingAppointment] = useState(null)
  const [expandedId, setExpandedId] = useState(null) // id do único card expandido (Lista ou Semanal)

  const [professionals, setProfessionals] = useState([])
  const [filterProfessionalId, setFilterProfessionalId] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [view, setView] = useState('calendar') // 'calendar' | 'week'
  const [weekOffset, setWeekOffset] = useState(0)

  useEffect(() => {
    if (isProfessional()) return
    listProfessionals().then(setProfessionals).catch(() => {})
  }, [isProfessional])

  const loadAppointments = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      let params = {}

      // Admin filtra pelo intervalo visível na visão atual; profissional sempre vê os seus
      if (!isProfessional()) {
        let rangeStart, rangeEnd

        if (view === 'week') {
          rangeStart = mondayOf(weekOffset)
          rangeEnd = new Date(rangeStart)
          rangeEnd.setDate(rangeStart.getDate() + 6)
        } else {
          rangeStart = new Date(year, month, 1)
          rangeStart.setDate(rangeStart.getDate() - rangeStart.getDay())
          rangeEnd = new Date(rangeStart)
          rangeEnd.setDate(rangeStart.getDate() + 41)
        }

        params = {
          start: toDateKey(rangeStart),
          end:   toDateKey(rangeEnd),
        }
      }

      const data = await listAppointments(params)
      setAppointments(data)
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [year, month, view, weekOffset, isProfessional])

  useEffect(() => {
    loadAppointments()
  }, [loadAppointments])

  const filteredAppointments = useMemo(() => {
    return appointments.filter((apt) => {
      if (filterProfessionalId && String(apt.professional?.id) !== filterProfessionalId) return false
      if (filterStatus && apt.status !== filterStatus) return false
      return true
    })
  }, [appointments, filterProfessionalId, filterStatus])

  // Contagem por dia E por status, para colorir os indicadores do calendário
  const statusCountsByDate = useMemo(() => {
    const counts = {}
    for (const apt of filteredAppointments) {
      const key = apt.start_time.slice(0, 10)
      counts[key] ??= {}
      counts[key][apt.status] = (counts[key][apt.status] ?? 0) + 1
    }
    return counts
  }, [filteredAppointments])

  const dayAppointments = useMemo(() => {
    return filteredAppointments
      .filter((apt) => apt.start_time.slice(0, 10) === selectedDate)
      .sort((a, b) => a.start_time.localeCompare(b.start_time))
  }, [filteredAppointments, selectedDate])

  const goToMonth = (offset) => {
    const next = new Date(year, month + offset, 1)
    setYear(next.getFullYear())
    setMonth(next.getMonth())
    setExpandedId(null)
  }

  const goToToday = () => {
    setYear(today.getFullYear())
    setMonth(today.getMonth())
    setSelectedDate(toDateKey(today))
    setExpandedId(null)
  }

  // ── Ações de status ────────────────────────────────────

  const runAction = async (fn, successMessage) => {
    setError('')
    try {
      await fn()
      if (successMessage) showToast(successMessage)
      await loadAppointments()
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

  // ── Expand/collapse in-place — só um card expandido por vez, nas duas
  // visualizações (Lista e Semanal usam o mesmo estado) ─────────────────
  const toggleExpand = (id) => setExpandedId((current) => (current === id ? null : id))
  const collapseAndRun = (fn) => (apt) => { setExpandedId(null); fn(apt) }

  const handleEditCard = collapseAndRun(setEditingAppointment)
  const handleConfirmCard = collapseAndRun(handleConfirm)
  const handleCompleteCard = collapseAndRun(handleComplete)
  const handleNoShowCard = collapseAndRun(handleNoShow)
  const handleDeleteCard = collapseAndRun(handleDelete)

  const handleCreated = () => {
    showToast('Agendamento criado com sucesso')
    loadAppointments()
  }

  const handleEdited = () => {
    showToast('Agendamento atualizado com sucesso')
    loadAppointments()
  }

  return (
    <div className="appointments-page">
      <div className="appointments-toolbar">
        <div className="appointments-filters">
          {!isProfessional() && (
            <div className="filter-group">
              <span className="filter-group-label">Profissionais</span>
              <div className="filter-chip-group">
                <button
                  type="button"
                  className={`filter-chip${filterProfessionalId === '' ? ' active' : ''}`}
                  onClick={() => setFilterProfessionalId('')}
                >
                  Todos
                </button>
                {professionals.map((p) => (
                  <button
                    type="button"
                    key={p.id}
                    className={`filter-chip${filterProfessionalId === String(p.id) ? ' active' : ''}`}
                    onClick={() => setFilterProfessionalId(String(p.id))}
                  >
                    {p.name}
                  </button>
                ))}
              </div>
            </div>
          )}

          <div className="filter-group">
            <span className="filter-group-label">Status</span>
            <div className="filter-chip-group">
              <button
                type="button"
                className={`filter-chip${filterStatus === '' ? ' active' : ''}`}
                onClick={() => setFilterStatus('')}
              >
                Todos
              </button>
              {Object.entries(STATUS_META).map(([value, meta]) => (
                <button
                  type="button"
                  key={value}
                  className={`filter-chip${filterStatus === value ? ' active' : ''}`}
                  onClick={() => setFilterStatus(value)}
                >
                  <span className={`filter-chip-dot ${meta.className}`} />
                  {meta.label}
                </button>
              ))}
            </div>
          </div>
        </div>

        <div className="appointments-toolbar-actions">
          <div className={`view-switch${view === 'week' ? ' is-week' : ''}`}>
            <span className="view-switch-thumb" />
            <button
              type="button"
              className={`view-switch-option${view === 'calendar' ? ' active' : ''}`}
              onClick={() => { setView('calendar'); setExpandedId(null) }}
            >
              Agenda
            </button>
            <button
              type="button"
              className={`view-switch-option${view === 'week' ? ' active' : ''}`}
              onClick={() => { setView('week'); setExpandedId(null) }}
            >
              Semana
            </button>
          </div>

          <Button variant="primary" className="appointments-new-btn" onClick={() => setShowCreateModal(true)}>
            <PlusIcon />
            Novo agendamento
          </Button>
        </div>
      </div>

      {error && (
        <Alert variant="danger" onClose={() => setError('')} dismissible>
          {error}
        </Alert>
      )}

      {view === 'week' && (
        <WeekCalendar
          appointments={filteredAppointments}
          weekOffset={weekOffset}
          onPrevWeek={() => { setWeekOffset((v) => v - 1); setExpandedId(null) }}
          onNextWeek={() => { setWeekOffset((v) => v + 1); setExpandedId(null) }}
          onToday={() => { setWeekOffset(0); setExpandedId(null) }}
          expandedId={expandedId}
          onToggle={toggleExpand}
          onEdit={handleEditCard}
          onConfirm={handleConfirmCard}
          onComplete={handleCompleteCard}
          onNoShow={handleNoShowCard}
          onDelete={isAdmin() ? handleDeleteCard : undefined}
        />
      )}

      {view === 'calendar' && (
      <div className="appointments-layout">
        <MonthCalendar
          year={year}
          month={month}
          statusCountsByDate={statusCountsByDate}
          selectedDate={selectedDate}
          onSelectDate={(date) => { setSelectedDate(date); setExpandedId(null) }}
          onPrevMonth={() => goToMonth(-1)}
          onNextMonth={() => goToMonth(1)}
          onToday={goToToday}
        />

        <div className="appointments-day-panel">
          <h6 className="appointments-day-title">
            {formatSelectedDate(selectedDate)}
          </h6>

          {loading ? (
            <div className="appointments-day-list">
              <AppointmentCardSkeleton />
              <AppointmentCardSkeleton />
              <AppointmentCardSkeleton />
            </div>
          ) : dayAppointments.length === 0 ? (
            <div className="text-center py-3">
              <p className="text-muted mb-3">Nenhum agendamento neste dia.</p>
              <Button variant="outline-primary" size="sm" onClick={() => setShowCreateModal(true)}>
                + Criar agendamento
              </Button>
            </div>
          ) : (
            <div className="appointments-day-list">
              {dayAppointments.map((apt) => (
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
                />
              ))}
            </div>
          )}
        </div>
      </div>
      )}

      <AppointmentFormModal
        show={showCreateModal}
        defaultDate={selectedDate}
        onClose={() => setShowCreateModal(false)}
        onCreated={handleCreated}
      />

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
    </div>
  )
}
