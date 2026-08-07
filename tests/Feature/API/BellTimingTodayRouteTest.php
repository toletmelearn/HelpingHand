<?php

namespace Tests\Feature\API;

use App\Models\BellTiming;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BellTimingTodayRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetMinimalSchema();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_bell_timing_today_route_returns_public_safe_schedule(): void
    {
        $day = now()->format('l');
        $otherDay = $day === 'Monday' ? 'Tuesday' : 'Monday';

        $this->createBellTiming($day, '10-A', 'Period 2', '10:00:00', '10:45:00', true, 2);
        $this->createBellTiming($day, '10-A', 'Period 1', '09:00:00', '09:45:00', false, 1);
        $this->createBellTiming($day, '10-A', 'Inactive Period', '11:00:00', '11:45:00', false, 3, false);
        $this->createBellTiming($day, '9-A', 'Other Class', '09:00:00', '09:45:00', false, 1);
        $this->createBellTiming($otherDay, '10-A', 'Other Day', '09:00:00', '09:45:00', false, 1);

        $response = $this->getJson('/api/v1/bell-timing/today/10-A');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', "Today's bell schedule retrieved successfully")
            ->assertJsonPath('data.class_section', '10-A')
            ->assertJsonPath('data.day', $day)
            ->assertJsonCount(2, 'data.schedule')
            ->assertJsonPath('data.schedule.0.period_name', 'Period 1')
            ->assertJsonPath('data.schedule.1.period_name', 'Period 2')
            ->assertJsonPath('data.schedule.0.order_index', 1)
            ->assertJsonPath('data.schedule.1.order_index', 2);

        $schedule = $response->json('data.schedule');

        $this->assertSame([
            'id',
            'period_name',
            'start_time',
            'end_time',
            'is_break',
            'order_index',
            'custom_label',
            'color_code',
        ], array_keys($schedule[0]));
        $this->assertFalse(collect($schedule)->contains('period_name', 'Inactive Period'));
        $this->assertFalse(collect($schedule)->contains('period_name', 'Other Class'));
        $this->assertFalse(collect($schedule)->contains('period_name', 'Other Day'));
    }

    public function test_exam_paper_public_routes_remain_blocked_by_api_access_control(): void
    {
        $this->getJson('/api/v1/exam-papers/available/10-A')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->postJson('/api/v1/exam-papers/search', [
            'query' => 'math',
        ])->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function createBellTiming(
        string $day,
        string $classSection,
        string $periodName,
        string $startTime,
        string $endTime,
        bool $isBreak,
        int $orderIndex,
        bool $isActive = true
    ): BellTiming {
        return BellTiming::create([
            'day_of_week' => $day,
            'period_name' => $periodName,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'class_section' => $classSection,
            'is_active' => $isActive,
            'is_break' => $isBreak,
            'order_index' => $orderIndex,
            'academic_year' => '2026-2027',
            'semester' => 'Term 1',
            'custom_label' => $isBreak ? 'Break' : null,
            'color_code' => $isBreak ? '#ffc107' : '#007bff',
            'created_by' => 1,
        ]);
    }

    private function resetMinimalSchema(): void
    {
        $this->dropMinimalSchema();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('bell_timings', function (Blueprint $table) {
            $table->id();
            $table->string('day_of_week');
            $table->string('period_name');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('class_section')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_break')->default(false);
            $table->string('period_type')->default('teaching');
            $table->integer('order_index')->default(0);
            $table->string('academic_year')->nullable();
            $table->string('semester')->nullable();
            $table->string('custom_label')->nullable();
            $table->string('color_code')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('bell_timings');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
}
