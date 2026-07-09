<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            if (!Schema::hasColumn('teachers', 'relative_name')) {
                $table->string('relative_name')->nullable()->after('date_of_birth')
                    ->comment("Father's / Husband's / Wife's name");
            }
            if (!Schema::hasColumn('teachers', 'permanent_address')) {
                $table->string('permanent_address', 500)->nullable()->after('address');
            }
            if (!Schema::hasColumn('teachers', 'classes_taught')) {
                $table->text('classes_taught')->nullable()->comment('Classes taught with subjects, free text');
            }
            if (!Schema::hasColumn('teachers', 'no_of_periods')) {
                $table->unsignedSmallInteger('no_of_periods')->nullable();
            }
            if (!Schema::hasColumn('teachers', 'class_section')) {
                $table->string('class_section')->nullable()->comment('Class & section, e.g. class-teacher assignment');
            }
            if (!Schema::hasColumn('teachers', 'responsibilities')) {
                $table->text('responsibilities')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn([
                'relative_name', 'permanent_address', 'classes_taught',
                'no_of_periods', 'class_section', 'responsibilities',
            ]);
        });
    }
};
