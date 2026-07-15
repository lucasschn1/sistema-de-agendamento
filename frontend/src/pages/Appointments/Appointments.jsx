import { useCallback, useEffect, useMemo, useState } from 'react'
import { Button, Alert, Table } from 'react-bootstrap'
import { useAuth } from '../../context/AuthContext'
import {
  listAppointments,
  confirmAppointment,
  completeAppointment,
  cancelAppointment,
  markNoShow,
  cancelRecurrence,
  deleteAppointment,
} from '../../api/appointments'
import { listProfessionals } from '../../api/users'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import { STATUS_META } from './statusMeta'
import MonthCalendar from './MonthCalendar'
import AppointmentCard, { TrashIcon } from './AppointmentCard'
import WeekCalendar, { mondayOf } from './WeekCalendar'
import AppointmentCardSkeleton from '../../components/AppointmentCardSkeleton'
import AppointmentFormModal from './AppointmentFormModal'
import EditAppointmentModal from './EditAppointmentModal'
import ReasonModal from './ReasonModal'
import ConfirmModal from '../../components/ConfirmModal'

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
  const [view, setView] = useState('calendar') // 'calendar' | 'week' | 'cancelled'
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

  // Cancelados não aparecem no calendário (poluição visual) — têm sua própria aba
  const filteredAppointments = useMemo(() => {
    return appointments.filter((apt) => {
      if (apt.status === 'cancelled') return false
      if (filterProfessionalId && String(apt.professional?.id) !== filterProfessionalId) return false
      if (filterStatus && apt.status !== filterStatus) return false
      return true
    })
  }, [appointments, filterProfessionalId, filterStatus])

  const cancelledAppointments = useMemo(() => {
    return appointments
      .filter((apt) => {
        if (apt.status !== 'cancelled') return false
        if (filterProfessionalId && String(apt.professional?.id) !== filterProfessionalId) return false
        return true
      })
      .sort((a, b) => b.start_time.localeCompare(a.start_time))
  }, [appointments, filterProfessionalId])

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

  const handleCancel = (apt) => {
    setReasonModal({
      title: 'Cancelar agendamento',
      required: true,
      onConfirm: (reason) => runAction(() => cancelAppointment(apt.id, reason), 'Agendamento cancelado'),
    })
  }

  const handleNoShow = (apt) => {
    setReasonModal({
      title: 'Marcar falta',
      required: false,
      onConfirm: (reason) => runAction(() => markNoShow(apt.id, reason), 'Marcado como falta'),
    })
  }

  const handleCancelRecurrence = (apt) => {
    setReasonModal({
      title: 'Cancelar recorrência (sessões futuras)',
      required: true,
      onConfirm: (reason) => runAction(
        () => cancelRecurrence(apt.recurrence_group_id, reason),
        'Recorrência cancelada'
      ),
    })
  }

  const handleDelete = (apt) => setDeletingAppointment(apt)

  const confirmDelete = () =>
    runAction(() => deleteAppointment(deletingAppointment.id), 'Agendamento excluído')

  // ── Expand/collapse in-place — só um card expandido por vez, nas duas
  // visualizações (Lista e Semanal usam o mesmo estado) ─────────────────
  const toggleExpand = (id) => setExpandedId((current) => (current === id ? null : id))
  const collapseAndRun = (fn) => (apt) => { setExpandedId(null); fn(apt) }

  const handleEditCard = collapseAndRun(setEditingAppointment)
  const handleConfirmCard = collapseAndRun(handleConfirm)
  const handleCompleteCard = collapseAndRun(handleComplete)
  const handleCancelCard = collapseAndRun(handleCancel)
  const handleNoShowCard = collapseAndRun(handleNoShow)
  const handleCancelRecurrenceCard = collapseAndRun(handleCancelRecurrence)
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
            <div className="filter-chip-group">
              <button
                type="button"
                className={`filter-chip${filterProfessionalId === '' ? ' active' : ''}`}
                onClick={() => setFilterProfessionalId('')}
              >
                Todos os profissionais
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
          )}

          <div className="filter-chip-group">
            <button
              type="button"
              className={`filter-chip${filterStatus === '' ? ' active' : ''}`}
              onClick={() => setFilterStatus('')}
            >
              Todos os status
            </button>
            {Object.entries(STATUS_META)
              .filter(([value]) => value !== 'cancelled')
              .map(([value, meta]) => (
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

        <Button variant="primary" onClick={() => setShowCreateModal(true)}>
          + Novo agendamento
        </Button>
      </div>

      {error && (
        <Alert variant="danger" onClose={() => setError('')} dismissible>
          {error}
        </Alert>
      )}

      <div className="filter-chip-group mb-3">
        <button
          type="button"
          className={`filter-chip${view === 'calendar' ? ' active' : ''}`}
          onClick={() => { setView('calendar'); setExpandedId(null) }}
        >
          Agenda
        </button>
        <button
          type="button"
          className={`filter-chip${view === 'week' ? ' active' : ''}`}
          onClick={() => { setView('week'); setExpandedId(null) }}
        >
          Semana
        </button>
        <button
          type="button"
          className={`filter-chip${view === 'cancelled' ? ' active' : ''}`}
          onClick={() => { setView('cancelled'); setExpandedId(null) }}
        >
          Cancelados{cancelledAppointments.length > 0 ? ` (${cancelledAppointments.length})` : ''}
        </button>
      </div>

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
          onCancel={handleCancelCard}
          onNoShow={handleNoShowCard}
          onCancelRecurrence={handleCancelRecurrenceCard}
          onDelete={isAdmin() ? handleDeleteCard : undefined}
        />
      )}

      {view === 'cancelled' && (
        <div className="patients-table-card">
          {cancelledAppointments.length === 0 ? (
            <p className="text-muted text-center py-4 mb-0">Nenhum agendamento cancelado neste período.</p>
          ) : (
            <Table hover responsive className="mb-0">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Paciente</th>
                  <th>Profissional</th>
                  <th>Procedimento</th>
                  <th>Motivo</th>
                  {isAdmin() && <th>Ações</th>}
                </tr>
              </thead>
              <tbody>
                {cancelledAppointments.map((apt) => (
                  <tr key={apt.id}>
                    <td>{apt.formatted_start}</td>
                    <td>{apt.patient?.name}</td>
                    <td>{apt.professional?.name}</td>
                    <td>{apt.service?.name}</td>
                    <td>{apt.cancellation_reason || '—'}</td>
                    {isAdmin() && (
                      <td>
                        <Button
                          size="sm"
                          variant="outline-danger"
                          className="appointment-card-delete-btn"
                          title="Excluir agendamento"
                          onClick={() => handleDelete(apt)}
                        >
                          <TrashIcon />
                        </Button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </Table>
          )}
        </div>
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
                  onCancel={handleCancelCard}
                  onNoShow={handleNoShowCard}
                  onCancelRecurrence={handleCancelRecurrenceCard}
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

      <ConfirmModal
        show={!!deletingAppointment}
        title="Excluir agendamento"
        message={`Tem certeza que deseja excluir o agendamento de ${deletingAppointment?.patient?.name} em ${deletingAppointment?.formatted_start}? Essa ação não pode ser desfeita pelo usuário.`}
        confirmLabel="Excluir"
        onClose={() => setDeletingAppointment(null)}
        onConfirm={confirmDelete}
      />
    </div>
  )
}
