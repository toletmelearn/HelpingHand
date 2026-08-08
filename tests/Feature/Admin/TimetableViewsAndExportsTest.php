<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
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
 * Phase 5: interactive Teacher/Room views and the 4 Excel exports --
 * gated the same as the existing PDF exports (TimetableSlot::class
 * 'viewAny'), all reading through the SAME buildTimetableGrid()/
 * masterTimetableData() helpers the PDF exports already use.
 */
class TimetableViewsAndExportsTest extends TestCase
{
    use RefreshDatabase;

    protected SchoolClass $class;
    protected Section $section;
    protected Subject $subject;
    protected Teacher $teacher;
    protected BellTiming $timing1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::create(['name' => 'View Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $this->section = Section::create(['name' => 'A', 'class_id' => $this->class->id]);
        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'VIEWT5' . uniqid()]);
        $this->teacher = Teacher::create(['name' => 'Original Teacher']);
        $this->timing1 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSlot(array $overrides = []): TimetableSlot
    {
        return TimetableSlot::create(array_merge([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'published',
        ], $overrides));
    }

    // ---- Teacher view ----

    public function test_teacher_view_renders_the_picker_with_no_teacher_selected(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('timetable.view.teacher'));

        $response->assertOk();
        $response->assertSee('Pick a teacher above');
    }

    public function test_teacher_view_renders_the_grid_for_a_selected_teacher(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot();

        $response = $this->actingAs($admin)->get(route('timetable.view.teacher', ['teacher_id' => $this->teacher->id]));

        $response->assertOk();
        $response->assertSee($this->teacher->name);
        $response->assertSee($this->subject->name);
        $response->assertSee('Period 1');
    }

    public function test_teacher_view_only_shows_published_slots(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot(['status' => 'draft']);

        $response = $this->actingAs($admin)->get(route('timetable.view.teacher', ['teacher_id' => $this->teacher->id]));

        $response->assertOk();
        $response->assertSee('No published timetable slots found');
    }

    public function test_guest_cannot_view_the_teacher_view(): void
    {
        $response = $this->get(route('timetable.view.teacher'));

        $response->assertRedirect(route('login'));
    }

    // ---- Room view ----

    public function test_room_view_renders_the_picker_with_no_room_selected(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('timetable.view.room'));

        $response->assertOk();
        $response->assertSee('Pick a room above');
    }

    public function test_room_view_renders_the_grid_for_a_selected_room(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot(['room_number' => '101']);

        $response = $this->actingAs($admin)->get(route('timetable.view.room', ['room' => '101']));

        $response->assertOk();
        $response->assertSee('Room 101');
        $response->assertSee($this->subject->name);
    }

    public function test_room_picker_only_lists_distinct_rooms_from_published_slots(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot(['room_number' => '101']);

        $response = $this->actingAs($admin)->get(route('timetable.view.room'));

        $response->assertOk();
        $response->assertSee('101', false);
    }

    // ---- Excel exports ----

    public function test_class_excel_export_downloads_a_spreadsheet(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot();

        $response = $this->actingAs($admin)->get(route('timetable.export.class', ['school_class_id' => $this->class->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $rows = $this->readXlsxRows($response);
        $this->assertSame(['Period', 'Monday'], $rows[0]);
        $this->assertStringContainsString($this->subject->name, $rows[1][1]);
        $this->assertStringContainsString($this->teacher->short_name, $rows[1][1]);
    }

    public function test_class_excel_export_with_no_slots_redirects_with_error(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('timetable.export.class', ['school_class_id' => $this->class->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_teacher_excel_export_downloads_a_spreadsheet(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot();

        $response = $this->actingAs($admin)->get(route('timetable.export.teacher', ['teacher_id' => $this->teacher->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_room_excel_export_downloads_a_spreadsheet(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot(['room_number' => '101']);

        $response = $this->actingAs($admin)->get(route('timetable.export.room', ['room' => '101']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_room_excel_export_with_no_slots_redirects_with_error(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('timetable.export.room', ['room' => 'nonexistent']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_master_excel_export_downloads_a_spreadsheet(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot();

        $response = $this->actingAs($admin)->get(route('timetable.export.master'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = $response->baseResponse->getFile()->getPathname();
        $spreadsheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($path);
        $this->assertSame(['Monday'], $spreadsheet->getSheetNames());
        $rows = $spreadsheet->getSheetByName('Monday')->toArray();
        $this->assertSame('Period', $rows[0][0]);
        $this->assertSame($this->class->name, $rows[0][1]);
    }

    public function test_master_excel_export_with_no_slots_redirects_with_error(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('timetable.export.master'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_guest_cannot_download_any_excel_export(): void
    {
        $response = $this->get(route('timetable.export.class', ['school_class_id' => $this->class->id]));

        $response->assertRedirect(route('login'));
    }

    private function readXlsxRows($response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($path);

        return $spreadsheet->getActiveSheet()->toArray();
    }
}
