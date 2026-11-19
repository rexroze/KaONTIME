<template>
  <section
    class="admin-section"
    aria-label="Dashboard"
  >
    <div
      v-if="loading"
      class="admin-loading"
    >
      Loading today’s numbers…
    </div>
    <template v-else-if="data">
      <div class="admin-stat-grid">
        <article class="admin-stat">
          <span>TODAY’S ORDERS</span><b>{{ data.todayOrders }}</b>
        </article>
        <article class="admin-stat">
          <span>TODAY’S TOTALS</span><b>{{ money(data.todayRevenue) }}</b><small>COD demo · not collected</small>
        </article>
        <article class="admin-stat accent">
          <span>WAITING TO COOK</span><b>{{ data.pendingOrders }}</b><small>Pending orders</small>
        </article>
        <article class="admin-stat">
          <span>IN KITCHEN</span><b>{{ data.preparingOrders }}</b><small>Preparing</small>
        </article>
        <article class="admin-stat">
          <span>CUSTOMERS</span><b>{{ data.totalCustomers }}</b>
        </article>
        <article
          class="admin-stat"
          :class="{ warn: data.lowStockCount > 0 }"
        >
          <span>LOW STOCK</span><b>{{ data.lowStockCount }}</b><small>5 or fewer left</small>
        </article>
      </div>

      <div class="admin-panel">
        <div class="admin-panel-head">
          <h2>Latest orders</h2>
          <router-link
            class="admin-link"
            to="/admin?tab=orders"
          >
            All orders
          </router-link>
        </div>
        <p
          v-if="!data.recentOrders.length"
          class="admin-empty"
        >
          No orders yet. New orders appear here as soon as a customer checks out.
        </p>
        <article
          v-for="order in data.recentOrders"
          :key="order.orderNumber"
          class="admin-order-row"
        >
          <div class="admin-order-main">
            <div class="admin-order-head">
              <b>{{ order.orderNumber }}</b><span class="status-chip">{{ order.status }}</span>
            </div>
            <p>{{ order.customerName }} · {{ order.customerPhone }}</p>
            <p>{{ order.items.map(item => `${item.quantity} × ${item.name}`).join(' · ') }}</p>
            <small>{{ shortDate(order.createdAt) }} · {{ money(order.totalAmount) }}</small>
          </div>
          <button
            v-if="nextStatus(order.status)"
            class="admin-action"
            :disabled="busy === order.orderNumber"
            @click="advance(order.orderNumber, nextStatus(order.status)!)"
          >
            {{ busy === order.orderNumber ? 'Saving…' : `Mark ${nextStatus(order.status)}` }}
          </button>
        </article>
      </div>
    </template>
    <p
      v-else
      class="admin-empty"
    >
      Could not load the dashboard. {{ error }}
    </p>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '../../boot/api'
import { errorMessage, money, shortDate } from './format'
import type { Dashboard, OrderStatus } from './types'

const emit = defineEmits<{ changed: [] }>()
const $q = useQuasar()
const data = ref<Dashboard | null>(null)
const loading = ref(true)
const error = ref('')
const busy = ref('')

function nextStatus(status: OrderStatus): OrderStatus | null {
  if (status === 'Pending') {
    return 'Preparing'
  }
  if (status === 'Preparing') {
    return 'Out for Delivery'
  }
  if (status === 'Out for Delivery') {
    return 'Delivered'
  }
  return null
}

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/admin/dashboard')
    data.value = response.data.data as Dashboard
  } catch (cause) {
    error.value = errorMessage(cause, 'Please try again.')
  } finally {
    loading.value = false
  }
}

async function advance(orderNumber: string, status: OrderStatus): Promise<void> {
  busy.value = orderNumber
  try {
    await api.patch(`/admin/orders/${encodeURIComponent(orderNumber)}/status`, { status })
    $q.notify({ type: 'positive', message: `${orderNumber} is now ${status}.` })
    await load()
    emit('changed')
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not update that order.') })
  } finally {
    busy.value = ''
  }
}

onMounted(load)
</script>
