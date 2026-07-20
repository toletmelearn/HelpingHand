<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportMappingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_name',
        'module',
        'mappings',
        'created_by'
    ];

    protected $casts = [
        'mappings' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
