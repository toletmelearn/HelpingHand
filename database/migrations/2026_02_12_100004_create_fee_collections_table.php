<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for fee_collections table.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
        Schema::dropIfExists('fee_collections');
        Schema::create('fee_collections', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique(); // auto generate unique
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('late_fine', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2); // calculated: total - discount + late_fine
            $table->date('payment_date');
            $table->enum('payment_mode', ['cash', 'upi', 'bank', 'online']);
            $table->text('remarks')->nullable();
            $table->foreignId('collected_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('receipt_no');
            $table->index('payment_date');
            $table->index('payment_mode');
        });
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_collections');
    }
};