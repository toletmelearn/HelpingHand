<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class BookIssue extends Model
{
    use Auditable;
    protected $fillable = [
        'book_id',
        'student_id',
        'issued_by',
        'issue_date',
        'due_date',
        'return_date',
        'delay_days',
        'fine_amount',
        'status',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'issued' && $this->due_date->isPast();
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'issued')->where('due_date', '<', now());
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }
}
