<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class AdmitCardFormat extends Model
{
    protected $fillable = [
        'name',
        'header_html',
        'body_html',
        'footer_html',
        'background_image',
        'is_active',
        'created_by'
    ];
    
    protected $casts = [
        'is_active' => 'boolean'
    ];
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
