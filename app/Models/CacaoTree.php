<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CacaoTree extends Model
{
    protected $fillable = ['farm_id', 'block_name', 'tree_count', 'variety', 'date_planted', 'growth_stage', 'status'];
    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }
    public function healthLogs()
    {
        return $this->hasMany(HealthLog::class);
    }
    public function predictionLogs()
    {
        return $this->hasMany(PredictionLog::class);
    }
}
