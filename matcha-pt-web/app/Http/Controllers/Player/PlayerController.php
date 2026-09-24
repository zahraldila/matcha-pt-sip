<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\MatchParticipant;
use App\Models\Player;
use App\Models\SessionModel;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlayerController extends Controller
{
    public function profile()
    {
        $user = Auth::user();
        $player = null;

        if ($user) {
            $player = Player::with('community')
                ->where('user_id', $user->user_id)
                ->orWhere('email', $user->email)
                ->first();

            // If player record doesn't exist yet, create one from user details
            if (! $player) {
                $player = Player::create([
                    'user_id' => $user->user_id,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'no_hp' => $user->no_hp,
                    'gender' => 'Male',
                    'usia' => 25,
                    'level' => 'Intermediate',
                    'rating' => 1.00,
                ]);
            }
        }

        $communities = Community::all();
        $recap = self::calculateRealPlayerRecap($user, $player);

        return view('players.profile', compact('recap', 'user', 'player', 'communities'));
    }

    public function updateProfile(Request $request, SupabaseStorageService $storageService)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Sanitize no_hp
        if ($request->has('no_hp')) {
            $cleanNoHp = preg_replace('/[^0-9]/', '', (string) $request->no_hp);
            $request->merge(['no_hp' => $cleanNoHp]);
        }

        $isAdmin = $user->isAdmin();

        $request->validate([
            'nama' => 'required|string|max:255',
            'no_hp' => [
                'required',
                'string',
                'regex:/^[0-9]{9,15}$/',
                'unique:tb_user,no_hp,'.$user->user_id.',user_id',
            ],
            'gender' => 'required|in:Male,Female',
            'usia' => $isAdmin ? 'nullable|integer|min:10|max:90' : 'required|integer|min:10|max:90',
            'level' => $isAdmin ? 'nullable|in:Newbie,Beginner,Intermediate,Advanced' : 'required|in:Newbie,Beginner,Intermediate,Advanced',
            'community_id' => 'nullable',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'no_hp.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'no_hp.regex' => 'Nomor WhatsApp hanya boleh berupa angka (9 - 15 digit).',
            'no_hp.unique' => 'Nomor WhatsApp ini sudah digunakan oleh akun lain.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'usia.required' => 'Usia wajib diisi.',
            'usia.min' => 'Usia minimal adalah 10 tahun.',
            'usia.max' => 'Usia maksimal adalah 90 tahun.',
            'level.required' => 'Kategori skill level wajib dipilih.',
            'foto.image' => 'File yang diunggah harus berupa gambar.',
            'foto.mimes' => 'Format foto harus JPEG, PNG, JPG, atau WEBP.',
            'foto.max' => 'Ukuran foto profil maksimal 2 MB.',
        ]);

        try {
            DB::beginTransaction();

            $cleanNoHp = preg_replace('/[^0-9]/', '', (string) $request->no_hp);
            $communityId = ($request->community_id && $request->community_id !== 'none') ? (int) $request->community_id : null;
            $playerLevel = $request->input('level', 'Intermediate');
            $playerUsia = $request->filled('usia') ? (int) $request->usia : 25;

            // Handle Avatar Upload / Remove
            $fotoUrl = $user->foto;

            if ($request->hasFile('foto')) {
                $uploadResult = $storageService->uploadAvatar($request->file('foto'));
                if (! $uploadResult['success']) {
                    DB::rollBack();

                    return back()->withErrors(['foto' => $uploadResult['message'] ?? 'Gagal mengunggah foto profil.'])->withInput();
                }

                // Hapus foto lama jika ada
                if (! empty($user->foto)) {
                    $storageService->deleteAvatar($user->foto);
                }

                $fotoUrl = $uploadResult['url'];
            } elseif ($request->input('hapus_foto') === '1') {
                if (! empty($user->foto)) {
                    $storageService->deleteAvatar($user->foto);
                }
                $fotoUrl = null;
            }

            // 1. Update tb_user
            $user->nama = trim($request->nama);
            $user->no_hp = $cleanNoHp;
            $user->foto = $fotoUrl;
            $user->save();

            // 2. Update or Create tb_player (Sync all existing player rows for this user)
            $matchingPlayers = Player::where('user_id', $user->user_id)
                ->orWhere('email', $user->email)
                ->orWhere('nama', $user->nama)
                ->get();

            if ($matchingPlayers->isNotEmpty()) {
                Player::where('user_id', $user->user_id)
                    ->orWhere('email', $user->email)
                    ->orWhere('nama', $user->nama)
                    ->update([
                        'user_id' => $user->user_id,
                        'nama' => trim($request->nama),
                        'no_hp' => $cleanNoHp,
                        'gender' => $request->gender,
                        'usia' => $playerUsia,
                        'level' => $playerLevel,
                        'community_id' => $communityId,
                        'foto' => $fotoUrl,
                    ]);
            } else {
                Player::create([
                    'user_id' => $user->user_id,
                    'community_id' => $communityId,
                    'nama' => trim($request->nama),
                    'usia' => $playerUsia,
                    'gender' => $request->gender,
                    'level' => $playerLevel,
                    'rating' => 1.00,
                    'no_hp' => $cleanNoHp,
                    'email' => strtolower(trim($user->email)),
                    'foto' => $fotoUrl,
                ]);
            }

            DB::commit();

            $successMsg = $isAdmin ? 'Profil administrator berhasil diperbarui!' : 'Profil pemain berhasil diperbarui!';

            return back()->with('success', $successMsg);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Gagal memperbarui profil: '.$e->getMessage()]);
        }
    }

    public function recap(Request $request)
    {
        $user = Auth::user();
        $isHost = $user && (bool) $user->is_host;
        $activeTab = $request->query('tab', $isHost ? 'host' : 'career');

        // 1. Data Riwayat Hosting (Untuk Host Game)
        $hostSessions = collect([]);
        $hostStats = [
            'total_sessions' => 0,
            'total_players' => 0,
            'completed_sessions' => 0,
            'favorite_venue' => '-',
        ];

        if ($isHost) {
            $dbSessions = SessionModel::where('host_user_id', $user->user_id)
                ->with(['sport', 'venue', 'courts', 'players'])
                ->latest('created_at')
                ->get();

            $totalPlayers = 0;
            $completedCount = 0;
            $venueCounts = [];

            $mappedSessions = $dbSessions->map(function ($s) use (&$totalPlayers, &$completedCount, &$venueCounts) {
                $joinedCount = $s->players->count();
                $totalPlayers += $joinedCount;

                $status = $s->status_session ?? 'Open';
                if (in_array(strtolower($status), ['ready for drawing', 'in progress', 'completed', 'finished'])) {
                    $completedCount++;
                }

                $venueName = $s->venue->nama_venue ?? 'Arena Olahraga';
                $venueCounts[$venueName] = ($venueCounts[$venueName] ?? 0) + 1;

                return [
                    'id' => $s->session_id,
                    'title' => $s->nama_session,
                    'sport' => $s->sport->nama_sport ?? 'Padel',
                    'venue' => $venueName,
                    'court' => $s->courts->first()->nama_court ?? 'Court 1',
                    'date' => $s->datetime ? $s->datetime->format('d M Y') : date('d M Y'),
                    'time' => $s->waktu_session ?? '18:30 WIB',
                    'quota' => (int) ($s->jumlah_pemain ?? 6),
                    'joined_count' => $joinedCount,
                    'status' => $status,
                    'scoring_system' => $s->scoring_system ?? 'Total of 3',
                    'players' => $s->players->map(function ($p) {
                        return [
                            'name' => $p->nama,
                            'gender' => $p->gender ?? 'Male',
                            'level' => $p->level ?? 'Intermediate',
                            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                        ];
                    })->toArray(),
                ];
            });

            // Cari venue terfavorit
            arsort($venueCounts);
            $favoriteVenue = ! empty($venueCounts) ? array_key_first($venueCounts) : 'Bonang Padel Arena';

            $totalCount = $mappedSessions->count();
            $hostStats = [
                'total_sessions' => $totalCount,
                'total_players' => $totalPlayers,
                'completed_sessions' => $completedCount,
                'favorite_venue' => $favoriteVenue,
            ];

            // Pagination (5 sesi per halaman)
            $perPage = 5;
            $currentPage = (int) $request->input('page', 1);
            $currentItems = $mappedSessions->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $hostSessions = new LengthAwarePaginator(
                $currentItems,
                $totalCount,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        // 2. Data Rekap Karir Pemain Nyata dari Database (BUG-MEM-003, 004, 005)
        $player = null;
        if ($user) {
            $player = Player::with('community')
                ->where('user_id', $user->user_id)
                ->orWhere('email', $user->email)
                ->first();
        }

        $recap = self::calculateRealPlayerRecap($user, $player);

        return view('players.recap', compact('user', 'isHost', 'activeTab', 'hostSessions', 'hostStats', 'recap'));
    }

    /**
     * Hitung statistik performa real player dari database (tb_match_participant, tb_match, tb_score)
     */
    public static function calculateRealPlayerRecap($user, ?Player $player = null): array
    {
        $playerName = $user->nama ?? ($player->nama ?? 'Pemain Matcha');
        $playerUsername = '@'.Str::slug($playerName, '_');
        $avatar = $user->foto ?? ($player->foto ?? null);
        if (empty($avatar)) {
            $avatar = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80';
        }

        $playerLevel = $player->level ?? 'Intermediate';
        $communityName = $player->community->nama_community ?? 'Personal (Non-Community)';
        $roleName = ($user && $user->role === 'venue_owner') ? 'Venue Owner' : (($user && $user->is_host) ? 'Host Game' : 'Member');

        if (! $player) {
            return [
                'player' => [
                    'name' => $playerName,
                    'username' => $playerUsername,
                    'role' => $roleName,
                    'level' => $playerLevel,
                    'community' => $communityName,
                    'avatar' => $avatar,
                    'total_matches' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'win_rate' => '0%',
                    'total_hours' => '0 Jam',
                    'streak' => '0 Match',
                ],
                'recent_matches' => [],
                'head_to_head' => [],
                'has_matches' => false,
            ];
        }

        // Ambil semua partisipasi pertandingan yang match-nya sudah Completed
        $participations = collect([]);
        try {
            $participations = MatchParticipant::where('player_id', $player->player_id)
                ->with([
                    'match.drawing.session.sport',
                    'match.drawing.session.venue',
                    'match.scores',
                    'match.participants.player',
                ])
                ->get();
        } catch (\Throwable $e) {
            $participations = collect([]);
        }

        $completedMatches = [];
        $totalWins = 0;
        $totalLosses = 0;
        $totalDraws = 0;
        $currentStreak = 0;
        $headToHeadMap = [];

        foreach ($participations as $part) {
            $match = $part->match;
            if (! $match || strtolower($match->status_match ?? '') !== 'completed') {
                continue;
            }

            $mySide = $part->side; // 'Team A' atau 'Team B'
            $isSideA = str_contains(strtolower($mySide ?? ''), 'a');

            // Tentukan hasil kemenangan match
            $winnerTeam = $match->winner_team;
            $isWinner = false;
            $isDraw = false;

            if (! empty($winnerTeam)) {
                $winnerSideA = str_contains(strtolower($winnerTeam), 'a');
                $isWinner = ($isSideA && $winnerSideA) || (! $isSideA && ! $winnerSideA);
            } else {
                // Evaluasi dari tb_score jika winner_team belum terisi eksplisit
                $scoreA = $match->scores->sum('game_score_a') + $match->scores->sum('set_score_a');
                $scoreB = $match->scores->sum('game_score_b') + $match->scores->sum('set_score_b');
                if ($scoreA > $scoreB) {
                    $isWinner = $isSideA;
                } elseif ($scoreB > $scoreA) {
                    $isWinner = ! $isSideA;
                } else {
                    $isDraw = true;
                }
            }

            if ($isWinner) {
                $totalWins++;
                $currentStreak++;
            } elseif ($isDraw) {
                $totalDraws++;
                $currentStreak = 0;
            } else {
                $totalLosses++;
                $currentStreak = 0;
            }

            // Partner & Lawan
            $partnerName = 'Solo';
            $opponents = [];
            foreach ($match->participants as $otherPart) {
                if ($otherPart->player_id == $player->player_id) {
                    continue;
                }
                $otherSideA = str_contains(strtolower($otherPart->side ?? ''), 'a');
                $pName = $otherPart->player->nama ?? 'Pemain';

                if ($otherSideA === $isSideA) {
                    $partnerName = $pName;
                } else {
                    $opponents[] = $pName;
                    if (! isset($headToHeadMap[$pName])) {
                        $headToHeadMap[$pName] = ['opponent' => $pName, 'win' => 0, 'lose' => 0, 'played' => 0];
                    }
                    $headToHeadMap[$pName]['played']++;
                    if ($isWinner) {
                        $headToHeadMap[$pName]['win']++;
                    } else {
                        $headToHeadMap[$pName]['lose']++;
                    }
                }
            }

            $session = $match->drawing->session ?? null;
            $sportName = $session->sport->nama_sport ?? 'Padel';
            $venueName = $session->venue->nama_venue ?? 'Arena Olahraga';
            $matchDate = $match->updated_at ? $match->updated_at->format('d M Y') : ($session && $session->datetime ? $session->datetime->format('d M Y') : date('d M Y'));

            $scoreDisplay = $match->hasil_pertandingan ?: 'Set Selesai';
            if ($match->scores->isNotEmpty()) {
                $sumA = $match->scores->sum('game_score_a');
                $sumB = $match->scores->sum('game_score_b');
                $scoreDisplay = $isSideA ? "{$sumA} - {$sumB}" : "{$sumB} - {$sumA}";
            }

            $completedMatches[] = [
                'sport' => $sportName,
                'venue' => $venueName,
                'result' => $isWinner ? 'WIN' : ($isDraw ? 'DRAW' : 'LOSE'),
                'score' => $scoreDisplay,
                'partner' => $partnerName,
                'opponents' => ! empty($opponents) ? $opponents : ['Lawan'],
                'match_date' => $matchDate,
                'timestamp' => $match->updated_at ? $match->updated_at->timestamp : 0,
            ];
        }

        $totalMatches = count($completedMatches);
        $winRatePercent = $totalMatches > 0 ? round(($totalWins / $totalMatches) * 100) : 0;
        $totalHours = $totalMatches > 0 ? round($totalMatches * 0.5, 1).' Jam' : '0 Jam';
        $streakDisplay = $currentStreak > 0 ? "🔥 {$currentStreak} Win Streak" : ($totalMatches > 0 ? '0 Win Streak' : '0 Match');

        // Urutkan recent matches dari yang paling baru
        usort($completedMatches, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        $recentMatches = array_slice($completedMatches, 0, 10);

        // Head to head
        $headToHead = array_values($headToHeadMap);
        usort($headToHead, fn ($a, $b) => $b['played'] <=> $a['played']);
        $headToHead = array_slice($headToHead, 0, 5);

        return [
            'player' => [
                'name' => $playerName,
                'username' => $playerUsername,
                'role' => $roleName,
                'level' => $playerLevel,
                'community' => $communityName,
                'avatar' => $avatar,
                'total_matches' => $totalMatches,
                'wins' => $totalWins,
                'losses' => $totalLosses,
                'win_rate' => $winRatePercent.'%',
                'total_hours' => $totalHours,
                'streak' => $streakDisplay,
            ],
            'recent_matches' => $recentMatches,
            'head_to_head' => $headToHead,
            'has_matches' => $totalMatches > 0,
        ];
    }

    public function toggleHost(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Balikkan nilai boolean is_host
        $user->is_host = ! $user->is_host;
        $user->save();

        $statusMsg = $user->is_host
            ? 'Mode Host berhasil diaktifkan! Sekarang kamu bisa membuat pertandingan.'
            : 'Mode Host dinonaktifkan. Status kamu kembali menjadi Pemain biasa.';

        return back()->with('success', $statusMsg);
    }

    /**
     * Halaman manajemen akun/pengguna — khusus Admin.
     * Route: GET /admin/users
     */
    public function manageUsers(Request $request)
    {
        if (! Auth::check() || ! Auth::user()->isAdmin()) {
            abort(403, 'Akses ditolak: Hanya Administrator yang dapat mengakses halaman ini.');
        }

        $search = trim($request->get('q', ''));
        $filterRole = $request->get('role', 'all');

        $query = User::with('player')->orderBy('user_id', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'ilike', '%'.$search.'%')
                    ->orWhere('email', 'ilike', '%'.$search.'%')
                    ->orWhere('no_hp', 'ilike', '%'.$search.'%');
            });
        }

        if ($filterRole !== 'all') {
            $query->where('role', $filterRole);
        }

        $users = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => User::count(),
            'member' => User::where('role', 'member')->count(),
            'host' => User::where('role', 'host')->count(),
            'venue_owner' => User::where('role', 'venue_owner')->count(),
            'admin' => User::where('role', 'admin')->count(),
        ];

        return view('admin.users', compact('users', 'search', 'filterRole', 'counts'));
    }

    /**
     * Update data pengguna oleh Admin.
     * Route: PUT /admin/users/{id}
     */
    public function updateUser(Request $request, $id)
    {
        if (! Auth::check() || ! Auth::user()->isAdmin()) {
            abort(403, 'Akses ditolak: Hanya Administrator yang dapat melakukan tindakan ini.');
        }

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:tb_user,email,'.$user->user_id.',user_id',
            'no_hp' => 'nullable|string|max:20',
            'role' => 'required|in:member,host,venue_owner,admin',
            'is_host' => 'nullable',
            'password' => 'nullable|string|min:6',
        ]);

        $user->nama = $validated['nama'];
        $user->email = $validated['email'];
        $user->no_hp = $validated['no_hp'] ?? null;
        $user->role = $validated['role'];
        $user->is_host = $request->has('is_host') || $validated['role'] === 'host';

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Sinkronisasi data ke player record jika ada
        if ($user->player) {
            $user->player->update([
                'nama' => $user->nama,
                'no_hp' => $user->no_hp,
            ]);
        }

        return back()->with('success', "Data pengguna \"{$user->nama}\" berhasil diperbarui.");
    }

    /**
     * Hapus akun pengguna oleh Admin.
     * Route: DELETE /admin/users/{id}
     */
    public function destroyUser($id)
    {
        if (! Auth::check() || ! Auth::user()->isAdmin()) {
            abort(403, 'Akses ditolak: Hanya Administrator yang dapat melakukan tindakan ini.');
        }

        $user = User::findOrFail($id);

        // Jangan izinkan admin menghapus akunnya sendiri
        if (Auth::id() == $user->user_id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        // Cek apakah user adalah host di sesi mabar
        if ($user->hostedSessions()->exists()) {
            return back()->with('error', "Pengguna \"{$user->nama}\" tidak dapat dihapus karena masih tercatat sebagai Host sesi mabar. Harap alihkan atau selesaikan sesi terlebih dahulu.");
        }

        // Cek apakah user memiliki venue
        if ($user->ownedVenues()->exists()) {
            return back()->with('error', "Pengguna \"{$user->nama}\" tidak dapat dihapus karena masih memiliki venue terdaftar. Harap hapus atau alihkan kepemilikan venue terlebih dahulu.");
        }

        try {
            DB::beginTransaction();

            // Putus relasi player agar data riwayat pertandingan tetap terjaga
            if ($user->player) {
                $user->player->update(['user_id' => null]);
            }

            // Lepas keterikatan kominitas yang dibuat
            Community::where('created_by', $user->user_id)->update(['created_by' => null]);

            $userName = $user->nama;
            $user->delete();

            DB::commit();

            return back()->with('success', "Pengguna \"{$userName}\" berhasil dihapus.");
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal menghapus pengguna: '.$e->getMessage());
        }
    }
}
