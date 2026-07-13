import { useState } from 'react'
import { Button, Spinner } from 'react-bootstrap'
import { statusLabel, statusClassName } from './statusMeta'
import { getAppointmentHistory } from '../../api/appointments'

function formatHistoryDate(value) {
  if (!value) return ''
  return new Date(value.replace(' ', 'T')).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

// =============================================
// CARD DE AGENDAMENTO — mostra dados + ações de status
// Clicável: expande e mostra detalhes (preço, duração, pagamento)
// =============================================

export default function AppointmentCard({ appointment: apt, onConfirm, onComplete, onCancel, onNoShow, onCancelRecurrence, onEdit }) {
  const [expanded, setExpanded] = useState(false)
  const [history, setHistory] = useState(null)
  const [historyLoading, setHistoryLoading] = useState(false)

  const toggleExpanded = () => {
    setExpanded((v) => !v)
    if (!history && !historyLoading) {
      setHistoryLoading(true)
      getAppointmentHistory(apt.id)
        .then(setHistory)
        .catch(() => setHistory([]))
        .finally(() => setHistoryLoading(false))
    }
  }

  const canConfirm  = apt.status === 'scheduled'
  const canComplete = apt.status === 'confirmed'
  const canCancel   = apt.status === 'scheduled' || apt.status === 'confirmed'
  const canNoShow   = apt.status === 'confirmed'
  const canCancelRecurrence = apt.is_recurring && canCancel
  const canEdit     = apt.status === 'scheduled' || apt.status === 'confirmed'

  return (
    <div className={`appointment-card ${statusClassName(apt.status)}${expanded ? ' is-expanded' : ''}`}>
      <div
        className="appointment-card-main"
        onClick={toggleExpanded}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') toggleExpanded() }}
      >
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

        <span className="appointment-card-expand-icon" aria-hidden="true">▾</span>
      </div>

      {expanded && (
        <div className="appointment-card-details">
          <div className="appointment-card-details-item">
            <span className="appointment-card-details-label">Preço</span>
            <span>{apt.formatted_price}</span>
          </div>
          <div className="appointment-card-details-item">
            <span className="appointment-card-details-label">Duração</span>
            <span>{apt.formatted_duration}</span>
          </div>
          <div className="appointment-card-details-item">
            <span className="appointment-card-details-label">Pagamento</span>
            <span>
              {apt.paid
                ? `Pago via ${apt.payment_method ?? '—'}${apt.payment_date ? ` em ${apt.payment_date}` : ''}`
                : 'Pendente'}
            </span>
          </div>

          <div className="appointment-card-history">
            <span className="appointment-card-details-label">Histórico</span>
            {historyLoading ? (
              <Spinner animation="border" size="sm" />
            ) : !history || history.length === 0 ? (
              <p className="appointment-card-history-empty">Nenhuma alteração registrada ainda.</p>
            ) : (
              <ul className="appointment-card-history-list">
                {history.map((entry) => (
                  <li key={entry.id}>
                    <strong>{statusLabel(entry.action)}</strong> por {entry.changed_by_name ?? 'sistema'}
                    {' · '}{formatHistoryDate(entry.created_at)}
                    {entry.reason && <span className="appointment-card-history-reason"> — {entry.reason}</span>}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}

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
