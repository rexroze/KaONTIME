<template>
  <section
    class="admin-section"
    aria-label="Promo codes"
  >
    <div class="admin-toolbar">
      <p class="admin-toolbar-note">
        Promos apply a percentage discount above a minimum spend. Checkout re-validates every code server-side.
      </p>
      <button
        class="admin-action"
        @click="openEditor(null)"
      >
        New promo
      </button>
    </div>

    <div
      v-if="loading"
      class="admin-loading"
    >
      Loading promos…
    </div>
    <p
      v-else-if="!promos.length"
      class="admin-empty"
    >
      No promo codes yet.
    </p>
    <div
      v-else
      class="admin-table"
    >
      <div class="admin-table-head">
        <span>CODE</span><span>DISCOUNT</span><span>MIN SPEND</span><span>STATUS</span><span />
      </div>
      <article
        v-for="promo in promos"
        :key="promo.id"
        class="admin-table-row"
      >
        <b>{{ promo.code }}</b>
        <span>{{ promo.discountPercent }}%</span>
        <span>{{ money(promo.minSpend) }}</span>
        <label class="admin-switch">
          <input
            type="checkbox"
            :checked="promo.active"
            :disabled="busy === promo.id"
            @change="toggle(promo, ($event.target as HTMLInputElement).checked)"
          /><span>{{ promo.active ? 'Active' : 'Paused' }}</span>
        </label>
        <span class="admin-row-actions">
          <button
            class="admin-action ghost"
            @click="openEditor(promo)"
          >
            Edit
          </button>
          <button
            class="admin-action danger"
            @click="remove(promo)"
          >
            Delete
          </button>
        </span>
      </article>
    </div>

    <q-dialog
      v-model="editorOpen"
      persistent
    >
      <q-card class="account-card admin-editor">
        <div class="eyebrow dark">
          PROMO CODE
        </div>
        <h2>{{ editing ? 'Edit promo' : 'New promo' }}</h2>
        <form
          class="address-form"
          @submit.prevent="save"
        >
          <label>
            Code<input
              v-model.trim="draft.code"
              required
              maxlength="64"
              style="text-transform: uppercase"
            />
          </label>
          <label>
            Discount percent<input
              v-model.number="draft.discountPercent"
              type="number"
              min="1"
              max="100"
              required
            />
          </label>
          <label>
            Minimum spend<input
              v-model.number="draft.minSpend"
              type="number"
              min="0"
              step="0.01"
              required
            />
          </label>
          <label>
            Description<input
              v-model.trim="draft.description"
              maxlength="255"
              placeholder="Optional note shown to admins"
            />
          </label>
          <label class="check-label">
            <input
              v-model="draft.active"
              type="checkbox"
            /> Active
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
            {{ saving ? 'Saving…' : 'Save promo' }}
          </button>
          <button
            type="button"
            class="admin-action ghost"
            :disabled="saving"
            @click="editorOpen = false"
          >
            Cancel
          </button>
        </form>
      </q-card>
    </q-dialog>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '../../boot/api'
import { errorMessage, money } from './format'
import type { Promo } from './types'

const $q = useQuasar()
const promos = ref<Promo[]>([])
const loading = ref(true)
const busy = ref<number | null>(null)
const editorOpen = ref(false)
const editing = ref<Promo | null>(null)
const error = ref('')
const saving = ref(false)
const draft = ref({ code: '', discountPercent: 10, minSpend: 0, description: '', active: true })

async function load(): Promise<void> {
  loading.value = true
  try {
    const response = await api.get('/admin/promos')
    promos.value = (response.data.data || []) as Promo[]
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not load promos.') })
  } finally {
    loading.value = false
  }
}

function openEditor(promo: Promo | null): void {
  editing.value = promo
  error.value = ''
  draft.value = promo
    ? {
        code: promo.code,
        discountPercent: promo.discountPercent,
        minSpend: promo.minSpend,
        description: promo.description || '',
        active: promo.active
      }
    : { code: '', discountPercent: 10, minSpend: 0, description: '', active: true }
  editorOpen.value = true
}

async function save(): Promise<void> {
  error.value = ''
  saving.value = true
  try {
    const payload = { ...draft.value, description: draft.value.description || null }
    if (editing.value) {
      await api.put(`/admin/promos/${editing.value.id}`, payload)
    } else {
      await api.post('/admin/promos', payload)
    }
    editorOpen.value = false
    await load()
  } catch (cause) {
    error.value = errorMessage(cause, 'Could not save this promo.')
  } finally {
    saving.value = false
  }
}

async function toggle(promo: Promo, active: boolean): Promise<void> {
  busy.value = promo.id
  try {
    const response = await api.put(`/admin/promos/${promo.id}`, {
      code: promo.code,
      discountPercent: promo.discountPercent,
      minSpend: promo.minSpend,
      description: promo.description,
      active
    })
    Object.assign(promo, response.data.data)
  } catch (cause) {
    promo.active = !active
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not update that promo.') })
  } finally {
    busy.value = null
  }
}

async function remove(promo: Promo): Promise<void> {
  if (!window.confirm(`Delete promo ${promo.code}? Past orders keep their recorded discount.`)) {
    return
  }
  busy.value = promo.id
  try {
    await api.delete(`/admin/promos/${promo.id}`)
    await load()
  } catch (cause) {
    $q.notify({ type: 'negative', message: errorMessage(cause, 'Could not delete that promo.') })
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>
