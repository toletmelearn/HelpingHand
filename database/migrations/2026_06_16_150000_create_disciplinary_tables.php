<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->date('incident_date');
            $table->string('title');
            $table->text('description');
            $table->foreignId('reported_by')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('demerit_points')->default(0);
            $table->string('status')->default('investigating'); // investigating, action_taken, resolved
            $table->timestamps();
        });

        Schema::create('disciplinary_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('disciplinary_incidents')->onDelete('cascade');
            $table->string('action_type'); // counselling, warning_letter, suspension, fine
            $table->text('action_details');
            $table->timestamp('parent_notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_actions');
        Schema::dropIfExists('disciplinary_incidents');
    }
};
