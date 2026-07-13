import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner } from 'react-bootstrap'
import { createPatient, updateUser } from '../../api/users'
import { parseApiError, parseApiFieldErrors } from '../../utils/apiError'
import { maskCpf, maskPhone } from '../../utils/masks'
import { useEmailAvailability } from '../../hooks/useEmailAvailability'
import PasswordInput from '../../components/PasswordInput'

// =============================================
// MODAL — CRIAR/EDITAR PACIENTE
// =============================================

export default function PatientFormModal({ show, patient, onClose, onSaved }) {
  const isEditing = !!patient

  const [name, setName]         = useState('')
  const [email, setEmail]       = useState('')
  const [password, setPassword] = useState('')
  const [cpf, setCpf]           = useState('')
  const [phone, setPhone]       = useState('')
  const [birthdate, setBirthdate] = useState('')

  const [error, setError]           = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [saving, setSaving]         = useState(false)

  const emailChanged = !isEditing || email !== patient?.email
  const { checking: checkingEmail, exists: emailTaken } = useEmailAvailability(email, {
    excludeId: patient?.id,
    enabled: show && emailChanged,
  })

  useEffect(() => {
    if (!show) return

    setError('')
    setFieldErrors({})
    setName(patient?.name ?? '')
    setEmail(patient?.email ?? '')
    setPassword('')
    setCpf('')
    setPhone('')
    setBirthdate('')
  }, [show, patient])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    setSaving(true)

    try {
      if (isEditing) {
        await updateUser(patient.id, { name, email })
      } else {
        await createPatient({ name, email, password, cpf, phone, birthdate })
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
        <Modal.Title>{isEditing ? 'Editar paciente' : 'Novo paciente'}</Modal.Title>
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
            <Form.Label>E-mail</Form.Label>
            <Form.Control
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              isInvalid={!!fieldErrors.email || emailTaken}
              required
            />
            <Form.Control.Feedback type="invalid">
              {fieldErrors.email || (emailTaken && 'Este e-mail já está cadastrado')}
            </Form.Control.Feedback>
            {checkingEmail && <div className="form-text">Verificando disponibilidade...</div>}
          </Form.Group>

          {!isEditing && (
            <>
              <Form.Group className="mb-3">
                <Form.Label>Senha</Form.Label>
                <PasswordInput
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  isInvalid={!!fieldErrors.password}
                  feedback={fieldErrors.password}
                  required
                />
              </Form.Group>

              <Form.Group className="mb-3">
                <Form.Label>CPF <span className="text-muted">(opcional)</span></Form.Label>
                <Form.Control
                  value={cpf}
                  onChange={(e) => setCpf(maskCpf(e.target.value))}
                  isInvalid={!!fieldErrors.cpf}
                  placeholder="000.000.000-00"
                />
                <Form.Control.Feedback type="invalid">{fieldErrors.cpf}</Form.Control.Feedback>
              </Form.Group>

              <Form.Group className="mb-3">
                <Form.Label>Telefone <span className="text-muted">(opcional)</span></Form.Label>
                <Form.Control
                  value={phone}
                  onChange={(e) => setPhone(maskPhone(e.target.value))}
                  isInvalid={!!fieldErrors.phone}
                  placeholder="(00) 00000-0000"
                />
                <Form.Control.Feedback type="invalid">{fieldErrors.phone}</Form.Control.Feedback>
              </Form.Group>

              <Form.Group>
                <Form.Label>Data de nascimento <span className="text-muted">(opcional)</span></Form.Label>
                <Form.Control
                  type="date"
                  value={birthdate}
                  onChange={(e) => setBirthdate(e.target.value)}
                />
              </Form.Group>
            </>
          )}
        </Modal.Body>

        <Modal.Footer>
          <Button variant="outline-secondary" onClick={onClose} disabled={saving}>
            Cancelar
          </Button>
          <Button variant="primary" type="submit" disabled={saving || emailTaken}>
            {saving ? <Spinner as="span" animation="border" size="sm" /> : 'Salvar'}
          </Button>
        </Modal.Footer>
      </Form>
    </Modal>
  )
}
