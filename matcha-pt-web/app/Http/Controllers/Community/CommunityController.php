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
        $dbCommunities = Community::with('players')->latest()->get();
        if ($dbCommunities->isNotEmpty()) {
            $communities = $dbCommunities->map(function ($c) {
                return [
                    'id' => $c->community_id,
                    'name' => $c->nama_community,
                    'sport' => 'Padel & Tennis',
                    'city' => 'Jakarta',
                    'member_count' => $c->players->count(),
                    'image' => $c->logo ?? 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?auto=format&fit=crop&w=800&q=80',
                    'tagline' => 'Komunitas Olahraga Matcha',
                    'description' => $c->deskripsi ?? 'Komunitas mabar Padel & Tennis di Matcha Match Arena.',
                    'schedule' => 'Rutin Setiap Pekan',
                ];
            })->toArray();
        } else {
            $communities = MatchaDummyDataService::getCommunities();
        }

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
            'nama_community'    => 'required|string|max:255',
            'sport_focus'       => 'required|in:Padel,Tennis,Both',
            'deskripsi'         => 'required|string',
            'jadwal_rutin'      => 'nullable|string|max:255',
            'tagline'           => 'nullable|string|max:255',
            'kota'              => 'required|string|max:255',
            'target_level'      => 'nullable|string',
            'membership_status' => 'nullable|string',
            'benefits'          => 'nullable|array',
            'venue_utama'       => 'nullable|string|max:255',
        ]);

        // Buat community baru dengan field yang valid di database
        $community = Community::create([
            'nama_community' => $validated['nama_community'],
            'deskripsi'      => $validated['deskripsi'],
            'logo'           => 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?auto=format&fit=crop&w=800&q=80',
        ]);

        // Jika pembuat komunitas adalah player, otomatis join ke komunitas ini
        if (Auth::check()) {
            Player::where('user_id', Auth::id())->update(['community_id' => $community->community_id]);
        }

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
        $community = Community::with('players')->find((int) $id);

        if (!$community) {
            $dummyList = MatchaDummyDataService::getCommunities();
            $dummy = collect($dummyList)->firstWhere('id', (int) $id) ?? $dummyList[0];

            $community = (object) [
                'community_id' => $dummy['id'],
                'nama_community' => $dummy['name'],
                'sport_utama' => $dummy['sport'] ?? 'Padel & Tennis',
                'deskripsi' => $dummy['description'] ?? 'Komunitas olahraga aktif.',
                'jadwal_rutin' => $dummy['schedule'] ?? 'Setiap Pekan',
                'logo' => $dummy['image'] ?? null,
                'players' => collect([]),
            ];
        }

        return view('communities.show', compact('community'));
    }

    /**
     * User bergabung ke komunitas.
     * Route: POST /communities/{id}/join
     */
    public function join(Request $request, $id)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu untuk bergabung ke komunitas.');
        }

        // Update field community_id pada tb_player milik Auth::user()
        $player = Player::where('user_id', Auth::id())->first();
        if ($player) {
            $player->update(['community_id' => (int) $id]);
        }

        return redirect()->route('communities.show', $id)
            ->with('success', 'Berhasil bergabung ke komunitas!');
    }

    /**
     * User meninggalkan komunitas.
     * Route: POST /communities/{id}/leave
     */
    public function leave(Request $request, $id)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // Set community_id = null pada tb_player milik Auth::user()
        Player::where('user_id', Auth::id())->update(['community_id' => null]);

        return redirect()->route('communities.index')
            ->with('success', 'Anda telah meninggalkan komunitas.');
    }
}
