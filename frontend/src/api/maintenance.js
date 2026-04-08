import api from './index.js'

export const getMaintenances    = (params = {}) => api.get('/planning/maintenance', { params })
export const getMaintenance     = (id)          => api.get(`/planning/maintenance/${id}`)
export const createMaintenance  = (data)        => api.post('/planning/maintenance', data)
export const updateMaintenance  = (id, data)    => api.put(`/planning/maintenance/${id}`, data)
export const deleteMaintenance  = (id)          => api.delete(`/planning/maintenance/${id}`)
export const startMaintenance   = (id)          => api.patch(`/planning/maintenance/${id}/start`)
export const completeMaintenance= (id)          => api.patch(`/planning/maintenance/${id}/complete`)
export const cancelMaintenance  = (id)          => api.patch(`/planning/maintenance/${id}/cancel`)
