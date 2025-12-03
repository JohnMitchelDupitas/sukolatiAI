<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiseaseDetectionsMetadata extends Model
{
    protected $table = 'disease_detections_metadata';

    protected $fillable = [
        'detection_id',
        'is_verified_by_user',
        'treatment_recommendations',
    ];

    protected $casts = [
        'is_verified_by_user' => 'boolean',
    ];

    /**
     * Relationship to DiseaseDetection
     */
    public function detection(): BelongsTo
    {
        return $this->belongsTo(DiseaseDetection::class, 'detection_id');
    }

    /**
     * Scope to get verified detections
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified_by_user', true);
    }

    /**
     * Scope to get detections with treatment recommendations
     */
    public function scopeWithTreatment($query)
    {
        return $query->whereNotNull('treatment_recommendations');
    }
}
