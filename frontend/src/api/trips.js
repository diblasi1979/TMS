import api from './index.js'

export const getTrips       = (params = {}) => api.get('/planning/trips', { params })
export const getTrip        = (id)          => api.get(`/planning/trips/${id}`)
export const createTrip     = (data)        => api.post('/planning/trips', data)
export const updateTrip     = (id, data)    => api.put(`/planning/trips/${id}`, data)
export const deleteTrip     = (id)          => api.delete(`/planning/trips/${id}`)
export const confirmTrip    = (id, data)    => api.patch(`/planning/trips/${id}/confirm`, data)
export const startTrip      = (id)          => api.patch(`/planning/trips/${id}/start`)
export const completeTrip   = (id)          => api.patch(`/planning/trips/${id}/complete`)
export const cancelTrip     = (id)          => api.patch(`/planning/trips/${id}/cancel`)

export const getVehicleAvailability  = (params = {}) => api.get('/planning/availability/vehicles', { params })
export const getOperatorAvailability = (params = {}) => api.get('/planning/availability/operators', { params })
export const getConflicts            = ()             => api.get('/planning/conflicts')
