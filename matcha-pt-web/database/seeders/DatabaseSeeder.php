<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Sport;
use App\Models\MatchFormat;
use App\Models\User;
use App\Models\Player;
use App\Models\Community;
use App\Models\Venue;
use App\Models\Court;
use App\Models\VenueAvail;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Sports
        $padel = Sport::firstOrCreate(['nama_sport' => 'Padel'], ['status_sport' => 'Active']);
        $tennis = Sport::firstOrCreate(['nama_sport' => 'Tennis'], ['status_sport' => 'Active']);

        // 2. Seed Match Formats
        $formats = [
            ['sport_id' => $padel->sport_id, 'nama_format' => 'Americano', 'deskripsi' => 'Round-robin murni di mana semua pemain berpasangan bergantian dengan semua orang'],
            ['sport_id' => $padel->sport_id, 'nama_format' => 'Mexicano', 'deskripsi' => 'Pertandingan dinamis berbasis performa (pemenang lawan pemenang)'],
            ['sport_id' => $padel->sport_id, 'nama_format' => 'Mix Americano', 'deskripsi' => 'Drawing putaran round-robin dengan komposisi pria dan wanita seimbang'],
            ['sport_id' => $padel->sport_id, 'nama_format' => 'Team Americano', 'deskripsi' => 'Setiap pasangan tim tetap bertanding melawan seluruh tim lainnya'],
            ['sport_id' => $tennis->sport_id, 'nama_format' => 'Tennis Single / Double', 'deskripsi' => 'Pertandingan tenis sistem set & game standar'],
            ['sport_id' => $padel->sport_id, 'nama_format' => 'King of the Court', 'deskripsi' => 'Berjuang naik ke lapangan pemenang (Court 1) dan pertahankan posisi'],
        ];

        foreach ($formats as $f) {
            MatchFormat::firstOrCreate(
                ['nama_format' => $f['nama_format'], 'sport_id' => $f['sport_id']],
                ['deskripsi' => $f['deskripsi']]
            );
        }

        // 3. Seed Users (Host & Venue Owner)
        $billyUser = User::firstOrCreate(
            ['email' => 'billy@matcha.app'],
            [
                'nama' => 'Billy Santoso',
                'no_hp' => '0812-9988-7766',
                'password' => Hash::make('secret123'),
                'role' => 'host',
            ]
        );

        $bambangUser = User::firstOrCreate(
            ['email' => 'bambang@gelorasport.com'],
            [
                'nama' => 'Bambang Sudirman',
                'no_hp' => '0812-3456-7890',
                'password' => Hash::make('secret123'),
                'role' => 'venue_owner',
            ]
        );

        // 4. Seed Communities
        $jtkComm = Community::firstOrCreate(
            ['nama_community' => 'JTK Padel Club Bandung'],
            [
                'deskripsi' => 'Komunitas pecinta padel terbesar di Bandung. Rutin mabar setiap Selasa & Jumat malam.',
                'logo' => 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=400&q=80',
            ]
        );

        $geloraComm = Community::firstOrCreate(
            ['nama_community' => 'Gelora Tennis Enthusiasts'],
            [
                'deskripsi' => 'Komunitas tenis weekend warrior Jakarta Pusat. Terbuka untuk semua level dari Newbie hingga Pro.',
                'logo' => 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=400&q=80',
            ]
        );

        // 5. Seed Players
        Player::firstOrCreate(
            ['user_id' => $billyUser->user_id],
            [
                'community_id' => $jtkComm->community_id,
                'nama' => 'Billy Santoso',
                'usia' => 28,
                'gender' => 'Male',
                'level' => 'Intermediate',
                'rating' => 4.25,
                'no_hp' => '0812-9988-7766',
                'email' => 'billy@matcha.app',
            ]
        );

        // 6. Seed Venues & Courts
        $geloraVenue = Venue::firstOrCreate(
            ['nama_venue' => 'Gelora Sports Center'],
            [
                'owner_user_id' => $bambangUser->user_id,
                'alamat' => 'Jl. Gelora Pemuda No. 12, Jakarta Pusat',
                'foto' => 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80',
                'fasilitas' => 'WC / Toilet Bersih, Kantin Sehat, Ruang Ganti & Shower, Tempat Parkir Luas, Loker Penyimpanan',
            ]
        );

        Court::firstOrCreate(
            ['nama_court' => 'Court 1 (Center Hard Court)', 'venue_id' => $geloraVenue->venue_id],
            ['sport_id' => $tennis->sport_id, 'status_ketersediaan' => 'Available', 'deskripsi' => 'Hard court standar turnamen']
        );

        Court::firstOrCreate(
            ['nama_court' => 'Court 2 (Regular Hard Court)', 'venue_id' => $geloraVenue->venue_id],
            ['sport_id' => $tennis->sport_id, 'status_ketersediaan' => 'Available', 'deskripsi' => 'Hard court reguler']
        );

        $bonangVenue = Venue::firstOrCreate(
            ['nama_venue' => 'Bonang Padel Arena & Club'],
            [
                'owner_user_id' => $bambangUser->user_id,
                'alamat' => 'Jl. Bonang Raya No. 45, Jakarta Selatan',
                'foto' => 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=800&q=80',
                'fasilitas' => 'WC & Shower Air Hangat, Cafe Matcha Bar, Ruang Ganti VIP, Free Wi-Fi, Rental Raket',
            ]
        );

        Court::firstOrCreate(
            ['nama_court' => 'Court 1 (Red Panoramic Court)', 'venue_id' => $bonangVenue->venue_id],
            ['sport_id' => $padel->sport_id, 'status_ketersediaan' => 'Available', 'deskripsi' => 'Panoramic glass court WPT']
        );

        Court::firstOrCreate(
            ['nama_court' => 'Court 2 (Blue Panoramic Court)', 'venue_id' => $bonangVenue->venue_id],
            ['sport_id' => $padel->sport_id, 'status_ketersediaan' => 'Available', 'deskripsi' => 'Panoramic glass court']
        );

        $jtkVenue = Venue::firstOrCreate(
            ['nama_venue' => 'JTK Padel & Tennis Hub'],
            [
                'owner_user_id' => $bambangUser->user_id,
                'alamat' => 'Kawasan Olahraga Terpadu JTK, Bandung',
                'foto' => 'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=800&q=80',
                'fasilitas' => 'WC Bersih, Kantin Komunitas, Ruang Istirahat, Parkir Luas, Lampu Lapangan LED',
            ]
        );

        Court::firstOrCreate(
            ['nama_court' => 'Court A (Padel Glass)', 'venue_id' => $jtkVenue->venue_id],
            ['sport_id' => $padel->sport_id, 'status_ketersediaan' => 'Available', 'deskripsi' => 'Padel court outdoor']
        );
    }
}
