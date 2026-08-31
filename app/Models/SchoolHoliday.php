<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolHoliday extends Model
{
    protected $fillable = [
        'academic_year',
        'holiday_name',
        'start_date',
        'end_date',
        'holiday_type',
        'description',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForAcademicYear($query, string $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }

    /**
     * True if $date (a date-string or DateTimeInterface) falls within any
     * holiday range. Uses whereDate(), not where(): start_date/end_date
     * are Eloquent 'date' casts, which serialize to a full
     * 'Y-m-d H:i:s' string on write -- a plain where() comparing against
     * a bare 'Y-m-d' input string silently never matches (same footgun
     * documented in DatesheetConflictChecker for the same reason).
     */
    public static function isHolidayOn($date): bool
    {
        $date = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

        return static::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    /** Every holiday overlapping [$from, $to]. */
    public static function getHolidaysInRange($from, $to)
    {
        $from = $from instanceof \DateTimeInterface ? $from->format('Y-m-d') : $from;
        $to = $to instanceof \DateTimeInterface ? $to->format('Y-m-d') : $to;

        return static::whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->orderBy('start_date')
            ->get();
    }
}
