<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Models\User;

class DefaulterLog extends Model
{
    use HasFactory;

    protected $table = 'defaulter_logs';

    protected $fillable = [
        'student_id',
        'stage',
        'action_type',
        'status',
        'details',
        'performed_by'
    ];

    protected $casts = [
        'student_id' => 'integer',
        'performed_by' => 'integer'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
