<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeReversalRequest extends Model
{
    use HasFactory;

    protected $table = 'fee_reversal_requests';

    protected $fillable = [
        'fee_collection_id',
        'student_id',
        'requested_by',
        'reason',
        'status', // pending, approved, rejected
        'processed_by',
        'reversal_date'
    ];

    protected $casts = [
        'reversal_date' => 'datetime'
    ];

    public function feeCollection()
    {
        return $this->belongsTo(FeeCollection::class, 'fee_collection_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
