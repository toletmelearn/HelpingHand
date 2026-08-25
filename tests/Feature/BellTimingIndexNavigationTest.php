<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timetable + Bell Timing V1 completion pass: bell-timing/index.blade.php
 * (and every other view under resources/views/bell-timing/*) is a
 * standalone HTML document -- it does not @extend('layouts.admin') the
 * way its own daily/weekly siblings do, so it previously had no link back
 * to the rest of the app at all; an admin landing here had to use the
 * browser Back button. Every other bell-timing/* view already links back
 * to this index (see create.blade.php's "Back to List", etc.), so this is
 * the one page whose header needed a way out. Locks in the fix rather
 * than re-testing the whole navigation tree.
 */
class BellTimingIndexNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_links_back_to_the_admin_dashboard(): void
    {
        $response = $this->actingAs($this->admin())->get(route('bell-timing.index'));

        $response->assertOk();
        $response->assertSee(route('admin.dashboard'), false);
    }
}
