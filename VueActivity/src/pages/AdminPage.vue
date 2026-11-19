<template>
  <q-layout view="hHh lpR fFf">
    <q-header class="topbar">
      <div class="nav wrap">
        <router-link
          class="brand"
          to="/"
        >
          <span class="brand-mark"><q-icon name="schedule" /></span><span>Ka<span>ONTIME</span><small>KITCHEN & EXPRESS</small></span>
        </router-link>
        <div class="nav-actions">
          <router-link
            class="nav-link"
            to="/"
          >
            <q-icon name="storefront" />Storefront
          </router-link>
          <button
            class="nav-link"
            @click="signOut"
          >
            <q-icon name="logout" />Sign out
          </button>
        </div>
      </div>
    </q-header>

    <q-page-container>
      <main class="admin-page">
        <header class="admin-hero">
          <div>
            <div class="eyebrow dark">
              KAONTIME OPERATIONS
            </div>
            <h1>Admin workspace</h1>
            <p>Dashboard, orders, menu, promos and store settings in one place.</p>
          </div>
          <span
            v-if="pendingCount"
            class="admin-pending-pill"
          >
            {{ pendingCount }} pending
          </span>
        </header>

        <div
          v-if="!session.isAdmin"
          class="empty-menu"
        >
          <h3>Admin access required</h3>
          <p>This workspace is available to authorized admin accounts only.</p>
          <router-link to="/">
            Back to the menu
          </router-link>
        </div>

        <template v-else>
          <nav
            class="admin-tabs"
            aria-label="Admin sections"
          >
            <button
              v-for="item in tabs"
              :key="item.key"
              class="admin-tab"
              :class="{ active: tab === item.key }"
              :aria-current="tab === item.key ? 'page' : undefined"
              @click="selectTab(item.key)"
            >
              <q-icon :name="item.icon" /><span>{{ item.label }}</span>
            </button>
          </nav>

          <DashboardTab
            v-if="tab === 'dashboard'"
            @changed="refreshBadge"
          />
          <OrdersTab
            v-else-if="tab === 'orders'"
            @changed="refreshBadge"
          />
          <MenuTab
            v-else-if="tab === 'menu'"
            @changed="refreshBadge"
          />
          <PromosTab v-else-if="tab === 'promos'" />
          <CustomersTab v-else-if="tab === 'customers'" />
          <ReportsTab v-else-if="tab === 'reports'" />
          <SettingsTab v-else-if="tab === 'settings'" />
        </template>
      </main>
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../boot/api'
import { useSessionStore } from '../stores/session'
import DashboardTab from '../components/admin/DashboardTab.vue'
import OrdersTab from '../components/admin/OrdersTab.vue'
import MenuTab from '../components/admin/MenuTab.vue'
import PromosTab from '../components/admin/PromosTab.vue'
import CustomersTab from '../components/admin/CustomersTab.vue'
import ReportsTab from '../components/admin/ReportsTab.vue'
import SettingsTab from '../components/admin/SettingsTab.vue'

type TabKey = 'dashboard' | 'orders' | 'menu' | 'promos' | 'customers' | 'reports' | 'settings'

const tabs: { key: TabKey; label: string; icon: string }[] = [
  { key: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
  { key: 'orders', label: 'Orders', icon: 'receipt_long' },
  { key: 'menu', label: 'Menu', icon: 'restaurant_menu' },
  { key: 'promos', label: 'Promos', icon: 'local_offer' },
  { key: 'customers', label: 'Customers', icon: 'group' },
  { key: 'reports', label: 'Reports', icon: 'insights' },
  { key: 'settings', label: 'Settings', icon: 'tune' }
]

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const tab = ref<TabKey>('dashboard')
const pendingCount = ref(0)

function isTabKey(value: unknown): value is TabKey {
  return typeof value === 'string' && tabs.some(item => item.key === value)
}

function selectTab(key: TabKey): void {
  tab.value = key
  void router.replace({ query: { ...route.query, tab: key } })
}

async function refreshBadge(): Promise<void> {
  try {
    const response = await api.get('/admin/dashboard')
    pendingCount.value = Number(response.data.data?.pendingOrders || 0)
  } catch {
    /* The badge is decorative; the active tab already reports its own errors. */
  }
}

async function signOut(): Promise<void> {
  await session.logout()
  await router.replace('/')
}

onMounted(async () => {
  await session.restore()
  if (isTabKey(route.query.tab)) {
    tab.value = route.query.tab
  }
  if (session.isAdmin) {
    await refreshBadge()
  } else {
    $q.notify({ type: 'warning', message: 'Sign in with an administrator account to use this workspace.' })
  }
})
</script>
