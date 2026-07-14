import api from './axios'

// =========================================================
// SUBLOCAÇÃO DE SALAS — wrapper da API /api/rentals
// =========================================================

// ---- Salas ----

// GET /api/rentals/rooms?active=true|false
export function listRentalRooms(activeOnly = true) {
  return api.get('/api/rentals/rooms', { params: { active: activeOnly ? 'true' : 'false' } }).then((res) => res.data.data)
}

export function createRentalRoom(payload) {
  return api.post('/api/rentals/rooms', payload).then((res) => res.data.data)
}

export function updateRentalRoom(id, payload) {
  return api.put(`/api/rentals/rooms/${id}`, payload).then((res) => res.data.data)
}

export function activateRentalRoom(id) {
  return api.patch(`/api/rentals/rooms/${id}/activate`).then((res) => res.data.data)
}

export function deactivateRentalRoom(id) {
  return api.patch(`/api/rentals/rooms/${id}/deactivate`).then((res) => res.data.data)
}

// ---- Reservas ----

// GET /api/rentals/bookings?start=&end=
export function listRentalBookings(start, end) {
  return api.get('/api/rentals/bookings', { params: { start, end } }).then((res) => res.data.data)
}

export function createRentalBooking(payload) {
  return api.post('/api/rentals/bookings', payload).then((res) => res.data.data)
}

export function cancelRentalBooking(id, reason) {
  return api.patch(`/api/rentals/bookings/${id}/cancel`, { reason }).then((res) => res.data.data)
}

// ---- Recorrências ----

export function listRentalRecurrences() {
  return api.get('/api/rentals/recurrences').then((res) => res.data.data)
}

export function createRentalRecurrence(payload) {
  return api.post('/api/rentals/recurrences', payload).then((res) => res.data.data)
}

export function releaseRentalRecurrence(id, reason) {
  return api.patch(`/api/rentals/recurrences/${id}/release`, { reason }).then((res) => res.data.data)
}

// ---- Faturas ----

export function listRentalInvoices() {
  return api.get('/api/rentals/invoices').then((res) => res.data.data)
}

export function payRentalInvoice(id, paymentMethod) {
  return api.patch(`/api/rentals/invoices/${id}/pay`, { payment_method: paymentMethod }).then((res) => res.data.data)
}
