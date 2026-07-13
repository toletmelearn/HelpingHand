<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends Model
{
    protected $fillable = [
        'guardian_name',
        'mobile',
        'aadhaar_number',
        'created_by',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'family_id');
    }
}
