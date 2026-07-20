<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Mirrors is_rte -- a legally/policy-significant, always-present
            // flag rather than a free-text category value, so the discount
            // engine's special_needs rule type has something reliable to
            // check.
            $table->boolean('is_special_needs')->default(false)->after('is_rte');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_special_needs');
        });
    }
};
