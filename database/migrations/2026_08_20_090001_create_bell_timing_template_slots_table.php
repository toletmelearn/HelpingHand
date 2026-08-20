<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One ordered slot within a template's canonical daily pattern.
     * Deliberately no day_of_week column here -- which days a template gets
     * applied to is chosen at Apply time (matching how Bulk Create already
     * works: one period structure, replicated across whichever days the
     * admin selects), not baked into the template itself.
     *
     * Mirrors bell_timings' own column set/semantics (period_name,
     * start_time, end_time, is_break, period_type, order_index,
     * custom_label, color_code) so the existing 12h/24h time-format
     * handling and is_break/period_type conventions apply unchanged --
     * these rows are never read by GeneratorService/FeasibilityService/
     * TimetableConflictResolver/timetable_slots, only by the template
     * apply workflow itself.
     */
    public function up(): void
    {
        Schema::create('bell_timing_template_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bell_timing_template_id')
                ->constrained('bell_timing_templates')
                ->onDelete('cascade');
            $table->string('period_name');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_break')->default(false);
            $table->enum('period_type', ['teaching', 'assembly', 'prayer', 'break', 'zero', 'dispersal'])
                ->default('teaching');
            $table->integer('order_index')->default(0);
            $table->string('custom_label')->nullable();
            $table->string('color_code')->default('#007bff');
            $table->timestamps();

            $table->index(['bell_timing_template_id', 'order_index'], 'bt_template_slots_template_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bell_timing_template_slots');
    }
};
