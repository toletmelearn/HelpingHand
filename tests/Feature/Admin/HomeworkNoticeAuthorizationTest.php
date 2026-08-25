<?php

namespace Tests\Feature\Admin;

use App\Models\HomeworkNotice;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Priority audit finding E1: Admin\HomeworkNoticeController never called
 * $this->authorize() anywhere, despite a complete, already-correct
 * HomeworkNoticePolicy existing (admin: full access; teacher: create, and
 * view/update/delete only their OWN notices via assigned_by === user id;
 * parent: view only) -- the policy was simply dead code. Any authenticated
 * account could create/edit/delete any homework notice under any teacher's
 * identity. Fixed by wiring the controller to the existing policy, unchanged,
 * rather than inventing new authorization rules.
 */
class HomeworkNoticeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->class = SchoolClass::create(['name' => 'HN Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeTeacher(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeParent(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'parent'], ['display_name' => 'Parent']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeNotice(int $assignedByUserId): HomeworkNotice
    {
        return HomeworkNotice::create([
            'title' => 'Test Homework', 'type' => 'homework', 'class_id' => $this->class->id,
            'assigned_by' => $assignedByUserId, 'status' => 'active', 'priority' => 'medium',
        ]);
    }

    public function test_admin_can_view_and_create_notices(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.homework-notices.index'))->assertOk();

        $response = $this->actingAs($admin)->post(route('admin.homework-notices.store'), [
            'title' => 'Admin Notice', 'description' => 'Details', 'type' => 'notice', 'class_id' => $this->class->id,
            'assigned_by' => $admin->id, 'status' => 'active', 'priority' => 'low',
        ]);
        $response->assertRedirect(route('admin.homework-notices.index'));
        $this->assertDatabaseHas('homework_notices', ['title' => 'Admin Notice']);
    }

    /**
     * Uses update() rather than edit() -- the admin.homework-notices.edit
     * Blade view doesn't exist yet (a pre-existing gap unrelated to this
     * authorization fix), but update() proves the same authorize('update', ...)
     * gate without depending on a view that was never built.
     */
    public function test_admin_can_edit_and_delete_any_notice(): void
    {
        $admin = $this->makeAdmin();
        $teacherUser = $this->makeTeacher();
        $notice = $this->makeNotice($teacherUser->id);

        $updateResponse = $this->actingAs($admin)->put(route('admin.homework-notices.update', $notice), [
            'title' => 'Updated by Admin', 'description' => 'Details', 'type' => 'homework', 'class_id' => $this->class->id,
            'assigned_by' => $teacherUser->id, 'status' => 'active', 'priority' => 'medium',
        ]);
        $updateResponse->assertRedirect(route('admin.homework-notices.index'));
        $this->assertSame('Updated by Admin', $notice->fresh()->title);

        $response = $this->actingAs($admin)->delete(route('admin.homework-notices.destroy', $notice));
        $response->assertRedirect(route('admin.homework-notices.index'));
        $this->assertSoftDeleted('homework_notices', ['id' => $notice->id]);
    }

    public function test_teacher_can_create_a_notice(): void
    {
        $teacherUser = $this->makeTeacher();

        $response = $this->actingAs($teacherUser)->post(route('admin.homework-notices.store'), [
            'title' => 'Teacher Notice', 'description' => 'Details', 'type' => 'homework', 'class_id' => $this->class->id,
            'assigned_by' => $teacherUser->id, 'status' => 'active', 'priority' => 'medium',
        ]);

        $response->assertRedirect(route('admin.homework-notices.index'));
        $this->assertDatabaseHas('homework_notices', ['title' => 'Teacher Notice', 'assigned_by' => $teacherUser->id]);
    }

    /** See test_admin_can_edit_and_delete_any_notice() for why update() is used instead of edit(). */
    public function test_teacher_can_edit_and_delete_their_own_notice(): void
    {
        $teacherUser = $this->makeTeacher();
        $notice = $this->makeNotice($teacherUser->id);

        $updateResponse = $this->actingAs($teacherUser)->put(route('admin.homework-notices.update', $notice), [
            'title' => 'Updated by Owner', 'description' => 'Details', 'type' => 'homework', 'class_id' => $this->class->id,
            'assigned_by' => $teacherUser->id, 'status' => 'active', 'priority' => 'medium',
        ]);
        $updateResponse->assertRedirect(route('admin.homework-notices.index'));
        $this->assertSame('Updated by Owner', $notice->fresh()->title);

        $response = $this->actingAs($teacherUser)->delete(route('admin.homework-notices.destroy', $notice));
        $response->assertRedirect(route('admin.homework-notices.index'));
        $this->assertSoftDeleted('homework_notices', ['id' => $notice->id]);
    }

    public function test_teacher_cannot_edit_or_delete_another_teachers_notice(): void
    {
        $teacherUser = $this->makeTeacher();
        $otherTeacherUser = $this->makeTeacher();
        $notice = $this->makeNotice($otherTeacherUser->id);

        $this->actingAs($teacherUser)->get(route('admin.homework-notices.edit', $notice))->assertForbidden();

        $response = $this->actingAs($teacherUser)->delete(route('admin.homework-notices.destroy', $notice));
        $response->assertForbidden();
        $this->assertDatabaseHas('homework_notices', ['id' => $notice->id, 'deleted_at' => null]);
    }

    public function test_parent_can_view_but_not_create_a_notice(): void
    {
        $parentUser = $this->makeParent();

        $this->actingAs($parentUser)->get(route('admin.homework-notices.index'))->assertOk();

        $response = $this->actingAs($parentUser)->post(route('admin.homework-notices.store'), [
            'title' => 'Parent Notice', 'type' => 'notice', 'class_id' => $this->class->id,
            'assigned_by' => $parentUser->id, 'status' => 'active', 'priority' => 'low',
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('homework_notices', ['title' => 'Parent Notice']);
    }

    public function test_parent_cannot_edit_or_delete_a_notice(): void
    {
        $parentUser = $this->makeParent();
        $teacherUser = $this->makeTeacher();
        $notice = $this->makeNotice($teacherUser->id);

        $this->actingAs($parentUser)->get(route('admin.homework-notices.edit', $notice))->assertForbidden();

        $response = $this->actingAs($parentUser)->delete(route('admin.homework-notices.destroy', $notice));
        $response->assertForbidden();
        $this->assertDatabaseHas('homework_notices', ['id' => $notice->id, 'deleted_at' => null]);
    }

    public function test_guest_cannot_view_notices(): void
    {
        $response = $this->get(route('admin.homework-notices.index'));
        $response->assertRedirect(route('login'));
    }
}
