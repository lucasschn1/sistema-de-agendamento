import { Table, Button } from 'react-bootstrap'

// =============================================
// SESSÕES AGUARDANDO REGISTRO DE PAGAMENTO
// Não são cobranças vencidas — são atendimentos já concluídos cujo
// pagamento ainda não foi registrado no sistema
// =============================================

export default function PendingSessionsTable({ sessions, onRegisterPayment }) {
  return (
    <div className="financial-table-card">
      {sessions.length === 0 ? (
        <p className="text-muted text-center py-3 mb-0">Nenhuma sessão aguardando registro de pagamento.</p>
      ) : (
        <Table hover responsive className="mb-0">
          <thead>
            <tr>
              <th>Data/hora da sessão</th>
              <th>Paciente</th>
              <th>Profissional</th>
              <th>Procedimento</th>
              <th>Valor</th>
              <th>Método</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            {sessions.map((apt) => (
              <tr key={apt.id} className="pending-session-row">
                <td>{apt.formatted_start}</td>
                <td>{apt.patient?.name}</td>
                <td>{apt.professional?.name}</td>
                <td>{apt.service?.name}</td>
                <td>{apt.formatted_price}</td>
                <td>{apt.payment_method ?? '—'}</td>
                <td>
                  <Button size="sm" variant="primary" onClick={() => onRegisterPayment(apt)}>
                    Registrar pagamento
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}
