<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('udise_pen')->nullable()->unique()->after('admission_no');
            $table->string('apaar_id', 12)->nullable()->unique()->after('udise_pen');
            $table->string('name_as_per_aadhaar')->nullable()->after('apaar_id');
            $table->boolean('apaar_consent_given')->default(false)->after('name_as_per_aadhaar');
            $table->date('apaar_consent_date')->nullable()->after('apaar_consent_given');
            $table->string('apaar_consent_by')->nullable()->after('apaar_consent_date');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'udise_pen',
                'apaar_id',
                'name_as_per_aadhaar',
                'apaar_consent_given',
                'apaar_consent_date',
                'apaar_consent_by',
            ]);
        });
    }
};
