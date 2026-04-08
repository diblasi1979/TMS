import api from './index.js'

export const getCompanies   = (page = 1) => api.get(`/companies?page=${page}`)
export const getCompany     = (id)       => api.get(`/companies/${id}`)
export const createCompany  = (data)     => api.post('/companies', data)
export const updateCompany  = (id, data) => api.put(`/companies/${id}`, data)
export const deleteCompany  = (id)       => api.delete(`/companies/${id}`)
