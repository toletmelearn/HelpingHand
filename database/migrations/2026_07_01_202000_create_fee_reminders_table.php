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
        Schema::create('fee_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_id')->constrained('fees')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('rule'); // 'Before Due Date', 'After Due Date', 'Repeated Reminder', 'Final Notice'
            $table->string('channel'); // 'sms', 'whatsapp', 'email', 'notification'
            $table->string('recipient');
            $table->text('message');
            $table->string('status')->default('pending'); // 'pending', 'sent', 'failed'
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Index to optimize lookup of duplicate dispatches and pending retries
            $table->index(['fee_id', 'rule', 'channel']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_reminders');
    }
};
