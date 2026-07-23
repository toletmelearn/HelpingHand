<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeCollection;
use App\Models\FeeCollectionItem;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\SecurityDeposit;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FeeCollectionItem had no booted() hook before this -- paying a deposit
 * fee head produced no record anywhere that the school was holding a
 * refundable liability. Confirms the new static::created() hook fires
 * regardless of which fee type/collection combination created the item,
 * and stays silent for ordinary (non-deposit) fee heads.
 */
class SecurityDepositCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_deposit_fee_type_creates_a_held_security_deposit()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = Student::create([
            'name' => 'Deposit Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998880001', 'address' => 'Somewhere',
        ]);

        $depositType = FeeType::create(['name' => 'Security Deposit', 'category' => 'deposit', 'status' => 'active', 'is_optional' => true]);
        $structure = FeeStructure::create(['class_name' => 'Class 1', 'academic_year' => '2026-2027', 'frequency' => 'yearly', 'status' => 'active']);

        $collection = FeeCollection::create([
            'receipt_no' => 'REC-DEP-0001',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 5000.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 5000.00,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'collected_by' => $admin->id,
        ]);

        $item = FeeCollectionItem::create([
            'fee_collection_id' => $collection->id,
            'fee_type_id' => $depositType->id,
            'amount' => 5000.00,
        ]);

        $this->assertDatabaseHas('security_deposits', [
            'student_id' => $student->id,
            'fee_type_id' => $depositType->id,
            'fee_collection_id' => $collection->id,
            'amount' => 5000.00,
            'status' => 'held',
        ]);
    }

    public function test_paying_an_ordinary_fee_type_does_not_create_a_security_deposit()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = Student::create([
            'name' => 'Tuition Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'female', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998880002', 'address' => 'Somewhere',
        ]);

        $tuitionType = FeeType::create(['name' => 'Tuition Fee', 'category' => 'recurring', 'status' => 'active']);
        $structure = FeeStructure::create(['class_name' => 'Class 1', 'academic_year' => '2026-2027', 'frequency' => 'yearly', 'status' => 'active']);

        $collection = FeeCollection::create([
            'receipt_no' => 'REC-DEP-0002',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 1000.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 1000.00,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'collected_by' => $admin->id,
        ]);

        FeeCollectionItem::create([
            'fee_collection_id' => $collection->id,
            'fee_type_id' => $tuitionType->id,
            'amount' => 1000.00,
        ]);

        $this->assertEquals(0, SecurityDeposit::where('student_id', $student->id)->count());
    }
}
