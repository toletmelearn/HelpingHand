<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminConfiguration;
use Illuminate\Support\Facades\Auth;

class AdminConfigurationController extends Controller
{
    protected $configGroups = [
        'biometric' => [
            'label' => 'Biometric System',
            'icon' => 'bi-fingerprint',
            'configs' => [
                'enable_biometric' => ['label' => 'Enable Biometric System', 'type' => 'boolean', 'default' => true],
                'auto_sync_enabled' => ['label' => 'Auto Sync Records', 'type' => 'boolean', 'default' => true],
                'late_arrival_threshold' => ['label' => 'Late Arrival Threshold (minutes)', 'type' => 'integer', 'default' => 15],
                'early_departure_threshold' => ['label' => 'Early Departure Threshold (minutes)', 'type' => 'integer', 'default' => 10],
            ]
        ],
        'fee' => [
            'label' => 'Fee Management',
            'icon' => 'bi-currency-dollar',
            'configs' => [
                'auto_calculate_fees' => ['label' => 'Auto Calculate Fees', 'type' => 'boolean', 'default' => true],
                'enable_partial_payment' => ['label' => 'Enable Partial Payment', 'type' => 'boolean', 'default' => true],
                'send_due_reminders' => ['label' => 'Send Due Reminders', 'type' => 'boolean', 'default' => true],
                'reminder_days_before' => ['label' => 'Reminder Days Before Due', 'type' => 'integer', 'default' => 3],
            ]
        ],
        'exam' => [
            'label' => 'Examination System',
            'icon' => 'bi-clipboard-check',
            'configs' => [
                'enable_exam_module' => ['label' => 'Enable Exam Module', 'type' => 'boolean', 'default' => true],
                'auto_result_calculation' => ['label' => 'Auto Result Calculation', 'type' => 'boolean', 'default' => true],
                'allow_result_editing' => ['label' => 'Allow Result Editing', 'type' => 'boolean', 'default' => false],
                'result_lock_days' => ['label' => 'Result Lock After (days)', 'type' => 'integer', 'default' => 7],
            ]
        ],
        'attendance' => [
            'label' => 'Attendance System',
            'icon' => 'bi-calendar-check',
            'configs' => [
                'enable_attendance' => ['label' => 'Enable Attendance System', 'type' => 'boolean', 'default' => true],
                'auto_mark_absent' => ['label' => 'Auto Mark Absent', 'type' => 'boolean', 'default' => true],
                'send_low_attendance_alert' => ['label' => 'Send Low Attendance Alerts', 'type' => 'boolean', 'default' => true],
                'attendance_threshold' => ['label' => 'Low Attendance Threshold (%)', 'type' => 'integer', 'default' => 75],
            ]
        ],
        'library' => [
            'label' => 'Library Management',
            'icon' => 'bi-book',
            'configs' => [
                'enable_library' => ['label' => 'Enable Library System', 'type' => 'boolean', 'default' => true],
                'auto_fine_calculation' => ['label' => 'Auto Fine Calculation', 'type' => 'boolean', 'default' => true],
                'max_books_per_student' => ['label' => 'Max Books Per Student', 'type' => 'integer', 'default' => 3],
                'return_days_limit' => ['label' => 'Return Days Limit', 'type' => 'integer', 'default' => 15],
            ]
        ],
        'notification' => [
            'label' => 'Notifications',
            'icon' => 'bi-bell',
            'configs' => [
                'enable_notifications' => ['label' => 'Enable All Notifications', 'type' => 'boolean', 'default' => true],
                'email_notifications' => ['label' => 'Email Notifications', 'type' => 'boolean', 'default' => true],
                'sms_notifications' => ['label' => 'SMS Notifications', 'type' => 'boolean', 'default' => false],
                'push_notifications' => ['label' => 'Push Notifications', 'type' => 'boolean', 'default' => true],
            ]
        ],
    ];

    public function index()
    {
        $modules = [];
        
        foreach ($this->configGroups as $moduleKey => $module) {
            $configs = AdminConfiguration::forModule($moduleKey)->get();
            $moduleConfigs = [];
            
            // Ensure all expected configs exist
            foreach ($module['configs'] as $key => $configDef) {
                $existing = $configs->firstWhere('key', $key);
                if (!$existing) {
                    // Create default config
                    $existing = AdminConfiguration::create([
                        'module' => $moduleKey,
                        'key' => $key,
                        'value' => $configDef['default'],
                        'type' => $configDef['type'],
                        'label' => $configDef['label'],
                        'is_active' => true,
                        'updated_by' => Auth::id(),
                    ]);
                }
                $moduleConfigs[$key] = $existing;
            }
            
            $modules[$moduleKey] = [
                'label' => $module['label'],
                'icon' => $module['icon'],
                'configs' => $moduleConfigs
            ];
        }
        
        return view('admin.configurations.index', compact('modules'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'configurations' => 'required|array',
            'configurations.*.module' => 'required|string',
            'configurations.*.key' => 'required|string',
            'configurations.*.value' => 'nullable',
        ]);

        foreach ($request->configurations as $configData) {
            $config = AdminConfiguration::forModule($configData['module'])
                                        ->forKey($configData['key'])
                                        ->first();
            
            if ($config) {
                $config->update([
                    'value' => $configData['value'],
                    'updated_by' => Auth::id(),
                ]);
            }
        }

        return redirect()->back()->with('success', 'Configuration updated successfully.');
    }

    public function toggle(Request $request, $id)
    {
        $config = AdminConfiguration::findOrFail($id);
        $config->update([
            'is_active' => !$config->is_active,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $config->is_active,
            'message' => $config->is_active ? 'Configuration enabled' : 'Configuration disabled'
        ]);
    }

    public function resetToDefaults(Request $request)
    {
        $module = $request->get('module');
        
        if (!isset($this->configGroups[$module])) {
            return redirect()->back()->with('error', 'Invalid module specified.');
        }

        $configs = AdminConfiguration::forModule($module)->get();
        
        foreach ($configs as $config) {
            if (isset($this->configGroups[$module]['configs'][$config->key]['default'])) {
                $config->update([
                    'value' => $this->configGroups[$module]['configs'][$config->key]['default'],
                    'updated_by' => Auth::id(),
                ]);
            }
        }

        return redirect()->back()->with('success', 'Configuration reset to defaults successfully.');
    }
}