<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'class_order',
        'description',
        'is_active'
    ];

    protected $casts = [
        'class_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderByOrder($query)
    {
        return $query->orderBy('class_order');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function getNextClasses()
    {
        return self::where('class_order', '>', $this->class_order)
                  ->active()
                  ->orderByOrder()
                  ->get();
    }

    public function getPreviousClasses()
    {
        return self::where('class_order', '<', $this->class_order)
                  ->active()
                  ->orderByOrder()
                  ->get();
    }
}