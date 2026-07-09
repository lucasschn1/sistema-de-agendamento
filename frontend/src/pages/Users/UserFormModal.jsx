import { useEffect, useState } from 'react'
import { Modal, Form, Button, Alert, Spinner } from 'react-bootstrap'
import { createProfessional, createAdmin, updateUser } from '../../api/users'
import { parseApiError, parseApiFieldErrors } from '../../utils/apiError'

const PROFESSIONAL_TYPES = ['Psicólogo', 'Psicopedagogo', 'Neuropsicólogo', 'Terapeuta Ocupacional']

// =============================================
// MODAL — CRIAR/EDITAR PROFISSIONAL OU ADMINISTRADOR
// =============================================

export default function UserFormModal({ show, user, onClose, onSaved }) {
  const isEditing = !!user

  const [role, setRole]         = useState('professional')
  const [name, setName]         = useState('')
  const [email, setEmail]       = useState('')
  const [password, setPassword] = useState('')
  const [professionalType, setProfessionalType] = useState('')
  const [councilId, setCouncilId] = useState('')
  const [specialty, setSpecialty] = useState('')
  const [bio, setBio]           = useState('')

  const [error, setError]           = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [saving, setSaving]         = useState(false)

  useEffect(() => {
    if (!show) return

    setError('')
    setFieldErrors({})
    setRole(user?.role ?? 'professional')
    setName(user?.name ?? '')
    setEmail(user?.email ?? '')
    setPassword('')
    setProfessionalType(user?.professional_type ?? '')
    setCouncilId(user?.council_id ?? '')
    setSpecialty(user?.specialty ?? '')
    setBio(user?.bio ?? '')
  }, [show, user])

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    setSaving(true)

    try {
      if (isEditing) {
        const payload = { name, email }
        if (role === 'professional') {
          Object.assign(payload, {
            professional_type: professionalType,
            council_id: councilId,
            specialty,
            bio,
          })
        }
        await updateUser(user.id, payload)
      } else if (role === 'admin') {
        await createAdmin({ name, email, password })
      } else {
        await createProfessional({
          name,
          email,
          password,
          professional_type: professionalType,
          council_id: councilId,
          specialty,
          bio,
        })
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
        <Modal.Title>{isEditing ? 'Editar usuário' : 'Novo usuário'}</Modal.Title>
      </Modal.Header>

      <Form onSubmit={handleSubmit}>
        <Modal.Body>
          {error && (
            <Alert variant="danger" onClose={() => setError('')} dismissible>
              {error}
            </Alert>
          )}

          {!isEditing && (
            <Form.Group className="mb-3">
              <Form.Label>Tipo</Form.Label>
              <Form.Select value={role} onChange={(e) => setRole(e.target.value)}>
                <option value="professional">Profissional</option>
                <option value="admin">Administrador</option>
              </Form.Select>
            </Form.Group>
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
              isInvalid={!!fieldErrors.email}
              required
            />
            <Form.Control.Feedback type="invalid">{fieldErrors.email}</Form.Control.Feedback>
          </Form.Group>

          {!isEditing && (
            <Form.Group className="mb-3">
              <Form.Label>Senha</Form.Label>
              <Form.Control
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                isInvalid={!!fieldErrors.password}
                minLength={6}
                required
              />
              <Form.Control.Feedback type="invalid">{fieldErrors.password}</Form.Control.Feedback>
            </Form.Group>
          )}

          {role === 'professional' && (
            <>
              <Form.Group className="mb-3">
                <Form.Label>Tipo de profissional</Form.Label>
                <Form.Select
                  value={professionalType}
                  onChange={(e) => setProfessionalType(e.target.value)}
                  isInvalid={!!fieldErrors.professional_type}
                  required
                >
                  <option value="">Selecione...</option>
                  {PROFESSIONAL_TYPES.map((t) => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </Form.Select>
                <Form.Control.Feedback type="invalid">{fieldErrors.professional_type}</Form.Control.Feedback>
              </Form.Group>

              <Form.Group className="mb-3">
                <Form.Label>Registro no conselho <span className="text-muted">(opcional)</span></Form.Label>
                <Form.Control
                  value={councilId}
                  onChange={(e) => setCouncilId(e.target.value)}
                  placeholder="Ex: CRP 06/123456"
                />
              </Form.Group>

              <Form.Group className="mb-3">
                <Form.Label>Especialidade <span className="text-muted">(opcional)</span></Form.Label>
                <Form.Control value={specialty} onChange={(e) => setSpecialty(e.target.value)} />
              </Form.Group>

              <Form.Group>
                <Form.Label>Bio <span className="text-muted">(opcional)</span></Form.Label>
                <Form.Control
                  as="textarea"
                  rows={2}
                  value={bio}
                  onChange={(e) => setBio(e.target.value)}
                />
              </Form.Group>
            </>
          )}
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
