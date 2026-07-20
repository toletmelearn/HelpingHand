<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CallLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'caller_name',
        'phone',
        'call_type',
        'purpose',
        'duration',
        'assigned_user_id',
        'status',
        'follow_up_date',
        'outcome',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
        'duration' => 'integer',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
