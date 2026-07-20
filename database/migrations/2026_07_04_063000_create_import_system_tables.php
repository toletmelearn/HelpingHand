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
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('module', 50);
            $table->string('status', 30);
            $table->json('column_mappings')->nullable();
            $table->json('settings')->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('success_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->integer('resume_pointer')->default(0);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['module', 'status']);
        });

        Schema::create('import_mapping_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('profile_name', 100);
            $table->string('module', 50);
            $table->json('mappings');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['module']);
        });

        Schema::create('import_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_name', 100);
            $table->string('version', 20);
            $table->string('file_path', 255)->nullable();
            $table->json('schema_definition')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_session_id');
            $table->integer('row_number');
            $table->json('raw_row_data')->nullable();
            $table->text('error_message');
            $table->timestamps();

            $table->foreign('import_session_id')
                ->references('id')
                ->on('import_sessions')
                ->onDelete('cascade');
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('field_name', 100);
            $table->string('field_type', 30);
            $table->json('validation_rules')->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['module']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('custom_field_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->text('field_value')->nullable();
            $table->timestamps();

            $table->foreign('custom_field_id')
                ->references('id')
                ->on('custom_fields')
                ->onDelete('cascade');

            $table->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_templates');
        Schema::dropIfExists('import_mapping_profiles');
        Schema::dropIfExists('import_sessions');
    }
};
