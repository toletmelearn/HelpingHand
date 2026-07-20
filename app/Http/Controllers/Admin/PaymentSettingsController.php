<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function showPaymentSettings()
    {
        // Get current payment settings
        $settings = [
            'school_upi_id' => env('SCHOOL_UPI_ID', ''),
            'qr_image_path' => env('SCHOOL_QR_PATH', '')
        ];

        return view('admin.settings.payment', compact('settings'));
    }

    public function updatePaymentSettings(Request $request)
    {
        $request->validate([
            'school_upi_id' => 'nullable|string|max:255',
            'qr_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        // Handle QR code image upload
        $qrImagePath = env('SCHOOL_QR_PATH', '');
        
        if ($request->hasFile('qr_image')) {
            // Delete old QR image if exists
            if (env('SCHOOL_QR_PATH') && Storage::exists(env('SCHOOL_QR_PATH'))) {
                Storage::delete(env('SCHOOL_QR_PATH'));
            }
            
            $qrImage = $request->file('qr_image');
            $qrImagePath = $qrImage->store('qr_codes', 'public');
        }

        // Update .env file with new settings
        $this->updateEnvFile([
            'SCHOOL_UPI_ID' => $request->school_upi_id ?? '',
            'SCHOOL_QR_PATH' => $qrImagePath
        ]);

        return redirect()->back()->with('success', 'Payment settings updated successfully!');
    }

    private function updateEnvFile(array $values)
    {
        $envFile = base_path('.env');
        $contentArray = collect(file($envFile));

        $changedKeys = $contentArray->map(function ($line) use ($values) {
            if (trim($line) === '') return $line;

            $matches = explode('=', $line);
            $key = trim($matches[0]);
            $value = isset($matches[1]) ? trim($matches[1]) : '';

            if (array_key_exists($key, $values)) {
                $newLine = $key . '=' . $values[$key] . "\n";
                return $newLine;
            }

            return $line;
        });

        $content = $changedKeys->join('');

        \Illuminate\Support\Facades\File::put($envFile, $content);
    }
}