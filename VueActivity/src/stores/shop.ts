import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { api } from '../boot/api'

export interface Food { id: number; name: string; category: string; price: number; prepTime: string; tag: string; description: string; image: string; available: boolean; stock: number; rating: number; reviewsCount: number }
export interface BasketItem extends Food { quantity: number }

const catalog: Food[] = [
  { id: 1, name: 'Signature Angus Cheeseburger', category: 'Burgers', price: 249, rating: 4.9, reviewsCount: 142, prepTime: '15–20 min', tag: 'Best seller', description: 'Grilled Angus beef, melted cheddar, caramelized onions, crisp greens and truffle aioli on toasted brioche.', image: '/images/burger.jpg', available: true, stock: 25 },
  { id: 2, name: 'Artisan Truffle Pepperoni Pizza', category: 'Pizza', price: 489, rating: 4.9, reviewsCount: 208, prepTime: '20–25 min', tag: 'Chef’s choice', description: 'Hand-stretched sourdough with San Marzano tomato, spicy pepperoni, Fior di Latte and white truffle oil.', image: '/images/pizza.jpg', available: true, stock: 18 },
  { id: 3, name: 'Creamy Smoked Bacon Carbonara', category: 'Pasta', price: 289, rating: 4.8, reviewsCount: 96, prepTime: '15–18 min', tag: 'Must try', description: 'Al dente fettuccine in parmesan cream with hardwood-smoked bacon, cracked pepper and garlic crostini.', image: '/images/pasta.jpg', available: true, stock: 14 },
  { id: 4, name: 'Crispy Garlic Honey Fried Chicken', category: 'Chicken', price: 269, rating: 4.9, reviewsCount: 310, prepTime: '15–20 min', tag: 'Top rated', description: 'Double-fried crunchy chicken glazed with honey garlic, toasted sesame, gravy and garlic rice.', image: '/images/chicken.jpg', available: true, stock: 30 },
  { id: 5, name: 'Sizzling Pork Sisig Bowl', category: 'Rice Bowls', price: 219, rating: 4.9, reviewsCount: 184, prepTime: '12–15 min', tag: 'Pinoy favorite', description: 'Crispy pork jowl, red onion, calamansi and chili with a sunny egg over fragrant garlic rice.', image: '/images/rice-bowl.jpg', available: true, stock: 22 },
  { id: 6, name: 'Crispy Truffle Parmesan Fries', category: 'Sides', price: 149, rating: 4.7, reviewsCount: 88, prepTime: '10 min', tag: 'Popular side', description: 'Golden skin-on fries with white truffle oil, aged parmesan, parsley and garlic mayo.', image: '/images/fries.jpg', available: true, stock: 35 },
  { id: 7, name: 'Belgian Dark Chocolate Lava Shake', category: 'Drinks', price: 169, rating: 4.8, reviewsCount: 115, prepTime: '5–8 min', tag: 'Sweet treat', description: 'Belgian dark chocolate blended with milk and topped with chocolate flakes and warm drizzle.', image: '/images/shake.jpg', available: true, stock: 20 },
  { id: 8, name: 'Mango Graham Float Sundae', category: 'Desserts', price: 159, rating: 4.9, reviewsCount: 162, prepTime: '5 min', tag: 'Best dessert', description: 'Guimaras mango layered with velvety cream and honey graham, chilled to perfection.', image: '/images/dessert.jpg', available: true, stock: 16 },
  { id: 9, name: 'Smoked BBQ Bacon Burger', category: 'Burgers', price: 279, rating: 4.8, reviewsCount: 75, prepTime: '15–20 min', tag: 'New', description: 'Flame-grilled beef with smoky bacon, sharp cheddar, onion rings and Texas BBQ sauce.', image: '/images/bbq-burger.jpg', available: true, stock: 15 },
  { id: 10, name: 'Roasted Garlic Iced Cold Brew', category: 'Drinks', price: 139, rating: 4.7, reviewsCount: 64, prepTime: '3–5 min', tag: 'Craft coffee', description: '18-hour steeped Arabica cold brew with oat milk and salted brown sugar swirl.', image: '/images/cold-brew.jpg', available: true, stock: 25 },
  { id: 11, name: 'Four Cheese Quattro Margherita', category: 'Pizza', price: 459, rating: 4.8, reviewsCount: 92, prepTime: '20–25 min', tag: 'Vegetarian', description: 'Fresh mozzarella, gorgonzola, aged parmesan and ricotta with basil and olive oil.', image: '/images/quattro-pizza.jpg', available: false, stock: 0 },
  { id: 12, name: 'Crispy Buffalo Chicken Tenders', category: 'Chicken', price: 239, rating: 4.8, reviewsCount: 128, prepTime: '15 min', tag: 'Spicy', description: 'Golden buttermilk tenders tossed in tangy buffalo glaze with cool ranch and celery.', image: '/images/tenders.jpg', available: true, stock: 19 }
]

export const useShopStore = defineStore('shop', () => {
  const foods = ref<Food[]>(catalog)
  const savedCart = (() => { try { const value=JSON.parse(localStorage.getItem('kaontime-cart')||'[]') as BasketItem[]; return Array.isArray(value)?value.filter(x=>Number.isInteger(x.id)&&Number.isInteger(x.quantity)&&x.quantity>0&&x.quantity<100):[] } catch { return [] } })()
  const cart = ref<BasketItem[]>(savedCart)
  const helloMessage = ref('')
  const apiStatus = ref<'checking' | 'online' | 'offline'>('checking')
  const apiError = ref('')
  const cartCount = computed(() => cart.value.reduce((n, item) => n + item.quantity, 0))
  const subtotal = computed(() => cart.value.reduce((n, item) => n + item.price * item.quantity, 0))
  const deliveryFee = computed(() => cart.value.length ? 50 : 0)
  const discount = ref(0)
  const total = computed(() => Math.max(0, subtotal.value - discount.value + deliveryFee.value))
  watch(cart, value => { try { localStorage.setItem('kaontime-cart', JSON.stringify(value)) } catch { /* Storage may be unavailable. */ } }, { deep:true })

  async function testBackend() {
    apiStatus.value = 'checking'; apiError.value = ''; helloMessage.value = ''
    try {
      const { data } = await api.get<{ message: string }>('/hello')
      helloMessage.value = data.message; apiStatus.value = 'online'
      return data.message
    } catch (error) {
      apiStatus.value = 'offline'
      apiError.value = 'Backend unavailable. Start the API server at 127.0.0.1:8000 and try again.'
      throw error
    }
  }
  async function loadMenu() {
    try {
      const { data } = await api.get<{ success: boolean; data: Food[] }>('/menu')
      if (data.success && Array.isArray(data.data) && data.data.length) foods.value = data.data.map(food => ({ ...food, image: food.image?.startsWith('http') ? food.image : `/images/${(food.image || '').split('/').pop()}` }))
    } catch { /* Keep the original menu available; API status is surfaced separately. */ }
  }
  async function applyPromo(code: string) {
    const { data } = await api.post('/promo/validate', { code, subtotal: subtotal.value })
    if (!data.success) throw new Error(data.message || 'That promo code could not be applied.')
    return data.data.discountPercent as number
  }
  function add(food: Food) {
    if (!food.available || food.stock < 1) return
    const item = cart.value.find(row => row.id === food.id)
    if (item) { if (item.quantity < food.stock) item.quantity++ } else cart.value.push({ ...food, quantity: 1 })
  }
  function change(id: number, amount: number) {
    const item = cart.value.find(row => row.id === id)
    if (!item) return
    item.quantity += amount
    if (item.quantity < 1) cart.value = cart.value.filter(row => row.id !== id)
    else item.quantity = Math.min(item.quantity, item.stock)
  }
  return { foods, cart, helloMessage, apiStatus, apiError, cartCount, subtotal, deliveryFee, discount, total, testBackend, loadMenu, applyPromo, add, change }
})
