<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisciplinaryAction extends Model
{
    use HasFactory;

    protected $table = 'disciplinary_actions';

    protected $fillable = [
        'incident_id',
        'action_type',
        'action_details',
        'parent_notified_at',
    ];

    protected $casts = [
        'parent_notified_at' => 'datetime',
    ];

    public function incident()
    {
        return $this->belongsTo(DisciplinaryIncident::class, 'incident_id');
    }
}
