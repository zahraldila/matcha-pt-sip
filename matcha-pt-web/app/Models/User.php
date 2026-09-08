<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'tb_user';
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'nama',
        'no_hp',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function player()
    {
        return $this->hasOne(Player::class, 'user_id', 'user_id');
    }

    public function ownedVenues()
    {
        return $this->hasMany(Venue::class, 'owner_user_id', 'user_id');
    }

    public function hostedSessions()
    {
        return $this->hasMany(SessionModel::class, 'host_user_id', 'user_id');
    }
}
