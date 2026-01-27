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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('transaction_type', ['issue', 'return', 'transfer', 'repair', 'scrap', 'damage', 'maintenance']);
            $table->integer('quantity')->default(1);
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable(); // user who received asset
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
