<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;

class MedicalCheckup extends Model
{
    use HasFactory;

    protected $table = 'medical_checkups';

    protected $fillable = [
        'student_id',
        'checkup_date',
        'doctor_name',
        'diagnosis',
        'treatment',
        'vaccination_logs',
    ];

    protected $casts = [
        'checkup_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
