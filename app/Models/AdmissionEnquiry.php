<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionEnquiry extends Model
{
    use HasFactory;

    protected $table = 'admission_enquiries';

    protected $fillable = [
        'candidate_name',
        'parent_name',
        'phone',
        'email',
        'status',
        'interview_date',
        'interview_score',
        'entrance_score',
        'counsellor_id',
        'aadhaar_number',
        'visit_scheduled_at',
        'follow_up_date',
        'follow_up_notes',
        'remarks',
    ];

    protected $casts = [
        'visit_scheduled_at' => 'datetime',
        'follow_up_date' => 'date',
        'interview_date' => 'date',
    ];

    public function counsellor()
    {
        return $this->belongsTo(User::class, 'counsellor_id');
    }

    public function documents()
    {
        return $this->hasMany(AdmissionEnquiryDocument::class);
    }

    public function payments()
    {
        return $this->hasMany(AdmissionEnquiryPayment::class);
    }
}
