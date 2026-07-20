<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IdCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'template_id',
        'card_number',
        'issue_date',
        'expiry_date',
        'status',
        'printed_by',
        'printed_at'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'printed_at' => 'datetime',
        'status' => 'string'
    ];

    // Define relationship with student
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    // Define relationship with user who printed
    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    // Scope for active cards
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope for expired cards
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
                    ->where('expiry_date', '<', today());
    }

    // Scope for valid cards
    public function scopeValid($query)
    {
        return $query->where('status', 'active')
                    ->where(function($query) {
                        $query->whereNull('expiry_date')
                              ->orWhere('expiry_date', '>=', today());
                    });
    }
}