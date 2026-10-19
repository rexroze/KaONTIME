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
            Storefront
          </router-link><button
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
            KAONTIME OPERATIONS
          </div><h1>Admin workspace</h1><p>Manage menu availability and demo orders.</p><div
            v-if="!session.isAdmin"
            class="empty-menu"
          >
            <h3>Admin access required</h3><p>This workspace is available to authorized admin accounts only.</p><router-link to="/">
              Back to the menu
            </router-link>
          </div><template v-else>
            <div class="account-tabs">
              <button
                :class="{active:tab==='menu'}"
                @click="tab='menu'"
              >
                Menu
              </button><button
                :class="{active:tab==='orders'}"
                @click="tab='orders'"
              >
                Orders
              </button><button
                :class="{active:tab==='reports'}"
                @click="tab='reports'"
              >
                Reports
              </button>
            </div><section v-if="tab==='menu'">
              <button
                class="place-order admin-add"
                @click="editFood(null)"
              >
                Add menu item
              </button><article
                v-for="food in foods"
                :key="food.id"
                class="admin-row"
              >
                <img
                  :src="food.image?.startsWith('http') ? food.image : `/images/${(food.image || '').split('/').pop()}`"
                  :alt="food.name"
                /><div class="admin-row-main">
                  <h3>{{ food.name }}</h3><span>{{ food.category }} · {{ money(food.price) }} · stock {{ food.stock }} · {{ food.available?'Available':'Unavailable' }}</span>
                </div><button @click="editFood(food)">
                  Edit
                </button><button
                  class="cancel-button"
                  @click="removeFood(food.id)"
                >
                  Delete
                </button>
              </article>
            </section><section v-else-if="tab==='orders'">
              <div
                v-if="!orders.length"
                class="empty-menu"
              >
                <h3>No orders to manage</h3>
              </div><article
                v-for="order in orders"
                :key="order.orderNumber"
                class="order-card"
              >
                <div class="order-heading">
                  <div><span>ORDER REFERENCE</span><h3>{{ order.orderNumber }}</h3></div><span class="status-chip">{{ order.status }}</span>
                </div><p>{{ order.customerName }} · {{ order.customerPhone }} · {{ order.deliveryAddress }}</p><p>{{ order.items.map(i=>`${i.quantity} × ${i.name}`).join(' · ') }}</p><div class="order-summary">
                  <span>{{ order.status }}</span><span>{{ money(order.totalAmount) }} · COD demo</span>
                </div><label class="status-select">Update status<select
                  :value="order.status"
                  :disabled="terminal(order.status)"
                  @change="updateStatus(order.orderNumber,($event.target as HTMLSelectElement).value)"
                ><option
                  v-for="status in statuses"
                  :key="status"
                  :value="status"
                  :disabled="!allowed(order.status,status)"
                >{{ status }}</option></select></label>
              </article>
            </section><section v-else>
              <div
                v-if="report"
                class="report-grid"
              >
                <div class="report-tile">
                  <span>ALL ORDERS</span><b>{{ report.totalOrders }}</b>
                </div><div class="report-tile">
                  <span>PENDING</span><b>{{ report.pendingOrders }}</b>
                </div><div class="report-tile">
                  <span>DEMO REVENUE</span><b>{{ money(report.demoRevenue) }}</b><small>Recorded totals only · no payments</small>
                </div><div class="report-tile">
                  <span>ORDER STATUSES</span><p
                    v-for="(count,status) in report.statusCounts"
                    :key="status"
                  >
                    {{ status }} <b>{{ count }}</b>
                  </p>
                </div><div class="report-tile">
                  <span>TOP DISHES</span><p
                    v-for="dish in report.topDishes"
                    :key="dish.name"
                  >
                    {{ dish.name }} <b>{{ dish.quantity }}</b>
                  </p>
                </div><div class="report-tile">
                  <span>LOW STOCK</span><p
                    v-for="item in report.lowStock"
                    :key="item.id"
                  >
                    {{ item.name }} <b>{{ item.stock }}</b>
                  </p>
                </div>
              </div><p
                v-else
                class="loading-menu"
              >
                Loading real report data…
              </p>
            </section>
          </template>
        </section>
      </main>
    </q-page-container><q-dialog v-model="editorOpen">
      <q-card class="account-card admin-editor">
        <div class="eyebrow dark">
          MENU ITEM
        </div><h2>{{ editingId===null?'Add item':'Edit item' }}</h2><form
          class="address-form"
          @submit.prevent="saveFood"
        >
          <label>Name<input
            v-model="draft.name"
            required
          /></label><label>Category<input
            v-model="draft.category"
            required
          /></label><label>Price<input
            v-model.number="draft.price"
            type="number"
            min="0"
            step="0.01"
            required
          /></label><label>Description<textarea
            v-model="draft.description"
            required
            rows="3"
          /></label><label>Image filename<input
            v-model="draft.image"
            placeholder="burger.jpg"
            required
          /></label><label>Prep time<input
            v-model="draft.prepTime"
            required
          /></label><label>Tag<input v-model="draft.tag" /></label><label>Stock<input
            v-model.number="draft.stock"
            type="number"
            min="0"
            required
          /></label><label class="check-label"><input
            v-model="draft.available"
            type="checkbox"
          /> Available</label><p
            v-if="error"
            class="checkout-error"
          >
            {{ error }}
          </p><button class="place-order">
            Save changes
          </button>
        </form>
      </q-card>
    </q-dialog>
  </q-layout>
</template>
<script setup lang="ts">import{onMounted,ref,watch}from'vue';import{useQuasar}from'quasar';import{api}from'../boot/api';import{useSessionStore}from'../stores/session';import{useShopStore,type Food}from'../stores/shop';import{useRouter}from'vue-router';type Order={orderNumber:string;status:string;customerName:string;customerPhone:string;deliveryAddress:string;totalAmount:number;items:{name:string;quantity:number}[]};type Report={totalOrders:number;pendingOrders:number;demoRevenue:number;topDishes:{name:string;quantity:number}[];lowStock:Food[];statusCounts:Record<string,number>};const $q=useQuasar();const session=useSessionStore();const shop=useShopStore();const router=useRouter();const tab=ref('menu'),foods=ref<Food[]>([]),orders=ref<Order[]>([]),report=ref<Report|null>(null),editorOpen=ref(false),editingId=ref<number|null>(null),error=ref(''),statuses=['Pending','Preparing','Out for Delivery','Delivered','Cancelled'],draft=ref({name:'',category:'',price:0,description:'',image:'',prepTime:'',tag:'',stock:0,available:true});function money(v:number){return`₱${Number(v||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2})}`}function terminal(s:string){return['Delivered','Cancelled'].includes(s)}function allowed(current:string,next:string){if(current===next)return true;if(next==='Cancelled')return['Pending','Preparing'].includes(current);const i=statuses.indexOf(current);return i>=0&&statuses[i+1]===next}async function load(){try{if(tab.value==='menu'){const{data}=await api.get('/admin/products');foods.value=data.data||[]}else if(tab.value==='orders'){const{data}=await api.get('/admin/orders');orders.value=data.data||[]}else{const{data}=await api.get('/admin/reports');report.value=data.data}}catch(e){const err=e as{response?:{data?:{message?:string}}};$q.notify({type:'negative',message:err.response?.data?.message||'Could not load admin data. Check your access and try again.'})}}function editFood(food:Food|null){editingId.value=food?.id??null;draft.value=food?{name:food.name,category:food.category,price:food.price,description:food.description,image:food.image.split('/').pop()||'',prepTime:food.prepTime,tag:food.tag,stock:food.stock,available:food.available}:{name:'',category:'',price:0,description:'',image:'',prepTime:'',tag:'',stock:0,available:true};error.value='';editorOpen.value=true}async function saveFood(){error.value='';try{if(editingId.value===null)await api.post('/admin/products',draft.value);else await api.put(`/admin/products/${editingId.value}`,draft.value);editorOpen.value=false;await load();await shop.loadMenu()}catch(e){const err=e as{response?:{data?:{message?:string;errors?:Record<string,string[]>}}};error.value=err.response?.data?.errors?Object.values(err.response.data.errors).flat().join(' '):err.response?.data?.message||'Could not save menu item.'}};async function removeFood(id:number){try{await api.delete(`/admin/products/${id}`);await load();await shop.loadMenu()}catch{$q.notify({type:'negative',message:'Could not delete menu item.'})}};async function updateStatus(number:string,status:string){try{await api.patch(`/admin/orders/${encodeURIComponent(number)}/status`,{status});await load()}catch(e){const err=e as{response?:{data?:{message?:string}}};$q.notify({type:'negative',message:err.response?.data?.message||'Could not update order status.'});await load()}};async function signOut(){await session.logout();await router.push('/')}watch(tab,()=>void load());onMounted(async()=>{await session.restore();if(session.isAdmin)await load()});</script>
