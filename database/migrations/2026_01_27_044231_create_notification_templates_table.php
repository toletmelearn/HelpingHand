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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('event_type'); // late_arrival, early_departure, absence, monthly_summary
            $table->string('channel'); // sms, email, push
            $table->string('subject')->nullable(); // for email
            $table->text('template_content');
            $table->json('variables')->nullable(); // available variables for substitution
            $table->boolean('is_active')->default(true);
            $table->integer('delay_minutes')->default(0); // delay before sending
            $table->text('recipient_roles')->nullable(); // json array of roles
            $table->text('conditions')->nullable(); // json conditions for triggering
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['event_type', 'channel']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
