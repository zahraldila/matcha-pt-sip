<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Player;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * CommunityController — SMK 3
 * ----------------------------
 * Tugas   : Kelola pendaftaran dan keanggotaan Komunitas.
 * Tabel DB: tb_community, tb_player (field community_id)
 *
 * Metode yang sudah ada : index(), create()
 * Metode yang perlu diisi: store(), show(), join(), leave()
 */
class CommunityController extends Controller
{
    public function index()
    {
        $communities = MatchaDummyDataService::getCommunities();
        return view('communities.index', compact('communities'));
    }

    public function create()
    {
        return view('communities.create');
    }

    /**
     * Simpan komunitas baru ke database.
     * Route: POST /communities
     */
    public function store(Request $request)
    {
        // Validasi request
        $validated = $request->validate([
            'nama_community'   => 'required|string|max:255',
            'sport_focus'      => 'required|in:Padel,Tennis,Both',
            'deskripsi'        => 'required|string',
            'jadwal_rutin'     => 'nullable|string|max:255',
            'tagline'          => 'nullable|string|max:255',
            'kota'             => 'required|string|max:255',
            'target_level'     => 'nullable|string',
            'membership_status' => 'nullable|string',
            'benefits'         => 'nullable|array',
            'venue_utama'      => 'nullable|string|max:255',
        ]);

        // Buat community baru
        $community = Community::create([
            'nama_community' => $validated['nama_community'],
            'deskripsi'      => $validated['deskripsi'],
            'jadwal_rutin'   => $validated['jadwal_rutin'] ?? null,
            'sport_utama'    => $validated['sport_focus'],
        ]);

        return redirect()->route('communities.show', $community->community_id)
            ->with('success', 'Komunitas berhasil dibuat!');
    }

    /**
     * Tampilkan halaman detail/profil komunitas.
     * Route: GET /communities/{id}
     */
    public function show($id)
    {
        // Ambil data komunitas beserta daftar anggota
        $community = Community::with('players')->findOrFail($id);

        return view('communities.show', compact('community'));
    }

    /**
     * User bergabung ke komunitas.
     * Route: POST /communities/{id}/join
     */
    public function join(Request $request, $id)
    {
        // Update field community_id pada tb_player milik Auth::user()
        Player::where('user_id', Auth::id())->update(['community_id' => $id]);

        return redirect()->route('communities.show', $id)
            ->with('success', 'Berhasil bergabung ke komunitas!');
    }

    /**
     * User meninggalkan komunitas.
     * Route: POST /communities/{id}/leave
     */
    public function leave(Request $request, $id)
    {
        // Set community_id = null pada tb_player milik Auth::user()
        Player::where('user_id', Auth::id())->update(['community_id' => null]);

        return redirect()->route('communities.index')
            ->with('success', 'Anda telah meninggalkan komunitas.');
    }
}
