import { Container, Row, Col, Card, Button, Navbar, Nav } from 'react-bootstrap'
import { useAuth } from '../../context/AuthContext'
import { useNavigate } from 'react-router-dom'

export default function Dashboard() {
  const { user, logout, isAdmin } = useAuth()
  const navigate = useNavigate()

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

  return (
    <>
      {/* Navbar */}
      <Navbar bg="primary" variant="dark" expand="lg" className="px-3">
        <Navbar.Brand href="/dashboard" className="fw-bold">
          ClinicaAme
        </Navbar.Brand>
        <Navbar.Toggle />
        <Navbar.Collapse className="justify-content-end">
          <Nav>
            <Nav.Item className="d-flex align-items-center text-white me-3">
              Olá, <strong className="ms-1">{user?.name}</strong>
            </Nav.Item>
            <Button variant="outline-light" size="sm" onClick={handleLogout}>
              Sair
            </Button>
          </Nav>
        </Navbar.Collapse>
      </Navbar>

      {/* Conteúdo */}
      <Container className="mt-4">
        <Row className="mb-4">
          <Col>
            <h4 className="fw-bold">Dashboard</h4>
            <p className="text-muted">
              Bem-vindo, {user?.name} —{' '}
              <span className="badge bg-primary">{user?.role}</span>
            </p>
          </Col>
        </Row>

        {/* Cards de resumo — placeholder por enquanto */}
        <Row className="g-3">
          <Col xs={12} sm={6} lg={3}>
            <Card className="border-0 shadow-sm text-center p-3">
              <Card.Body>
                <div className="fs-1 fw-bold text-primary">—</div>
                <div className="text-muted mt-1">Agendamentos hoje</div>
              </Card.Body>
            </Card>
          </Col>

          <Col xs={12} sm={6} lg={3}>
            <Card className="border-0 shadow-sm text-center p-3">
              <Card.Body>
                <div className="fs-1 fw-bold text-success">—</div>
                <div className="text-muted mt-1">Confirmados</div>
              </Card.Body>
            </Card>
          </Col>

          <Col xs={12} sm={6} lg={3}>
            <Card className="border-0 shadow-sm text-center p-3">
              <Card.Body>
                <div className="fs-1 fw-bold text-warning">—</div>
                <div className="text-muted mt-1">Pagamentos pendentes</div>
              </Card.Body>
            </Card>
          </Col>

          {isAdmin() && (
            <Col xs={12} sm={6} lg={3}>
              <Card className="border-0 shadow-sm text-center p-3">
                <Card.Body>
                  <div className="fs-1 fw-bold text-info">—</div>
                  <div className="text-muted mt-1">Receita do mês</div>
                </Card.Body>
              </Card>
            </Col>
          )}
        </Row>
      </Container>
    </>
  )
}