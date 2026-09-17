<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kudos extends Model
{
    use HasFactory;

    protected $table = 'tb_kudos';

    protected $primaryKey = 'kudos_id';

    protected $fillable = [
        'session_id',
        'giver_user_id',
        'recipient_player_id',
        'recipient_name',
        'badge',
    ];

    public function session()
    {
        return $this->belongsTo(SessionModel::class, 'session_id', 'session_id');
    }

    public function giver()
    {
        return $this->belongsTo(User::class, 'giver_user_id', 'user_id');
    }

    public function recipientPlayer()
    {
        return $this->belongsTo(Player::class, 'recipient_player_id', 'player_id');
    }
}
