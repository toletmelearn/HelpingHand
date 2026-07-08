<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_enquiry_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_enquiry_id')->constrained('admission_enquiries')->onDelete('cascade');
            $table->string('document_type'); // birth_certificate, aadhaar_card, transfer_certificate, photo, etc.
            $table->string('document_path');
            $table->string('original_filename');
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->string('file_size')->nullable();
            $table->string('file_mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_enquiry_documents');
    }
};
