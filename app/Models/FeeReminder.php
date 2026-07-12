<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_id',
        'student_fee_ledger_id',
        'student_id',
        'rule',
        'channel',
        'recipient',
        'message',
        'status',
        'retry_count',
        'error_message',
        'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'retry_count' => 'integer'
    ];

    /**
     * Get the fee associated with the reminder.
     * @deprecated legacy reference, kept nullable for historical rows -- see studentFeeLedger().
     */
    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    /**
     * Get the outstanding ledger debit this reminder is about.
     */
    public function studentFeeLedger(): BelongsTo
    {
        return $this->belongsTo(StudentFeeLedger::class);
    }

    /**
     * Get the student associated with the reminder.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
