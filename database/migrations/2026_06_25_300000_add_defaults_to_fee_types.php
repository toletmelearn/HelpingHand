<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('fee_types')) {
            Schema::table('fee_types', function (Blueprint $table) {
                if (!Schema::hasColumn('fee_types', 'default_frequency')) {
                    $table->string('default_frequency')->default('monthly');
                }
                if (!Schema::hasColumn('fee_types', 'default_charge_months')) {
                    $table->json('default_charge_months')->nullable();
                }
                if (!Schema::hasColumn('fee_types', 'default_late_fee_rule_id')) {
                    $table->unsignedBigInteger('default_late_fee_rule_id')->nullable();
                    $table->foreign('default_late_fee_rule_id')->references('id')->on('late_fee_rules')->onDelete('set null');
                }
            });
        }

        // Seed defaults for standard fee types
        $defaults = [
            'Tuition Fee' => [
                'default_frequency' => 'monthly',
                'default_charge_months' => ["April", "May", "July", "August", "September", "October", "November", "December", "January", "February", "March"]
            ],
            'Transport Fee' => [
                'default_frequency' => 'monthly',
                'default_charge_months' => ["April", "May", "July", "August", "September", "October", "November", "December", "January", "February", "March"]
            ],
            'Admission Fee' => [
                'default_frequency' => 'session_wise_admission',
                'default_charge_months' => ["April"]
            ],
            'Computer Fee' => [
                'default_frequency' => 'yearly',
                'default_charge_months' => ["April"]
            ],
            'Smart Class Fee' => [
                'default_frequency' => 'monthly',
                'default_charge_months' => ["April", "May", "July", "August", "September", "October", "November", "December", "January", "February", "March"]
            ],
            'Exam Fee' => [
                'default_frequency' => 'custom',
                'default_charge_months' => ["August", "December", "March"]
            ],
            'Annual Charges' => [
                'default_frequency' => 'yearly',
                'default_charge_months' => ["April"]
            ],
            'Library Fee' => [
                'default_frequency' => 'yearly',
                'default_charge_months' => ["April"]
            ],
            'Sports Fee' => [
                'default_frequency' => 'yearly',
                'default_charge_months' => ["April"]
            ],
            'Lab Fee' => [
                'default_frequency' => 'yearly',
                'default_charge_months' => ["April"]
            ],
            'ID Card Fee' => [
                'default_frequency' => 'yearly',
                'default_charge_months' => ["April"]
            ],
            'Miscellaneous Fee' => [
                'default_frequency' => 'yearly',
                'default_charge_months' => ["April"]
            ],
            'Uniform Fee' => [
                'default_frequency' => 'yearly',
                'default_charge_months' => ["April"]
            ]
        ];

        foreach ($defaults as $name => $data) {
            DB::table('fee_types')
                ->where('name', $name)
                ->update([
                    'default_frequency' => $data['default_frequency'],
                    'default_charge_months' => json_encode($data['default_charge_months'])
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('fee_types')) {
            Schema::table('fee_types', function (Blueprint $table) {
                $table->dropForeign(['default_late_fee_rule_id']);
                $table->dropColumn(['default_frequency', 'default_charge_months', 'default_late_fee_rule_id']);
            });
        }
    }
};
