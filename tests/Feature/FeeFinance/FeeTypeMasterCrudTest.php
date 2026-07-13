<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fee-head master CRUD screen (FeeTypeController) -- separate from
 * FeeStructureController::feeTypeMaster(), which only edits *defaults* on
 * existing rows. This covers identity: create, rename, deactivate, delete.
 */
class FeeTypeMasterCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccountant(): User
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $role = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $accountant->roles()->attach($role->id);
        return $accountant;
    }

    public function test_accountant_can_create_a_fee_head()
    {
        $accountant = $this->makeAccountant();

        $response = $this->actingAs($accountant)->post(route('admin.fee-types.store'), [
            'name' => 'Field Trip',
            'description' => 'Annual field trip charges',
            'category' => 'recurring',
            'default_frequency' => 'yearly',
        ]);

        $response->assertRedirect(route('admin.fee-types.index'));
        $this->assertDatabaseHas('fee_types', [
            'name' => 'Field Trip',
            'status' => 'active',
        ]);
    }

    public function test_fee_head_name_must_be_unique()
    {
        $accountant = $this->makeAccountant();
        FeeType::create(['name' => 'Hostel', 'status' => 'active']);

        $response = $this->actingAs($accountant)->post(route('admin.fee-types.store'), [
            'name' => 'Hostel',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_accountant_can_update_a_fee_head()
    {
        $accountant = $this->makeAccountant();
        $type = FeeType::create(['name' => 'Mess', 'status' => 'active']);

        $response = $this->actingAs($accountant)->put(route('admin.fee-types.update', $type->id), [
            'name' => 'Mess Fee',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect(route('admin.fee-types.index'));
        $this->assertDatabaseHas('fee_types', ['id' => $type->id, 'name' => 'Mess Fee']);
    }

    public function test_renaming_to_an_existing_name_is_rejected()
    {
        $accountant = $this->makeAccountant();
        FeeType::create(['name' => 'Late Fine', 'status' => 'active']);
        $target = FeeType::create(['name' => 'Activity', 'status' => 'active']);

        $response = $this->actingAs($accountant)->put(route('admin.fee-types.update', $target->id), [
            'name' => 'Late Fine',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_accountant_can_deactivate_and_reactivate_a_fee_head()
    {
        $accountant = $this->makeAccountant();
        $type = FeeType::create(['name' => 'Development Fund', 'status' => 'active']);

        $this->actingAs($accountant)->put(route('admin.fee-types.deactivate', $type->id));
        $this->assertEquals('inactive', $type->fresh()->status);

        $this->actingAs($accountant)->put(route('admin.fee-types.activate', $type->id));
        $this->assertEquals('active', $type->fresh()->status);
    }

    public function test_accountant_can_soft_delete_a_fee_head()
    {
        $accountant = $this->makeAccountant();
        $type = FeeType::create(['name' => 'Registration', 'status' => 'active']);

        $this->actingAs($accountant)->delete(route('admin.fee-types.destroy', $type->id));

        $this->assertSoftDeleted('fee_types', ['id' => $type->id]);
    }

    public function test_unauthenticated_user_cannot_reach_fee_type_routes()
    {
        $response = $this->get(route('admin.fee-types.index'));

        $response->assertRedirect(route('login'));
    }
}
