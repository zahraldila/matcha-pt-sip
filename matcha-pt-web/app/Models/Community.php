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
        'jadwal_rutin',
        'sport_utama',
        'sport',
    ];

    public function players()
    {
        return $this->hasMany(Player::class, 'community_id', 'community_id');
    }

    /**
     * Resolusi cabang olahraga / sport komunitas dari kolom database 'sport' dengan mapping UI yang rapi.
     */
    public function getSportAttribute($value = null)
    {
        $rawSport = $value ?: ($this->attributes['sport'] ?? null);

        if (!empty($rawSport)) {
            return match (strtolower(trim($rawSport))) {
                'tennis' => 'Tennis',
                'all_racquet', 'both', 'all racquet', 'padel & tennis' => 'Padel & Tennis',
                default => 'Padel',
            };
        }

        // Penanganan aman untuk data komunitas lama yang belum memiliki nilai sport
        $text = strtolower(($this->nama_community ?? '') . ' ' . ($this->deskripsi ?? ''));
        $hasPadel = str_contains($text, 'padel');
        $hasTennis = str_contains($text, 'tennis') || str_contains($text, 'tenis');
        $hasBoth = str_contains($text, 'all racquet') || str_contains($text, 'both') || ($hasPadel && $hasTennis) || str_contains($text, 'raket');

        if ($hasBoth) {
            return 'Padel & Tennis';
        }
        if ($hasTennis) {
            return 'Tennis';
        }
        if ($hasPadel) {
            return 'Padel';
        }

        $dummy = collect(\App\Services\MatchaDummyDataService::getCommunities())
            ->first(fn($item) => $item['id'] == $this->community_id || strcasecmp($item['name'], $this->nama_community) === 0);
        if ($dummy && !empty($dummy['sport'])) {
            return $dummy['sport'] === 'Tennis' ? 'Tennis' : ($dummy['sport'] === 'Padel' ? 'Padel' : 'Padel & Tennis');
        }

        return 'Padel';
    }

    /**
     * Alias sport_utama yang merujuk ke getSportAttribute.
     */
    public function getSportUtamaAttribute($value = null)
    {
        if (!empty($value)) {
            return match (strtolower(trim($value))) {
                'tennis' => 'Tennis',
                'all_racquet', 'both', 'all racquet', 'padel & tennis' => 'Padel & Tennis',
                default => 'Padel',
            };
        }

        return $this->sport;
    }

    /**
     * Resolusi nama admin / pembuat komunitas dari data asli.
     */
    public function getAdminNameAttribute()
    {
        // 1. Ambil dari relasi player yang terdaftar di komunitas ini
        if ($this->relationLoaded('players') ? $this->players->isNotEmpty() : $this->players()->exists()) {
            $firstPlayer = $this->players->sortBy('created_at')->first();
            if ($firstPlayer) {
                if (!empty($firstPlayer->user?->nama)) {
                    return $firstPlayer->user->nama;
                }
                if (!empty($firstPlayer->nama)) {
                    return $firstPlayer->nama;
                }
            }
        }

        // 2. Ambil dari data komunitas bawaan / dummy data service (misal: Gelora Tennis Enthusiasts)
        $dummy = collect(\App\Services\MatchaDummyDataService::getCommunities())
            ->first(fn($item) => $item['id'] == $this->community_id || strcasecmp($item['name'], $this->nama_community) === 0);
        if ($dummy && !empty($dummy['admin_name'])) {
            return $dummy['admin_name'];
        }

        // 3. Ambil dari user pembuat yang tercatat di database berdasarkan waktu pembuatan
        if ($this->created_at) {
            $creator = \App\Models\User::where('created_at', '<=', $this->created_at)
                ->where('role', '!=', 'venue_owner')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($creator && !empty($creator->nama)) {
                return $creator->nama;
            }
        }

        // 4. Fallback user asli yang ada di database
        $anyUser = \App\Models\User::where('role', '!=', 'venue_owner')->orderBy('user_id')->first();
        return $anyUser?->nama ?? 'Admin';
    }
}
