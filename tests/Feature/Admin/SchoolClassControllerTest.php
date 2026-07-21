<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SchoolClassControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);
        $this->actingAs($this->admin);
    }

    /** @test */
    public function it_can_display_school_classes_index()
    {
        SchoolClass::create([
            'name' => 'Class 1',
            'class_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.school-classes.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.school-classes.index');
    }

    /** @test */
    public function it_can_create_a_school_class_via_store()
    {
        $response = $this->post(route('admin.school-classes.store'), [
            'name' => 'Class 5',
            'class_order' => 5,
            'description' => 'Fifth standard',
        ]);

        $response->assertRedirect(route('admin.school-classes.index'));
        $this->assertDatabaseHas('school_classes', [
            'name' => 'Class 5',
            'class_order' => 5,
            'is_active' => true,
        ]);
    }
}
