import { useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'

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
  const navigate = useNavigate()
  const { logout } = useAuth()

  const pageTitle = title ?? resolveTitle(location.pathname)

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

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
        <button className="topbar-logout" onClick={handleLogout} title="Sair" aria-label="Sair">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
        </button>
      </div>
    </header>
  )
}
