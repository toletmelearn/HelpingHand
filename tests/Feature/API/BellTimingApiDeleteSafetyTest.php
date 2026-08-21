<?php

namespace Tests\Feature\API;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 3A regression coverage: API\BellTimingController::destroy() used
 * to call $bellTiming->delete() unconditionally -- the exact same bug
 * Phase 1 fixed on the web controller (App\Http\Controllers\
 * BellTimingController), still live here because Phase 1 never touched
 * this separate API controller. These tests exercise the real HTTP
 * routes (Sanctum-authenticated, through routes/api.php +
 * ApiAccessControl) rather than calling the controller directly, so the
 * fix and the pre-existing admin-only authorization gate are proven
 * together, mirroring how tests/Feature/BellTimingDeleteSafetyTest.php
 * covers the web side.
 */
class BellTimingApiDeleteSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'name' => 'API Admin',
            'email' => uniqid('api_admin_', true) . '@example.test',
            'password' => Hash::make('password123'),
        ]);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function teacherUser(): User
    {
        $user = User::create([
            'name' => 'API Teacher',
            'email' => uniqid('api_teacher_', true) . '@example.test',
            'password' => Hash::make('password123'),
        ]);
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
    }

    /** A Sanctum token scoped exactly like a real admin mobile client -- ApiAccessControl requires both the admin role AND this specific token ability. */
    private function asAdmin(User $admin): self
    {
        $token = $admin->createToken('test-token', ['mobile:user', 'mobile:admin'])->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function asTeacher(User $teacher): self
    {
        $token = $teacher->createToken('test-token', ['mobile:user', 'mobile:teacher'])->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function makeBellTiming(array $overrides = []): BellTiming
    {
        return BellTiming::create(array_merge([
            'day_of_week' => 'Tuesday',
            'period_name' => 'Period 3',
            'start_time' => '10:15',
            'end_time' => '11:00',
            'class_section' => 'Class 5',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 3,
        ], $overrides));
    }

    /** @return array{0: SchoolClass, 1: Section, 2: Subject, 3: Teacher} */
    private function makeDependencyFixtures(): array
    {
        $schoolClass = SchoolClass::create(['name' => 'API Delete Safety Class', 'class_order' => 950, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $schoolClass->id]);
        $subject = Subject::create(['name' => 'API Delete Safety Subject', 'code' => 'API-DEL', 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'API Delete Safety Teacher', 'status' => 'active']);

        return [$schoolClass, $section, $subject, $teacher];
    }

    // ============================================================
    // 1. Successful deletion
    // ============================================================

    public function test_authorized_admin_can_delete_an_unused_bell_timing(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->asAdmin($this->admin())
            ->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('bell_timings', ['id' => $bellTiming->id]);
    }

    // ============================================================
    // 2-5. Blocked deletion per dependency type
    // ============================================================

    public function test_delete_blocked_by_timetable_slots(): void
    {
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->asAdmin($this->admin())
            ->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $response->assertStatus(409);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }

    public function test_delete_blocked_by_a_published_timetable_slot(): void
    {
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->asAdmin($this->admin())
            ->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $response->assertStatus(409);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('published', strtolower($response->json('message')));
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
        $this->assertDatabaseHas('timetable_slots', ['bell_timing_id' => $bellTiming->id, 'status' => 'published']);
    }

    public function test_delete_blocked_by_teacher_substitutions(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, $section, $subject, $teacher] = $this->makeDependencyFixtures();
        $substitution = TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $bellTiming->id,
            'created_by' => $admin->id,
        ]);

        // Before this fix, this would have thrown a raw, unhandled
        // QueryException (teacher_substitutions.bell_timing_id has no
        // cascade -- default RESTRICT) instead of a clean 409 JSON error.
        $response = $this->asAdmin($admin)
            ->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $response->assertStatus(409);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $substitution->id]);
    }

    public function test_delete_blocked_by_teacher_availabilities(): void
    {
        $bellTiming = $this->makeBellTiming();
        [, , , $teacher] = $this->makeDependencyFixtures();
        // teacher_availabilities.bell_timing_id cascades on delete -- before
        // this fix, this row would have been silently destroyed with no
        // warning at all instead of blocking the deletion.
        $availability = TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $bellTiming->id,
        ]);

        $response = $this->asAdmin($this->admin())
            ->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $response->assertStatus(409);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
        $this->assertDatabaseHas('teacher_availabilities', ['id' => $availability->id]);
    }

    // ============================================================
    // 6-9. No side effects / clean response on a blocked delete
    // ============================================================

    public function test_blocked_delete_leaves_the_bell_timing_unchanged(): void
    {
        $bellTiming = $this->makeBellTiming(['period_name' => 'Original Name']);
        [, , , $teacher] = $this->makeDependencyFixtures();
        TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $bellTiming->id]);

        $this->asAdmin($this->admin())->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $bellTiming->refresh();
        $this->assertSame('Original Name', $bellTiming->period_name);
        $this->assertTrue($bellTiming->is_active);
    }

    public function test_blocked_delete_leaves_dependent_records_unchanged(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, $section, $subject, $teacher] = $this->makeDependencyFixtures();
        $substitution = TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $bellTiming->id,
            'created_by' => $admin->id,
            'status' => 'pending',
        ]);

        $this->asAdmin($admin)->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $substitution->refresh();
        $this->assertSame('pending', $substitution->status);
        $this->assertSame($bellTiming->id, $substitution->bell_timing_id);
    }

    public function test_blocked_delete_returns_proper_json_response(): void
    {
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->asAdmin($this->admin())
            ->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $response->assertStatus(409);
        $response->assertJsonStructure(['success', 'message', 'errors', 'timestamp']);
        $response->assertJson(['success' => false]);
    }

    public function test_blocked_delete_does_not_expose_sql_or_query_exception_details(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, $section, $subject, $teacher] = $this->makeDependencyFixtures();
        TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $bellTiming->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->asAdmin($admin)
            ->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('QueryException', $body);
        $this->assertStringNotContainsString('SQL:', $body);
        $this->assertStringNotContainsString('Integrity constraint violation', $body);
    }

    // ============================================================
    // 10-11. Authorization
    // ============================================================

    public function test_unauthenticated_request_cannot_delete(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $response->assertStatus(401);
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }

    public function test_non_admin_teacher_cannot_delete(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->asTeacher($this->teacherUser())
            ->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }

    public function test_admin_role_without_the_mobile_admin_token_ability_cannot_delete(): void
    {
        $admin = $this->admin();
        // Right role, but the token was never granted the mobile:admin
        // ability ApiAccessControl requires -- role alone must not be
        // enough to reach this destructive endpoint.
        $token = $admin->createToken('test-token', ['mobile:user'])->plainTextToken;
        $bellTiming = $this->makeBellTiming();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/v1/bell-timing/{$bellTiming->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }
}
