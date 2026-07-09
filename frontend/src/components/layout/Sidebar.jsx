import { NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'

// =============================================
// ÍCONES SVG INLINE
// =============================================

const Icons = {
  Dashboard: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="3" width="7" height="7" rx="1" />
      <rect x="14" y="3" width="7" height="7" rx="1" />
      <rect x="3" y="14" width="7" height="7" rx="1" />
      <rect x="14" y="14" width="7" height="7" rx="1" />
    </svg>
  ),
  Calendar: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="4" width="18" height="18" rx="2" />
      <path d="M16 2v4M8 2v4M3 10h18" />
    </svg>
  ),
  Users: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
    </svg>
  ),
  Stethoscope: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6 6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3" />
      <path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4" />
      <circle cx="20" cy="10" r="2" />
    </svg>
  ),
  Cash: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <rect x="2" y="6" width="20" height="12" rx="2" />
      <circle cx="12" cy="12" r="3" />
      <path d="M6 12h.01M18 12h.01" />
    </svg>
  ),
  UserCog: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <circle cx="19" cy="11" r="2" />
      <path d="M19 9v-.5M19 13v-.5M17.3 10l-.4-.25M21.1 12l-.4-.25M17.3 12l-.4.25M21.1 10l-.4.25" />
    </svg>
  ),
  Logout: () => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
      <polyline points="16 17 21 12 16 7" />
      <line x1="21" y1="12" x2="9" y2="12" />
    </svg>
  ),
}

// =============================================
// COMPONENTE SIDEBAR
// =============================================

export default function Sidebar({ isOpen, onClose }) {
  const { user, logout, isAdmin } = useAuth()
  const navigate = useNavigate()

  const handleLogout = () => {
    logout()
    navigate('/login')
  }

  // Primeira letra do nome para o avatar
  const avatarLetter = user?.name?.charAt(0).toUpperCase() ?? 'U'

  const roleLabel = {
    admin:        'Administrador',
    professional: 'Profissional',
    patient:      'Paciente',
  }[user?.role] ?? user?.role

  return (
    <aside className={`sidebar${isOpen ? ' is-open' : ''}`}>

      {/* Fecha o menu — só visível em mobile */}
      <button className="sidebar-close" onClick={onClose} aria-label="Fechar menu">
        ✕
      </button>

      {/* Logo */}
      <div className="sidebar-logo">
        <p className="sidebar-logo-title">ClinicaAme</p>
        <p className="sidebar-logo-subtitle">Sistema de agendamento</p>
      </div>

      {/* Navegação */}
      <nav className="sidebar-nav">

        {/* Seção Geral */}
        <div className="sidebar-section-label">Geral</div>

        <NavLink
          to="/dashboard"
          className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}
        >
          <Icons.Dashboard />
          Dashboard
        </NavLink>

        <NavLink
          to="/appointments"
          className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}
        >
          <Icons.Calendar />
          Agendamentos
        </NavLink>

        <NavLink
          to="/patients"
          className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}
        >
          <Icons.Users />
          Pacientes
        </NavLink>

        {/* Seção Admin — só aparece para admins */}
        {isAdmin() && (
          <>
            <div className="sidebar-section-label" style={{ marginTop: 8 }}>Admin</div>

            <NavLink
              to="/procedures"
              className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}
            >
              <Icons.Stethoscope />
              Procedimentos
            </NavLink>

            <NavLink
              to="/financial"
              className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}
            >
              <Icons.Cash />
              Financeiro
            </NavLink>

            <NavLink
              to="/users"
              className={({ isActive }) => `sidebar-link${isActive ? ' active' : ''}`}
            >
              <Icons.UserCog />
              Usuários
            </NavLink>
          </>
        )}
      </nav>

      {/* Usuário logado */}
      <div className="sidebar-user">
        <div className="sidebar-avatar">{avatarLetter}</div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <p className="sidebar-user-name">{user?.name}</p>
          <p className="sidebar-user-role">{roleLabel}</p>
        </div>
        <button
          className="sidebar-logout"
          onClick={handleLogout}
          title="Sair"
        >
          <Icons.Logout />
        </button>
      </div>

    </aside>
  )
}
