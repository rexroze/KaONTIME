import { defineConfig } from '#q-app/wrappers'

export default defineConfig(() => ({
  boot: ['api', 'native'],
  css: ['app.scss'],
  extras: ['material-icons'],
  build: {
    target: { browser: ['es2022', 'chrome120', 'firefox120', 'safari17'], node: 'node20' },
    typescript: { strict: true, vueShim: true },
    vitePlugins: []
  },
  devServer: { open: false, proxy: { '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true } } },
  framework: { config: {}, plugins: ['Notify'] },
  animations: [],
  ssr: { prodPort: 3000, middlewares: ['render'] },
  pwa: { workboxMode: 'GenerateSW' },
  capacitor: {
    appName: 'KaONTIME',
    version: '1.0.0',
    hideSplashscreen: false
  },
  electron: { inspectPort: 5858, bundler: 'packager', packager: {}, builder: { appId: 'vueactivity' } },
  bex: {}
}))
