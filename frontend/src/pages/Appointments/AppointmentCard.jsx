import { Button } from 'react-bootstrap'
import { statusLabel, statusClassName } from './statusMeta'

// =============================================
// CARD DE AGENDAMENTO — mostra dados + ações de status
// =============================================

export default function AppointmentCard({ appointment: apt, onConfirm, onComplete, onCancel, onNoShow, onCancelRecurrence, onEdit }) {
  const canConfirm  = apt.status === 'scheduled'
  const canComplete = apt.status === 'confirmed'
  const canCancel   = apt.status === 'scheduled' || apt.status === 'confirmed'
  const canNoShow   = apt.status === 'confirmed'
  const canCancelRecurrence = apt.is_recurring && canCancel
  const canEdit     = apt.status === 'scheduled' || apt.status === 'confirmed'

  return (
    <div className="appointment-card">
      <div className="appointment-card-time">{apt.formatted_start}</div>

      <div className="appointment-card-info">
        <p className="appointment-card-patient">
          {apt.patient?.name}
          {apt.is_recurring && <span className="appointment-card-recurring-badge">Recorrente</span>}
        </p>
        <p className="appointment-card-detail">
          {apt.service?.name} · {apt.professional?.name}
        </p>
        {apt.notes && <p className="appointment-card-notes">{apt.notes}</p>}
      </div>

      <span className={`appointment-card-status ${statusClassName(apt.status)}`}>
        {statusLabel(apt.status)}
      </span>

      <div className="appointment-card-actions">
        {canEdit && (
          <Button size="sm" variant="outline-secondary" onClick={() => onEdit(apt)}>
            Editar
          </Button>
        )}
        {canConfirm && (
          <Button size="sm" variant="outline-success" onClick={() => onConfirm(apt)}>
            Confirmar
          </Button>
        )}
        {canComplete && (
          <Button size="sm" variant="outline-primary" onClick={() => onComplete(apt)}>
            Concluir
          </Button>
        )}
        {canNoShow && (
          <Button size="sm" variant="outline-warning" onClick={() => onNoShow(apt)}>
            Falta
          </Button>
        )}
        {canCancel && (
          <Button size="sm" variant="outline-danger" onClick={() => onCancel(apt)}>
            Cancelar
          </Button>
        )}
        {canCancelRecurrence && (
          <Button size="sm" variant="outline-danger" onClick={() => onCancelRecurrence(apt)}>
            Cancelar recorrência
          </Button>
        )}
      </div>
    </div>
  )
}
