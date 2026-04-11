import api from './index.js'

export const getOrders     = (params = {}) => api.get('/distribution/orders', { params })
export const getOrder      = (id)          => api.get(`/distribution/orders/${id}`)
export const createOrder   = (data)        => api.post('/distribution/orders', data)
export const exportPendingOrders = (data = {}) => api.post('/distribution/orders/export-pending', data)
export const updateOrder   = (id, data)    => api.put(`/distribution/orders/${id}`, data)
export const deleteOrder   = (id)          => api.delete(`/distribution/orders/${id}`)
export const cancelOrder   = (id)          => api.patch(`/distribution/orders/${id}/cancel`)

export const getOrderEvents   = (orderId)        => api.get(`/distribution/orders/${orderId}/events`)
export const createOrderEvent = (orderId, data)  => api.post(`/distribution/orders/${orderId}/events`, data)
