<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea/index'

interface Product {
    id: number
    title: string
    slug: string
    description: string
    type: string
    meta: Record<string, any>
}

interface ProductPrice {
    id: number
    title: string
    slug: string
    amount: string
    currency: string
    billing_period: string
    trial_days: number
}

interface Props {
    show: boolean
    product: Product | null
    price: ProductPrice | null
}

interface Emits {
    (e: 'close'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Form state
const gateway = ref<'stripe' | 'paypal'>('stripe')
const quantity = ref(1)
const customerNote = ref('')
const processing = ref(false)
const error = ref('')

// Computed properties
const totalAmount = computed(() => {
    if (!props.price) return 0
    return parseFloat(props.price.amount) * quantity.value
})

const formattedTotal = computed(() => {
    if (!props.price) return ''
    const amount = totalAmount.value
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: props.price.currency
    }).format(amount)
})

const isSubscription = computed(() => {
    return props.price?.billing_period !== 'once'
})

// Watch for prop changes to reset form
watch(() => props.show, (show) => {
    if (show) {
        gateway.value = 'stripe'
        quantity.value = 1
        customerNote.value = ''
        processing.value = false
        error.value = ''
    }
})

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

// Handle payment submission using API
const handlePayment = async () => {
    if (!props.price) return
    
    processing.value = true
    error.value = ''
    
    try {
        // Demo credentials - in production, use proper authentication
        const demoCredentials = {
            email: 'test@example.com',
            password: 'password'
        }
        
        // Get API token using demo credentials
        const tokenResponse = await fetch('/api/auth/token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(demoCredentials)
        })

        if (!tokenResponse.ok) {
            if (tokenResponse.status === 401) {
                // Redirect to login if unauthorized
                const returnUrl = encodeURIComponent(window.location.href)
                window.location.href = `/login?redirect=${returnUrl}`
                return
            }
            throw new Error('Failed to get API token')
        }

        const tokenData = await tokenResponse.json()
        const apiToken = tokenData.access_token

        // Make payment API request with Bearer token
        const response = await fetch('/api/payments/orders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${apiToken}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                product_price_id: props.price.id,
                gateway: gateway.value,
                quantity: isSubscription.value ? 1 : quantity.value,
                success_url: window.location.origin + '/products?success=1',
                cancel_url: window.location.origin + '/products?cancelled=1',
                customer_note: customerNote.value.trim() || undefined,
            }),
        })

        if (!response.ok) {
            const errorData = await response.json()
            if (response.status === 401) {
                // Redirect to login if unauthorized
                const returnUrl = encodeURIComponent(window.location.href)
                window.location.href = `/login?redirect=${returnUrl}`
                return
            }
            throw new Error(errorData.message || 'Payment processing failed')
        }

        const data = await response.json()
        
        if (data.success && data.url) {
            // Redirect to payment gateway
            window.location.href = data.url
        } else {
            throw new Error(data.message || 'Failed to create order')
        }
        
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'An error occurred while processing your payment'
        processing.value = false
    }
}

// Get product features
const getProductFeatures = (product: Product | null) => {
    if (!product) return []
    return product.meta?.features || []
}

// Close modal
const closeModal = () => {
    if (!processing.value) {
        emit('close')
    }
}
</script>

<template>
    <Dialog :open="show" @update:open="closeModal" data-testid="payment-modal">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ isSubscription ? 'Subscribe to' : 'Purchase' }} {{ product?.title }}
                </DialogTitle>
                <DialogDescription>
                    Review your order and choose your payment method
                </DialogDescription>
            </DialogHeader>
            
            <div v-if="product && price" class="space-y-6">
                <!-- Error message -->
                <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg" data-testid="error-message">
                    <p class="text-sm text-destructive">{{ error }}</p>
                </div>

                <!-- Product Summary -->
                <Card>
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div>
                                <CardTitle class="text-lg">{{ product.title }}</CardTitle>
                                <CardDescription>{{ price.title }}</CardDescription>
                            </div>
                            <Badge variant="outline">{{ product.type }}</Badge>
                        </div>
                    </CardHeader>
                    
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">{{ product.description }}</p>
                        
                        <!-- Features -->
                        <div v-if="getProductFeatures(product).length > 0">
                            <h4 class="font-medium mb-2">Included features:</h4>
                            <ul class="text-sm text-muted-foreground space-y-1">
                                <li v-for="feature in getProductFeatures(product)" :key="feature" class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 bg-primary rounded-full"></span>
                                    {{ feature }}
                                </li>
                            </ul>
                        </div>

                        <!-- Trial info -->
                        <div v-if="price.trial_days > 0" class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <p class="text-sm text-green-700 dark:text-green-300">
                                🎉 This subscription includes a {{ price.trial_days }}-day free trial
                            </p>
                        </div>

                        <!-- Subscription info -->
                        <div v-if="isSubscription" class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                🔄 This is a recurring subscription. You can cancel anytime from your account dashboard.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Order Details -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Order Details</CardTitle>
                    </CardHeader>
                    
                    <CardContent class="space-y-4">
                        <!-- Quantity (only for one-time purchases) -->
                        <div v-if="!isSubscription" class="space-y-2">
                            <Label for="quantity">Quantity</Label>
                            <Input
                                id="quantity"
                                v-model.number="quantity"
                                type="number"
                                min="1"
                                max="10"
                                class="w-24"
                                data-testid="quantity-input"
                            />
                        </div>

                        <!-- Customer Note -->
                        <div class="space-y-2">
                            <Label for="customer-note">Special Instructions (Optional)</Label>
                            <Textarea
                                id="customer-note"
                                v-model="customerNote"
                                placeholder="Any special requests or instructions for your order..."
                                class="min-h-[80px] resize-none"
                                maxlength="1000"
                                data-testid="customer-note"
                            />
                            <p class="text-xs text-muted-foreground" data-testid="character-counter">{{ customerNote.length }}/1000 characters</p>
                        </div>

                        <Separator />

                        <!-- Pricing breakdown -->
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>{{ price.title }}</span>
                                <span>{{ formatPrice(price) }}</span>
                            </div>
                            <div v-if="!isSubscription && quantity > 1" class="flex justify-between text-sm">
                                <span>Quantity</span>
                                <span>×{{ quantity }}</span>
                            </div>
                            <Separator />
                            <div class="flex justify-between font-semibold">
                                <span>Total</span>
                                <span data-testid="total-amount">{{ formattedTotal }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payment Method -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Payment Method</CardTitle>
                    </CardHeader>
                    
                    <CardContent class="space-y-4">
                        <!-- Gateway selection -->
                        <div class="grid grid-cols-2 gap-4">
                            <button
                                @click="gateway = 'stripe'"
                                :class="[
                                    'p-4 border-2 rounded-lg transition-all',
                                    gateway === 'stripe' 
                                        ? 'border-primary bg-primary/5' 
                                        : 'border-border hover:border-primary/50'
                                ]"
                                data-testid="gateway-stripe"
                            >
                                <div class="space-y-2">
                                    <div class="text-lg font-semibold text-blue-600">Stripe</div>
                                    <div class="text-xs text-muted-foreground">Credit/Debit Cards</div>
                                </div>
                            </button>
                            
                            <button
                                @click="gateway = 'paypal'"
                                :class="[
                                    'p-4 border-2 rounded-lg transition-all',
                                    gateway === 'paypal' 
                                        ? 'border-primary bg-primary/5' 
                                        : 'border-border hover:border-primary/50'
                                ]"
                                data-testid="gateway-paypal"
                            >
                                <div class="space-y-2">
                                    <div class="text-lg font-semibold text-blue-800">PayPal</div>
                                    <div class="text-xs text-muted-foreground">PayPal Account</div>
                                </div>
                            </button>
                        </div>

                        <!-- Security note -->
                        <div class="p-3 bg-muted rounded-lg">
                            <p class="text-xs text-muted-foreground">
                                🔒 Your payment information is secure and encrypted. We do not store your payment details.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex gap-3 justify-end">
                    <Button variant="outline" @click="closeModal" :disabled="processing">
                        Cancel
                    </Button>
                    <Button @click="handlePayment" :disabled="processing" data-testid="pay-button">
                        <span v-if="processing">Processing...</span>
                        <span v-else>
                            {{ isSubscription ? `Subscribe ${formattedTotal}` : `Pay ${formattedTotal}` }}
                        </span>
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>