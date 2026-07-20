<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniformCheck extends Model
{
    use HasFactory;

    protected $table = 'uniform_checks';

    protected $fillable = [
        'student_id',
        'check_date',
        'is_compliant',
        'remarks',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
