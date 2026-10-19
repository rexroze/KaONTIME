import { boot } from 'quasar/wrappers'
import axios from 'axios'
import { useSessionStore } from '../stores/session'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 8000,
  headers: { 'Content-Type': 'application/json' }
})

api.interceptors.request.use(config => {
  const token = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('kaontime-token') : null
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})
api.interceptors.response.use(response => response, error => {
  if (error.response?.status === 401 && typeof window !== 'undefined') {
    sessionStorage.removeItem('kaontime-token')
    try { useSessionStore().clear() } catch { /* Pinia may not be initialized during boot. */ }
  }
  return Promise.reject(error)
})

export default boot(() => {})
