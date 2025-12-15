<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class CacaoTree extends Model implements Auditable
{
    use HasFactory;
    use AuditableTrait;

    protected $fillable = [
        'farm_id',
        'tree_code',
        'block_name',
        'variety',
        'date_planted',
        'latitude',
        'longitude',
        'pod_count',
        'status',
        'growth_stage'
    ];

    // Track all fields for audit
    protected $auditInclude = [
        'farm_id',
        'tree_code',
        'block_name',
        'variety',
        'date_planted',
        'latitude',
        'longitude',
        'pod_count',
        'status',
        'growth_stage'
    ];

    // Exclude timestamps
    protected $auditExclude = [
        'updated_at',
    ];

    // Events to track
    protected $auditEvents = ['created', 'updated', 'deleted'];

    // Return false to use default timestamps behavior
    public function getAuditTimestamps(): bool
    {
        return false;
    }

    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }

    public function detections()
    {
        return $this->hasMany(DiseaseDetection::class, 'cacao_tree_id');
    }

    public function monitoringLogs()
    {
        return $this->hasMany(TreeMonitoringLogs::class, 'cacao_tree_id');
    }

    // REMOVE THIS - The AuditableTrait already provides it!
    // public function audits()
    // {
    //     return $this->morphMany(\OwenIt\Auditing\Models\Audit::class, 'auditable');
    // }

    public function latestLog()
    {
        return $this->hasOne(TreeMonitoringLogs::class, 'cacao_tree_id')->latestOfMany();
    }

    /**
     * Get current pod count from latest monitoring log
     * Returns the actual pod count from tree_monitoring_logs
     */
    public function getCurrentPodCount()
    {
        $latestLog = $this->latestLog()->first();

        if ($latestLog && $latestLog->pod_count !== null) {
            return $latestLog->pod_count;
        }

        // Fallback to tree's pod_count (which is usually 0)
        return $this->pod_count ?? 0;
    }
}
