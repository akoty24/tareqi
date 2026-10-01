import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, notificationsApi } from '@/api'
import { tokenStorage } from '@/api/client'
import { unregisterPush } from '@/native'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(tokenStorage.get())
  const unreadNotifications = ref(0)
  const initialized = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  // Staff = any user with a role; what they can do depends on its permissions.
  const isAdmin = computed(() => !!user.value?.role)
  const isSuperAdmin = computed(() => !!user.value?.role?.is_super)
  const permissions = computed(() => new Set(user.value?.permissions ?? []))

  /** can('users.block') — mirrors the backend Gate abilities. */
  function can(permission) {
    return permissions.value.has(permission)
  }

  function canAny(list) {
    return list.some((p) => permissions.value.has(p))
  }

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

  /** Lightweight badge refresh (polling, push received, app resumed). */
  async function refreshUnread() {
    if (!token.value) return
    const { data } = await notificationsApi.unreadCount()
    unreadNotifications.value = data.unread_count
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
      await unregisterPush()
      await authApi.logout()
    } finally {
      clearSession()
    }
  }

  return {
    user, token, unreadNotifications, initialized,
    isAuthenticated, isAdmin, isSuperAdmin, permissions,
    can, canAny, init, refresh, refreshUnread, login, register, logout, clearSession,
  }
})
