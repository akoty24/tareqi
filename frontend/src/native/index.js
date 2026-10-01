import { Capacitor } from '@capacitor/core'
import { devicesApi } from '@/api'

/**
 * Bridge to the Android app (Capacitor). Every function is a no-op in the
 * browser, so the web build and the APK share the same code.
 */
export const isNative = Capacitor.isNativePlatform()
export const platform = Capacitor.getPlatform() // 'android' | 'ios' | 'web'

// Push needs android/app/google-services.json (Firebase); without it the
// native SDK crashes on register(), so it is opt-in at build time.
const PUSH_ENABLED = import.meta.env.VITE_PUSH_ENABLED === 'true'
const DEVICE_TOKEN_KEY = 'mishwar_device_token'

const storage = {
  get: () => { try { return localStorage.getItem(DEVICE_TOKEN_KEY) } catch { return null } },
  set: (v) => { try { localStorage.setItem(DEVICE_TOKEN_KEY, v) } catch { /* ignore */ } },
  clear: () => { try { localStorage.removeItem(DEVICE_TOKEN_KEY) } catch { /* ignore */ } },
}

/** Hardware back button + refresh when the app comes back to the foreground. */
export async function setupNativeShell(router, { onResume } = {}) {
  if (!isNative) return
  const { App } = await import('@capacitor/app')

  const roots = ['home', 'login', 'admin']
  App.addListener('backButton', ({ canGoBack }) => {
    if (!canGoBack || roots.includes(router.currentRoute.value.name)) App.exitApp()
    else router.back()
  })
  if (onResume) App.addListener('resume', onResume)
}

/** Ask for permission, register with FCM and send the token to the API. */
export async function registerPush(router, { onReceived } = {}) {
  if (!isNative || !PUSH_ENABLED) return
  const { PushNotifications } = await import('@capacitor/push-notifications')

  let permission = await PushNotifications.checkPermissions()
  if (permission.receive === 'prompt' || permission.receive === 'prompt-with-rationale') {
    permission = await PushNotifications.requestPermissions()
  }
  if (permission.receive !== 'granted') return

  if (platform === 'android') {
    // Must match the channel_id sent by the backend (FcmClient).
    await PushNotifications.createChannel({ id: 'mishwar_default', name: 'مشوار', importance: 4, sound: 'default', vibration: true })
  }

  await PushNotifications.removeAllListeners()
  await PushNotifications.addListener('registration', async ({ value }) => {
    storage.set(value)
    try {
      await devicesApi.register(value, platform)
    } catch {
      /* retried on next launch */
    }
  })
  // App in the foreground: the system tray is not used, refresh the badge instead.
  await PushNotifications.addListener('pushNotificationReceived', () => onReceived?.())
  // Tap on a notification: open the related page.
  await PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
    const link = notification.data?.link
    if (link && link.startsWith('/')) router.push(link)
  })
  await PushNotifications.register()
}

/** Stop pushes for this account on this device (called on logout). */
export async function unregisterPush() {
  const token = storage.get()
  if (!token) return
  storage.clear()
  try {
    await devicesApi.unregister(token)
  } catch {
    /* token already gone */
  }
}
