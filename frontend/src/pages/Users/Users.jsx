import { useCallback, useEffect, useState } from 'react'
import { Button, Table, Badge, Alert, Spinner, Form, Nav } from 'react-bootstrap'
import { listUsers, deactivateUser, restoreUser } from '../../api/users'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import UserFormModal from './UserFormModal'
import ResetPasswordModal from './ResetPasswordModal'
import ConfirmModal from '../../components/ConfirmModal'

export default function Users() {
  const { showToast } = useToast()

  const [role, setRole] = useState('professional') // 'professional' | 'admin'
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState('')
  const [showInactive, setShowInactive] = useState(false)

  const [showModal, setShowModal]   = useState(false)
  const [editingUser, setEditingUser] = useState(null)
  const [resettingUser, setResettingUser] = useState(null)
  const [deactivatingUser, setDeactivatingUser] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const data = await listUsers(role, !showInactive)
      setUsers(data)
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [role, showInactive])

  useEffect(() => {
    load()
  }, [load])

  const handleDeactivate = async () => {
    try {
      await deactivateUser(deactivatingUser.id)
      showToast('Usuário desativado com sucesso')
      await load()
    } catch (err) {
      showToast(parseApiError(err), 'danger')
    }
  }

  const handleReactivate = async (user) => {
    try {
      await restoreUser(user.id)
      showToast('Usuário reativado com sucesso')
      await load()
    } catch (err) {
      showToast(parseApiError(err), 'danger')
    }
  }

  const openCreate = () => {
    setEditingUser(null)
    setShowModal(true)
  }

  const openEdit = (user) => {
    setEditingUser(user)
    setShowModal(true)
  }

  const handleSaved = () => {
    showToast(editingUser ? 'Usuário atualizado com sucesso' : 'Usuário criado com sucesso')
    load()
  }

  const handlePasswordReset = () => {
    showToast('Senha redefinida com sucesso')
    load()
  }

  return (
    <div className="users-page">
      <Nav variant="pills" activeKey={role} onSelect={setRole} className="users-role-tabs mb-3">
        <Nav.Item>
          <Nav.Link eventKey="professional">Profissionais</Nav.Link>
        </Nav.Item>
        <Nav.Item>
          <Nav.Link eventKey="admin">Administradores</Nav.Link>
        </Nav.Item>
      </Nav>

      <div className="patients-toolbar">
        <Form.Check
          type="switch"
          id="show-inactive-users"
          label="Mostrar inativos"
          checked={showInactive}
          onChange={(e) => setShowInactive(e.target.checked)}
        />

        <Button variant="primary" onClick={openCreate}>
          + Novo usuário
        </Button>
      </div>

      {error && (
        <Alert variant="danger" onClose={() => setError('')} dismissible>
          {error}
        </Alert>
      )}

      <div className="patients-table-card">
        {loading ? (
          <div className="text-center py-4">
            <Spinner animation="border" size="sm" />
          </div>
        ) : users.length === 0 ? (
          <div className="text-center py-4">
            <p className="text-muted mb-3">Nenhum usuário encontrado.</p>
            <Button variant="outline-primary" size="sm" onClick={openCreate}>
              + Cadastrar usuário
            </Button>
          </div>
        ) : (
          <Table hover responsive className="mb-0">
            <thead>
              <tr>
                <th>Nome</th>
                <th>E-mail</th>
                {role === 'professional' && <th>Tipo</th>}
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td>{u.name}</td>
                  <td>{u.email}</td>
                  {role === 'professional' && <td>{u.professional_type}</td>}
                  <td>
                    <Badge bg={u.active ? 'success' : 'secondary'}>
                      {u.active ? 'Ativo' : 'Inativo'}
                    </Badge>
                  </td>
                  <td>
                    <Button size="sm" variant="outline-primary" className="me-2" onClick={() => openEdit(u)}>
                      Editar
                    </Button>
                    <Button size="sm" variant="outline-secondary" className="me-2" onClick={() => setResettingUser(u)}>
                      Redefinir senha
                    </Button>
                    {u.active ? (
                      <Button size="sm" variant="outline-danger" onClick={() => setDeactivatingUser(u)}>
                        Desativar
                      </Button>
                    ) : (
                      <Button size="sm" variant="outline-success" onClick={() => handleReactivate(u)}>
                        Reativar
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </div>

      <UserFormModal
        show={showModal}
        user={editingUser}
        onClose={() => setShowModal(false)}
        onSaved={handleSaved}
      />

      <ResetPasswordModal
        show={!!resettingUser}
        user={resettingUser}
        onClose={() => setResettingUser(null)}
        onSaved={handlePasswordReset}
      />

      <ConfirmModal
        show={!!deactivatingUser}
        title="Desativar usuário"
        message={`Tem certeza que deseja desativar ${deactivatingUser?.name}? Ele(a) não poderá mais acessar o sistema até ser reativado(a).`}
        confirmLabel="Desativar"
        onClose={() => setDeactivatingUser(null)}
        onConfirm={handleDeactivate}
      />
    </div>
  )
}
