<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    protected $table = 'tb_venue';
    protected $primaryKey = 'venue_id';

    protected $fillable = [
        'owner_user_id',
        'nama_venue',
        'alamat',
        'foto',
        'fasilitas',
        'catatan',
        'kota',
        'jam_operasional',
        'hari_buka',
        'no_whatsapp',
        'nama_pic',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'user_id');
    }

    public function courts()
    {
        return $this->hasMany(Court::class, 'venue_id', 'venue_id');
    }

    public function availabilities()
    {
        return $this->hasMany(VenueAvail::class, 'venue_id', 'venue_id');
    }

    public function sessions()
    {
        return $this->hasMany(SessionModel::class, 'venue_id', 'venue_id');
    }
}
