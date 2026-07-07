import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

// Pages
import Login     from '../pages/Login/Login'
import Dashboard from '../pages/Dashboard/Dashboard'

// =========================================================
// ROTA PROTEGIDA
// Redireciona para /login se não estiver autenticado
// =========================================================
function PrivateRoute({ children }) {
  const { isAuthenticated, loading } = useAuth()

  // Aguarda verificar o token salvo antes de redirecionar
  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center vh-100">
        <div className="spinner-border text-primary" role="status">
          <span className="visually-hidden">Carregando...</span>
        </div>
      </div>
    )
  }

  return isAuthenticated() ? children : <Navigate to="/login" replace />
}

// =========================================================
// ROTA PÚBLICA
// Redireciona para /dashboard se já estiver autenticado
// =========================================================
function PublicRoute({ children }) {
  const { isAuthenticated, loading } = useAuth()

  if (loading) return null

  return !isAuthenticated() ? children : <Navigate to="/dashboard" replace />
}

// =========================================================
// ROTAS DA APLICAÇÃO
// =========================================================
export default function AppRoutes() {
  return (
    <Routes>
      {/* Rota raiz — redireciona para dashboard ou login */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />

      {/* Rotas públicas */}
      <Route
        path="/login"
        element={
          <PublicRoute>
            <Login />
          </PublicRoute>
        }
      />

      {/* Rotas protegidas */}
      <Route
        path="/dashboard"
        element={
          <PrivateRoute>
            <Dashboard />
          </PrivateRoute>
        }
      />

      {/* Rota 404 — redireciona para dashboard */}
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}