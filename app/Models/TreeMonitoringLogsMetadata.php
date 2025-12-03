<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreeMonitoringLogsMetadata extends Model
{
    protected $table = 'tree_monitoring_logs_metadata';

    protected $fillable = [
        'monitoring_log_id',
        'image_path',
    ];

    /**
     * Relationship to TreeMonitoringLogs
     */
    public function monitoringLog(): BelongsTo
    {
        return $this->belongsTo(TreeMonitoringLogs::class, 'monitoring_log_id');
    }

    /**
     * Scope to get with image data
     */
    public function scopeWithImages($query)
    {
        return $query->whereNotNull('image_path');
    }
}
