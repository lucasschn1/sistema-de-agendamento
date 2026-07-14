import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import MainLayout from '../components/layout/MainLayout'

// Pages
import Login          from '../pages/Login/Login'
import ForgotPassword from '../pages/Login/ForgotPassword'
import Dashboard      from '../pages/Dashboard/Dashboard'
import Appointments   from '../pages/Appointments/Appointments'
import Patients       from '../pages/Patients/Patients'
import Procedures     from '../pages/Procedures/Procedures'
import Financial      from '../pages/Financial/Financial'
import Users          from '../pages/Users/Users'
import Rentals        from '../pages/Rentals/Rentals'

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
      <Route
        path="/forgot-password"
        element={
          <PublicRoute>
            <ForgotPassword />
          </PublicRoute>
        }
      />

      {/* Rotas protegidas — todas usam o MainLayout (sidebar + topbar) */}
      <Route
        element={
          <PrivateRoute>
            <MainLayout />
          </PrivateRoute>
        }
      >
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/appointments" element={<Appointments />} />
        <Route path="/patients" element={<Patients />} />
        <Route path="/procedures" element={<Procedures />} />
        <Route path="/financial" element={<Financial />} />
        <Route path="/users" element={<Users />} />
        <Route path="/rentals" element={<Rentals />} />
      </Route>

      {/* Rota 404 — redireciona para dashboard */}
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}
