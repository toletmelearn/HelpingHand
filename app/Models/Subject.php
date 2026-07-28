<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'subject_type',
        'is_active',
        'sort_order',
        'prefer_morning',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'prefer_morning' => 'boolean',
    ];

    /**
     * Get the results for this subject
     */
    public function results()
    {
        return $this->hasMany(CBSEResult::class, 'subject_id');
    }

    /**
     * Scope for active subjects
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for scholastic subjects
     */
    public function scopeScholastic($query)
    {
        return $query->where('subject_type', 'scholastic');
    }

    /**
     * Scope for co-scholastic subjects
     */
    public function scopeCoScholastic($query)
    {
        return $query->where('subject_type', 'co_scholastic');
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . $this->code . ')';
    }
}