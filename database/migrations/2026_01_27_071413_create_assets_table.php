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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->unsignedBigInteger('category_id');
            $table->string('vendor')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->text('warranty_details')->nullable();
            $table->enum('condition', ['new', 'good', 'repair', 'scrap'])->default('good');
            $table->enum('status', ['active', 'in_use', 'under_repair', 'disposed'])->default('active');
            $table->text('description')->nullable();
            $table->string('location')->nullable(); // room/class/lab
            $table->string('serial_number')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('available_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('category_id')->references('id')->on('asset_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
