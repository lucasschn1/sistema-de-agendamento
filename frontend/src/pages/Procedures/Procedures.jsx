import { useCallback, useEffect, useState } from 'react'
import { Button, Table, Badge, Alert, Spinner, Form, InputGroup } from 'react-bootstrap'
import { listProcedures, deactivateProcedure, activateProcedure } from '../../api/procedures'
import { parseApiError } from '../../utils/apiError'
import { useToast } from '../../context/ToastContext'
import { usePersistedState } from '../../hooks/usePersistedState'
import ProcedureFormModal from './ProcedureFormModal'
import ConfirmModal from '../../components/ConfirmModal'

export default function Procedures() {
  const { showToast } = useToast()

  const [procedures, setProcedures] = useState([])
  const [loading, setLoading]       = useState(true)
  const [error, setError]           = useState('')

  const [search, setSearch]             = useState('')
  const [isTyping, setIsTyping]         = useState(false)
  const [showInactive, setShowInactive] = usePersistedState('procedures:showInactive', false)

  const [showModal, setShowModal]           = useState(false)
  const [editingProcedure, setEditingProcedure] = useState(null)
  const [deactivatingProcedure, setDeactivatingProcedure] = useState(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')

    try {
      const data = await listProcedures({
        active: showInactive ? 'false' : 'true',
        search: search.trim() || undefined,
      })
      setProcedures(data)
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
    }, 300)
    return () => clearTimeout(timer)
  }, [load])

  const handleDeactivate = async () => {
    try {
      await deactivateProcedure(deactivatingProcedure.id)
      showToast('Procedimento desativado com sucesso')
      await load()
    } catch (err) {
      showToast(parseApiError(err), 'danger')
    }
  }

  const handleActivate = async (procedure) => {
    try {
      await activateProcedure(procedure.id)
      showToast('Procedimento ativado com sucesso')
      await load()
    } catch (err) {
      showToast(parseApiError(err), 'danger')
    }
  }

  const openCreate = () => {
    setEditingProcedure(null)
    setShowModal(true)
  }

  const openEdit = (procedure) => {
    setEditingProcedure(procedure)
    setShowModal(true)
  }

  const handleSaved = () => {
    showToast(editingProcedure ? 'Procedimento atualizado com sucesso' : 'Procedimento criado com sucesso')
    load()
  }

  return (
    <div className="procedures-page">
      <div className="procedures-toolbar">
        <InputGroup className="patients-search">
          <Form.Control
            placeholder="Buscar procedimento..."
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
          id="show-inactive-procedures"
          label="Mostrar inativos"
          checked={showInactive}
          onChange={(e) => setShowInactive(e.target.checked)}
        />

        <Button variant="primary" onClick={openCreate}>
          + Novo procedimento
        </Button>
      </div>

      {error && (
        <Alert variant="danger" onClose={() => setError('')} dismissible>
          {error}
        </Alert>
      )}

      <div className="procedures-table-card">
        {loading ? (
          <div className="text-center py-4">
            <Spinner animation="border" size="sm" />
          </div>
        ) : procedures.length === 0 ? (
          <div className="text-center py-4">
            <p className="text-muted mb-3">Nenhum procedimento encontrado.</p>
            <Button variant="outline-primary" size="sm" onClick={openCreate}>
              + Cadastrar procedimento
            </Button>
          </div>
        ) : (
          <Table hover responsive className="mb-0">
            <thead>
              <tr>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Preço</th>
                <th>Duração</th>
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              {procedures.map((p) => (
                <tr key={p.id}>
                  <td>{p.name}</td>
                  <td>{p.category}</td>
                  <td>{p.formatted_price}</td>
                  <td>{p.formatted_duration}</td>
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
                      <Button size="sm" variant="outline-danger" onClick={() => setDeactivatingProcedure(p)}>
                        Desativar
                      </Button>
                    ) : (
                      <Button size="sm" variant="outline-success" onClick={() => handleActivate(p)}>
                        Ativar
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </div>

      <ProcedureFormModal
        show={showModal}
        procedure={editingProcedure}
        onClose={() => setShowModal(false)}
        onSaved={handleSaved}
      />

      <ConfirmModal
        show={!!deactivatingProcedure}
        title="Desativar procedimento"
        message={`Tem certeza que deseja desativar "${deactivatingProcedure?.name}"? Ele não poderá mais ser usado em novos agendamentos até ser reativado.`}
        confirmLabel="Desativar"
        onClose={() => setDeactivatingProcedure(null)}
        onConfirm={handleDeactivate}
      />
    </div>
  )
}
