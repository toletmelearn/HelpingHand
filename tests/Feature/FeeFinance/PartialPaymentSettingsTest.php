<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AdminConfiguration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * fine_on_unpaid_balance / minimum_payment_amount are new 'fee' module
 * AdminConfiguration keys -- confirms they persist through the real
 * generic settings screen (AdminConfigurationController), the same store
 * ParentPaymentController reads at request time.
 */
class PartialPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);
        return $admin;
    }

    public function test_admin_can_save_the_two_new_fee_settings()
    {
        $admin = $this->makeAdmin();

        // Visiting the index first auto-creates default rows for every
        // configured key (AdminConfigurationController::index()).
        $this->actingAs($admin)->get(route('admin.configurations.index'));

        $fineConfig = AdminConfiguration::forModule('fee')->forKey('fine_on_unpaid_balance')->first();
        $minConfig = AdminConfiguration::forModule('fee')->forKey('minimum_payment_amount')->first();
        $this->assertNotNull($fineConfig);
        $this->assertNotNull($minConfig);
        $this->assertFalse($fineConfig->getValue());

        $response = $this->actingAs($admin)->post(route('admin.configurations.update'), [
            'configurations' => [
                ['module' => 'fee', 'key' => 'fine_on_unpaid_balance', 'value' => '1'],
                ['module' => 'fee', 'key' => 'minimum_payment_amount', 'value' => '500'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertTrue(AdminConfiguration::get('fee', 'fine_on_unpaid_balance', false));
        $this->assertEquals('500', AdminConfiguration::get('fee', 'minimum_payment_amount', null));
    }
}
