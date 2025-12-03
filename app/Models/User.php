<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firstname',
        'middlename',
        'lastname',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone' => 'encrypted',
        ];
    }

    /**
     * Get the programs for the user.
     */
    //nadagdag to para sa join
    // public function programs()
    // {
    //     return $this->hasMany(Program::class);
    // }
    public function farms()
    {
        return $this->hasMany(Farm::class);
    }
    public function predictionLogs()
    {
        return $this->hasMany(PredictionLog::class);
    }

    /**
     * Get all login histories for this user
     */
    public function loginHistories()
    {
        return $this->hasMany(LoginHistory::class);
    }

    /**
     * Get the most recent login
     */
    public function latestLogin()
    {
        return $this->hasOne(LoginHistory::class)->latestOfMany();
    }
}
