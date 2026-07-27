<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * T2b item 3: one teaching event (one teacher, one subject, one period)
 * that serves several classes at once. See member class-sections via
 * members(); the actual TimetableSlot rows it produces are linked back
 * via TimetableSlot::combined_class_group_id.
 */
class CombinedClassGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject_id',
        'academic_session_id',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function members()
    {
        return $this->hasMany(CombinedClassGroupMember::class);
    }

    public function slots()
    {
        return $this->hasMany(TimetableSlot::class, 'combined_class_group_id');
    }
}
