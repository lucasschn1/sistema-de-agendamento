import { useState, useEffect } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import Sidebar from './Sidebar'
import Topbar from './Topbar'

// =============================================
// COMPONENTE MAINLAYOUT
// Estrutura base das páginas autenticadas:
// Sidebar fixa à esquerda + Topbar + conteúdo rolável
// Em telas estreitas, a sidebar vira um menu off-canvas (hambúrguer)
// =============================================

export default function MainLayout({ title, children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const location = useLocation()

  // Fecha o menu mobile automaticamente ao navegar
  useEffect(() => {
    setSidebarOpen(false)
  }, [location.pathname])

  return (
    <div className="app-layout">
      <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      {sidebarOpen && <div className="sidebar-backdrop" onClick={() => setSidebarOpen(false)} />}

      <div className="app-content">
        <Topbar title={title} onToggleSidebar={() => setSidebarOpen((v) => !v)} />

        <main className="app-main">
          {children ?? <Outlet />}
        </main>
      </div>
    </div>
  )
}
