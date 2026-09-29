<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Sport;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class SyncVenueCourtsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $padelSport = Sport::where('nama_sport', 'ilike', 'padel')->first() ?? Sport::find(1);
        $tennisSport = Sport::where('nama_sport', 'ilike', 'tennis')->first() ?? Sport::find(2);

        $padelId = $padelSport ? $padelSport->sport_id : 1;
        $tennisId = $tennisSport ? $tennisSport->sport_id : 2;

        $venuesWithoutCourts = Venue::doesntHave('courts')->get();

        $createdCount = 0;

        foreach ($venuesWithoutCourts as $venue) {
            $sportTypeLower = strtolower(trim((string) ($venue->sport_type ?? '')));
            $venueNameLower = strtolower(trim((string) ($venue->nama_venue ?? '')));

            // Tentukan sport_id: Prioritas sport_type -> nama_venue -> default padel
            if (str_contains($sportTypeLower, 'tennis') || str_contains($venueNameLower, 'tennis')) {
                $sportId = $tennisId;
            } else {
                $sportId = $padelId;
            }

            // Tentukan jumlah court: gunakan jumlah_court di venue atau default 2 court
            $rawCount = (int) ($venue->jumlah_court ?? 0);
            $courtCount = $rawCount > 0 ? min($rawCount, 8) : 2;

            for ($i = 1; $i <= $courtCount; $i++) {
                Court::create([
                    'venue_id' => $venue->venue_id,
                    'sport_id' => $sportId,
                    'nama_court' => "Court {$i}",
                    'status_ketersediaan' => 'Available',
                    'tipe_court' => $venue->tipe_arena ?? 'Outdoor',
                    'deskripsi' => 'Lapangan '.($sportId === $padelId ? 'Padel' : 'Tennis').' standar',
                    'harga_per_jam' => 150000,
                ]);
                $createdCount++;
            }
        }

        $this->command->info("Berhasil sinkronisasi: {$createdCount} court dibuat untuk {$venuesWithoutCourts->count()} venue.");
    }
}
