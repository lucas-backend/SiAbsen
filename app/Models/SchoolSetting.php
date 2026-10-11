<?php

namespace App\Models;

use Database\Factories\SchoolSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property float $latitude
 * @property float $longitude
 * @property int $radius_meters
 */
class SchoolSetting extends Model
{
    /** @use HasFactory<SchoolSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'radius_meters',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
        ];
    }
}
