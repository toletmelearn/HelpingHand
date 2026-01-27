<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Services\BiometricSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class WebhookController extends Controller
{
    protected BiometricSyncService $syncService;

    public function __construct(BiometricSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Handle biometric device webhook
     *
     * @param Request $request
     * @param string $webhookToken
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleBiometricWebhook(Request $request, string $webhookToken)
    {
        // Find device by webhook token
        $device = BiometricDevice::where('api_key', $webhookToken)->first();
        
        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook token'
            ], 401);
        }

        if (!$device->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Device is inactive'
            ], 400);
        }

        try {
            // Validate webhook payload
            $validator = Validator::make($request->all(), [
                'event_type' => 'required|string|in:punch,in,out,batch_sync,device_status',
                'timestamp' => 'required|date',
                'data' => 'required|array'
            ]);

            if ($validator->fails()) {
                Log::warning('Invalid webhook payload received', [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'errors' => $validator->errors()->toArray(),
                    'payload' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $eventType = $request->input('event_type');
            $data = $request->input('data');
            $timestamp = Carbon::parse($request->input('timestamp'));

            // Process based on event type
            switch ($eventType) {
                case 'punch':
                    return $this->handlePunchEvent($device, $data, $timestamp);
                
                case 'in':
                    return $this->handleInPunch($device, $data, $timestamp);
                
                case 'out':
                    return $this->handleOutPunch($device, $data, $timestamp);
                
                case 'batch_sync':
                    return $this->handleBatchSync($device, $data, $timestamp);
                
                case 'device_status':
                    return $this->handleDeviceStatus($device, $data, $timestamp);
                
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unsupported event type'
                    ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage(), [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error_id' => uniqid()
            ], 500);
        }
    }

    /**
     * Handle single punch event
     */
    protected function handlePunchEvent(BiometricDevice $device, array $data, Carbon $timestamp)
    {
        $validator = Validator::make($data, [
            'biometric_id' => 'required|string',
            'punch_time' => 'required|date',
            'punch_type' => 'required|in:in,out',
            'verification_type' => 'nullable|string',
            'device_punch_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Queue the punch record for processing
            $this->queuePunchRecord($device, $data, $timestamp);
            
            return response()->json([
                'success' => true,
                'message' => 'Punch record queued for processing',
                'queued_at' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue punch record: ' . $e->getMessage(), [
                'device_id' => $device->id,
                'data' => $data
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process punch record'
            ], 500);
        }
    }

    /**
     * Handle IN punch specifically
     */
    protected function handleInPunch(BiometricDevice $device, array $data, Carbon $timestamp)
    {
        $data['punch_type'] = 'in';
        return $this->handlePunchEvent($device, $data, $timestamp);
    }

    /**
     * Handle OUT punch specifically
     */
    protected function handleOutPunch(BiometricDevice $device, array $data, Carbon $timestamp)
    {
        $data['punch_type'] = 'out';
        return $this->handlePunchEvent($device, $data, $timestamp);
    }

    /**
     * Handle batch synchronization
     */
    protected function handleBatchSync(BiometricDevice $device, array $data, Carbon $timestamp)
    {
        $validator = Validator::make($data, [
            'punch_records' => 'required|array|min:1',
            'batch_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $punchRecords = $data['punch_records'];
            $batchId = $data['batch_id'] ?? uniqid();
            
            // Queue batch for processing
            $this->queueBatchRecords($device, $punchRecords, $batchId, $timestamp);
            
            return response()->json([
                'success' => true,
                'message' => 'Batch records queued for processing',
                'batch_id' => $batchId,
                'record_count' => count($punchRecords),
                'queued_at' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue batch records: ' . $e->getMessage(), [
                'device_id' => $device->id,
                'batch_data' => $data
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process batch records'
            ], 500);
        }
    }

    /**
     * Handle device status update
     */
    protected function handleDeviceStatus(BiometricDevice $device, array $data, Carbon $timestamp)
    {
        try {
            $status = $data['status'] ?? 'unknown';
            $message = $data['message'] ?? '';
            $batteryLevel = $data['battery_level'] ?? null;
            $signalStrength = $data['signal_strength'] ?? null;

            // Update device status
            $device->update([
                'status' => $status,
                'last_sync_at' => $timestamp,
                'last_error' => $status === 'error' ? $message : null
            ]);

            // Log status update
            Log::info('Device status update received', [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'status' => $status,
                'message' => $message,
                'battery_level' => $batteryLevel,
                'signal_strength' => $signalStrength,
                'timestamp' => $timestamp->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device status updated',
                'acknowledged_at' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update device status: ' . $e->getMessage(), [
                'device_id' => $device->id,
                'status_data' => $data
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update device status'
            ], 500);
        }
    }

    /**
     * Queue single punch record for async processing
     */
    protected function queuePunchRecord(BiometricDevice $device, array $punchData, Carbon $timestamp)
    {
        // In a production environment, you'd dispatch a job here
        // For now, we'll process synchronously but log the queuing
        
        Log::info('Punch record queued for processing', [
            'device_id' => $device->id,
            'biometric_id' => $punchData['biometric_id'],
            'punch_type' => $punchData['punch_type'],
            'punch_time' => $punchData['punch_time']
        ]);

        // In production, dispatch job:
        // ProcessPunchRecordJob::dispatch($device, $punchData, $timestamp);
    }

    /**
     * Queue batch records for async processing
     */
    protected function queueBatchRecords(BiometricDevice $device, array $punchRecords, string $batchId, Carbon $timestamp)
    {
        Log::info('Batch records queued for processing', [
            'device_id' => $device->id,
            'batch_id' => $batchId,
            'record_count' => count($punchRecords),
            'timestamp' => $timestamp->toISOString()
        ]);

        // In production, dispatch job:
        // ProcessBatchRecordsJob::dispatch($device, $punchRecords, $batchId, $timestamp);
    }

    /**
     * Health check endpoint for webhook monitoring
     */
    public function healthCheck()
    {
        return response()->json([
            'success' => true,
            'service' => 'Biometric Webhook Service',
            'status' => 'operational',
            'timestamp' => Carbon::now()->toISOString(),
            'version' => '1.0.0'
        ]);
    }

    /**
     * Get webhook configuration info
     */
    public function getConfigInfo(Request $request)
    {
        // This endpoint should be protected in production
        $webhookUrl = url('/api/webhooks/biometric/{webhook_token}');
        
        return response()->json([
            'success' => true,
            'webhook_endpoint' => $webhookUrl,
            'supported_events' => [
                'punch' => 'Single punch record',
                'in' => 'IN punch record',
                'out' => 'OUT punch record',
                'batch_sync' => 'Multiple punch records',
                'device_status' => 'Device status update'
            ],
            'required_headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer {webhook_token}'
            ],
            'timestamp_format' => 'ISO 8601 (YYYY-MM-DDTHH:MM:SSZ)'
        ]);
    }
}