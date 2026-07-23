<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported directly: an admin deleted an already-billed fee structure, then
 * created a replacement with a different Tuition amount. Deleting never
 * reverses already-generated ledger charges, and creating a brand-new
 * structure for the same class+year blindly bulk-billed every already-
 * assigned student again -- doubling their Tuition (the one-time-fee
 * lifetime dedup added earlier this session protected Admission
 * Fee/Security Deposit from this, but ordinary recurring items had no
 * equivalent protection). This proves: (1) a structure that has already
 * generated charges can no longer be deleted at all, and (2) creating a
 * second structure for a class/year that already has billed students
 * routes them through StructureAdjustmentService::changeFeeStructure()
 * instead of duplicating -- old future dues drop, new amount applies once.
 */
class RecreatingStructureDoesNotDoubleBillTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'super-admin']);
        $role = Role::firstOrCreate(['name' => 'super-admin'], ['display_name' => 'Super Admin']);
        $admin->roles()->attach($role->id);
        return $admin;
    }

    public function test_deleting_a_structure_with_existing_charges_is_blocked()
    {
        $admin = $this->actingAsSuperAdmin();
        $class = SchoolClass::create(['name' => 'Nursery', 'class_order' => 1, 'is_active' => true]);
        $tuition = FeeType::create(['name' => 'Tuition', 'status' => 'active']);

        $structure = FeeStructure::create([
            'class_name' => 'Nursery', 'academic_year' => '2025-2026',
            'frequency' => 'monthly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $tuition->id,
            'amount' => 2479, 'billing_frequency' => 'monthly',
            'charge_months' => ['April'],
        ]);

        $student = Student::create([
            'name' => 'Delete Test Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887711', 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
        ]);

        \App\Services\BulkFeeAssignmentService::bulkAssign($structure, [$student->id]);

        $response = $this->actingAs($admin)->delete(route('admin.fee-structures.destroy', $structure->id));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertNotNull(FeeStructure::find($structure->id), 'Structure should NOT have been deleted.');
    }

    public function test_creating_a_replacement_structure_for_an_already_billed_class_does_not_duplicate_tuition()
    {
        $admin = $this->actingAsSuperAdmin();
        $class = SchoolClass::create(['name' => 'Nursery', 'class_order' => 1, 'is_active' => true]);
        $tuition = FeeType::firstOrCreate(['name' => 'Tuition'], ['status' => 'active']);

        $oldStructure = FeeStructure::create([
            'class_name' => 'Nursery', 'academic_year' => '2025-2026',
            'frequency' => 'monthly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $oldStructure->id, 'fee_type_id' => $tuition->id,
            'amount' => 2479, 'billing_frequency' => 'monthly',
            'charge_months' => ['January', 'February', 'March'],
        ]);

        $student = Student::create([
            'name' => 'Recreate Test Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887722', 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
        ]);

        \App\Services\BulkFeeAssignmentService::bulkAssign($oldStructure, [$student->id]);
        $this->assertEquals(7437, StudentFeeLedger::where('student_id', $student->id)->sum('debit'));

        // Simulate "delete then recreate" by soft-deleting the old structure
        // directly (bypassing the now-blocked destroy() endpoint, to isolate
        // testing the autoAssignToStudents() fix specifically) and creating
        // a fresh one via the real store() endpoint with a DIFFERENT amount.
        $oldStructure->delete();

        $response = $this->actingAs($admin)->post(route('admin.fee-structures.store'), [
            'class_name' => 'Nursery',
            'academic_year' => '2025-2026',
            'frequency' => 'quarterly',
            'fee_type_id' => [$tuition->id],
            'amount' => [10067],
            'billing_frequency' => ['quarterly'],
            'charge_months_raw' => ['Q1,Q2,Q3,Q4'],
        ]);
        $response->assertRedirect(route('admin.fee-structures.index'));

        $ledger = StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $tuition->id)->get();

        // Old future-dated unpaid debits from the deleted structure are
        // dropped, replaced by the new quarterly billing -- NOT layered on
        // top of the old monthly charges. changeFeeStructure() only bills
        // periods still in the future relative to today (same rule already
        // used by Student Promotion), so already-elapsed quarters are
        // correctly skipped rather than retroactively billed -- the point
        // of this test is that NONE of the old Rs.2,479 charges survive.
        $this->assertGreaterThan(0, $ledger->where('debit', 10067)->count(), 'At least one new quarterly charge must exist.');
        $this->assertEquals(0, $ledger->where('debit', 2479)->count(), 'Old monthly charges must be dropped, not left duplicated.');
    }
}
