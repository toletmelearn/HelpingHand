<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BiometricSyncLog extends Model
{
    protected $fillable = [
        'biometric_device_id',
        'sync_type',
        'started_at',
        'completed_at',
        'status',
        'records_processed',
        'records_created',
        'records_updated',
        'duplicates_skipped',
        'errors_count',
        'error_details',
        'sync_metadata',
        'log_message',
    ];
    
    protected $casts = [
        'sync_metadata' => 'array',
        'records_processed' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'duplicates_skipped' => 'integer',
        'errors_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
    
    protected $appends = [
        'duration',
        'success_rate',
    ];
    
    // Relationships
    public function biometricDevice()
    {
        return $this->belongsTo(BiometricDevice::class);
    }
    
    public function biometricRecords()
    {
        return $this->hasMany(TeacherBiometricRecord::class, 'sync_log_id');
    }
    
    // Accessors
    public function getDurationAttribute()
    {
        if ($this->completed_at) {
            return $this->completed_at->diffInSeconds($this->started_at);
        }
        return null;
    }
    
    public function getSuccessRateAttribute()
    {
        if ($this->records_processed > 0) {
            return round((($this->records_created + $this->records_updated) / $this->records_processed) * 100, 2);
        }
        return 0;
    }
    
    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }
    
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
    
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('started_at', '>=', Carbon::now()->subDays($days));
    }
    
    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('biometric_device_id', $deviceId);
    }
    
    // Methods
    public static function startLog($deviceId, $syncType = 'scheduled')
    {
        return self::create([
            'biometric_device_id' => $deviceId,
            'sync_type' => $syncType,
            'started_at' => Carbon::now(),
            'status' => 'in_progress',
        ]);
    }
    
    public function complete($recordsProcessed = 0, $recordsCreated = 0, $recordsUpdated = 0, $duplicatesSkipped = 0, $errorsCount = 0, $errorDetails = null)
    {
        $this->status = $errorsCount > 0 ? 'failed' : 'success';
        $this->records_processed = $recordsProcessed;
        $this->records_created = $recordsCreated;
        $this->records_updated = $recordsUpdated;
        $this->duplicates_skipped = $duplicatesSkipped;
        $this->errors_count = $errorsCount;
        $this->error_details = $errorDetails;
        $this->completed_at = Carbon::now();
        $this->save();
    }
    
    public function addError($message)
    {
        $this->errors_count += 1;
        $currentErrors = $this->error_details ? json_decode($this->error_details, true) : [];
        $currentErrors[] = [
            'timestamp' => Carbon::now()->toISOString(),
            'message' => $message
        ];
        $this->error_details = json_encode($currentErrors);
        $this->save();
    }
    
    public function addMetadata($key, $value)
    {
        $metadata = $this->sync_metadata ?: [];
        $metadata[$key] = $value;
        $this->sync_metadata = $metadata;
        $this->save();
    }
}
