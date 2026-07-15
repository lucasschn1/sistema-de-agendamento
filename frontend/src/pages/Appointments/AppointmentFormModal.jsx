import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner, Row, Col } from 'react-bootstrap'
import { listPatients, listProfessionals } from '../../api/users'
import { listProcedures } from '../../api/procedures'
import { createAppointment, createRecurrence, checkAvailability } from '../../api/appointments'
import { parseApiError, parseApiFieldErrors } from '../../utils/apiError'

const WEEKDAYS = [
  { value: 0, label: 'Domingo' },
  { value: 1, label: 'Segunda-feira' },
  { value: 2, label: 'Terça-feira' },
  { value: 3, label: 'Quarta-feira' },
  { value: 4, label: 'Quinta-feira' },
  { value: 5, label: 'Sexta-feira' },
  { value: 6, label: 'Sábado' },
]

// =============================================
// MODAL — CRIAR AGENDAMENTO (único ou recorrente)
// =============================================

export default function AppointmentFormModal({ show, onClose, onCreated, defaultDate }) {
  const [patients, setPatients]         = useState([])
  const [professionals, setProfessionals] = useState([])
  const [procedures, setProcedures]     = useState([])

  const [patientId, setPatientId]           = useState('')
  const [professionalId, setProfessionalId] = useState('')
  const [serviceId, setServiceId]           = useState('')
  const [time, setTime]                     = useState('')
  const [price, setPrice]                   = useState('')
  const [notes, setNotes]                   = useState('')

  const [isRecurring, setIsRecurring]     = useState(false)
  const [date, setDate]                   = useState('') // agendamento único
  const [recurrenceType, setRecurrenceType] = useState('semanal')
  const [dayOfWeek, setDayOfWeek]         = useState('1')
  const [startDate, setStartDate]         = useState('')
  const [endDate, setEndDate]             = useState('')

  const [error, setError]           = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [saving, setSaving]         = useState(false)
  const [loadingOptions, setLoadingOptions] = useState(false)

  const [availability, setAvailability] = useState(null) // null | 'checking' | 'available' | 'unavailable'

  // Carrega listas de apoio (pacientes, profissionais, procedimentos) ao abrir o modal
  useEffect(() => {
    if (!show) return

    setError('')
    setFieldErrors({})
    setDate(defaultDate ?? '')
    setStartDate(defaultDate ?? '')
    setEndDate('')
    setTime('')
    setPatientId('')
    setProfessionalId('')
    setServiceId('')
    setPrice('')
    setNotes('')
    setIsRecurring(false)
    setRecurrenceType('semanal')
    setDayOfWeek('1')

    setLoadingOptions(true)
    Promise.all([listPatients(), listProfessionals(), listProcedures()])
      .then(([patientsData, professionalsData, proceduresData]) => {
        setPatients(patientsData)
        setProfessionals(professionalsData)
        setProcedures(proceduresData)
      })
      .catch((err) => setError(parseApiError(err)))
      .finally(() => setLoadingOptions(false))
  }, [show, defaultDate])

  // Checa disponibilidade em tempo real assim que profissional, data, horário e procedimento estão definidos
  useEffect(() => {
    if (!show || isRecurring || !professionalId || !date || !time || !serviceId) {
      setAvailability(null)
      return
    }

    const service = procedures.find((s) => String(s.id) === String(serviceId))
    if (!service) return

    setAvailability('checking')

    const timer = setTimeout(() => {
      checkAvailability({
        professional_id: professionalId,
        date: `${date} ${time}:00`,
        duration: service.duration_minutes,
      })
        .then((result) => setAvailability(result.available ? 'available' : 'unavailable'))
        .catch(() => setAvailability(null))
    }, 400)

    return () => clearTimeout(timer)
  }, [show, isRecurring, professionalId, date, time, serviceId, procedures])

  // Sugere o valor do procedimento como ponto de partida, mas o valor final do
  // agendamento é sempre editável e independente — só preenche se ainda estiver vazio
  useEffect(() => {
    if (!serviceId || price !== '') return
    const service = procedures.find((s) => String(s.id) === String(serviceId))
    if (service?.price) setPrice(String(service.price))
  }, [serviceId]) // eslint-disable-line react-hooks/exhaustive-deps

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    setSaving(true)

    try {
      if (isRecurring) {
        await createRecurrence({
          patient_id:      Number(patientId),
          professional_id: Number(professionalId),
          service_id:      Number(serviceId),
          type:            recurrenceType,
          day_of_week:     Number(dayOfWeek),
          start_hour:      `${time}:00`,
          start_date:      startDate,
          end_date:        endDate || undefined,
          price:           Number(price),
          notes,
        })
      } else {
        await createAppointment({
          patient_id:      Number(patientId),
          professional_id: Number(professionalId),
          service_id:      Number(serviceId),
          start_time:      `${date} ${time}:00`,
          price:           Number(price),
          notes,
        })
      }

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
        <Modal.Title>Novo agendamento</Modal.Title>
      </Modal.Header>

      <Form onSubmit={handleSubmit}>
        <Modal.Body>
          {error && (
            <Alert variant="danger" onClose={() => setError('')} dismissible>
              {error}
            </Alert>
          )}

          {loadingOptions ? (
            <div className="text-center py-4">
              <Spinner animation="border" size="sm" />
            </div>
          ) : (
            <>
              <Form.Group className="mb-3">
                <Form.Label>Paciente</Form.Label>
                <Form.Select
                  value={patientId}
                  onChange={(e) => setPatientId(e.target.value)}
                  isInvalid={!!fieldErrors.patient_id}
                  required
                >
                  <option value="">Selecione...</option>
                  {patients.map((p) => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </Form.Select>
                <Form.Control.Feedback type="invalid">{fieldErrors.patient_id}</Form.Control.Feedback>
              </Form.Group>

              <Form.Group className="mb-3">
                <Form.Label>Profissional</Form.Label>
                <Form.Select
                  value={professionalId}
                  onChange={(e) => setProfessionalId(e.target.value)}
                  isInvalid={!!fieldErrors.professional_id}
                  required
                >
                  <option value="">Selecione...</option>
                  {professionals.map((p) => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </Form.Select>
                <Form.Control.Feedback type="invalid">{fieldErrors.professional_id}</Form.Control.Feedback>
              </Form.Group>

              <Form.Group className="mb-3">
                <Form.Label>Procedimento</Form.Label>
                <Form.Select
                  value={serviceId}
                  onChange={(e) => setServiceId(e.target.value)}
                  isInvalid={!!fieldErrors.service_id}
                  required
                >
                  <option value="">Selecione...</option>
                  {procedures.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name} ({s.formatted_duration})
                    </option>
                  ))}
                </Form.Select>
                <Form.Control.Feedback type="invalid">{fieldErrors.service_id}</Form.Control.Feedback>
              </Form.Group>

              <Form.Group className="mb-3">
                <Form.Label>Valor cobrado (R$)</Form.Label>
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
                <div className="form-text">Pode ser diferente do valor usual deste procedimento — defina o valor deste atendimento específico.</div>
              </Form.Group>

              <Form.Check
                type="switch"
                id="is-recurring"
                label="Agendamento recorrente"
                checked={isRecurring}
                onChange={(e) => setIsRecurring(e.target.checked)}
                className="mb-3"
              />

              {isRecurring ? (
                <>
                  <Row>
                    <Col>
                      <Form.Group className="mb-3">
                        <Form.Label>Frequência</Form.Label>
                        <Form.Select
                          value={recurrenceType}
                          onChange={(e) => setRecurrenceType(e.target.value)}
                          required
                        >
                          <option value="semanal">Semanal</option>
                          <option value="quinzenal">Quinzenal</option>
                        </Form.Select>
                      </Form.Group>
                    </Col>
                    <Col>
                      <Form.Group className="mb-3">
                        <Form.Label>Dia da semana</Form.Label>
                        <Form.Select
                          value={dayOfWeek}
                          onChange={(e) => setDayOfWeek(e.target.value)}
                          required
                        >
                          {WEEKDAYS.map((d) => (
                            <option key={d.value} value={d.value}>{d.label}</option>
                          ))}
                        </Form.Select>
                      </Form.Group>
                    </Col>
                  </Row>

                  <Row>
                    <Col>
                      <Form.Group className="mb-3">
                        <Form.Label>Início</Form.Label>
                        <Form.Control
                          type="date"
                          value={startDate}
                          onChange={(e) => setStartDate(e.target.value)}
                          required
                        />
                      </Form.Group>
                    </Col>
                    <Col>
                      <Form.Group className="mb-3">
                        <Form.Label>Fim <span className="text-muted">(opcional)</span></Form.Label>
                        <Form.Control
                          type="date"
                          value={endDate}
                          onChange={(e) => setEndDate(e.target.value)}
                        />
                      </Form.Group>
                    </Col>
                  </Row>

                  <Form.Group className="mb-3">
                    <Form.Label>Horário</Form.Label>
                    <Form.Control
                      type="time"
                      value={time}
                      onChange={(e) => setTime(e.target.value)}
                      required
                    />
                  </Form.Group>
                </>
              ) : (
                <Row>
                  <Col>
                    <Form.Group className="mb-3">
                      <Form.Label>Data</Form.Label>
                      <Form.Control
                        type="date"
                        value={date}
                        onChange={(e) => setDate(e.target.value)}
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
              )}

              {!isRecurring && availability && (
                <div className={`availability-hint availability-${availability} mb-3`}>
                  {availability === 'checking' && (
                    <>
                      <Spinner animation="border" size="sm" className="me-2" />
                      Checando disponibilidade...
                    </>
                  )}
                  {availability === 'available' && '✓ Horário disponível'}
                  {availability === 'unavailable' && '✕ Horário indisponível para este profissional'}
                </div>
              )}

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
            </>
          )}
        </Modal.Body>

        <Modal.Footer>
          <Button variant="outline-secondary" onClick={onClose} disabled={saving}>
            Cancelar
          </Button>
          <Button variant="primary" type="submit" disabled={saving || loadingOptions}>
            {saving ? <Spinner as="span" animation="border" size="sm" /> : 'Agendar'}
          </Button>
        </Modal.Footer>
      </Form>
    </Modal>
  )
}
