import { useState } from 'react'
import { Modal, Form, Button, Spinner } from 'react-bootstrap'

// =============================================
// MODAL — PEDE UM MOTIVO (marcar falta)
// =============================================

export default function ReasonModal({ show, title, required, onClose, onConfirm }) {
  const [reason, setReason]   = useState('')
  const [saving, setSaving]   = useState(false)

  const handleConfirm = async () => {
    setSaving(true)
    try {
      await onConfirm(reason)
      setReason('')
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

      <Modal.Body>
        <Form.Group>
          <Form.Label>Motivo{required ? '' : ' (opcional)'}</Form.Label>
          <Form.Control
            as="textarea"
            rows={3}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            autoFocus
          />
        </Form.Group>
      </Modal.Body>

      <Modal.Footer>
        <Button variant="outline-secondary" onClick={onClose} disabled={saving}>
          Voltar
        </Button>
        <Button
          variant="danger"
          onClick={handleConfirm}
          disabled={saving || (required && !reason.trim())}
        >
          {saving ? <Spinner as="span" animation="border" size="sm" /> : 'Confirmar'}
        </Button>
      </Modal.Footer>
    </Modal>
  )
}
