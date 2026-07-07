import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import api from '../api/axios'

// =========================================================
// CONTEXTO
// =========================================================

const AuthContext = createContext(null)

// =========================================================
// PROVIDER
// =========================================================

export function AuthProvider({ children }) {
  const [user, setUser]       = useState(null)
  const [loading, setLoading] = useState(true) // true enquanto verifica token salvo

  // Ao montar, verifica se há token salvo e carrega o usuário
  useEffect(() => {
    const token = localStorage.getItem('access_token')

    if (token) {
      loadUser()
    } else {
      setLoading(false)
    }
  }, [])

  // Busca dados do usuário logado via GET /api/me
  const loadUser = useCallback(async () => {
    try {
      const response = await api.get('/api/me')
      setUser(response.data.data)
    } catch {
      // Token inválido ou expirado — limpa o estado
      logout()
    } finally {
      setLoading(false)
    }
  }, [])

  // Login — salva tokens e carrega usuário
  const login = async (email, password) => {
    const response = await api.post('/auth/login', { email, password })
    const { access_token, refresh_token, user } = response.data.data

    localStorage.setItem('access_token', access_token)
    localStorage.setItem('refresh_token', refresh_token)

    setUser(user)
    return user
  }

  // Logout — limpa tokens e estado
  const logout = () => {
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
    setUser(null)
  }

  // Helpers de role
  const isAdmin        = () => user?.role === 'admin'
  const isProfessional = () => user?.role === 'professional'
  const isAuthenticated = () => !!user

  return (
    <AuthContext.Provider value={{
      user,
      loading,
      login,
      logout,
      loadUser,
      isAdmin,
      isProfessional,
      isAuthenticated,
    }}>
      {children}
    </AuthContext.Provider>
  )
}

// =========================================================
// HOOK
// =========================================================

// Uso nos componentes: const { user, login, logout } = useAuth()
export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth deve ser usado dentro de AuthProvider')
  }

  return context
}