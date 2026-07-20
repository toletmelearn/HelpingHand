<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Exam;
use App\Models\ExamBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExamBlueprintTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->exam = Exam::create([
            'name' => 'Term 1 Geometry Exam',
            'exam_type' => 'mid_term',
            'class_name' => 'Grade 10',
            'subject' => 'Mathematics',
            'exam_date' => '2026-07-15',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'total_marks' => 100.00,
            'passing_marks' => 33.00,
            'academic_year' => '2026',
        ]);
    }

    public function test_blueprint_mapping_page_loads()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.exams.blueprints.index', $this->exam->id));

        $response->assertStatus(200);
        $response->assertSee('Blueprint Mapping for Term 1 Geometry Exam');
    }

    public function test_can_add_blueprint_topic_under_limit()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.exams.blueprints.store', $this->exam->id), [
                'topic_name' => 'Chapter 1: Triangles',
                'competency_level' => 'application',
                'weightage_percentage' => 45.00,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('exam_blueprints', [
            'exam_id' => $this->exam->id,
            'topic_name' => 'Chapter 1: Triangles',
            'weightage_percentage' => 45.00,
        ]);
    }

    public function test_blueprint_prevents_exceeding_100_percent()
    {
        // First mapping
        ExamBlueprint::create([
            'exam_id' => $this->exam->id,
            'topic_name' => 'Chapter 1: Triangles',
            'competency_level' => 'application',
            'weightage_percentage' => 80.00,
        ]);

        // Attempt mapping that pushes total over 100%
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.exams.blueprints.store', $this->exam->id), [
                'topic_name' => 'Chapter 2: Circles',
                'competency_level' => 'recall',
                'weightage_percentage' => 30.00,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('exam_blueprints', [
            'topic_name' => 'Chapter 2: Circles',
        ]);
    }

    public function test_can_remove_blueprint_topic()
    {
        $blueprint = ExamBlueprint::create([
            'exam_id' => $this->exam->id,
            'topic_name' => 'Chapter 1: Triangles',
            'competency_level' => 'application',
            'weightage_percentage' => 20.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('admin.exams.blueprints.destroy', $blueprint->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('exam_blueprints', [
            'id' => $blueprint->id,
        ]);
    }
}
