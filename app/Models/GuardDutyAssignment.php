<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuardDutyAssignment extends Model
{
    use HasFactory;

    protected $table = 'guard_duty_assignments';

    protected $fillable = [
        'user_id',
        'gate_name',
        'duty_date',
        'shift',
        'assigned_by',
        'status',
    ];

    protected $casts = [
        'duty_date' => 'date',
    ];

    public function guardUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
