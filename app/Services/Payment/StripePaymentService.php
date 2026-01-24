<?php

namespace App\Services\Payment;

use Stripe\Stripe;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;

class StripePaymentService
{
    protected $stripe;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a customer in Stripe
     */
    public function createCustomer($userData)
    {
        try {
            $customer = Customer::create([
                'name' => $userData['name'] ?? '',
                'email' => $userData['email'] ?? '',
                'phone' => $userData['phone'] ?? '',
                'metadata' => [
                    'user_id' => $userData['user_id'] ?? null,
                ]
            ]);

            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe customer creation failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process a payment
     */
    public function processPayment($paymentData)
    {
        try {
            $charge = Charge::create([
                'amount' => $paymentData['amount'] * 100, // Convert to cents
                'currency' => $paymentData['currency'] ?? 'usd',
                'source' => $paymentData['token'], // obtained with Stripe.js
                'description' => $paymentData['description'] ?? 'School Fee Payment',
                'metadata' => $paymentData['metadata'] ?? [],
            ]);

            return [
                'success' => true,
                'charge' => $charge
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a payment intent for SCA compliance
     */
    public function createPaymentIntent($paymentData)
    {
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $paymentData['amount'] * 100, // Convert to cents
                'currency' => $paymentData['currency'] ?? 'usd',
                'payment_method_types' => ['card'],
                'description' => $paymentData['description'] ?? 'School Fee Payment',
                'metadata' => $paymentData['metadata'] ?? [],
            ]);

            return [
                'success' => true,
                'intent' => $intent
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment intent creation failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment($chargeId, $amount = null)
    {
        try {
            $refundData = ['charge' => $chargeId];
            
            if ($amount) {
                $refundData['amount'] = $amount * 100; // Convert to cents
            }

            $refund = \Stripe\Refund::create($refundData);

            return [
                'success' => true,
                'refund' => $refund
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get payment details
     */
    public function getPayment($chargeId)
    {
        try {
            $charge = Charge::retrieve($chargeId);

            return [
                'success' => true,
                'charge' => $charge
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe get payment failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}