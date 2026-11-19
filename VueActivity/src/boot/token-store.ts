/**
 * Token storage abstraction.
 *
 * On the web the token stays in sessionStorage so existing browser behaviour
 * (and tests) are unchanged. On native (Android/iOS via Capacitor) it is kept
 * in @capacitor/preferences so it survives an app restart.
 *
 * The active backend is resolved once during initTokenStore() and mirrored in
 * an in-memory cache so getToken() can stay synchronous.
 */
const TOKEN_KEY = 'kaontime-token'

let cache = ''
let initialized = false
let initPromise: Promise<void> | null = null
let native = false

function readWebToken (): string {
  try {
    return typeof sessionStorage !== 'undefined' ? sessionStorage.getItem(TOKEN_KEY) || '' : ''
  } catch {
    return ''
  }
}

async function isNative (): Promise<boolean> {
  try {
    const { Capacitor } = await import('@capacitor/core')
    return Capacitor.isNativePlatform()
  } catch {
    return false
  }
}

/** Load the persisted token into the in-memory cache. Safe to call repeatedly. */
export function initTokenStore (): Promise<void> {
  if (initPromise !== null) return initPromise

  initPromise = (async () => {
    native = await isNative()

    if (native) {
      try {
        const { Preferences } = await import('@capacitor/preferences')
        const { value } = await Preferences.get({ key: TOKEN_KEY })
        cache = value || ''
      } catch {
        cache = ''
      }
    } else {
      cache = readWebToken()
    }

    initialized = true
  })()

  return initPromise
}

/** Synchronous read of the token (cached after initTokenStore resolves). */
export function getToken (): string {
  if (initialized) return cache
  // Before init completes, web reads straight through; native has no value yet.
  return native ? cache : readWebToken()
}

/** Persist the token to the active backend and update the cache. */
export async function setToken (token: string): Promise<void> {
  await initTokenStore()
  cache = token

  if (native) {
    const { Preferences } = await import('@capacitor/preferences')
    await Preferences.set({ key: TOKEN_KEY, value: token })
    return
  }

  try {
    sessionStorage.setItem(TOKEN_KEY, token)
  } catch { /* storage can be unavailable (private mode) */ }
}

/** Remove the token from the active backend and the cache. */
export async function clearToken (): Promise<void> {
  await initTokenStore()
  cache = ''

  if (native) {
    const { Preferences } = await import('@capacitor/preferences')
    await Preferences.remove({ key: TOKEN_KEY })
    return
  }

  try {
    sessionStorage.removeItem(TOKEN_KEY)
  } catch { /* storage can be unavailable (private mode) */ }
}
