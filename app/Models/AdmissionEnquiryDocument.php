<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionEnquiryDocument extends Model
{
    protected $fillable = [
        'admission_enquiry_id',
        'document_type',
        'document_path',
        'original_filename',
        'is_verified',
        'verified_by',
        'verified_at',
        'verification_notes',
        'file_size',
        'file_mime_type',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(AdmissionEnquiry::class, 'admission_enquiry_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
