<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sport extends Model
{
    use HasFactory;

    protected $table = 'tb_sport';
    protected $primaryKey = 'sport_id';
    public $timestamps = false;

    protected $fillable = [
        'nama_sport',
        'status_sport',
    ];

    public function courts()
    {
        return $this->hasMany(Court::class, 'sport_id', 'sport_id');
    }

    public function matchFormats()
    {
        return $this->hasMany(MatchFormat::class, 'sport_id', 'sport_id');
    }

    public function sessions()
    {
        return $this->hasMany(SessionModel::class, 'sport_id', 'sport_id');
    }
}
