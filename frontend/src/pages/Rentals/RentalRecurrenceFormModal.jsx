import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner } from 'react-bootstrap'
import { createRentalRecurrence, listRentalRooms } from '../../api/rentals'
import { listProfessionals } from '../../api/users'
import { parseApiError, parseApiFieldErrors } from '../../utils/apiError'

const PERIOD_OPTIONS = [
  { value: 'manha', label: 'Manhã (08h-12h)' },
  { value: 'tarde', label: 'Tarde (12h-16h)' },
  { value: 'noite', label: 'Noite (16h-20h)' },
]

const DAY_OPTIONS = [
  { value: 0, label: 'Domingo' },
  { value: 1, label: 'Segunda' },
  { value: 2, label: 'Terça' },
  { value: 3, label: 'Quarta' },
  { value: 4, label: 'Quinta' },
  { value: 5, label: 'Sexta' },
  { value: 6, label: 'Sábado' },
]

// =============================================
// MODAL — NOVA SUBLOCAÇÃO FIXA (RECORRENTE)
// =============================================

export default function RentalRecurrenceFormModal({ show, defaultRoomId, defaultPeriod, defaultDate, onClose, onCreated }) {
  const [rooms, setRooms] = useState([])
  const [professionals, setProfessionals] = useState([])

  const [roomId, setRoomId] = useState('')
  const [tenantId, setTenantId] = useState('')
  const [period, setPeriod] = useState('manha')
  const [dayOfWeek, setDayOfWeek] = useState(1)
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
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
    setPeriod(defaultPeriod ?? 'manha')
    setDayOfWeek(defaultDate ? new Date(`${defaultDate}T00:00:00`).getDay() : 1)
    setStartDate(defaultDate ?? '')
    setEndDate('')
    setPrice('')

    listRentalRooms(true).then(setRooms).catch(() => {})
    listProfessionals(true).then(setProfessionals).catch(() => {})
  }, [show, defaultRoomId, defaultPeriod, defaultDate])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    setSaving(true)

    try {
      const result = await createRentalRecurrence({
        rental_room_id: roomId,
        tenant_user_id: tenantId,
        period,
        day_of_week: dayOfWeek,
        start_date: startDate,
        end_date: endDate || undefined,
        price,
      })

      onCreated?.(result)
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
        <Modal.Title>Nova sublocação fixa</Modal.Title>
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
            <Form.Select value={roomId} onChange={(e) => setRoomId(e.target.value)} isInvalid={!!fieldErrors.rental_room_id} required>
              <option value="">Selecione...</option>
              {rooms.map((r) => (
                <option key={r.id} value={r.id}>{r.name}</option>
              ))}
            </Form.Select>
            <Form.Control.Feedback type="invalid">{fieldErrors.rental_room_id}</Form.Control.Feedback>
          </Form.Group>

          <Form.Group className="mb-3">
            <Form.Label>Profissional (locatário)</Form.Label>
            <Form.Select value={tenantId} onChange={(e) => setTenantId(e.target.value)} isInvalid={!!fieldErrors.tenant_user_id} required>
              <option value="">Selecione...</option>
              {professionals.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </Form.Select>
            <Form.Control.Feedback type="invalid">{fieldErrors.tenant_user_id}</Form.Control.Feedback>
          </Form.Group>

          <Form.Group className="mb-3">
            <Form.Label>Bloco</Form.Label>
            <Form.Select value={period} onChange={(e) => setPeriod(e.target.value)} isInvalid={!!fieldErrors.period}>
              {PERIOD_OPTIONS.map((p) => (
                <option key={p.value} value={p.value}>{p.label}</option>
              ))}
            </Form.Select>
            <Form.Control.Feedback type="invalid">{fieldErrors.period}</Form.Control.Feedback>
            <div className="form-text">Só blocos completos podem virar fixos — o horário avulso é sempre esporádico.</div>
          </Form.Group>

          <Form.Group className="mb-3">
            <Form.Label>Dia da semana</Form.Label>
            <Form.Select value={dayOfWeek} onChange={(e) => setDayOfWeek(Number(e.target.value))}>
              {DAY_OPTIONS.map((d) => (
                <option key={d.value} value={d.value}>{d.label}</option>
              ))}
            </Form.Select>
          </Form.Group>

          <div className="d-flex gap-3 mb-3">
            <Form.Group className="flex-fill">
              <Form.Label>Início</Form.Label>
              <Form.Control
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                isInvalid={!!fieldErrors.start_date}
                required
              />
              <Form.Control.Feedback type="invalid">{fieldErrors.start_date}</Form.Control.Feedback>
            </Form.Group>

            <Form.Group className="flex-fill">
              <Form.Label>Fim <span className="text-muted">(opcional)</span></Form.Label>
              <Form.Control
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
              />
            </Form.Group>
          </div>

          <Form.Group>
            <Form.Label>Valor mensal</Form.Label>
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
            <div className="form-text">Gera automaticamente a primeira fatura antecipada, com vencimento na data de início.</div>
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
