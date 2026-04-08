import api from './index.js'

export const getRoutes    = (params = {}) => api.get('/distribution/routes', { params })
export const getRoute     = (id)          => api.get(`/distribution/routes/${id}`)
export const createRoute  = (data)        => api.post('/distribution/routes', data)
export const updateRoute  = (id, data)    => api.put(`/distribution/routes/${id}`, data)
export const deleteRoute  = (id)          => api.delete(`/distribution/routes/${id}`)

export const addOrderToRoute      = (routeId, orderId) => api.post(`/distribution/routes/${routeId}/orders`, { order_id: orderId })
export const removeOrderFromRoute = (routeId, orderId) => api.delete(`/distribution/routes/${routeId}/orders/${orderId}`)

export const dispatchRoute = (routeId) => api.patch(`/distribution/routes/${routeId}/dispatch`)
export const completeRoute = (routeId) => api.patch(`/distribution/routes/${routeId}/complete`)
export const cancelRoute   = (routeId) => api.patch(`/distribution/routes/${routeId}/cancel`)
