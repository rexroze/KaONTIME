<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <main class="account-page activity-page">
        <router-link
          class="brand"
          to="/"
        >
          <span class="brand-mark"><q-icon name="schedule" /></span><span>Ka<span>ONTIME</span><small>KITCHEN & EXPRESS</small></span>
        </router-link><section class="account-card">
          <div class="eyebrow dark">
            PROJECT CONNECTION
          </div><h1>Activity check</h1><p>A small window into the project’s API connection.</p><div class="status-card">
            <div class="status-card-head">
              <span>BACKEND CONNECTION</span><span :class="['status-dot',shop.apiStatus]" />
            </div><strong>{{ shop.apiStatus==='online'?'Backend connected':shop.apiStatus==='checking'?'Checking connection…':'Backend not connected' }}</strong><p>{{ shop.apiStatus==='online'?'The API responded successfully.':'Menu browsing remains available while the API is offline.' }}</p><div
              v-if="shop.helloMessage"
              class="api-message"
            >
              <small>GET /api/hello · exact response</small><span>{{ shop.helloMessage }}</span>
            </div><p
              v-if="shop.apiError"
              class="api-error"
            >
              {{ shop.apiError }}
            </p><button
              class="place-order"
              :disabled="shop.apiStatus==='checking'"
              @click="check"
            >
              Test Backend
            </button>
          </div><router-link
            class="back-link"
            to="/"
          >
            ← Back to the menu
          </router-link>
        </section>
      </main>
    </q-page-container>
  </q-layout>
</template>
<script setup lang="ts">import{onMounted}from'vue';import{useQuasar}from'quasar';import{useShopStore}from'../stores/shop';const shop=useShopStore();const $q=useQuasar();async function check(){try{await shop.testBackend()}catch{$q.notify({type:'negative',message:shop.apiError})}};onMounted(()=>{if(shop.apiStatus==='checking')void check()});</script>
