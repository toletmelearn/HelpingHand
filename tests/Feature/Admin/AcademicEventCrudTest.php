<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicEventCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_admin_can_create_an_academic_event(): void
    {
        $admin = $this->makeUserWithRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.academic-events.store'), [
            'title' => 'Diwali Break',
            'type' => 'holiday',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'description' => 'School closed for Diwali',
        ]);

        $response->assertRedirect(route('admin.academic-events.index'));
        $this->assertDatabaseHas('academic_events', [
            'title' => 'Diwali Break',
            'type' => 'holiday',
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_update_an_academic_event(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $event = AcademicEvent::create([
            'title' => 'Sports Day',
            'type' => 'event',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.academic-events.update', $event), [
            'title' => 'Annual Sports Day',
            'type' => 'event',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-02',
        ]);

        $response->assertRedirect(route('admin.academic-events.index'));
        $this->assertDatabaseHas('academic_events', [
            'id' => $event->id,
            'title' => 'Annual Sports Day',
            'is_active' => 0,
        ]);
    }

    public function test_admin_can_delete_an_academic_event(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $event = AcademicEvent::create([
            'title' => 'PTM',
            'type' => 'ptm',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.academic-events.destroy', $event));

        $response->assertRedirect(route('admin.academic-events.index'));
        $this->assertDatabaseMissing('academic_events', ['id' => $event->id]);
    }

    public function test_non_admin_cannot_create_an_academic_event(): void
    {
        $teacher = $this->makeUserWithRole('teacher');

        $response = $this->actingAs($teacher)->post(route('admin.academic-events.store'), [
            'title' => 'Should Fail',
            'type' => 'event',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-01',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('academic_events', ['title' => 'Should Fail']);
    }

    public function test_index_lists_events_for_authorized_users(): void
    {
        $teacher = $this->makeUserWithRole('teacher');
        AcademicEvent::create([
            'title' => 'Winter Break',
            'type' => 'holiday',
            'start_date' => '2026-12-20',
            'end_date' => '2027-01-01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)->get(route('admin.academic-events.index'));

        $response->assertOk();
        $response->assertSee('Winter Break');
    }
}
