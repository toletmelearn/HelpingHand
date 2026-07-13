<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use App\Services\BulkFeeAssignmentService;
use App\Services\StructureAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transport already runs entirely through its own billing pipeline and
 * never touches FeeStructureItem -- this just confirms the migration
 * removes "Transport Fee" from the academic fee-structure builder without
 * touching anything else (the payment/receipt engine needs no changes,
 * per the Phase 4 research).
 */
class TransportFeeTypeDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccountant(): User
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $role = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $accountant->roles()->attach($role->id);
        return $accountant;
    }

    public function test_transport_fee_type_is_inactive_after_migration()
    {
        $transport = FeeType::where('name', 'Transport Fee')->first();

        $this->assertNotNull($transport);
        $this->assertEquals('inactive', $transport->status);
    }

    public function test_transport_fee_type_is_absent_from_fee_structure_builder()
    {
        $accountant = $this->makeAccountant();

        $response = $this->actingAs($accountant)->get(route('admin.fee-structures.create'));

        $response->assertStatus(200);
        $feeTypes = $response->viewData('feeTypes');
        $this->assertFalse($feeTypes->contains('name', 'Transport Fee'));
    }

    public function test_bulk_fee_assignment_still_skips_a_legacy_transport_item()
    {
        $transport = FeeType::where('name', 'Transport Fee')->first();
        $class = SchoolClass::create(['name' => 'Class T', 'class_order' => 1, 'is_active' => true]);

        $structure = FeeStructure::create([
            'class_name' => $class->name,
            'academic_year' => '2026-2027',
            'frequency' => 'monthly',
            'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id,
            'fee_type_id' => $transport->id,
            'amount' => 500,
            'billing_frequency' => 'monthly',
        ]);

        $student = Student::create([
            'name' => 'Transport Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'school_class_id' => $class->id,
        ]);

        BulkFeeAssignmentService::bulkAssign($structure, [$student->id]);

        $this->assertDatabaseMissing('student_fee_ledgers', [
            'student_id' => $student->id,
            'reference_id' => $structure->id,
        ]);
    }

    public function test_change_fee_structure_still_skips_a_legacy_transport_item()
    {
        $transport = FeeType::where('name', 'Transport Fee')->first();
        $oldClass = SchoolClass::create(['name' => 'Class Old', 'class_order' => 1, 'is_active' => true]);
        $newClass = SchoolClass::create(['name' => 'Class New', 'class_order' => 2, 'is_active' => true]);

        $newStructure = FeeStructure::create([
            'class_name' => $newClass->name,
            'academic_year' => '2026-2027',
            'frequency' => 'monthly',
            'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $newStructure->id,
            'fee_type_id' => $transport->id,
            'amount' => 500,
            'billing_frequency' => 'monthly',
        ]);

        $student = Student::create([
            'name' => 'Transport Kid 2', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'school_class_id' => $newClass->id,
        ]);
        StudentFeeAssignment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $newStructure->id,
            'academic_year' => '2026-2027',
        ]);

        (new StructureAdjustmentService())->changeFeeStructure($student, $newStructure, now()->toDateString());

        $this->assertDatabaseMissing('student_fee_ledgers', [
            'student_id' => $student->id,
            'reference_id' => $newStructure->id,
        ]);
    }
}
