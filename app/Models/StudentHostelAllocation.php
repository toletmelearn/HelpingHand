<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentHostelAllocation extends Model
{
    use HasFactory;

    protected $table = 'student_hostel_allocations';

    protected $fillable = [
        'student_id',
        'room_id',
        'status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function room()
    {
        return $this->belongsTo(HostelRoom::class, 'room_id');
    }
}
