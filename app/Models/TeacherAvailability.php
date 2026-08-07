<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Absence of a row = available. A row only ever means "blocked" in
 * practice (is_available defaults true only so the schema doesn't
 * preclude a future explicit-available use case). Timetable-module T2a.
 */
class TeacherAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'day',
        'bell_timing_id',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    protected static function booted(): void
    {
        // `day` is redundant with bell_timing_id's own day_of_week --
        // always derive it from the chosen bell timing rather than
        // trusting client input, so the two columns can never drift out
        // of sync (see the create-table migration for why both exist).
        static::saving(function (TeacherAvailability $availability) {
            if ($availability->bell_timing_id) {
                $bellTiming = $availability->relationLoaded('bellTiming')
                    ? $availability->bellTiming
                    : BellTiming::find($availability->bell_timing_id);

                if ($bellTiming) {
                    $availability->day = $bellTiming->day_order;
                }
            }
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function bellTiming(): BelongsTo
    {
        return $this->belongsTo(BellTiming::class);
    }
}
