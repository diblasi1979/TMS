import api from './index.js'

export const getVehicles        = (params = {})   => api.get('/transport/vehicles', { params })
export const getVehicle         = (id)            => api.get(`/transport/vehicles/${id}`)
export const createVehicle      = (data)          => api.post('/transport/vehicles', data)
export const updateVehicle      = (id, data)      => api.put(`/transport/vehicles/${id}`, data)
export const deleteVehicle      = (id)            => api.delete(`/transport/vehicles/${id}`)
export const updateVehicleStatus = (id, status)  => api.patch(`/transport/vehicles/${id}/status`, { status })
export const getExpiringVehicles = ()             => api.get('/transport/vehicles/expiring')
