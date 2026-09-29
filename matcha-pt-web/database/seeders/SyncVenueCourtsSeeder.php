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

        $venues = Venue::with('courts')->get();

        $createdCount = 0;

        foreach ($venues as $venue) {
            $sportTypeLower = strtolower(trim((string) ($venue->sport_type ?? '')));
            $venueNameLower = strtolower(trim((string) ($venue->nama_venue ?? '')));

            $hasPadel = str_contains($sportTypeLower, 'padel') || str_contains($venueNameLower, 'padel');
            $hasTennis = str_contains($sportTypeLower, 'tennis') || str_contains($sportTypeLower, 'tenis') || str_contains($venueNameLower, 'tennis') || str_contains($venueNameLower, 'tenis');
            $isDualSport = $hasPadel && $hasTennis;

            $existingCourts = $venue->courts;
            $hasPadelCourt = $existingCourts->contains('sport_id', $padelId);
            $hasTennisCourt = $existingCourts->contains('sport_id', $tennisId);

            if ($isDualSport) {
                // Pastikan venue dual sport memiliki minimal 1 court padel dan 1 court tennis
                if (! $hasPadelCourt) {
                    Court::create([
                        'venue_id' => $venue->venue_id,
                        'sport_id' => $padelId,
                        'nama_court' => 'Court Padel 1',
                        'status_ketersediaan' => 'Available',
                        'tipe_court' => $venue->tipe_arena ?? 'Outdoor',
                        'deskripsi' => 'Lapangan Padel panoramic',
                        'harga_per_jam' => 150000,
                    ]);
                    $createdCount++;
                }
                if (! $hasTennisCourt) {
                    Court::create([
                        'venue_id' => $venue->venue_id,
                        'sport_id' => $tennisId,
                        'nama_court' => 'Court Tennis 1',
                        'status_ketersediaan' => 'Available',
                        'tipe_court' => $venue->tipe_arena ?? 'Outdoor',
                        'deskripsi' => 'Lapangan Tennis standar',
                        'harga_per_jam' => 150000,
                    ]);
                    $createdCount++;
                }
            } elseif ($existingCourts->isEmpty()) {
                $sportId = $hasTennis ? $tennisId : $padelId;
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
        }

        $this->command->info("Berhasil sinkronisasi court: {$createdCount} court baru ditambahkan.");
    }
}
