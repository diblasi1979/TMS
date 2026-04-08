import api from './index.js'

export const getShifts    = (params = {}) => api.get('/planning/shifts', { params })
export const getShift     = (id)          => api.get(`/planning/shifts/${id}`)
export const createShift  = (data)        => api.post('/planning/shifts', data)
export const updateShift  = (id, data)    => api.put(`/planning/shifts/${id}`, data)
export const deleteShift  = (id)          => api.delete(`/planning/shifts/${id}`)
