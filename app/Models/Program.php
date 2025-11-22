<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['code', 'description'];
    //dito mag add ng user_id sa fillable
    //tas php artisan make:migration add_user_id_to_programs_table --table=programs
    //Schema::table('programs', function (Blueprint $table) {
    //$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
//});
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
