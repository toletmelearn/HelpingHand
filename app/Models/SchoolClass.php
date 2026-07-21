<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'class_order',
        'section_id',
        'academic_session_id',
        'teacher_id',
        'description',
        'capacity',
        'stream',
        'is_active'
    ];

    protected $casts = [
        'class_order' => 'integer',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderByOrder($query)
    {
        return $query->orderBy('class_order');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function getNextClasses()
    {
        return self::where('class_order', '>', $this->class_order)
                  ->active()
                  ->orderByOrder()
                  ->get();
    }

    public function getPreviousClasses()
    {
        return self::where('class_order', '<', $this->class_order)
                  ->active()
                  ->orderByOrder()
                  ->get();
    }
}