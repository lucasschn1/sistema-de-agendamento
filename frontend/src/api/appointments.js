import api from './axios'

// =========================================================
// AGENDAMENTOS — wrapper da API /api/appointments
// =========================================================

// GET /api/appointments?start=&end=&status=
export function listAppointments(params = {}) {
  return api.get('/api/appointments', { params }).then((res) => res.data.data)
}

// GET /api/appointments/{id}
export function getAppointment(id) {
  return api.get(`/api/appointments/${id}`).then((res) => res.data.data)
}

// GET /api/appointments/{id}/history
export function getAppointmentHistory(id) {
  return api.get(`/api/appointments/${id}/history`).then((res) => res.data.data)
}

// GET /api/availability?professional_id=&date=&duration=&exclude_id=
export function checkAvailability(params) {
  return api.get('/api/availability', { params }).then((res) => res.data.data)
}

// POST /api/appointments
export function createAppointment(payload) {
  return api.post('/api/appointments', payload).then((res) => res.data.data)
}

// POST /api/appointments/recurrence
export function createRecurrence(payload) {
  return api.post('/api/appointments/recurrence', payload).then((res) => res.data.data)
}

// PATCH /api/appointments/recurrence/{groupId}/cancel
export function cancelRecurrence(groupId, reason, fromDate) {
  return api
    .patch(`/api/appointments/recurrence/${groupId}/cancel`, { reason, from_date: fromDate })
    .then((res) => res.data.data)
}

// PUT /api/appointments/{id}
export function updateAppointment(id, payload) {
  return api.put(`/api/appointments/${id}`, payload).then((res) => res.data.data)
}

// PATCH /api/appointments/{id}/confirm
export function confirmAppointment(id) {
  return api.patch(`/api/appointments/${id}/confirm`).then((res) => res.data.data)
}

// PATCH /api/appointments/{id}/complete
export function completeAppointment(id) {
  return api.patch(`/api/appointments/${id}/complete`).then((res) => res.data.data)
}

// PATCH /api/appointments/{id}/cancel
export function cancelAppointment(id, reason) {
  return api.patch(`/api/appointments/${id}/cancel`, { reason }).then((res) => res.data.data)
}

// PATCH /api/appointments/{id}/no-show
export function markNoShow(id, reason) {
  return api.patch(`/api/appointments/${id}/no-show`, { reason }).then((res) => res.data.data)
}

// PATCH /api/appointments/{id}/reschedule
export function rescheduleAppointment(id, startTime) {
  return api.patch(`/api/appointments/${id}/reschedule`, { start_time: startTime }).then((res) => res.data.data)
}

// DELETE /api/appointments/{id}  (admin)
export function deleteAppointment(id) {
  return api.delete(`/api/appointments/${id}`).then((res) => res.data.data)
}
