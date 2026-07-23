<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id',
        'title',
        'type',
        'start_date',
        'end_date',
        'description',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function scopeHolidays($query)
    {
        return $query->where('type', 'holiday');
    }

    public function scopeForSession($query, $academicSessionId)
    {
        return $query->where('academic_session_id', $academicSessionId);
    }

    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);
    }

    public static function isHoliday($date): ?self
    {
        $date = \Illuminate\Support\Carbon::parse($date)->toDateString();

        return static::query()
            ->holidays()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }
}
