<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Datesheet module: the planning/approval layer sitting in front of Exam.
 * Modeled after TimetableGeneration (batch header owning many line items
 * with its own lifecycle) and AdmitCard (guarded state machine with
 * actor+timestamp columns per transition) -- both existing, proven
 * patterns in this codebase, reused rather than reinvented.
 *
 * Published Datesheets are immutable. A post-publish correction creates a
 * brand-new Datesheet linked via superseded_by_id and goes through the
 * full draft->under_review->approved->published cycle again.
 */
class Datesheet extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'name',
        'exam_type',
        'academic_session_id',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'submitted_by',
        'reviewed_by',
        'approved_by',
        'published_by',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'published_at',
        'superseded_by_id',
        'revises_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(DatesheetClass::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DatesheetEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Datesheet::class, 'superseded_by_id');
    }

    public function revises(): BelongsTo
    {
        return $this->belongsTo(Datesheet::class, 'revises_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Mirrors AdmitCard::canTransitionTo() exactly: a fixed transition map,
     * published is terminal (no forward transitions out of it -- a
     * correction is a new Datesheet, not a status change on this one).
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $transitions = [
            self::STATUS_DRAFT => [self::STATUS_UNDER_REVIEW],
            self::STATUS_UNDER_REVIEW => [self::STATUS_DRAFT, self::STATUS_APPROVED],
            self::STATUS_APPROVED => [self::STATUS_DRAFT, self::STATUS_PUBLISHED],
            self::STATUS_PUBLISHED => [],
        ];

        return in_array($newStatus, $transitions[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
