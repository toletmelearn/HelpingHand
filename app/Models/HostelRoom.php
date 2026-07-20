<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostelRoom extends Model
{
    use HasFactory;

    protected $table = 'hostel_rooms';

    protected $fillable = [
        'hostel_id',
        'room_no',
        'capacity',
        'cost_per_bed',
    ];

    public function hostel()
    {
        return $this->belongsTo(Hostel::class, 'hostel_id');
    }

    public function allocations()
    {
        return $this->hasMany(StudentHostelAllocation::class, 'room_id');
    }
}
