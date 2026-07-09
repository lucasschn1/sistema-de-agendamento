import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner, Row, Col } from 'react-bootstrap'
import { listPatients, listProfessionals } from '../../api/users'
import { listProcedures } from '../../api/procedures'
import { createAppointment, createRecurrence } from '../../api/appointments'
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
          notes,
        })
      } else {
        await createAppointment({
          patient_id:      Number(patientId),
          professional_id: Number(professionalId),
          service_id:      Number(serviceId),
          start_time:      `${date} ${time}:00`,
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
                      {s.name} — {s.formatted_price} ({s.formatted_duration})
                    </option>
                  ))}
                </Form.Select>
                <Form.Control.Feedback type="invalid">{fieldErrors.service_id}</Form.Control.Feedback>
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
