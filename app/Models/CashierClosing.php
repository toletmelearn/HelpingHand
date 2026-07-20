<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class CashierClosing extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'cashier_id',
        'closing_date',
        'opening_balance',
        'expected_cash',
        'expected_upi',
        'expected_bank',
        'expected_cheque',
        'expected_online',
        'actual_cash',
        'actual_upi',
        'actual_bank',
        'actual_cheque',
        'actual_online',
        'discrepancy_reason',
        'status',
        'remarks'
    ];

    protected $casts = [
        'closing_date' => 'date',
        'opening_balance' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'expected_upi' => 'decimal:2',
        'expected_bank' => 'decimal:2',
        'expected_cheque' => 'decimal:2',
        'expected_online' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'actual_upi' => 'decimal:2',
        'actual_bank' => 'decimal:2',
        'actual_cheque' => 'decimal:2',
        'actual_online' => 'decimal:2',
        'cashier_id' => 'integer'
    ];

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
