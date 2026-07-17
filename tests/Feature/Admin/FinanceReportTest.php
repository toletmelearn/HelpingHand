<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $accountantUser;
    protected $student;
    protected $schoolClass;
    protected $section;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);

        // 'view-finance-reports' is normally seeded by PermissionSeeder,
        // a seeder that doesn't run automatically under RefreshDatabase --
        // grant it explicitly so these HTTP-level tests don't 403 before
        // ever reaching the controller.
        $viewFinanceReportsPermission = \App\Models\Permission::firstOrCreate(['name' => 'view-finance-reports']);
        $accountantRole->grantPermission($viewFinanceReportsPermission->name);
        $adminRole->grantPermission($viewFinanceReportsPermission->name);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->accountantUser = User::factory()->create(['role' => 'accountant']);
        $this->accountantUser->roles()->attach($accountantRole->id);

        $this->schoolClass = SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10
        ]);

        $this->section = Section::create([
            'name' => 'A',
            'class_id' => $this->schoolClass->id
        ]);

        $this->student = Student::create([
            'name' => 'Jane Doe',
            'admission_no' => 'ADM-2026-7777',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '123456789012',
            'address' => 'Test Address',
            'phone' => '9876543210',
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id
        ]);
    }

    /** @test */
    public function guests_and_students_cannot_access_finance_reports_portal()
    {
        $response = $this->get(route('admin.fees.reports.index'));
        $response->assertRedirect('/login');

        $studentUser = User::factory()->create(['role' => 'student']);
        $response2 = $this->actingAs($studentUser)->get(route('admin.fees.reports.index'));
        $response2->assertStatus(403);
    }

    /** @test */
    public function accountants_can_access_finance_reports_dashboard_and_switch_reports()
    {
        $response = $this->actingAs($this->accountantUser)->get(route('admin.fees.reports.index'));
        $response->assertStatus(200);
        $response->assertSee('Finance Reports Portal');
        $response->assertSee('Collection Register');

        // Switch to session comparison
        $responseSession = $this->actingAs($this->accountantUser)->get(route('admin.fees.reports.index', ['type' => 'session_comparison']));
        $responseSession->assertStatus(200);
        $responseSession->assertSee('Session Comparison');

        // Switch to Day Book
        $responseDay = $this->actingAs($this->accountantUser)->get(route('admin.fees.reports.index', ['type' => 'day_book']));
        $responseDay->assertStatus(200);
        $responseDay->assertSee('Day Book');
    }

    /** @test */
    public function report_portal_allows_exporting_to_csv_and_excel_streams()
    {
        $responseCsv = $this->actingAs($this->accountantUser)->get(route('admin.fees.reports.export', [
            'type' => 'collection_register',
            'format' => 'csv'
        ]));

        $responseCsv->assertStatus(200);
        $responseCsv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        \App\Models\StudentFeeLedger::create([
            'student_id' => $this->student->id, 'date' => '2026-07-01', 'description' => 'Tuition',
            'reference_type' => 'fee_structure_item', 'reference_id' => 1, 'debit' => 5000.00,
            'credit' => 0.00, 'running_balance' => 5000.00, 'unpaid_amount' => 5000.00,
        ]);

        // Regression test: this used to assert 'application/vnd.ms-excel'
        // (a .xls content-type) while the actual streamed content was
        // plain comma-separated text -- Excel would then try to parse it
        // as its native binary format, fail, and dump the whole export
        // into a single cell instead of splitting rows/columns. Reported
        // by a user reviewing a real downloaded Outstanding Register
        // export. The "excel" format is really just CSV, same as every
        // other register export in this app -- assert that instead.
        $responseExcel = $this->actingAs($this->accountantUser)->get(route('admin.fees.reports.export', [
            'type' => 'outstanding_register',
            'format' => 'excel'
        ]));

        $responseExcel->assertStatus(200);
        $responseExcel->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $responseExcel->streamedContent();
        $this->assertStringContainsString("Admission No", $content);
        $this->assertGreaterThan(1, substr_count($content, "\n"), 'Export should have multiple lines, not everything crammed onto one row.');
    }

    /** @test */
    public function outstanding_and_demand_registers_are_sorted_class_wise_and_support_a_section_filter()
    {
        // A lower class_order class -- should sort BEFORE $this->schoolClass
        // (order 10) once results are grouped class-wise.
        $juniorClass = SchoolClass::create(['name' => 'Class 5', 'class_order' => 5]);
        $juniorStudent = Student::create([
            'name' => 'Amit Junior', 'admission_no' => 'ADM-2026-5001', 'father_name' => 'Father',
            'mother_name' => 'Mother', 'date_of_birth' => '2015-01-01', 'aadhar_number' => '555555555555',
            'address' => 'Test', 'phone' => '9555555555', 'class_id' => $juniorClass->id,
        ]);

        // A second section within the same class as $this->student, to
        // prove the section filter narrows within a class, not just across
        // classes.
        $otherSection = Section::create(['name' => 'B', 'class_id' => $this->schoolClass->id]);
        $otherSectionStudent = Student::create([
            'name' => 'Priya OtherSection', 'admission_no' => 'ADM-2026-5002', 'father_name' => 'Father',
            'mother_name' => 'Mother', 'date_of_birth' => '2010-01-01', 'aadhar_number' => '666666666666',
            'address' => 'Test', 'phone' => '9666666666', 'class_id' => $this->schoolClass->id,
            'section_id' => $otherSection->id,
        ]);

        foreach ([$this->student, $juniorStudent, $otherSectionStudent] as $student) {
            \App\Models\StudentFeeLedger::create([
                'student_id' => $student->id, 'date' => '2026-07-01', 'description' => 'Tuition',
                'reference_type' => 'fee_structure_item', 'reference_id' => 1, 'debit' => 2000.00,
                'credit' => 0.00, 'running_balance' => 2000.00, 'unpaid_amount' => 2000.00,
            ]);
        }

        foreach (['outstanding_register', 'demand_register'] as $type) {
            // Class-wise: the lower class_order student's row appears
            // before the higher class_order student's row.
            $response = $this->actingAs($this->adminUser)->get(route('admin.fees.reports.export', [
                'type' => $type, 'format' => 'csv',
            ]));
            $content = $response->streamedContent();
            $juniorPos = strpos($content, 'Amit Junior');
            $seniorPos = strpos($content, $this->student->name);
            $this->assertNotFalse($juniorPos, "{$type}: junior class student should appear in the export.");
            $this->assertNotFalse($seniorPos, "{$type}: {$this->student->name} should appear in the export.");
            $this->assertLessThan($seniorPos, $juniorPos, "{$type}: Class 5 (order 5) should sort before Class 10 (order 10).");

            // Section filter: only the requested section's student shows.
            $filteredResponse = $this->actingAs($this->adminUser)->get(route('admin.fees.reports.export', [
                'type' => $type, 'format' => 'csv', 'section_id' => $this->section->id,
            ]));
            $filteredContent = $filteredResponse->streamedContent();
            $this->assertStringContainsString($this->student->name, $filteredContent, "{$type}: section filter should keep the matching section's student.");
            $this->assertStringNotContainsString('Priya OtherSection', $filteredContent, "{$type}: section filter should exclude the other section's student.");
        }
    }

    /** @test */
    public function report_portal_allows_print_preview_generation()
    {
        $responsePrint = $this->actingAs($this->accountantUser)->get(route('admin.fees.reports.export', [
            'type' => 'cash_book',
            'format' => 'print'
        ]));

        $responsePrint->assertStatus(200);
        $responsePrint->assertSee('Print Preview Mode');
        $responsePrint->assertSee('Helping Hand School');
    }
}
