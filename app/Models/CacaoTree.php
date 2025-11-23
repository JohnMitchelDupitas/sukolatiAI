<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class CacaoTree extends Model
{
    use HasFactory;

    // 1. UPDATED FILLABLE (Matches your new table columns)
    // - Removed: 'tree_count'
    // - Added: 'tree_code'
    protected $fillable = [
        'farm_id',
        'tree_code',
        'block_name',
        'variety',
        'date_planted',
        'latitude',
        'longitude'
    ];
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

    public function detections()
    {
        return $this->hasMany(DiseaseDetection::class, 'cacao_tree_id');
    }

    public function logs()
    {
        return $this->hasMany(TreeMonitoringLogs::class);
    }

    public function latestLog()
    {
        return $this->hasOne(TreeMonitoringLogs::class)->latestOfMany();
    }
}
