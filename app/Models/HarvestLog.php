<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HarvestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tree_id',
        'pod_count',
        'harvest_date',
        'harvester_id',
    ];

    protected $casts = [
        'harvest_date' => 'date',
        'pod_count' => 'integer',
    ];

    /**
     * Get the tree associated with this harvest log
     */
    public function tree()
    {
        return $this->belongsTo(CacaoTree::class);
    }

    /**
     * Get the user who performed the harvest
     */
    public function harvester()
    {
        return $this->belongsTo(User::class, 'harvester_id');
    }

    /**
     * Calculate estimated dry weight (kg)
     * 1 pod ≈ 0.04kg dry beans
     */
    public function getEstimatedDryWeightAttribute()
    {
        return $this->pod_count * 0.04;
    }
}
