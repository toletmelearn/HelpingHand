<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which classes/sections a Datesheet covers. section_id null = whole
 * class, matching the same convention already used by
 * TeacherClassSubjectAssignment/TimetableSlot this session.
 */
class DatesheetClass extends Model
{
    protected $fillable = [
        'datesheet_id',
        'school_class_id',
        'section_id',
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
}
