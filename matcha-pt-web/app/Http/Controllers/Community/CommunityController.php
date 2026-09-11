<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Player;
use App\Services\MatchaDummyDataService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use App\Exceptions\ConfigurationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'all'); // 'all', 'joined'
        $selectedSport = $request->query('sport', 'all');
        $search = trim($request->query('q', $request->query('search', '')));

        $currentUserCommunityId = Auth::check() ? Player::where('user_id', Auth::id())->value('community_id') : null;

        $dbCommunities = Community::with(['players.user', 'creator'])->latest()->get();
        if ($dbCommunities->isNotEmpty()) {
            $allCommunities = $dbCommunities->map(function ($c) use ($currentUserCommunityId) {
                return [
                    'id' => $c->community_id,
                    'name' => $c->nama_community,
                    'sport' => $c->sport,
                    'city' => $c->kota_homebase ?: (str_contains(strtolower($c->nama_community . ' ' . $c->deskripsi), 'bandung') ? 'Bandung' : 'Jakarta'),
                    'members_count' => $c->players->count(),
                    'admin_name' => $c->admin_name,
                    'image' => $c->logo ?: asset('images/default-community.jpg'),
                    'tagline' => $c->tagline ?: 'Komunitas Olahraga Matcha',
                    'description' => $c->deskripsi ?? ('Komunitas mabar ' . $c->sport . ' di Matcha Match Arena.'),
                    'schedule' => $c->jadwal_rutin ?: 'Rutin Setiap Pekan',
                    'status' => $c->status_keanggotaan ?: 'Active',
                    'is_member' => ($currentUserCommunityId && $currentUserCommunityId == $c->community_id),
                ];
            });
        } else {
            $allCommunities = collect(MatchaDummyDataService::getCommunities());
        }

        // Sport filter
        if ($selectedSport !== 'all') {
            $allCommunities = $allCommunities->filter(function ($c) use ($selectedSport) {
                return str_contains(strtolower($c['sport']), strtolower($selectedSport));
            });
        }

        // Search filter (name, city, description, admin_name, tagline)
        if ($search !== '') {
            $searchLower = strtolower($search);
            $allCommunities = $allCommunities->filter(function ($c) use ($searchLower) {
                $inName = str_contains(strtolower($c['name'] ?? ''), $searchLower);
                $inCity = str_contains(strtolower($c['city'] ?? ''), $searchLower);
                $inDesc = str_contains(strtolower($c['description'] ?? ''), $searchLower);
                $inAdmin = str_contains(strtolower($c['admin_name'] ?? ''), $searchLower);
                $inTagline = str_contains(strtolower($c['tagline'] ?? ''), $searchLower);
                $inSport = str_contains(strtolower($c['sport'] ?? ''), $searchLower);
                return $inName || $inCity || $inDesc || $inAdmin || $inTagline || $inSport;
            });
        }

        // Tab counts (reflecting search results if active)
        $totalCommunitiesCount = $allCommunities->count();
        $myCommunitiesCount = $allCommunities->where('is_member', true)->count();

        // Active tab filter
        if ($activeTab === 'joined') {
            $filteredCommunities = $allCommunities->where('is_member', true)->values();
        } else {
            $filteredCommunities = $allCommunities->values();
        }

        // Pagination: 6 items per page
        $perPage = 6;
        $currentPage = Paginator::resolveCurrentPage('page') ?: 1;
        $totalItems = $filteredCommunities->count();
        $currentItems = $filteredCommunities->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $communities = new LengthAwarePaginator(
            $currentItems,
            $totalItems,
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
        );
        $communities->withQueryString();

        return view('communities.index', compact(
            'communities',
            'activeTab',
            'selectedSport',
            'search',
            'myCommunitiesCount',
            'totalCommunitiesCount'
        ));
    }

    public function create()
    {
        return view('communities.create');
    }

    /**
     * Upload logo komunitas ke Supabase Storage.
     * Route: POST /communities/upload-logo
     */
    public function uploadLogo(Request $request, SupabaseStorageService $storageService)
    {
        $request->validate([
            'logo' => 'required|file|mimes:jpeg,jpg,png|max:2048',
        ], [
            'logo.required' => 'File logo belum dipilih.',
            'logo.mimes'    => 'Format logo tidak valid. Hanya JPG, JPEG, atau PNG yang diterima.',
            'logo.max'      => 'Ukuran logo maksimal 2 MB.',
        ]);

        try {
            $result = $storageService->uploadLogo($request->file('logo'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Logo upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses file logo: ' . $e->getMessage(),
            ], 500);
        }

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success'  => true,
            'url'      => $result['url'],
            'filename' => $result['filename'],
        ]);
    }

    /**
     * Simpan komunitas baru ke database.
     * Route: POST /communities
     */
    public function store(Request $request, SupabaseStorageService $storageService)
    {
        // Validasi request
        $validated = $request->validate([
            'nama_community'     => 'required|string|max:255',
            'sport'              => 'nullable|string|in:padel,tennis,all_racquet,Padel,Tennis,Both,both',
            'sport_focus'        => 'nullable|string|in:padel,tennis,all_racquet,Padel,Tennis,Both,both',
            'deskripsi'          => 'required|string',
            'jadwal_rutin'       => 'nullable|string|max:255',
            'tagline'            => 'nullable|string|max:255',
            'kota_homebase'      => 'nullable|string|max:255',
            'kota'               => 'nullable|string|max:255',
            'target_level'       => 'nullable|string|max:100',
            'status_keanggotaan' => 'nullable|string|max:100',
            'membership_status'  => 'nullable|string|max:100',
            'benefits'           => 'nullable|array',
            'homebase_venue'     => 'nullable|string|max:255',
            'venue_utama'        => 'nullable|string|max:255',
            'logo_url'           => 'nullable|string',
            'logo'               => 'nullable|file|mimes:jpeg,jpg,png|max:2048',
        ], [
            'logo.mimes' => 'Format logo tidak valid. Hanya JPG, JPEG, atau PNG yang diterima.',
            'logo.max'   => 'Ukuran logo maksimal 2 MB.',
        ]);

        // Normalisasi cabang olahraga ke nilai konsisten: padel, tennis, all_racquet
        $rawSport = $request->input('sport') ?: $request->input('sport_focus', 'padel');
        $normalizedSport = match (strtolower(trim((string) $rawSport))) {
            'tennis' => 'tennis',
            'all_racquet', 'both', 'all racquet', 'padel & tennis' => 'all_racquet',
            default => 'padel',
        };

        // Normalisasi benefit ke array key konsisten
        $rawBenefits = (array) $request->input('benefits', []);
        $benefitMap = [
            'sesi mabar mingguan' => 'weekly_mabar',
            'weekly_mabar' => 'weekly_mabar',
            'internal tournament' => 'internal_tournament',
            'internal_tournament' => 'internal_tournament',
            'coaching clinic' => 'coaching_clinic',
            'coaching_clinic' => 'coaching_clinic',
            'whatsapp group aktif' => 'whatsapp_group',
            'whatsapp_group' => 'whatsapp_group',
            'diskon sewa court' => 'court_discount',
            'court_discount' => 'court_discount',
            'jersey official club' => 'official_jersey',
            'official_jersey' => 'official_jersey',
            'tracking rating pemain' => 'rating_tracking',
            'rating_tracking' => 'rating_tracking',
            'networking profesional' => 'networking',
            'networking' => 'networking',
        ];
        $normalizedBenefits = [];
        foreach ($rawBenefits as $b) {
            $key = strtolower(trim((string) $b));
            if (isset($benefitMap[$key])) {
                $normalizedBenefits[] = $benefitMap[$key];
            } else if (!empty($key)) {
                $normalizedBenefits[] = $key;
            }
        }
        $normalizedBenefits = array_values(array_unique($normalizedBenefits));

        $logoUrl = $validated['logo_url'] ?? null;

        // Fallback: Jika logo diunggah langsung bersamaan dengan form submit
        if (!$logoUrl && $request->hasFile('logo')) {
            try {
                $uploadResult = $storageService->uploadLogo($request->file('logo'));
                if ($uploadResult['success']) {
                    $logoUrl = $uploadResult['url'];
                } else {
                    return redirect()->back()
                        ->withInput()
                        ->withErrors(['logo' => $uploadResult['message']]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Logo upload error during store: ' . $e->getMessage());
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['logo' => 'Gagal mengunggah logo: ' . $e->getMessage()]);
            }
        }

        // Hanya simpan logo jika benar-benar berasal dari Supabase Storage public object
        if ($logoUrl && !str_contains($logoUrl, '/storage/v1/object/public/')) {
            $logoUrl = null;
        }

        // Simpan community dan daftarkan creator sebagai member dalam satu database transaction
        try {
            DB::beginTransaction();

            // Buat community baru dengan seluruh field yang valid di database
            $community = Community::create([
                'nama_community'     => $validated['nama_community'],
                'tagline'            => $request->input('tagline') ?: null,
                'kota_homebase'      => $request->input('kota_homebase') ?: ($request->input('kota') ?: null),
                'sport'              => $normalizedSport,
                'target_level'       => $request->input('target_level') ?: null,
                'status_keanggotaan' => $request->input('status_keanggotaan') ?: ($request->input('membership_status') ?: 'Open'),
                'deskripsi'          => $validated['deskripsi'],
                'jadwal_rutin'       => $request->input('jadwal_rutin') ?: null,
                'homebase_venue'     => $request->input('homebase_venue') ?: ($request->input('venue_utama') ?: null),
                'benefits'           => !empty($normalizedBenefits) ? $normalizedBenefits : null,
                'created_by'         => Auth::id(),
                'logo'               => $logoUrl ?: null,
            ]);

            // Daftarkan authenticated creator sebagai member komunitas (menggunakan struktur membership tb_player)
            if (Auth::check()) {
                $user = Auth::user();
                $player = Player::where('user_id', $user->user_id)->first();
                if ($player) {
                    $player->community_id = $community->community_id;
                    $player->save();
                } else {
                    Player::create([
                        'user_id'      => $user->user_id,
                        'community_id' => $community->community_id,
                        'nama'         => $user->nama,
                        'rating'       => 1.00,
                        'no_hp'        => $user->no_hp,
                        'email'        => $user->email,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Create community error: ' . $e->getMessage());
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Gagal membuat komunitas: ' . $e->getMessage()]);
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

        $user = Auth::user();
        $player = Player::where('user_id', $user->user_id)->first();

        // Cek apakah user sudah menjadi anggota komunitas ini (termasuk creator)
        if ($player && $player->community_id == (int) $id) {
            return redirect()->route('communities.show', $id)
                ->with('info', 'Anda sudah menjadi bagian dari komunitas.');
        }

        // Jika belum memiliki record Player, buatkan
        if (!$player) {
            Player::create([
                'user_id'      => $user->user_id,
                'community_id' => (int) $id,
                'nama'         => $user->nama,
                'rating'       => 1.00,
                'no_hp'        => $user->no_hp,
                'email'        => $user->email,
            ]);
        } else {
            // Update field community_id pada tb_player milik Auth::user()
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
