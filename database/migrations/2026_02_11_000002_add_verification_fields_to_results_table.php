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
            // Add verification fields for multi-subject result system
            $table->boolean('is_verified')->default(false)->after('is_locked');
            $table->unsignedBigInteger('verified_by')->nullable()->after('is_verified');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('verification_comments')->nullable()->after('verified_at');
            
            // Add foreign key for verified_by
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            
            // Add index for verification status
            $table->index(['is_verified', 'verified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropIndex(['is_verified', 'verified_at']);
            
            $table->dropColumn([
                'is_verified',
                'verified_by',
                'verified_at',
                'verification_comments'
            ]);
        });
    }
};