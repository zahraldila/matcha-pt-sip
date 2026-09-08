<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayingHistory extends Model
{
    use HasFactory;

    protected $table = 'tb_playing_history';
    protected $primaryKey = 'history_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'player_id',
        'match_id',
        'jumlah_permainan',
        'waktu_permainan',
        'status_permainan',
        'partner_player_id',
        'opponent_player_id',
    ];

    protected $casts = [
        'waktu_permainan' => 'datetime',
        'jumlah_permainan' => 'integer',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id', 'player_id');
    }

    public function match()
    {
        return $this->belongsTo(GameMatch::class, 'match_id', 'match_id');
    }

    public function partner()
    {
        return $this->belongsTo(Player::class, 'partner_player_id', 'player_id');
    }

    public function opponent()
    {
        return $this->belongsTo(Player::class, 'opponent_player_id', 'player_id');
    }
}
