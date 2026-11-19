<template>
  <section
    class="admin-section"
    aria-label="Menu"
  >
    <div class="admin-toolbar">
      <label class="admin-search">
        <q-icon name="search" /><input
          v-model.trim="search"
          type="search"
          placeholder="Search menu items"
        />
      </label>
      <div class="admin-toolbar-actions">
        <button
          class="admin-action"
          @click="addItem"
        >
          Add item
        </button>
      </div>
    </div>

    <div class="admin-chip-row">
      <button
        v-for="option in categoryOptions"
        :key="option"
        class="admin-chip"
        :class="{ active: category === option }"
        @click="category = option"
      >
        {{ option }}
      </button>
    </div>

    <div
      v-if="loading"
      class="admin-loading"
    >
      Loading menu…
    </div>
    <p
      v-else-if="!visibleFoods.length"
      class="admin-empty"
    >
      No menu items match this view.
    </p>
    <div
      v-else
      class="admin-menu-list"
    >
      <article
        v-for="food in visibleFoods"
        :key="food.id"
        class="admin-menu-row"
        :class="{ unavailable: !food.available }"
      >
        <img
          :src="resolveImage(food.image)"
          :alt="food.name"
          loading="lazy"
        />
        <div class="admin-menu-main">
          <h3>{{ food.name }}</h3>
          <span>{{ food.category }} · {{ money(food.price) }} · {{ food.prepTime }}</span>
          <span
            class="admin-badge"
            :class="food.available ? 'ok' : 'off'"
          >{{ food.available ? 'Available' : 'Unavailable' }}</span>
        </div>
        <div class="admin-stock">
          <span>Stock</span>
          <div class="admin-stepper">
            <button
              :disabled="busy === food.id || food.stock <= 0"
              aria-label="Decrease stock"
              @click="adjustStock(food, -1)"
            >
              −
            </button>
            <b>{{ food.stock }}</b>
            <button
              :disabled="busy === food.id"
              aria-label="Increase stock"
              @click="adjustStock(food, 1)"
            >
              +
            </button>
          </div>
          <label class="admin-switch">
            <input
              type="checkbox"
              :checked="food.available"
              :disabled="busy === food.id"
              @change="toggleAvailability(food, ($event.target as HTMLInputElement).checked)"
            /><span>Show in store</span>
          </label>
        </div>
        <div class="admin-menu-actions">
          <button
            class="admin-action ghost"
            @click="edit(food)"
          >
            Edit
          </button>
          <button
            class="admin-action danger"
            @click="remove(food)"
          >
            Delete
          </button>
        </div>
      </article>
    </div>

    <ProductDialog
      v-model="editorOpen"
      :food="editing"
      :categories="categories"
      @saved="onSaved"
    />
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '../../boot/api'
import { errorMessage, money, resolveImage } from './format'
import ProductDialog from './ProductDialog.vue'
import type { Category, Food } from './types'

const emit = defineEmits<{ changed: [] }>()
const $q = useQuasar()
const foods = ref<Food[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)
const busy = ref<number | null>(null)
const search = ref('')
const category = ref('All')
const editorOpen = ref(false)
const editing = ref<Food | null>(null)

const categoryOptions = computed(() => ['All', ...categories.value.map(entry => entry.name)])

const visibleFoods = computed(() => {
  const needle = search.value.toLowerCase()
  return foods.value.filter(food => {
    const matchesCategory = category.value === 'All' || food.category === category.value
    const matchesSearch = !needle ||
      food.name.toLowerCase().includes(needle) ||
      food.category.toLowerCase().includes(needle)
    return matchesCategory && matchesSearch
  })
})

async function load(): Promise<void> {
  loading.value = true
  try {
    const [products, categoryList] = await Promise.all([
      api.get('/admin/products'),
      api.get('/admin/categories').catch(() => ({ data: { data: [] as Category[] } }))
    ])
    foods.value = (products.data.data || []) as Food[]
    categories.value = (categoryList.data.data || []) as Category[]
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not load the menu.') })
  } finally {
    loading.value = false
  }
}

function addItem(): void {
  editing.value = null
  editorOpen.value = true
}

function edit(food: Food): void {
  editing.value = food
  editorOpen.value = true
}

async function onSaved(): Promise<void> {
  await load()
  emit('changed')
}

async function adjustStock(food: Food, delta: number): Promise<void> {
  const next = Math.max(0, food.stock + delta)
  busy.value = food.id
  try {
    const response = await api.patch(`/admin/products/${food.id}/stock`, { stock: next })
    Object.assign(food, response.data.data)
    emit('changed')
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not update stock.') })
  } finally {
    busy.value = null
  }
}

async function toggleAvailability(food: Food, available: boolean): Promise<void> {
  busy.value = food.id
  try {
    const response = await api.patch(`/admin/products/${food.id}/stock`, { stock: food.stock, available })
    Object.assign(food, response.data.data)
    emit('changed')
  } catch (cause) {
    food.available = !available
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not update availability.') })
  } finally {
    busy.value = null
  }
}

async function remove(food: Food): Promise<void> {
  if (!window.confirm(`Remove ${food.name} from the menu? Existing order receipts are preserved.`)) {
    return
  }
  busy.value = food.id
  try {
    await api.delete(`/admin/products/${food.id}`)
    $q.notify({ type: 'positive', message: `${food.name} removed.` })
    await load()
    emit('changed')
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not remove that item.') })
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>
