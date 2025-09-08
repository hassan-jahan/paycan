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
import { type BreadcrumbItem } from '@/types'

// Props from backend
interface Props {
    subscription: Subscription
    availablePlans?: ProductPrice[]
}

interface Subscription {
    id: number
    title: string
    status: string
    gateway: string
    gateway_subscription_id: string
    gateway_status: string
    gateway_data: Record<string, any>
    trial_ends_at?: string
    ends_at?: string
    next_billing_date?: string
    canceled_at?: string
    created_at: string
    updated_at: string
    product_price: ProductPrice
    order: {
        id: number
        order_number: string
        status: string
        total: string
        currency: string
        created_at: string
    }
    transactions?: Transaction[]
}

interface ProductPrice {
    id: number
    title: string
    amount: string
    currency: string
    billing_period: string
    trial_days: number
    product: Product
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

const props = defineProps<Props>()

// State
const showCancelDialog = ref(false)
const showResumeDialog = ref(false)
const showPlanChangeDialog = ref(false)
const selectedPlan = ref<string>('')
const processing = ref(false)
const error = ref('')

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Subscriptions', href: '/payment/subscriptions' },
    { title: props.subscription.product_price.product.title, href: `/payment/subscriptions/${props.subscription.id}` }
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

// Check if subscription can be canceled
const canCancel = computed(() => {
    return ['active', 'trialing'].includes(props.subscription.status) && !props.subscription.canceled_at
})

// Check if subscription can be resumed
const canResume = computed(() => {
    return props.subscription.status === 'canceled' && 
           props.subscription.ends_at && 
           new Date(props.subscription.ends_at) > new Date()
})

// Get next billing info
const getNextBillingInfo = () => {
    const subscription = props.subscription
    
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

// Cancel subscription
const cancelSubscription = async () => {
    processing.value = true
    error.value = ''

    try {
        router.post(`/payment/subscriptions/${props.subscription.id}/cancel`, {}, {
            onSuccess: () => {
                showCancelDialog.value = false
                processing.value = false
            },
            onError: (errors) => {
                error.value = errors.message || 'Failed to cancel subscription'
                processing.value = false
            }
        })
    } catch (err) {
        error.value = 'An error occurred while canceling your subscription'
        processing.value = false
    }
}

// Resume subscription
const resumeSubscription = async () => {
    processing.value = true
    error.value = ''

    try {
        router.post(`/payment/subscriptions/${props.subscription.id}/resume`, {}, {
            onSuccess: () => {
                showResumeDialog.value = false
                processing.value = false
            },
            onError: (errors) => {
                error.value = errors.message || 'Failed to resume subscription'
                processing.value = false
            }
        })
    } catch (err) {
        error.value = 'An error occurred while resuming your subscription'
        processing.value = false
    }
}

// Change plan
const changePlan = async () => {
    if (!selectedPlan.value) return

    processing.value = true
    error.value = ''

    try {
        router.put(`/payment/subscriptions/${props.subscription.id}/change-plan`, {
            new_product_price_id: selectedPlan.value
        }, {
            onSuccess: () => {
                showPlanChangeDialog.value = false
                processing.value = false
                selectedPlan.value = ''
            },
            onError: (errors) => {
                error.value = errors.message || 'Failed to change subscription plan'
                processing.value = false
            }
        })
    } catch (err) {
        error.value = 'An error occurred while changing your plan'
        processing.value = false
    }
}

// Get plan comparison info
const getSelectedPlanInfo = computed(() => {
    if (!selectedPlan.value || !props.availablePlans) return null
    
    const plan = props.availablePlans.find(p => p.id.toString() === selectedPlan.value)
    if (!plan) return null
    
    const currentAmount = parseFloat(props.subscription.product_price.amount)
    const newAmount = parseFloat(plan.amount)
    const difference = newAmount - currentAmount
    
    return {
        plan,
        isUpgrade: difference > 0,
        isDowngrade: difference < 0,
        difference: Math.abs(difference),
        formattedDifference: new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: plan.currency
        }).format(Math.abs(difference))
    }
})
</script>

<template>
    <Head :title="`${subscription.product_price.product.title} Subscription`" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-foreground">{{ subscription.product_price.product.title }}</h1>
                    <p class="text-muted-foreground">{{ subscription.product_price.title }}</p>
                </div>
                <Badge :variant="getStatusVariant(subscription.status)" class="text-base px-3 py-1">
                    {{ getStatusDisplay(subscription.status) }}
                </Badge>
            </div>

            <!-- Error Alert -->
            <Alert v-if="error" variant="destructive">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <!-- Main subscription details -->
            <Card>
                <CardHeader>
                    <CardTitle>Subscription Details</CardTitle>
                    <CardDescription>Manage your subscription settings and billing</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Basic details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <p class="font-medium text-foreground">Price</p>
                            <p class="text-lg font-semibold text-primary">
                                {{ formatPrice(subscription.product_price.amount, subscription.product_price.currency, subscription.product_price.billing_period) }}
                            </p>
                        </div>
                        <div>
                            <p class="font-medium text-foreground">Status</p>
                            <Badge :variant="getStatusVariant(subscription.status)" class="mt-1">
                                {{ getStatusDisplay(subscription.status) }}
                            </Badge>
                        </div>
                        <div>
                            <p class="font-medium text-foreground">Started</p>
                            <p class="text-muted-foreground">{{ formatDate(subscription.created_at) }}</p>
                        </div>
                        <div>
                            <p class="font-medium text-foreground">Next Billing</p>
                            <p class="text-muted-foreground">{{ getNextBillingInfo() }}</p>
                        </div>
                    </div>

                    <Separator />

                    <!-- Product description -->
                    <div>
                        <h4 class="font-medium text-foreground mb-2">What's Included</h4>
                        <p class="text-muted-foreground mb-4">{{ subscription.product_price.product.description }}</p>
                        
                        <!-- Features -->
                        <div v-if="subscription.product_price.product.meta?.features?.length">
                            <ul class="text-sm text-muted-foreground space-y-1">
                                <li v-for="feature in subscription.product_price.product.meta.features" :key="feature" class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 bg-primary rounded-full"></span>
                                    {{ feature }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <Separator />

                    <!-- Action buttons -->
                    <div class="flex flex-wrap gap-3">
                        <!-- Cancel subscription -->
                        <Dialog v-if="canCancel" v-model:open="showCancelDialog">
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

                        <!-- Resume subscription -->
                        <Dialog v-if="canResume" v-model:open="showResumeDialog">
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

                        <!-- Change plan -->
                        <Dialog v-if="availablePlans && availablePlans.length > 1 && subscription.status === 'active'" v-model:open="showPlanChangeDialog">
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
                                        <Select v-model="selectedPlan">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Choose a plan..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem 
                                                    v-for="plan in availablePlans" 
                                                    :key="plan.id" 
                                                    :value="plan.id.toString()"
                                                    :disabled="plan.id === subscription.product_price.id"
                                                >
                                                    <div class="flex justify-between items-center w-full">
                                                        <span>{{ plan.title }}</span>
                                                        <span class="font-semibold">
                                                            {{ formatPrice(plan.amount, plan.currency, plan.billing_period) }}
                                                        </span>
                                                    </div>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    
                                    <!-- Plan comparison -->
                                    <div v-if="getSelectedPlanInfo" class="p-3 bg-accent/50 rounded-lg text-sm">
                                        <div class="flex justify-between items-center">
                                            <span>{{ getSelectedPlanInfo.isUpgrade ? 'Upgrade to:' : 'Downgrade to:' }}</span>
                                            <span class="font-semibold">{{ getSelectedPlanInfo.plan.title }}</span>
                                        </div>
                                        <div class="flex justify-between items-center mt-1">
                                            <span>Price difference:</span>
                                            <span :class="[
                                                'font-semibold',
                                                getSelectedPlanInfo.isUpgrade ? 'text-red-600' : 'text-green-600'
                                            ]">
                                                {{ getSelectedPlanInfo.isUpgrade ? '+' : '-' }}{{ getSelectedPlanInfo.formattedDifference }}{{ subscription.product_price.billing_period === 'monthly' ? '/month' : '/year' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button variant="outline" @click="showPlanChangeDialog = false; selectedPlan = ''" :disabled="processing">
                                        Cancel
                                    </Button>
                                    <Button @click="changePlan" :disabled="processing || !selectedPlan">
                                        {{ processing ? 'Changing...' : 'Change Plan' }}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardContent>
            </Card>

            <!-- Billing history -->
            <Card v-if="subscription.transactions && subscription.transactions.length > 0">
                <CardHeader>
                    <CardTitle>Billing History</CardTitle>
                    <CardDescription>Your recent subscription payments</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="transaction in subscription.transactions" :key="transaction.id" 
                             class="flex items-center justify-between p-3 border rounded-lg">
                            <div class="flex items-center gap-3">
                                <Badge :variant="getStatusVariant(transaction.status)" size="sm">
                                    {{ getStatusDisplay(transaction.status) }}
                                </Badge>
                                <div>
                                    <p class="font-medium text-sm">{{ transaction.gateway.charAt(0).toUpperCase() + transaction.gateway.slice(1) }} Payment</p>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(transaction.created_at) }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">{{ formatPrice(transaction.amount, transaction.currency, 'once') }}</p>
                                <p class="text-xs text-muted-foreground">{{ transaction.gateway_transaction_id }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Back button -->
            <div class="flex justify-start">
                <Link href="/payment/subscriptions">
                    <Button variant="outline">← Back to Subscriptions</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Custom styles for subscription detail */
</style>