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

    /** Phase 2B-i: confirmAdmission() now resolves a real Section via class_sections, not a bare ClassManagement id -- see AdminAdmissionController::validSectionIdsForClass(). */
    private function createClassWithSection(string $className, int $classOrder, int $capacity = 40): array
    {
        $class = SchoolClass::create(['name' => $className, 'class_order' => $classOrder, 'is_active' => true, 'capacity' => $capacity]);
        $classManagement = ClassManagement::create(['name' => $className, 'section' => '', 'capacity' => $capacity, 'is_active' => true]);
        \Illuminate\Support\Facades\DB::table('legacy_class_map')->insert([
            'class_management_id' => $classManagement->id,
            'school_class_id' => $class->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $section = \App\Models\Section::create(['name' => 'A', 'capacity' => $capacity]);
        \Illuminate\Support\Facades\DB::table('class_sections')->insert([
            'class_management_id' => $classManagement->id,
            'section_id' => $section->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$class, $section];
    }

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

        [$class, $section] = $this->createClassWithSection('Class 3', 3);

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

        [$class, $section] = $this->createClassWithSection('Class 9', 9);

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
