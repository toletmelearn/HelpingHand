<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use Illuminate\Http\Request;

class BiometricDeviceController extends Controller
{
    public function index()
    {
        $devices = BiometricDevice::all();
        return view('admin.biometric-devices.index', compact('devices'));
    }
    
    public function create()
    {
        return view('admin.biometric-devices.create');
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'device_type' => 'nullable|in:zkteco,essl,mantra,generic_rest',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,maintenance',
            'description' => 'nullable|string',
            'timeout' => 'nullable|integer|min:1|max:300',
        ]);
        
        $request->merge([
            'port' => $request->port ?: 4370,
            'device_type' => $request->device_type ?: 'zkteco',
            'status' => $request->status ?: 'active',
            'timeout' => $request->timeout ?: 30,
        ]);
        
        BiometricDevice::create($request->all());
        
        return redirect()->route('admin.biometric-devices.index')
            ->with('success', 'Biometric device created successfully.');
    }
    
    public function show(BiometricDevice $biometricDevice)
    {
        return view('admin.biometric-devices.show', compact('biometricDevice'));
    }
    
    public function edit(BiometricDevice $biometricDevice)
    {
        return view('admin.biometric-devices.edit', compact('biometricDevice'));
    }
    
    public function update(Request $request, BiometricDevice $biometricDevice)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'device_type' => 'nullable|in:zkteco,essl,mantra,generic_rest',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,maintenance',
            'description' => 'nullable|string',
            'timeout' => 'nullable|integer|min:1|max:300',
        ]);
        
        $request->merge([
            'port' => $request->port ?: 4370,
            'device_type' => $request->device_type ?: 'zkteco',
            'status' => $request->status ?: 'active',
            'timeout' => $request->timeout ?: 30,
        ]);
        
        $biometricDevice->update($request->all());
        
        return redirect()->route('admin.biometric-devices.index')
            ->with('success', 'Biometric device updated successfully.');
    }
    
    public function destroy(BiometricDevice $biometricDevice)
    {
        $biometricDevice->delete();
        return redirect()->route('admin.biometric-devices.index');
    }
    
    public function testConnection(BiometricDevice $device)
    {
        // Implementation pending
        return response()->json(['success' => true]);
    }
    
    public function sync(BiometricDevice $device)
    {
        // Implementation pending
        return response()->json(['success' => true]);
    }
    
    public function syncLogs(BiometricDevice $device)
    {
        $logs = $device->syncLogs()->latest()->paginate(20);
        return view('admin.biometric-devices.logs', compact('device', 'logs'));
    }
}
