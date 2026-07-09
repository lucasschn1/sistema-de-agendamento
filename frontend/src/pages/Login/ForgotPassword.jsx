import { Link } from 'react-router-dom'
import { Container, Row, Col, Card } from 'react-bootstrap'

export default function ForgotPassword() {
  return (
    <Container fluid className="vh-100 d-flex align-items-center justify-content-center bg-light">
      <Row className="w-100 justify-content-center">
        <Col xs={12} sm={8} md={6} lg={4}>
          <div className="text-center mb-4">
            <h2 className="fw-bold text-primary">ClinicaAme</h2>
            <p className="text-muted">Sistema de Agendamento</p>
          </div>

          <Card className="shadow-sm border-0">
            <Card.Body className="p-4">
              <h5 className="card-title mb-3 text-center">Esqueci minha senha</h5>

              <p className="text-muted">
                Por segurança, a redefinição de senha é feita pela administração da clínica.
                Entre em contato com o(a) administrador(a) do sistema informando seu e-mail de
                cadastro para receber uma nova senha de acesso.
              </p>

              <div className="text-center mt-4">
                <Link to="/login">Voltar para o login</Link>
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </Container>
  )
}
