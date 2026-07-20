<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notebook_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->date('check_date');
            $table->string('deficiencies')->nullable(); // json array or string: incomplete_work, poor_handwriting, etc.
            $table->date('recheck_date')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_signed')->default(false);
            $table->foreignId('checked_by')->nullable()->constrained('teachers')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notebook_checks');
    }
};
