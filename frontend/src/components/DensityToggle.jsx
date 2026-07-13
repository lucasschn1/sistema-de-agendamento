import { Form } from 'react-bootstrap'
import { usePersistedState } from '../hooks/usePersistedState'

// Preferência compartilhada de densidade das tabelas de listagem (Pacientes, Usuários, Procedimentos)
export function useTableDensity() {
  return usePersistedState('ui:tableDensity', 'comfortable')
}

export default function DensityToggle({ density, onChange }) {
  return (
    <Form.Check
      type="switch"
      id="table-density-toggle"
      label="Modo compacto"
      className="density-toggle"
      checked={density === 'compact'}
      onChange={(e) => onChange(e.target.checked ? 'compact' : 'comfortable')}
    />
  )
}
