<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { type BreadcrumbItem } from '@/types'

// Props from backend
interface Props {
    subscriptions: {
        data: Subscription[]
        links: any[]
        meta: any
    }
}

interface Subscription {
    id: number
    title: string
    status: string
    gateway: string
    gateway_subscription_id: string
    gateway_status: string
    trial_ends_at?: string
    ends_at?: string
    next_billing_date?: string
    canceled_at?: string
    created_at: string
    updated_at: string
    product_price: {
        id: number
        title: string
        amount: string
        currency: string
        billing_period: string
        trial_days: number
        product: Product
    }
    order: {
        id: number
        status: string
        total: string
        currency: string
        created_at: string
    }
}

interface Product {
    id: number
    title: string
    type: string
    description: string
    meta: Record<string, any>
}

const props = defineProps<Props>()

// All subscriptions loaded successfully

// State for modal management
const selectedSubscription = ref<Subscription | null>(null)
const showSubscriptionModal = ref(false)
const showCancelDialog = ref(false)
const showResumeDialog = ref(false)
const showChangeDialog = ref(false)
const selectedNewPlan = ref('')
const processing = ref(false)
const error = ref('')
const availablePlans = ref<any[]>([])
const loadingPlans = ref(false)

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Subscriptions', href: '/payment/subscriptions' }
]

// Format price with billing period
const formatPrice = (amount: string, currency: string, billingPeriod: string) => {
    const formatted = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(parseFloat(amount))
    
    const periods: Record<string, string> = {
        monthly: '/month',
        yearly: '/year',
        weekly: '/week',
        daily: '/day'
    }
    
    return `${formatted}${periods[billingPeriod] || ''}`
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
        active: 'default',
        trialing: 'secondary',
        incomplete: 'outline',
        canceled: 'destructive',
        past_due: 'destructive',
        unpaid: 'destructive'
    }
    return variants[status] || 'outline'
}

// Get status display
const getStatusDisplay = (status: string) => {
    const displays: Record<string, string> = {
        active: 'Active',
        trialing: 'Trial',
        incomplete: 'Incomplete',
        canceled: 'Canceled',
        past_due: 'Past Due',
        unpaid: 'Unpaid'
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
    return icons[type] || '🔄'
}

// Check if subscription can be canceled
const canCancel = (subscription: Subscription) => {
    return ['active', 'trialing'].includes(subscription.status) && !subscription.canceled_at
}

// Check if subscription can be resumed
const canResume = (subscription: Subscription) => {
    return subscription.status === 'canceled' && subscription.ends_at && new Date(subscription.ends_at) > new Date()
}

// Get next billing info
const getNextBillingInfo = (subscription: Subscription) => {
    if (subscription.status === 'canceled') {
        if (subscription.ends_at) {
            return `Ends on ${formatDate(subscription.ends_at)}`
        }
        return 'Canceled'
    }
    
    if (subscription.trial_ends_at && new Date(subscription.trial_ends_at) > new Date()) {
        return `Trial ends ${formatDate(subscription.trial_ends_at)}`
    }
    
    if (subscription.next_billing_date) {
        return `Next billing ${formatDate(subscription.next_billing_date)}`
    }
    
    return 'Active'
}

// Get status description
const getStatusDescription = (subscription: Subscription) => {
    if (subscription.status === 'trialing') {
        return 'You are currently in your free trial period'
    }
    if (subscription.status === 'active') {
        return 'Your subscription is active and will renew automatically'
    }
    if (subscription.status === 'canceled') {
        if (subscription.ends_at && new Date(subscription.ends_at) > new Date()) {
            return 'Your subscription is canceled but you still have access until it expires'
        }
        return 'Your subscription has been canceled'
    }
    if (subscription.status === 'past_due') {
        return 'Your subscription payment is past due. Please update your payment method'
    }
    return ''
}

// Modal management functions
const openSubscriptionModal = (subscription: Subscription) => {
    selectedSubscription.value = subscription
    showSubscriptionModal.value = true
    error.value = ''
}

const closeModals = () => {
    showSubscriptionModal.value = false
    showCancelDialog.value = false
    showResumeDialog.value = false
    showChangeDialog.value = false
    selectedNewPlan.value = ''
    error.value = ''
    processing.value = false
    availablePlans.value = []
    loadingPlans.value = false
}

// Load available plans for subscription change
const loadAvailablePlans = async () => {
    if (!selectedSubscription.value) return
    
    loadingPlans.value = true
    
    try {
        // Get API token using demo credentials
        const demoCredentials = {
            email: 'test@example.com',
            password: 'password'
        }
        
        const tokenResponse = await fetch('/api/auth/token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(demoCredentials)
        })

        if (!tokenResponse.ok) {
            throw new Error('Failed to get API token')
        }

        const tokenData = await tokenResponse.json()
        const apiToken = tokenData.access_token

        // Fetch available plans from the dedicated endpoint
        const response = await fetch(`/api/payments/subscriptions/${selectedSubscription.value.id}/available-plans`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${apiToken}`,
                'X-Requested-With': 'XMLHttpRequest',
            }
        })

        const data = await response.json()

        if (response.ok && data.success) {
            availablePlans.value = data.available_plans
        } else {
            throw new Error(data.error || 'Failed to load available plans')
        }
    } catch (err) {
        console.error('Failed to load available plans:', err)
        error.value = err instanceof Error ? err.message : 'Failed to load available plans'
        availablePlans.value = []
    } finally {
        loadingPlans.value = false
    }
}

// Cancel subscription
const cancelSubscription = async () => {
    if (!selectedSubscription.value) return
    
    processing.value = true
    error.value = ''

    try {
        // Get API token using demo credentials (same as order creation)
        const demoCredentials = {
            email: 'test@example.com',
            password: 'password'
        }
        
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
                const returnUrl = encodeURIComponent(window.location.href)
                window.location.href = `/login?redirect=${returnUrl}`
                return
            }
            throw new Error('Failed to get API token')
        }

        const tokenData = await tokenResponse.json()
        const apiToken = tokenData.access_token

        // Cancel subscription using API token
        const response = await fetch(`/api/payments/subscriptions/${selectedSubscription.value.id}/cancel`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${apiToken}`,
                'X-Requested-With': 'XMLHttpRequest',
            }
        })

        const data = await response.json()

        if (!response.ok || !data.success) {
            throw new Error(data.error || data.message || 'Failed to cancel subscription')
        }

        closeModals()
        router.reload()
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'An error occurred while canceling your subscription'
        processing.value = false
    }
}

// Resume subscription
const resumeSubscription = async () => {
    if (!selectedSubscription.value) return
    
    processing.value = true
    error.value = ''

    try {
        // Get API token using demo credentials (same as order creation)
        const demoCredentials = {
            email: 'test@example.com',
            password: 'password'
        }
        
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
                const returnUrl = encodeURIComponent(window.location.href)
                window.location.href = `/login?redirect=${returnUrl}`
                return
            }
            throw new Error('Failed to get API token')
        }

        const tokenData = await tokenResponse.json()
        const apiToken = tokenData.access_token

        // Resume subscription using API token
        const response = await fetch(`/api/payments/subscriptions/${selectedSubscription.value.id}/resume`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${apiToken}`,
                'X-Requested-With': 'XMLHttpRequest',
            }
        })

        const data = await response.json()

        if (!response.ok || !data.success) {
            throw new Error(data.error || data.message || 'Failed to resume subscription')
        }

        closeModals()
        router.reload()
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'An error occurred while resuming your subscription'
        processing.value = false
    }
}

// Change subscription plan
const changeSubscriptionPlan = async () => {
    if (!selectedSubscription.value || !selectedNewPlan.value) return

    processing.value = true
    error.value = ''

    try {
        // Get API token using demo credentials (same as order creation)
        const demoCredentials = {
            email: 'test@example.com',
            password: 'password'
        }
        
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
                const returnUrl = encodeURIComponent(window.location.href)
                window.location.href = `/login?redirect=${returnUrl}`
                return
            }
            throw new Error('Failed to get API token')
        }

        const tokenData = await tokenResponse.json()
        const apiToken = tokenData.access_token

        // Change subscription plan using API token
        const response = await fetch(`/api/payments/subscriptions/${selectedSubscription.value.id}/change-plan`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${apiToken}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                new_product_price_id: selectedNewPlan.value
            })
        })

        const data = await response.json()

        if (!response.ok || !data.success) {
            throw new Error(data.error || data.message || 'Failed to change subscription plan')
        }

        closeModals()
        router.reload()
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'An error occurred while changing your plan'
        processing.value = false
    }
}

// Open customer portal for payment method management
const openCustomerPortal = async () => {
    if (!selectedSubscription.value) return

    processing.value = true
    error.value = ''

    try {
        // Get API token using demo credentials
        const demoCredentials = {
            email: 'test@example.com',
            password: 'password'
        }
        
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
                const returnUrl = encodeURIComponent(window.location.href)
                window.location.href = `/login?redirect=${returnUrl}`
                return
            }
            throw new Error('Failed to get API token')
        }

        const tokenData = await tokenResponse.json()
        const apiToken = tokenData.access_token

        // Create customer portal session
        const response = await fetch(`/api/payments/subscriptions/${selectedSubscription.value.id}/customer-portal`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${apiToken}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                return_url: window.location.href
            })
        })

        const data = await response.json()

        if (!response.ok || !data.success) {
            throw new Error(data.error || data.message || 'Failed to open customer portal')
        }

        // Open customer portal in new window/tab
        const portalWindow = window.open(data.url, '_blank', 'width=1024,height=768,scrollbars=yes,resizable=yes')
        
        if (!portalWindow) {
            // Fallback if popup is blocked - redirect in same window
            window.location.href = data.url
        } else {
            // Focus the new window
            portalWindow.focus()
        }
        
        processing.value = false
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'An error occurred while opening the payment portal'
        processing.value = false
    }
}
</script>

<template>
    <Head title="My Subscriptions" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-foreground">My Subscriptions</h1>
                    <p class="text-muted-foreground">Manage your recurring subscriptions</p>
                </div>
                <Link href="/products">
                    <Button>Browse Products</Button>
                </Link>
            </div>

            <!-- Subscriptions list -->
            <div class="space-y-4">
                <template v-if="subscriptions.data && subscriptions.data.length > 0">
                    <Card v-for="subscription in subscriptions.data" :key="subscription.id" class="hover:shadow-md transition-shadow">
                        <CardHeader>
                            <div class="flex items-start justify-between">
                                <div>
                                    <CardTitle class="flex items-center gap-2">
                                        <span class="text-xl">{{ getProductTypeIcon(subscription.product_price.product.type) }}</span>
                                        {{ subscription.product_price.product.title }}
                                    </CardTitle>
                                    <CardDescription>
                                        {{ subscription.product_price.title }} • {{ formatPrice(subscription.product_price.amount, subscription.product_price.currency, subscription.product_price.billing_period) }}
                                    </CardDescription>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="getStatusVariant(subscription.status)">
                                        {{ getStatusDisplay(subscription.status) }}
                                    </Badge>
                                </div>
                            </div>
                        </CardHeader>
                        
                        <CardContent class="space-y-4">
                            <!-- Subscription details -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <p class="font-medium text-foreground">Started</p>
                                    <p class="text-muted-foreground">{{ formatDate(subscription.created_at) }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-foreground">Gateway</p>
                                    <p class="text-muted-foreground">{{ subscription.gateway.charAt(0).toUpperCase() + subscription.gateway.slice(1) }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-foreground">Next Billing</p>
                                    <p class="text-muted-foreground">{{ getNextBillingInfo(subscription) }}</p>
                                </div>
                            </div>

                            <!-- Status description -->
                            <template v-if="getStatusDescription(subscription)">
                                <Separator />
                                <div class="p-3 bg-accent/50 rounded-lg">
                                    <p class="text-sm text-muted-foreground">
                                        {{ getStatusDescription(subscription) }}
                                    </p>
                                </div>
                            </template>

                            <!-- Actions -->
                            <div class="flex justify-between items-center">
                                <div class="flex gap-2">
                                    <template v-if="canCancel(subscription)">
                                        <Button variant="outline" size="sm" class="text-destructive hover:text-destructive" @click="openSubscriptionModal(subscription)">
                                            Cancel
                                        </Button>
                                    </template>
                                    <template v-if="canResume(subscription)">
                                        <Button variant="outline" size="sm" class="text-green-600 hover:text-green-600" @click="openSubscriptionModal(subscription)">
                                            Resume
                                        </Button>
                                    </template>
                                </div>
                                <Button variant="outline" size="sm" @click="openSubscriptionModal(subscription)">Manage</Button>
                            </div>
                        </CardContent>
                    </Card>
                </template>
                
                <!-- Empty state -->
                <div v-else class="text-center py-12">
                    <div class="text-6xl mb-4">🔄</div>
                    <h3 class="text-xl font-semibold text-foreground mb-2">No Subscriptions Yet</h3>
                    <p class="text-muted-foreground mb-6">You don't have any active subscriptions.</p>
                    <Link href="/products">
                        <Button>Browse Subscriptions</Button>
                    </Link>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="subscriptions.links && subscriptions.links.length > 3" class="flex items-center justify-center gap-2">
                <template v-for="link in subscriptions.links" :key="link.label">
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

        <!-- Subscription Management Modal -->
        <Dialog v-model:open="showSubscriptionModal" v-if="selectedSubscription">
            <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <span class="text-xl">{{ getProductTypeIcon(selectedSubscription.product_price.product.type) }}</span>
                        {{ selectedSubscription.product_price.product.title }}
                    </DialogTitle>
                    <DialogDescription>
                        Manage your subscription details and settings
                    </DialogDescription>
                </DialogHeader>

                <!-- Error Alert -->
                <Alert v-if="error" variant="destructive" class="mb-4">
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>

                <div class="space-y-6">
                    <!-- Subscription Status -->
                    <div class="flex items-center justify-between p-4 bg-accent/20 rounded-lg">
                        <div>
                            <h3 class="font-semibold text-lg">{{ selectedSubscription.product_price.title }}</h3>
                            <p class="text-muted-foreground">{{ formatPrice(selectedSubscription.product_price.amount, selectedSubscription.product_price.currency, selectedSubscription.product_price.billing_period) }}</p>
                        </div>
                        <Badge :variant="getStatusVariant(selectedSubscription.status)" class="text-base px-3 py-1">
                            {{ getStatusDisplay(selectedSubscription.status) }}
                        </Badge>
                    </div>

                    <!-- Detailed Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h4 class="font-semibold">Subscription Details</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Started:</span>
                                    <span>{{ formatDate(selectedSubscription.created_at) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Gateway:</span>
                                    <span>{{ selectedSubscription.gateway.charAt(0).toUpperCase() + selectedSubscription.gateway.slice(1) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Gateway ID:</span>
                                    <span class="text-sm font-mono bg-accent/50 px-2 py-1 rounded">{{ selectedSubscription.gateway_subscription_id }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Next Billing:</span>
                                    <span>{{ getNextBillingInfo(selectedSubscription) }}</span>
                                </div>
                                <div v-if="selectedSubscription.trial_ends_at" class="flex justify-between">
                                    <span class="text-muted-foreground">Trial Ends:</span>
                                    <span>{{ formatDate(selectedSubscription.trial_ends_at) }}</span>
                                </div>
                                <div v-if="selectedSubscription.canceled_at" class="flex justify-between">
                                    <span class="text-muted-foreground">Canceled:</span>
                                    <span>{{ formatDate(selectedSubscription.canceled_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="font-semibold">Product Information</h4>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-sm text-muted-foreground mb-2">Description:</p>
                                    <p class="text-sm">{{ selectedSubscription.product_price.product.description }}</p>
                                </div>
                                <div v-if="selectedSubscription.product_price.product.meta?.features">
                                    <p class="text-sm text-muted-foreground mb-2">Features:</p>
                                    <ul class="text-sm space-y-1">
                                        <li v-for="feature in selectedSubscription.product_price.product.meta.features" :key="feature" class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 bg-primary rounded-full"></span>
                                            {{ feature }}
                                        </li>
                                    </ul>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground mb-1">Product Type:</p>
                                    <Badge variant="outline">{{ selectedSubscription.product_price.product.type }}</Badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Description -->
                    <div v-if="getStatusDescription(selectedSubscription)" class="p-4 bg-accent/50 rounded-lg">
                        <p class="text-sm">{{ getStatusDescription(selectedSubscription) }}</p>
                    </div>

                    <!-- Action Buttons -->
                    <Separator />
                    <div class="flex flex-wrap gap-3">
                        <!-- Cancel Subscription -->
                        <Dialog v-if="canCancel(selectedSubscription)" v-model:open="showCancelDialog">
                            <DialogTrigger asChild>
                                <Button variant="outline" class="text-destructive hover:text-destructive">
                                    Cancel Subscription
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Cancel Subscription</DialogTitle>
                                    <DialogDescription>
                                        Are you sure you want to cancel your subscription? You'll continue to have access until your current billing period ends.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button variant="outline" @click="showCancelDialog = false" :disabled="processing">
                                        Keep Subscription
                                    </Button>
                                    <Button variant="destructive" @click="cancelSubscription" :disabled="processing">
                                        {{ processing ? 'Canceling...' : 'Cancel Subscription' }}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

                        <!-- Resume Subscription -->
                        <Dialog v-if="canResume(selectedSubscription)" v-model:open="showResumeDialog">
                            <DialogTrigger asChild>
                                <Button variant="outline" class="text-green-600 hover:text-green-600">
                                    Resume Subscription
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Resume Subscription</DialogTitle>
                                    <DialogDescription>
                                        Resume your subscription and continue enjoying all benefits. Your next billing date will be updated accordingly.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button variant="outline" @click="showResumeDialog = false" :disabled="processing">
                                        Cancel
                                    </Button>
                                    <Button @click="resumeSubscription" :disabled="processing">
                                        {{ processing ? 'Resuming...' : 'Resume Subscription' }}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

                        <!-- Customer Portal for Payment Methods -->
                        <Button 
                            v-if="selectedSubscription.gateway === 'stripe'" 
                            variant="outline" 
                            @click="openCustomerPortal"
                            :disabled="processing"
                        >
                            {{ processing ? 'Opening...' : 'Manage Payment Methods' }}
                        </Button>

                        <!-- For PayPal, redirect to their account management -->
                        <Button 
                            v-if="selectedSubscription.gateway === 'paypal'" 
                            variant="outline" 
                            @click="openCustomerPortal"
                            :disabled="processing"
                            title="Opens PayPal account management to update payment methods"
                        >
                            {{ processing ? 'Opening...' : 'Manage via PayPal' }}
                        </Button>

                        <!-- Change Plan -->
                        <Dialog v-if="selectedSubscription.status === 'active'" v-model:open="showChangeDialog" @update:open="(open) => { if (open) loadAvailablePlans() }">
                            <DialogTrigger asChild>
                                <Button variant="outline">Change Plan</Button>
                            </DialogTrigger>
                            <DialogContent class="max-w-md">
                                <DialogHeader>
                                    <DialogTitle>Change Plan</DialogTitle>
                                    <DialogDescription>
                                        Select a new plan for your subscription. Changes will be applied immediately.
                                    </DialogDescription>
                                </DialogHeader>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="text-sm font-medium">Select New Plan</label>
                                        <Select v-model="selectedNewPlan">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Choose a plan..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <template v-if="loadingPlans">
                                                    <SelectItem value="" disabled>
                                                        Loading available plans...
                                                    </SelectItem>
                                                </template>
                                                <template v-else-if="availablePlans.length > 0">
                                                    <SelectItem 
                                                        v-for="plan in availablePlans" 
                                                        :key="plan.id" 
                                                        :value="plan.id.toString()"
                                                    >
                                                        {{ plan.product.title }} - {{ plan.title }} 
                                                        ({{ formatPrice(plan.amount.toString(), plan.currency, plan.billing_period) }})
                                                    </SelectItem>
                                                </template>
                                                <template v-else>
                                                    <SelectItem value="" disabled>
                                                        No other plans available
                                                    </SelectItem>
                                                </template>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button variant="outline" @click="showChangeDialog = false; selectedNewPlan = ''" :disabled="processing">
                                        Cancel
                                    </Button>
                                    <Button @click="changeSubscriptionPlan" :disabled="processing || !selectedNewPlan || loadingPlans">
                                        {{ processing ? 'Changing...' : 'Change Plan' }}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

                        <!-- Close Modal -->
                        <Button variant="outline" @click="closeModals" class="ml-auto">
                            Close
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
/* Custom styles for subscription management */
</style>