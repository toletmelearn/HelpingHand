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
 * Reported directly: an admin added a "Security Deposit" fee item to an
 * already-existing fee structure via Edit, but no already-assigned student
 * was ever charged for it -- FeeStructureController::update() only rewrote
 * the FeeStructureItem rows, it never generated any ledger charges. This
 * proves that editing a structure now backfills eligible already-assigned
 * students for any item they haven't been charged yet, while leaving
 * students who already have that item untouched (no duplicate charge).
 */
class EditingStructureBackfillsAssignedStudentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_one_time_fee_via_edit_bills_already_assigned_students()
    {
        $admin = User::factory()->create(['role' => 'super-admin']);
        $role = Role::firstOrCreate(['name' => 'super-admin'], ['display_name' => 'Super Admin']);
        $admin->roles()->attach($role->id);

        $class = SchoolClass::create(['name' => 'Nursery', 'class_order' => 1, 'is_active' => true]);
        $tuitionType = FeeType::create(['name' => 'Tuition Fee', 'status' => 'active']);
        $depositType = FeeType::create(['name' => 'Security Deposit', 'status' => 'active']);

        $structure = FeeStructure::create([
            'class_name' => 'Nursery', 'academic_year' => '2025-2026',
            'frequency' => 'monthly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $tuitionType->id,
            'amount' => 3000, 'billing_frequency' => 'monthly',
            'charge_months' => ['January'],
        ]);

        $student = Student::create([
            'name' => 'Backfill Test Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2020-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887766', 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
        ]);

        \App\Services\BulkFeeAssignmentService::bulkAssign($structure, [$student->id]);
        $this->assertEquals(3000, StudentFeeLedger::where('student_id', $student->id)->sum('debit'));

        // Admin edits the structure to add a Security Deposit -- no admission
        // fee/session data involved, uses the new "one_time" option.
        $response = $this->actingAs($admin)->put(route('admin.fee-structures.update', $structure->id), [
            'class_name' => 'Nursery',
            'academic_year' => '2025-2026',
            'frequency' => 'monthly',
            'status' => 'active',
            'fee_heads' => [
                $tuitionType->id => [
                    'fee_type_id' => $tuitionType->id,
                    'amount' => 3000,
                    'billing_frequency' => 'monthly',
                    'charge_months_raw' => 'January',
                ],
                $depositType->id => [
                    'fee_type_id' => $depositType->id,
                    'amount' => 1500,
                    'billing_frequency' => 'one_time',
                    'charge_months_raw' => 'OneTime',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.fee-structures.index'));

        // The already-assigned student is now billed the new deposit exactly once.
        $this->assertEquals(1500, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $depositType->id)->sum('debit'));
        $this->assertEquals(1, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $depositType->id)->count());

        // Tuition is untouched (student already had it -- no duplicate).
        $this->assertEquals(3000, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $tuitionType->id)->sum('debit'));
    }
}
