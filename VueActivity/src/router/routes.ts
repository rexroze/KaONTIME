import type { RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  { path: '/', component: () => import('../pages/StorefrontPage.vue') },
  { path: '/activity', component: () => import('../pages/ActivityPage.vue') },
  { path: '/login', component: () => import('../pages/AccountPage.vue') },
  { path: '/register', component: () => import('../pages/AccountPage.vue') },
  {
    path: '/orders',
    component: () => import('../pages/OrdersPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin',
    component: () => import('../pages/AdminPage.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  { path: '/:pathMatch(.*)*', redirect: '/' }
]
export default routes
