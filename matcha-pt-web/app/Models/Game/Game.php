<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use App\Models\Venue\Venue;
use App\Models\Venue\Court;

class Game extends Model
{
    protected $guarded = [];

    protected $casts = [
        'drawing_result' => 'array',
        'is_locked' => 'boolean',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function participants()
    {
        return $this->hasMany(MatchParticipant::class);
    }

    public function scores()
    {
        return $this->hasMany(MatchScore::class);
    }
}
