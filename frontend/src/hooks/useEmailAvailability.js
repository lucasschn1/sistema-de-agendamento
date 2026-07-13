import { useEffect, useRef, useState } from 'react'
import { checkEmailExists } from '../api/users'

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

// Verifica em tempo real (debounced) se um e-mail já está cadastrado,
// antes do usuário tentar submeter o formulário
export function useEmailAvailability(email, { excludeId, enabled = true } = {}) {
  const [checking, setChecking] = useState(false)
  const [exists, setExists] = useState(false)
  const requestId = useRef(0)

  useEffect(() => {
    if (!enabled || !EMAIL_RE.test(email)) {
      setChecking(false)
      setExists(false)
      return
    }

    setChecking(true)
    const currentRequest = ++requestId.current

    const timer = setTimeout(async () => {
      try {
        const result = await checkEmailExists(email, excludeId)
        if (currentRequest === requestId.current) {
          setExists(result)
        }
      } catch {
        // falha silenciosa — validação de e-mail continua acontecendo no submit
      } finally {
        if (currentRequest === requestId.current) {
          setChecking(false)
        }
      }
    }, 400)

    return () => clearTimeout(timer)
  }, [email, excludeId, enabled])

  return { checking, exists }
}
