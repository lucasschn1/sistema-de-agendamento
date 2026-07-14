import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner } from 'react-bootstrap'
import { payRentalInvoice } from '../../api/rentals'
import { getPaymentMethods } from '../../api/financial'
import { parseApiError } from '../../utils/apiError'

// =============================================
// MODAL — MARCAR FATURA DE SUBLOCAÇÃO COMO PAGA
// =============================================

export default function RentalPayInvoiceModal({ show, invoice, onClose, onSaved }) {
  const [methods, setMethods] = useState([])
  const [method, setMethod] = useState('')

  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!show) return
    setError('')
    setMethod('')
    getPaymentMethods().then(setMethods).catch(() => {})
  }, [show])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setSaving(true)

    try {
      await payRentalInvoice(invoice.id, method)
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
        <Modal.Title>Marcar fatura como paga</Modal.Title>
      </Modal.Header>

      <Form onSubmit={handleSubmit}>
        <Modal.Body>
          {error && (
            <Alert variant="danger" onClose={() => setError('')} dismissible>
              {error}
            </Alert>
          )}

          {invoice && (
            <p className="text-muted mb-3">
              {invoice.tenant?.name} — {invoice.reference_month} ({invoice.formatted_amount})
            </p>
          )}

          <Form.Group>
            <Form.Label>Método de pagamento</Form.Label>
            <Form.Select value={method} onChange={(e) => setMethod(e.target.value)} required>
              <option value="">Selecione...</option>
              {methods.map((m) => (
                <option key={m} value={m}>{m}</option>
              ))}
            </Form.Select>
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
