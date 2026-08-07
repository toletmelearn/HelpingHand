<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * T4b: one row per GenerateTimetableJob run. Status is the run's own
 * lifecycle, not a fixed DB enum: queued -> running -> completed (ready
 * for review) -> published | discarded (terminal), or failed at any point
 * before completed.
 */
class TimetableGeneration extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DISCARDED = 'discarded';

    /** T6 item 2: same pattern spread across days vs one day's pattern repeated on every running day. */
    public const STYLE_ROTATING = 'rotating';
    public const STYLE_FIXED_DAILY = 'fixed_daily';

    protected $fillable = [
        'academic_year',
        'academic_session_id',
        'school_class_ids',
        'style',
        'status',
        'placed_count',
        'unplaced_count',
        'report',
        'error',
        'requested_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'school_class_ids' => 'array',
        'report' => 'array',
        'placed_count' => 'integer',
        'unplaced_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function slots()
    {
        return $this->hasMany(TimetableSlot::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /** Null while there's nothing to divide by yet (queued/running). */
    public function getPlacementPercentAttribute(): ?float
    {
        $total = $this->placed_count + $this->unplaced_count;

        return $total > 0 ? round(($this->placed_count / $total) * 100, 1) : null;
    }
}
