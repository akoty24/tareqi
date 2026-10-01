import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Guests can browse; actions (book, report, publish…) send them to the login
 * page and back to the current page afterwards.
 *
 *   const requireLogin = useRequireLogin()
 *   if (!requireLogin()) return
 */
export function useRequireLogin() {
  const auth = useAuthStore()
  const router = useRouter()
  const route = useRoute()

  return function requireLogin() {
    if (auth.isAuthenticated) return true
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return false
  }
}
