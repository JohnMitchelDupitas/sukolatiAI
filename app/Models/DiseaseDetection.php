<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiseaseDetection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cacao_tree_id',
        'image_path',
        'detected_disease',
        'confidence',
        'ai_response_log'
    ];

    // Link to the specific tree
    public function cacaoTree()
    {
        return $this->belongsTo(CacaoTree::class, 'cacao_tree_id');
    }

    // Link to the farmer
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Link to metadata (verification and treatment data)
    public function metadata()
    {
        return $this->hasOne(DiseaseDetectionsMetadata::class, 'detection_id');
    }
}
