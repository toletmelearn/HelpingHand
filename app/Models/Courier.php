<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Courier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tracking_number',
        'courier_company',
        'courier_type',
        'parcel_type',
        'sender',
        'receiver',
        'recipient_user_id',
        'delivery_date',
        'status',
        'attachment_path',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
