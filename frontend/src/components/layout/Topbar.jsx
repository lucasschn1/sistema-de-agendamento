import { useLocation } from 'react-router-dom'

// =============================================
// TÍTULOS DE PÁGINA POR ROTA
// =============================================

const PAGE_TITLES = {
  '/dashboard':    'Dashboard',
  '/appointments': 'Agendamentos',
  '/patients':     'Pacientes',
  '/procedures':   'Procedimentos',
  '/financial':    'Financeiro',
  '/users':        'Usuários',
}

function resolveTitle(pathname) {
  const match = Object.keys(PAGE_TITLES).find((path) => pathname.startsWith(path))
  return match ? PAGE_TITLES[match] : 'ClinicaAme'
}

// =============================================
// COMPONENTE TOPBAR
// =============================================

export default function Topbar({ title, children, onToggleSidebar }) {
  const location = useLocation()

  const pageTitle = title ?? resolveTitle(location.pathname)

  const today = new Date().toLocaleDateString('pt-BR', {
    weekday: 'long',
    day:     'numeric',
    month:   'long',
    year:    'numeric',
  })

  return (
    <header className="topbar">
      <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
        <button className="topbar-menu-btn" onClick={onToggleSidebar} aria-label="Abrir menu">
          ☰
        </button>
        <h1 className="topbar-title">{pageTitle}</h1>
      </div>

      <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
        {children}
        <span className="topbar-date">{today}</span>
      </div>
    </header>
  )
}
