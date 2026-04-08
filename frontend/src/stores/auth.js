import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import * as authApi from '@/api/auth.js'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('auth_token') ?? null)
  const user  = ref(JSON.parse(localStorage.getItem('auth_user') ?? 'null'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin         = computed(() => user.value?.role === 'admin')

  async function login(email, password) {
    const { data } = await authApi.login(email, password)
    token.value = data.access_token
    user.value  = data.user
    localStorage.setItem('auth_token', data.access_token)
    localStorage.setItem('auth_user', JSON.stringify(data.user))
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      clearSession()
    }
  }

  async function logoutAll() {
    try {
      await authApi.logoutAll()
    } finally {
      clearSession()
    }
  }

  function clearSession() {
    token.value = null
    user.value  = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
  }

  return { token, user, isAuthenticated, isAdmin, login, logout, logoutAll, clearSession }
})
