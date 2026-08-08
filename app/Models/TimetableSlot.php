<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimetableSlot extends Model
{
    use HasFactory;

    /**
     * T4b: a slot is 'published' (the live timetable, the default every
     * pre-T4b row backfilled to), 'draft' (a proposed arrangement from a
     * GenerateTimetableJob run, not yet live), or 'archived' (a formerly-
     * published slot displaced by a PUBLISH -- kept for history, excluded
     * from the uniqueness constraints entirely, see the migration that
     * added class_bell_active_key/teacher_active_key).
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'school_class_id',
        'section_id',
        'bell_timing_id',
        'subject_id',
        'teacher_id',
        'co_teacher_id',
        'combined_class_group_id',
        'room_number',
        'academic_year',
        'status',
        'timetable_generation_id',
        'is_locked',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function bellTiming()
    {
        return $this->belongsTo(BellTiming::class, 'bell_timing_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    /** T6 item 4: the optional second (team-teaching) teacher occupying this same slot alongside teacher_id. */
    public function coTeacher()
    {
        return $this->belongsTo(Teacher::class, 'co_teacher_id');
    }

    public function combinedClassGroup()
    {
        return $this->belongsTo(CombinedClassGroup::class, 'combined_class_group_id');
    }

    public function timetableGeneration()
    {
        return $this->belongsTo(TimetableGeneration::class);
    }

    /**
     * The live timetable -- every reader outside draft review/generation
     * (feasibility, substitutions, PDFs, and eventually parent views) must
     * use this, never the bare unscoped query. See timetable-T4b-report.md
     * for the audited list of readers.
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /** Phase 5 (Locked Lessons): rows Auto-Fix, a future Rebalance pass, and the generator must never move. */
    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }
}
