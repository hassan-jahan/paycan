<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { type BreadcrumbItem } from '@/types'

// Props from backend
interface Props {
    order: Order
}

interface Order {
    id: number
    status: string
    total: string
    currency: string
    billing_name: string
    billing_email: string
    billing_address?: string
    billing_city?: string
    billing_state?: string
    billing_zipcode?: string
    billing_country?: string
    customer_note?: string
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Orders', href: '/payment/orders' },
    { title: `Order #${props.order.id}`, href: `/payment/orders/${props.order.id}` }
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
        month: 'long',
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

// Has billing address
const hasBillingAddress = computed(() => {
    return props.order.billing_address || props.order.billing_city || props.order.billing_state
})

// Format billing address
const formattedBillingAddress = computed(() => {
    const parts = [
        props.order.billing_address,
        [props.order.billing_city, props.order.billing_state].filter(Boolean).join(', '),
        props.order.billing_zipcode,
        props.order.billing_country
    ].filter(Boolean)
    
    return parts.join('\n')
})
</script>

<template>
    <Head :title="`Order #${order.id}`" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-foreground flex items-center gap-3">
                        <span class="text-2xl">{{ getProductTypeIcon(order.product_price.product.type) }}</span>
                        Order #{{ order.id }}
                    </h1>
                    <p class="text-muted-foreground">{{ order.product_price.product.title }}</p>
                </div>
                <Badge :variant="getStatusVariant(order.status)" class="text-sm">
                    {{ getStatusDisplay(order.status) }}
                </Badge>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Product details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Product Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-lg">{{ order.product_price.product.title }}</h3>
                                <p class="text-muted-foreground">{{ order.product_price.title }}</p>
                            </div>
                            
                            <p class="text-sm text-muted-foreground">
                                {{ order.product_price.product.description }}
                            </p>

                            <!-- Product features -->
                            <div v-if="order.product_price.product.meta?.features" class="space-y-2">
                                <p class="font-medium">Features:</p>
                                <ul class="text-sm text-muted-foreground space-y-1">
                                    <li v-for="feature in order.product_price.product.meta.features" :key="feature" 
                                        class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 bg-primary rounded-full"></span>
                                        {{ feature }}
                                    </li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Fulfillment details -->
                    <Card v-if="order.fulfillments && order.fulfillments.length > 0">
                        <CardHeader>
                            <CardTitle>Fulfillment Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div v-for="fulfillment in order.fulfillments" :key="fulfillment.id" 
                                     class="p-4 border rounded-lg">
                                    <div class="flex items-center justify-between mb-3">
                                        <Badge :variant="getStatusVariant(fulfillment.status)">
                                            {{ getStatusDisplay(fulfillment.status) }}
                                        </Badge>
                                        <span class="text-sm text-muted-foreground">
                                            {{ fulfillment.type.charAt(0).toUpperCase() + fulfillment.type.slice(1) }}
                                        </span>
                                    </div>

                                    <!-- Digital fulfillment -->
                                    <div v-if="fulfillment.type === 'digital' && fulfillment.meta" class="space-y-2">
                                        <div v-if="fulfillment.meta.license_key" class="space-y-1">
                                            <p class="text-sm font-medium">License Key:</p>
                                            <code class="text-sm bg-muted px-2 py-1 rounded">{{ fulfillment.meta.license_key }}</code>
                                        </div>
                                        <div v-if="fulfillment.meta.download_link" class="space-y-1">
                                            <p class="text-sm font-medium">Download:</p>
                                            <Button size="sm" asChild>
                                                <a :href="fulfillment.meta.download_link" target="_blank">
                                                    Download Now
                                                </a>
                                            </Button>
                                        </div>
                                        <div v-if="fulfillment.meta.expires_at" class="space-y-1">
                                            <p class="text-sm font-medium">Expires:</p>
                                            <p class="text-sm text-muted-foreground">{{ formatDate(fulfillment.meta.expires_at) }}</p>
                                        </div>
                                    </div>

                                    <!-- Physical fulfillment -->
                                    <div v-if="fulfillment.type === 'physical'" class="space-y-2">
                                        <div v-if="fulfillment.tracking_number" class="space-y-1">
                                            <p class="text-sm font-medium">Tracking Number:</p>
                                            <code class="text-sm bg-muted px-2 py-1 rounded">{{ fulfillment.tracking_number }}</code>
                                        </div>
                                        <div v-if="fulfillment.carrier" class="space-y-1">
                                            <p class="text-sm font-medium">Carrier:</p>
                                            <p class="text-sm text-muted-foreground">{{ fulfillment.carrier }}</p>
                                        </div>
                                        <div v-if="fulfillment.meta?.estimated_delivery" class="space-y-1">
                                            <p class="text-sm font-medium">Estimated Delivery:</p>
                                            <p class="text-sm text-muted-foreground">{{ formatDate(fulfillment.meta.estimated_delivery) }}</p>
                                        </div>
                                    </div>

                                    <!-- Service fulfillment -->
                                    <div v-if="fulfillment.type === 'service' && fulfillment.meta" class="space-y-2">
                                        <div v-if="fulfillment.meta.service_code" class="space-y-1">
                                            <p class="text-sm font-medium">Service Code:</p>
                                            <code class="text-sm bg-muted px-2 py-1 rounded">{{ fulfillment.meta.service_code }}</code>
                                        </div>
                                        <div v-if="fulfillment.meta.instructions" class="space-y-1">
                                            <p class="text-sm font-medium">Instructions:</p>
                                            <p class="text-sm text-muted-foreground">{{ fulfillment.meta.instructions }}</p>
                                        </div>
                                        <div v-if="fulfillment.meta.valid_until" class="space-y-1">
                                            <p class="text-sm font-medium">Valid Until:</p>
                                            <p class="text-sm text-muted-foreground">{{ formatDate(fulfillment.meta.valid_until) }}</p>
                                        </div>
                                    </div>

                                    <div v-if="fulfillment.fulfilled_at" class="text-xs text-muted-foreground mt-2">
                                        Fulfilled on {{ formatDate(fulfillment.fulfilled_at) }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Payment history -->
                    <Card v-if="order.transactions && order.transactions.length > 0">
                        <CardHeader>
                            <CardTitle>Payment History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div v-for="transaction in order.transactions" :key="transaction.id" 
                                     class="flex items-center justify-between p-4 border rounded-lg">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <Badge :variant="getStatusVariant(transaction.status)">
                                                {{ getStatusDisplay(transaction.status) }}
                                            </Badge>
                                            <span class="text-sm font-medium">
                                                {{ transaction.type.charAt(0).toUpperCase() + transaction.type.slice(1) }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-muted-foreground">
                                            {{ transaction.gateway.charAt(0).toUpperCase() + transaction.gateway.slice(1) }}
                                            • {{ formatDate(transaction.created_at) }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            ID: {{ transaction.gateway_transaction_id }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold">{{ formatPrice(transaction.amount, transaction.currency) }}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Order summary -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Order Summary</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span>Order Date:</span>
                                    <span>{{ formatDate(order.created_at) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Order ID:</span>
                                    <span>#{{ order.id }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Payment Status:</span>
                                    <Badge :variant="getStatusVariant(order.status)" size="sm">
                                        {{ getStatusDisplay(order.status) }}
                                    </Badge>
                                </div>
                                <Separator />
                                <div class="flex justify-between font-semibold">
                                    <span>Total:</span>
                                    <span>{{ formatPrice(order.total, order.currency) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Billing information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Billing Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2 text-sm">
                                <div>
                                    <p class="font-medium">{{ order.billing_name }}</p>
                                    <p class="text-muted-foreground">{{ order.billing_email }}</p>
                                </div>
                                
                                <div v-if="hasBillingAddress">
                                    <Separator class="my-2" />
                                    <p class="font-medium">Address:</p>
                                    <pre class="text-muted-foreground whitespace-pre-wrap">{{ formattedBillingAddress }}</pre>
                                </div>

                                <div v-if="order.customer_note">
                                    <Separator class="my-2" />
                                    <p class="font-medium">Customer Note:</p>
                                    <p class="text-muted-foreground">{{ order.customer_note }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <Link href="/payment/orders" class="block">
                                <Button variant="outline" class="w-full">Back to Orders</Button>
                            </Link>
                            <Button variant="outline" class="w-full" @click="window.print()">
                                Print Order
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>