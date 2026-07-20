<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // RTE (Right to Education) admissions were previously handled
            // as a one-off manual billing correction with no reusable flag
            // anywhere in the schema -- this makes it a real, queryable
            // student attribute the discount engine's new rte_quota rule
            // type can check automatically.
            $table->boolean('is_rte')->default(false)->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_rte');
        });
    }
};
