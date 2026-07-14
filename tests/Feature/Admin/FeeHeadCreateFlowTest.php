<?php

namespace Tests\Feature\Admin;

use App\Models\FeeType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeHeadCreateFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_create_a_new_fee_head_end_to_end()
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        $this->actingAs($admin)
            ->get(route('admin.fee-types.create'))
            ->assertOk();

        $response = $this->actingAs($admin)->post(route('admin.fee-types.store'), [
            'name' => 'Field Trip Fee',
            'description' => 'Annual field trip contribution',
            'category' => 'optional',
            'default_frequency' => 'yearly',
            'is_optional' => '1',
        ]);

        $response->assertRedirect(route('admin.fee-types.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('fee_types', [
            'name' => 'Field Trip Fee',
            'status' => 'active',
            'category' => 'optional',
            'default_frequency' => 'yearly',
            'is_optional' => true,
        ]);

        // The new head must immediately show up in the fee structure builder's
        // checkbox list (FeeType::active()) -- the whole point of adding one.
        $this->actingAs($admin)
            ->get(route('admin.fee-structures.create'))
            ->assertOk()
            ->assertSee('Field Trip Fee');
    }

    /** @test */
    public function duplicate_fee_head_name_is_rejected_with_validation_error()
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        FeeType::create(['name' => 'Existing Fee', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.fee-types.store'), [
            'name' => 'Existing Fee',
            'category' => 'recurring',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertEquals(1, FeeType::where('name', 'Existing Fee')->count());
    }
}
