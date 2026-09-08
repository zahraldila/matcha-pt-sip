<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Venue;
use Illuminate\Http\Request;

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
        // TODO [SMK 2]: Ambil data venue berdasarkan $id dan list court-nya
        $venue = Venue::with('courts')->findOrFail($id);

        return view('venues.courts.index', compact('venue'));
    }

    /**
     * Tampilkan form tambah court baru untuk venue tertentu.
     * Route: GET /venues/{id}/courts/create
     */
    public function create($id)
    {
        // TODO [SMK 2]: Validasi bahwa user adalah venue_owner dari venue ini
        $venue = Venue::findOrFail($id);

        return view('venues.courts.create', compact('venue'));
    }

    /**
     * Simpan court baru ke database.
     * Route: POST /venues/{id}/courts
     */
    public function store(Request $request, $id)
    {
        // TODO [SMK 2]: Validasi request, lalu simpan ke tb_court
        // Contoh field: nama_court, tipe_court, harga_per_jam, sport_id
        $request->validate([
            'nama_court'    => 'required|string|max:100',
            'sport_id'      => 'required|integer',
            'harga_per_jam' => 'nullable|numeric',
        ]);

        // Court::create([...]);

        return redirect()->route('venues.courts.index', $id)
            ->with('success', 'Court berhasil ditambahkan!');
    }
}
