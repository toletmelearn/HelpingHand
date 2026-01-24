<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Payment\StripePaymentService;
use App\Models\Fee;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(StripePaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Show the payment form for a specific fee
     */
    public function showPaymentForm($feeId)
    {
        $fee = Fee::with('student')->findOrFail($feeId);

        // Authorize that the user can pay this fee
        $this->authorizeFeeAccess($fee);

        return view('payments.form', compact('fee'));
    }

    /**
     * Process the payment
     */
    public function processPayment(Request $request, $feeId)
    {
        $request->validate([
            'token' => 'required|string',
            'amount' => 'required|numeric|min:0.50',
        ]);

        $fee = Fee::findOrFail($feeId);

        // Authorize that the user can pay this fee
        $this->authorizeFeeAccess($fee);

        // Verify the amount matches the due amount
        if ($request->amount != $fee->due_amount) {
            return redirect()->back()->withErrors(['amount' => 'Payment amount does not match due amount.']);
        }

        // Process the payment
        $paymentData = [
            'amount' => $request->amount,
            'currency' => 'inr', // Using Indian Rupees
            'token' => $request->token,
            'description' => 'School Fee Payment for ' . $fee->student->name,
            'metadata' => [
                'fee_id' => $fee->id,
                'student_id' => $fee->student_id,
                'user_id' => Auth::id(),
            ]
        ];

        $result = $this->paymentService->processPayment($paymentData);

        if ($result['success']) {
            // Update the fee record
            $fee->update([
                'paid_amount' => $fee->paid_amount + $request->amount,
                'due_amount' => max(0, $fee->due_amount - $request->amount),
                'status' => $fee->due_amount <= $request->amount ? 'paid' : 'partial',
                'payment_transaction_id' => $result['charge']->id,
                'payment_method' => 'stripe',
                'payment_status' => $result['charge']->status,
            ]);

            // Log the payment activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($fee)
                ->withProperties([
                    'payment_id' => $result['charge']->id,
                    'amount' => $request->amount,
                    'status' => $result['charge']->status
                ])
                ->log('fee_payment_processed');

            return redirect()->route('fees.show', $fee->id)
                ->with('success', 'Payment processed successfully!');
        } else {
            return redirect()->back()
                ->withErrors(['payment' => 'Payment failed: ' . $result['error']]);
        }
    }

    /**
     * Create a payment intent for Stripe Elements
     */
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.50',
            'fee_id' => 'required|exists:fees,id',
        ]);

        $fee = Fee::findOrFail($request->fee_id);

        // Authorize that the user can pay this fee
        $this->authorizeFeeAccess($fee);

        // Verify the amount
        if ($request->amount > $fee->due_amount) {
            return response()->json(['error' => 'Payment amount exceeds due amount'], 400);
        }

        $paymentData = [
            'amount' => $request->amount,
            'currency' => 'inr',
            'description' => 'School Fee Payment',
            'metadata' => [
                'fee_id' => $fee->id,
                'student_id' => $fee->student_id,
                'user_id' => Auth::id(),
            ]
        ];

        $result = $this->paymentService->createPaymentIntent($paymentData);

        if ($result['success']) {
            return response()->json([
                'client_secret' => $result['intent']->client_secret
            ]);
        } else {
            return response()->json(['error' => $result['error']], 500);
        }
    }

    /**
     * Handle Stripe webhook
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                config('services.stripe.webhook.secret')
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid payload for Stripe webhook');
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid signature for Stripe webhook');
            return response('Invalid signature', 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $this->handlePaymentSuccess($paymentIntent);
                break;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                $this->handlePaymentFailure($paymentIntent);
                break;
            // ... handle other event types
            default:
                // Unexpected event type
                Log::info('Received unexpected event type: ' . $event->type);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentSuccess($paymentIntent)
    {
        // Find the fee based on metadata
        $feeId = $paymentIntent->metadata->fee_id ?? null;

        if ($feeId) {
            $fee = Fee::find($feeId);
            if ($fee) {
                $fee->update([
                    'payment_transaction_id' => $paymentIntent->id,
                    'payment_status' => 'succeeded',
                ]);

                // Log the activity
                activity()
                    ->performedOn($fee)
                    ->withProperties(['transaction_id' => $paymentIntent->id])
                    ->log('payment_succeeded');
            }
        }
    }

    /**
     * Handle failed payment
     */
    protected function handlePaymentFailure($paymentIntent)
    {
        // Find the fee based on metadata
        $feeId = $paymentIntent->metadata->fee_id ?? null;

        if ($feeId) {
            $fee = Fee::find($feeId);
            if ($fee) {
                $fee->update([
                    'payment_transaction_id' => $paymentIntent->id,
                    'payment_status' => 'failed',
                ]);

                // Log the activity
                activity()
                    ->performedOn($fee)
                    ->withProperties(['transaction_id' => $paymentIntent->id])
                    ->log('payment_failed');
            }
        }
    }

    /**
     * Authorize fee access based on user role
     */
    protected function authorizeFeeAccess(Fee $fee)
    {
        $user = Auth::user();

        // Check if user has admin or accountant role
        $isAdmin = $user->roles()->where('name', 'admin')->exists();
        $isAccountant = $user->roles()->where('name', 'accountant')->exists();
        
        if ($isAdmin || $isAccountant) {
            return; // Admins and accountants can access all fees
        }

        $isStudent = $user->roles()->where('name', 'student')->exists();
        $isParent = $user->roles()->where('name', 'parent')->exists();
        
        if ($isStudent) {
            // Students can only access their own fees
            if ($fee->student_id !== $user->student->id) {
                abort(403, 'Unauthorized to access this fee');
            }
        } elseif ($isParent) {
            // Parents can access fees for their children
            // Note: We need to adjust this based on actual parent-child relationship implementation
            // For now, assuming the user can access fees for students linked to their account
            $hasAccess = false;
            // Check if the fee belongs to a student associated with this parent
            // This assumes there's a relationship between users and students they can pay for
            
            // In a real implementation, you would check the guardian relationship
            $studentIds = $user->guardians()->pluck('student_id');
            if ($studentIds->contains($fee->student_id)) {
                $hasAccess = true;
            }
            
            if (!$hasAccess) {
                abort(403, 'Unauthorized to access this fee');
            }
        } else {
            abort(403, 'Unauthorized to access fees');
        }
    }
}