import { useEffect, useState } from 'react'
import { Button, Spinner } from 'react-bootstrap'
import { statusLabel, statusClassName } from './statusMeta'
import { getAppointmentHistory } from '../../api/appointments'
import { TrashIcon } from './AppointmentCard'

function formatHistoryDate(value) {
  if (!value) return ''
  return new Date(value.replace(' ', 'T')).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function EditIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" width={15} height={15}>
      <path d="M12 20h9" />
      <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
    </svg>
  )
}

function ConfirmIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" width={15} height={15}>
      <polyline points="20 6 9 17 4 12" />
    </svg>
  )
}

function CompleteIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" width={15} height={15}>
      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
      <polyline points="22 4 12 14.01 9 11.01" />
    </svg>
  )
}

function NoShowIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" width={15} height={15}>
      <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="8.5" cy="7" r="4" />
      <line x1="17" y1="8" x2="22" y2="13" />
      <line x1="22" y1="8" x2="17" y2="13" />
    </svg>
  )
}

function CancelIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" width={15} height={15}>
      <circle cx="12" cy="12" r="10" />
      <line x1="15" y1="9" x2="9" y2="15" />
      <line x1="9" y1="9" x2="15" y2="15" />
    </svg>
  )
}

function RepeatCancelIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" width={15} height={15}>
      <path d="M17 1l4 4-4 4" />
      <path d="M3 11V9a4 4 0 0 1 4-4h14" />
      <path d="M7 23l-4-4 4-4" />
      <path d="M21 13v2a4 4 0 0 1-4 4H3" />
    </svg>
  )
}

// =============================================
// CONTEÚDO EXPANDIDO DO AGENDAMENTO — grade de detalhes, histórico e ações
// Compartilhado entre a Agenda em Lista e a Agenda Semanal: os dois cards
// usam exatamente este mesmo componente ao expandir, garantindo a mesma
// experiência (e a mesma lógica de ações) nas duas visualizações
// =============================================

export default function AppointmentExpandedContent({
  appointment: apt, onEdit, onConfirm, onComplete, onCancel, onNoShow, onCancelRecurrence, onDelete,
}) {
  const [history, setHistory] = useState(null)
  const [historyLoading, setHistoryLoading] = useState(false)

  useEffect(() => {
    setHistory(null)
    setHistoryLoading(true)
    getAppointmentHistory(apt.id)
      .then(setHistory)
      .catch(() => setHistory([]))
      .finally(() => setHistoryLoading(false))
  }, [apt.id])

  const canConfirm  = apt.status === 'scheduled'
  const canComplete = apt.status === 'confirmed'
  const canCancel   = apt.status === 'scheduled' || apt.status === 'confirmed'
  const canNoShow   = apt.status === 'confirmed'
  const canCancelRecurrence = apt.is_recurring && canCancel
  const canEdit     = apt.status === 'scheduled' || apt.status === 'confirmed'

  return (
    <div className="appointment-expanded" onClick={(e) => e.stopPropagation()}>
      <div className="appointment-expanded-grid">
        <div className="appointment-expanded-field">
          <span className="appointment-expanded-label">Paciente</span>
          <span className="appointment-expanded-value">{apt.patient?.name}</span>
        </div>
        <div className="appointment-expanded-field">
          <span className="appointment-expanded-label">Profissional</span>
          <span className="appointment-expanded-value">{apt.professional?.name}</span>
        </div>
        <div className="appointment-expanded-field">
          <span className="appointment-expanded-label">Procedimento</span>
          <span className="appointment-expanded-value">{apt.service?.name}</span>
        </div>
        <div className="appointment-expanded-field">
          <span className="appointment-expanded-label">Valor</span>
          <span className="appointment-expanded-value">{apt.formatted_price}</span>
        </div>
        <div className="appointment-expanded-field">
          <span className="appointment-expanded-label">Data e horário</span>
          <span className="appointment-expanded-value">{apt.formatted_start}</span>
        </div>
        <div className="appointment-expanded-field">
          <span className="appointment-expanded-label">Status</span>
          <span className={`appointment-card-status ${statusClassName(apt.status)}`}>
            {statusLabel(apt.status)}
          </span>
        </div>
      </div>

      {apt.notes && (
        <div className="appointment-expanded-notes">
          <span className="appointment-expanded-label">Observações</span>
          <p className="mb-0">{apt.notes}</p>
        </div>
      )}

      <div className="appointment-detail-history">
        <span className="appointment-expanded-label">Histórico</span>

        {historyLoading ? (
          <div className="py-2"><Spinner animation="border" size="sm" /></div>
        ) : !history || history.length === 0 ? (
          <p className="appointment-card-history-empty">Nenhuma alteração registrada ainda.</p>
        ) : (
          <ul className="appointment-detail-timeline">
            {history.map((entry) => (
              <li key={entry.id} className={`appointment-detail-timeline-item ${statusClassName(entry.action)}`}>
                <p className="appointment-detail-timeline-text">
                  <strong>{statusLabel(entry.action)}</strong> por {entry.changed_by_name ?? 'sistema'}
                </p>
                <p className="appointment-detail-timeline-date">{formatHistoryDate(entry.created_at)}</p>
                {entry.reason && <p className="appointment-card-history-reason mb-0">{entry.reason}</p>}
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="appointment-expanded-actions">
        {canEdit && (
          <Button variant="outline-secondary" className="appointment-action-btn" onClick={() => onEdit(apt)}>
            <EditIcon /> Editar
          </Button>
        )}
        {canConfirm && (
          <Button variant="outline-success" className="appointment-action-btn" onClick={() => onConfirm(apt)}>
            <ConfirmIcon /> Confirmar
          </Button>
        )}
        {canComplete && (
          <Button variant="outline-primary" className="appointment-action-btn" onClick={() => onComplete(apt)}>
            <CompleteIcon /> Concluir
          </Button>
        )}
        {canNoShow && (
          <Button variant="outline-warning" className="appointment-action-btn" onClick={() => onNoShow(apt)}>
            <NoShowIcon /> Falta
          </Button>
        )}
        {canCancel && (
          <Button variant="outline-danger" className="appointment-action-btn" onClick={() => onCancel(apt)}>
            <CancelIcon /> Cancelar
          </Button>
        )}
        {canCancelRecurrence && (
          <Button variant="outline-danger" className="appointment-action-btn" onClick={() => onCancelRecurrence(apt)}>
            <RepeatCancelIcon /> Cancelar recorrência
          </Button>
        )}
        {onDelete && (
          <Button variant="outline-danger" className="appointment-action-btn" onClick={() => onDelete(apt)}>
            <TrashIcon /> Excluir
          </Button>
        )}
      </div>
    </div>
  )
}
