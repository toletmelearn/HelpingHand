<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\User;
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

    /**
     * End-to-end check of the actual reported concern: does the PROMOTION module
     * have the same "wrong fee type charged" problem as admission did? A student
     * admitted in one session, then promoted (via the real StudentPromotionController
     * route, not a direct DB write) into a new class for a new session, must still
     * be excluded from that new session's Admission Fee while correctly being
     * billed the new class's Annual/Tuition Fee like everyone else.
     *
     * @test
     */
    public function promoted_student_is_excluded_from_admission_fee_but_still_billed_annual_fee()
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        $oldSession = AcademicSession::create([
            'name' => '2025-2026', 'code' => '2025-2026',
            'start_date' => '2025-04-01', 'end_date' => '2026-03-31',
            'is_current' => false, 'is_active' => true,
        ]);
        $newSession = AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'is_active' => true,
        ]);

        $classSix = SchoolClass::create(['name' => 'Class 6', 'class_order' => 6, 'is_active' => true]);
        $classSeven = SchoolClass::create(['name' => 'Class 7', 'class_order' => 7, 'is_active' => true]);

        $admissionFeeType = FeeType::create(['name' => 'Admission Fee', 'status' => 'active']);
        $annualFeeType = FeeType::create(['name' => 'Annual Fee', 'status' => 'active']);

        // Student was admitted into Class 6 during the OLD session.
        $student = $this->makeStudent('Promoted Student', $oldSession->id);
        $student->update(['class_id' => $classSix->id, 'school_class_id' => $classSix->id, 'class' => $classSix->name]);

        // Promote via the real controller route, exactly as an admin would.
        $this->actingAs($admin)->post(route('admin.student-promotions.store'), [
            'academic_session_id' => $newSession->id,
            'from_class' => $classSix->id,
            'to_class' => $classSeven->id,
            'students' => [$student->id],
            'remarks' => 'Annual promotion',
        ])->assertRedirect(route('admin.student-promotions.index'));

        $student->refresh();
        $this->assertEquals($classSeven->id, $student->class_id);
        // Promotion must never touch the original admission session marker.
        $this->assertEquals($oldSession->id, $student->admission_session_id);

        // New session's fee structure for the student's new class (Class 7).
        $feeStructure = FeeStructure::create([
            'class_name' => 'Class 7', 'academic_year' => '2026-2027',
            'frequency' => 'yearly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id, 'fee_type_id' => $admissionFeeType->id,
            'amount' => 5000, 'billing_frequency' => 'session_wise_admission', 'charge_months' => ['Annual'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id, 'fee_type_id' => $annualFeeType->id,
            'amount' => 12000, 'billing_frequency' => 'yearly', 'charge_months' => ['Annual'],
        ]);

        BulkFeeAssignmentService::bulkAssign($feeStructure, [$student->id]);

        $this->assertFalse(
            StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $admissionFeeType->id)->exists(),
            'Promoted student must not be charged Admission Fee for the new session.'
        );
        $this->assertTrue(
            StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $annualFeeType->id)->exists(),
            'Promoted student must still be billed the normal Annual Fee for their new class.'
        );
    }
}
