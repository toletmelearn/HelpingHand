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
        if (!Schema::hasTable('processed_stripe_events')) {
            Schema::create('processed_stripe_events', function (Blueprint $table) {
                $table->string('event_id')->primary();
                $table->string('event_type');
                $table->string('payment_intent')->nullable();
                $table->string('status')->default('success'); // success, failed, retrying
                $table->text('error_message')->nullable();
                $table->text('payload_hash');
                $table->integer('attempts')->default(1);
                $table->timestamp('processed_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_stripe_events');
    }
};
