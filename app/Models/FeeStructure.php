<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\FeeStructureItem;
use App\Models\FeeCollection;
use App\Models\StudentFeeAssignment;

class FeeStructure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'class_name',
        'academic_year',
        'frequency',
        'installment_count',
        'installment_frequency',
        'status',
        'created_by'
    ];

    protected $casts = [
        'status' => 'string',
        'frequency' => 'string',
        'installment_count' => 'integer',
        'installment_frequency' => 'string',
        'created_by' => 'integer'
    ];

    // Define relationship with fee structure items
    public function feeStructureItems()
    {
        return $this->hasMany(FeeStructureItem::class);
    }

    // Define relationship with student fee assignments
    public function studentFeeAssignments(): HasMany
    {
        return $this->hasMany(StudentFeeAssignment::class);
    }

    // Define relationship with fee collections
    public function feeCollections(): HasMany
    {
        return $this->hasMany(FeeCollection::class);
    }

    // Define relationship with creator (user)
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scope for active fee structures
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope for specific academic year
    public function scopeForAcademicYear($query, string $year)
    {
        return $query->where('academic_year', $year);
    }

    // Scope for specific class
    public function scopeForClass($query, string $className)
    {
        return $query->where('class_name', $className);
    }

    // Get total amount
    public function getTotalAmountAttribute()
    {
        return $this->feeStructureItems->sum('amount');
    }
}