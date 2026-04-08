import api from './index.js'

export const getClients   = (page = 1) => api.get(`/clients?page=${page}`)
export const getClient    = (id)       => api.get(`/clients/${id}`)
export const createClient = (data)     => api.post('/clients', data)
export const updateClient = (id, data) => api.put(`/clients/${id}`, data)
export const deleteClient = (id)       => api.delete(`/clients/${id}`)
