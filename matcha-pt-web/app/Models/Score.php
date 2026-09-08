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
        'score_side_a',
        'score_side_b',
        'status_score',
    ];

    public function match()
    {
        return $this->belongsTo(GameMatch::class, 'match_id', 'match_id');
    }
}
