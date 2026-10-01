import axios from 'axios'
import { i18n } from '@/i18n'

const TOKEN_KEY = 'mishwar_token'

export const tokenStorage = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (token) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

// The Android app talks to an absolute API URL. It defaults to VITE_API_URL
// (see .env.android) and can be changed from the login screen, so one APK
// works against a dev PC on the LAN or the production server.
const API_URL_KEY = 'mishwar_api_url'
const DEFAULT_API_URL = import.meta.env.VITE_API_URL || '/api'

export const apiUrlStorage = {
  default: DEFAULT_API_URL,
  get: () => {
    try {
      return localStorage.getItem(API_URL_KEY) || DEFAULT_API_URL
    } catch {
      return DEFAULT_API_URL
    }
  },
  set: (url) => {
    const value = (url || '').trim().replace(/\/+$/, '')
    try {
      if (value && value !== DEFAULT_API_URL) localStorage.setItem(API_URL_KEY, value)
      else localStorage.removeItem(API_URL_KEY)
    } catch {
      /* ignore */
    }
    client.defaults.baseURL = apiUrlStorage.get()
  },
}

const client = axios.create({
  baseURL: apiUrlStorage.get(),
  headers: { Accept: 'application/json' },
})

client.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) config.headers.Authorization = `Bearer ${token}`
  config.headers['Accept-Language'] = i18n.global.locale.value
  return config
})

/**
 * Normalized API error: { status, message, code, errors }.
 * The backend always answers { success:false, message, errors?, error_code? }.
 */
export class ApiError extends Error {
  constructor({ status, message, code, errors }) {
    super(message)
    this.status = status
    this.code = code
    this.errors = errors || {}
  }
}

let onUnauthorized = () => {}
export const setUnauthorizedHandler = (fn) => (onUnauthorized = fn)

client.interceptors.response.use(
  (response) => response.data,
  (error) => {
    const status = error.response?.status ?? 0
    const body = error.response?.data ?? {}
    const apiError = new ApiError({
      status,
      message: body.message || i18n.global.t(status ? 'errors.generic' : 'errors.network'),
      code: body.error_code,
      errors: body.errors,
    })

    // Expired/revoked token or blocked account: drop the session.
    if (status === 401 || body.error_code === 'account_blocked') {
      onUnauthorized(apiError)
    }

    return Promise.reject(apiError)
  },
)

export default client
