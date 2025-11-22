<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PredictionLog extends Model
{
    protected $fillable = ['user_id', 'farm_id', 'cacao_tree_id', 'image_path', 'disease', 'confidence', 'recommendation', 'model_response'];
    protected $casts = ['model_response' => 'array'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function farm()
    {
        return $this->belongsTo(Farm::class);
    }
    public function cacaoTree()
    {
        return $this->belongsTo(CacaoTree::class, 'cacao_tree_id');
    }
}
