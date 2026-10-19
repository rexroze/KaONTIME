<template>
  <q-layout view="hHh lpR fFf">
    <q-header class="topbar">
      <div class="nav wrap">
        <router-link
          class="brand"
          to="/"
        >
          <span class="brand-mark"><q-icon name="schedule" /></span><span>Ka<span>ONTIME</span><small>KITCHEN & EXPRESS</small></span>
        </router-link><div class="nav-actions">
          <router-link
            class="nav-link"
            to="/"
          >
            Menu
          </router-link><router-link
            v-if="session.isAdmin"
            class="nav-link"
            to="/admin"
          >
            Admin
          </router-link><button
            v-if="session.user"
            class="nav-link"
            @click="signOut"
          >
            Sign out
          </button>
        </div>
      </div>
    </q-header><q-page-container>
      <main class="account-page orders-page">
        <section class="account-card wide">
          <div class="eyebrow dark">
            YOUR ACCOUNT
          </div><h1>My orders</h1><p>Your order history and saved delivery details.</p><div class="account-tabs">
            <button
              :class="{active:tab==='orders'}"
              @click="tab='orders'"
            >
              Order history
            </button><button
              :class="{active:tab==='favorites'}"
              @click="tab='favorites'"
            >
              Favorites
            </button><button
              :class="{active:tab==='addresses'}"
              @click="tab='addresses'"
            >
              Addresses
            </button>
          </div><div
            v-if="!session.user"
            class="empty-menu"
          >
            <h3>Sign in to see your account</h3><router-link
              class="place-order"
              to="/login"
            >
              Sign in
            </router-link>
          </div><template v-else>
            <section v-if="tab==='orders'">
              <div
                v-if="loading"
                class="loading-menu"
              >
                Loading your orders…
              </div><div
                v-else-if="!orders.length"
                class="empty-menu"
              >
                <h3>No demo orders yet</h3><p>Your confirmed orders will appear here.</p><router-link to="/">
                  Explore the menu
                </router-link>
              </div><article
                v-for="order in orders"
                :key="order.orderNumber"
                class="order-card"
              >
                <div class="order-heading">
                  <div><span>ORDER REFERENCE</span><h3>{{ order.orderNumber }}</h3></div><span class="status-chip">{{ order.status }}</span>
                </div><div class="order-summary">
                  <span>{{ date(order.createdAt) }}</span><span>{{ order.items.length }} items · {{ money(order.totalAmount) }}</span>
                </div><details>
                  <summary>View receipt</summary><p
                    v-for="item in order.items"
                    :key="item.productId"
                  >
                    {{ item.quantity }} × {{ item.name }} <b>{{ money(item.subtotal) }}</b>
                  </p><p>Demo address: {{ order.deliveryAddress }}</p><p>Payment: COD · recorded as a demo</p>
                </details><button
                  v-if="order.status==='Pending'"
                  class="cancel-button"
                  :disabled="busy===order.orderNumber"
                  @click="cancel(order.orderNumber)"
                >
                  Cancel pending order
                </button><small>Academic order record · no delivery or payment</small>
              </article>
            </section><section v-else-if="tab==='favorites'">
              <div
                v-if="!favoriteFoods.length"
                class="empty-menu"
              >
                <h3>No saved dishes yet</h3><p>Tap the heart on a menu item to keep it close.</p><router-link to="/">
                  Browse the menu
                </router-link>
              </div><article
                v-for="food in favoriteFoods"
                :key="food.id"
                class="saved-row"
              >
                <img
                  :src="food.image"
                  :alt="food.name"
                /><div><h3>{{ food.name }}</h3><p>{{ money(food.price) }}</p></div><button
                  aria-label="Remove favorite"
                  @click="removeFavorite(food.id)"
                >
                  Remove
                </button>
              </article>
            </section><section v-else>
              <form
                class="address-form"
                @submit.prevent="saveAddress"
              >
                <h3>{{ editing===null?'Add a delivery address':'Edit address' }}</h3><label>Label<input
                  v-model="form.label"
                  required
                  placeholder="Home, work…"
                /></label><label>Recipient name<input
                  v-model="form.recipientName"
                  required
                /></label><label>Phone<input
                  v-model="form.phone"
                  required
                  type="tel"
                /></label><label>Address<textarea
                  v-model="form.address"
                  required
                  rows="2"
                /></label><label class="check-label"><input
                  v-model="form.isDefault"
                  type="checkbox"
                /> Make default address</label><button class="place-order">
                  {{ editing===null?'Save address':'Update address' }}
                </button><button
                  v-if="editing!==null"
                  type="button"
                  class="cancel-button"
                  @click="resetForm"
                >
                  Cancel
                </button><p
                  v-if="addressError"
                  class="checkout-error"
                >
                  {{ addressError }}
                </p>
              </form><article
                v-for="a in addresses"
                :key="a.id"
                class="address-row"
              >
                <div>
                  <b>{{ a.label }}</b><span
                    v-if="a.isDefault"
                    class="status-chip"
                  >Default</span><p>{{ a.recipientName }} · {{ a.phone }}</p><p>{{ a.address }}</p>
                </div><div class="address-actions">
                  <button @click="edit(a)">
                    Edit
                  </button><button @click="removeAddress(a.id)">
                    Delete
                  </button><button
                    v-if="!a.isDefault"
                    @click="makeDefault(a)"
                  >
                    Set default
                  </button>
                </div>
              </article><p
                v-if="!addresses.length"
                class="empty-menu"
              >
                No saved addresses yet.
              </p>
            </section>
          </template>
        </section><p class="account-note">
          Academic project · no real payments or deliveries
        </p>
      </main>
    </q-page-container>
  </q-layout>
</template>
<script setup lang="ts">import{onMounted,ref}from'vue';import{useQuasar}from'quasar';import{api}from'../boot/api';import{useSessionStore}from'../stores/session';import{useShopStore,type Food}from'../stores/shop';import{useRouter}from'vue-router';type Order={orderNumber:string;status:string;createdAt:string;totalAmount:number;deliveryAddress:string;items:{productId:number;name:string;quantity:number;subtotal:number}[]};type Address={id:number;label:string;recipientName:string;phone:string;address:string;isDefault:boolean};const $q=useQuasar();const session=useSessionStore();const shop=useShopStore();const router=useRouter();const tab=ref('orders'),orders=ref<Order[]>([]),addresses=ref<Address[]>([]),favoriteFoods=ref<Food[]>([]),loading=ref(true),busy=ref(''),editing=ref<number|null>(null),addressError=ref(''),form=ref({label:'',recipientName:'',phone:'',address:'',isDefault:false});function money(v:number){return`₱${Number(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2})}`}function date(v:string){return new Date(v).toLocaleString('en-PH',{dateStyle:'medium',timeStyle:'short'})}async function load(){if(!session.user)return;loading.value=true;try{const results=await Promise.all([api.get('/orders'),api.get('/addresses'),api.get('/favorites')]);orders.value=results[0].data.data||[];addresses.value=results[1].data.data||[];favoriteFoods.value=results[2].data.data||[]}catch{ $q.notify({type:'negative',message:'Could not load account details. Please refresh and try again.'})}finally{loading.value=false}};async function cancel(n:string){busy.value=n;try{await api.patch(`/orders/${encodeURIComponent(n)}/cancel`);await load()}catch(e){const err=e as{response?:{data?:{message?:string}}};$q.notify({type:'negative',message:err.response?.data?.message||'Could not cancel this order.'})}finally{busy.value=''}};async function removeFavorite(id:number){try{await api.delete(`/favorites/${id}`);await load()}catch{$q.notify({type:'negative',message:'Could not remove favorite.'})}}function resetForm(){editing.value=null;form.value={label:'',recipientName:'',phone:'',address:'',isDefault:false}}function edit(a:Address){editing.value=a.id;form.value={label:a.label,recipientName:a.recipientName,phone:a.phone,address:a.address,isDefault:a.isDefault}};async function saveAddress(){addressError.value='';try{if(editing.value===null)await api.post('/addresses',form.value);else await api.put(`/addresses/${editing.value}`,form.value);resetForm();await load()}catch(e){const err=e as{response?:{data?:{message?:string;errors?:Record<string,string[]>}}};addressError.value=err.response?.data?.errors?Object.values(err.response.data.errors).flat().join(' '):err.response?.data?.message||'Could not save address.'}};async function removeAddress(id:number){try{await api.delete(`/addresses/${id}`);await load()}catch{$q.notify({type:'negative',message:'Could not delete address.'})}};async function makeDefault(a:Address){try{await api.put(`/addresses/${a.id}`,{...a,isDefault:true});await load()}catch{$q.notify({type:'negative',message:'Could not update default address.'})}};async function signOut(){await session.logout();await router.push('/')}onMounted(async()=>{await session.restore();await shop.loadMenu();await load()});</script>
