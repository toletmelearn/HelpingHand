<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Teacher;

class TeacherExperience extends Model
{
    protected $fillable = [
        'teacher_id',
        'organization_name',
        'position',
        'employment_type',
        'start_date',
        'end_date',
        'currently_working',
        'responsibilities',
        'salary',
        'reason_for_leaving',
        'certificate_path'
    ];
    
    protected $dates = ['start_date', 'end_date'];
    
    protected $casts = [
        'currently_working' => 'boolean',
        'salary' => 'decimal:2'
    ];
    
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
    
    public function getDurationAttribute()
    {
        if ($this->end_date) {
            $startDate = $this->start_date->format('M Y');
            $endDate = $this->end_date->format('M Y');
            return "{$startDate} - {$endDate}";
        } else {
            return $this->start_date->format('M Y') . ' - Present';
        }
    }
}
