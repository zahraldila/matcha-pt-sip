<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Sport;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * CourtController — SMK 2
 * -------------------------
 * Tugas   : Kelola data Court/Lapangan milik sebuah Venue.
 * Tabel DB: tb_court
 *
 * Metode yang perlu diisi:
 *   - index()  → tampilkan daftar court milik venue
 *   - create() → tampilkan form tambah court baru
 *   - store()  → simpan court baru ke tb_court
 */
class CourtController extends Controller
{
    /**
     * Tampilkan semua court milik venue tertentu.
     * Route: GET /venues/{id}/courts
     */
    public function index($id)
    {
        $venueModel = $this->ownedVenue($id, true);
        $venue = [
            'id' => $venueModel->venue_id,
            'name' => $venueModel->nama_venue,
            'sport' => $venueModel->courts->first()?->sport?->nama_sport ?? 'Padel',
            'address' => $venueModel->alamat,
            'city' => $venueModel->kota ?: 'Jakarta',
            'pic_name' => $venueModel->nama_pic ?: ($venueModel->owner?->nama ?? 'PIC Venue'),
            'pic_phone' => $venueModel->no_whatsapp ?: ($venueModel->owner?->no_hp ?? '-'),
            'operating_hours' => $venueModel->jam_operasional ?: '07:00 - 22:00',
            'hari_buka' => $venueModel->hari_buka ?: 'Setiap Hari (Senin - Minggu)',
            'unavailability_note' => $venueModel->catatan ?: 'Sesuai jadwal ketersediaan lapangan reguler.',
            'catatan' => $venueModel->catatan,
            'image' => $venueModel->foto ?? 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80',
            'facilities' => explode(',', $venueModel->fasilitas ?: 'Belum dicatat'),
            'courts' => $venueModel->courts->map(fn (Court $court) => [
                'name' => $court->nama_court,
                'status' => $court->status_ketersediaan ?? 'Available',
                'type' => $court->tipe_court ?? 'Tidak ditentukan',
            ]),
        ];

        return view('venues.show', compact('venue'));
    }

    /**
     * Tampilkan form tambah court baru untuk venue tertentu.
     * Route: GET /venues/{id}/courts/create
     */
    public function create(Request $request, $id)
    {
        $venue = $this->ownedVenue($id);
        $count = max(1, min(12, (int) $request->query('count', 1)));
        $sport = $request->query('sport', 'Padel');
        $type = in_array($request->query('type'), ['Indoor', 'Outdoor', 'Semi-Indoor']) 
            ? $request->query('type') 
            : 'Indoor';

        $sports = Sport::all();

        return view('venues.courts.create', compact('venue', 'count', 'sport', 'type', 'sports'));
    }

    /**
     * Simpan court baru ke database (mendukung batch multi-court).
     * Route: POST /venues/{id}/courts
     */
    public function store(Request $request, $id)
    {
        $venue = $this->ownedVenue($id);

        // Jika dikirim dalam format batch multi-court (courts[])
        if ($request->has('courts') && is_array($request->input('courts'))) {
            $validated = $request->validate([
                'courts'                 => 'required|array|min:1',
                'courts.*.nama_court'    => 'required|string|max:100',
                'courts.*.sport_id'      => 'nullable',
                'courts.*.sport_name'    => 'nullable|string',
                'courts.*.tipe_court'    => 'required|in:Indoor,Outdoor,Semi-Indoor',
                'courts.*.harga_per_jam' => 'required|numeric|min:0',
            ]);

            $padelSportId = Sport::whereRaw('LOWER(nama_sport) = ?', ['padel'])->value('sport_id') 
                ?? Sport::value('sport_id');
            $tennisSportId = Sport::whereRaw('LOWER(nama_sport) = ?', ['tennis'])->value('sport_id') 
                ?? $padelSportId;

            foreach ($validated['courts'] as $c) {
                $targetSportId = $c['sport_id'] ?? null;
                if (!$targetSportId && !empty($c['sport_name'])) {
                    $targetSportId = strtolower($c['sport_name']) === 'tennis' ? $tennisSportId : $padelSportId;
                }
                if (!$targetSportId) {
                    $targetSportId = $padelSportId;
                }

                Court::create([
                    'venue_id'            => $venue->venue_id,
                    'sport_id'            => $targetSportId,
                    'nama_court'          => $c['nama_court'],
                    'status_ketersediaan' => 'Available',
                    'deskripsi'           => "Tipe: {$c['tipe_court']} • Rp " . number_format($c['harga_per_jam']) . "/jam",
                ]);
            }

            return redirect()->route('venues.show', $venue->venue_id)
                ->with('success', count($validated['courts']) . ' Court berhasil ditambahkan!');
        }

        // Fallback untuk single court
        $validated = $request->validate([
            'nama_court'    => 'required|string|max:100',
            'tipe_court'    => 'required|in:Indoor,Outdoor,Semi-Indoor',
            'harga_per_jam' => 'required|numeric|min:0',
            'sport_id'      => 'nullable',
            'sport_name'    => 'nullable|string',
        ]);

        $sportId = $validated['sport_id'] ?? null;
        if (!$sportId && !empty($validated['sport_name'])) {
            $sportId = Sport::whereRaw('LOWER(nama_sport) = ?', [strtolower($validated['sport_name'])])->value('sport_id');
        }
        if (!$sportId) {
            $sportId = Sport::whereRaw('LOWER(nama_sport) = ?', ['padel'])->value('sport_id') ?? Sport::value('sport_id');
        }

        Court::create([
            'venue_id'            => $venue->venue_id,
            'sport_id'            => $sportId,
            'nama_court'          => $validated['nama_court'],
            'status_ketersediaan' => 'Available',
            'deskripsi'           => "Tipe: {$validated['tipe_court']} • Rp " . number_format($validated['harga_per_jam']) . "/jam",
        ]);

        return redirect()->route('venues.show', $venue->venue_id)
            ->with('success', 'Court berhasil ditambahkan!');
    }

    public function destroy($id, $courtId)
    {
        $venue = $this->ownedVenue($id);
        $court = Court::where('venue_id', $venue->venue_id)->where('court_id', $courtId)->firstOrFail();
        $court->delete();

        return redirect()->route('venues.show', $venue->venue_id)
            ->with('success', 'Lapangan / Court berhasil dihapus.');
    }

    private function ownedVenue($id, bool $withCourts = false): Venue
    {
        $query = Venue::query()
            ->where('venue_id', $id)
            ->where('owner_user_id', Auth::id());

        if ($withCourts) {
            $query->with('courts');
        }

        return $query->firstOrFail();
    }
}
