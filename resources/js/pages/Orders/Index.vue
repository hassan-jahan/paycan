<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { type BreadcrumbItem } from '@/types'

// Props from backend
interface Props {orders
    orders: {
        data: Order[]
        links: any[]
        meta: any
    }
}

interface Order {
    id: number
    status: string
    total: string
    currency: string
    created_at: string
    updated_at: string
    product_price: {
        id: number
        title: string
        amount: string
        billing_period: string
        product: Product
    }
    transactions: Transaction[]
    fulfillments: Fulfillment[]
}

interface Product {
    id: number
    title: string
    type: string
    description: string
    meta: Record<string, any>
}

interface Transaction {
    id: number
    type: string
    status: string
    amount: string
    currency: string
    gateway: string
    gateway_transaction_id: string
    created_at: string
    meta?: Record<string, any>
}

interface Fulfillment {
    id: number
    status: string
    type: string
    tracking_number?: string
    carrier?: string
    fulfilled_at?: string
    meta?: Record<string, any>
}

const props = defineProps<Props>()

// State for modal management
const selectedOrder = ref<Order | null>(null)
const showOrderModal = ref(false)

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Orders', href: '/payment/orders' }
]

// Format price
const formatPrice = (amount: string, currency: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(parseFloat(amount))
}

// Format date
const formatDate = (dateString: string) => {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(dateString))
}

// Get status variant
const getStatusVariant = (status: string) => {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        pending: 'outline',
        processing: 'secondary',
        completed: 'default',
        cancelled: 'destructive',
        failed: 'destructive'
    }
    return variants[status] || 'outline'
}

// Get status display
const getStatusDisplay = (status: string) => {
    const displays: Record<string, string> = {
        pending: 'Pending',
        processing: 'Processing',
        completed: 'Completed',
        cancelled: 'Cancelled',
        failed: 'Failed'
    }
    return displays[status] || status
}

// Get product type icon
const getProductTypeIcon = (type: string) => {
    const icons: Record<string, string> = {
        digital: '💻',
        physical: '📦',
        service: '🛠️',
        subscription: '🔄'
    }
    return icons[type] || '📄'
}

// Get fulfillment status
const getFulfillmentStatus = (order: Order) => {
    if (!order.fulfillments || order.fulfillments.length === 0) {
        return 'Not fulfilled'
    }

    const latestFulfillment = order.fulfillments[order.fulfillments.length - 1]
    return latestFulfillment.status
}

// Get fulfillment info
const getFulfillmentInfo = (order: Order) => {
    if (!order.fulfillments || order.fulfillments.length === 0) {
        return null
    }

    const latestFulfillment = order.fulfillments[order.fulfillments.length - 1]

    if (latestFulfillment.type === 'physical' && latestFulfillment.tracking_number) {
        return {
            type: 'shipping',
            text: `Tracking: ${latestFulfillment.tracking_number}`,
            carrier: latestFulfillment.carrier
        }
    }

    if (latestFulfillment.type === 'digital' && latestFulfillment.meta?.download_link) {
        return {
            type: 'download',
            text: 'Download available',
            link: latestFulfillment.meta.download_link
        }
    }

    if (latestFulfillment.type === 'service' && latestFulfillment.meta?.service_code) {
        return {
            type: 'service',
            text: `Service Code: ${latestFulfillment.meta.service_code}`,
        }
    }

    return null
}

// Modal management functions
const openOrderModal = (order: Order) => {
    selectedOrder.value = order
    showOrderModal.value = true
}

const closeOrderModal = () => {
    showOrderModal.value = false
    selectedOrder.value = null
}
</script>

<template>
    <Head title="My Orders" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-foreground">My Orders</h1>
                    <p class="text-muted-foreground">Track your purchases and downloads</p>
                </div>
                <Link href="/products">
                    <Button>Browse Products</Button>
                </Link>
            </div>

            <!-- Orders list -->
            <div class="space-y-4">
                <template v-if="orders.data && orders.data.length > 0">
                    <Card v-for="order in orders.data" :key="order.id" class="hover:shadow-md transition-shadow">
                        <CardHeader>
                            <div class="flex items-start justify-between">
                                <div>
                                    <CardTitle class="flex items-center gap-2">
                                        <span class="text-xl">{{ getProductTypeIcon(order.product_price.product.type) }}</span>
                                        {{ order.product_price.product.title }}
                                    </CardTitle>
                                    <CardDescription>
                                        Order #{{ order.id }} • {{ order.product_price.title }}
                                    </CardDescription>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="getStatusVariant(order.status)">
                                        {{ getStatusDisplay(order.status) }}
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="space-y-4">
                            <!-- Order details -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <p class="font-medium text-foreground">Total</p>
                                    <p class="text-muted-foreground">{{ formatPrice(order.total, order.currency) }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-foreground">Date</p>
                                    <p class="text-muted-foreground">{{ formatDate(order.created_at) }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-foreground">Fulfillment</p>
                                    <p class="text-muted-foreground">{{ getFulfillmentStatus(order) }}</p>
                                </div>
                            </div>

                            <!-- Fulfillment info -->
                            <template v-if="getFulfillmentInfo(order)">
                                <Separator />
                                <div class="p-3 bg-accent/50 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-foreground">
                                                {{ getFulfillmentInfo(order)?.text }}
                                            </p>
                                            <p v-if="getFulfillmentInfo(order)?.carrier" class="text-xs text-muted-foreground">
                                                via {{ getFulfillmentInfo(order)?.carrier }}
                                            </p>
                                        </div>
                                        <div v-if="getFulfillmentInfo(order)?.type === 'download'" class="flex gap-2">
                                            <Button size="sm" variant="outline" asChild>
                                                <a :href="getFulfillmentInfo(order)?.link" target="_blank">
                                                    Download
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Payment info -->
                            <template v-if="order.transactions && order.transactions.length > 0">
                                <Separator />
                                <div class="space-y-2">
                                    <p class="text-sm font-medium text-foreground">Payment Details</p>
                                    <div v-for="transaction in order.transactions" :key="transaction.id"
                                         class="flex items-center justify-between text-sm">
                                        <div class="flex items-center gap-2">
                                            <Badge :variant="getStatusVariant(transaction.status)" size="sm">
                                                {{ getStatusDisplay(transaction.status) }}
                                            </Badge>
                                            <span class="text-muted-foreground">
                                                {{ transaction.gateway.charAt(0).toUpperCase() + transaction.gateway.slice(1) }}
                                            </span>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium">{{ formatPrice(transaction.amount, transaction.currency) }}</p>
                                            <p class="text-xs text-muted-foreground">{{ formatDate(transaction.created_at) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Actions -->
                            <div class="flex justify-end">
                                <Button variant="outline" size="sm" @click="openOrderModal(order)">View Details</Button>
                            </div>
                        </CardContent>
                    </Card>
                </template>

                <!-- Empty state -->
                <div v-else class="text-center py-12">
                    <div class="text-6xl mb-4">🛒</div>
                    <h3 class="text-xl font-semibold text-foreground mb-2">No Orders Yet</h3>
                    <p class="text-muted-foreground mb-6">You haven't made any purchases yet.</p>
                    <Link href="/products">
                        <Button>Browse Products</Button>
                    </Link>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="orders.links && orders.links.length > 3" class="flex items-center justify-center gap-2">
                <template v-for="link in orders.links" :key="link.label">
                    <Button
                        v-if="link.url"
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-primary text-primary-foreground': link.active }"
                        asChild
                    >
                        <Link :href="link.url" v-html="link.label" />
                    </Button>
                    <span v-else class="px-3 py-2 text-sm text-muted-foreground" v-html="link.label" />
                </template>
            </div>
        </div>

        <!-- Order Details Modal -->
        <Dialog v-model:open="showOrderModal" v-if="selectedOrder">
            <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <span class="text-xl">{{ getProductTypeIcon(selectedOrder.product_price.product.type) }}</span>
                        Order #{{ selectedOrder.id }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ selectedOrder.product_price.product.title }} • {{ selectedOrder.product_price.title }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-6">
                    <!-- Order Status -->
                    <div class="flex items-center justify-between p-4 bg-accent/20 rounded-lg">
                        <div>
                            <h3 class="font-semibold text-lg">{{ formatPrice(selectedOrder.total, selectedOrder.currency) }}</h3>
                            <p class="text-muted-foreground">{{ formatDate(selectedOrder.created_at) }}</p>
                        </div>
                        <Badge :variant="getStatusVariant(selectedOrder.status)" class="text-base px-3 py-1">
                            {{ getStatusDisplay(selectedOrder.status) }}
                        </Badge>
                    </div>

                    <!-- Order Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h4 class="font-semibold">Order Information</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Order ID:</span>
                                    <span class="font-mono">#{{ selectedOrder.id }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Status:</span>
                                    <Badge :variant="getStatusVariant(selectedOrder.status)">{{ getStatusDisplay(selectedOrder.status) }}</Badge>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Date:</span>
                                    <span>{{ formatDate(selectedOrder.created_at) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Total:</span>
                                    <span class="font-semibold">{{ formatPrice(selectedOrder.total, selectedOrder.currency) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Fulfillment:</span>
                                    <span>{{ getFulfillmentStatus(selectedOrder) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="font-semibold">Product Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Product:</p>
                                    <p class="font-medium">{{ selectedOrder.product_price.product.title }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Price Plan:</p>
                                    <p>{{ selectedOrder.product_price.title }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground mb-2">Description:</p>
                                    <p class="text-sm">{{ selectedOrder.product_price.product.description }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Product Type:</p>
                                    <Badge variant="outline">{{ selectedOrder.product_price.product.type }}</Badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fulfillment Details -->
                    <div v-if="selectedOrder.fulfillments && selectedOrder.fulfillments.length > 0">
                        <Separator />
                        <div class="space-y-4">
                            <h4 class="font-semibold">Fulfillment Details</h4>
                            <div v-for="fulfillment in selectedOrder.fulfillments" :key="fulfillment.id" 
                                 class="p-4 border rounded-lg space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium">{{ fulfillment.type.charAt(0).toUpperCase() + fulfillment.type.slice(1) }} Fulfillment</p>
                                        <p class="text-sm text-muted-foreground">{{ fulfillment.status }}</p>
                                    </div>
                                    <Badge :variant="getStatusVariant(fulfillment.status)">{{ fulfillment.status }}</Badge>
                                </div>
                                
                                <div v-if="fulfillment.tracking_number" class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Tracking Number:</span>
                                        <span class="font-mono bg-accent/50 px-2 py-1 rounded">{{ fulfillment.tracking_number }}</span>
                                    </div>
                                    <div v-if="fulfillment.carrier" class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">Carrier:</span>
                                        <span>{{ fulfillment.carrier }}</span>
                                    </div>
                                </div>

                                <div v-if="fulfillment.meta?.download_link" class="flex justify-between items-center">
                                    <span class="text-sm text-muted-foreground">Download:</span>
                                    <Button size="sm" variant="outline" asChild>
                                        <a :href="fulfillment.meta.download_link" target="_blank">
                                            Download File
                                        </a>
                                    </Button>
                                </div>

                                <div v-if="fulfillment.meta?.service_code" class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">Service Code:</span>
                                    <span class="font-mono bg-accent/50 px-2 py-1 rounded">{{ fulfillment.meta.service_code }}</span>
                                </div>

                                <div v-if="fulfillment.fulfilled_at" class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">Fulfilled:</span>
                                    <span>{{ formatDate(fulfillment.fulfilled_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History -->
                    <div v-if="selectedOrder.transactions && selectedOrder.transactions.length > 0">
                        <Separator />
                        <div class="space-y-4">
                            <h4 class="font-semibold">Payment History</h4>
                            <div class="space-y-3">
                                <div v-for="transaction in selectedOrder.transactions" :key="transaction.id" 
                                     class="flex items-center justify-between p-3 border rounded-lg">
                                    <div class="flex items-center gap-3">
                                        <Badge :variant="getStatusVariant(transaction.status)">
                                            {{ getStatusDisplay(transaction.status) }}
                                        </Badge>
                                        <div>
                                            <p class="font-medium text-sm">{{ transaction.gateway.charAt(0).toUpperCase() + transaction.gateway.slice(1) }} Payment</p>
                                            <p class="text-xs text-muted-foreground">{{ formatDate(transaction.created_at) }}</p>
                                            <p class="text-xs text-muted-foreground font-mono">{{ transaction.gateway_transaction_id }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold">{{ formatPrice(transaction.amount, transaction.currency) }}</p>
                                        <p class="text-xs text-muted-foreground">{{ transaction.type }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Close Button -->
                    <Separator />
                    <div class="flex justify-end">
                        <Button variant="outline" @click="closeOrderModal">
                            Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
/* Custom styles for pagination links */
</style>
