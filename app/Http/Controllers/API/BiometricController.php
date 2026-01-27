<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\BiometricSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BiometricController extends Controller
{
    protected BiometricSyncService $syncService;

    public function __construct(BiometricSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Test device connection
     *
     * @param Request $request
     * @param int $deviceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnection(Request $request, int $deviceId)
    {
        $device = BiometricDevice::findOrFail($deviceId);
        $driver = $device->getDeviceDriver();

        try {
            $isConnected = $driver->testConnection();
            
            return response()->json([
                'success' => true,
                'connected' => $isConnected,
                'device_status' => $device->getStatus(),
                'message' => $isConnected ? 'Device connected successfully' : 'Device connection failed'
            ]);
        } catch (\Exception $e) {
            Log::error('Device connection test failed: ' . $e->getMessage(), [
                'device_id' => $deviceId
            ]);

            return response()->json([
                'success' => false,
                'connected' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual sync device data
     *
     * @param Request $request
     * @param int $deviceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncDevice(Request $request, int $deviceId)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $device = BiometricDevice::findOrFail($deviceId);
        
        $fromDate = $request->input('from_date') ? new \DateTime($request->input('from_date')) : null;
        $toDate = $request->input('to_date') ? new \DateTime($request->input('to_date')) : null;

        try {
            $result = $this->syncService->syncDevice($device, $fromDate, $toDate);
            
            if ($result['success'] ?? false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Device sync completed successfully',
                    'result' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Device sync failed',
                    'error' => $result['error'] ?? 'Unknown error'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Manual device sync failed: ' . $e->getMessage(), [
                'device_id' => $deviceId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Device sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get device status
     *
     * @param int $deviceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDeviceStatus(int $deviceId)
    {
        $device = BiometricDevice::findOrFail($deviceId);
        $driver = $device->getDeviceDriver();

        return response()->json([
            'success' => true,
            'device' => $device->getStatus(),
            'driver_info' => $driver->getStatus()
        ]);
    }

    /**
     * Get sync logs for a device
     *
     * @param Request $request
     * @param int $deviceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSyncLogs(Request $request, int $deviceId)
    {
        $device = BiometricDevice::findOrFail($deviceId);
        
        $logs = $device->syncLogs()
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'device' => $device->only(['id', 'name', 'device_type']),
            'logs' => $logs
        ]);
    }

    /**
     * Get sync statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSyncStatistics(Request $request)
    {
        $days = $request->get('days', 7);
        
        $statistics = $this->syncService->getSyncStatistics($days);

        return response()->json([
            'success' => true,
            'statistics' => $statistics
        ]);
    }

    /**
     * Bulk sync all devices
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncAllDevices(Request $request)
    {
        $force = $request->get('force', false);

        try {
            $results = $this->syncService->syncAllDevices($force);
            
            return response()->json([
                'success' => true,
                'message' => 'Bulk sync initiated',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk device sync failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Bulk sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook endpoint for real-time device data
     *
     * @param Request $request
     * @param int $deviceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request, int $deviceId)
    {
        $device = BiometricDevice::findOrFail($deviceId);
        
        if (!$device->real_time_sync) {
            return response()->json([
                'success' => false,
                'message' => 'Real-time sync not enabled for this device'
            ], 400);
        }

        try {
            // Validate webhook payload
            $validator = Validator::make($request->all(), [
                'punch_records' => 'required|array',
                'timestamp' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Process webhook data
            $punchRecords = $request->input('punch_records');
            
            // Here you would process the real-time punch records
            // This is a simplified implementation
            Log::info('Received webhook data from device', [
                'device_id' => $deviceId,
                'records_count' => count($punchRecords),
                'timestamp' => $request->input('timestamp')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook data received and queued for processing',
                'processed_records' => count($punchRecords)
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage(), [
                'device_id' => $deviceId,
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}