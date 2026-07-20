<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        putenv('SESSION_DRIVER=array');
        putenv('CACHE_STORE=array');
        parent::setUp();

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens') && !\Illuminate\Support\Facades\Schema::hasColumn('personal_access_tokens', 'token')) {
                \Illuminate\Support\Facades\Schema::table('personal_access_tokens', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->morphs('tokenable');
                    $table->string('name');
                    $table->string('token', 64)->unique();
                    $table->text('abilities')->nullable();
                    $table->timestamp('last_used_at')->nullable();
                    $table->timestamp('expires_at')->nullable();
                });
            }
        } catch (\Exception $e) {
            // Silence DB exceptions
        }
    }
}
