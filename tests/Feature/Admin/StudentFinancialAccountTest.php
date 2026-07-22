<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\StudentFinancialAccount;
use App\Models\StudentFeeLedger;
use App\Models\FeeType;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentFinancialAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Student $student;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now()
        ]);
        
        $role = \App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($role->id);

        $this->class = SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10,
        ]);

        // Triggers the booted observer, creating the StudentFinancialAccount automatically
        $this->student = Student::create([
            'name' => 'Jane Doe',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => '987654321012',
            'address' => 'Address',
            'phone' => '9876543210',
            'mobile' => '9876543210',
            'admission_no' => 'ADM-1002',
            'class_id' => $this->class->id,
            'school_class_id' => $this->class->id,
            'section_id' => 1,
            'section' => 'A',
            'roll_number' => 5
        ]);
    }

    /** @test */
    public function it_creates_financial_account_on_student_creation()
    {
        $this->assertDatabaseHas('student_financial_accounts', [
            'student_id' => $this->student->id,
            'status' => 'active'
        ]);

        $account = $this->student->financialAccount;
        $this->assertNotNull($account);
        $this->assertEquals('FIN-' . str_pad($this->student->id, 6, '0', STR_PAD_LEFT), $account->account_no);
    }

    /** @test */
    public function it_requires_authentication_for_accounts_routes()
    {
        $this->get(route('admin.financial-accounts.index'))
            ->assertRedirect(route('login'));

        $this->get(route('admin.financial-accounts.show', $this->student->id))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function it_can_display_student_financial_accounts_list()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.financial-accounts.index'));

        $response->assertStatus(200);
        $response->assertSee('Student Financial Accounts');
        $response->assertSee($this->student->name);
        $response->assertSee($this->student->financialAccount->account_no);
    }

    /** @test */
    public function it_calculates_summary_metrics_correctly_from_ledger()
    {
        // 1. Post Opening Balance
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => now()->subDays(5)->format('Y-m-d'),
            'description' => 'Opening Balance',
            'reference_type' => 'opening_balance',
            'reference_id' => 1,
            'debit' => 1000.00,
            'credit' => 0.00,
            'running_balance' => 1000.00
        ]);

        // 2. Post tuition charge (fee_structure_item)
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => now()->subDays(4)->format('Y-m-d'),
            'description' => 'Tuition Fee Charge',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 2,
            'debit' => 5000.00,
            'credit' => 0.00,
            'running_balance' => 6000.00
        ]);

        // 3. Post Discount
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => now()->subDays(3)->format('Y-m-d'),
            'description' => 'Sibling Discount',
            'reference_type' => 'discount_applied',
            'reference_id' => 3,
            'debit' => 0.00,
            'credit' => 500.00,
            'running_balance' => 5500.00
        ]);

        // 4. Post Payment
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => now()->subDays(2)->format('Y-m-d'),
            'description' => 'Partial Tuition Payment',
            'reference_type' => 'fee_collection',
            'reference_id' => 4,
            'debit' => 0.00,
            'credit' => 3000.00,
            'running_balance' => 2500.00
        ]);

        // 5. Post Late Fee
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'description' => 'Late payment fee',
            'reference_type' => 'late_fine',
            'reference_id' => 5,
            'debit' => 150.00,
            'credit' => 0.00,
            'running_balance' => 2650.00
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.financial-accounts.show', $this->student->id));

        $response->assertStatus(200);
        $response->assertSee('₹1,000.00'); // Opening Balance
        $response->assertSee('₹5,000.00'); // Total Charges
        $response->assertSee('₹500.00');   // Total Discounts
        $response->assertSee('₹3,000.00'); // Total Payments
        $response->assertSee('₹150.00');   // Total Late Fees
        $response->assertSee('₹2,650.00'); // Outstanding Balance
    }

    /** @test */
    public function it_can_post_manual_debit_and_credit_adjustments()
    {
        // 1. Post Debit Adjustment (Fine)
        $response = $this->actingAs($this->admin)
            ->post(route('admin.financial-accounts.adjustment', $this->student->id), [
                'type' => 'debit',
                'amount' => 250.00,
                'description' => 'Damaged library book fine'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $this->student->id,
            'debit' => 250.00,
            'credit' => 0.00,
            'description' => 'Damaged library book fine',
            'reference_type' => 'adjustment'
        ]);

        $this->assertEquals(250.00, \App\Services\FinanceAccountService::getOutstandingBalance($this->student->id));

        // 2. Post Credit Adjustment (Credit note)
        $response = $this->actingAs($this->admin)
            ->post(route('admin.financial-accounts.adjustment', $this->student->id), [
                'type' => 'credit',
                'amount' => 100.00,
                'description' => 'Waved fine adjustment'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $this->student->id,
            'debit' => 0.00,
            'credit' => 100.00,
            'description' => 'Waved fine adjustment',
            'reference_type' => 'adjustment'
        ]);

        $this->assertEquals(150.00, \App\Services\FinanceAccountService::getOutstandingBalance($this->student->id));
    }

    /** @test */
    public function it_can_export_statement_pdf_and_csv()
    {
        $responsePdf = $this->actingAs($this->admin)
            ->get(route('admin.financial-accounts.export.pdf', $this->student->id));
        $responsePdf->assertStatus(200);
        $responsePdf->assertHeader('content-type', 'application/pdf');

        $responseExcel = $this->actingAs($this->admin)
            ->get(route('admin.financial-accounts.export.excel', $this->student->id));
        $responseExcel->assertStatus(200);
        $responseExcel->assertHeader('content-type', 'text/csv; charset=UTF-8');
        
        ob_start();
        $responseExcel->sendContent();
        $content = ob_get_clean();
        $this->assertStringContainsString('STUDENT FINANCIAL STATEMENT', $content);
    }
}
