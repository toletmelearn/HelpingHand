<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AttendanceDeleteUiDisabledTest extends TestCase
{
    public function test_index_view_does_not_render_active_delete_form(): void
    {
        $response = $this->view('attendance.index', $this->indexViewData());

        $response->assertDontSee('name="_method" value="DELETE"', false);
        $response->assertDontSee('Delete this attendance record?', false);
        $response->assertDontSee('method="POST"', false);
    }

    public function test_index_view_shows_delete_disabled_message_or_disabled_control(): void
    {
        $response = $this->view('attendance.index', $this->indexViewData());

        $response->assertSee('Delete disabled');
        $response->assertSee('Deletion is disabled until an audit-preserving correction workflow is enabled.');
        $response->assertSee('disabled', false);
    }

    public function test_show_view_does_not_render_active_delete_form(): void
    {
        $response = $this->view('attendance.show', [
            'attendance' => $this->attendance(),
        ]);

        $response->assertDontSee('name="_method" value="DELETE"', false);
        $response->assertDontSee('Are you sure you want to delete this attendance record?', false);
        $response->assertDontSee('method="POST"', false);
    }

    public function test_show_view_shows_delete_disabled_message_or_disabled_control(): void
    {
        $response = $this->view('attendance.show', [
            'attendance' => $this->attendance(),
        ]);

        $response->assertSee('Delete disabled');
        $response->assertSee('Deletion is disabled until an audit-preserving correction workflow is enabled.');
        $response->assertSee('disabled', false);
    }

    public function test_edit_view_does_not_render_delete_form(): void
    {
        $response = $this->withViewErrors([])->view('attendance.edit', [
            'attendance' => $this->attendance(),
            'subjects' => collect(['Math', 'Science']),
        ]);

        $response->assertDontSee('name="_method" value="DELETE"', false);
        $response->assertDontSee('Delete Record', false);
        $response->assertDontSee('attendance.destroy', false);
    }

    private function indexViewData(): array
    {
        $attendance = $this->attendance();
        $items = new EloquentCollection([$attendance]);

        return [
            'attendances' => new LengthAwarePaginator($items, 1, 20, 1, [
                'path' => '/attendance',
            ]),
            'classes' => collect(['Class 10']),
            'stats' => [
                'total_students' => 1,
                'present_today' => 1,
                'attendance_rate' => 100,
            ],
        ];
    }

    private function attendance(): Attendance
    {
        $attendance = new Attendance([
            'student_id' => 1,
            'date' => '2026-06-06',
            'status' => 'present',
            'remarks' => 'Original',
            'period' => null,
            'subject' => 'Math',
            'class' => 'Class 10',
            'session' => '2026-2027',
            'marked_by' => 1,
            'ip_address' => '127.0.0.1',
            'device_info' => 'test',
        ]);

        $attendance->id = 1;
        $attendance->exists = true;
        $attendance->setRelation('student', (object) [
            'id' => 1,
            'name' => 'Student One',
            'father_name' => 'Father One',
            'mother_name' => 'Mother One',
            'roll_number' => '10',
            'aadhar_number' => '123456789012',
            'phone' => '9999999999',
        ]);
        $attendance->setRelation('markedBy', new User([
            'name' => 'Admin User',
        ]));

        $attendance->created_at = now();
        $attendance->updated_at = now();

        return $attendance;
    }
}
