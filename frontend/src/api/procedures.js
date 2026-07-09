import api from './axios'

// =========================================================
// PROCEDIMENTOS — wrapper da API /api/procedures
// =========================================================

// GET /api/procedures?category=&active=&search=
export function listProcedures(params = {}) {
  return api.get('/api/procedures', { params }).then((res) => res.data.data)
}

// GET /api/procedures/{id}
export function getProcedure(id) {
  return api.get(`/api/procedures/${id}`).then((res) => res.data.data)
}

// GET /api/procedures/categories
export function listCategories() {
  return api.get('/api/procedures/categories').then((res) => res.data.data)
}

// POST /api/procedures
export function createProcedure(payload) {
  return api.post('/api/procedures', payload).then((res) => res.data.data)
}

// PUT /api/procedures/{id}
export function updateProcedure(id, payload) {
  return api.put(`/api/procedures/${id}`, payload).then((res) => res.data.data)
}

// PATCH /api/procedures/{id}/activate
export function activateProcedure(id) {
  return api.patch(`/api/procedures/${id}/activate`).then((res) => res.data.data)
}

// PATCH /api/procedures/{id}/deactivate
export function deactivateProcedure(id) {
  return api.patch(`/api/procedures/${id}/deactivate`).then((res) => res.data.data)
}
