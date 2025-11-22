<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'type',
        'breed',
        'health_status',
        'birth_date',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
