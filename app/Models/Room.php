<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a room_number string -- the free-text field already used directly
 * on TimetableSlot/DatesheetEntry/ExamSeatingArrangement, with no FK to
 * this table -- to the building it physically sits in, so
 * TransferTimeValidator can tell whether two rooms need travel time
 * between them.
 */
class Room extends Model
{
    protected $fillable = [
        'room_number',
        'building_id',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
