<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_session_id',
        'row_number',
        'raw_row_data',
        'error_message'
    ];

    protected $casts = [
        'raw_row_data' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(ImportSession::class, 'import_session_id');
    }
}
