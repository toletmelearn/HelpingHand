<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AcademicSession;
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
 * Reported directly: StudentPromotionController::store() correctly synced
 * class_id/school_class_id/class but never touched fee assignments --
 * StructureAdjustmentService::changeFeeStructure() already existed, fully
 * tested, but had zero production callers. This proves the real promotion
 * route now re-assigns the destination class's fee structure.
 */
class PromotionReassignsFeeStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotion_drops_old_future_dues_and_assigns_new_class_structure()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $session = AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'is_active' => true,
        ]);

        $classA = SchoolClass::create(['name' => 'Class 4', 'class_order' => 4, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class 5', 'class_order' => 5, 'is_active' => true]);

        $tuitionType = FeeType::create(['name' => 'Tuition Fee', 'status' => 'active']);

        $oldStructure = FeeStructure::create([
            'class_name' => 'Class 4', 'academic_year' => '2026-2027',
            'frequency' => 'monthly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $oldStructure->id, 'fee_type_id' => $tuitionType->id,
            'amount' => 800, 'billing_frequency' => 'monthly',
            'charge_months' => ['January', 'February', 'March'],
        ]);

        $newStructure = FeeStructure::create([
            'class_name' => 'Class 5', 'academic_year' => '2026-2027',
            'frequency' => 'monthly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $newStructure->id, 'fee_type_id' => $tuitionType->id,
            'amount' => 1200, 'billing_frequency' => 'monthly',
            'charge_months' => ['January', 'February', 'March'],
        ]);

        $student = Student::create([
            'name' => 'Promotion Test Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2014-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887744', 'address' => 'Somewhere',
            'class_id' => $classA->id, 'school_class_id' => $classA->id, 'class' => $classA->name,
        ]);

        \App\Services\BulkFeeAssignmentService::bulkAssign($oldStructure, [$student->id]);
        $this->assertEquals(2400, StudentFeeLedger::where('student_id', $student->id)->sum('debit'));

        $response = $this->actingAs($admin)->post(route('admin.student-promotions.store'), [
            'academic_session_id' => $session->id,
            'from_class' => $classA->id,
            'to_class' => $classB->id,
            'students' => [$student->id],
        ]);

        $response->assertRedirect(route('admin.student-promotions.index'));

        $student->refresh();
        $this->assertEquals($classB->id, $student->class_id);
        $this->assertEquals('Class 5', $student->class);

        // Old assignment replaced by the new one.
        $this->assertEquals(1, StudentFeeAssignment::where('student_id', $student->id)->count());
        $this->assertEquals($newStructure->id, StudentFeeAssignment::where('student_id', $student->id)->first()->fee_structure_id);

        // Old class's un-elapsed debits (dated in the future relative to
        // "today" in this test's fixed dates) are gone, replaced by the new
        // class's amount.
        $ledger = StudentFeeLedger::where('student_id', $student->id)->get();
        $this->assertTrue($ledger->every(fn ($row) => (float) $row->debit !== 800.0 || $row->debit == 0));
    }

    public function test_promotion_with_no_matching_fee_structure_does_not_fail()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $session = AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'is_active' => true,
        ]);

        $classA = SchoolClass::create(['name' => 'Class 6', 'class_order' => 6, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class 7', 'class_order' => 7, 'is_active' => true]);

        $student = Student::create([
            'name' => 'No Structure Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887733', 'address' => 'Somewhere',
            'class_id' => $classA->id, 'school_class_id' => $classA->id, 'class' => $classA->name,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.student-promotions.store'), [
            'academic_session_id' => $session->id,
            'from_class' => $classA->id,
            'to_class' => $classB->id,
            'students' => [$student->id],
        ]);

        $response->assertRedirect(route('admin.student-promotions.index'));
        $student->refresh();
        $this->assertEquals($classB->id, $student->class_id);
    }
}
