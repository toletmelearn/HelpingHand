<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Services\BulkFeeAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionFeeNewVsContinuingTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(string $name, ?int $admissionSessionId): Student
    {
        return Student::create([
            'name' => $name,
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01',
            'gender' => 'male',
            'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887771',
            'address' => 'Somewhere',
            'admission_session_id' => $admissionSessionId,
        ]);
    }

    /** @test */
    public function bulk_fee_assignment_charges_admission_fee_only_to_students_new_for_that_session()
    {
        $session = AcademicSession::create([
            'name' => '2026-2027',
            'code' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $olderSession = AcademicSession::create([
            'name' => '2025-2026',
            'code' => '2025-2026',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_current' => false,
            'is_active' => true,
        ]);

        SchoolClass::create(['name' => 'Class 6', 'class_order' => 6, 'is_active' => true]);

        $admissionFeeType = FeeType::create(['name' => 'Admission Fee', 'status' => 'active']);

        $feeStructure = FeeStructure::create([
            'class_name' => 'Class 6',
            'academic_year' => '2026-2027',
            'frequency' => 'yearly',
            'status' => 'active',
        ]);

        FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id,
            'fee_type_id' => $admissionFeeType->id,
            'amount' => 5000,
            'billing_frequency' => 'session_wise_admission',
            'charge_months' => ['Annual'],
        ]);

        // Newly admitted this session -- must be charged Admission Fee.
        $newStudent = $this->makeStudent('New Admission', $session->id);

        // Promoted/continuing from a prior session -- must NOT be charged again.
        $continuingStudent = $this->makeStudent('Continuing Student', $olderSession->id);

        // Legacy student predating the admission_session_id field entirely (null) --
        // per the "treat existing students as continuing" decision, must also be excluded.
        $legacyStudent = $this->makeStudent('Legacy Student', null);

        BulkFeeAssignmentService::bulkAssign($feeStructure, [
            $newStudent->id, $continuingStudent->id, $legacyStudent->id,
        ]);

        $this->assertTrue(
            StudentFeeLedger::where('student_id', $newStudent->id)
                ->where('fee_type_id', $admissionFeeType->id)
                ->exists()
        );

        $this->assertFalse(
            StudentFeeLedger::where('student_id', $continuingStudent->id)
                ->where('fee_type_id', $admissionFeeType->id)
                ->exists()
        );

        $this->assertFalse(
            StudentFeeLedger::where('student_id', $legacyStudent->id)
                ->where('fee_type_id', $admissionFeeType->id)
                ->exists()
        );
    }

    /** @test */
    public function bulk_fee_assignment_falls_back_to_old_behavior_when_session_cannot_be_resolved()
    {
        // No AcademicSession row matches this academic_year string at all.
        SchoolClass::create(['name' => 'Class 7', 'class_order' => 7, 'is_active' => true]);
        $admissionFeeType = FeeType::create(['name' => 'Admission Fee', 'status' => 'active']);

        $feeStructure = FeeStructure::create([
            'class_name' => 'Class 7',
            'academic_year' => '2099-2100',
            'frequency' => 'yearly',
            'status' => 'active',
        ]);

        FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id,
            'fee_type_id' => $admissionFeeType->id,
            'amount' => 5000,
            'billing_frequency' => 'session_wise_admission',
            'charge_months' => ['Annual'],
        ]);

        $student = $this->makeStudent('Unresolvable Session Student', null);

        BulkFeeAssignmentService::bulkAssign($feeStructure, [$student->id]);

        // Old behavior preserved: with no session to compare against, the item is
        // still billed (only the same-year dedup check applies).
        $this->assertTrue(
            StudentFeeLedger::where('student_id', $student->id)
                ->where('fee_type_id', $admissionFeeType->id)
                ->exists()
        );
    }

    /** @test */
    public function admission_confirmation_stamps_the_current_academic_session_on_new_students()
    {
        $session = AcademicSession::create([
            'name' => '2026-2027',
            'code' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $student = $this->makeStudent('Direct Create Student', $session->id);

        $this->assertTrue($student->isNewAdmissionFor($session));

        $otherSession = AcademicSession::create([
            'name' => '2027-2028',
            'code' => '2027-2028',
            'start_date' => '2027-04-01',
            'end_date' => '2028-03-31',
            'is_current' => false,
            'is_active' => true,
        ]);

        $this->assertFalse($student->isNewAdmissionFor($otherSession));
    }
}
