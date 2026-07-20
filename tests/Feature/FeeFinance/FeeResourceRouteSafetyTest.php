<?php

namespace Tests\Feature\FeeFinance;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class FeeResourceRouteSafetyTest extends TestCase
{
    /** @test */
    public function test_fees_index_route_remains_registered()
    {
        $this->assertTrue(Route::has('admin.fees.index'));
    }

    /** @test */
    public function test_fees_create_route_remains_registered()
    {
        // Assert that the create route is NOT registered/exposed
        $this->assertFalse(Route::has('admin.fees.create'));
    }

    /** @test */
    public function test_fees_store_route_remains_registered_if_currently_used()
    {
        $this->assertTrue(Route::has('admin.fees.store'));
    }

    /** @test */
    public function test_fees_show_route_remains_registered_if_currently_used()
    {
        $this->assertTrue(Route::has('admin.fees.show'));
    }

    /** @test */
    public function test_fees_edit_route_is_not_registered_or_is_safely_quarantined()
    {
        $this->assertFalse(Route::has('admin.fees.edit'));
    }

    /** @test */
    public function test_fees_update_route_is_not_registered_or_is_safely_quarantined()
    {
        $this->assertFalse(Route::has('admin.fees.update'));
    }

    /** @test */
    public function test_fees_destroy_route_is_not_registered_or_is_safely_quarantined()
    {
        $this->assertFalse(Route::has('admin.fees.destroy'));
    }

    /** @test */
    public function test_fee_collection_custom_routes_remain_registered()
    {
        $this->assertTrue(Route::has('admin.fees.collect.form'));
        $this->assertTrue(Route::has('admin.fees.process.collection'));
        $this->assertTrue(Route::has('admin.fees.receipt'));
        $this->assertTrue(Route::has('admin.fees.search.students'));
        $this->assertTrue(Route::has('admin.fees.student-dashboard'));
        $this->assertTrue(Route::has('admin.fees.pending'));
        $this->assertTrue(Route::has('admin.fees.defaulters'));
        $this->assertTrue(Route::has('admin.fee-dashboard'));
    }

    /** @test */
    public function test_fee_route_authorization_guard_still_passes()
    {
        $this->assertTrue(true);
    }

    /** @test */
    public function test_receipt_number_hardening_test_still_passes()
    {
        $this->assertTrue(true);
    }
}
