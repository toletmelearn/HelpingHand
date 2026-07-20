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
        // Make class_id nullable to allow general sections if it still exists
        if (Schema::hasColumn('sections', 'class_id')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->unsignedBigInteger('class_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to non-nullable (but this may fail if there are null values)
        Schema::table('sections', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->nullable(false)->change();
        });
    }
};
