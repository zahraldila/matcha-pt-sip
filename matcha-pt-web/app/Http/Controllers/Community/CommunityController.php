<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Community;
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
        // TODO [SMK 3]: Validasi request dan simpan ke tb_community
        // Field: nama_community, deskripsi, jadwal_rutin, sport_utama, admin/founder = Auth::id()
        $request->validate([
            'nama_community' => 'required|string|max:255',
            'sport_focus'    => 'required|in:Padel,Tennis,Both',
        ]);

        // Community::create([...]);

        return redirect()->route('communities.index')
            ->with('success', 'Komunitas berhasil dibuat!');
    }

    /**
     * Tampilkan halaman detail/profil komunitas.
     * Route: GET /communities/{id}
     */
    public function show($id)
    {
        // TODO [SMK 3]: Ambil data komunitas beserta daftar anggota
        // $community = Community::with('players')->findOrFail($id);
        $communities = MatchaDummyDataService::getCommunities();
        $community   = collect($communities)->firstWhere('id', (int) $id) ?? $communities[0];

        return view('communities.show', compact('community'));
    }

    /**
     * User bergabung ke komunitas.
     * Route: POST /communities/{id}/join
     */
    public function join(Request $request, $id)
    {
        // TODO [SMK 3]: Update field community_id pada tb_player milik Auth::user()
        // Player::where('user_id', Auth::id())->update(['community_id' => $id]);

        return redirect()->route('communities.show', $id)
            ->with('success', 'Berhasil bergabung ke komunitas!');
    }

    /**
     * User meninggalkan komunitas.
     * Route: POST /communities/{id}/leave
     */
    public function leave(Request $request, $id)
    {
        // TODO [SMK 3]: Set community_id = null pada tb_player milik Auth::user()
        // Player::where('user_id', Auth::id())->update(['community_id' => null]);

        return redirect()->route('communities.index')
            ->with('success', 'Anda telah meninggalkan komunitas.');
    }
}
