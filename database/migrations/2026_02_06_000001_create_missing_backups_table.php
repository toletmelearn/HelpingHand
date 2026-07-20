<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('backups')) {
            Schema::create('backups', function (Blueprint $table) {
                $table->id();
                $table->string('filename');
                $table->string('path');
                $table->string('type');
                $table->string('location');
                $table->unsignedBigInteger('size')->default(0);
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                if (Schema::hasTable('users')) {
                    try {
                        $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                    } catch (\Throwable $e) {
                        // ignore foreign key errors to keep migration safe
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('backups')) {
            Schema::dropIfExists('backups');
        }
    }
};
