<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Models\User;

class DisciplinaryIncident extends Model
{
    use HasFactory;

    protected $table = 'disciplinary_incidents';

    protected $fillable = [
        'student_id',
        'incident_date',
        'title',
        'description',
        'reported_by',
        'demerit_points',
        'status',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'demerit_points' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function actions()
    {
        return $this->hasMany(DisciplinaryAction::class, 'incident_id');
    }
}
