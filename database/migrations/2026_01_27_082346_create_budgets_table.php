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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->year('fiscal_year');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('allocated_amount', 15, 2)->default(0.00);
            $table->decimal('spent_amount', 15, 2)->default(0.00);
            $table->decimal('remaining_amount', 15, 2)->storedAs('allocated_amount - spent_amount');
            $table->enum('status', ['draft', 'approved', 'locked', 'closed'])->default('draft');
            $table->date('approval_date')->nullable();
            $table->date('lock_date')->nullable();
            $table->date('close_date')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['fiscal_year', 'name']);
            $table->index(['fiscal_year', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
