<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;

class MatchScore extends Model
{
    protected $guarded = [];

    protected $casts = [
        'point_history' => 'array',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}
