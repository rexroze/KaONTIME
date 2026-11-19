<template>
  <section
    class="admin-section"
    aria-label="Store settings"
  >
    <div
      v-if="loading"
      class="admin-loading"
    >
      Loading settings…
    </div>
    <form
      v-else
      class="account-card admin-settings"
      @submit.prevent="save"
    >
      <div class="eyebrow dark">
        STORE SETTINGS
      </div>
      <h2>Checkout & storefront</h2>
      <p class="admin-settings-note">
        These values are applied by the API on every new order. Existing orders keep their recorded amounts.
      </p>

      <label>
        Delivery fee (₱)<input
          v-model.number="form.deliveryFee"
          type="number"
          min="0"
          max="9999"
          step="0.01"
          required
        />
      </label>

      <label class="check-label">
        <input
          v-model="form.storeOpen"
          type="checkbox"
        /> Store is open for orders
      </label>

      <label>
        Store message
        <textarea
          v-model.trim="form.storeMessage"
          rows="3"
          maxlength="160"
          placeholder="e.g. Kitchen closes at 8pm today."
        />
        <small class="admin-counter">{{ form.storeMessage.length }}/160</small>
      </label>

      <p
        v-if="error"
        class="checkout-error"
        role="alert"
      >
        {{ error }}
      </p>
      <p
        v-if="saved"
        class="admin-saved"
      >
        Settings saved.
      </p>
      <button
        class="place-order"
        :disabled="saving"
      >
        {{ saving ? 'Saving…' : 'Save settings' }}
      </button>
    </form>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../../boot/api'
import { errorMessage } from './format'
import type { StoreSettings } from './types'

const form = ref<StoreSettings>({ deliveryFee: 50, storeOpen: true, storeMessage: '' })
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const saved = ref(false)

async function load(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/admin/settings')
    form.value = response.data.data as StoreSettings
  } catch (cause) {
    error.value = errorMessage(cause, 'Could not load settings.')
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  saving.value = true
  error.value = ''
  saved.value = false
  try {
    const response = await api.put('/admin/settings', {
      deliveryFee: form.value.deliveryFee,
      storeOpen: form.value.storeOpen,
      storeMessage: form.value.storeMessage
    })
    form.value = response.data.data as StoreSettings
    saved.value = true
  } catch (cause) {
    error.value = errorMessage(cause, 'Could not save settings.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
