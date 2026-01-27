<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'filename',
        'path',
        'type',
        'location',
        'size',
        'status',
        'notes',
        'metadata',
        'created_by',
        'scheduled_at',
        'completed_at',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ];
        
        return $labels[$this->status] ?? ucfirst($this->status);
    }
    
    public function getTypeLabelAttribute()
    {
        $labels = [
            'full' => 'Full Backup',
            'database' => 'Database Only',
            'files' => 'Files Only',
        ];
        
        return $labels[$this->type] ?? ucfirst($this->type);
    }
    
    public function getLocationLabelAttribute()
    {
        $labels = [
            'local' => 'Local',
            'cloud' => 'Cloud',
        ];
        
        return $labels[$this->location] ?? ucfirst($this->location);
    }
    
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
    
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
    
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    
    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        } else {
            return '0 bytes';
        }
    }
}
