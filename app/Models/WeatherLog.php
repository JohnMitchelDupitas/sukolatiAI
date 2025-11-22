<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherLog extends Model
{
    protected $fillable = ['farm_id', 'temperature', 'humidity', 'rainfall', 'wind_speed', 'cloudiness', 'raw', 'recorded_at'];
    protected $casts = ['raw' => 'array', 'recorded_at' => 'datetime'];
    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }
}
