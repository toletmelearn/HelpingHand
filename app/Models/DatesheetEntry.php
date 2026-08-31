<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (class/section, subject) paper within a Datesheet.
 * subject_id is a real FK to Subject -- deliberately stricter than the
 * legacy Exam.subject free string; the string is derived from
 * Subject::name only at publish time (DatesheetPublishService), for
 * backward compatibility with the existing Exam table, never stored here.
 *
 * exam_id is populated only once this entry has been published into a
 * real Exam row -- the whole integration point with the existing,
 * unchanged Exam/Marks/Result/Grade/Admit Card chain.
 */
class DatesheetEntry extends Model
{
    protected $fillable = [
        'datesheet_id',
        'school_class_id',
        'section_id',
        'subject_id',
        'exam_date',
        'start_time',
        'end_time',
        'total_marks',
        'passing_marks',
        'room',
        'instructions',
        'exam_id',
    ];

    protected $casts = [
        'exam_date' => 'date',
    ];

    public function datesheet(): BelongsTo
    {
        return $this->belongsTo(Datesheet::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** Derived, never stored -- see migration docblock for why. */
    public function getDayOfWeekAttribute(): ?string
    {
        return $this->exam_date?->format('l');
    }
}
