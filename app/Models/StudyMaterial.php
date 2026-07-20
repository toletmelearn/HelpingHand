<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;

class StudyMaterial extends Model
{
    use HasFactory;

    protected $table = 'study_materials';

    protected $fillable = [
        'class_id',
        'subject_id',
        'title',
        'description',
        'file_path',
        'uploaded_by',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
