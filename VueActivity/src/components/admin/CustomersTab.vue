<template>
  <section
    class="admin-section"
    aria-label="Customers"
  >
    <div class="admin-toolbar">
      <label class="admin-search">
        <q-icon name="search" /><input
          v-model.trim="search"
          type="search"
          placeholder="Name or email"
          @keyup.enter="applySearch"
        />
      </label>
      <div class="admin-toolbar-actions">
        <button
          class="admin-action"
          :disabled="loading"
          @click="applySearch"
        >
          Search
        </button>
      </div>
    </div>

    <div
      v-if="loading"
      class="admin-loading"
    >
      Loading customers…
    </div>
    <p
      v-else-if="!customers.length"
      class="admin-empty"
    >
      No customers match this search.
    </p>
    <div
      v-else
      class="admin-table"
    >
      <div class="admin-table-head customers">
        <span>CUSTOMER</span><span>ORDERS</span><span>SPENT</span><span>LAST ORDER</span><span>ADMIN</span>
      </div>
      <article
        v-for="customer in customers"
        :key="customer.id"
        class="admin-table-row customers"
      >
        <span class="admin-customer">
          <b>{{ customer.name }}</b>
          <small>{{ customer.email }}</small>
        </span>
        <span>{{ customer.ordersCount }}</span>
        <span>{{ money(customer.totalSpent) }}</span>
        <span>{{ shortDate(customer.lastOrderAt) }}</span>
        <label class="admin-switch">
          <input
            type="checkbox"
            :checked="customer.isAdmin"
            :disabled="busy === customer.id || customer.id === session.user?.id"
            @change="toggleAdmin(customer, ($event.target as HTMLInputElement).checked)"
          /><span>{{ customer.isAdmin ? 'Admin' : 'Customer' }}</span>
        </label>
      </article>
    </div>

    <nav
      v-if="meta.lastPage > 1"
      class="admin-pager"
      aria-label="Customer pages"
    >
      <button
        class="admin-action ghost"
        :disabled="page <= 1 || loading"
        @click="go(page - 1)"
      >
        Previous
      </button>
      <span>Page {{ meta.currentPage }} of {{ meta.lastPage }} · {{ meta.total }} customers</span>
      <button
        class="admin-action ghost"
        :disabled="page >= meta.lastPage || loading"
        @click="go(page + 1)"
      >
        Next
      </button>
    </nav>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '../../boot/api'
import { errorMessage, money, shortDate } from './format'
import { useSessionStore } from '../../stores/session'
import type { Customer, PageMeta } from './types'

const $q = useQuasar()
const session = useSessionStore()
const customers = ref<Customer[]>([])
const meta = ref<PageMeta>({ currentPage: 1, lastPage: 1, total: 0, perPage: 15 })
const page = ref(1)
const search = ref('')
const loading = ref(false)
const busy = ref<number | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await api.get('/admin/customers', {
      params: { search: search.value || undefined, page: page.value, per_page: 15 }
    })
    customers.value = (response.data.data || []) as Customer[]
    meta.value = response.data.meta || meta.value
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not load customers.') })
  } finally {
    loading.value = false
  }
}

function applySearch(): void {
  page.value = 1
  void load()
}

function go(target: number): void {
  page.value = target
  void load()
}

async function toggleAdmin(customer: Customer, isAdmin: boolean): Promise<void> {
  const verb = isAdmin ? 'Grant admin access to' : 'Remove admin access from'
  if (!window.confirm(`${verb} ${customer.name}?`)) {
    return
  }
  busy.value = customer.id
  try {
    await api.patch(`/admin/customers/${customer.id}/admin`, { isAdmin })
    customer.isAdmin = isAdmin
    $q.notify({ type: 'positive', message: `${customer.name} is now ${isAdmin ? 'an admin' : 'a customer'}.` })
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not change that account.') })
    await load()
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>
