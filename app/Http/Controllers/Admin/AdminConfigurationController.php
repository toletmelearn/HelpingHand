<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminConfiguration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdminConfigurationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin']);
    }

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
                'payment_allocation_policy' => ['label' => 'Payment Allocation Policies (JSON Configuration)', 'type' => 'json', 'default' => [
                    'rules' => [
                        ['name' => 'mandatory_first', 'enabled' => true, 'weight' => 1000],
                        ['name' => 'current_session_first', 'enabled' => true, 'weight' => 500],
                        ['name' => 'current_month_first', 'enabled' => true, 'weight' => 200],
                        ['name' => 'priority_based', 'enabled' => true, 'weight' => 100],
                        ['name' => 'oldest_due_first', 'enabled' => true, 'weight' => 50]
                    ],
                    'priority_list' => ['admission', 'tuition', 'late_fine', 'late_fee']
                ]],
                'upi_vpa' => ['label' => 'School UPI VPA (e.g. school@upi)', 'type' => 'string', 'default' => ''],
                'bank_account_name' => ['label' => 'Bank Account Holder Name', 'type' => 'string', 'default' => ''],
                'bank_account_number' => ['label' => 'Bank Account Number', 'type' => 'string', 'default' => ''],
                'bank_ifsc' => ['label' => 'Bank IFSC Code', 'type' => 'string', 'default' => ''],
                'bank_name' => ['label' => 'Bank Name & Branch', 'type' => 'string', 'default' => ''],
                'fine_on_unpaid_balance' => ['label' => 'Apply Late Fine to Partially-Unpaid Balances', 'type' => 'boolean', 'default' => false],
                'minimum_payment_amount' => ['label' => 'Minimum Payment Amount (blank = no minimum)', 'type' => 'string', 'default' => ''],
                'concession_stacking_policy' => ['label' => 'Concession Stacking Policy (highest_single_wins or stack_with_cap)', 'type' => 'string', 'default' => 'highest_single_wins'],
                'concession_stacking_cap_percent' => ['label' => 'Stacking Cap (% of fee head, used only when stack_with_cap)', 'type' => 'string', 'default' => '100'],
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
                'exam_types' => ['label' => 'Exam Types (comma-separated)', 'type' => 'string', 'default' => 'General, Unit Test, Half Yearly, Final'],
                'exam_terms' => ['label' => 'Exam Terms (comma-separated)', 'type' => 'string', 'default' => 'Term 1, Term 2, Final'],
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
        'general' => [
            'label' => 'General School Settings',
            'icon' => 'bi-building',
            'configs' => [
                'school_name' => ['label' => 'School Name', 'type' => 'string', 'default' => 'HELPINGHAND PUBLIC SCHOOL'],
                'school_address' => ['label' => 'School Address', 'type' => 'string', 'default' => '123 Education Street, City Name, State - 123456'],
                'school_phone' => ['label' => 'School Phone', 'type' => 'string', 'default' => '+91-1234567890'],
                'school_email' => ['label' => 'School Email', 'type' => 'string', 'default' => 'info@helpinghand.edu.in'],
                'school_logo' => ['label' => 'School Logo', 'type' => 'file', 'default' => ''],
                'payment_qr' => ['label' => 'Payment QR Code', 'type' => 'file', 'default' => ''],
            ]
        ],
        'payroll' => [
            'label' => 'Payroll & Salaries Settings',
            'icon' => 'bi-wallet2',
            'configs' => [
                'payroll_hra_percent' => ['label' => 'HRA Allowance (%)', 'type' => 'string', 'default' => '10.00'],
                'payroll_da_percent' => ['label' => 'DA Allowance (%)', 'type' => 'string', 'default' => '5.00'],
                'payroll_ta_percent' => ['label' => 'TA Allowance (%)', 'type' => 'string', 'default' => '2.00'],
                'payroll_medical_percent' => ['label' => 'Medical Allowance (%)', 'type' => 'string', 'default' => '1.50'],
                'payroll_pf_percent' => ['label' => 'Provident Fund (PF) Deduction (%)', 'type' => 'string', 'default' => '12.00'],
                'payroll_esi_percent' => ['label' => 'ESI Deduction (%)', 'type' => 'string', 'default' => '0.75'],
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
            'configurations.*.file' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        foreach ($request->configurations as $index => $configData) {
            $config = AdminConfiguration::forModule($configData['module'])
                                        ->forKey($configData['key'])
                                        ->first();
            
            if ($config) {
                $val = $configData['value'] ?? null;
                
                // Handle file upload
                if ($request->hasFile("configurations.{$index}.file")) {
                    // Delete old file if exists
                    $oldVal = $config->getValue();
                    if ($oldVal && Storage::disk('public')->exists($oldVal)) {
                        Storage::disk('public')->delete($oldVal);
                    }
                    
                    $file = $request->file("configurations.{$index}.file");
                    $val = $file->store('logos', 'public');
                }
                
                // If it's a file configuration but no new file was uploaded, preserve the current value
                if ($config->type === 'file' && !$request->hasFile("configurations.{$index}.file")) {
                    continue;
                }

                // If type is json, decode input string to array for model casting
                if ($config->type === 'json' && is_string($val)) {
                    $val = json_decode($val, true);
                }

                $config->update([
                    'value' => $val,
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