<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AdmitCard::transitionTo('revoked', ...) has always written
     * revoked_at/revoked_by -- but the original migration only ever added
     * published_at/published_by, so revoking an admit card (the entire
     * point of the "Block Fee Defaulters" feature) throws a real SQL error
     * the moment it actually finds a genuine defaulter to revoke.
     */
    public function up(): void
    {
        if (!Schema::hasTable('admit_cards') || Schema::hasColumn('admit_cards', 'revoked_at')) {
            return;
        }

        Schema::table('admit_cards', function (Blueprint $table) {
            $table->foreignId('revoked_by')->nullable()->after('published_at')->constrained('users')->onDelete('set null');
            $table->timestamp('revoked_at')->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('admit_cards') || !Schema::hasColumn('admit_cards', 'revoked_at')) {
            return;
        }

        Schema::table('admit_cards', function (Blueprint $table) {
            $table->dropForeign(['revoked_by']);
            $table->dropColumn(['revoked_by', 'revoked_at']);
        });
    }
};
