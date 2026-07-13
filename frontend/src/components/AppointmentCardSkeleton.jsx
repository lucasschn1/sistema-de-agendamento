// Placeholder do formato de um AppointmentCard, exibido enquanto a agenda carrega
export default function AppointmentCardSkeleton() {
  return (
    <div className="appointment-card appointment-card-skeleton">
      <div className="skeleton-bar" style={{ width: 48, height: 14 }} />
      <div className="appointment-card-info">
        <div className="skeleton-bar" style={{ width: '55%', height: 14, marginBottom: 8 }} />
        <div className="skeleton-bar" style={{ width: '35%', height: 12 }} />
      </div>
      <div className="skeleton-bar" style={{ width: 72, height: 22, borderRadius: 'var(--radius-pill)' }} />
    </div>
  )
}
