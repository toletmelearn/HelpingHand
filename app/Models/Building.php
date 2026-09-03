<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = [
        'name',
        'transfer_time_in_minutes',
    ];

    protected $casts = [
        'transfer_time_in_minutes' => 'integer',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Minutes required to travel from this building to $otherBuildingId --
     * 0 for the same building. Prefers an explicit
     * BuildingTransferTime override for this exact pair; falls back to
     * the stricter (larger) of the two buildings' own
     * transfer_time_in_minutes default when no override exists.
     */
    public function transferTimeTo(int $otherBuildingId): int
    {
        if ($this->id === $otherBuildingId) {
            return 0;
        }

        $override = BuildingTransferTime::forPair($this->id, $otherBuildingId)->value('transfer_time_in_minutes');
        if ($override !== null) {
            return (int) $override;
        }

        $other = self::find($otherBuildingId);

        return max($this->transfer_time_in_minutes, $other->transfer_time_in_minutes ?? 0);
    }
}
