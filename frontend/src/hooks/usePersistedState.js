import { useState } from 'react'

// Como um useState comum, mas guarda o valor em sessionStorage —
// sobrevive à troca de página (a página some/some não some ao navegar) e some ao fechar a aba
export function usePersistedState(key, defaultValue) {
  const [state, setState] = useState(() => {
    try {
      const stored = sessionStorage.getItem(key)
      return stored !== null ? JSON.parse(stored) : defaultValue
    } catch {
      return defaultValue
    }
  })

  const setPersistedState = (value) => {
    setState((prev) => {
      const next = typeof value === 'function' ? value(prev) : value
      try {
        sessionStorage.setItem(key, JSON.stringify(next))
      } catch {
        // sessionStorage indisponível (modo privado, etc.) — ignora silenciosamente
      }
      return next
    })
  }

  return [state, setPersistedState]
}
