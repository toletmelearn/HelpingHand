<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class SelfServiceToken extends Model
{
    protected $fillable = [
        'teacher_id',
        'token',
        'device_name',
        'ip_address',
        'expires_at',
        'last_used_at',
        'is_active',
        'permissions',
    ];
    
    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];
    
    // Relationships
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('expires_at', '>', Carbon::now());
    }
    
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
    
    // Methods
    public static function generateForTeacher($teacherId, $deviceName = null, $ipAddress = null, $expiresInDays = 30)
    {
        return self::create([
            'teacher_id' => $teacherId,
            'token' => Str::random(60),
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
            'expires_at' => Carbon::now()->addDays($expiresInDays),
            'is_active' => true,
            'permissions' => ['view_attendance', 'view_reports'],
        ]);
    }
    
    public function isValid()
    {
        return $this->is_active && $this->expires_at->isFuture();
    }
    
    public function can($permission)
    {
        return $this->isValid() && in_array($permission, $this->permissions ?: []);
    }
    
    public function useToken($ipAddress = null)
    {
        if ($this->isValid()) {
            $this->last_used_at = Carbon::now();
            if ($ipAddress) {
                $this->ip_address = $ipAddress;
            }
            $this->save();
            return true;
        }
        return false;
    }
    
    public function revoke()
    {
        $this->is_active = false;
        $this->save();
    }
    
    public function extend($days = 30)
    {
        $this->expires_at = Carbon::now()->addDays($days);
        $this->save();
    }
    
    public static function findByToken($token)
    {
        return self::where('token', $token)->first();
    }
    
    public function getRemainingDaysAttribute()
    {
        if ($this->expires_at) {
            return max(0, Carbon::now()->diffInDays($this->expires_at, false));
        }
        return 0;
    }
}
