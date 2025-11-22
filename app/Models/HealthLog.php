<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthLog extends Model
{
    protected $fillable = ['cacao_tree_id', 'user_id', 'notes', 'height_m', 'canopy_diameter_m', 'flowers', 'pods', 'observed_pests'];
    public function cacaoTree()
    {
        return $this->belongsTo(CacaoTree::class, 'cacao_tree_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
