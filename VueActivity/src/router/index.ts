import { route } from 'quasar/wrappers'
import { createRouter, createWebHistory } from 'vue-router'
import { useSessionStore } from '../stores/session'
import routes from './routes'

export default route(() => {
  const router = createRouter({
    routes,
    history: createWebHistory(import.meta.env.BASE_URL),
    scrollBehavior: () => ({ left: 0, top: 0 })
  })

  router.beforeEach(async to => {
    const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
    const requiresAdmin = to.matched.some(record => record.meta.requiresAdmin)
    if (!requiresAuth) {
      return true
    }
    const session = useSessionStore()
    await session.restore()
    if (!session.user) {
      return { path: '/login', query: { redirect: to.fullPath } }
    }
    if (requiresAdmin && !session.isAdmin) {
      return { path: '/' }
    }
    return true
  })

  return router
})
