<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'capacity',
        'description'
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function classes()
    {
        return $this->hasMany(SchoolClass::class);
    }
}