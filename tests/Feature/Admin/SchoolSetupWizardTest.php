<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\AdminConfiguration;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\ClassManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SchoolSetupWizardTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['app.enforce_onboarding_check_in_tests' => true]);
        
        // Admin
        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);
        
        // Super Admin
        $this->superAdmin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin'], ['display_name' => 'Super Admin']);
        $this->superAdmin->roles()->attach($superAdminRole->id);
        
        $this->actingAs($this->admin);
        
        // Ensure not onboarded by default
        AdminConfiguration::set('general', 'is_onboarded', false, 'boolean');
    }

    protected function tearDown(): void
    {
        config(['app.enforce_onboarding_check_in_tests' => false]);
        parent::tearDown();
    }

    /** @test */
    public function dashboard_redirects_to_setup_wizard_index_when_not_onboarded()
    {
        $response = $this->get(route('admin.dashboard'));
        
        $response->assertRedirect(route('admin.setup-wizard.index'));
    }

    /** @test */
    public function index_redirects_to_highest_incomplete_step()
    {
        // Step 1: Incomplete (No school_name)
        $response = $this->get(route('admin.setup-wizard.index'));
        $response->assertRedirect(route('admin.setup-wizard', ['step' => 1]));

        // Complete Step 1
        AdminConfiguration::set('general', 'school_name', 'Test Academy', 'string');

        // Step 2: Now incomplete (No session)
        $response = $this->get(route('admin.setup-wizard.index'));
        $response->assertRedirect(route('admin.setup-wizard', ['step' => 2]));
    }

    /** @test */
    public function setup_wizard_prevents_skipping_steps()
    {
        // Try to access step 3 directly when step 1 and 2 are not done
        $response = $this->get(route('admin.setup-wizard', ['step' => 3]));
        
        $response->assertRedirect(route('admin.setup-wizard', ['step' => 1]));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function cannot_complete_wizard_with_missing_state()
    {
        // Set Step 1 only
        AdminConfiguration::set('general', 'school_name', 'Test Academy', 'string');

        // Post complete directly
        $response = $this->post(route('admin.setup-wizard.complete'), [
            'confirm_setup' => '1'
        ]);

        $response->assertRedirect(route('admin.setup-wizard', ['step' => 2])); // redirects to next incomplete step (Step 2)
        $response->assertSessionHas('error');
        $this->assertFalse((bool) AdminConfiguration::get('general', 'is_onboarded'));
    }

    /** @test */
    public function non_super_admin_cannot_access_reset_form_or_perform_reset()
    {
        // Logged in as $this->admin (regular admin)
        $response = $this->get(route('admin.setup-wizard.reset'));
        $response->assertStatus(403);

        $response = $this->post(route('admin.setup-wizard.reset.perform'), [
            'confirm_reset' => '1'
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function super_admin_can_reset_configurations()
    {
        $this->actingAs($this->superAdmin);

        // Seed some configs and classes
        AdminConfiguration::set('general', 'school_name', 'Clear Me School', 'string');
        AdminConfiguration::set('general', 'is_onboarded', true, 'boolean');
        SchoolClass::create(['name' => 'Grade 1', 'class_order' => 1]);
        ClassManagement::create(['name' => 'Grade 1', 'section' => 'A', 'capacity' => 40]);

        $response = $this->get(route('admin.setup-wizard.reset'));
        $response->assertStatus(200);

        $response = $this->post(route('admin.setup-wizard.reset.perform'), [
            'confirm_reset' => '1'
        ]);

        $response->assertRedirect(route('admin.setup-wizard.index'));
        
        $this->assertNull(AdminConfiguration::get('general', 'school_name'));
        $this->assertFalse((bool) AdminConfiguration::get('general', 'is_onboarded'));
        $this->assertEquals(0, SchoolClass::count());
        $this->assertEquals(0, ClassManagement::count());
    }

    /** @test */
    public function setup_wizard_full_flow_succeeds()
    {
        // 1. Submit Step 1: School Profile
        $profileData = [
            'school_name' => 'Test School Academy',
            'school_email' => 'info@testacademy.com',
            'school_phone' => '+91-9999999999',
            'school_address' => '123 Education Boulevard, Campus City',
        ];

        $response = $this->post(route('admin.setup-wizard.submit', ['step' => 1]), $profileData);
        $response->assertRedirect(route('admin.setup-wizard', ['step' => 2]));
        
        $this->assertEquals('Test School Academy', AdminConfiguration::get('general', 'school_name'));

        // 2. Submit Step 2: Academic Session
        $sessionData = [
            'name' => '2026-27',
            'code' => 'ACAD-2026',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
        ];

        $response = $this->post(route('admin.setup-wizard.submit', ['step' => 2]), $sessionData);
        $response->assertRedirect(route('admin.setup-wizard', ['step' => 3]));
        
        $this->assertDatabaseHas('academic_sessions', ['name' => '2026-27', 'is_current' => true]);

        // 3. Submit Step 3: Classes & Sections
        $classSectionData = [
            'classes' => ['Class 1', 'Class 2'],
            'sections' => [
                'Class 1' => ['A', 'B'],
                'Class 2' => ['A'],
            ]
        ];

        $response = $this->post(route('admin.setup-wizard.submit', ['step' => 3]), $classSectionData);
        $response->assertRedirect(route('admin.setup-wizard', ['step' => 4]));
        
        $this->assertDatabaseHas('school_classes', ['name' => 'Class 1']);
        $this->assertDatabaseHas('school_classes', ['name' => 'Class 2']);
        $this->assertDatabaseHas('class_management', ['name' => 'Class 1', 'section' => 'A']);
        $this->assertDatabaseHas('class_management', ['name' => 'Class 1', 'section' => 'B']);
        $this->assertDatabaseHas('class_management', ['name' => 'Class 2', 'section' => 'A']);

        // 4. Submit Step 4: Subjects
        $subjectsData = [
            'subjects' => ['Mathematics', 'English Language'],
        ];

        $response = $this->post(route('admin.setup-wizard.submit', ['step' => 4]), $subjectsData);
        $response->assertRedirect(route('admin.setup-wizard', ['step' => 5]));
        
        $this->assertDatabaseHas('subjects', ['name' => 'Mathematics']);
        $this->assertDatabaseHas('subjects', ['name' => 'English Language']);

        // 5. Submit Step 5: Complete Onboarding
        $completeData = [
            'confirm_setup' => '1',
        ];

        $response = $this->post(route('admin.setup-wizard.complete'), $completeData);
        $response->assertRedirect(route('admin.dashboard'));
        
        $this->assertTrue((bool) AdminConfiguration::get('general', 'is_onboarded'));

        // 6. Access dashboard directly now without redirects
        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }
}
