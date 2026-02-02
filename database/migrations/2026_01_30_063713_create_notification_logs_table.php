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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_setting_id')->constrained()->onDelete('cascade');
            $table->string('recipient_type'); // student, parent, teacher, admin, class, section
            $table->unsignedBigInteger('recipient_id')->nullable(); // polymorphic ID
            $table->string('notification_type'); // sms, email
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
            
            $table->index(['status', 'sent_at']);
            $table->index('notification_type');
            $table->index(['recipient_type', 'recipient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
