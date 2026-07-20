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
        Schema::table('results', function (Blueprint $table) {
            $table->unsignedBigInteger('class_id')->nullable()->after('subject');
            $table->unsignedBigInteger('subject_id')->nullable()->after('class_id');
            
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('set null');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropForeign(['subject_id']);
            $table->dropColumn(['class_id', 'subject_id']);
        });
    }
};
