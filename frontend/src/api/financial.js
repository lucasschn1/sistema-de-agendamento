import api from './axios'

// =========================================================
// FINANCEIRO — wrapper da API /api/financial
// =========================================================

// GET /api/financial/summary/current
export function getCurrentMonthSummary() {
  return api.get('/api/financial/summary/current').then((res) => res.data.data)
}

// GET /api/financial/summary/month?year=&month=
export function getSummaryByMonth(year, month) {
  return api.get('/api/financial/summary/month', { params: { year, month } }).then((res) => res.data.data)
}

// GET /api/financial/pending
export function getPendingPayments() {
  return api.get('/api/financial/pending').then((res) => res.data.data)
}

// GET /api/financial/paid?start=&end=&method=
export function getPaidAppointments(start, end, method) {
  return api.get('/api/financial/paid', { params: { start, end, method } }).then((res) => res.data.data)
}

// GET /api/financial/methods
export function getPaymentMethods() {
  return api.get('/api/financial/methods').then((res) => res.data.data)
}

// POST /api/financial/payment
export function registerPayment(payload) {
  return api.post('/api/financial/payment', payload).then((res) => res.data.data)
}

// PATCH /api/financial/payment/{id}/undo
export function undoPayment(id, reason) {
  return api.patch(`/api/financial/payment/${id}/undo`, { reason }).then((res) => res.data.data)
}
