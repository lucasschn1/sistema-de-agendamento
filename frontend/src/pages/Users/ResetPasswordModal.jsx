import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner } from 'react-bootstrap'
import { resetUserPassword } from '../../api/users'
import { parseApiError } from '../../utils/apiError'

// =============================================
// MODAL — REDEFINIR SENHA (uso administrativo)
// =============================================

export default function ResetPasswordModal({ show, user, onClose, onSaved }) {
  const [newPassword, setNewPassword] = useState('')
  const [error, setError]     = useState('')
  const [saving, setSaving]   = useState(false)

  useEffect(() => {
    if (!show) return
    setError('')
    setNewPassword('')
  }, [show])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSaving(true)

    try {
      await resetUserPassword(user.id, newPassword)
      onSaved?.()
      onClose()
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal show={show} onHide={onClose} centered>
      <Modal.Header closeButton>
        <Modal.Title>Redefinir senha</Modal.Title>
      </Modal.Header>

      <Form onSubmit={handleSubmit}>
        <Modal.Body>
          {error && (
            <Alert variant="danger" onClose={() => setError('')} dismissible>
              {error}
            </Alert>
          )}

          <p className="text-muted">
            Definir uma nova senha para <strong>{user?.name}</strong>. Ele(a) deverá usá-la no próximo login.
          </p>

          <Form.Group>
            <Form.Label>Nova senha</Form.Label>
            <Form.Control
              type="password"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              minLength={6}
              required
              autoFocus
            />
          </Form.Group>
        </Modal.Body>

        <Modal.Footer>
          <Button variant="outline-secondary" onClick={onClose} disabled={saving}>
            Cancelar
          </Button>
          <Button variant="primary" type="submit" disabled={saving}>
            {saving ? <Spinner as="span" animation="border" size="sm" /> : 'Redefinir'}
          </Button>
        </Modal.Footer>
      </Form>
    </Modal>
  )
}
