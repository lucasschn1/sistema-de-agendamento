import axios from 'axios'

// Instância base do axios apontando para a API PHP
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
})

// =========================================================
// INTERCEPTOR DE REQUEST
// Injeta o Bearer token em toda requisição autenticada
// =========================================================
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('access_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// =========================================================
// INTERCEPTOR DE RESPONSE
// Trata erros globais — 401 redireciona para login
// =========================================================
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config

    // Token expirado — tenta renovar com refresh token
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true

      const refreshToken = localStorage.getItem('refresh_token')

      if (refreshToken) {
        try {
          const response = await axios.post(
            `${import.meta.env.VITE_API_URL}/auth/refresh`,
            { refresh_token: refreshToken }
          )

          const newToken = response.data.data.access_token
          localStorage.setItem('access_token', newToken)
          originalRequest.headers.Authorization = `Bearer ${newToken}`

          // Refaz a requisição original com o novo token
          return api(originalRequest)
        } catch {
          // Refresh token também expirou — limpa e redireciona para login
          localStorage.removeItem('access_token')
          localStorage.removeItem('refresh_token')
          window.location.href = '/login'
        }
      } else {
        // Sem refresh token — redireciona para login
        window.location.href = '/login'
      }
    }

    return Promise.reject(error)
  }
)

export default api