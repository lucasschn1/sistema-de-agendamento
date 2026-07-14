import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner } from 'react-bootstrap'
import { createRentalBooking, listRentalRooms } from '../../api/rentals'
import { listProfessionals } from '../../api/users'
import { parseApiError, parseApiFieldErrors } from '../../utils/apiError'

const PERIOD_OPTIONS = [
  { value: 'manha', label: 'Manhã (08h-12h)' },
  { value: 'tarde', label: 'Tarde (12h-16h)' },
  { value: 'noite', label: 'Noite (16h-20h)' },
  { value: 'avulso', label: 'Avulso (horário específico)' },
]

// Horas cheias em que o avulso pode ser marcado (08h-20h — última reserva vai até 21h)
const HOUR_OPTIONS = Array.from({ length: 13 }, (_, i) => 8 + i)

// =============================================
// MODAL — NOVA RESERVA AVULSA DE SUBLOCAÇÃO
// =============================================

export default function RentalBookingFormModal({ show, defaultDate, defaultRoomId, defaultPeriod, defaultHour, onClose, onCreated }) {
  const [rooms, setRooms] = useState([])
  const [professionals, setProfessionals] = useState([])

  const [roomId, setRoomId] = useState('')
  const [tenantId, setTenantId] = useState('')
  const [bookingDate, setBookingDate] = useState('')
  const [period, setPeriod] = useState('avulso')
  const [hour, setHour] = useState(18)
  const [price, setPrice] = useState('')

  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!show) return

    setError('')
    setFieldErrors({})
    setRoomId(defaultRoomId ? String(defaultRoomId) : '')
    setTenantId('')
    setBookingDate(defaultDate ?? '')
    setPeriod(defaultPeriod ?? 'avulso')
    setHour(defaultHour ?? 18)
    setPrice('')

    listRentalRooms(true).then(setRooms).catch(() => {})
    listProfessionals(true).then(setProfessionals).catch(() => {})
  }, [show, defaultDate, defaultRoomId, defaultPeriod, defaultHour])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    setSaving(true)

    try {
      await createRentalBooking({
        rental_room_id: roomId,
        tenant_user_id: tenantId,
        booking_date: bookingDate,
        period,
        ...(period === 'avulso' ? { hour } : {}),
        price,
      })

      onCreated?.()
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
        <Modal.Title>Nova reserva avulsa</Modal.Title>
      </Modal.Header>

      <Form onSubmit={handleSubmit}>
        <Modal.Body>
          {error && (
            <Alert variant="danger" onClose={() => setError('')} dismissible>
              {error}
            </Alert>
          )}

          <Form.Group className="mb-3">
            <Form.Label>Sala</Form.Label>
            <Form.Select
              value={roomId}
              onChange={(e) => setRoomId(e.target.value)}
              isInvalid={!!fieldErrors.rental_room_id}
              required
            >
              <option value="">Selecione...</option>
              {rooms.map((r) => (
                <option key={r.id} value={r.id}>{r.name}</option>
              ))}
            </Form.Select>
            <Form.Control.Feedback type="invalid">{fieldErrors.rental_room_id}</Form.Control.Feedback>
          </Form.Group>

          <Form.Group className="mb-3">
            <Form.Label>Profissional (locatário)</Form.Label>
            <Form.Select
              value={tenantId}
              onChange={(e) => setTenantId(e.target.value)}
              isInvalid={!!fieldErrors.tenant_user_id}
              required
            >
              <option value="">Selecione...</option>
              {professionals.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </Form.Select>
            <Form.Control.Feedback type="invalid">{fieldErrors.tenant_user_id}</Form.Control.Feedback>
          </Form.Group>

          <Form.Group className="mb-3">
            <Form.Label>Data</Form.Label>
            <Form.Control
              type="date"
              value={bookingDate}
              onChange={(e) => setBookingDate(e.target.value)}
              isInvalid={!!fieldErrors.booking_date}
              required
            />
            <Form.Control.Feedback type="invalid">{fieldErrors.booking_date}</Form.Control.Feedback>
          </Form.Group>

          <Form.Group className="mb-3">
            <Form.Label>Período</Form.Label>
            <Form.Select
              value={period}
              onChange={(e) => setPeriod(e.target.value)}
              isInvalid={!!fieldErrors.period}
            >
              {PERIOD_OPTIONS.map((p) => (
                <option key={p.value} value={p.value}>{p.label}</option>
              ))}
            </Form.Select>
            <Form.Control.Feedback type="invalid">{fieldErrors.period}</Form.Control.Feedback>
            <div className="form-text">Reservas criadas aqui são sempre esporádicas — para um horário fixo toda semana, feche este formulário e escolha "Sublocação fixa" ao clicar no horário livre na Agenda.</div>
          </Form.Group>

          {period === 'avulso' && (
            <Form.Group className="mb-3">
              <Form.Label>Horário</Form.Label>
              <Form.Select
                value={hour}
                onChange={(e) => setHour(Number(e.target.value))}
                isInvalid={!!fieldErrors.hour}
              >
                {HOUR_OPTIONS.map((h) => (
                  <option key={h} value={h}>{String(h).padStart(2, '0')}:00 — {String(h + 1).padStart(2, '0')}:00</option>
                ))}
              </Form.Select>
              <Form.Control.Feedback type="invalid">{fieldErrors.hour}</Form.Control.Feedback>
            </Form.Group>
          )}

          <Form.Group>
            <Form.Label>Preço</Form.Label>
            <Form.Control
              type="number"
              step="0.01"
              min="0"
              value={price}
              onChange={(e) => setPrice(e.target.value)}
              isInvalid={!!fieldErrors.price}
              required
            />
            <Form.Control.Feedback type="invalid">{fieldErrors.price}</Form.Control.Feedback>
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
