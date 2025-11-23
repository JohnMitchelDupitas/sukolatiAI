<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreeMonitoringLogs extends Model
{
    use HasFactory;

    protected $fillable = [
        'cacao_tree_id',
        'user_id',
        'status',
        'disease_type',
        'remedy_applied',
        'image_path',
        'pod_count',
        'inspection_date'
    ];

    public function tree()
    {
        return $this->belongsTo(CacaoTree::class, 'cacao_tree_id');
    }
}
