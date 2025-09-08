<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Subscription;
use App\Notifications\OrderFulfilledNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class FulfillmentService
{
    /**
     * Process fulfillment for a completed purchase
     */
    public function processPurchaseFulfillment(Order $order)
    {
        try {
            // Get product type to determine fulfillment method
            $productType = $order->productPrice->product->type;
            
            // Create fulfillment record
            $fulfillment = Fulfillment::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'type' => $productType,
            ]);
            
            // Process different types of products
            $result = match ($productType) {
                'digital' => $this->fulfillDigitalProduct($order, $fulfillment),
                'physical' => $this->fulfillPhysicalProduct($order, $fulfillment),
                'service' => $this->fulfillServiceProduct($order, $fulfillment),
                'subscription' => $this->fulfillSubscriptionProduct($order, $fulfillment),
                default => false
            };
            
            if ($result) {
                // Mark fulfillment as completed
                $fulfillment->update([
                    'status' => 'completed',
                    'fulfilled_at' => now(),
                ]);
                
                // Notify customer
                $order->user->notify(new OrderFulfilledNotification($order, $fulfillment));
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Error processing fulfillment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process fulfillment for subscription renewal
     */
    public function processSubscriptionRenewalFulfillment(Subscription $subscription)
    {
        try {
            // For subscription renewals, we typically just extend access
            // Create a record in fulfillments table for tracking
            $fulfillment = Fulfillment::create([
                'order_id' => $subscription->order_id, // Use the original order
                'status' => 'completed',
                'type' => 'subscription_access',
                'meta' => [
                    'subscription_id' => $subscription->id,
                    'renewal_date' => now()->toDateTimeString(),
                    'next_renewal' => $subscription->next_billing_date,
                ],
                'fulfilled_at' => now(),
            ]);
            
            // Here you would extend access if needed
            // In case of a content subscription, you might need to refresh access tokens, etc.
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error processing subscription renewal: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fulfill a digital product (e.g., generate download links, license keys)
     */
    private function fulfillDigitalProduct(Order $order, Fulfillment $fulfillment)
    {
        // Example implementation for digital product fulfillment
        try {
            // Generate license key, download link, or whatever digital delivery you need
            $licenseKey = $this->generateLicenseKey();
            $downloadLink = $this->generateDownloadLink($order->productPrice->product);
            
            // Store fulfillment details
            $fulfillment->update([
                'meta' => [
                    'license_key' => $licenseKey,
                    'download_link' => $downloadLink,
                    'expires_at' => now()->addYear()->toDateTimeString(),
                ]
            ]);
            
            // You might want to send the license key and download link via email
            // This is often handled by the notification, but you can also do it here
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error fulfilling digital product: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fulfill a physical product (e.g., prepare for shipping)
     */
    private function fulfillPhysicalProduct(Order $order, Fulfillment $fulfillment)
    {
        // Example implementation for physical product fulfillment
        try {
            // In a real application, you might integrate with a shipping API
            // or create a task in your fulfillment system
            
            // For example, create a shipping label
            $trackingNumber = 'TRACK-' . rand(10000, 99999);
            $carrier = 'Example Shipping Co.';
            
            // Update fulfillment record
            $fulfillment->update([
                'status' => 'processing', // Physical products typically have a processing stage
                'tracking_number' => $trackingNumber,
                'carrier' => $carrier,
                'meta' => [
                    'shipping_address' => [
                        'name' => $order->billing_name,
                        'address' => $order->billing_address,
                        'city' => $order->billing_city,
                        'state' => $order->billing_state,
                        'zipcode' => $order->billing_zipcode,
                        'country' => $order->billing_country,
                    ],
                    'estimated_delivery' => now()->addDays(5)->toDateTimeString(),
                ]
            ]);
            
            // For demo purposes, we'll mark it as fulfilled immediately
            // In a real application, you would update this when the product ships
            return true;
        } catch (\Exception $e) {
            Log::error('Error fulfilling physical product: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fulfill a service product (e.g., schedule an appointment)
     */
    private function fulfillServiceProduct(Order $order, Fulfillment $fulfillment)
    {
        // Example implementation for service product fulfillment
        try {
            // For a service, you might schedule an appointment or create an account
            // in your service delivery system
            
            // For example, generate a service activation code
            $serviceCode = 'SERVICE-' . strtoupper(substr(md5(uniqid()), 0, 8));
            
            // Update fulfillment record
            $fulfillment->update([
                'meta' => [
                    'service_code' => $serviceCode,
                    'instructions' => 'Contact our team at support@example.com to schedule your appointment.',
                    'valid_until' => now()->addMonths(3)->toDateTimeString(),
                ]
            ]);
            
            // You might also want to create a task for your service team
            // or send an internal notification
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error fulfilling service product: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fulfill a subscription product (e.g., activate subscription access)
     */
    private function fulfillSubscriptionProduct(Order $order, Fulfillment $fulfillment)
    {
        // Example implementation for subscription initial fulfillment
        try {
            // Get or create subscription based on the order
            $subscription = Subscription::where('order_id', $order->id)->first();
            
            if (!$subscription) {
                Log::error('Subscription not found for order: ' . $order->id);
                return false;
            }
            
            // Activate the subscription if it's not already active
            if ($subscription->status !== 'active' && $subscription->status !== 'trialing') {
                $subscription->update([
                    'status' => $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture() 
                        ? 'trialing' 
                        : 'active'
                ]);
            }
            
            // Update fulfillment record
            $fulfillment->update([
                'type' => 'subscription_access',
                'meta' => [
                    'subscription_id' => $subscription->id,
                    'activated_at' => now()->toDateTimeString(),
                    'next_billing_date' => $subscription->next_billing_date,
                    'trial_ends_at' => $subscription->trial_ends_at,
                ]
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error fulfilling subscription product: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a license key for digital products
     */
    private function generateLicenseKey()
    {
        // Simple license key generator for example purposes
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 16));
    }
    
    /**
     * Generate a download link for a digital product
     */
    private function generateDownloadLink($product)
    {
        // Simple download link generator for example purposes
        $token = bin2hex(random_bytes(32));
        return url('/download/' . $product->id . '/' . $token);
    }
}