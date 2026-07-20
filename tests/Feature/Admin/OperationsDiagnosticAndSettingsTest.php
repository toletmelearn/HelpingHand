<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\AdminConfiguration;
use App\Services\Operations\DiagnosticEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class OperationsDiagnosticAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup admin role and user
        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);
    }

    /**
     * Test erp:diagnose Artisan CLI command.
     */
    public function test_diagnose_command_executes_successfully()
    {
        $code = Artisan::call('erp:diagnose');
        $this->assertEquals(0, $code);

        $output = Artisan::output();
        $this->assertStringContainsString('HelpingHand ERP System Diagnostics Tool', $output);
        $this->assertStringContainsString('Database Connectivity', $output);
        $this->assertStringContainsString('Writable Storage Paths', $output);
    }

    /**
     * Test system health diagnostics web endpoint.
     */
    public function test_web_health_dashboard_endpoint()
    {
        // Setup cache scheduler pulse so scheduler check is green
        Cache::put('scheduler_heartbeat', time(), 10);

        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.health'));

        $response->assertStatus(200);
        $response->assertViewHas('results');
        $response->assertViewHas('metrics');

        $results = $response->viewData('results');
        $this->assertCount(5, $results); // Database, Storage, Cache, Queue, Scheduler
    }

    /**
     * Test configurations hub display and persistence update operations.
     */
    public function test_unified_configurations_hub_persistence()
    {
        // 1. Check settings view loads initial default values
        $response = $this->actingAs($this->adminUser)
            ->get(route('operations.settings'));

        $response->assertStatus(200);
        $response->assertViewHas('config');
        $response->assertSee('HelpingHand School');

        // 2. Perform settings batch update request
        $updatePayload = [
            'school_name' => 'Green Valley High',
            'school_email' => 'contact@greenvalley.test',
            'school_phone' => '+91 9876543210',
            'school_address' => '456 Hilltop Ave, Green Valley',
            'stripe_mode' => 'live',
            'stripe_publishable_key' => 'pk_live_12345',
            'stripe_secret_key' => 'sk_live_67890',
            'password_min_length' => 12,
            'session_timeout_minutes' => 60,
        ];

        $postResponse = $this->actingAs($this->adminUser)
            ->post(route('operations.settings.update'), $updatePayload);

        $postResponse->assertRedirect(route('operations.settings'));
        $postResponse->assertSessionHas('success');

        // 3. Assert updated configurations exist in DB
        $this->assertEquals('Green Valley High', AdminConfiguration::get('general', 'school_name'));
        $this->assertEquals('contact@greenvalley.test', AdminConfiguration::get('general', 'school_email'));
        $this->assertEquals('live', AdminConfiguration::get('finance', 'stripe_mode'));
        $this->assertEquals('pk_live_12345', AdminConfiguration::get('finance', 'stripe_publishable_key'));
        $this->assertEquals(12, AdminConfiguration::get('security', 'password_min_length'));
        $this->assertEquals(60, AdminConfiguration::get('security', 'session_timeout_minutes'));
    }
}
