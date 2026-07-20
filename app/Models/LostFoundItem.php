<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LostFoundItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lost_found_items';

    protected $fillable = [
        'item_name',
        'description',
        'location_found',
        'location_lost',
        'item_type',
        'date_reported',
        'reported_by_user_id',
        'reported_by_name',
        'claimant_name',
        'claimant_phone',
        'verification_details',
        'status',
        'returned_at',
        'photo_path',
    ];

    protected $casts = [
        'date_reported' => 'date',
        'returned_at' => 'datetime',
    ];

    public function reportedByUser()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }
}
