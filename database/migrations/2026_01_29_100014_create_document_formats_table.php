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
        Schema::create('document_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable(); // e.g., 'certificate', 'admit-card', 'report', etc.
            $table->text('description')->nullable();
            $table->json('template_content')->nullable(); // Stores the HTML template structure
            $table->json('css_styles')->nullable(); // Stores CSS styling information
            $table->text('header_content')->nullable();
            $table->text('footer_content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('page_size', 20)->default('A4'); // e.g., A4, Letter, Legal
            $table->enum('orientation', ['portrait', 'landscape'])->default('portrait');
            $table->decimal('margin_top', 8, 2)->default(10.00);
            $table->decimal('margin_bottom', 8, 2)->default(10.00);
            $table->decimal('margin_left', 8, 2)->default(10.00);
            $table->decimal('margin_right', 8, 2)->default(10.00);
            $table->timestamps();
        });
        
        // Add indexes for better performance
        Schema::table('document_formats', function (Blueprint $table) {
            $table->index('type');
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_formats');
    }
};
