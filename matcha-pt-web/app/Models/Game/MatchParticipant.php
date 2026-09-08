<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;

class MatchParticipant extends Model
{
    protected $guarded = [];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}
