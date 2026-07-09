// Metadados de exibição para o status de um agendamento
export const STATUS_META = {
  scheduled: { label: 'Agendado',   className: 'status-scheduled' },
  confirmed: { label: 'Confirmado', className: 'status-confirmed' },
  completed: { label: 'Concluído',  className: 'status-completed' },
  cancelled: { label: 'Cancelado',  className: 'status-cancelled' },
  no_show:   { label: 'Falta',      className: 'status-no-show' },
}

export function statusLabel(status) {
  return STATUS_META[status]?.label ?? status
}

export function statusClassName(status) {
  return STATUS_META[status]?.className ?? ''
}
