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
        // Implementation pending
        return redirect()->route('admin.biometric-devices.index');
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
        // Implementation pending
        return redirect()->route('admin.biometric-devices.index');
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
