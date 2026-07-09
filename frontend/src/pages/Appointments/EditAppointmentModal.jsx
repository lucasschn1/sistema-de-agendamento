import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner, Row, Col } from 'react-bootstrap'
import { updateAppointment, rescheduleAppointment } from '../../api/appointments'
import { parseApiError, parseApiFieldErrors } from '../../utils/apiError'

// =============================================
// MODAL — EDITAR AGENDAMENTO (data/hora, preço, observações)
// =============================================

export default function EditAppointmentModal({ show, appointment, onClose, onSaved }) {
  const [date, setDate]     = useState('')
  const [time, setTime]     = useState('')
  const [price, setPrice]   = useState('')
  const [notes, setNotes]   = useState('')

  const [error, setError]           = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [saving, setSaving]         = useState(false)

  useEffect(() => {
    if (!show || !appointment) return

    setError('')
    setFieldErrors({})
    const [datePart, timePart] = appointment.start_time.split(' ')
    setDate(datePart)
    setTime(timePart.slice(0, 5))
    setPrice(appointment.price ?? '')
    setNotes(appointment.notes ?? '')
  }, [show, appointment])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    setSaving(true)

    try {
      const [originalDate, originalTime] = appointment.start_time.split(' ')
      const newStartTime = `${date} ${time}:00`

      // Só reagenda se data/hora realmente mudaram (evita erro de conflito com o próprio horário)
      if (date !== originalDate || time !== originalTime.slice(0, 5)) {
        await rescheduleAppointment(appointment.id, newStartTime)
      }

      await updateAppointment(appointment.id, {
        notes,
        price: price === '' ? null : Number(price),
      })

      onSaved?.()
      onClose()
    } catch (err) {
      setError(parseApiError(err))
      setFieldErrors(parseApiFieldErrors(err))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal show={show} onHide={onClose} centered>
      <Modal.Header closeButton>
        <Modal.Title>Editar agendamento</Modal.Title>
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
              {appointment.patient?.name} — {appointment.service?.name} ({appointment.professional?.name})
            </p>
          )}

          <Row>
            <Col>
              <Form.Group className="mb-3">
                <Form.Label>Data</Form.Label>
                <Form.Control
                  type="date"
                  value={date}
                  onChange={(e) => setDate(e.target.value)}
                  isInvalid={!!fieldErrors.start_time}
                  required
                />
              </Form.Group>
            </Col>
            <Col>
              <Form.Group className="mb-3">
                <Form.Label>Horário</Form.Label>
                <Form.Control
                  type="time"
                  value={time}
                  onChange={(e) => setTime(e.target.value)}
                  required
                />
              </Form.Group>
            </Col>
          </Row>
          <Form.Control.Feedback type="invalid" className={fieldErrors.start_time ? 'd-block mb-3' : ''}>
            {fieldErrors.start_time}
          </Form.Control.Feedback>

          <Form.Group className="mb-3">
            <Form.Label>Preço (R$)</Form.Label>
            <Form.Control
              type="number"
              step="0.01"
              min="0"
              value={price}
              onChange={(e) => setPrice(e.target.value)}
              isInvalid={!!fieldErrors.price}
            />
            <Form.Control.Feedback type="invalid">{fieldErrors.price}</Form.Control.Feedback>
          </Form.Group>

          <Form.Group>
            <Form.Label>Observações</Form.Label>
            <Form.Control
              as="textarea"
              rows={2}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Opcional"
            />
          </Form.Group>
        </Modal.Body>

        <Modal.Footer>
          <Button variant="outline-secondary" onClick={onClose} disabled={saving}>
            Cancelar
          </Button>
          <Button variant="primary" type="submit" disabled={saving}>
            {saving ? <Spinner as="span" animation="border" size="sm" /> : 'Salvar'}
          </Button>
        </Modal.Footer>
      </Form>
    </Modal>
  )
}
