<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class TreeMonitoringLogs extends Model implements Auditable
{
    use HasFactory;
    use AuditableTrait;

    protected $fillable = [
        'cacao_tree_id',
        'user_id',
        'status',
        'disease_type',
        'pod_count',
        'inspection_date',
    ];

    // Track all fields for audit
    protected $auditInclude = [
        'cacao_tree_id',
        'user_id',
        'status',
        'disease_type',
        'pod_count',
        'inspection_date',
    ];

    // Exclude timestamps from audit
    protected $auditExclude = [
        'updated_at',
    ];

    //  Events to track
    protected $auditEvents = ['created', 'updated', 'deleted'];

    //  Return false to use default timestamps behavior
    public function getAuditTimestamps(): bool
    {
        return false;
    }

    //  Override getAuditData to capture user context correctly
    public function getAuditData(): array
    {
        $data = parent::getAuditData();

        // Get current authenticated user
        $user = \Illuminate\Support\Facades\Auth::user();

        if ($user) {
            $data['user_id'] = $user->id;
            $data['user_type'] = get_class($user);
            Log::info(' getAuditData: User context captured', [
                'user_id' => $user->id,
                'user_type' => get_class($user)
            ]);
        } else {
            Log::warning(' getAuditData: No authenticated user found');
        }

        return $data;
    }

    public function tree()
    {
        return $this->belongsTo(CacaoTree::class, 'cacao_tree_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function metadata()
    {
        return $this->hasOne(TreeMonitoringLogsMetadata::class, 'monitoring_log_id');
    }

    //  REMOVE THIS - The AuditableTrait already provides it!
    // public function audits()
    // {
    //     return $this->morphMany(\OwenIt\Auditing\Models\Audit::class, 'auditable');
    // }
}
