<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Services\BulkFeeAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported directly: BulkFeeAssignmentService inserted a chunk's
 * StudentFeeAssignment rows as one raw multi-row statement -- if any single
 * student in that chunk was already assigned (a real, expected case when
 * assignment is re-run, e.g. after adding one new student to a class),
 * the unique-constraint violation failed the WHOLE chunk, including every
 * other, genuinely-new assignment in it.
 */
class BulkAssignmentFaultIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_already_assigned_student_does_not_block_the_rest_of_the_chunk()
    {
        $class = SchoolClass::create(['name' => 'Class 3', 'class_order' => 3, 'is_active' => true]);
        $feeType = FeeType::create(['name' => 'Tuition Fee', 'status' => 'active']);
        $structure = FeeStructure::create([
            'class_name' => 'Class 3', 'academic_year' => '2026-2027',
            'frequency' => 'yearly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $feeType->id,
            'amount' => 1000, 'billing_frequency' => 'one_time', 'charge_months' => ['Annual'],
        ]);

        $students = collect(range(1, 5))->map(function ($i) use ($class) {
            return Student::create([
                'name' => "Bulk Kid {$i}", 'father_name' => 'Father', 'mother_name' => 'Mother',
                'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
                'aadhaar_number' => (string) random_int(100000000000, 999999999999),
                'phone' => '999888' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'address' => 'Somewhere',
                'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
            ]);
        });

        // Student #3 is already assigned (e.g. from an earlier run).
        $alreadyAssigned = $students[2];
        BulkFeeAssignmentService::bulkAssign($structure, [$alreadyAssigned->id]);
        $this->assertEquals(1, StudentFeeAssignment::where('student_id', $alreadyAssigned->id)->count());

        // Re-running for all 5 (as if a new student was just added to the
        // class and assignment is re-run for the whole class) must not
        // throw, and must still assign the other 4.
        BulkFeeAssignmentService::bulkAssign($structure, $students->pluck('id')->all());

        foreach ($students as $student) {
            $this->assertEquals(
                1,
                StudentFeeAssignment::where('student_id', $student->id)->where('fee_structure_id', $structure->id)->count(),
                "Student {$student->id} should have exactly one assignment, not zero or duplicated."
            );
        }
    }
}
