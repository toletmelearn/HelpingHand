<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;

class BiometricDevice extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'name',
        'device_type',
        'connection_type',
        'ip_address',
        'port',
        'api_url',
        'username',
        'password',
        'api_key',
        'device_config',
        'sync_frequency',
        'real_time_sync',
        'is_active',
        'status',
        'last_sync_at',
        'last_error',
        'retry_count',
        'next_sync_at',
        'description',
    ];
    
    protected $casts = [
        'device_config' => 'array',
        'sync_frequency' => 'integer',
        'real_time_sync' => 'boolean',
        'is_active' => 'boolean',
        'retry_count' => 'integer',
        'last_sync_at' => 'datetime',
        'next_sync_at' => 'datetime',
    ];
    
    protected $appends = [
        'display_name',
        'connection_string',
        'is_online',
    ];
    
    // Relationships
    public function syncLogs()
    {
        return $this->hasMany(BiometricSyncLog::class);
    }
    
    public function biometricRecords()
    {
        return $this->hasMany(TeacherBiometricRecord::class, 'biometric_device_id');
    }
    
    // Accessors
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . $this->device_type . ')';
    }
    
    public function getConnectionStringAttribute()
    {
        if ($this->connection_type === 'TCP/IP' && $this->ip_address) {
            return $this->ip_address . ':' . ($this->port ?: '4370');
        } elseif ($this->connection_type === 'REST' && $this->api_url) {
            return $this->api_url;
        }
        return 'N/A';
    }
    
    public function getIsOnlineAttribute()
    {
        return $this->status === 'online' && $this->is_active;
    }
    
    // Mutators
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }
    
    public function getPasswordAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }
    
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }
    
    public function getApiKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }
    
    public function scopeNeedsSync($query)
    {
        return $query->where('is_active', true)
                    ->where('next_sync_at', '<=', Carbon::now());
    }
    
    // Methods
    public function testConnection()
    {
        // Implementation will be in device-specific classes
        return false;
    }
    
    public function syncData()
    {
        // Implementation will be in device-specific classes
        return [];
    }
    
    public function updateStatus($status, $errorMessage = null)
    {
        $this->status = $status;
        if ($status === 'online') {
            $this->last_sync_at = Carbon::now();
            $this->retry_count = 0;
        } elseif ($status === 'error') {
            $this->last_error = $errorMessage;
            $this->retry_count += 1;
        }
        
        $this->calculateNextSync();
        $this->save();
    }
    
    public function calculateNextSync()
    {
        if ($this->real_time_sync) {
            $this->next_sync_at = Carbon::now()->addMinutes(1);
        } else {
            $this->next_sync_at = Carbon::now()->addMinutes($this->sync_frequency);
        }
    }
    
    public function getDeviceDriver()
    {
        $driverClass = '\\App\\Services\\BiometricDevices\\' . $this->device_type . 'Device';
        if (class_exists($driverClass)) {
            return new $driverClass($this);
        }
        return new \App\Services\BiometricDevices\GenericRESTDevice($this);
    }
}
