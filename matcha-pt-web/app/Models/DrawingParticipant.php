<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawingParticipant extends Model
{
    use HasFactory;

    protected $table = 'tb_drawing_participant';
    protected $primaryKey = 'drawing_participant_id';
    public $timestamps = false;

    protected $fillable = [
        'drawing_id',
        'player_id',
        'court_id',
    ];

    public function drawing()
    {
        return $this->belongsTo(Drawing::class, 'drawing_id', 'drawing_id');
    }

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id', 'player_id');
    }

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }
}
