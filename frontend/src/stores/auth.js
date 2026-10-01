import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api'
import { tokenStorage } from '@/api/client'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(tokenStorage.get())
  const unreadNotifications = ref(0)
  const initialized = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  function setSession({ user: u, token: t }) {
    user.value = u
    token.value = t
    tokenStorage.set(t)
  }

  function clearSession() {
    user.value = null
    token.value = null
    unreadNotifications.value = 0
    tokenStorage.clear()
  }

  /** Restore the session from the stored token (called once by the router guard). */
  async function init() {
    if (initialized.value) return
    if (token.value) {
      try {
        await refresh()
      } catch {
        clearSession()
      }
    }
    initialized.value = true
  }

  async function refresh() {
    const { data } = await authApi.me()
    user.value = data.user
    unreadNotifications.value = data.unread_notifications
  }

  async function login(credentials) {
    const { data } = await authApi.login(credentials)
    setSession(data)
  }

  async function register(payload) {
    const { data } = await authApi.register(payload)
    setSession(data)
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      clearSession()
    }
  }

  return {
    user, token, unreadNotifications, initialized,
    isAuthenticated, isAdmin,
    init, refresh, login, register, logout, clearSession,
  }
})
