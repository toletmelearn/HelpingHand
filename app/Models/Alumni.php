<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alumni extends Model
{
    use HasFactory;

    protected $table = 'alumni';

    protected $fillable = [
        'student_id',
        'graduation_year',
        'current_occupation',
        'contact_email',
        'feedback',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
