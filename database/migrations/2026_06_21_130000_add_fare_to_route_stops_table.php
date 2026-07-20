<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('route_stops') && !Schema::hasColumn('route_stops', 'fare')) {
            Schema::table('route_stops', function (Blueprint $table) {
                $table->decimal('fare', 10, 2)->default(0.00)->after('arrival_time');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('route_stops') && Schema::hasColumn('route_stops', 'fare')) {
            Schema::table('route_stops', function (Blueprint $table) {
                $table->dropColumn('fare');
            });
        }
    }
};
