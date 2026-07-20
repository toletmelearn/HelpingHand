<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionRule extends Model
{
    protected $fillable = [
        'class_name',
        'min_overall_percentage',
        'max_failed_subjects',
        'min_attendance_percentage',
        'academic_year',
    ];

    protected $casts = [
        'min_overall_percentage' => 'float',
        'max_failed_subjects' => 'integer',
        'min_attendance_percentage' => 'float',
    ];
}
