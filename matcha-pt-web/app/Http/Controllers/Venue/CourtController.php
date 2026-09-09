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
            'city' => 'Jakarta',
            'pic_name' => $venueModel->owner?->nama ?? 'PIC Venue',
            'pic_phone' => $venueModel->owner?->no_hp ?? '-',
            'operating_hours' => '07:00 - 22:00',
            'unavailability_note' => 'Belum ada ketentuan availability khusus.',
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
    public function create($id)
    {
        $venue = $this->ownedVenue($id);

        return view('venues.courts.create', compact('venue'));
    }

    /**
     * Simpan court baru ke database.
     * Route: POST /venues/{id}/courts
     */
    public function store(Request $request, $id)
    {
        $venue = $this->ownedVenue($id);

        $validated = $request->validate([
            'nama_court'    => 'required|string|max:100',
            'tipe_court'    => 'required|in:Indoor,Outdoor,Semi-Indoor',
            'harga_per_jam' => 'required|numeric|min:0',
        ]);

        $sportId = Sport::query()
            ->whereRaw('LOWER(nama_sport) = ?', ['padel'])
            ->value('sport_id')
            ?? Sport::query()->value('sport_id');

        abort_unless($sportId, 422, 'Belum ada data sport yang tersedia.');

        $court = new Court();
        $court->forceFill([
            'venue_id' => $venue->venue_id,
            'sport_id' => $sportId,
            'nama_court' => $validated['nama_court'],
            'tipe_court' => $validated['tipe_court'],
            'harga_per_jam' => $validated['harga_per_jam'],
        ])->save();

        return redirect()->route('venues.index')
            ->with('success', 'Court berhasil ditambahkan!');
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
