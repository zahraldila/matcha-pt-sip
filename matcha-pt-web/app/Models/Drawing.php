<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Drawing extends Model
{
    use HasFactory;

    protected $table = 'tb_drawing';
    protected $primaryKey = 'drawing_id';
    const UPDATED_AT = null;

    protected $fillable = [
        'session_id',
        'match_format_id',
        'tanggal_drawing',
        'jam_drawing',
    ];

    protected $casts = [
        'tanggal_drawing' => 'date',
    ];

    public function session()
    {
        return $this->belongsTo(SessionModel::class, 'session_id', 'session_id');
    }

    public function matchFormat()
    {
        return $this->belongsTo(MatchFormat::class, 'match_format_id', 'match_format_id');
    }

    public function participants()
    {
        return $this->hasMany(DrawingParticipant::class, 'drawing_id', 'drawing_id');
    }

    public function matches()
    {
        return $this->hasMany(GameMatch::class, 'drawing_id', 'drawing_id');
    }
}
