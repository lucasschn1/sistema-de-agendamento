// Extrai uma mensagem legível do erro retornado pela API (ver Response::error/validationError no backend)
export function parseApiError(err) {
  const apiError = err.response?.data?.error

  if (apiError?.errors) {
    return Object.values(apiError.errors).join(', ')
  }

  if (apiError?.message) {
    return apiError.message
  }

  return 'Erro ao conectar com o servidor. Tente novamente.'
}

// Extrai o mapa de erros por campo (422 ValidationException), ex: { email: 'já cadastrado' }
// Retorna {} quando a API não devolveu erros por campo (ex: 400/409 com mensagem única)
export function parseApiFieldErrors(err) {
  return err.response?.data?.error?.errors ?? {}
}
