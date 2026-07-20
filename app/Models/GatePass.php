<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GatePass extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'gate_passes';

    protected $fillable = [
        'pass_type',
        'holder_name',
        'student_id',
        'user_id',
        'vehicle_no',
        'purpose',
        'request_date',
        'departure_time',
        'arrival_time',
        'requested_by',
        'approved_by',
        'verified_by',
        'status',
        'exit_gate',
        'override_reason',
    ];

    protected $casts = [
        'request_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function setDepartureTimeAttribute($value)
    {
        $this->attributes['departure_time'] = $value ? \Carbon\Carbon::parse($value)->format('H:i:s') : null;
    }

    public function setArrivalTimeAttribute($value)
    {
        $this->attributes['arrival_time'] = $value ? \Carbon\Carbon::parse($value)->format('H:i:s') : null;
    }
}
