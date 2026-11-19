<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="close"
  >
    <q-card class="account-card admin-editor">
      <div class="eyebrow dark">
        MENU ITEM
      </div>
      <h2>{{ food ? 'Edit item' : 'Add item' }}</h2>
      <form
        class="address-form"
        @submit.prevent="save"
      >
        <label>
          Name<input
            v-model.trim="draft.name"
            required
            maxlength="255"
          />
        </label>
        <label>
          Category
          <input
            v-model.trim="draft.category"
            list="admin-category-options"
            required
            maxlength="100"
          />
        </label>
        <datalist id="admin-category-options">
          <option
            v-for="category in categories"
            :key="category.name"
            :value="category.name"
          />
        </datalist>
        <label>
          Price<input
            v-model.number="draft.price"
            type="number"
            min="0"
            step="0.01"
            required
          />
        </label>
        <label>
          Description<textarea
            v-model.trim="draft.description"
            required
            rows="3"
            maxlength="5000"
          />
        </label>
        <label>
          Image URL or filename
          <input
            v-model.trim="draft.image"
            placeholder="burger.jpg or https://…"
            required
            maxlength="500"
          />
        </label>
        <div
          v-if="draft.image"
          class="admin-image-preview"
        >
          <img
            :src="resolveImage(draft.image)"
            alt="Menu item preview"
          />
          <small>Preview</small>
        </div>
        <label>
          Prep time<input
            v-model.trim="draft.prepTime"
            placeholder="15-20 min"
            required
            maxlength="80"
          />
        </label>
        <label>
          Tag<input
            v-model.trim="draft.tag"
            placeholder="Best seller"
            maxlength="100"
          />
        </label>
        <label>
          Stock<input
            v-model.number="draft.stock"
            type="number"
            min="0"
            step="1"
            required
          />
        </label>
        <label class="check-label">
          <input
            v-model="draft.available"
            type="checkbox"
          /> Available for ordering
        </label>
        <p
          v-if="error"
          class="checkout-error"
          role="alert"
        >
          {{ error }}
        </p>
        <button
          class="place-order"
          :disabled="saving"
        >
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
        <button
          type="button"
          class="admin-action ghost"
          :disabled="saving"
          @click="close(false)"
        >
          Cancel
        </button>
      </form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { api } from '../../boot/api'
import { errorMessage, resolveImage } from './format'
import type { Category, Food } from './types'

const props = defineProps<{
  modelValue: boolean
  food: Food | null
  categories: Category[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: []
}>()

interface Draft {
  name: string
  category: string
  price: number
  description: string
  image: string
  prepTime: string
  tag: string
  stock: number
  available: boolean
}

function blank(): Draft {
  return {
    name: '',
    category: '',
    price: 0,
    description: '',
    image: '',
    prepTime: '',
    tag: '',
    stock: 0,
    available: true
  }
}

const draft = ref<Draft>(blank())
const saving = ref(false)
const error = ref('')

watch(() => props.modelValue, open => {
  if (!open) {
    return
  }
  error.value = ''
  const food = props.food
  draft.value = food
    ? {
        name: food.name,
        category: food.category,
        price: food.price,
        description: food.description,
        image: food.image,
        prepTime: food.prepTime,
        tag: food.tag || '',
        stock: food.stock,
        available: food.available
      }
    : blank()
})

function close(value: boolean): void {
  emit('update:modelValue', value)
}

async function save(): Promise<void> {
  error.value = ''
  saving.value = true
  try {
    const payload = { ...draft.value, tag: draft.value.tag || null }
    if (props.food) {
      await api.put(`/admin/products/${props.food.id}`, payload)
    } else {
      await api.post('/admin/products', payload)
    }
    emit('saved')
    close(false)
  } catch (cause) {
    error.value = errorMessage(cause, 'Could not save this menu item.')
  } finally {
    saving.value = false
  }
}
</script>
