import api from './axios'

// =========================================================
// USUÁRIOS — wrapper da API /api/users
// =========================================================

// GET /api/users?role=patient|professional&active=true|false
export function listUsers(role, activeOnly = true) {
  return api
    .get('/api/users', { params: { role, active: activeOnly ? 'true' : 'false' } })
    .then((res) => res.data.data)
}

export function listPatients(activeOnly = true) {
  return listUsers('patient', activeOnly)
}

export function listProfessionals(activeOnly = true) {
  return listUsers('professional', activeOnly)
}

// GET /api/users/search?name=
export function searchUsers(name) {
  return api.get('/api/users/search', { params: { name } }).then((res) => res.data.data)
}

// GET /api/users/{id}
export function getUser(id) {
  return api.get(`/api/users/${id}`).then((res) => res.data.data)
}

// POST /api/users/patient
export function createPatient(payload) {
  return api.post('/api/users/patient', payload).then((res) => res.data.data)
}

// POST /api/users/professional
export function createProfessional(payload) {
  return api.post('/api/users/professional', payload).then((res) => res.data.data)
}

// POST /api/users/admin
export function createAdmin(payload) {
  return api.post('/api/users/admin', payload).then((res) => res.data.data)
}

// PUT /api/users/{id}
export function updateUser(id, payload) {
  return api.put(`/api/users/${id}`, payload).then((res) => res.data.data)
}

// PATCH /api/users/{id}/deactivate
export function deactivateUser(id) {
  return api.patch(`/api/users/${id}/deactivate`).then((res) => res.data.data)
}

// PATCH /api/users/{id}/restore
export function restoreUser(id) {
  return api.patch(`/api/users/${id}/restore`).then((res) => res.data.data)
}

// PATCH /api/users/{id}/reset-password
export function resetUserPassword(id, newPassword) {
  return api.patch(`/api/users/${id}/reset-password`, { new_password: newPassword }).then((res) => res.data.data)
}
