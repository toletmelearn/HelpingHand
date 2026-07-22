<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AdmitCard;
use App\Models\AdmitCardFormat;
use App\Models\Exam;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported directly: the "Block All Fee Defaulters" button read
 * Student::fees() -- the legacy `fees` table, which nothing in the live fee
 * flow ever writes to (confirmed live: 0 rows). The button always found
 * zero defaulters no matter how much anyone actually owed. This proves the
 * real routes now read the live ledger balance.
 */
class AdmitCardDefaulterGateReadsLiveLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmitCard(User $admin, string $studentName, int $classOrder): AdmitCard
    {
        $class = SchoolClass::create(['name' => "Class {$classOrder}", 'class_order' => $classOrder, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'Term Exam', 'exam_type' => 'term', 'class_name' => $class->name, 'subject' => 'Math',
            'exam_date' => today()->addDays(10), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33, 'academic_year' => '2026-2027', 'status' => 'active',
        ]);
        $format = AdmitCardFormat::create(['name' => 'Standard', 'is_active' => true]);
        $student = Student::create([
            'name' => $studentName, 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887711', 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
        ]);

        return AdmitCard::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'admit_card_format_id' => $format->id,
            'academic_session' => '2026-2027', 'status' => 'published',
            'generated_by' => $admin->id,
            'data' => ['student_name' => $studentName],
        ]);
    }

    public function test_block_defaulters_revokes_admit_cards_with_real_outstanding_ledger_balance()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $withDues = $this->makeAdmitCard($admin, 'Owes Money Kid', 4);
        LedgerService::postDebit($withDues->student_id, today()->subMonth()->toDateString(), 'Tuition', 'fee_structure_item', 1, 1000);

        $noDues = $this->makeAdmitCard($admin, 'Cleared Kid', 5);

        $response = $this->actingAs($admin)->post(route('admin.admit-cards.block-defaulters'));
        $response->assertRedirect();

        $this->assertEquals('revoked', $withDues->fresh()->status);
        $this->assertEquals('published', $noDues->fresh()->status);
    }

    public function test_unblock_cleared_republishes_admit_cards_once_ledger_balance_is_zero_or_negative()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $admitCard = $this->makeAdmitCard($admin, 'Paying Off Kid', 6);
        LedgerService::postDebit($admitCard->student_id, today()->subMonth()->toDateString(), 'Tuition', 'fee_structure_item', 1, 1000);
        $admitCard->update(['status' => 'revoked']);

        // Still owes -> stays revoked.
        $this->actingAs($admin)->post(route('admin.admit-cards.unblock-cleared'));
        $this->assertEquals('revoked', $admitCard->fresh()->status);

        // Pays in full (even a slight overpayment) -> gets republished.
        LedgerService::postCredit($admitCard->student_id, today()->toDateString(), 'Payment', 'fee_collection', 1, 1000);
        $this->actingAs($admin)->post(route('admin.admit-cards.unblock-cleared'));
        $this->assertEquals('published', $admitCard->fresh()->status);
    }
}
