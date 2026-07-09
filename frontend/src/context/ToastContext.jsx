import { createContext, useCallback, useContext, useState } from 'react'
import { ToastContainer, Toast } from 'react-bootstrap'

const ToastContext = createContext(null)

let nextId = 1

// =========================================================
// PROVIDER
// =========================================================

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])

  const showToast = useCallback((message, variant = 'success') => {
    const id = nextId++
    setToasts((prev) => [...prev, { id, message, variant }])
  }, [])

  const removeToast = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id))
  }, [])

  return (
    <ToastContext.Provider value={{ showToast }}>
      {children}

      <ToastContainer position="bottom-end" className="p-3" style={{ zIndex: 1080 }}>
        {toasts.map((t) => (
          <Toast
            key={t.id}
            bg={t.variant}
            onClose={() => removeToast(t.id)}
            delay={4000}
            autohide
          >
            <Toast.Body className={t.variant === 'danger' || t.variant === 'success' ? 'text-white' : ''}>
              {t.message}
            </Toast.Body>
          </Toast>
        ))}
      </ToastContainer>
    </ToastContext.Provider>
  )
}

// =========================================================
// HOOK
// =========================================================

// Uso: const { showToast } = useToast(); showToast('Salvo com sucesso')
export function useToast() {
  const context = useContext(ToastContext)

  if (!context) {
    throw new Error('useToast deve ser usado dentro de ToastProvider')
  }

  return context
}
