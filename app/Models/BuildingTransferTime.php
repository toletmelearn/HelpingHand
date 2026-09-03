<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildingTransferTime extends Model
{
    protected $fillable = [
        'building_a_id',
        'building_b_id',
        'transfer_time_in_minutes',
    ];

    protected $casts = [
        'transfer_time_in_minutes' => 'integer',
    ];

    /**
     * Always stores/looks up a pair with the smaller id first, so a given
     * pair of buildings is represented exactly once regardless of which
     * order the two ids were checked in.
     */
    public static function setPair(int $buildingIdA, int $buildingIdB, int $minutes): self
    {
        [$a, $b] = $buildingIdA < $buildingIdB ? [$buildingIdA, $buildingIdB] : [$buildingIdB, $buildingIdA];

        return self::updateOrCreate(
            ['building_a_id' => $a, 'building_b_id' => $b],
            ['transfer_time_in_minutes' => $minutes]
        );
    }

    public static function forPair(int $buildingIdA, int $buildingIdB)
    {
        [$a, $b] = $buildingIdA < $buildingIdB ? [$buildingIdA, $buildingIdB] : [$buildingIdB, $buildingIdA];

        return self::where('building_a_id', $a)->where('building_b_id', $b);
    }
}
