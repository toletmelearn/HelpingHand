<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\TeacherLeave;
use App\Models\TeacherSalary;
use App\Models\User;
use Carbon\Carbon;

class HrSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = Teacher::all();
        $admin = User::first();

        if ($teachers->isEmpty()) {
            echo "No teachers found. Please seed academic/teacher data first.\n";
            return;
        }

        $adminId = $admin ? $admin->id : 1;

        foreach ($teachers as $index => $teacher) {
            // Seed a leave application
            TeacherLeave::create([
                'teacher_id' => $teacher->id,
                'leave_type' => $index % 2 == 0 ? 'casual_leave' : 'medical_leave',
                'start_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
                'days' => 3,
                'reason' => 'Family event / medical recovery request.',
                'status' => $index === 0 ? 'approved' : 'pending',
                'approved_by' => $index === 0 ? $adminId : null,
                'approved_at' => $index === 0 ? Carbon::now() : null,
                'approval_notes' => $index === 0 ? 'Leave approved. Arrange substitute.' : null,
            ]);

            // Seed a payroll salary log
            TeacherSalary::create([
                'teacher_id' => $teacher->id,
                'pay_scale' => 'GRADE-A',
                'basic_salary' => 45000.00,
                'hra' => 5000.00,
                'da' => 2500.00,
                'ta' => 1500.00,
                'medical_allowance' => 1000.00,
                'special_allowance' => 2000.00,
                'gross_salary' => 57000.00,
                'pf_amount' => 4500.00,
                'esi_amount' => 1500.00,
                'tax_deduction' => 3000.00,
                'other_deductions' => 500.00,
                'net_salary' => 47500.00,
                'payment_status' => 'paid',
                'payment_date' => Carbon::now()->subDays(2),
                'payment_method' => 'bank_transfer',
                'reference_number' => 'TXN-REF-' . rand(10000, 99999),
                'paid_by' => $adminId,
                'remarks' => 'Monthly salary credited successfully.',
            ]);
        }

        echo "HR leave applications and payroll data seeded successfully!\n";
    }
}
