<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchFormat extends Model
{
    use HasFactory;

    protected $table = 'tb_match_format';
    protected $primaryKey = 'match_format_id';
    public $timestamps = false;

    protected $fillable = [
        'sport_id',
        'nama_format',
        'deskripsi',
    ];

    public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id', 'sport_id');
    }

    public function drawings()
    {
        return $this->hasMany(Drawing::class, 'match_format_id', 'match_format_id');
    }
}
