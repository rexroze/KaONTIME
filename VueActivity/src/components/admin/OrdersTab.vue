<template>
  <section
    class="admin-section"
    aria-label="Orders"
  >
    <div class="admin-toolbar">
      <label class="admin-search">
        <q-icon name="search" /><input
          v-model.trim="search"
          type="search"
          placeholder="Order number, customer or phone"
          @keyup.enter="applyFilters"
        />
      </label>
      <div class="admin-toolbar-actions">
        <button
          class="admin-action"
          :disabled="loading"
          @click="applyFilters"
        >
          Search
        </button>
        <button
          class="admin-action ghost"
          :disabled="loading"
          @click="refresh"
        >
          Refresh
        </button>
      </div>
    </div>

    <div
      class="admin-chip-row"
      role="tablist"
    >
      <button
        v-for="option in statusFilters"
        :key="option"
        class="admin-chip"
        :class="{ active: status === option }"
        role="tab"
        :aria-selected="status === option"
        @click="selectStatus(option)"
      >
        {{ option }}
      </button>
    </div>

    <div
      v-if="loading"
      class="admin-loading"
    >
      Loading orders…
    </div>
    <p
      v-else-if="!orders.length"
      class="admin-empty"
    >
      No orders match this view.
    </p>
    <template v-else>
      <article
        v-for="order in orders"
        :key="order.orderNumber"
        class="admin-order-card"
      >
        <header class="admin-order-head">
          <div>
            <span class="admin-label">ORDER REFERENCE</span>
            <b>{{ order.orderNumber }}</b>
            <small>{{ shortDate(order.createdAt) }}</small>
          </div>
          <span
            class="status-chip"
            :class="{ cancelled: order.status === 'Cancelled', done: order.status === 'Delivered' }"
          >{{ order.status }}</span>
        </header>

        <div class="admin-order-body">
          <p><b>{{ order.customerName }}</b> · {{ order.customerPhone }}</p>
          <p class="admin-address">
            {{ order.deliveryAddress }}
          </p>
          <p
            v-if="order.orderNotes"
            class="admin-notes"
          >
            “{{ order.orderNotes }}”
          </p>
          <p>{{ order.items.map(item => `${item.quantity} × ${item.name}`).join(' · ') }}</p>
          <p
            v-if="order.discount > 0"
            class="admin-discount"
          >
            Discount −{{ money(order.discount) }} · Delivery {{ money(order.deliveryFee) }}
          </p>
        </div>

        <footer class="admin-order-foot">
          <strong>{{ money(order.totalAmount) }}</strong><span>{{ order.paymentMethod }}</span>
          <label class="admin-status-select">
            <span>Status</span>
            <select
              :value="order.status"
              :disabled="isTerminal(order.status) || busy === order.orderNumber"
              @change="updateStatus(order, ($event.target as HTMLSelectElement).value as OrderStatus)"
            >
              <option
                v-for="option in ORDER_STATUSES"
                :key="option"
                :value="option"
                :disabled="!canMoveTo(order.status, option)"
              >
                {{ option }}
              </option>
            </select>
          </label>
        </footer>
      </article>

      <nav
        class="admin-pager"
        aria-label="Order pages"
      >
        <button
          class="admin-action ghost"
          :disabled="page <= 1 || loading"
          @click="go(page - 1)"
        >
          Previous
        </button>
        <span>Page {{ meta.currentPage }} of {{ Math.max(meta.lastPage, 1) }} · {{ meta.total }} orders</span>
        <button
          class="admin-action ghost"
          :disabled="page >= meta.lastPage || loading"
          @click="go(page + 1)"
        >
          Next
        </button>
      </nav>
    </template>
  </section>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '../../boot/api'
import { ORDER_STATUSES, canMoveTo, errorMessage, isTerminal, money, shortDate } from './format'
import type { Order, OrderStatus, PageMeta } from './types'

const emit = defineEmits<{ changed: [] }>()
const $q = useQuasar()
const statusFilters = ['All', ...ORDER_STATUSES] as const
const orders = ref<Order[]>([])
const meta = ref<PageMeta>({ currentPage: 1, lastPage: 1, total: 0, perPage: 15 })
const page = ref(1)
const status = ref<string>('All')
const search = ref('')
const loading = ref(false)
const busy = ref('')

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await api.get('/admin/orders', {
      params: {
        status: status.value === 'All' ? undefined : status.value,
        search: search.value || undefined,
        page: page.value,
        per_page: 15
      }
    })
    orders.value = (response.data.data || []) as Order[]
    meta.value = response.data.meta || meta.value
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not load orders.') })
  } finally {
    loading.value = false
  }
}

function applyFilters(): void {
  page.value = 1
  void load()
}

function selectStatus(option: string): void {
  status.value = option
  applyFilters()
}

function go(target: number): void {
  page.value = target
  void load()
}

function refresh(): void {
  void load()
}

async function updateStatus(order: Order, next: OrderStatus): Promise<void> {
  if (next === order.status) {
    return
  }
  if (next === 'Cancelled' && !window.confirm(`${order.orderNumber} will be cancelled and its stock returned. Continue?`)) {
    return
  }
  busy.value = order.orderNumber
  try {
    await api.patch(`/admin/orders/${encodeURIComponent(order.orderNumber)}/status`, { status: next })
    $q.notify({ type: 'positive', message: `${order.orderNumber} is now ${next}.` })
    await load()
    emit('changed')
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not update that order.') })
    await load()
  } finally {
    busy.value = ''
  }
}

let pollTimer: number | undefined

onMounted(() => {
  void load()
  // Keep the kitchen queue fresh without hammering the API.
  pollTimer = window.setInterval(() => {
    if (document.hidden || loading.value || busy.value) {
      return
    }
    void load()
  }, 45000)
})

onBeforeUnmount(() => {
  if (pollTimer !== undefined) {
    window.clearInterval(pollTimer)
  }
})
</script>
