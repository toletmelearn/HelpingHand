<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibrarySetting extends Model
{
    protected $fillable = [
        'default_issue_days',
        'fine_per_day',
        'low_stock_threshold',
        'auto_reminder_enabled',
    ];

    protected $casts = [
        'fine_per_day' => 'decimal:2',
        'auto_reminder_enabled' => 'boolean',
    ];

    public static function getSetting()
    {
        return self::firstOrCreate([], [
            'default_issue_days' => 14,
            'fine_per_day' => 1.00,
            'low_stock_threshold' => 5,
            'auto_reminder_enabled' => true,
        ]);
    }
}
