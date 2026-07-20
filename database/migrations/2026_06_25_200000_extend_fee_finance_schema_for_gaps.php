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
        // 1. Create late_fee_rules table
        if (!Schema::hasTable('late_fee_rules')) {
            Schema::create('late_fee_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type'); // flat, daily_incremental, slab
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->integer('grace_days')->default(0);
                $table->json('slab_config')->nullable();
                $table->decimal('max_limit', 10, 2)->nullable();
                $table->timestamps();
            });
        }

        // 2. Add columns to fee_types table
        if (Schema::hasTable('fee_types')) {
            Schema::table('fee_types', function (Blueprint $table) {
                if (!Schema::hasColumn('fee_types', 'category')) {
                    $table->string('category')->default('recurring');
                }
            });
        }

        // 3. Add columns to fee_structure_items table
        if (Schema::hasTable('fee_structure_items')) {
            Schema::table('fee_structure_items', function (Blueprint $table) {
                if (!Schema::hasColumn('fee_structure_items', 'late_fee_rule_id')) {
                    $table->unsignedBigInteger('late_fee_rule_id')->nullable();
                    $table->foreign('late_fee_rule_id')->references('id')->on('late_fee_rules')->onDelete('set null');
                }
                if (!Schema::hasColumn('fee_structure_items', 'exam_id')) {
                    $table->unsignedBigInteger('exam_id')->nullable();
                }
            });
        }

        // 4. Create fee_structure_item_installments table
        if (!Schema::hasTable('fee_structure_item_installments')) {
            Schema::create('fee_structure_item_installments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fee_structure_item_id');
                $table->string('month');
                $table->decimal('amount', 10, 2);
                $table->timestamps();

                $table->foreign('fee_structure_item_id')
                    ->references('id')
                    ->on('fee_structure_items')
                    ->onDelete('cascade');
            });
        }

        // 5. Add snapshot columns to student_transport_dues table
        if (Schema::hasTable('student_transport_dues')) {
            Schema::table('student_transport_dues', function (Blueprint $table) {
                if (!Schema::hasColumn('student_transport_dues', 'route_name_snapshot')) {
                    $table->string('route_name_snapshot')->nullable();
                }
                if (!Schema::hasColumn('student_transport_dues', 'stop_name_snapshot')) {
                    $table->string('stop_name_snapshot')->nullable();
                }
                if (!Schema::hasColumn('student_transport_dues', 'fare_snapshot')) {
                    $table->decimal('fare_snapshot', 10, 2)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_transport_dues')) {
            Schema::table('student_transport_dues', function (Blueprint $table) {
                $table->dropColumn(['route_name_snapshot', 'stop_name_snapshot', 'fare_snapshot']);
            });
        }

        Schema::dropIfExists('fee_structure_item_installments');

        if (Schema::hasTable('fee_structure_items')) {
            Schema::table('fee_structure_items', function (Blueprint $table) {
                $table->dropForeign(['late_fee_rule_id']);
                $table->dropColumn(['late_fee_rule_id', 'exam_id']);
            });
        }

        if (Schema::hasTable('fee_types')) {
            Schema::table('fee_types', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }

        Schema::dropIfExists('late_fee_rules');
    }
};
