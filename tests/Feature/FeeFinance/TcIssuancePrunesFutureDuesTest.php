<?php

namespace Tests\Feature\FeeFinance;

use App\Models\Certificate;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\StudentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nothing in the codebase ever set a student to tc_issued or left_school,
 * though several gates (TerminalStatusReconciliationDetector, attendance
 * preflight, advanced reports) already check for those statuses. Publishing
 * a Transfer Certificate is the actual leaving-school event, so it now
 * mirrors StudentPromotionController::markAsPassedOut() (Phase 0): prune
 * future-dated ledger debits via the existing, tested
 * StructureAdjustmentService::withdrawStudent(), leaving already-due
 * (past-dated) debits untouched.
 */
class TcIssuancePrunesFutureDuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_tc_sets_status_and_drops_future_dues_but_keeps_past_dues()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $class = SchoolClass::create(['name' => 'Class 5', 'class_order' => 5, 'is_active' => true]);

        $student = Student::create([
            'name' => 'Departing Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2014-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887711', 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
        ]);

        $pastDate = now()->subMonth()->toDateString();
        $futureDate = now()->addMonth()->toDateString();

        \App\Services\LedgerService::postDebit($student->id, $pastDate, 'Already-due tuition', 'fee_structure_item', 1, 500);
        \App\Services\LedgerService::postDebit($student->id, $futureDate, 'Next month tuition', 'fee_structure_item', 2, 500);

        $this->assertEquals(1000, StudentFeeLedger::where('student_id', $student->id)->sum('debit'));

        $certificate = Certificate::create([
            'certificate_type' => 'tc',
            'serial_number' => 'CERT-2026-000001',
            'recipient_id' => $student->id,
            'recipient_type' => Student::class,
            'content_data' => ['Reason' => 'Family relocated'],
            'status' => 'generated',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.certificates.publish', $certificate->id));

        $response->assertRedirect();
        $certificate->refresh();
        $this->assertEquals('published', $certificate->status);

        $student->refresh();
        $this->assertNull($student->class_id);
        $this->assertNull($student->school_class_id);
        $this->assertEquals('Left School (TC Issued)', $student->class);

        $this->assertDatabaseHas('student_statuses', [
            'student_id' => $student->id,
            'status' => 'tc_issued',
            'document_number' => 'CERT-2026-000001',
        ]);

        // Future debit dropped, past (already-due) debit untouched.
        $this->assertEquals(500, StudentFeeLedger::where('student_id', $student->id)->sum('debit'));
        $this->assertDatabaseHas('student_fee_ledgers', ['student_id' => $student->id, 'description' => 'Already-due tuition']);
        $this->assertDatabaseMissing('student_fee_ledgers', ['student_id' => $student->id, 'description' => 'Next month tuition']);
    }

    public function test_publishing_non_tc_certificate_does_not_change_student_status()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $class = SchoolClass::create(['name' => 'Class 6', 'class_order' => 6, 'is_active' => true]);

        $student = Student::create([
            'name' => 'Staying Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2013-01-01', 'gender' => 'female', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887700', 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
        ]);

        $certificate = Certificate::create([
            'certificate_type' => 'bonafide',
            'serial_number' => 'CERT-2026-000002',
            'recipient_id' => $student->id,
            'recipient_type' => Student::class,
            'content_data' => ['Reason' => 'Passport application'],
            'status' => 'generated',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.certificates.publish', $certificate->id));
        $response->assertRedirect();

        $student->refresh();
        $this->assertEquals($class->id, $student->class_id);
        $this->assertDatabaseMissing('student_statuses', ['student_id' => $student->id, 'status' => 'tc_issued']);
    }
}
