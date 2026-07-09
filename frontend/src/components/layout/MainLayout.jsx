import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'
import Topbar from './Topbar'

// =============================================
// COMPONENTE MAINLAYOUT
// Estrutura base das páginas autenticadas:
// Sidebar fixa à esquerda + Topbar + conteúdo rolável
// =============================================

export default function MainLayout({ title, children }) {
  return (
    <div className="app-layout">
      <Sidebar />

      <div className="app-content">
        <Topbar title={title} />

        <main className="app-main">
          {children ?? <Outlet />}
        </main>
      </div>
    </div>
  )
}
