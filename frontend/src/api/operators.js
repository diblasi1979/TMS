import api from './index.js'

export const getOperators           = (params = {}) => api.get('/transport/operators', { params })
export const getOperator            = (id)          => api.get(`/transport/operators/${id}`)
export const createOperator         = (data)        => api.post('/transport/operators', data)
export const updateOperator         = (id, data)    => api.put(`/transport/operators/${id}`, data)
export const deleteOperator         = (id)          => api.delete(`/transport/operators/${id}`)
export const updateOperatorStatus   = (id, status)  => api.patch(`/transport/operators/${id}/status`, { status })
export const getExpiringLicenses    = ()            => api.get('/transport/operators/expiring-licenses')
