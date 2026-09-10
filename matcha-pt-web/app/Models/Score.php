<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    use HasFactory;

    protected $table = 'tb_score';
    protected $primaryKey = 'score_id';

    protected $fillable = [
        'match_id',
        'set_number',
        'game_number',
        'point_score_a',
        'point_score_b',
        'game_score_a',
        'game_score_b',
        'set_score_a',
        'set_score_b',
        'score_side_a',
        'score_side_b',
        'scoring_system',
        'status_score',
    ];

    public function match()
    {
        return $this->belongsTo(GameMatch::class, 'match_id', 'match_id');
    }
}
