<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CombinedClassGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'combined_class_group_id',
        'school_class_id',
        'section_id',
    ];

    public function group()
    {
        return $this->belongsTo(CombinedClassGroup::class, 'combined_class_group_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
