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
        'google_maps_url',
        'sport_type',
        'jumlah_court',
        'tipe_arena',
        'jenis_permukaan',
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

    /**
     * Resolves the primary/first photo of the venue.
     */
    public function getMainPhotoAttribute(): string
    {
        $rawPhotos = array_filter(array_map('trim', explode(',', $this->foto ?? '')));
        $resolvedPhotos = array_values(array_map(function ($p) {
            return str_starts_with($p, 'http') ? $p : asset($p);
        }, $rawPhotos));

        return $resolvedPhotos[0] ?? 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80';
    }

    /**
     * Resolves all gallery photos as absolute URLs.
     *
     * @return string[]
     */
    public function getGalleryPhotosAttribute(): array
    {
        $rawPhotos = array_filter(array_map('trim', explode(',', $this->foto ?? '')));

        return array_values(array_map(function ($p) {
            return str_starts_with($p, 'http') ? $p : asset($p);
        }, $rawPhotos));
    }
}
