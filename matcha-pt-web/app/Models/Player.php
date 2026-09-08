<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    use HasFactory;

    protected $table = 'tb_player';
    protected $primaryKey = 'player_id';

    protected $fillable = [
        'user_id',
        'community_id',
        'nama',
        'usia',
        'gender',
        'level',
        'rating',
        'no_hp',
        'email',
    ];

    protected $casts = [
        'rating' => 'float',
        'usia' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id', 'community_id');
    }

    public function sessions()
    {
        return $this->belongsToMany(SessionModel::class, 'tb_session_player', 'player_id', 'session_id');
    }

    public function matchParticipants()
    {
        return $this->hasMany(MatchParticipant::class, 'player_id', 'player_id');
    }

    public function playingHistories()
    {
        return $this->hasMany(PlayingHistory::class, 'player_id', 'player_id');
    }
}
