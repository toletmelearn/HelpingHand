<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('advanced_reports')) {
            Schema::create('advanced_reports', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type');
                $table->string('module');
                $table->json('filters')->nullable();
                $table->json('columns')->nullable();
                $table->string('chart_type')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                // If users table exists, add FK, otherwise skip
                if (Schema::hasTable('users')) {
                    try {
                        $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                    } catch (\Throwable $e) {
                        // ignore FK creation errors to keep migration safe
                    }
                }

                $table->index(['module', 'type']);
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('advanced_reports')) {
            Schema::dropIfExists('advanced_reports');
        }
    }
};
