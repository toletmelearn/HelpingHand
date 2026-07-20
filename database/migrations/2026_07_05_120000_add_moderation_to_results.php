<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->decimal('original_marks_obtained', 8, 2)->nullable();
            $table->decimal('grace_marks_applied', 8, 2)->default(0);
            $table->unsignedBigInteger('moderated_by')->nullable();
            $table->string('moderation_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn([
                'original_marks_obtained',
                'grace_marks_applied',
                'moderated_by',
                'moderation_reason'
            ]);
        });
    }
};
