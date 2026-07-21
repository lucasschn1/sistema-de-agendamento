import { useEffect, useState } from 'react'
import { Modal, Button, Spinner } from 'react-bootstrap'

// =============================================
// MODAL — EXCLUSÃO DE AGENDAMENTO
// Ação única de encerramento (substitui o antigo cancelamento). Para sessões
// recorrentes, oferece o mesmo modelo de escolha do Google Calendar/Outlook:
// só esta sessão, esta e as futuras, ou a série inteira.
// =============================================

const SCOPE_OPTIONS = [
  {
    value: 'this',
    label: 'Excluir este agendamento',
    description: 'Remove apenas esta sessão. As demais sessões da recorrência continuam normalmente.',
  },
  {
    value: 'future',
    label: 'Excluir este agendamento e os futuros',
    description: 'Remove esta sessão e todas as sessões futuras da mesma recorrência. Sessões anteriores são mantidas.',
  },
  {
    value: 'all',
    label: 'Excluir todos os agendamentos',
    description: 'Remove TODAS as sessões desta recorrência, incluindo as já realizadas. Essa ação afeta toda a série e não pode ser desfeita.',
    destructive: true,
  },
]

export default function DeleteAppointmentModal({ show, appointment, onClose, onConfirm }) {
  const [scope, setScope] = useState('this')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (show) setScope('this')
  }, [show, appointment?.id])

  const isRecurring = !!appointment?.is_recurring

  const handleConfirm = async () => {
    setSaving(true)
    try {
      await onConfirm(isRecurring ? scope : 'this')
      onClose()
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal show={show} onHide={onClose} centered>
      <Modal.Header closeButton>
        <Modal.Title>Excluir agendamento</Modal.Title>
      </Modal.Header>

      <Modal.Body>
        {!isRecurring ? (
          <p className="mb-0">
            Tem certeza que deseja excluir o agendamento de <strong>{appointment?.patient?.name}</strong> em{' '}
            {appointment?.formatted_start}? Essa ação não pode ser desfeita.
          </p>
        ) : (
          <>
            <p className="mb-3">
              Este agendamento faz parte de uma recorrência. O que você deseja excluir?
            </p>
            <div className="delete-scope-options">
              {SCOPE_OPTIONS.map((opt) => (
                <label
                  key={opt.value}
                  className={`delete-scope-option${opt.destructive ? ' is-destructive' : ''}${scope === opt.value ? ' is-selected' : ''}`}
                >
                  <input
                    type="radio"
                    name="delete-scope"
                    value={opt.value}
                    checked={scope === opt.value}
                    onChange={() => setScope(opt.value)}
                  />
                  <span className="delete-scope-option-text">
                    <strong>{opt.label}</strong>
                    <span className="delete-scope-option-desc">{opt.description}</span>
                  </span>
                </label>
              ))}
            </div>
          </>
        )}
      </Modal.Body>

      <Modal.Footer>
        <Button variant="outline-secondary" onClick={onClose} disabled={saving}>
          Cancelar
        </Button>
        <Button variant="danger" onClick={handleConfirm} disabled={saving}>
          {saving ? <Spinner as="span" animation="border" size="sm" /> : 'Excluir'}
        </Button>
      </Modal.Footer>
    </Modal>
  )
}
