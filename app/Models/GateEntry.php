<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GateEntry extends Model
{
    use HasFactory;

    protected $table = 'gate_entries';

    protected $fillable = [
        'visitor_name',
        'phone',
        'purpose',
        'department',
        'id_proof_type',
        'id_proof_number',
        'photo_path',
        'is_blacklisted',
        'is_emergency',
        'remarks',
        'check_in',
        'check_out',
        'vehicle_no',
        'host_user_id',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'is_blacklisted' => 'boolean',
        'is_emergency' => 'boolean',
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }
}
