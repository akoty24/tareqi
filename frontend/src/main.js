import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { i18n } from './i18n'
import { setUnauthorizedHandler } from './api/client'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toast'
import { setupNativeShell } from './native'
import './style.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(i18n)
app.use(router)

// Token expired / revoked / account blocked -> back to login.
setUnauthorizedHandler((error) => {
  const auth = useAuthStore()
  if (!auth.token) return
  auth.clearSession()
  if (error.code === 'account_blocked') useToastStore().error(error.message)
  if (router.currentRoute.value.name !== 'login') router.push({ name: 'login' })
})

// Android app: hardware back button, refresh the session when reopened.
setupNativeShell(router, {
  onResume: () => {
    const auth = useAuthStore()
    if (auth.isAuthenticated) auth.refresh().catch(() => {})
  },
})

app.mount('#app')
