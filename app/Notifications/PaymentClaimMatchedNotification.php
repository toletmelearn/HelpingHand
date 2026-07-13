<?php

namespace App\Notifications;

use App\Models\PaymentClaim;
use Illuminate\Notifications\Notification;

/**
 * database channel only, deliberately not queued -- NotificationService's
 * SMS/WhatsApp/Email methods are confirmed log-only mocks today, so the
 * in-app notifications table is the one channel that actually persists
 * and can be shown to the parent.
 */
class PaymentClaimMatchedNotification extends Notification
{
    protected PaymentClaim $claim;

    public function __construct(PaymentClaim $claim)
    {
        $this->claim = $claim;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payment Confirmed',
            'message' => sprintf(
                'Your UPI payment of ₹%s (UTR %s) has been matched and receipted.',
                number_format((float) $this->claim->amount, 2),
                $this->claim->utr
            ),
            'payment_claim_id' => $this->claim->id,
            'student_id' => $this->claim->student_id,
            'amount' => (float) $this->claim->amount,
            'fee_collection_id' => $this->claim->fee_collection_id,
        ];
    }
}
