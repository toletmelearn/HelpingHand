<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        }
        if (Schema::hasColumn('sections', 'class_id')) {
            try {
                Schema::table('sections', function (Blueprint $table) {
                    $table->dropForeign('sections_class_id_foreign');
                });
            } catch (\Exception $e) {}
            try {
                Schema::table('sections', function (Blueprint $table) {
                    $table->dropColumn('class_id');
                });
            } catch (\Exception $e) {}
        }
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('class_id')->constrained('class_management')->onDelete('cascade');
        });
    }
};
