<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\GateEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class VisitorGateTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);
    }

    public function test_visitor_log_dashboard_loads()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('visitor.log'));

        $response->assertStatus(200);
        $response->assertSee('Visitor');
    }

    public function test_can_check_in_visitor_successfully()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('visitor.checkin'), [
                'visitor_name' => 'Elon Musk',
                'purpose' => 'Business Discussion',
                'vehicle_no' => 'TSLA-001',
                'host_user_id' => $this->adminUser->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('gate_entries', [
            'visitor_name' => 'Elon Musk',
            'purpose' => 'Business Discussion',
            'vehicle_no' => 'TSLA-001',
            'host_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_can_check_out_visitor()
    {
        $visitor = GateEntry::create([
            'visitor_name' => 'Steve Jobs',
            'purpose' => 'Keynote Presentation',
            'check_in' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('visitor.checkout', $visitor->id));

        $response->assertRedirect();
        $this->assertNotNull($visitor->fresh()->check_out);
    }
}
