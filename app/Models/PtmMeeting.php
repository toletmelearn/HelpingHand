<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Teacher;
use App\Models\ParentModel;

class PtmMeeting extends Model
{
    use HasFactory;

    protected $table = 'ptm_meetings';

    protected $fillable = [
        'teacher_id',
        'parent_id',
        'meeting_date',
        'time_slot',
        'status',
        'notes',
    ];

    protected $casts = [
        'meeting_date' => 'date',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function parent()
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }
}
