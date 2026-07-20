<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'module',
        'status',
        'column_mappings',
        'settings',
        'total_rows',
        'processed_rows',
        'success_rows',
        'error_rows',
        'resume_pointer',
        'start_time',
        'end_time',
        'created_by'
    ];

    protected $casts = [
        'column_mappings' => 'array',
        'settings' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function errors()
    {
        return $this->hasMany(ImportError::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
