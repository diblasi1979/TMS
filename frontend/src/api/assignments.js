import api from './index.js'

export const getAssignments   = (page = 1)  => api.get(`/transport/assignments?page=${page}`)
export const getActiveAssignments = ()      => api.get('/transport/assignments/active')
export const createAssignment = (data)      => api.post('/transport/assignments', data)
export const releaseAssignment = (id)       => api.patch(`/transport/assignments/${id}/release`)
export const getAlerts        = ()          => api.get('/transport/alerts')
