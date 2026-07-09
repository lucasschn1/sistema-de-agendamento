import { useCallback, useEffect, useState } from 'react'
import { Button, Table, Badge, Alert, Spinner, Form, InputGroup } from 'react-bootstrap'
import { listPatients, searchUsers, deactivateUser, restoreUser } from '../../api/users'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import { usePersistedState } from '../../hooks/usePersistedState'
import PatientFormModal from './PatientFormModal'
import ConfirmModal from '../../components/ConfirmModal'

export default function Patients() {
  const { showToast } = useToast()

  const [patients, setPatients] = useState([])
  const [loading, setLoading]   = useState(true)
  const [error, setError]       = useState('')

  const [search, setSearch]           = useState('')
  const [isTyping, setIsTyping]       = useState(false)
  const [showInactive, setShowInactive] = usePersistedState('patients:showInactive', false)

  const [showModal, setShowModal]   = useState(false)
  const [editingPatient, setEditingPatient] = useState(null)
  const [deactivatingPatient, setDeactivatingPatient] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const data = search.trim()
        ? await searchUsers(search.trim())
        : await listPatients(!showInactive)

      const onlyPatients = search.trim()
        ? data.filter((u) => u.role === 'patient')
        : data

      setPatients(onlyPatients)
    } catch (err) {
      setError(parseApiError(err))
    } finally {
      setLoading(false)
    }
  }, [search, showInactive])

  useEffect(() => {
    const timer = setTimeout(() => {
      setIsTyping(false)
      load()
    }, 300) // debounce da busca
    return () => clearTimeout(timer)
  }, [load])

  const handleDeactivate = async () => {
    try {
      await deactivateUser(deactivatingPatient.id)
      showToast('Paciente desativado com sucesso')
      await load()
    } catch (err) {
      showToast(parseApiError(err), 'danger')
    }
  }

  const handleReactivate = async (patient) => {
    try {
      await restoreUser(patient.id)
      showToast('Paciente reativado com sucesso')
      await load()
    } catch (err) {
      showToast(parseApiError(err), 'danger')
    }
  }

  const openCreate = () => {
    setEditingPatient(null)
    setShowModal(true)
  }

  const openEdit = (patient) => {
    setEditingPatient(patient)
    setShowModal(true)
  }

  const handleSaved = () => {
    showToast(editingPatient ? 'Paciente atualizado com sucesso' : 'Paciente criado com sucesso')
    load()
  }

  return (
    <div className="patients-page">
      <div className="patients-toolbar">
        <InputGroup className="patients-search">
          <Form.Control
            placeholder="Buscar por nome..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setIsTyping(true)
            }}
          />
          {(isTyping || loading) && (
            <InputGroup.Text>
              <Spinner animation="border" size="sm" />
            </InputGroup.Text>
          )}
        </InputGroup>

        <Form.Check
          type="switch"
          id="show-inactive"
          label="Mostrar inativos"
          checked={showInactive}
          onChange={(e) => setShowInactive(e.target.checked)}
        />

        <Button variant="primary" onClick={openCreate}>
          + Novo paciente
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
        ) : patients.length === 0 ? (
          <div className="text-center py-4">
            <p className="text-muted mb-3">Nenhum paciente encontrado.</p>
            <Button variant="outline-primary" size="sm" onClick={openCreate}>
              + Cadastrar paciente
            </Button>
          </div>
        ) : (
          <Table hover responsive className="mb-0">
            <thead>
              <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              {patients.map((p) => (
                <tr key={p.id}>
                  <td>{p.name}</td>
                  <td>{p.email}</td>
                  <td>
                    <Badge bg={p.active ? 'success' : 'secondary'}>
                      {p.active ? 'Ativo' : 'Inativo'}
                    </Badge>
                  </td>
                  <td>
                    <Button size="sm" variant="outline-primary" className="me-2" onClick={() => openEdit(p)}>
                      Editar
                    </Button>
                    {p.active ? (
                      <Button size="sm" variant="outline-danger" onClick={() => setDeactivatingPatient(p)}>
                        Desativar
                      </Button>
                    ) : (
                      <Button size="sm" variant="outline-success" onClick={() => handleReactivate(p)}>
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

      <PatientFormModal
        show={showModal}
        patient={editingPatient}
        onClose={() => setShowModal(false)}
        onSaved={handleSaved}
      />

      <ConfirmModal
        show={!!deactivatingPatient}
        title="Desativar paciente"
        message={`Tem certeza que deseja desativar ${deactivatingPatient?.name}? Ele(a) não poderá mais ser selecionado(a) em novos agendamentos até ser reativado(a).`}
        confirmLabel="Desativar"
        onClose={() => setDeactivatingPatient(null)}
        onConfirm={handleDeactivate}
      />
    </div>
  )
}
