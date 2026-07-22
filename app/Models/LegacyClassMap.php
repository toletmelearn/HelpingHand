<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyClassMap extends Model
{
    protected $table = 'legacy_class_map';

    protected $fillable = [
        'class_management_id',
        'school_class_id',
    ];

    public function classManagement()
    {
        return $this->belongsTo(ClassManagement::class, 'class_management_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }
}
