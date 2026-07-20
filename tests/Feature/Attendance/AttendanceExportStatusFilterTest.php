<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\AttendanceController;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AttendanceExportStatusFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->integer('role_id');
            $table->integer('user_id');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->integer('role_id');
            $table->integer('permission_id');
        });

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('roll_number')->nullable();
            $table->string('class')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('student_id')->nullable();
            $table->integer('teacher_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->text('remarks')->nullable();
            $table->string('period')->nullable();
            $table->string('subject')->nullable();
            $table->string('class')->nullable();
            $table->string('session')->nullable();
            $table->integer('marked_by')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamps();
        });

        $this->actingAs($this->createExportUser());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('students');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_export_controller_applies_allowlisted_status_filter(): void
    {
        $presentId = $this->createAttendance('present');
        $absentId = $this->createAttendance('absent');

        $rows = $this->csvRowsForRequest(['status' => 'absent']);

        $this->assertAttendanceIds($rows, [$absentId]);
    }

    public function test_export_controller_ignores_unsupported_status_safely(): void
    {
        $presentId = $this->createAttendance('present');
        $absentId = $this->createAttendance('absent');

        $rows = $this->csvRowsForRequest(['status' => 'unexpected']);

        $this->assertAttendanceIds($rows, [$presentId, $absentId]);
    }

    public function test_export_without_status_keeps_previous_behavior(): void
    {
        $presentId = $this->createAttendance('present', 'Class 1', '2026-06-06');
        $absentId = $this->createAttendance('absent', 'Class 1', '2026-06-06');
        $this->createAttendance('late', 'Class 2', '2026-06-06');
        $this->createAttendance('half_day', 'Class 1', '2026-06-07');

        $rows = $this->csvRowsForRequest([
            'from_date' => '2026-06-06',
            'to_date' => '2026-06-06',
            'class' => 'Class 1',
        ]);

        $this->assertAttendanceIds($rows, [$presentId, $absentId]);
    }

    public function test_index_export_link_preserves_status_when_present(): void
    {
        $html = $this->renderIndexView('/attendance?status=half_day');
        $query = $this->exportLinkQuery($html);

        $this->assertSame('half_day', $query['status'] ?? null);
        $this->assertSame('csv', $query['format'] ?? null);
    }

    public function test_index_export_link_preserves_date_class_and_status_together(): void
    {
        $html = $this->renderIndexView('/attendance?date=2026-06-06&class=Class%201&status=late');
        $query = $this->exportLinkQuery($html);

        $this->assertSame('2026-06-06', $query['from_date'] ?? null);
        $this->assertSame('2026-06-06', $query['to_date'] ?? null);
        $this->assertSame('Class 1', $query['class'] ?? null);
        $this->assertSame('late', $query['status'] ?? null);
        $this->assertSame('csv', $query['format'] ?? null);
    }

    public function test_reports_export_link_does_not_add_status(): void
    {
        $html = $this->renderReportsView('/attendance/reports?date=2026-06-06&class=Class%201&status=late');
        $query = $this->exportLinkQuery($html);

        $this->assertArrayNotHasKey('status', $query);
        $this->assertSame('2026-06-06', $query['from_date'] ?? null);
        $this->assertSame('Class 1', $query['class'] ?? null);
    }

    public function test_csv_headers_remain_unchanged_after_status_filter_support(): void
    {
        $this->createAttendance('present');

        $rows = $this->csvRowsForRequest(['status' => 'present']);

        $this->assertSame([
            'Date',
            'Class',
            'Student Name',
            'Roll Number',
            'Status',
            'Subject',
            'Period',
            'Period Display',
            'Remarks',
            'Marked By',
            'IP Address',
        ], $rows[0]);
    }

    /**
     * @param array<string, string> $params
     * @return array<int, array<int, string|null>>
     */
    private function csvRowsForRequest(array $params): array
    {
        $request = Request::create('/attendance/export', 'GET', $params);
        $response = (new AttendanceController())->export($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $output = $this->streamedContent($response);
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $output)));

        return array_map(function (string $line, int $index): array {
            if ($index === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }

            return str_getcsv($line);
        }, $lines, array_keys($lines));
    }

    private function streamedContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /**
     * @param array<int, int> $expectedIds
     * @param array<int, array<int, string|null>> $rows
     */
    private function assertAttendanceIds(array $rows, array $expectedIds): void
    {
        $expectedRemarks = array_map(fn (int $id): string => 'attendance-id-' . $id, $expectedIds);
        $actualRemarks = array_map(fn (array $row): ?string => $row[8] ?? null, array_slice($rows, 1));

        sort($expectedRemarks);
        sort($actualRemarks);

        $this->assertSame($expectedRemarks, $actualRemarks);
    }

    private function createAttendance(string $status, string $class = 'Class 1', string $date = '2026-06-06'): int
    {
        $studentId = DB::table('students')->insertGetId([
            'name' => 'Student ' . $status,
            'roll_number' => (string) random_int(1, 999),
            'class' => $class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $attendanceId = DB::table('attendances')->insertGetId([
            'student_id' => $studentId,
            'date' => $date,
            'status' => $status,
            'remarks' => '',
            'period' => null,
            'subject' => 'General',
            'class' => $class,
            'session' => '2026-2027',
            'marked_by' => auth()->id(),
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendances')
            ->where('id', $attendanceId)
            ->update(['remarks' => 'attendance-id-' . $attendanceId]);

        return $attendanceId;
    }

    private function createExportUser(): User
    {
        $user = User::create([
            'name' => 'Export User',
            'email' => 'export-user@example.test',
            'password' => 'secret',
        ]);

        $role = Role::create(['name' => 'exporter']);
        $permission = Permission::create(['name' => 'view-attendance', 'guard_name' => 'web']);

        $user->roles()->attach($role->id);
        $role->permissions()->attach($permission->id);
        $user->load('roles');

        return $user;
    }

    private function renderIndexView(string $uri): string
    {
        $this->setCurrentRequest($uri);

        return view('attendance.index', [
            'attendances' => $this->paginator('/attendance'),
            'classes' => collect(['Class 1', 'Class 2']),
            'stats' => [],
        ])->render();
    }

    private function renderReportsView(string $uri): string
    {
        $this->setCurrentRequest($uri);

        return view('attendance.reports', [
            'attendances' => $this->paginator('/attendance/reports'),
            'classes' => collect(['Class 1', 'Class 2']),
            'stats' => [
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'percentage' => 0,
            ],
        ])->render();
    }

    /**
     * @return array<string, string>
     */
    private function exportLinkQuery(string $html): array
    {
        preg_match('/<a href="([^"]+)" class="btn btn-primary">\s*<i class="bi bi-download"><\/i>\s*Export CSV/s', $html, $matches);

        $this->assertNotEmpty($matches, 'Export CSV link was not found.');

        $href = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $query);

        return $query;
    }

    private function setCurrentRequest(string $uri): void
    {
        $request = Request::create($uri, 'GET');

        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
    }

    private function paginator(string $path): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            new Collection(),
            0,
            15,
            1,
            ['path' => $path]
        );
    }
}
