<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'certificate_type',
        'serial_number',
        'recipient_id',
        'recipient_type',
        'content_data',
        'formatted_content',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'published_at',
        'revoked_at',
        'revocation_reason',
        'metadata'
    ];
    
    protected $casts = [
        'content_data' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    public function recipient()
    {
        return $this->morphTo('recipient', 'recipient_type', 'recipient_id');
    }
    
    public function getStatusLabelAttribute()
    {
        $labels = [
            'draft' => 'Draft',
            'generated' => 'Generated',
            'published' => 'Published',
            'locked' => 'Locked',
            'revoked' => 'Revoked'
        ];
        
        return $labels[$this->status] ?? ucfirst($this->status);
    }
    
    public function canBeModified(): bool
    {
        return in_array($this->status, ['draft', 'generated']);
    }
    
    public function canBeApproved(): bool
    {
        return $this->status === 'draft' && !empty($this->content_data);
    }
    
    public function canBePublished(): bool
    {
        return $this->status === 'generated';
    }
    
    public function canBeRevoked(): bool
    {
        return in_array($this->status, ['published', 'locked']);
    }
    
    public function approve($userId)
    {
        $this->status = 'generated';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();
        
        return $this;
    }
    
    public function publish()
    {
        $this->status = 'published';
        $this->published_at = now();
        $this->save();
        
        return $this;
    }
    
    public function lock()
    {
        $this->status = 'locked';
        $this->save();
        
        return $this;
    }
    
    public function revoke($reason, $userId)
    {
        $this->status = 'revoked';
        $this->revoked_at = now();
        $this->revocation_reason = $reason;
        $this->save();
        
        return $this;
    }
}
