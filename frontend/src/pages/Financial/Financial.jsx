import { useCallback, useEffect, useState } from 'react'
import { Row, Col, Table, Button, Alert, Spinner } from 'react-bootstrap'
import { getCurrentMonthSummary, getPendingPayments } from '../../api/financial'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import PaymentModal from './PaymentModal'

export default function Financial() {
  const { showToast } = useToast()
  const [summary, setSummary] = useState(null)
  const [pending, setPending] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState('')

  const [payingAppointment, setPayingAppointment] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const [summaryData, pendingData] = await Promise.all([
        getCurrentMonthSummary(),
        getPendingPayments(),
      ])
      setSummary(summaryData)
      setPending(pendingData)
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const handlePaymentSaved = () => {
    showToast('Pagamento registrado com sucesso')
    load()
  }

  return (
    <div className="financial-page">
      {error && (
        <Alert variant="danger" onClose={() => setError('')} dismissible>
          {error}
        </Alert>
      )}

      {loading ? (
        <div className="text-center py-4">
          <Spinner animation="border" size="sm" />
        </div>
      ) : (
        <>
          {/* Resumo do mês atual */}
          <Row className="g-3 mb-4">
            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
                <div className="stat-card-label">Receita bruta (mês)</div>
                <div className="stat-card-value">{formatCurrency(summary?.gross_revenue)}</div>
              </div>
            </Col>
            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
                <div className="stat-card-label">Recebido</div>
                <div className="stat-card-value success">{formatCurrency(summary?.received)}</div>
              </div>
            </Col>
            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
                <div className="stat-card-label">Pendente</div>
                <div className="stat-card-value warning">{formatCurrency(summary?.pending)}</div>
              </div>
            </Col>
            <Col xs={12} sm={6} lg={3}>
              <div className="stat-card">
                <div className="stat-card-label">Sessões concluídas</div>
                <div className="stat-card-value">{summary?.total_completed ?? 0}</div>
                <div className="stat-card-desc">
                  {summary?.total_cancelled ?? 0} cancelada(s) · {summary?.total_no_show ?? 0} falta(s)
                </div>
              </div>
            </Col>
          </Row>

          {/* Pagamentos pendentes */}
          <h6 className="mb-3 fw-bold">Pagamentos pendentes</h6>
          <div className="financial-table-card">
            {pending.length === 0 ? (
              <p className="text-muted text-center py-3 mb-0">Nenhum pagamento pendente.</p>
            ) : (
              <Table hover responsive className="mb-0">
                <thead>
                  <tr>
                    <th>Data</th>
                    <th>Paciente</th>
                    <th>Procedimento</th>
                    <th>Valor</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {pending.map((apt) => (
                    <tr key={apt.id}>
                      <td>{apt.formatted_start}</td>
                      <td>{apt.patient?.name}</td>
                      <td>{apt.service?.name}</td>
                      <td>{apt.formatted_price}</td>
                      <td>
                        <Button size="sm" variant="primary" onClick={() => setPayingAppointment(apt)}>
                          Registrar pagamento
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </div>
        </>
      )}

      <PaymentModal
        show={!!payingAppointment}
        appointment={payingAppointment}
        onClose={() => setPayingAppointment(null)}
        onSaved={handlePaymentSaved}
      />
    </div>
  )
}

function formatCurrency(value) {
  return (value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
