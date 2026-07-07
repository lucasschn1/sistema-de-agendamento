import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Container, Row, Col, Card, Form, Button, Alert, Spinner } from 'react-bootstrap'
import { useAuth } from '../../context/AuthContext'

export default function Login() {
  const navigate        = useNavigate()
  const { login }       = useAuth()

  const [email, setEmail]       = useState('')
  const [password, setPassword] = useState('')
  const [error, setError]       = useState('')
  const [loading, setLoading]   = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    try {
      await login(email, password)
      navigate('/dashboard')

    } catch (err) {
      // Trata erros da API
      const apiError = err.response?.data?.error

      if (apiError?.errors) {
        // Erro de validação (422)
        const messages = Object.values(apiError.errors).join(', ')
        setError(messages)
      } else if (apiError?.message) {
        // Erro de negócio (401, 403, etc.)
        setError(apiError.message)
      } else {
        setError('Erro ao conectar com o servidor. Tente novamente.')
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <Container fluid className="vh-100 d-flex align-items-center justify-content-center bg-light">
      <Row className="w-100 justify-content-center">
        <Col xs={12} sm={8} md={6} lg={4}>

          {/* Logo / Título */}
          <div className="text-center mb-4">
            <h2 className="fw-bold text-primary">ClinicaAme</h2>
            <p className="text-muted">Sistema de Agendamento</p>
          </div>

          {/* Card de Login */}
          <Card className="shadow-sm border-0">
            <Card.Body className="p-4">
              <h5 className="card-title mb-4 text-center">Entrar</h5>

              {/* Mensagem de erro */}
              {error && (
                <Alert variant="danger" onClose={() => setError('')} dismissible>
                  {error}
                </Alert>
              )}

              <Form onSubmit={handleSubmit}>
                {/* Email */}
                <Form.Group className="mb-3">
                  <Form.Label>E-mail</Form.Label>
                  <Form.Control
                    type="email"
                    placeholder="seu@email.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    autoFocus
                    disabled={loading}
                  />
                </Form.Group>

                {/* Senha */}
                <Form.Group className="mb-4">
                  <Form.Label>Senha</Form.Label>
                  <Form.Control
                    type="password"
                    placeholder="••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    disabled={loading}
                  />
                </Form.Group>

                {/* Botão */}
                <Button
                  type="submit"
                  variant="primary"
                  className="w-100"
                  disabled={loading}
                >
                  {loading ? (
                    <>
                      <Spinner
                        as="span"
                        animation="border"
                        size="sm"
                        className="me-2"
                      />
                      Entrando...
                    </>
                  ) : (
                    'Entrar'
                  )}
                </Button>
              </Form>
            </Card.Body>
          </Card>

        </Col>
      </Row>
    </Container>
  )
}