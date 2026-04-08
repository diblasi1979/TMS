import api from './index.js'

export const getUsers   = (page = 1) => api.get(`/users?page=${page}`)
export const getUser    = (id)       => api.get(`/users/${id}`)
export const createUser = (data)     => api.post('/users', data)
export const updateUser = (id, data) => api.put(`/users/${id}`, data)
export const deleteUser = (id)       => api.delete(`/users/${id}`)
