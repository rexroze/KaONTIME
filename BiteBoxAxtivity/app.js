const { createApp } = Vue

const API_BASE_URL = 'http://localhost:5000/api'

createApp({
    data() {
        return {
            brandName: 'KaONTIME',
            tagline: 'Fresh Eats, Right On Time',
            apiConnected: false,
            apiLoading: false,
            apiError: null,
            search: '',
            selectedCategory: 'All',
            sortBy: 'popular',
            showCart: false,
            showSuccessModal: false,
            showOrdersModal: false,
            showFavoritesOnly: false,
            activePromoCode: '',
            appliedDiscount: 0,
            promoMessage: '',
            promoStatus: '', // 'success' | 'error'
            toast: {
                show: false,
                message: '',
                icon: '✓',
                timeout: null
            },
            completedOrder: null,
            customerName: 'Jameboy Escartin',
            customerPhone: '0917-123-4567',
            deliveryAddress: 'Gorordo Ave, Lahug, Cebu City',
            paymentMethod: 'GCash',
            orderNotes: '',
            favorites: [1, 4], // default favorites
            cart: [],
            dbOrders: [],
            isSubmittingOrder: false,
            foods: [
                {
                    id: 1,
                    name: 'Signature Angus Cheeseburger',
                    category: 'Burgers',
                    price: 249,
                    rating: 4.9,
                    reviewsCount: 142,
                    prepTime: '15-20 min',
                    tag: 'Best Seller',
                    description: '100% pure grilled Angus beef patty, melted cheddar, caramelized onions, crisp greens, and signature truffle aioli on a toasted brioche bun.',
                    image: 'images/burger.jpg',
                    available: true,
                    stock: 25
                },
                {
                    id: 2,
                    name: 'Artisan Truffle Pepperoni Pizza',
                    category: 'Pizza',
                    price: 489,
                    rating: 4.9,
                    reviewsCount: 208,
                    prepTime: '20-25 min',
                    tag: 'Chef Choice',
                    description: 'Hand-stretched sourdough crust with San Marzano tomato sauce, spicy Italian pepperoni, Fior di Latte mozzarella, and white truffle oil drizzle.',
                    image: 'images/pizza.jpg',
                    available: true,
                    stock: 18
                },
                {
                    id: 3,
                    name: 'Creamy Smoked Bacon Carbonara',
                    category: 'Pasta',
                    price: 289,
                    rating: 4.8,
                    reviewsCount: 96,
                    prepTime: '15-18 min',
                    tag: 'Must Try',
                    description: 'Al dente fettuccine tossed in rich egg yolk parmesan cream sauce, crispy hardwood smoked bacon bits, cracked black pepper, and garlic herb crostini.',
                    image: 'images/pasta.jpg',
                    available: true,
                    stock: 14
                },
                {
                    id: 4,
                    name: 'Crispy Garlic Honey Fried Chicken',
                    category: 'Chicken',
                    price: 269,
                    rating: 4.9,
                    reviewsCount: 310,
                    prepTime: '15-20 min',
                    tag: 'Top Rated',
                    description: 'Double-fried ultra crunchy chicken thighs coated with sweet honey-garlic glaze, toasted sesame seeds, served with savory gravy and garlic rice.',
                    image: 'images/chicken.jpg',
                    available: true,
                    stock: 30
                },
                {
                    id: 5,
                    name: 'Sizzling Pork Sisig Bowl',
                    category: 'Rice Bowls',
                    price: 219,
                    rating: 4.9,
                    reviewsCount: 184,
                    prepTime: '12-15 min',
                    tag: 'Pinoy Favorite',
                    description: 'Crispy minced pork jowl with sautéed red onions, fresh calamansi, chili peppers, topped with a runny sunny-side egg over fragrant garlic jasmine rice.',
                    image: 'images/rice-bowl.jpg',
                    available: true,
                    stock: 22
                },
                {
                    id: 6,
                    name: 'Crispy Truffle Parmesan Fries',
                    category: 'Sides',
                    price: 149,
                    rating: 4.7,
                    reviewsCount: 88,
                    prepTime: '10 min',
                    tag: 'Popular Side',
                    description: 'Skin-on golden Russet potatoes tossed in fragrant white truffle oil, grated aged parmesan, fresh parsley, served with house garlic mayo.',
                    image: 'images/fries.jpg',
                    available: true,
                    stock: 35
                },
                {
                    id: 7,
                    name: 'Belgian Dark Chocolate Lava Shake',
                    category: 'Drinks',
                    price: 169,
                    rating: 4.8,
                    reviewsCount: 115,
                    prepTime: '5-8 min',
                    tag: 'Sweet Treat',
                    description: 'Decadent Belgian dark chocolate ganache blended with premium dairy milk, topped with whipped mousse, chocolate flakes, and warm drizzle.',
                    image: 'images/shake.jpg',
                    available: true,
                    stock: 20
                },
                {
                    id: 8,
                    name: 'Mango Graham Float Sundae',
                    category: 'Desserts',
                    price: 159,
                    rating: 4.9,
                    reviewsCount: 162,
                    prepTime: '5 min',
                    tag: 'Best Dessert',
                    description: 'Sweet Guimaras ripe mango cubes layered with velvety whipped sweet cream and crushed honey graham crackers, chilled to perfection.',
                    image: 'images/dessert.jpg',
                    available: true,
                    stock: 16
                },
                {
                    id: 9,
                    name: 'Smoked BBQ Bacon Burger',
                    category: 'Burgers',
                    price: 279,
                    rating: 4.8,
                    reviewsCount: 75,
                    prepTime: '15-20 min',
                    tag: 'New',
                    description: 'Flame-grilled prime beef patty topped with crispy hickory smoked bacon, sharp cheddar cheese, beer-battered onion rings, and Texas smoky BBQ sauce.',
                    image: 'images/bbq-burger.jpg',
                    available: true,
                    stock: 15
                },
                {
                    id: 10,
                    name: 'Roasted Garlic Iced Cold Brew',
                    category: 'Drinks',
                    price: 139,
                    rating: 4.7,
                    reviewsCount: 64,
                    prepTime: '3-5 min',
                    tag: 'Craft Coffee',
                    description: '18-hour steeped single-origin Arabica cold brew poured over artisan ice rocks with silky oat milk and salted brown sugar swirl.',
                    image: 'images/cold-brew.jpg',
                    available: true,
                    stock: 25
                },
                {
                    id: 11,
                    name: 'Four Cheese Quattro Margherita',
                    category: 'Pizza',
                    price: 459,
                    rating: 4.8,
                    reviewsCount: 92,
                    prepTime: '20-25 min',
                    tag: 'Vegetarian',
                    description: 'A harmonious blend of Fresh Mozzarella, Gorgonzola, aged Parmesan, and Ricotta cheese with fresh basil and extra virgin olive oil.',
                    image: 'images/quattro-pizza.jpg',
                    available: false,
                    stock: 0
                },
                {
                    id: 12,
                    name: 'Crispy Buffalo Chicken Tenders',
                    category: 'Chicken',
                    price: 239,
                    rating: 4.8,
                    reviewsCount: 128,
                    prepTime: '15 min',
                    tag: 'Spicy',
                    description: 'Golden buttermilk soaked chicken breast tenders tossed in spicy tangy buffalo glaze, served with cool buttermilk ranch and celery sticks.',
                    image: 'images/tenders.jpg',
                    available: true,
                    stock: 19
                }
            ]
        }
    },

    computed: {
        categories() {
            const rawCategories = [...new Set(this.foods.map(food => food.category))]
            return ['All', ...rawCategories]
        },

        filteredFoods() {
            let list = this.foods.filter(food => {
                const searchLower = this.search.trim().toLowerCase()
                const matchesSearch = !searchLower || 
                    food.name.toLowerCase().includes(searchLower) ||
                    food.description.toLowerCase().includes(searchLower) ||
                    food.category.toLowerCase().includes(searchLower)

                const matchesCategory = this.selectedCategory === 'All' || food.category === this.selectedCategory
                const matchesFavorites = !this.showFavoritesOnly || this.favorites.includes(food.id)

                return matchesSearch && matchesCategory && matchesFavorites
            })

            // Sort logic
            if (this.sortBy === 'rating') {
                list.sort((a, b) => b.rating - a.rating)
            } else if (this.sortBy === 'price-asc') {
                list.sort((a, b) => a.price - b.price)
            } else if (this.sortBy === 'price-desc') {
                list.sort((a, b) => b.price - a.price)
            } else if (this.sortBy === 'popular') {
                list.sort((a, b) => b.reviewsCount - a.reviewsCount)
            }

            return list
        },

        cartCount() {
            return this.cart.reduce((total, item) => total + item.quantity, 0)
        },

        cartSubtotal() {
            return this.cart.reduce((total, item) => total + (item.price * item.quantity), 0)
        },

        freeDeliveryThreshold() {
            return 500
        },

        freeDeliveryProgress() {
            if (this.cartSubtotal >= this.freeDeliveryThreshold) return 100
            return Math.min(100, Math.round((this.cartSubtotal / this.freeDeliveryThreshold) * 100))
        },

        deliveryFee() {
            if (this.cart.length === 0) return 0
            return this.cartSubtotal >= this.freeDeliveryThreshold ? 0 : 49
        },

        discountAmount() {
            if (this.appliedDiscount > 0) {
                return (this.cartSubtotal * this.appliedDiscount)
            }
            return 0
        },

        cartTotal() {
            if (this.cart.length === 0) return 0
            const total = this.cartSubtotal - this.discountAmount + this.deliveryFee
            return Math.max(0, total)
        }
    },

    methods: {
        async checkBackend() {
            try {
                this.apiLoading = true
                const response = await fetch(`${API_BASE_URL}/health`)
                if (response.ok) {
                    const data = await response.json()
                    this.apiConnected = true
                    this.apiError = null
                    console.log('⚡ Connected to Express & Neon DB:', data)
                } else {
                    this.apiConnected = false
                }
            } catch (err) {
                this.apiConnected = false
                this.apiError = err.message
                console.warn('Backend offline, running in local fallback mode.')
            } finally {
                this.apiLoading = false
            }
        },

        async fetchMenu() {
            try {
                const response = await fetch(`${API_BASE_URL}/menu`)
                if (response.ok) {
                    const result = await response.json()
                    if (result.success && result.data.length > 0) {
                        this.foods = result.data
                        this.apiConnected = true
                    }
                }
            } catch (err) {
                console.warn('Could not fetch remote menu, using preloaded food catalog.')
            }
        },

        async fetchDbOrders() {
            try {
                const response = await fetch(`${API_BASE_URL}/orders`)
                if (response.ok) {
                    const result = await response.json()
                    if (result.success) {
                        this.dbOrders = result.data
                        this.showOrdersModal = true
                    }
                } else {
                    this.showToastNotification('Failed to fetch orders from database', '❌')
                }
            } catch (err) {
                this.showToastNotification('Express backend is currently unreachable', '⚠️')
            }
        },

        async updateOrderStatus(orderId, newStatus) {
            try {
                const response = await fetch(`${API_BASE_URL}/orders/${orderId}/status`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: newStatus })
                })
                if (response.ok) {
                    const result = await response.json()
                    this.showToastNotification(`Order status updated to: ${newStatus}`, '✅')
                    // Update local list
                    const found = this.dbOrders.find(o => o.id === orderId)
                    if (found) found.status = newStatus
                }
            } catch (err) {
                this.showToastNotification('Error updating order status', '❌')
            }
        },

        getCategoryIcon(category) {
            const icons = {
                'All': '🍴',
                'Burgers': '🍔',
                'Pizza': '🍕',
                'Pasta': '🍝',
                'Chicken': '🍗',
                'Rice Bowls': '🍚',
                'Sides': '🍟',
                'Drinks': '🥤',
                'Desserts': '🍨'
            }
            return icons[category] || '🍽️'
        },

        getItemCartQuantity(foodId) {
            const item = this.cart.find(i => i.id === foodId)
            return item ? item.quantity : 0
        },

        addToCart(food) {
            if (!food.available || food.stock <= 0) return

            const existingItem = this.cart.find(item => item.id === food.id)
            if (existingItem) {
                if (existingItem.quantity < food.stock) {
                    existingItem.quantity++
                    this.showToastNotification(`Added another ${food.name} to order!`)
                } else {
                    this.showToastNotification(`Max stock reached for this item.`, '⚠️')
                }
            } else {
                this.cart.push({
                    ...food,
                    quantity: 1
                })
                this.showToastNotification(`Added ${food.name} to order!`)
            }
        },

        increaseQuantity(item) {
            const food = this.foods.find(f => f.id === item.id)
            if (food && item.quantity < food.stock) {
                item.quantity++
            } else {
                this.showToastNotification(`Maximum available stock reached.`, '⚠️')
            }
        },

        decreaseQuantity(item) {
            if (item.quantity > 1) {
                item.quantity--
            } else {
                this.removeFromCart(item)
            }
        },

        removeFromCart(item) {
            const index = this.cart.findIndex(cartItem => cartItem.id === item.id)
            if (index !== -1) {
                const removedItem = this.cart[index]
                this.cart.splice(index, 1)
                this.showToastNotification(`Removed ${removedItem.name} from order.`, '🗑️')
            }
        },

        toggleFavorite(food) {
            const index = this.favorites.indexOf(food.id)
            if (index === -1) {
                this.favorites.push(food.id)
                this.showToastNotification(`Saved ${food.name} to favorites!`, '❤️')
            } else {
                this.favorites.splice(index, 1)
                this.showToastNotification(`Removed from favorites.`, '🤍')
            }
        },

        isFavorite(id) {
            return this.favorites.includes(id)
        },

        async applyPromo() {
            const code = this.activePromoCode.trim().toUpperCase()
            if (!code) {
                this.promoMessage = 'Please enter a valid coupon code.'
                this.promoStatus = 'error'
                return
            }

            // Try validating with Express Backend API first
            if (this.apiConnected) {
                try {
                    const response = await fetch(`${API_BASE_URL}/promo/validate`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code, subtotal: this.cartSubtotal })
                    })
                    const result = await response.json()
                    if (result.success) {
                        this.appliedDiscount = result.data.discountPercent / 100
                        this.promoMessage = result.message
                        this.promoStatus = 'success'
                        this.showToastNotification(`🎉 ${result.data.discountPercent}% promo voucher applied!`)
                        return
                    } else {
                        this.promoMessage = result.message || 'Invalid promo code.'
                        this.promoStatus = 'error'
                        return
                    }
                } catch (e) {
                    console.warn('API promo check failed, using fallback.')
                }
            }

            // Client-side fallback
            if (code === 'ONTIME20' || code === 'KAONTIME') {
                this.appliedDiscount = 0.20
                this.promoMessage = 'Promo applied! 20% discount added to your meal.'
                this.promoStatus = 'success'
                this.showToastNotification('🎉 20% promo voucher applied!')
            } else if (code === 'SEMI50') {
                this.appliedDiscount = 0.50
                this.promoMessage = 'Semifinal Special 50% discount applied!'
                this.promoStatus = 'success'
                this.showToastNotification('🎉 50% discount voucher applied!')
            } else {
                this.promoMessage = 'Invalid promo code. Try "KAONTIME" or "SEMI50"!'
                this.promoStatus = 'error'
            }
        },

        showToastNotification(message, icon = '✓') {
            if (this.toast.timeout) {
                clearTimeout(this.toast.timeout)
            }
            this.toast.message = message
            this.toast.icon = icon
            this.toast.show = true

            this.toast.timeout = setTimeout(() => {
                this.toast.show = false
            }, 2600)
        },

        async checkout() {
            if (this.cart.length === 0) return

            this.isSubmittingOrder = true

            const orderRef = 'KAON-' + Date.now().toString().slice(-6) + '-' + Math.floor(100 + Math.random() * 900)
            const estimatedDeliveryMinutes = Math.floor(20 + Math.random() * 15)

            const orderPayload = {
                customerName: this.customerName || 'Valued Foodie',
                customerPhone: this.customerPhone || '0917-123-4567',
                deliveryAddress: this.deliveryAddress,
                paymentMethod: this.paymentMethod,
                orderNotes: this.orderNotes,
                items: this.cart.map(item => ({
                    productId: item.id,
                    name: item.name,
                    price: item.price,
                    quantity: item.quantity
                })),
                subtotal: this.cartSubtotal,
                discount: this.discountAmount,
                deliveryFee: this.deliveryFee,
                totalAmount: this.cartTotal,
                promoCode: this.activePromoCode || null
            }

            let savedOrder = null

            // Submit to Express backend + Neon PostgreSQL
            if (this.apiConnected) {
                try {
                    const response = await fetch(`${API_BASE_URL}/orders`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(orderPayload)
                    })
                    const result = await response.json()
                    if (result.success) {
                        savedOrder = result.data
                        this.showToastNotification('🚀 Order placed successfully!')
                    }
                } catch (err) {
                    console.error('Failed to submit order to API:', err)
                }
            }

            this.completedOrder = {
                orderNumber: savedOrder ? savedOrder.orderNumber : orderRef,
                items: JSON.parse(JSON.stringify(this.cart)),
                subtotal: this.cartSubtotal,
                discount: this.discountAmount,
                deliveryFee: this.deliveryFee,
                total: this.cartTotal,
                address: this.deliveryAddress,
                paymentMethod: this.paymentMethod,
                deliveryTime: `${estimatedDeliveryMinutes} mins`,
                placedAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                dbSaved: Boolean(savedOrder)
            }

            this.cart = []
            this.activePromoCode = ''
            this.appliedDiscount = 0
            this.promoMessage = ''
            this.showCart = false
            this.showSuccessModal = true
            this.isSubmittingOrder = false
        },

        closeSuccessModal() {
            this.showSuccessModal = false
            this.completedOrder = null
        }
    },

    async mounted() {
        await this.checkBackend()
        await this.fetchMenu()
    }
}).mount('#app')