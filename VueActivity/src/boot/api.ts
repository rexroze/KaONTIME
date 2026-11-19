import { boot } from 'quasar/wrappers'
import axios from 'axios'
import { useSessionStore } from '../stores/session'
import { clearToken, getToken, initTokenStore } from './token-store'

const NATIVE_FALLBACK_API = 'https://vueactivity-kaontime-api.vercel.app/api'
const envBaseURL = (import.meta.env.VITE_API_BASE_URL || '').trim()

export const api = axios.create({
  baseURL: envBaseURL || '/api',
  // Neon (serverless Postgres) cold starts can take a while.
  timeout: 20000,
  headers: { 'Content-Type': 'application/json' }
})

/**
 * On a Capacitor native build a relative "/api" would resolve against the app
 * bundle origin, not the backend. When no explicit env URL is provided, point
 * the app at the production API instead.
 */
async function resolveBaseURL (): Promise<void> {
  if (envBaseURL) return

  try {
    const { Capacitor } = await import('@capacitor/core')
    if (Capacitor.isNativePlatform()) api.defaults.baseURL = NATIVE_FALLBACK_API
  } catch { /* web build: keep the relative default */ }
}

api.interceptors.request.use(config => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(response => response, error => {
  if (error.response?.status === 401 && typeof window !== 'undefined') {
    void clearToken()
    try { useSessionStore().clear() } catch { /* Pinia may not be initialized during boot. */ }
  }
  return Promise.reject(error)
})

export default boot(async () => {
  await resolveBaseURL()
  // Prime the persisted token early so requests fired right after boot are authenticated.
  await initTokenStore()
})
