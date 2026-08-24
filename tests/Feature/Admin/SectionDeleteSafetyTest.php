<?php

namespace Tests\Feature\Admin;

use App\Models\ClassManagement;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2C safety fix: Admin\SectionController::destroy() previously
 * deleted (soft-deleted) any Section with zero dependency check -- even
 * one referenced by hundreds of real students, or configured as a valid
 * admission option for a class -- confirmed live and unguarded by Phase
 * 2B-ii's read-only audit. This proves the fix blocks deletion when
 * either dependency exists, and still allows it when genuinely unused.
 */
class SectionDeleteSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($role->id);
    }

    public function test_admin_can_delete_a_section_with_no_dependencies(): void
    {
        $section = Section::create(['name' => 'Unused Section', 'capacity' => 40]);

        $response = $this->actingAs($this->admin)->delete(route('admin.sections.destroy', $section->id));

        $response->assertRedirect(route('admin.sections.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('sections', ['id' => $section->id]);
    }

    public function test_section_referenced_by_a_student_cannot_be_deleted(): void
    {
        $section = Section::create(['name' => 'In Use Section', 'capacity' => 40]);
        Student::create([
            'name' => 'Kid', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2018-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => '123412341234', 'phone' => '9000000001', 'address' => 'Addr',
            'section_id' => $section->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.sections.destroy', $section->id));

        $response->assertRedirect(route('admin.sections.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('sections', ['id' => $section->id, 'deleted_at' => null]);
    }

    public function test_section_configured_for_a_class_cannot_be_deleted(): void
    {
        $section = Section::create(['name' => 'Bridged Section', 'capacity' => 40]);
        $class = SchoolClass::create(['name' => 'Grade X', 'class_order' => 1, 'capacity' => 40]);
        $classManagement = ClassManagement::create(['name' => 'Grade X', 'section' => '', 'capacity' => 40]);
        DB::table('legacy_class_map')->insert([
            'class_management_id' => $classManagement->id, 'school_class_id' => $class->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('class_sections')->insert([
            'class_management_id' => $classManagement->id, 'section_id' => $section->id,
            'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.sections.destroy', $section->id));

        $response->assertRedirect(route('admin.sections.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('sections', ['id' => $section->id, 'deleted_at' => null]);
    }

    public function test_non_admin_cannot_delete_a_section(): void
    {
        $section = Section::create(['name' => 'Protected Section', 'capacity' => 40]);
        $clerk = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $clerk->roles()->attach($role->id);

        $response = $this->actingAs($clerk)->delete(route('admin.sections.destroy', $section->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('sections', ['id' => $section->id, 'deleted_at' => null]);
    }
}
