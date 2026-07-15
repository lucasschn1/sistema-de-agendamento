import { Modal, Button } from 'react-bootstrap'

const ROLE_LABELS = {
  admin: 'Administrador',
  professional: 'Profissional',
  patient: 'Paciente',
}

function formatDate(value) {
  if (!value) return null
  const date = new Date(`${value.slice(0, 10)}T00:00:00`)
  return date.toLocaleDateString('pt-BR')
}

function formatDateTime(value) {
  if (!value) return null
  const date = new Date(value.replace(' ', 'T'))
  return date.toLocaleDateString('pt-BR')
}

// =============================================
// MODAL — DETALHES DO PACIENTE/USUÁRIO (SOMENTE LEITURA)
// =============================================

export default function PersonDetailModal({ show, person, onClose, onEdit }) {
  if (!person) return null

  const isProfessional = person.role === 'professional'

  return (
    <Modal show={show} onHide={onClose} centered>
      <Modal.Header closeButton>
        <Modal.Title>Detalhes</Modal.Title>
      </Modal.Header>

      <Modal.Body>
        <div className="person-detail-header">
          <span className="person-detail-avatar">{person.name?.charAt(0).toUpperCase()}</span>
          <div>
            <p className="person-detail-name">{person.name}</p>
            <div className="person-detail-badges">
              <span className={person.active ? 'status-pill-active' : 'status-pill-inactive'}>
                {person.active ? 'Ativo' : 'Inativo'}
              </span>
              {person.role && (
                <span className="person-detail-role-pill">
                  {isProfessional && person.professional_type ? person.professional_type : ROLE_LABELS[person.role] ?? person.role}
                </span>
              )}
            </div>
          </div>
        </div>

        <div className="person-detail-grid">
          <div className="person-detail-field">
            <span className="person-detail-label">E-mail</span>
            <span className="person-detail-value">{person.email || '—'}</span>
          </div>

          <div className="person-detail-field">
            <span className="person-detail-label">Telefone</span>
            <span className="person-detail-value">{person.phone || '—'}</span>
          </div>

          <div className="person-detail-field">
            <span className="person-detail-label">CPF</span>
            <span className="person-detail-value">{person.cpf || '—'}</span>
          </div>

          <div className="person-detail-field">
            <span className="person-detail-label">Data de nascimento</span>
            <span className="person-detail-value">{formatDate(person.birthdate) || '—'}</span>
          </div>

          {isProfessional && (
            <>
              <div className="person-detail-field">
                <span className="person-detail-label">Conselho (CRP/CRM)</span>
                <span className="person-detail-value">{person.council_id || '—'}</span>
              </div>

              <div className="person-detail-field">
                <span className="person-detail-label">Especialidade</span>
                <span className="person-detail-value">{person.specialty || '—'}</span>
              </div>
            </>
          )}

          <div className="person-detail-field">
            <span className="person-detail-label">Cadastrado em</span>
            <span className="person-detail-value">{formatDateTime(person.created_at) || '—'}</span>
          </div>
        </div>

        {isProfessional && person.bio && (
          <div className="person-detail-bio">
            <span className="person-detail-label">Bio</span>
            <p className="person-detail-value mb-0">{person.bio}</p>
          </div>
        )}
      </Modal.Body>

      <Modal.Footer>
        <Button variant="outline-secondary" onClick={onClose}>
          Fechar
        </Button>
        {onEdit && (
          <Button variant="primary" onClick={() => onEdit(person)}>
            Editar
          </Button>
        )}
      </Modal.Footer>
    </Modal>
  )
}
