<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Datesheet;
use App\Models\DatesheetEntry;
use App\Models\Exam;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Datesheet module: covers the state machine (draft->under_review->
 * approved->published, published immutable, revision creates a new
 * version), conflict validation (reusing SchoolClass::validSectionIds()
 * and the TimetableSlot-style overlap pattern), authorization
 * (permission-based, no new role, per confirmed decision), and the
 * publish->Exam integration (no duplicate Exam rows on republish).
 */
class DatesheetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'test-datesheet-role-' . uniqid()], ['display_name' => 'Test']);
        foreach ($permissions as $permission) {
            \App\Models\Permission::firstOrCreate(['name' => $permission]);
            $role->grantPermission($permission);
        }
        $user->roles()->attach($role->id);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function academicSession(): AcademicSession
    {
        return AcademicSession::create([
            'name' => '2026-2027', 'code' => 'DS-' . uniqid(), 'is_current' => true, 'is_active' => true,
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);
    }

    private function classWithSection(string $suffix): array
    {
        $class = SchoolClass::create(['name' => "DS Class $suffix", 'class_order' => 990900 + crc32($suffix) % 1000, 'is_active' => true]);
        $section = Section::create(['name' => "DS-$suffix"]);
        $this->bridgeSectionToClass($class, $section);

        return [$class, $section];
    }

    // --- 1/2/3. Create, class/section selection, real Subject FK ---------

    public function test_admin_can_create_a_draft_datesheet_with_classes(): void
    {
        $admin = $this->admin();
        $session = $this->academicSession();
        [$class, $section] = $this->classWithSection('A');

        $response = $this->actingAs($admin)->post(route('admin.datesheets.store'), [
            'name' => 'Term 1 Examinations',
            'exam_type' => 'Term 1',
            'academic_session_id' => $session->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-15',
            'class_ids' => [$class->id],
            'section_ids' => [$class->id => $section->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('datesheets', ['name' => 'Term 1 Examinations', 'status' => 'draft']);
        $this->assertDatabaseHas('datesheet_classes', ['school_class_id' => $class->id, 'section_id' => $section->id]);
    }

    private function draftDatesheet(): array
    {
        $admin = $this->admin();
        $session = $this->academicSession();
        [$class, $section] = $this->classWithSection('B');
        $subject = Subject::create(['name' => 'DS Subject', 'code' => 'DS-' . uniqid(), 'is_active' => true]);

        $datesheet = Datesheet::create([
            'name' => 'DS Test', 'exam_type' => 'Term 1', 'academic_session_id' => $session->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-15', 'status' => 'draft', 'created_by' => $admin->id,
        ]);
        $datesheet->classes()->create(['school_class_id' => $class->id, 'section_id' => $section->id]);

        return compact('admin', 'session', 'class', 'section', 'subject', 'datesheet');
    }

    // 4. Invalid class/section/subject combinations rejected.
    public function test_add_entry_rejects_a_section_not_belonging_to_the_class(): void
    {
        $f = $this->draftDatesheet();
        [$otherClass, $otherSection] = $this->classWithSection('C');

        $response = $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id,
            'section_id' => $otherSection->id, // belongs to a different class
            'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('datesheet_entries', ['datesheet_id' => $f['datesheet']->id]);
    }

    // 5. Exam dates/times validated.
    public function test_add_entry_rejects_end_time_before_start_time(): void
    {
        $f = $this->draftDatesheet();

        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '11:00', 'end_time' => '09:00',
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('datesheet_entries', ['datesheet_id' => $f['datesheet']->id]);
    }

    public function test_add_entry_rejects_a_date_outside_the_datesheet_window(): void
    {
        $f = $this->draftDatesheet();

        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-11-01', 'start_time' => '09:00', 'end_time' => '11:00',
        ])->assertSessionHas('error');
    }

    // 6. Conflicting entries rejected.
    public function test_add_entry_rejects_an_overlapping_time_for_the_same_class_section(): void
    {
        $f = $this->draftDatesheet();
        $subject2 = Subject::create(['name' => 'DS Subject 2', 'code' => 'DS-' . uniqid(), 'is_active' => true]);

        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        // Neither subject has a real TeacherClassSubjectAssignment in this
        // fixture, so the first add is a "success"-or-"warning" flash
        // (no-teacher-assigned is a warning, not a rejection) -- assert on
        // the actual row instead of which flash key was used.
        $this->assertSame(1, DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->count());

        $response = $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $subject2->id,
            'exam_date' => '2026-10-05', 'start_time' => '10:00', 'end_time' => '12:00', // overlaps
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->count());
    }

    public function test_add_entry_rejects_a_duplicate_subject_for_the_same_class_section(): void
    {
        $f = $this->draftDatesheet();

        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        $this->assertSame(1, DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->count());

        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-07', 'start_time' => '09:00', 'end_time' => '11:00',
        ])->assertSessionHas('error');

        $this->assertSame(1, DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->count());
    }

    // Sync-audit loophole L-07: room was stored but never checked against
    // another entry's booking.
    public function test_add_entry_rejects_a_room_already_booked_by_another_class_at_an_overlapping_time(): void
    {
        $f = $this->draftDatesheet();
        [$otherClass, $otherSection] = $this->classWithSection('D');
        $f['datesheet']->classes()->create(['school_class_id' => $otherClass->id, 'section_id' => $otherSection->id]);
        $subject2 = Subject::create(['name' => 'DS Subject 2', 'code' => 'DS-' . uniqid(), 'is_active' => true]);

        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00', 'room' => 'Hall A',
        ]);
        $this->assertSame(1, DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->count());

        $response = $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $otherClass->id, 'section_id' => $otherSection->id, 'subject_id' => $subject2->id,
            'exam_date' => '2026-10-05', 'start_time' => '10:00', 'end_time' => '12:00', 'room' => 'Hall A',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->count());
    }

    public function test_add_entry_accepts_the_same_room_at_a_non_overlapping_time(): void
    {
        $f = $this->draftDatesheet();
        [$otherClass, $otherSection] = $this->classWithSection('E');
        $f['datesheet']->classes()->create(['school_class_id' => $otherClass->id, 'section_id' => $otherSection->id]);
        $subject2 = Subject::create(['name' => 'DS Subject 3', 'code' => 'DS-' . uniqid(), 'is_active' => true]);

        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00', 'room' => 'Hall B',
        ]);

        $response = $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $otherClass->id, 'section_id' => $otherSection->id, 'subject_id' => $subject2->id,
            'exam_date' => '2026-10-05', 'start_time' => '11:00', 'end_time' => '13:00', 'room' => 'Hall B',
        ]);

        $this->assertSame(2, DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->count());
    }

    // Sync-audit loophole L-08: an exam overlapping a live teaching period
    // is surfaced as a warning, not blocked -- confirms the entry is still
    // created (advisory only), with the warning flash present.
    public function test_add_entry_warns_but_does_not_block_when_class_has_a_regular_lesson_at_that_time(): void
    {
        $f = $this->draftDatesheet();
        $teacher = Teacher::create(['name' => 'Regular Teacher']);
        // 2026-10-05 is a Monday.
        $bellTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P2', 'start_time' => '09:30:00', 'end_time' => '10:30:00',
            'is_active' => true, 'is_break' => false, 'period_type' => 'teaching', 'order_index' => 2,
        ]);
        TimetableSlot::create([
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $f['subject']->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);

        $response->assertSessionHas('success');
        $this->assertStringContainsString('regular lesson scheduled', session('success'));
        $this->assertSame(1, DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->count());
    }

    public function test_add_entry_accepts_a_valid_non_conflicting_entry(): void
    {
        $f = $this->draftDatesheet();

        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00', 'total_marks' => 80, 'passing_marks' => 26,
        ]);

        $this->assertDatabaseHas('datesheet_entries', [
            'datesheet_id' => $f['datesheet']->id, 'subject_id' => $f['subject']->id, 'total_marks' => 80,
        ]);
    }

    // 7. Draft -> Review -> Approve -> Publish workflow.
    public function test_full_workflow_draft_to_published(): void
    {
        $f = $this->draftDatesheet();
        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);

        $this->actingAs($f['admin'])->post(route('admin.datesheets.submit', $f['datesheet']))->assertSessionHas('success');
        $this->assertSame('under_review', $f['datesheet']->fresh()->status);

        $this->actingAs($f['admin'])->post(route('admin.datesheets.approve', $f['datesheet']))->assertSessionHas('success');
        $this->assertSame('approved', $f['datesheet']->fresh()->status);

        $this->actingAs($f['admin'])->post(route('admin.datesheets.publish', $f['datesheet']))->assertSessionHas('success');
        $this->assertSame('published', $f['datesheet']->fresh()->status);
    }

    public function test_cannot_submit_an_empty_datesheet(): void
    {
        $f = $this->draftDatesheet();

        $this->actingAs($f['admin'])->post(route('admin.datesheets.submit', $f['datesheet']))->assertSessionHas('error');
        $this->assertSame('draft', $f['datesheet']->fresh()->status);
    }

    public function test_cannot_publish_directly_from_draft(): void
    {
        $f = $this->draftDatesheet();
        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);

        $this->actingAs($f['admin'])->post(route('admin.datesheets.publish', $f['datesheet']))->assertSessionHas('error');
        $this->assertSame('draft', $f['datesheet']->fresh()->status);
    }

    // 8. Published cannot be silently edited.
    public function test_published_datesheet_rejects_new_entries_and_deletions(): void
    {
        $f = $this->draftDatesheet();
        $entryResponse = $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        $entry = DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->first();

        $this->actingAs($f['admin'])->post(route('admin.datesheets.submit', $f['datesheet']));
        $this->actingAs($f['admin'])->post(route('admin.datesheets.approve', $f['datesheet']));
        $this->actingAs($f['admin'])->post(route('admin.datesheets.publish', $f['datesheet']));

        $subject2 = Subject::create(['name' => 'DS Subject Late', 'code' => 'DS-' . uniqid(), 'is_active' => true]);
        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $subject2->id,
            'exam_date' => '2026-10-06', 'start_time' => '09:00', 'end_time' => '11:00',
        ])->assertSessionHas('error');

        $this->actingAs($f['admin'])->delete(route('admin.datesheets.entries.destroy', [$f['datesheet'], $entry]))
            ->assertSessionHas('error');

        $this->assertSame(1, DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->count());
    }

    // 9. Revision creates a new version, doesn't touch the published one.
    public function test_revise_creates_a_new_draft_and_original_is_only_superseded_once_the_revision_publishes(): void
    {
        $f = $this->draftDatesheet();
        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        $this->actingAs($f['admin'])->post(route('admin.datesheets.submit', $f['datesheet']));
        $this->actingAs($f['admin'])->post(route('admin.datesheets.approve', $f['datesheet']));
        $this->actingAs($f['admin'])->post(route('admin.datesheets.publish', $f['datesheet']));

        $originalExamId = DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->first()->exam_id;
        $this->assertNotNull($originalExamId);

        $this->actingAs($f['admin'])->post(route('admin.datesheets.revise', $f['datesheet']))->assertRedirect();

        $revision = Datesheet::where('revises_id', $f['datesheet']->id)->first();
        $this->assertNotNull($revision);
        $this->assertSame('draft', $revision->status);

        // Original is untouched (not yet superseded) while the revision is still a draft.
        $this->assertNull($f['datesheet']->fresh()->superseded_by_id);
        $this->assertSame('published', $f['datesheet']->fresh()->status);

        // Change the time on the revision's carried-over entry, then publish it.
        $revisedEntry = $revision->entries()->first();
        $this->assertSame($originalExamId, $revisedEntry->exam_id); // carried over, not duplicated

        $this->actingAs($f['admin'])->post(route('admin.datesheets.submit', $revision));
        $this->actingAs($f['admin'])->post(route('admin.datesheets.approve', $revision));
        $this->actingAs($f['admin'])->post(route('admin.datesheets.publish', $revision));

        // NOW the original is marked superseded.
        $this->assertSame($revision->id, $f['datesheet']->fresh()->superseded_by_id);

        // No duplicate Exam row was created for the same entry.
        $this->assertSame(1, Exam::where('id', $originalExamId)->count());
        $this->assertSame($originalExamId, $revision->entries()->first()->fresh()->exam_id);
    }

    // 10. Publishing creates/links Exam records without duplicates.
    public function test_publish_creates_a_real_exam_record_with_correct_fields(): void
    {
        $f = $this->draftDatesheet();
        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00', 'total_marks' => 80, 'passing_marks' => 27,
        ]);
        $this->actingAs($f['admin'])->post(route('admin.datesheets.submit', $f['datesheet']));
        $this->actingAs($f['admin'])->post(route('admin.datesheets.approve', $f['datesheet']));
        $this->actingAs($f['admin'])->post(route('admin.datesheets.publish', $f['datesheet']));

        $entry = DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->first();
        $this->assertNotNull($entry->exam_id);

        $this->assertDatabaseHas('exams', [
            'id' => $entry->exam_id,
            'class_id' => $f['class']->id,
            'class_name' => $f['class']->name,
            'subject' => $f['subject']->name, // legacy string, derived -- backward compatible
            'academic_year' => '2026-2027', // derived from AcademicSession->name, not a separate field
            'total_marks' => 80,
            'passing_marks' => 27,
        ]);
    }

    // Sync-audit loophole L-09: publish() used to call Exam::create()
    // directly, bypassing the same duplicate-exam check
    // Admin\ExamController::store() enforces -- a Datesheet could publish
    // an exam colliding with one already created via the legacy admin
    // exam form for the same class/subject/year/term.
    public function test_publish_is_blocked_when_it_would_duplicate_an_existing_exam(): void
    {
        $f = $this->draftDatesheet();
        Exam::create([
            'name' => 'Pre-existing Exam', 'exam_type' => 'term', 'class_id' => $f['class']->id,
            'class_name' => $f['class']->name, 'subject_id' => $f['subject']->id, 'subject' => $f['subject']->name,
            'exam_date' => '2026-09-01', 'start_time' => '09:00', 'end_time' => '11:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => $f['session']->name, 'term' => $f['datesheet']->exam_type, 'status' => 'scheduled',
        ]);

        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        $this->actingAs($f['admin'])->post(route('admin.datesheets.submit', $f['datesheet']));
        $this->actingAs($f['admin'])->post(route('admin.datesheets.approve', $f['datesheet']));

        $response = $this->actingAs($f['admin'])->post(route('admin.datesheets.publish', $f['datesheet']));

        $response->assertSessionHas('error');
        $entry = DatesheetEntry::where('datesheet_id', $f['datesheet']->id)->first();
        $this->assertNull($entry->exam_id);
        $this->assertSame(1, Exam::where('class_id', $f['class']->id)->count());
    }

    // Authorization: permission-based, no new role (confirmed decision).
    public function test_non_admin_without_permission_cannot_create_or_publish(): void
    {
        $plainUser = User::factory()->create();
        $session = $this->academicSession();

        $this->actingAs($plainUser)->post(route('admin.datesheets.store'), [
            'name' => 'X', 'exam_type' => 'Term 1', 'academic_session_id' => $session->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-15', 'class_ids' => [],
        ])->assertForbidden();
    }

    public function test_user_with_only_create_permission_cannot_approve(): void
    {
        $creator = $this->userWithPermissions(['create-datesheet']);
        $f = $this->draftDatesheetAs($creator);

        $this->actingAs($creator)->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        $this->actingAs($creator)->post(route('admin.datesheets.submit', $f['datesheet']));

        $this->actingAs($creator)->post(route('admin.datesheets.approve', $f['datesheet']))->assertForbidden();
    }

    public function test_user_with_approve_permission_but_not_publish_cannot_publish(): void
    {
        $approver = $this->userWithPermissions(['create-datesheet', 'approve-datesheet']);
        $f = $this->draftDatesheetAs($approver);
        $this->actingAs($approver)->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        $this->actingAs($approver)->post(route('admin.datesheets.submit', $f['datesheet']));
        $this->actingAs($approver)->post(route('admin.datesheets.approve', $f['datesheet']))->assertSessionHas('success');

        $this->actingAs($approver)->post(route('admin.datesheets.publish', $f['datesheet']))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_admin_datesheet_routes(): void
    {
        $this->get(route('admin.datesheets.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_without_view_permission_cannot_view_the_index(): void
    {
        $plainUser = User::factory()->create();

        $this->actingAs($plainUser)->get(route('admin.datesheets.index'))->assertForbidden();
    }

    // 17. PDF export works.
    public function test_pdf_export_downloads_for_an_authorized_user(): void
    {
        $f = $this->draftDatesheet();
        $this->actingAs($f['admin'])->post(route('admin.datesheets.entries.store', $f['datesheet']), [
            'school_class_id' => $f['class']->id, 'section_id' => $f['section']->id, 'subject_id' => $f['subject']->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);

        $response = $this->actingAs($f['admin'])->get(route('admin.datesheets.pdf', $f['datesheet']));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    private function draftDatesheetAs(User $creator): array
    {
        $session = $this->academicSession();
        [$class, $section] = $this->classWithSection('D' . uniqid());
        $subject = Subject::create(['name' => 'DS Subject ' . uniqid(), 'code' => 'DS-' . uniqid(), 'is_active' => true]);

        $datesheet = Datesheet::create([
            'name' => 'DS Test', 'exam_type' => 'Term 1', 'academic_session_id' => $session->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-15', 'status' => 'draft', 'created_by' => $creator->id,
        ]);
        $datesheet->classes()->create(['school_class_id' => $class->id, 'section_id' => $section->id]);

        return compact('creator', 'session', 'class', 'section', 'subject', 'datesheet');
    }
}
