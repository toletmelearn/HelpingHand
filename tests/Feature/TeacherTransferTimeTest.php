<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\BuildingTransferTime;
use App\Models\BellTiming;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableConflictResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Priority 1.3: a teacher scheduled back-to-back in two different
 * buildings with less than the configured transfer time between them is a
 * physically impossible placement -- nothing previously modelled
 * buildings at all, so TimetableConflictResolver could only ever catch
 * the SAME room double-booked, never two different rooms too far apart to
 * reach in time. Proves the buildings/rooms schema, Building's per-pair
 * transfer-time lookup, and TimetableConflictResolver's new
 * transferTimeConflicts() check all work together as a hard block.
 */
class TeacherTransferTimeTest extends TestCase
{
    use RefreshDatabase;

    private function makeClass(): SchoolClass
    {
        return SchoolClass::create(['name' => 'Transfer Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
    }

    private function makeTiming(array $overrides = []): BellTiming
    {
        return BellTiming::create(array_merge([
            'day_of_week' => 'Monday', 'period_name' => 'P1',
            'start_time' => '08:00', 'end_time' => '08:45',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
            'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ], $overrides));
    }

    public function test_buildings_created(): void
    {
        $main = Building::create(['name' => 'Main Building', 'transfer_time_in_minutes' => 10]);
        $annex = Building::create(['name' => 'Annex Building', 'transfer_time_in_minutes' => 10]);
        $science = Building::create(['name' => 'Science Block', 'transfer_time_in_minutes' => 15]);

        $this->assertDatabaseHas('buildings', ['id' => $main->id, 'name' => 'Main Building']);
        $this->assertDatabaseHas('buildings', ['id' => $annex->id, 'name' => 'Annex Building']);
        $this->assertDatabaseHas('buildings', ['id' => $science->id, 'name' => 'Science Block', 'transfer_time_in_minutes' => 15]);
    }

    public function test_rooms_assigned_to_buildings(): void
    {
        $main = Building::create(['name' => 'Main Building']);
        $room = Room::create(['room_number' => 'Room 101', 'building_id' => $main->id]);

        $this->assertSame($main->id, $room->fresh()->building_id);
        $this->assertTrue($main->rooms()->where('room_number', 'Room 101')->exists());
    }

    public function test_same_building_no_transfer_needed(): void
    {
        $main = Building::create(['name' => 'Main Building', 'transfer_time_in_minutes' => 10]);
        Room::create(['room_number' => 'Room 101', 'building_id' => $main->id]);
        Room::create(['room_number' => 'Room 102', 'building_id' => $main->id]);

        $class = $this->makeClass();
        $subject1 = Subject::create(['name' => 'Maths', 'code' => 'TT' . uniqid()]);
        $subject2 = Subject::create(['name' => 'Science', 'code' => 'TT' . uniqid()]);
        $teacher = Teacher::create(['name' => 'T Teacher', 'status' => 'active']);

        $p1 = $this->makeTiming(['period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'order_index' => 1]);
        $p2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'order_index' => 2]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $p1->id,
            'subject_id' => $subject1->id, 'teacher_id' => $teacher->id,
            'room_number' => 'Room 101', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        // A different subject_id than the existing slot's, purely so the
        // pre-existing subject-per-day cap (unrelated to this test) never
        // trips -- this test is isolating transfer-time only.
        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $p2->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject2->id,
            'room_number' => 'Room 102',
        ]);

        $this->assertFalse($result['conflict']);
    }

    public function test_different_buildings_with_gap_OK(): void
    {
        $main = Building::create(['name' => 'Main Building', 'transfer_time_in_minutes' => 10]);
        $annex = Building::create(['name' => 'Annex Building', 'transfer_time_in_minutes' => 10]);
        Room::create(['room_number' => 'Room 101', 'building_id' => $main->id]);
        Room::create(['room_number' => 'Annex 1', 'building_id' => $annex->id]);

        $class = $this->makeClass();
        $subject1 = Subject::create(['name' => 'Maths', 'code' => 'TT' . uniqid()]);
        $subject2 = Subject::create(['name' => 'Science', 'code' => 'TT' . uniqid()]);
        $teacher = Teacher::create(['name' => 'T Teacher', 'status' => 'active']);

        // 15-minute gap between periods -- more than the 10-minute requirement.
        $p1 = $this->makeTiming(['period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'order_index' => 1]);
        $p2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '09:00', 'end_time' => '09:45', 'order_index' => 2]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $p1->id,
            'subject_id' => $subject1->id, 'teacher_id' => $teacher->id,
            'room_number' => 'Room 101', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $p2->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject2->id,
            'room_number' => 'Annex 1',
        ]);

        $this->assertFalse($result['conflict'], 'A gap at or above the transfer time must never conflict.');
    }

    public function test_different_buildings_no_gap_conflict(): void
    {
        $main = Building::create(['name' => 'Main Building', 'transfer_time_in_minutes' => 10]);
        $annex = Building::create(['name' => 'Annex Building', 'transfer_time_in_minutes' => 10]);
        Room::create(['room_number' => 'Room 101', 'building_id' => $main->id]);
        Room::create(['room_number' => 'Annex 1', 'building_id' => $annex->id]);

        $class = $this->makeClass();
        $subject1 = Subject::create(['name' => 'Maths', 'code' => 'TT' . uniqid()]);
        $subject2 = Subject::create(['name' => 'Science', 'code' => 'TT' . uniqid()]);
        $teacher = Teacher::create(['name' => 'T Teacher', 'status' => 'active']);

        // Back-to-back periods -- zero gap, less than the 10-minute requirement.
        $p1 = $this->makeTiming(['period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'order_index' => 1]);
        $p2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'order_index' => 2]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $p1->id,
            'subject_id' => $subject1->id, 'teacher_id' => $teacher->id,
            'room_number' => 'Room 101', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $p2->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject2->id,
            'room_number' => 'Annex 1',
        ]);

        $this->assertTrue($result['conflict']);
        $this->assertSame('transfer_time', $result['type']);
        $this->assertStringContainsString('Main Building', $result['message']);
        $this->assertStringContainsString('Annex Building', $result['message']);
    }

    public function test_transfer_time_config_per_building_pair(): void
    {
        $main = Building::create(['name' => 'Main Building', 'transfer_time_in_minutes' => 10]);
        $science = Building::create(['name' => 'Science Block', 'transfer_time_in_minutes' => 10]);

        // Main<->Science Block is a longer walk than the buildings' own
        // 10-minute defaults -- override just this pair to 20 minutes.
        BuildingTransferTime::setPair($main->id, $science->id, 20);

        Room::create(['room_number' => 'Room 101', 'building_id' => $main->id]);
        Room::create(['room_number' => 'Lab 3A', 'building_id' => $science->id]);

        $class = $this->makeClass();
        $subject1 = Subject::create(['name' => 'Maths', 'code' => 'TT' . uniqid()]);
        $subject2 = Subject::create(['name' => 'Science', 'code' => 'TT' . uniqid()]);
        $teacher = Teacher::create(['name' => 'T Teacher', 'status' => 'active']);

        // 15-minute gap -- enough for the 10-minute default, NOT enough for
        // the 20-minute pair override, proving the override is actually used.
        $p1 = $this->makeTiming(['period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'order_index' => 1]);
        $p2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '09:00', 'end_time' => '09:45', 'order_index' => 2]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $p1->id,
            'subject_id' => $subject1->id, 'teacher_id' => $teacher->id,
            'room_number' => 'Room 101', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $p2->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject2->id,
            'room_number' => 'Lab 3A',
        ]);

        $this->assertTrue($result['conflict']);
        $this->assertSame('transfer_time', $result['type']);
        $this->assertStringContainsString('20 minute', $result['message']);
    }

    public function test_3_slots_same_teacher_validate_all_gaps(): void
    {
        $main = Building::create(['name' => 'Main Building', 'transfer_time_in_minutes' => 10]);
        $annex = Building::create(['name' => 'Annex Building', 'transfer_time_in_minutes' => 10]);
        Room::create(['room_number' => 'Room 101', 'building_id' => $main->id]);
        Room::create(['room_number' => 'Room 102', 'building_id' => $main->id]);
        Room::create(['room_number' => 'Annex 1', 'building_id' => $annex->id]);

        $class = $this->makeClass();
        $subject1 = Subject::create(['name' => 'Maths', 'code' => 'TT' . uniqid()]);
        $subject2 = Subject::create(['name' => 'Physics', 'code' => 'TT' . uniqid()]);
        $subject3 = Subject::create(['name' => 'Chemistry', 'code' => 'TT' . uniqid()]);
        $teacher = Teacher::create(['name' => 'T Teacher', 'status' => 'active']);

        // P1 (Main, Room 101) 08:00-08:45 -- same building as P2, fine.
        // P2 (Main, Room 102) 08:45-09:30 -- back-to-back with P1, same
        // building, fine. The new P3 placement (Annex) immediately after
        // P2 with zero gap must be caught against P2 specifically, not
        // just against P1.
        $p1 = $this->makeTiming(['period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'order_index' => 1]);
        $p2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'order_index' => 2]);
        $p3 = $this->makeTiming(['period_name' => 'P3', 'start_time' => '09:30', 'end_time' => '10:15', 'order_index' => 3]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $p1->id,
            'subject_id' => $subject1->id, 'teacher_id' => $teacher->id,
            'room_number' => 'Room 101', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $p2->id,
            'subject_id' => $subject2->id, 'teacher_id' => $teacher->id,
            'room_number' => 'Room 102', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $p3->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject3->id,
            'room_number' => 'Annex 1',
        ]);

        $this->assertTrue($result['conflict']);
        $this->assertSame('transfer_time', $result['type']);
        // Blocked by P2 (the adjacent period), not the more distant P1.
        $blockingSlot = TimetableSlot::where('bell_timing_id', $p2->id)->first();
        $this->assertSame($blockingSlot->id, $result['conflicts'][0]['blocking_slot_id']);
    }
}
