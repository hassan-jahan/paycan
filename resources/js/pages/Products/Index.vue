<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { type BreadcrumbItem } from '@/types'
import PaymentModal from './PaymentModal.vue'

// Props from backend
interface Props {
    productsByType: {
        digital?: Product[]
        physical?: Product[]
        service?: Product[]
        subscription?: Product[]
    }
}

interface Product {
    id: number
    title: string
    slug: string
    description: string
    type: string
    image?: string
    is_active: boolean
    meta: Record<string, any>
    active_prices: ProductPrice[]
}

interface ProductPrice {
    id: number
    title: string
    slug: string
    amount: string
    currency: string
    billing_period: string
    trial_days: number
    is_active: boolean
    gateway_data: Record<string, any>
}

const props = defineProps<Props>()

const page = usePage()
const isAuthenticated = computed(() => !!page.props.auth?.user)

// Payment modal state
const showPaymentModal = ref(false)
const selectedProduct = ref<Product | null>(null)
const selectedPrice = ref<ProductPrice | null>(null)

// Notification state
const showSuccessNotification = ref(false)
const showCancelledNotification = ref(false)

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: '/products' }
]

// Type display names
const typeDisplayNames: Record<string, string> = {
    digital: 'Digital Products',
    physical: 'Physical Products', 
    service: 'Services',
    subscription: 'Subscriptions'
}

// Type descriptions
const typeDescriptions: Record<string, string> = {
    digital: 'Software, courses, and digital downloads',
    physical: 'Physical items shipped to your address',
    service: 'Professional services and consultations',
    subscription: 'Recurring services and subscriptions'
}

// Type icons (using basic shapes for now)
const typeIcons: Record<string, string> = {
    digital: '💻',
    physical: '📦',
    service: '🛠️',
    subscription: '🔄'
}

// Format price for display
const formatPrice = (price: ProductPrice) => {
    const amount = parseFloat(price.amount)
    const formatted = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: price.currency
    }).format(amount)
    
    if (price.billing_period === 'once') {
        return formatted
    }
    
    const periods: Record<string, string> = {
        monthly: '/month',
        yearly: '/year',
        weekly: '/week',
        daily: '/day'
    }
    
    return `${formatted}${periods[price.billing_period] || ''}`
}

// Get billing period display
const getBillingDisplay = (period: string) => {
    const displays: Record<string, string> = {
        once: 'One-time',
        monthly: 'Monthly',
        yearly: 'Annual',
        weekly: 'Weekly',
        daily: 'Daily'
    }
    return displays[period] || period
}

// Open payment modal
const openPaymentModal = (product: Product, price: ProductPrice) => {
    if (!isAuthenticated.value) {
        // Redirect to login with return URL
        const returnUrl = encodeURIComponent(window.location.href)
        window.location.href = `/login?redirect=${returnUrl}`
        return
    }
    
    selectedProduct.value = product
    selectedPrice.value = price
    showPaymentModal.value = true
}

// Get product features
const getProductFeatures = (product: Product) => {
    return product.meta?.features || []
}

// Get category badge variant
const getCategoryVariant = (type: string) => {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        digital: 'default',
        physical: 'secondary', 
        service: 'outline',
        subscription: 'destructive'
    }
    return variants[type] || 'default'
}

// Check URL parameters for payment status
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search)
    
    if (urlParams.get('success') === '1') {
        showSuccessNotification.value = true
        // Clear the URL parameter
        const newUrl = new URL(window.location.href)
        newUrl.searchParams.delete('success')
        window.history.replaceState({}, document.title, newUrl.toString())
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            showSuccessNotification.value = false
        }, 5000)
    }
    
    if (urlParams.get('cancelled') === '1') {
        showCancelledNotification.value = true
        // Clear the URL parameter
        const newUrl = new URL(window.location.href)
        newUrl.searchParams.delete('cancelled')
        window.history.replaceState({}, document.title, newUrl.toString())
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            showCancelledNotification.value = false
        }, 5000)
    }
})
</script>

<template>
    <Head title="Products" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-8">
            <!-- Header -->
            <div class="text-center space-y-4">
                <h1 class="text-4xl font-bold text-foreground">Our Products</h1>
                <p class="text-xl text-muted-foreground max-w-2xl mx-auto">
                    Discover our range of digital products, physical items, services, and subscriptions
                </p>
            </div>
            
            <!-- Success Notification -->
            <div v-if="showSuccessNotification" class="fixed top-4 right-4 z-50 max-w-md" data-testid="success-notification">
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 shadow-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-green-800 dark:text-green-300">
                                Payment Successful!
                            </h3>
                            <p class="mt-1 text-sm text-green-700 dark:text-green-400">
                                Your payment has been processed successfully. You should receive a confirmation email shortly.
                            </p>
                        </div>
                        <div class="ml-4 flex-shrink-0">
                            <button @click="showSuccessNotification = false" class="rounded-md bg-green-50 dark:bg-green-900/20 text-green-500 hover:text-green-600 focus:outline-none" data-testid="dismiss-notification">
                                <span class="sr-only">Dismiss</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cancelled Notification -->
            <div v-if="showCancelledNotification" class="fixed top-4 right-4 z-50 max-w-md" data-testid="cancelled-notification">
                <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4 shadow-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-orange-800 dark:text-orange-300">
                                Payment Cancelled
                            </h3>
                            <p class="mt-1 text-sm text-orange-700 dark:text-orange-400">
                                Your payment was cancelled. No charges have been made to your account.
                            </p>
                        </div>
                        <div class="ml-4 flex-shrink-0">
                            <button @click="showCancelledNotification = false" class="rounded-md bg-orange-50 dark:bg-orange-900/20 text-orange-500 hover:text-orange-600 focus:outline-none" data-testid="dismiss-notification">
                                <span class="sr-only">Dismiss</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products by type -->
            <div class="space-y-12">
                <template v-for="(products, type) in productsByType" :key="type">
                    <section class="space-y-6">
                        <!-- Section header -->
                        <div class="flex items-center gap-4">
                            <div class="text-3xl">{{ typeIcons[type as string] }}</div>
                            <div>
                                <h2 class="text-2xl font-semibold text-foreground">
                                    {{ typeDisplayNames[type as string] }}
                                </h2>
                                <p class="text-muted-foreground">
                                    {{ typeDescriptions[type as string] }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Products grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" data-testid="products-grid">
                            <Card v-for="product in products" :key="product.id" class="flex flex-col" data-testid="product-card">
                                <CardHeader>
                                    <div class="flex items-start justify-between">
                                        <Badge :variant="getCategoryVariant(product.type)" class="mb-2" :data-badge="product.type">
                                            {{ product.type.charAt(0).toUpperCase() + product.type.slice(1) }}
                                        </Badge>
                                    </div>
                                    <CardTitle class="line-clamp-2" data-testid="product-title">{{ product.title }}</CardTitle>
                                    <CardDescription class="line-clamp-3">
                                        {{ product.description }}
                                    </CardDescription>
                                </CardHeader>
                                
                                <CardContent class="flex-1">
                                    <!-- Features -->
                                    <div v-if="getProductFeatures(product).length > 0" class="space-y-2">
                                        <p class="text-sm font-medium text-foreground">Features:</p>
                                        <ul class="text-sm text-muted-foreground space-y-1">
                                            <li v-for="feature in getProductFeatures(product).slice(0, 3)" :key="feature" class="flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 bg-primary rounded-full"></span>
                                                {{ feature }}
                                            </li>
                                            <li v-if="getProductFeatures(product).length > 3" class="text-xs text-muted-foreground/70">
                                                +{{ getProductFeatures(product).length - 3 }} more features
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Meta information -->
                                    <div v-if="product.meta" class="mt-4 space-y-2">
                                        <div v-if="product.meta.category" class="text-xs text-muted-foreground">
                                            Category: {{ product.meta.category }}
                                        </div>
                                        <div v-if="product.meta.duration" class="text-xs text-muted-foreground">
                                            Duration: {{ product.meta.duration }}
                                        </div>
                                        <div v-if="product.meta.weight" class="text-xs text-muted-foreground">
                                            Weight: {{ product.meta.weight }}
                                        </div>
                                    </div>
                                </CardContent>
                                
                                <CardFooter class="flex flex-col gap-3">
                                    <Separator />
                                    
                                    <!-- Pricing options -->
                                    <div class="w-full space-y-2">
                                        <div v-for="price in product.active_prices" :key="price.id" 
                                             class="flex items-center justify-between p-3 border rounded-lg hover:bg-accent/50 transition-colors">
                                            <div>
                                                <div class="font-medium">{{ price.title }}</div>
                                                <div class="text-sm text-muted-foreground">
                                                    {{ getBillingDisplay(price.billing_period) }}
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="text-right">
                                                    <div class="text-lg font-semibold">{{ formatPrice(price) }}</div>
                                                    <div v-if="price.trial_days > 0" class="text-xs text-green-600">
                                                        {{ price.trial_days }} day free trial
                                                    </div>
                                                </div>
                                                <Button @click="openPaymentModal(product, price)" size="sm" data-testid="price-button">
                                                    {{ price.billing_period === 'once' ? 'Buy Now' : 'Subscribe' }}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </CardFooter>
                            </Card>
                        </div>
                    </section>
                </template>
            </div>

            <!-- Empty state -->
            <div v-if="Object.keys(productsByType).length === 0" class="text-center py-12">
                <div class="text-6xl mb-4">🛍️</div>
                <h3 class="text-xl font-semibold text-foreground mb-2">No Products Available</h3>
                <p class="text-muted-foreground">Check back later for new products and services.</p>
            </div>
        </div>

        <!-- Payment Modal -->
        <PaymentModal
            :show="showPaymentModal"
            :product="selectedProduct"
            :price="selectedPrice"
            @close="showPaymentModal = false"
        />
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>