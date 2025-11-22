<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Farm extends Model
{
    protected $fillable = ['user_id', 'name', 'location', 'latitude', 'longitude', 'soil_type', 'area_hectares'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function cacaoTrees()
    {
        return $this->hasMany(CacaoTree::class);
    }
    public function weatherLogs()
    {
        return $this->hasMany(WeatherLog::class);
    }
    public function predictionLogs()
    {
        return $this->hasMany(PredictionLog::class);
    }
}
