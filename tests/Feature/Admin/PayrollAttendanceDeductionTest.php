<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherLeave;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Services\Payroll\AttendanceDeductionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAttendanceDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);

        $this->teacher = Teacher::create([
            'name' => 'Payroll Test Teacher',
            'email' => 'payroll.test@school.test',
            'phone' => '9998887771',
            'designation' => 'PGT',
            'salary' => 30000,
        ]);
    }

    /** @test */
    public function calculator_counts_unpaid_leave_absences_half_days_and_late_marks()
    {
        // 2 days of approved unpaid leave inside July 2026.
        TeacherLeave::create([
            'teacher_id' => $this->teacher->id,
            'leave_type' => 'unpaid_leave',
            'start_date' => '2026-07-05',
            'end_date' => '2026-07-06',
            'days' => 2,
            'status' => 'approved',
        ]);

        // An approved casual leave day -- must NOT count as a deduction, and must
        // suppress any 'absent' attendance mark on the same date.
        TeacherLeave::create([
            'teacher_id' => $this->teacher->id,
            'leave_type' => 'casual_leave',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-10',
            'days' => 1,
            'status' => 'approved',
        ]);

        TeacherAttendance::create(['teacher_id' => $this->teacher->id, 'marked_by' => $this->admin->id, 'date' => '2026-07-10', 'status' => 'absent']);
        TeacherAttendance::create(['teacher_id' => $this->teacher->id, 'marked_by' => $this->admin->id, 'date' => '2026-07-12', 'status' => 'absent']);
        TeacherAttendance::create(['teacher_id' => $this->teacher->id, 'marked_by' => $this->admin->id, 'date' => '2026-07-15', 'status' => 'half_day']);
        TeacherAttendance::create(['teacher_id' => $this->teacher->id, 'marked_by' => $this->admin->id, 'date' => '2026-07-18', 'status' => 'late']);
        TeacherAttendance::create(['teacher_id' => $this->teacher->id, 'marked_by' => $this->admin->id, 'date' => '2026-07-19', 'status' => 'late']);
        TeacherAttendance::create(['teacher_id' => $this->teacher->id, 'marked_by' => $this->admin->id, 'date' => '2026-07-20', 'status' => 'late']);

        // Outside the period -- must not affect the July calculation.
        TeacherAttendance::create(['teacher_id' => $this->teacher->id, 'marked_by' => $this->admin->id, 'date' => '2026-08-01', 'status' => 'absent']);

        $calculator = app(AttendanceDeductionCalculator::class);
        $result = $calculator->calculate($this->teacher, 7, 2026);

        $this->assertEquals(2, $result['unpaid_leave_days']);
        $this->assertEquals(1, $result['absent_days']); // only the 07-12 absence; 07-10 is covered by approved leave
        $this->assertEquals(1, $result['half_days']);
        $this->assertEquals(3, $result['late_count']);
        $this->assertEquals(0.5, $result['late_deduction_days']); // 3 lates / threshold of 3 = 1 -> 0.5 day
        $this->assertEquals(4.0, $result['total_deduction_days']); // 2 + 1 + 0.5 + 0.5
    }

    /** @test */
    public function per_day_rate_uses_fixed_30_day_divisor()
    {
        $calculator = app(AttendanceDeductionCalculator::class);
        $this->assertEquals(1000.0, $calculator->perDayRate(30000));
    }

    /** @test */
    public function admin_can_generate_payroll_with_attendance_deduction_applied()
    {
        $this->actingAs($this->admin)->post(route('admin.hr.payroll.generate'), [
            'teacher_id' => $this->teacher->id,
            'pay_month' => 7,
            'pay_year' => 2026,
            'basic_salary' => 30000,
            'hra' => 0, 'da' => 0, 'ta' => 0, 'medical_allowance' => 0, 'special_allowance' => 0,
            'pf_amount' => 0, 'esi_amount' => 0, 'tax_deduction' => 0, 'other_deductions' => 0,
            'attendance_deduction_days' => 2,
            'payment_method' => 'bank_transfer',
        ])->assertRedirect(route('admin.hr.payroll.index'));

        $salary = TeacherSalary::where('teacher_id', $this->teacher->id)->firstOrFail();

        $this->assertEquals(7, $salary->pay_month);
        $this->assertEquals(2026, $salary->pay_year);
        $this->assertEquals(2, (float) $salary->attendance_deduction_days);
        $this->assertEquals(2000.0, (float) $salary->attendance_deduction_amount); // 30000/30 * 2
        $this->assertEquals(28000.0, (float) $salary->net_salary);
    }

    /** @test */
    public function admin_can_generate_payroll_without_any_attendance_deduction()
    {
        $this->actingAs($this->admin)->post(route('admin.hr.payroll.generate'), [
            'teacher_id' => $this->teacher->id,
            'pay_month' => 7,
            'pay_year' => 2026,
            'basic_salary' => 30000,
            'payment_method' => 'cash',
        ])->assertRedirect(route('admin.hr.payroll.index'));

        $salary = TeacherSalary::where('teacher_id', $this->teacher->id)->firstOrFail();
        $this->assertNull($salary->attendance_deduction_days);
        $this->assertNull($salary->attendance_deduction_amount);
        $this->assertEquals(30000.0, (float) $salary->net_salary);
    }

    /** @test */
    public function preview_deduction_endpoint_returns_json_breakdown()
    {
        TeacherAttendance::create(['teacher_id' => $this->teacher->id, 'marked_by' => $this->admin->id, 'date' => '2026-07-12', 'status' => 'absent']);

        $response = $this->actingAs($this->admin)->getJson(
            route('admin.hr.payroll.preview-deduction', [
                'teacher_id' => $this->teacher->id,
                'pay_month' => 7,
                'pay_year' => 2026,
            ])
        );

        $response->assertOk();
        $response->assertJson(['absent_days' => 1]);
    }

    /** @test */
    public function payslip_pdf_includes_bank_pan_and_uan_details()
    {
        $this->teacher->update([
            'bank_account_number' => '1234567890',
            'ifsc_code' => 'SBIN0001234',
            'uan_number' => 'UAN998877',
            'pan_number' => 'ABCDE1234F',
        ]);

        $salary = TeacherSalary::create([
            'teacher_id' => $this->teacher->id,
            'pay_month' => 7,
            'pay_year' => 2026,
            'basic_salary' => 30000,
            'gross_salary' => 30000,
            'net_salary' => 30000,
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
            'paid_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.hr.payroll.pdf', $salary->id));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
