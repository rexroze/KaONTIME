<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <main class="account-page">
        <router-link
          class="brand"
          to="/"
        >
          <span class="brand-mark"><q-icon name="schedule" /></span><span>Ka<span>ONTIME</span><small>KITCHEN & EXPRESS</small></span>
        </router-link><section class="account-card">
          <div class="eyebrow dark">
            {{ register?'JOIN THE TABLE':'WELCOME BACK' }}
          </div><h1>{{ register?'Create your account':'Sign in' }}</h1><p>{{ register?'Keep your favorites, addresses and orders together.':'Your saved dishes and demo orders are waiting.' }}</p><form @submit.prevent="submit">
            <label v-if="register">Your name<input
              v-model.trim="name"
              autocomplete="name"
              required
              minlength="2"
            /></label><label>Email address<input
              v-model.trim="email"
              type="email"
              autocomplete="email"
              required
            /></label><label>Password<input
              v-model="password"
              type="password"
              :autocomplete="register?'new-password':'current-password'"
              required
              minlength="8"
            /></label><label v-if="register">Confirm password<input
              v-model="confirmation"
              type="password"
              autocomplete="new-password"
              required
            /></label><p
              v-if="error"
              class="checkout-error"
              role="alert"
            >
              {{ error }}
            </p><button
              class="place-order"
              :disabled="loading"
            >
              {{ loading?'One moment…':register?'Create account':'Sign in' }} <q-icon name="arrow_forward" />
            </button>
          </form><p class="account-switch">
            {{ register?'Already have an account?':'New to KaONTIME?' }} <router-link :to="register?'/login':'/register'">
              {{ register?'Sign in':'Create an account' }}
            </router-link>
          </p><router-link
            class="back-link"
            to="/"
          >
            ← Back to the menu
          </router-link>
        </section><small class="account-note">Academic project · no real payments or deliveries</small>
      </main>
    </q-page-container>
  </q-layout>
</template>
<script setup lang="ts">import{computed,ref}from'vue';import{useRoute,useRouter}from'vue-router';import{useSessionStore}from'../stores/session';const route=useRoute(),router=useRouter(),session=useSessionStore(),register=computed(()=>route.path==='/register'),name=ref(''),email=ref(''),password=ref(''),confirmation=ref(''),error=ref(''),loading=ref(false);async function submit(){error.value='';if(register.value&&password.value!==confirmation.value){error.value='Passwords do not match.';return}loading.value=true;try{await session.authenticate(register.value?'/auth/register':'/auth/login',register.value?{name:name.value,email:email.value,password:password.value,password_confirmation:confirmation.value}:{email:email.value,password:password.value});await router.replace('/')}catch(e){const err=e as{response?:{data?:{message?:string;errors?:Record<string,string[]>}}};error.value=err.response?.data?.errors?Object.values(err.response.data.errors).flat().join(' '):err.response?.data?.message||'We couldn’t connect. Please try again.'}finally{loading.value=false}}</script>
