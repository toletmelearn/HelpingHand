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
        Schema::table('teachers', function (Blueprint $table) {
            // Add password column if it doesn't exist
            if (!Schema::hasColumn('teachers', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
            
            // Add index for email (often used for login)
            if (!Schema::hasIndex('teachers', 'teachers_email_unique')) {
                $table->unique('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // Drop password column
            $table->dropColumn('password');
            
            // Drop email unique index
            $table->dropUnique(['email']);
        });
    }
};
