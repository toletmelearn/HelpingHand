<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StudentPromotionRouteSafetyTest extends TestCase
{
    public function test_implemented_student_promotion_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('admin.student-promotions.index'));
        $this->assertTrue(Route::has('admin.student-promotions.create'));
        $this->assertTrue(Route::has('admin.student-promotions.store'));
    }

    public function test_unimplemented_student_promotion_resource_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('admin.student-promotions.show'));
        $this->assertFalse(Route::has('admin.student-promotions.edit'));
        $this->assertFalse(Route::has('admin.student-promotions.update'));
        $this->assertFalse(Route::has('admin.student-promotions.destroy'));
    }

    public function test_custom_promotion_history_and_passed_out_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('admin.student-promotions.get-students'));
        $this->assertTrue(Route::has('admin.student-promotions.get-destination-classes'));
        $this->assertTrue(Route::has('admin.student-promotions.history'));
        $this->assertTrue(Route::has('admin.student-promotions.passed-out'));
    }
}
