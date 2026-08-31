<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Notifications\TimetablePublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase 2.6: nothing notified a teacher when their published schedule
 * changed. Confirms TimetablePublishedNotification is sent to every
 * teacher (primary or co-teacher) with a slot in what was just published,
 * never to an unrelated teacher, and that the teacher-facing notifications
 * page actually shows it.
 */
class TimetablePublishNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_publishing_a_manual_draft_notifies_the_affected_teacher(): void
    {
        Notification::fake();

        $class = SchoolClass::create(['name' => 'Notify Class A', 'class_order' => 993001, 'is_active' => true]);
        $section = Section::create(['name' => 'NA-1', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Notify Subject', 'code' => 'NTF-' . uniqid(), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Notified Teacher', 'status' => 'active']);
        $login = TeacherLogin::create(['teacher_id' => $teacher->id, 'username' => 'notify' . uniqid(), 'password' => Hash::make('secret123')]);
        $bell = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $bell->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => TimetableSlot::STATUS_DRAFT, 'timetable_generation_id' => null,
        ]);

        $this->actingAs($this->admin())->post(route('timetable.manual-draft.publish'), [
            'school_class_id' => $class->id, 'section_id' => $section->id,
        ]);

        Notification::assertSentTo($login, TimetablePublishedNotification::class);
    }

    public function test_unaffected_teacher_is_not_notified(): void
    {
        Notification::fake();

        $class = SchoolClass::create(['name' => 'Notify Class B', 'class_order' => 993002, 'is_active' => true]);
        $section = Section::create(['name' => 'NB-1', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Notify Subject B', 'code' => 'NTF-' . uniqid(), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Affected Teacher', 'status' => 'active']);
        TeacherLogin::create(['teacher_id' => $teacher->id, 'username' => 'notify' . uniqid(), 'password' => Hash::make('secret123')]);
        $unrelatedTeacher = Teacher::create(['name' => 'Unrelated Teacher', 'status' => 'active']);
        $unrelatedLogin = TeacherLogin::create(['teacher_id' => $unrelatedTeacher->id, 'username' => 'notify' . uniqid(), 'password' => Hash::make('secret123')]);
        $bell = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $bell->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => TimetableSlot::STATUS_DRAFT, 'timetable_generation_id' => null,
        ]);

        $this->actingAs($this->admin())->post(route('timetable.manual-draft.publish'), [
            'school_class_id' => $class->id, 'section_id' => $section->id,
        ]);

        Notification::assertNotSentTo($unrelatedLogin, TimetablePublishedNotification::class);
    }

    public function test_teacher_sees_notification_on_notifications_page(): void
    {
        $class = SchoolClass::create(['name' => 'Notify Class C', 'class_order' => 993003, 'is_active' => true]);
        $section = Section::create(['name' => 'NC-1', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Notify Subject C', 'code' => 'NTF-' . uniqid(), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Page Teacher', 'status' => 'active']);
        $login = TeacherLogin::create(['teacher_id' => $teacher->id, 'username' => 'notify' . uniqid(), 'password' => Hash::make('secret123')]);
        $bell = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $bell->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => TimetableSlot::STATUS_DRAFT, 'timetable_generation_id' => null,
        ]);

        $this->actingAs($this->admin())->post(route('timetable.manual-draft.publish'), [
            'school_class_id' => $class->id, 'section_id' => $section->id,
        ]);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.notifications.index'));

        $response->assertOk();
        $response->assertSee('Timetable Updated');
        $response->assertSee('Notify Class C');
    }

    public function test_teacher_can_mark_notification_as_read(): void
    {
        $class = SchoolClass::create(['name' => 'Notify Class D', 'class_order' => 993004, 'is_active' => true]);
        $section = Section::create(['name' => 'ND-1', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Notify Subject D', 'code' => 'NTF-' . uniqid(), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Read Teacher', 'status' => 'active']);
        $login = TeacherLogin::create(['teacher_id' => $teacher->id, 'username' => 'notify' . uniqid(), 'password' => Hash::make('secret123')]);
        $bell = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $bell->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => TimetableSlot::STATUS_DRAFT, 'timetable_generation_id' => null,
        ]);
        $this->actingAs($this->admin())->post(route('timetable.manual-draft.publish'), [
            'school_class_id' => $class->id, 'section_id' => $section->id,
        ]);
        $notificationId = $login->fresh()->notifications()->first()->id;

        $this->actingAs($login, 'teacher')->post(route('teacher.notifications.read', $notificationId));

        $this->assertNotNull($login->fresh()->notifications()->first()->read_at);
    }
}
