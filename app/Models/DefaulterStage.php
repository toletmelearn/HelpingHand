<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;

class DefaulterStage extends Model
{
    use HasFactory;

    protected $table = 'defaulter_stages';

    protected $fillable = [
        'student_id',
        'stage',
        'outstanding_amount',
        'last_action_date',
        'remarks'
    ];

    protected $casts = [
        'student_id' => 'integer',
        'outstanding_amount' => 'decimal:2',
        'last_action_date' => 'datetime'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
