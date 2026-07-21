import { useEffect, useState } from 'react'
import { Row, Col, Form, Spinner, InputGroup } from 'react-bootstrap'
import { listProfessionals, listPatients } from '../../api/users'
import { listProcedures } from '../../api/procedures'
import { getPaymentMethods } from '../../api/financial'

// =============================================
// FILTROS COMBINADOS DO HISTÓRICO DE PAGAMENTOS
// busca por texto (debounced) + profissional + paciente + procedimento + método
// =============================================

export default function PaymentHistoryFilters({ filters, onChange, searching }) {
  const [professionals, setProfessionals] = useState([])
  const [patients, setPatients] = useState([])
  const [procedures, setProcedures] = useState([])
  const [methods, setMethods] = useState([])

  const [search, setSearch] = useState(filters.search ?? '')
  const [isTyping, setIsTyping] = useState(false)

  useEffect(() => {
    listProfessionals(true).then(setProfessionals).catch(() => {})
    listPatients(true).then(setPatients).catch(() => {})
    listProcedures({ active: true }).then(setProcedures).catch(() => {})
    getPaymentMethods().then(setMethods).catch(() => {})
  }, [])

  useEffect(() => {
    const timer = setTimeout(() => {
      setIsTyping(false)
      onChange({ ...filters, search })
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search])

  const handleSelectChange = (key) => (e) => {
    onChange({ ...filters, [key]: e.target.value })
  }

  return (
    <Row className="g-3 financial-history-filters">
      <Col xs={12} md={4}>
        <InputGroup size="sm">
          <Form.Control
            placeholder="Buscar por paciente, profissional ou procedimento..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setIsTyping(true) }}
          />
          {(isTyping || searching) && (
            <InputGroup.Text>
              <Spinner animation="border" size="sm" />
            </InputGroup.Text>
          )}
        </InputGroup>
      </Col>

      <Col xs={6} md={2}>
        <Form.Select size="sm" value={filters.professional_id ?? ''} onChange={handleSelectChange('professional_id')}>
          <option value="">Profissional</option>
          {professionals.map((p) => (
            <option key={p.id} value={p.id}>{p.name}</option>
          ))}
        </Form.Select>
      </Col>

      <Col xs={6} md={2}>
        <Form.Select size="sm" value={filters.patient_id ?? ''} onChange={handleSelectChange('patient_id')}>
          <option value="">Paciente</option>
          {patients.map((p) => (
            <option key={p.id} value={p.id}>{p.name}</option>
          ))}
        </Form.Select>
      </Col>

      <Col xs={6} md={2}>
        <Form.Select size="sm" value={filters.service_id ?? ''} onChange={handleSelectChange('service_id')}>
          <option value="">Procedimento</option>
          {procedures.map((s) => (
            <option key={s.id} value={s.id}>{s.name}</option>
          ))}
        </Form.Select>
      </Col>

      <Col xs={6} md={2}>
        <Form.Select size="sm" value={filters.method ?? ''} onChange={handleSelectChange('method')}>
          <option value="">Método</option>
          {methods.map((m) => (
            <option key={m} value={m}>{m}</option>
          ))}
        </Form.Select>
      </Col>
    </Row>
  )
}
