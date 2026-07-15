import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner } from 'react-bootstrap'
import { createProcedure, updateProcedure } from '../../api/procedures'
import { parseApiError, parseApiFieldErrors } from '../../utils/apiError'

const CATEGORIES = ['Individual', 'Casal', 'Familiar', 'Grupo', 'Avaliação']

// =============================================
// MODAL — CRIAR/EDITAR PROCEDIMENTO
// Só cadastra o serviço em si (nome, categoria, duração) — o valor cobrado é
// definido individualmente em cada agendamento, não fica fixo aqui
// =============================================

export default function ProcedureFormModal({ show, procedure, onClose, onSaved }) {
  const isEditing = !!procedure

  const [name, setName]         = useState('')
  const [description, setDescription] = useState('')
  const [duration, setDuration] = useState('')
  const [category, setCategory] = useState('')

  const [error, setError]           = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [saving, setSaving]         = useState(false)

  useEffect(() => {
    if (!show) return

    setError('')
    setFieldErrors({})
    setName(procedure?.name ?? '')
    setDescription(procedure?.description ?? '')
    setDuration(procedure?.duration_minutes ?? '')
    setCategory(procedure?.category ?? '')
  }, [show, procedure])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    setSaving(true)

    const payload = {
      name,
      description,
      duration_minutes: Number(duration),
      category,
    }

    try {
      if (isEditing) {
        await updateProcedure(procedure.id, payload)
      } else {
        await createProcedure(payload)
      }

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
        <Modal.Title>{isEditing ? 'Editar procedimento' : 'Novo procedimento'}</Modal.Title>
      </Modal.Header>

      <Form onSubmit={handleSubmit}>
        <Modal.Body>
          {error && (
            <Alert variant="danger" onClose={() => setError('')} dismissible>
              {error}
            </Alert>
          )}

          <Form.Group className="mb-3">
            <Form.Label>Nome</Form.Label>
            <Form.Control
              value={name}
              onChange={(e) => setName(e.target.value)}
              isInvalid={!!fieldErrors.name}
              required
              autoFocus
            />
            <Form.Control.Feedback type="invalid">{fieldErrors.name}</Form.Control.Feedback>
          </Form.Group>

          <Form.Group className="mb-3">
            <Form.Label>Categoria</Form.Label>
            <Form.Select
              value={category}
              onChange={(e) => setCategory(e.target.value)}
              isInvalid={!!fieldErrors.category}
              required
            >
              <option value="">Selecione...</option>
              {CATEGORIES.map((c) => (
                <option key={c} value={c}>{c}</option>
              ))}
            </Form.Select>
            <Form.Control.Feedback type="invalid">{fieldErrors.category}</Form.Control.Feedback>
          </Form.Group>

          <Form.Group className="mb-3">
            <Form.Label>Duração (min)</Form.Label>
            <Form.Control
              type="number"
              min="1"
              value={duration}
              onChange={(e) => setDuration(e.target.value)}
              isInvalid={!!fieldErrors.duration_minutes}
              required
            />
            <Form.Control.Feedback type="invalid">{fieldErrors.duration_minutes}</Form.Control.Feedback>
          </Form.Group>

          <Form.Group>
            <Form.Label>Descrição <span className="text-muted">(opcional)</span></Form.Label>
            <Form.Control
              as="textarea"
              rows={2}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
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
