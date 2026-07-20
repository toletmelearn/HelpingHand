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
        Schema::table('homework_notices', function (Blueprint $table) {
            $table->boolean('visible_to_parent')->default(false)->after('priority');
            $table->string('attachment_path')->nullable()->after('visible_to_parent');
            $table->text('parent_notes')->nullable()->after('attachment_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('homework_notices', function (Blueprint $table) {
            $table->dropColumn(['visible_to_parent', 'attachment_path', 'parent_notes']);
        });
    }
};
