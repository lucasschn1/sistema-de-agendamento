import { useState } from 'react'
import { Modal, Button, Spinner } from 'react-bootstrap'

// =============================================
// MODAL — CONFIRMAÇÃO GENÉRICA PARA AÇÕES SENSÍVEIS
// =============================================

export default function ConfirmModal({ show, title, message, confirmLabel = 'Confirmar', variant = 'danger', onClose, onConfirm }) {
  const [saving, setSaving] = useState(false)

  const handleConfirm = async () => {
    setSaving(true)
    try {
      await onConfirm()
      onClose()
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal show={show} onHide={onClose} centered>
      <Modal.Header closeButton>
        <Modal.Title>{title}</Modal.Title>
      </Modal.Header>

      <Modal.Body>{message}</Modal.Body>

      <Modal.Footer>
        <Button variant="outline-secondary" onClick={onClose} disabled={saving}>
          Cancelar
        </Button>
        <Button variant={variant} onClick={handleConfirm} disabled={saving}>
          {saving ? <Spinner as="span" animation="border" size="sm" /> : confirmLabel}
        </Button>
      </Modal.Footer>
    </Modal>
  )
}
