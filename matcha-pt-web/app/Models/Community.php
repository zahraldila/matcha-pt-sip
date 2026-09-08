<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    use HasFactory;

    protected $table = 'tb_community';
    protected $primaryKey = 'community_id';

    protected $fillable = [
        'nama_community',
        'deskripsi',
        'logo',
    ];

    public function players()
    {
        return $this->hasMany(Player::class, 'community_id', 'community_id');
    }
}
