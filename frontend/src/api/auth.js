import api from './index.js'

export const login = (email, password) =>
  api.post('/auth/login', { email, password })

export const logout = () => api.post('/auth/logout')

export const logoutAll = () => api.post('/auth/logout-all')

export const me = () => api.get('/auth/me')
