<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;

    protected $table = 'tb_court';
    protected $primaryKey = 'court_id';

    protected $fillable = [
        'venue_id',
        'sport_id',
        'nama_court',
        'status_ketersediaan',
        'image_url',
        'deskripsi',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id', 'venue_id');
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id', 'sport_id');
    }

    public function sessions()
    {
        return $this->belongsToMany(SessionModel::class, 'tb_session_court', 'court_id', 'session_id');
    }

    public function matches()
    {
        return $this->hasMany(GameMatch::class, 'court_id', 'court_id');
    }
}
