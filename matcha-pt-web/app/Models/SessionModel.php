<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionModel extends Model
{
    use HasFactory;

    protected $table = 'tb_session';
    protected $primaryKey = 'session_id';

    protected $fillable = [
        'host_user_id',
        'sport_id',
        'venue_id',
        'nama_session',
        'scoring_system',
        'waktu_session',
        'datetime',
        'status_session',
        'jumlah_pemain',
        'jenis_permainan',
        'share_token',
    ];

    public function ensureShareToken(): string
    {
        if (empty($this->share_token)) {
            do {
                $token = bin2hex(random_bytes(16));
            } while (static::where('share_token', $token)->exists());

            $this->share_token = $token;
            $this->save();
        }

        return $this->share_token;
    }

    public function getShareUrlAttribute(): string
    {
        return route('games.share', ['token' => $this->ensureShareToken()]);
    }

    public function getJenisPermainanAttribute($value): string
    {
        return $value ?: 'Double';
    }

    protected $casts = [
        'datetime' => 'datetime',
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id', 'user_id');
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id', 'sport_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id', 'venue_id');
    }

    public function courts()
    {
        return $this->belongsToMany(Court::class, 'tb_session_court', 'session_id', 'court_id');
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'tb_session_player', 'session_id', 'player_id');
    }

    public function drawings()
    {
        return $this->hasMany(Drawing::class, 'session_id', 'session_id');
    }
}
