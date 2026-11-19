import { boot } from 'quasar/wrappers'

/**
 * Native-only polish (status bar, splash screen, hardware back button).
 * Every Capacitor plugin is imported lazily behind isNativePlatform() so the
 * web build stays completely unaffected.
 */
export default boot(async ({ router }) => {
  let Capacitor: typeof import('@capacitor/core').Capacitor

  try {
    ;({ Capacitor } = await import('@capacitor/core'))
  } catch {
    return
  }

  if (!Capacitor.isNativePlatform()) return

  // Status bar: brand-coloured, light icons on the dark green background.
  try {
    const { StatusBar, Style } = await import('@capacitor/status-bar')
    await StatusBar.setOverlaysWebView({ overlay: false })
    await StatusBar.setBackgroundColor({ color: '#1b5e20' })
    await StatusBar.setStyle({ style: Style.Dark })
  } catch { /* plugin unavailable: non-fatal */ }

  // The launch splash is configured with launchAutoHide=false, so keep it up
  // until the first route has resolved (i.e. the app is about to mount).
  try {
    const { SplashScreen } = await import('@capacitor/splash-screen')
    void router.isReady().then(() => SplashScreen.hide().catch(() => {}))
  } catch { /* plugin unavailable: non-fatal */ }

  // Android hardware back: go back when possible, otherwise background the app.
  try {
    const { App } = await import('@capacitor/app')
    await App.addListener('backButton', ({ canGoBack }) => {
      if (canGoBack) router.back()
      else void App.minimizeApp()
    })
  } catch { /* plugin unavailable: non-fatal */ }
})
