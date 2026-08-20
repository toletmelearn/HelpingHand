<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable, day-independent Bell Timing structure. Deliberately separate
 * from BellTiming itself -- see the migration doc-comment for why reusing
 * bell_timings rows as "templates" is unsafe (class_section=NULL already
 * means something else, and timetable_slots.bell_timing_id cascades on
 * delete). Applying a template only ever creates ordinary BellTiming rows
 * via the same path Bulk Create already uses -- nothing here is ever read
 * by GeneratorService/FeasibilityService/TimetableConflictResolver.
 */
class BellTimingTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'academic_year',
        'semester',
        'created_by',
        'updated_by',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(BellTimingTemplateSlot::class)->orderBy('order_index');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
