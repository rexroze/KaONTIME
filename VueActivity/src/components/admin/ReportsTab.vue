<template>
  <section
    class="admin-section"
    aria-label="Reports"
  >
    <div class="admin-toolbar">
      <div class="admin-range">
        <label>
          From<input
            v-model="from"
            type="date"
            :max="to"
          />
        </label>
        <label>
          To<input
            v-model="to"
            type="date"
            :min="from"
          />
        </label>
      </div>
      <button
        class="admin-action"
        :disabled="loading"
        @click="load"
      >
        Apply
      </button>
    </div>

    <div
      v-if="loading"
      class="admin-loading"
    >
      Building report…
    </div>
    <template v-else-if="report">
      <div class="admin-stat-grid">
        <article class="admin-stat">
          <span>ORDERS</span><b>{{ report.totalOrders }}</b><small>{{ from }} → {{ to }}</small>
        </article>
        <article class="admin-stat">
          <span>RECORDED VALUE</span><b>{{ money(report.demoRevenue) }}</b><small>COD demo · not collected</small>
        </article>
        <article class="admin-stat">
          <span>PENDING</span><b>{{ report.pendingOrders }}</b>
        </article>
        <article
          class="admin-stat"
          :class="{ warn: report.lowStock.length > 0 }"
        >
          <span>LOW STOCK ITEMS</span><b>{{ report.lowStock.length }}</b>
        </article>
      </div>

      <div class="admin-panel">
        <div class="admin-panel-head">
          <h2>Status breakdown</h2>
        </div>
        <p
          v-if="!statusRows.length"
          class="admin-empty"
        >
          No orders in this range.
        </p>
        <div
          v-for="row in statusRows"
          :key="row.status"
          class="admin-bar"
        >
          <span>{{ row.status }}</span>
          <div class="admin-bar-track">
            <i :style="{ width: `${row.percent}%` }" />
          </div>
          <b>{{ row.count }}</b>
        </div>
      </div>

      <div class="admin-panel">
        <div class="admin-panel-head">
          <h2>Top dishes</h2>
        </div>
        <p
          v-if="!report.topDishes.length"
          class="admin-empty"
        >
          No dishes sold in this range.
        </p>
        <p
          v-for="dish in report.topDishes"
          :key="dish.name"
          class="admin-list-row"
        >
          <span>{{ dish.name }}</span><b>{{ dish.quantity }} sold</b>
        </p>
      </div>

      <div class="admin-panel">
        <div class="admin-panel-head">
          <h2>Low stock</h2>
        </div>
        <p
          v-if="!report.lowStock.length"
          class="admin-empty"
        >
          Every item has more than 5 in stock.
        </p>
        <p
          v-for="item in report.lowStock"
          :key="item.id"
          class="admin-list-row"
        >
          <span>{{ item.name }}</span><b>{{ item.stock }} left</b>
        </p>
      </div>
    </template>
    <p
      v-else
      class="admin-empty"
    >
      Could not build the report.
    </p>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '../../boot/api'
import { errorMessage, money } from './format'
import type { Report } from './types'

const $q = useQuasar()
const today = new Date()
const weekAgo = new Date(today.getTime() - 6 * 24 * 60 * 60 * 1000)
const iso = (date: Date): string => date.toISOString().slice(0, 10)

const from = ref(iso(weekAgo))
const to = ref(iso(today))
const report = ref<Report | null>(null)
const loading = ref(true)

const statusRows = computed(() => {
  const counts = report.value?.statusCounts || {}
  const total = Object.values(counts).reduce((sum, count) => sum + Number(count), 0) || 1
  return Object.entries(counts).map(([status, count]) => ({
    status,
    count: Number(count),
    percent: Math.round((Number(count) / total) * 100)
  }))
})

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await api.get('/admin/reports', { params: { from: from.value, to: to.value } })
    report.value = response.data.data as Report
  } catch (cause) {
    report.value = null
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not build the report.') })
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
