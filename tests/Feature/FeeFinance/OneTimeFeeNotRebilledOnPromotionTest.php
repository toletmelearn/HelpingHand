<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AcademicSession;
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
 * Reported directly: Admission Fee ('session_wise_admission') and Security
 * Deposit ('one_time') were being re-billed every time a student's fee
 * structure was (re-)generated -- including on promotion, via
 * StructureAdjustmentService::changeFeeStructure(), which posted a debit
 * for every fee-structure item with zero regard for billing_frequency.
 * This proves a newly-admitted student is charged each one-time fee
 * exactly once, and that promoting them into a class whose fee structure
 * repeats the same fee types does not charge either again, while the
 * recurring Tuition Fee keeps accruing normally.
 */
class OneTimeFeeNotRebilledOnPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_fee_and_security_deposit_are_billed_once_and_never_repeated_on_promotion()
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
        $admissionType = FeeType::create(['name' => 'Admission Fee', 'status' => 'active']);
        $depositType = FeeType::create(['name' => 'Security Deposit', 'status' => 'active']);

        $oldStructure = FeeStructure::create([
            'class_name' => 'Class 4', 'academic_year' => '2026-2027',
            'frequency' => 'monthly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $oldStructure->id, 'fee_type_id' => $tuitionType->id,
            'amount' => 800, 'billing_frequency' => 'monthly',
            'charge_months' => ['January', 'February', 'March'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $oldStructure->id, 'fee_type_id' => $admissionType->id,
            'amount' => 5000, 'billing_frequency' => 'session_wise_admission',
            'charge_months' => ['April'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $oldStructure->id, 'fee_type_id' => $depositType->id,
            'amount' => 3000, 'billing_frequency' => 'one_time',
            'charge_months' => ['OneTime'],
        ]);

        // Destination class's structure repeats the same one-time fee types
        // -- this is the realistic case (a shared "Admission Fee"/"Security
        // Deposit" fee type reused across every class's structure).
        $newStructure = FeeStructure::create([
            'class_name' => 'Class 5', 'academic_year' => '2026-2027',
            'frequency' => 'monthly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $newStructure->id, 'fee_type_id' => $tuitionType->id,
            'amount' => 1200, 'billing_frequency' => 'monthly',
            'charge_months' => ['January', 'February', 'March'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $newStructure->id, 'fee_type_id' => $admissionType->id,
            'amount' => 5000, 'billing_frequency' => 'session_wise_admission',
            'charge_months' => ['April'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $newStructure->id, 'fee_type_id' => $depositType->id,
            'amount' => 3000, 'billing_frequency' => 'one_time',
            'charge_months' => ['OneTime'],
        ]);

        $student = Student::create([
            'name' => 'One Time Fee Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2014-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887755', 'address' => 'Somewhere',
            'class_id' => $classA->id, 'school_class_id' => $classA->id, 'class' => $classA->name,
            'admission_session_id' => $session->id,
        ]);

        \App\Services\BulkFeeAssignmentService::bulkAssign($oldStructure, [$student->id]);

        // Each one-time fee charged exactly once on initial assignment.
        $this->assertEquals(5000, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $admissionType->id)->sum('debit'));
        $this->assertEquals(3000, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $depositType->id)->sum('debit'));
        $this->assertEquals(2400, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $tuitionType->id)->sum('debit'));

        $response = $this->actingAs($admin)->post(route('admin.student-promotions.store'), [
            'academic_session_id' => $session->id,
            'from_class' => $classA->id,
            'to_class' => $classB->id,
            'students' => [$student->id],
        ]);
        $response->assertRedirect(route('admin.student-promotions.index'));

        // Admission Fee and Security Deposit must NOT be billed again --
        // still exactly one debit each, for the original amount.
        $this->assertEquals(5000, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $admissionType->id)->sum('debit'));
        $this->assertEquals(1, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $admissionType->id)->count());
        $this->assertEquals(3000, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $depositType->id)->sum('debit'));
        $this->assertEquals(1, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $depositType->id)->count());

        // Tuition Fee (a genuinely recurring item) is unaffected by the fix
        // and continues to be billed for the new class.
        $this->assertGreaterThan(0, StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $tuitionType->id)->where('debit', 1200)->count());
    }
}
