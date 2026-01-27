<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Asset;

class AssetCategory extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'type',
        'description',
        'is_active'
    ];
    
    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
