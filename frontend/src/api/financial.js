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

// GET /api/financial/summary/today
export function getTodaySummary() {
  return api.get('/api/financial/summary/today').then((res) => res.data.data)
}

// GET /api/financial/summary/professionals?start=&end=
export function getSummaryByProfessionals(start, end) {
  return api.get('/api/financial/summary/professionals', { params: { start, end } }).then((res) => res.data.data)
}

// GET /api/financial/paid/recent?page=&per_page=
export function getRecentPayments(page = 1, perPage = 10) {
  return api.get('/api/financial/paid/recent', { params: { page, per_page: perPage } }).then((res) => res.data.data)
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

// GET /api/financial/history/summary?year=&month=&professional_id=&patient_id=&service_id=&method=&search=
export function getHistorySummary(params) {
  return api.get('/api/financial/history/summary', { params }).then((res) => res.data.data)
}

// GET /api/financial/history/payments?year=&month=&...&page=&per_page=
export function getHistoryPayments(params) {
  return api.get('/api/financial/history/payments', { params }).then((res) => res.data.data)
}
