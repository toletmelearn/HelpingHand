<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered slot in a BellTimingTemplate's canonical daily pattern.
 * Mirrors BellTiming's own column semantics (period_name, start_time,
 * end_time, is_break, period_type, order_index, custom_label, color_code)
 * so the existing is_break/period_type consistency rule and 12h/24h time
 * handling apply unchanged -- see BellTiming::booted() for the
 * is_break<->period_type sync this class deliberately mirrors below.
 */
class BellTimingTemplateSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'bell_timing_template_id',
        'period_name',
        'start_time',
        'end_time',
        'is_break',
        'period_type',
        'order_index',
        'custom_label',
        'color_code',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'is_break' => 'boolean',
        'order_index' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (BellTimingTemplateSlot $slot) {
            if ($slot->is_break && in_array($slot->period_type, [null, BellTiming::PERIOD_TYPE_TEACHING], true)) {
                $slot->period_type = BellTiming::PERIOD_TYPE_BREAK;
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BellTimingTemplate::class, 'bell_timing_template_id');
    }
}
