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
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('notification_type'); // sms, email, both
            $table->boolean('is_enabled')->default(true);
            $table->string('template_subject')->nullable();
            $table->text('template_body');
            $table->json('recipients')->nullable(); // who receives: students, parents, teachers, admins
            $table->json('conditions')->nullable(); // trigger conditions
            $table->string('schedule_type')->default('immediate'); // immediate, daily, weekly
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['event_type', 'is_enabled']);
            $table->index('notification_type');
            $table->index('schedule_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
