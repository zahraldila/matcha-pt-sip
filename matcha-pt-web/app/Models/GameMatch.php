<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'tb_match';
    protected $primaryKey = 'match_id';

    protected $fillable = [
        'drawing_id',
        'court_id',
        'nomor_match',
        'status_match',
        'waktu_mulai',
        'waktu_selesai',
        'hasil_pertandingan',
        'winner_team',
    ];

    public function drawing()
    {
        return $this->belongsTo(Drawing::class, 'drawing_id', 'drawing_id');
    }

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }

    public function participants()
    {
        return $this->hasMany(MatchParticipant::class, 'match_id', 'match_id');
    }

    public function scores()
    {
        return $this->hasMany(Score::class, 'match_id', 'match_id');
    }

    public function playingHistories()
    {
        return $this->hasMany(PlayingHistory::class, 'match_id', 'match_id');
    }
}
