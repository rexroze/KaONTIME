export interface Food {
  id: number
  name: string
  category: string
  price: number
  rating: number
  reviewsCount: number
  prepTime: string
  tag: string | null
  description: string
  image: string
  available: boolean
  stock: number
}

export interface OrderItem {
  productId: number | null
  name: string
  price: number
  quantity: number
  subtotal: number
}

export type OrderStatus = 'Pending' | 'Preparing' | 'Out for Delivery' | 'Delivered' | 'Cancelled'

export interface Order {
  orderNumber: string
  status: OrderStatus
  createdAt: string | null
  customerName: string
  customerPhone: string
  deliveryAddress: string
  paymentMethod: string
  orderNotes: string | null
  subtotal: number
  discount: number
  deliveryFee: number
  totalAmount: number
  items: OrderItem[]
}

export interface PageMeta {
  currentPage: number
  lastPage: number
  total: number
  perPage: number
}

export interface Dashboard {
  todayOrders: number
  todayRevenue: number
  pendingOrders: number
  preparingOrders: number
  totalCustomers: number
  lowStockCount: number
  recentOrders: Order[]
}

export interface Promo {
  id: number
  code: string
  discountPercent: number
  description: string | null
  minSpend: number
  active: boolean
  createdAt: string | null
}

export interface Customer {
  id: number
  name: string
  email: string
  isAdmin: boolean
  ordersCount: number
  totalSpent: number
  lastOrderAt: string | null
  createdAt: string | null
}

export interface StoreSettings {
  deliveryFee: number
  storeOpen: boolean
  storeMessage: string
}

export interface Category {
  name: string
  productCount: number
}

export interface Report {
  totalOrders: number
  pendingOrders: number
  demoRevenue: number
  topDishes: { name: string; quantity: number }[]
  lowStock: Food[]
  statusCounts: Record<string, number>
}
