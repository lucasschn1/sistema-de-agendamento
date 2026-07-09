import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner } from 'react-bootstrap'
import { registerPayment, getPaymentMethods } from '../../api/financial'
import { parseApiError } from '../../utils/apiError'

// =============================================
// MODAL — REGISTRAR PAGAMENTO DE UM AGENDAMENTO
// =============================================

export default function PaymentModal({ show, appointment, onClose, onSaved }) {
  const [methods, setMethods] = useState([])
  const [method, setMethod]   = useState('')
  const [date, setDate]       = useState('')

  const [error, setError]   = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!show) return

    setError('')
    setMethod('')
    setDate(new Date().toISOString().slice(0, 10))

    getPaymentMethods()
      .then(setMethods)
      .catch((err) => setError(parseApiError(err)))
  }, [show])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSaving(true)

    try {
      await registerPayment({
        appointment_id: appointment.id,
        method,
        date,
      })

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
        <Modal.Title>Registrar pagamento</Modal.Title>
      </Modal.Header>

      <Form onSubmit={handleSubmit}>
        <Modal.Body>
          {error && (
            <Alert variant="danger" onClose={() => setError('')} dismissible>
              {error}
            </Alert>
          )}

          {appointment && (
            <p className="text-muted mb-3">
              {appointment.patient?.name} — {appointment.service?.name} ({appointment.formatted_price})
            </p>
          )}

          <Form.Group className="mb-3">
            <Form.Label>Método de pagamento</Form.Label>
            <Form.Select value={method} onChange={(e) => setMethod(e.target.value)} required>
              <option value="">Selecione...</option>
              {methods.map((m) => (
                <option key={m} value={m}>{m}</option>
              ))}
            </Form.Select>
          </Form.Group>

          <Form.Group>
            <Form.Label>Data do pagamento</Form.Label>
            <Form.Control
              type="date"
              value={date}
              onChange={(e) => setDate(e.target.value)}
              required
            />
          </Form.Group>
        </Modal.Body>

        <Modal.Footer>
          <Button variant="outline-secondary" onClick={onClose} disabled={saving}>
            Cancelar
          </Button>
          <Button variant="primary" type="submit" disabled={saving}>
            {saving ? <Spinner as="span" animation="border" size="sm" /> : 'Confirmar'}
          </Button>
        </Modal.Footer>
      </Form>
    </Modal>
  )
}
