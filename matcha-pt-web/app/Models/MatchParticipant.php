<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchParticipant extends Model
{
    use HasFactory;

    protected $table = 'tb_match_participant';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'match_id',
        'player_id',
        'side',
    ];

    public function match()
    {
        return $this->belongsTo(GameMatch::class, 'match_id', 'match_id');
    }

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id', 'player_id');
    }
}
