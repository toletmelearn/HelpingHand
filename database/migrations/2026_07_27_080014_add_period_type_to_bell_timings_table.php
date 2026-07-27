<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bell_timings', function (Blueprint $table) {
            $table->enum('period_type', ['teaching', 'assembly', 'prayer', 'break', 'zero', 'dispersal'])
                ->default('teaching')
                ->after('is_break');
        });

        // Keep existing semantics intact: rows already flagged is_break=true
        // become period_type='break' so capacity/solver math (which will
        // switch to filtering on period_type) doesn't change behaviour for
        // any pre-existing row until an admin explicitly assigns
        // assembly/prayer/zero/dispersal to a period.
        DB::table('bell_timings')->where('is_break', true)->update(['period_type' => 'break']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bell_timings', function (Blueprint $table) {
            $table->dropColumn('period_type');
        });
    }
};
