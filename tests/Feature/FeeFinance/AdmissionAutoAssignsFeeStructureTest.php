<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AcademicSession;
use App\Models\AdmissionEnquiry;
use App\Models\ClassManagement;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\StudentFeeLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported directly: confirmAdmission() created the Student and stamped
 * admission_session_id, but nothing ever assigned a fee structure or wrote
 * a ledger entry -- a student was only billed if a staff member happened to
 * create/copy a FeeStructure for their class AFTER admission. This proves
 * the real admission route now auto-assigns the class's active structure.
 */
class AdmissionAutoAssignsFeeStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_admission_auto_assigns_the_class_fee_structure()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $session = AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'is_active' => true,
        ]);

        $class = SchoolClass::create(['name' => 'Class 3', 'class_order' => 3, 'is_active' => true]);
        $section = ClassManagement::create(['name' => 'Class 3', 'section' => 'A', 'capacity' => 40, 'is_active' => true]);

        $tuitionType = FeeType::create(['name' => 'Tuition Fee', 'status' => 'active']);
        $feeStructure = FeeStructure::create([
            'class_name' => 'Class 3', 'academic_year' => '2026-2027',
            'frequency' => 'monthly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id, 'fee_type_id' => $tuitionType->id,
            'amount' => 1000, 'billing_frequency' => 'monthly',
            'charge_months' => ['April', 'May', 'June'],
        ]);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'New Kid', 'parent_name' => 'Parent Name',
            'phone' => '9998887766', 'status' => 'selected',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_of_birth' => '2018-01-01',
            'gender' => 'male',
            'category' => 'General',
        ]);

        $response->assertRedirect(route('admin.admissions.index'));

        $student = Student::where('name', 'New Kid')->firstOrFail();

        $this->assertEquals(1, StudentFeeAssignment::where('student_id', $student->id)->count());
        $this->assertGreaterThan(0, StudentFeeLedger::where('student_id', $student->id)->count());

        // The 3 charge_months given -> 3 monthly debits of 1000 each.
        $this->assertEquals(3000, StudentFeeLedger::where('student_id', $student->id)->sum('debit'));
    }

    public function test_confirming_admission_with_no_matching_fee_structure_does_not_fail()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $class = SchoolClass::create(['name' => 'Class 9', 'class_order' => 9, 'is_active' => true]);
        $section = ClassManagement::create(['name' => 'Class 9', 'section' => 'A', 'capacity' => 40, 'is_active' => true]);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'No Fee Setup Kid', 'parent_name' => 'Parent Name',
            'phone' => '9998887755', 'status' => 'selected',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_of_birth' => '2018-01-01',
            'gender' => 'male',
            'category' => 'General',
        ]);

        $response->assertRedirect(route('admin.admissions.index'));
        $this->assertDatabaseHas('students', ['name' => 'No Fee Setup Kid']);
    }
}
