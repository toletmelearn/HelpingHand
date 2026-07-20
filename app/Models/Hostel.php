<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hostel extends Model
{
    use HasFactory;

    protected $table = 'hostels';

    protected $fillable = [
        'name',
        'type',
        'capacity',
    ];

    public function rooms()
    {
        return $this->hasMany(HostelRoom::class, 'hostel_id');
    }
}
