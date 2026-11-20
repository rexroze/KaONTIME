<template>
  <q-layout
    view="hHh lpR fFf"
    class="app-shell"
  >
    <q-header class="topbar">
      <div class="promo-strip">
        <span>✦ TODAY'S LITTLE EXTRA</span>&nbsp; <b>₱50 flat delivery</b> · made with care
      </div>
      <div class="nav wrap">
        <a
          class="brand"
          href="#top"
          @click="resetFilters"
        ><span class="brand-mark"><q-icon name="schedule" /></span><span>Ka<span>ONTIME</span><small>KITCHEN & EXPRESS</small></span></a><div class="location">
          <q-icon name="place" /><span>Cebu City <small>· 20–30 min</small></span>
        </div><div class="nav-end">
          <div class="nav-actions nav-actions--store">
            <router-link
              class="nav-link"
              to="/orders"
            >
              My orders
            </router-link><router-link
              class="nav-link"
              :to="session.user ? '/orders' : '/login'"
            >
              {{ session.user ? 'Account' : 'Sign in' }}
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
            </button><button
              class="nav-link"
              :class="{chosen:favoritesOnly}"
              @click="favoritesOnly=!favoritesOnly"
            >
              <q-icon name="favorite_border" /><span>Saved</span><i v-if="session.favoriteIds.length">{{ session.favoriteIds.length }}</i>
            </button>
          </div>
          <button
            class="order-pill"
            @click="cartOpen=true"
          >
            <q-icon name="shopping_bag" /><span>Your order</span><b>{{ shop.cartCount }} · {{ money(shop.subtotal) }}</b>
          </button>
          <q-btn
            v-if="$q.screen.lt.md"
            class="nav-menu-toggle"
            flat
            round
            dense
            icon="menu"
            aria-label="Open navigation menu"
          >
            <q-menu
              class="mobile-menu"
              anchor="bottom right"
              self="top right"
            >
              <q-list>
                <q-item
                  v-close-popup
                  clickable
                  to="/orders"
                >
                  <q-item-section avatar>
                    <q-icon name="receipt_long" />
                  </q-item-section>
                  <q-item-section>My orders</q-item-section>
                </q-item>
                <q-item
                  v-close-popup
                  clickable
                  :to="session.user ? '/orders' : '/login'"
                >
                  <q-item-section avatar>
                    <q-icon :name="session.user ? 'person' : 'login'" />
                  </q-item-section>
                  <q-item-section>{{ session.user ? 'Account' : 'Sign in' }}</q-item-section>
                </q-item>
                <q-item
                  v-close-popup
                  clickable
                  @click="favoritesOnly=!favoritesOnly"
                >
                  <q-item-section avatar>
                    <q-icon :name="favoritesOnly ? 'favorite' : 'favorite_border'" />
                  </q-item-section>
                  <q-item-section>Saved</q-item-section>
                  <q-item-section
                    v-if="session.favoriteIds.length"
                    side
                  >
                    <span class="mobile-menu-count">{{ session.favoriteIds.length }}</span>
                  </q-item-section>
                </q-item>
                <q-item
                  v-if="session.isAdmin"
                  v-close-popup
                  clickable
                  to="/admin"
                >
                  <q-item-section avatar>
                    <q-icon name="admin_panel_settings" />
                  </q-item-section>
                  <q-item-section>Admin</q-item-section>
                </q-item>
                <q-item
                  v-if="session.user"
                  v-close-popup
                  clickable
                  @click="signOut"
                >
                  <q-item-section avatar>
                    <q-icon name="logout" />
                  </q-item-section>
                  <q-item-section>Sign out</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </div>
      </div>
    </q-header><q-page-container>
      <main id="top">
        <section class="hero">
          <div class="hero-text">
            <div class="eyebrow">
              <span class="eyebrow-dot" />GOOD FOOD, MADE FOR YOUR MOMENT
            </div><h1>Happiness is<br />just <span>around the bite.</span></h1><p>Big comfort, bright flavors, and the little things that make a meal feel like yours. Made fresh in Cebu, brought right to your door.</p><div class="search-box">
              <q-icon name="search" /><input
                v-model="search"
                placeholder="What are you craving today?"
                aria-label="Search menu"
              /><button
                v-if="search"
                aria-label="Clear search"
                @click="search=''"
              >
                ×
              </button>
            </div><div class="hero-foot">
              <span><b>✦</b> Fresh from our kitchen</span><span><b>◷</b> At your door in 25 min</span><span><b>⌖</b> Cebu City, Philippines</span>
            </div>
          </div><div class="hero-art">
            <div class="hero-orbit" /><div class="hero-photo">
              <img
                src="/images/burger.jpg"
                alt="Signature Angus cheeseburger"
              /><span class="photo-note">THE CROWD<br />FAVORITE <b>↗</b></span>
            </div><div class="floating-stamp">
              <span>MADE</span><strong>FRESH</strong><span>EVERY DAY · ✳</span>
            </div><div class="food-caption">
              <span class="caption-dot" /> Locally loved, always on time
            </div>
          </div><div class="hero-index">
            01 <span>—</span> 04
          </div>
        </section><section
          id="menu"
          class="menu-section wrap"
        >
          <div class="section-top">
            <div>
              <div class="eyebrow dark">
                A VERY GOOD PLACE TO START
              </div><h2>Find your <span>favorite.</span></h2>
            </div><div class="menu-intro">
              Freshly made, never rushed.<br />There’s something for every kind of craving.
            </div>
          </div><div
            v-if="shop.apiStatus==='checking'"
            class="loading-menu"
            role="status"
          >
            Warming up the kitchen…
          </div><div class="filter-row">
            <div class="category-list">
              <button
                v-for="cat in categories"
                :key="cat"
                :class="{active:category===cat}"
                @click="category=cat"
              >
                {{ cat }}
              </button>
            </div><label class="sort-select">Sort <q-icon name="expand_more" /><select v-model="sort"><option value="popular">Most popular</option><option value="rating">Highest rated</option><option value="price-asc">Price: low to high</option><option value="price-desc">Price: high to low</option></select></label>
          </div><div class="result-line">
            <span>{{ favoritesOnly?'YOUR SAVED DISHES':category==='All'?'THE WHOLE MENU':category.toUpperCase() }}</span><span>{{ filtered.length }} delicious things</span>
          </div><div
            v-if="filtered.length"
            class="food-grid"
          >
            <article
              v-for="(food,index) in filtered"
              :key="food.id"
              class="food-card"
              :style="{'--delay':`${index*55}ms`}"
            >
              <div class="card-image">
                <img
                  :src="food.image"
                  :alt="food.name"
                  loading="lazy"
                /><span
                  v-if="food.tag"
                  class="food-tag"
                >{{ food.tag }}</span><button
                  class="heart"
                  :class="{loved:isFav(food.id)}"
                  :aria-label="isFav(food.id)?'Remove favorite':'Save favorite'"
                  @click="toggleFav(food.id)"
                >
                  <q-icon :name="isFav(food.id)?'favorite':'favorite_border'" />
                </button><span class="prep">◷ {{ food.prepTime }}</span><span
                  v-if="!food.available||food.stock===0"
                  class="sold-overlay"
                >A little break<br />from the menu</span>
              </div><div class="card-content">
                <div class="food-meta">
                  <span>{{ food.category }}</span><b>★ {{ food.rating }} <small>({{ food.reviewsCount }})</small></b>
                </div><h3>{{ food.name }}</h3><p>{{ food.description }}</p><div class="card-bottom">
                  <strong>{{ money(food.price) }}</strong><button
                    :disabled="!food.available||food.stock===0"
                    @click="shop.add(food)"
                  >
                    <q-icon name="add" /> Add to order <small v-if="qty(food.id)">{{ qty(food.id) }}</small>
                  </button>
                </div>
              </div>
            </article>
          </div><div
            v-else
            class="empty-menu"
          >
            <span>✳</span><h3>No bites found just yet.</h3><p>Try another search or take a look at the full menu.</p><button @click="resetFilters">
              Show me everything
            </button>
          </div>
        </section>
      </main><footer class="footer">
        <div class="wrap footer-inner">
          <a
            class="brand"
            href="#top"
          ><span class="brand-mark"><q-icon name="schedule" /></span><span>Ka<span>ONTIME</span><small>KITCHEN & EXPRESS</small></span></a><p>Good food, good company, right on time.</p><span>CEBU CITY · PHILIPPINES <b>✳</b> © 2026</span><router-link to="/activity">
            Project activity
          </router-link><small>Academic project · no real payments or deliveries</small>
        </div>
      </footer>
    </q-page-container>
    <q-dialog
      v-model="cartOpen"
      position="right"
    >
      <q-card class="cart-panel">
        <div class="panel-head">
          <div>
            <div class="eyebrow dark">
              LOOKS DELICIOUS
            </div><h2>Your order <span>({{ shop.cartCount }})</span></h2>
          </div><q-btn
            v-close-popup
            flat
            round
            icon="close"
            aria-label="Close order"
          />
        </div><div class="delivery-progress">
          <b>Delivery is a flat ₱50</b><div><span style="width:100%" /></div>
        </div><div
          v-if="!shop.cart.length"
          class="cart-empty"
        >
          <span>◌</span><h3>Your bag is waiting.</h3><p>Find a new favorite in the menu.</p><q-btn
            unelevated
            label="Browse the menu"
            @click="cartOpen=false"
          />
        </div><div
          v-else
          class="cart-body"
        >
          <div class="cart-items">
            <div
              v-for="item in shop.cart"
              :key="item.id"
              class="cart-item"
            >
              <img
                :src="item.image"
                :alt="item.name"
              /><div class="cart-item-main">
                <strong>{{ item.name }}</strong><small>{{ money(item.price) }} each</small><div class="qty">
                  <button
                    aria-label="Decrease quantity"
                    @click="shop.change(item.id,-1)"
                  >
                    −
                  </button><span>{{ item.quantity }}</span><button
                    aria-label="Increase quantity"
                    @click="shop.change(item.id,1)"
                  >
                    +
                  </button>
                </div>
              </div><b>{{ money(item.price*item.quantity) }}</b>
            </div>
          </div><div class="promo-box">
            <label for="promo">Got a little code?</label><div>
              <input
                id="promo"
                v-model="promoCode"
                placeholder="ENTER PROMO CODE"
                @keyup.enter="applyPromo"
              /><button
                :disabled="promoLoading"
                @click="applyPromo"
              >
                {{ promoLoading?'…':'Apply' }}
              </button>
            </div><p
              v-if="promoMessage"
              :class="{bad:promoBad}"
            >
              {{ promoMessage }}
            </p>
          </div><form
            class="checkout"
            @submit.prevent="checkout"
          >
            <div class="eyebrow dark">
              DELIVERY DETAILS
            </div><p
              v-if="!session.user"
              class="signin-note"
            >
              Sign in to continue. Your basket will be here when you return. <router-link
                to="/login"
                @click="cartOpen=false"
              >
                Sign in
              </router-link>
            </p><template v-else>
              <label>Your name <input
                v-model="customerName"
                required
                autocomplete="name"
                placeholder="Full name"
              /></label><label>Phone number <input
                v-model="customerPhone"
                required
                type="tel"
                autocomplete="tel"
                placeholder="09XX XXX XXXX"
              /></label><label v-if="addresses.length">Saved address <select v-model="selectedAddress"><option value="">Enter a new address</option><option
                v-for="a in addresses"
                :key="a.id"
                :value="a.address"
              >{{ a.label }} · {{ a.address }}</option></select></label><label>Delivery address <textarea
                v-model="address"
                required
                autocomplete="street-address"
                placeholder="House / unit, street, barangay, city"
                rows="2"
              /></label><label>Order notes <input
                v-model="notes"
                placeholder="Anything we should know? (optional)"
              /></label><div class="payment-choice">
                <span>Classroom demo only</span><strong>COD · recorded as a demo</strong><p>No real payment or delivery is scheduled.</p>
              </div>
            </template><div class="bill">
              <div><span>Subtotal</span><b>{{ money(shop.subtotal) }}</b></div><div v-if="shop.discount">
                <span>Promo discount</span><b class="discount">−{{ money(shop.discount) }}</b>
              </div><div><span>Delivery fee</span><b>{{ money(shop.deliveryFee) }}</b></div><div class="bill-total">
                <span>Total</span><b>{{ money(shop.total) }}</b>
              </div>
            </div><p
              v-if="checkoutError"
              class="checkout-error"
              role="alert"
            >
              {{ checkoutError }}
            </p><button
              v-if="session.user"
              class="place-order"
              type="submit"
              :disabled="submitting||shop.apiStatus!=='online'||!shop.cart.length"
            >
              {{ submitting?'Sending order…':shop.apiStatus!=='online'?'Backend required to place order':`Place order · ${money(shop.total)}` }} <q-icon name="arrow_forward" />
            </button><small class="checkout-note">Demo order is recorded only after server confirmation. No real payment or delivery.</small>
          </form>
        </div>
      </q-card>
    </q-dialog><q-dialog v-model="successOpen">
      <q-card class="success-card">
        <div class="success-icon">
          <q-icon name="check" />
        </div><div class="eyebrow dark">
          ORDER CONFIRMED
        </div><h2>Recorded.<br /><span>Thank you.</span></h2><p>Demo order saved. No real delivery is scheduled and no payment was taken.</p><div class="receipt">
          <div><span>ORDER REFERENCE</span><b>{{ receipt?.orderNumber }}</b></div><div><span>STATUS</span><b>{{ receipt?.status }}</b></div><div><span>ADDRESS</span><b>{{ receipt?.deliveryAddress }}</b></div><div class="receipt-total">
            <span>TOTAL · COD RECORDED</span><b>{{ money(Number(receipt?.totalAmount??0)) }}</b>
          </div>
        </div><q-btn
          unelevated
          label="Back to the menu"
          @click="successOpen=false"
        />
      </q-card>
    </q-dialog>
  </q-layout>
</template>
<script setup lang="ts">
import {computed,onMounted,ref,watch} from 'vue';import {useQuasar} from 'quasar';import {useRouter} from 'vue-router';import {api} from '../boot/api';import {useShopStore,type Food} from '../stores/shop';import {useSessionStore} from '../stores/session'
const shop=useShopStore(),session=useSessionStore(),$q=useQuasar(),router=useRouter();const search=ref(''),category=ref('All'),sort=ref('popular'),favoritesOnly=ref(false),cartOpen=ref(false),successOpen=ref(false),promoCode=ref(''),promoMessage=ref(''),promoBad=ref(false),promoLoading=ref(false),customerName=ref(''),customerPhone=ref(''),address=ref(''),notes=ref(''),checkoutError=ref(''),submitting=ref(false),receipt=ref<Record<string,unknown>|null>(null),validatedPromoCode=ref(''),validatedPromoPercent=ref(0),addresses=ref<{id:number;label:string;address:string}[]>([]),selectedAddress=ref('')
const categories=computed(()=>['All',...new Set(shop.foods.map(food=>food.category))]);const filtered=computed(()=>{let items=shop.foods.filter(food=>(!search.value||`${food.name} ${food.category} ${food.description}`.toLowerCase().includes(search.value.toLowerCase()))&&(category.value==='All'||food.category===category.value)&&(!favoritesOnly.value||session.favoriteIds.includes(food.id)));if(sort.value==='rating')items=items.sort((a,b)=>b.rating-a.rating);if(sort.value==='popular')items=items.sort((a,b)=>b.reviewsCount-a.reviewsCount);if(sort.value==='price-asc')items=items.sort((a,b)=>a.price-b.price);if(sort.value==='price-desc')items=items.sort((a,b)=>b.price-a.price);return items});function money(value:number){return `₱${Number(value||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2})}`}function qty(id:number){return shop.cart.find(item=>item.id===id)?.quantity||0}function isFav(id:number){return session.favoriteIds.includes(id)}async function toggleFav(id:number){if(!session.user){void $q.dialog({title:'Sign in to save favorites',message:'Your favorites are saved to your account.',cancel:true,ok:'Sign in'}).onOk(()=>{void router.push('/login')});return}try{await api.request({method:isFav(id)?'delete':'put',url:`/favorites/${id}`});await session.loadFavorites()}catch{$q.notify({type:'negative',message:'Could not update favorites. Please try again.'})}}async function signOut(){await session.logout();favoritesOnly.value=false;await router.push('/')}function clearPromo(){shop.discount=0;validatedPromoCode.value='';validatedPromoPercent.value=0;promoMessage.value=''}function refreshPromo(){if(!validatedPromoCode.value)return;if(promoCode.value.trim().toUpperCase()!==validatedPromoCode.value){clearPromo();return}shop.discount=shop.subtotal*validatedPromoPercent.value/100}function resetFilters(){search.value='';category.value='All';favoritesOnly.value=false}async function applyPromo(){if(!promoCode.value.trim()){promoBad.value=true;promoMessage.value='Enter a promo code first.';return}promoLoading.value=true;promoMessage.value='';try{const{data}=await api.post('/promo/validate',{code:promoCode.value.trim().toUpperCase(),subtotal:shop.subtotal});if(!data.success)throw new Error(data.message||'That code could not be applied.');const percent=Number(data.data.discountPercent);validatedPromoCode.value=promoCode.value.trim().toUpperCase();validatedPromoPercent.value=percent;refreshPromo();promoBad.value=false;promoMessage.value=`${percent}% discount applied.`}catch(e){promoBad.value=true;promoMessage.value=e instanceof Error&&e.message!=='Network Error'?e.message:'Promo validation unavailable. Check your connection and try again.'}finally{promoLoading.value=false}}function errorMessage(e:unknown){const err=e as {response?:{data?:{message?:string;errors?:Record<string,string[]>}};message?:string};const errors=err.response?.data?.errors;return errors?Object.values(errors).flat().join(' '):err.response?.data?.message||'Could not reach the server. Your order was not confirmed. Please try again.'}async function checkout(){checkoutError.value='';if(!session.user){checkoutError.value='Sign in before placing your order.';return}if(shop.apiStatus!=='online'){checkoutError.value='Backend unavailable. Your order has not been sent.';return}submitting.value=true;try{const payload={customerName:customerName.value,customerPhone:customerPhone.value,deliveryAddress:address.value,paymentMethod:'COD',orderNotes:notes.value,items:shop.cart.map((item:Food&{quantity:number})=>({productId:item.id,quantity:item.quantity})),promoCode:validatedPromoCode.value||undefined};const serialized=JSON.stringify(payload);if(sessionStorage.getItem('checkout-payload')!==serialized){sessionStorage.setItem('checkout-payload',serialized);sessionStorage.setItem('checkout-key',crypto.randomUUID())}const{data}=await api.post('/orders',payload,{headers:{'Idempotency-Key':sessionStorage.getItem('checkout-key')}});if(!data.success||!data.data)throw new Error(data.message||'The server did not confirm this order.');receipt.value=data.data;shop.cart=[];sessionStorage.removeItem('checkout-key');sessionStorage.removeItem('checkout-payload');clearPromo();promoCode.value='';cartOpen.value=false;successOpen.value=true;await shop.loadMenu()}catch(e){checkoutError.value=errorMessage(e)}finally{submitting.value=false}}watch(()=>[shop.subtotal,promoCode.value],refreshPromo);watch(selectedAddress,val=>{if(val)address.value=val});onMounted(async()=>{await Promise.all([shop.loadMenu(),session.restore(),shop.testBackend().catch(()=>undefined)]);if(session.user){await session.loadFavorites();try{const{data}=await api.get('/addresses');addresses.value=data.data||[]}catch{addresses.value=[]}}})
</script>
