<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'visitor_name',
        'guardian_id',
        'teacher_id',
        'receptionist_id',
        'scheduled_date',
        'start_time',
        'end_time',
        'purpose',
        'status',
        'feedback',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function guardian()
    {
        return $this->belongsTo(Guardian::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function receptionist()
    {
        return $this->belongsTo(User::class, 'receptionist_id');
    }

    public function setStartTimeAttribute($value)
    {
        $this->attributes['start_time'] = \Carbon\Carbon::parse($value)->format('H:i:s');
    }

    public function setEndTimeAttribute($value)
    {
        $this->attributes['end_time'] = \Carbon\Carbon::parse($value)->format('H:i:s');
    }
}
