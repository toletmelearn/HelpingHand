<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $table = 'medical_records';

    protected $fillable = [
        'student_id',
        'blood_group',
        'height_cm',
        'weight_kg',
        'allergies',
        'medical_conditions',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
