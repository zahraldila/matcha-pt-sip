<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VenueAvail extends Model
{
    use HasFactory;

    protected $table = 'tb_venue_avail';
    protected $primaryKey = 'availability_id';
    public $timestamps = false;

    protected $fillable = [
        'venue_id',
        'hari',
        'jam_buka',
        'jam_tutup',
        'booking_status',
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id', 'venue_id');
    }
}
