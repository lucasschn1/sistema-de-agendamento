import { useCallback, useEffect, useMemo, useState } from 'react'
import { Button, Alert, Spinner } from 'react-bootstrap'
import { useAuth } from '../../context/AuthContext'
import {
  listAppointments,
  confirmAppointment,
  completeAppointment,
  cancelAppointment,
  markNoShow,
  cancelRecurrence,
} from '../../api/appointments'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import MonthCalendar from './MonthCalendar'
import AppointmentCard from './AppointmentCard'
import AppointmentFormModal from './AppointmentFormModal'
import EditAppointmentModal from './EditAppointmentModal'
import ReasonModal from './ReasonModal'

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
  const { isProfessional } = useAuth()
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

  const loadAppointments = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      let params = {}

      // Admin filtra pelo intervalo visível no calendário; profissional sempre vê os seus
      if (!isProfessional()) {
        const gridStart = new Date(year, month, 1)
        gridStart.setDate(gridStart.getDate() - gridStart.getDay())
        const gridEnd = new Date(gridStart)
        gridEnd.setDate(gridStart.getDate() + 41)

        params = {
          start: toDateKey(gridStart),
          end:   toDateKey(gridEnd),
        }
      }

      const data = await listAppointments(params)
      setAppointments(data)
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [year, month, isProfessional])

  useEffect(() => {
    loadAppointments()
  }, [loadAppointments])

  const countsByDate = useMemo(() => {
    const counts = {}
    // Cancelados não contam como agendamento ativo no indicador do calendário
    for (const apt of appointments) {
      if (apt.status === 'cancelled') continue
      const key = apt.start_time.slice(0, 10)
      counts[key] = (counts[key] ?? 0) + 1
    }
    return counts
  }, [appointments])

  const dayAppointments = useMemo(() => {
    return appointments
      .filter((apt) => apt.start_time.slice(0, 10) === selectedDate)
      .sort((a, b) => a.start_time.localeCompare(b.start_time))
  }, [appointments, selectedDate])

  const goToMonth = (offset) => {
    const next = new Date(year, month + offset, 1)
    setYear(next.getFullYear())
    setMonth(next.getMonth())
  }

  const goToToday = () => {
    setYear(today.getFullYear())
    setMonth(today.getMonth())
    setSelectedDate(toDateKey(today))
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
        <Button variant="primary" onClick={() => setShowCreateModal(true)}>
          + Novo agendamento
        </Button>
      </div>

      {error && (
        <Alert variant="danger" onClose={() => setError('')} dismissible>
          {error}
        </Alert>
      )}

      <div className="appointments-layout">
        <MonthCalendar
          year={year}
          month={month}
          countsByDate={countsByDate}
          selectedDate={selectedDate}
          onSelectDate={setSelectedDate}
          onPrevMonth={() => goToMonth(-1)}
          onNextMonth={() => goToMonth(1)}
          onToday={goToToday}
        />

        <div className="appointments-day-panel">
          <h6 className="appointments-day-title">
            {formatSelectedDate(selectedDate)}
          </h6>

          {loading ? (
            <div className="text-center py-4">
              <Spinner animation="border" size="sm" />
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
                  onConfirm={handleConfirm}
                  onComplete={handleComplete}
                  onCancel={handleCancel}
                  onNoShow={handleNoShow}
                  onCancelRecurrence={handleCancelRecurrence}
                  onEdit={setEditingAppointment}
                />
              ))}
            </div>
          )}
        </div>
      </div>

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
    </div>
  )
}
