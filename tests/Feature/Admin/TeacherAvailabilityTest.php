<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T2a item 5: teacher availability grid -- toggling, persistence, and the
 * admin-any / teacher-own-only policy split (mirrors TimetableSlotPolicy).
 */
class TeacherAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function teacherUser(): array
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);
        $teacher = Teacher::create(['name' => 'Availability Teacher ' . uniqid(), 'user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function makeBellTiming(string $day = 'Monday', string $period = 'P1', int $order = 1): BellTiming
    {
        return BellTiming::create([
            'day_of_week' => $day,
            'period_name' => $period,
            'start_time' => '08:00',
            'end_time' => '08:45',
            'is_active' => true,
            'is_break' => false,
            'order_index' => $order,
            'class_section' => null,
        ]);
    }

    public function test_admin_can_view_any_teachers_grid(): void
    {
        $admin = $this->admin();
        [, $teacher] = $this->teacherUser();
        $this->makeBellTiming();

        $response = $this->actingAs($admin)->get(route('teacher-availability.edit', $teacher));

        $response->assertOk();
        $response->assertSee($teacher->name);
    }

    public function test_teacher_can_view_own_grid(): void
    {
        [$user, $teacher] = $this->teacherUser();
        $this->makeBellTiming();

        $response = $this->actingAs($user)->get(route('teacher-availability.edit', $teacher));

        $response->assertOk();
    }

    public function test_teacher_cannot_view_another_teachers_grid(): void
    {
        [$user] = $this->teacherUser();
        [, $otherTeacher] = $this->teacherUser();
        $this->makeBellTiming();

        $response = $this->actingAs($user)->get(route('teacher-availability.edit', $otherTeacher));

        $response->assertForbidden();
    }

    public function test_index_redirects_teacher_straight_to_their_own_grid(): void
    {
        [$user, $teacher] = $this->teacherUser();

        $response = $this->actingAs($user)->get(route('teacher-availability.index'));

        $response->assertRedirect(route('teacher-availability.edit', $teacher));
    }

    public function test_update_creates_blocked_rows_for_submitted_ids(): void
    {
        $admin = $this->admin();
        [, $teacher] = $this->teacherUser();
        $timing = $this->makeBellTiming();

        $response = $this->actingAs($admin)->post(route('teacher-availability.update', $teacher), [
            'blocked' => [$timing->id],
        ]);

        $response->assertRedirect(route('teacher-availability.edit', $teacher));
        $this->assertDatabaseHas('teacher_availabilities', [
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $timing->id,
            'is_available' => false,
        ]);
    }

    public function test_update_removes_rows_no_longer_submitted_as_blocked(): void
    {
        $admin = $this->admin();
        [, $teacher] = $this->teacherUser();
        $timing = $this->makeBellTiming();

        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $timing->id,
            'is_available' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('teacher-availability.update', $teacher), [
            'blocked' => [],
        ]);

        $response->assertRedirect(route('teacher-availability.edit', $teacher));
        $this->assertDatabaseMissing('teacher_availabilities', [
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $timing->id,
        ]);
    }

    public function test_teacher_cannot_update_another_teachers_grid(): void
    {
        [$user] = $this->teacherUser();
        [, $otherTeacher] = $this->teacherUser();
        $timing = $this->makeBellTiming();

        $response = $this->actingAs($user)->post(route('teacher-availability.update', $otherTeacher), [
            'blocked' => [$timing->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('teacher_availabilities', ['teacher_id' => $otherTeacher->id]);
    }
}
