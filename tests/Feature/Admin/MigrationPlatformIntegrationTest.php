<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\ParentModel;
use App\Models\ImportSession;
use App\Services\Imports\ImportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class MigrationPlatformIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected $testClass;
    protected $testSection;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup role and user
        $this->adminUser = User::factory()->create(['name' => 'Admin User']);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->testClass = \App\Models\SchoolClass::create([
            'name' => 'Grade 1',
            'class_order' => 1
        ]);
        $this->testSection = \App\Models\Section::create([
            'name' => 'A',
            'capacity' => 30
        ]);
    }

    /**
     * Test dynamic module readiness status indicators.
     */
    public function test_module_readiness_status_indicators()
    {
        $engine = app(ImportEngine::class);

        // Verify unregistered module returns 'red'
        $this->assertEquals('red', $engine->getModuleStatus('unknown-module'));

        // Verify registered empty module returns 'yellow'
        $this->assertEquals('yellow', $engine->getModuleStatus('students'));
        $this->assertEquals('yellow', $engine->getModuleStatus('teachers'));

        // Create a student and verify it becomes 'green'
        Student::create([
            'admission_no' => 'ADM-TEST-001',
            'name' => 'Jane Kid',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'phone' => '1234567890',
            'gender' => 'female',
            'date_of_birth' => '2015-05-10',
            'admission_date' => '2026-04-01',
            'is_active' => true,
            'aadhaar_number' => '123456789012',
            'address' => '123 Test Rd'
        ]);
        $this->assertEquals('green', $engine->getModuleStatus('students'));

        // Create a teacher and verify it becomes 'green'
        Teacher::create([
            'employee_id' => 'EMP-TEST-001',
            'name' => 'John Teacher',
            'phone' => '1234567890',
            'email' => 'john.teacher@school.test',
            'designation' => 'Principal',
        ]);
        $this->assertEquals('green', $engine->getModuleStatus('teachers'));
    }

    /**
     * Test Admin Dashboard passes correct onboarding checklist flags.
     */
    public function test_admin_dashboard_onboarding_checklist_flags()
    {
        // 1. Initial State: 0 students & 0 teachers -> should show checklist
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('showOnboardingChecklist', true);

        // 2. Populate Students, Teachers, and Parents -> checklist should collapse
        Student::create([
            'admission_no' => 'ADM-TEST-001',
            'name' => 'Jane Kid',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'phone' => '1234567890',
            'gender' => 'female',
            'date_of_birth' => '2015-05-10',
            'admission_date' => '2026-04-01',
            'is_active' => true,
            'aadhaar_number' => '123456789012',
            'address' => '123 Test Rd'
        ]);
        Teacher::create([
            'employee_id' => 'EMP-TEST-001',
            'name' => 'John Teacher',
            'phone' => '1234567890',
            'email' => 'john.teacher@school.test',
            'designation' => 'Principal',
        ]);
        ParentModel::create([
            'name' => 'John Doe Parent',
            'phone' => '9999999999',
            'email' => 'john@parent.test',
            'password' => Hash::make('password'),
        ]);

        $response2 = $this->actingAs($this->adminUser)
            ->get(route('admin.dashboard'));

        $response2->assertStatus(200);
        $response2->assertViewHas('showOnboardingChecklist', false);
    }

    /**
     * Test Data Management Dashboard returns advanced metrics.
     */
    public function test_data_management_dashboard_supplies_advanced_metrics()
    {
        $start = Carbon::now()->subSeconds(20);
        $end = Carbon::now();

        // Create an import session logs
        $session1 = ImportSession::create([
            'uuid' => 'test-uuid-completed-1',
            'module' => 'students',
            'status' => 'completed',
            'total_rows' => 50,
            'processed_rows' => 50,
            'success_rows' => 45,
            'error_rows' => 5,
            'start_time' => $start,
            'end_time' => $end,
            'created_by' => $this->adminUser->id,
        ]);
        DB::table('import_sessions')->where('id', $session1->id)->update([
            'created_at' => now()->subMinutes(2)
        ]);

        $session2 = ImportSession::create([
            'uuid' => 'test-uuid-processing-1',
            'module' => 'teachers',
            'status' => 'processing',
            'total_rows' => 20,
            'processed_rows' => 5,
            'success_rows' => 5,
            'error_rows' => 0,
            'start_time' => $end,
            'created_by' => $this->adminUser->id,
        ]);
        DB::table('import_sessions')->where('id', $session2->id)->update([
            'created_at' => now()
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('imports.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('stats');
        
        $stats = $response->viewData('stats');
        
        $this->assertEquals(2, $stats['total_imports']);
        $this->assertEquals(1, $stats['successful_imports']);
        $this->assertEquals(1, $stats['running_jobs']);
        $this->assertEquals(1, $stats['rollbacks_available']);
        // Speed = 50 rows / 20 seconds = 2.5 rows/sec
        $this->assertEquals(2.5, $stats['average_speed']);
        $this->assertEquals('teachers', $stats['last_imported_module']);
        $this->assertEquals($this->adminUser->name, $stats['last_imported_user']);
    }
}
