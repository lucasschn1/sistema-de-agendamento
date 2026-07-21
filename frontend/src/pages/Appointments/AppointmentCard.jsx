import { statusLabel, statusClassName } from './statusMeta'
import AppointmentExpandedContent from './AppointmentExpandedContent'

export function TrashIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" width={14} height={14}>
      <polyline points="3 6 5 6 21 6" />
      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
      <line x1="10" y1="11" x2="10" y2="17" />
      <line x1="14" y1="11" x2="14" y2="17" />
    </svg>
  )
}

// =============================================
// CARD DE AGENDAMENTO — resumo compacto; clicar expande/recolhe in-place
// (sem modal) revelando detalhes, histórico e ações
// =============================================

export default function AppointmentCard({
  appointment: apt, isExpanded, onToggle, timeBadge,
  onEdit, onConfirm, onComplete, onNoShow, onDelete,
}) {
  const handleToggle = () => onToggle(apt.id)

  return (
    <div className={`appointment-card ${statusClassName(apt.status)}${isExpanded ? ' is-expanded' : ''}`}>
      <div
        className="appointment-card-main"
        onClick={handleToggle}
        role="button"
        tabIndex={0}
        aria-expanded={isExpanded}
        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleToggle() } }}
      >
        <div className="appointment-card-time">{apt.formatted_start}</div>

        <div className="appointment-card-info">
          <p className="appointment-card-patient">
            {apt.patient?.name}
            {apt.is_recurring && <span className="appointment-card-recurring-badge">Recorrente</span>}
            {timeBadge && (
              <span className={`appointment-card-time-badge${timeBadge === 'Agora' ? ' is-now' : ''}`}>
                {timeBadge}
              </span>
            )}
          </p>
          <p className="appointment-card-detail">{apt.service?.name} · {apt.professional?.name}</p>
          <p className="appointment-card-price">{apt.formatted_price}</p>
        </div>

        <span className={`appointment-card-status ${statusClassName(apt.status)}`}>
          {statusLabel(apt.status)}
        </span>

        <span className="appointment-card-expand-icon" aria-hidden="true">▾</span>
      </div>

      <div className="appointment-card-expand-wrapper">
        {isExpanded && (
          <AppointmentExpandedContent
            appointment={apt}
            onEdit={onEdit}
            onConfirm={onConfirm}
            onComplete={onComplete}
            onNoShow={onNoShow}
            onDelete={onDelete}
          />
        )}
      </div>
    </div>
  )
}
