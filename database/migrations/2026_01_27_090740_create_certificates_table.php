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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_type'); // TC, Bonafide, Character, Experience
            $table->string('serial_number')->unique();
            $table->unsignedBigInteger('recipient_id'); // Student or Teacher ID
            $table->string('recipient_type'); // App\Models\Student or App\Models\Teacher
            $table->text('content_data'); // JSON field containing certificate data
            $table->text('formatted_content')->nullable(); // Rendered HTML content
            $table->string('status')->default('draft'); // draft, generated, published, locked, revoked
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
