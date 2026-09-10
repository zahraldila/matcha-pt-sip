<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Models\SessionModel;
use App\Models\Venue;
use App\Models\Court;
use App\Models\Sport;
use App\Models\Player;
use App\Services\MatchaDummyDataService;
use App\Services\Drawing\TeamAmericanoService;
use App\Services\Drawing\AmericanoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $selectedSport = $request->query('sport', 'all');

        // Fetch 100% real sessions from Supabase database
        $sessionQuery = SessionModel::with(['sport', 'venue', 'courts', 'players', 'host'])
            ->orderByRaw("CASE WHEN status_session = 'Open' THEN 0 WHEN status_session = 'Ready for Drawing' THEN 1 ELSE 2 END")
            ->latest('created_at');

        if ($selectedSport !== 'all') {
            $sessionQuery->whereHas('sport', function ($q) use ($selectedSport) {
                $q->whereRaw('LOWER(nama_sport) = ?', [strtolower($selectedSport)]);
            });
        }

        $dbSessions = $sessionQuery->get();

        $games = $dbSessions->map(function ($s) {
            $joinedCount = $s->players->count();
            $quota = (int) ($s->jumlah_pemain ?? 6);
            $slotLeft = max(0, $quota - $joinedCount);
            $status = $slotLeft === 0 ? 'Ready for Drawing' : "Open ({$slotLeft} Slot Left)";

            return [
                'id' => $s->session_id,
                'title' => $s->nama_session,
                'sport' => $s->sport->nama_sport ?? 'Padel',
                'venue_id' => $s->venue_id,
                'venue_name' => $s->venue->nama_venue ?? 'Arena Olahraga',
                'court_name' => $s->courts->first()->nama_court ?? 'Court 1',
                'date' => $s->datetime ? $s->datetime->format('Y-m-d') : date('Y-m-d'),
                'time' => $s->waktu_session ?? '18:30 WIB',
                'duration' => '2 Jam',
                'quota' => $quota,
                'joined_count' => $joinedCount,
                'status' => $status,
                'level_recommendation' => 'All Level Welcome',
                'match_format' => 'Americano / Double',
                'scoring_system' => $s->scoring_system ?? 'Total of 3',
                'host' => [
                    'name' => $s->host->nama ?? 'Host Matcha',
                    'role' => 'Host Game',
                    'level' => 'Intermediate',
                    'phone' => $s->host->no_hp ?? '-',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ],
                'participants' => $s->players->map(function ($p) {
                    return [
                        'name' => $p->nama,
                        'gender' => $p->gender ?? 'Male',
                        'age' => $p->usia ?? 25,
                        'level' => $p->level ?? 'Intermediate',
                        'is_member' => !empty($p->user_id),
                        'phone' => $p->no_hp,
                        'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                    ];
                })->toArray(),
                'drawing' => null,
            ];
        })->values()->all();

        return view('games.index', compact('games', 'selectedSport'));
    }

    public function create()
    {
        if (Auth::user()->role !== 'host') {
            return redirect()->route('games.index')->with('error', 'Akses ditolak: Fitur ini khusus untuk akun Host Game.');
        }

        $venues = Venue::with('courts')->get();

        if ($venues->isEmpty()) {
            $venues = MatchaDummyDataService::getVenues();
        }

        $hostPlayer = Player::where('user_id', Auth::id())->first();

        return view('games.create', compact('venues', 'hostPlayer'));
    }

    public function searchPlayers(Request $request)
    {
        $search = $request->query('search', '');

        $players = Player::query()
            ->where(function ($query) use ($search) {
                $query->where('nama', 'ilike', "%{$search}%");
            })
            ->orderBy('nama')
            ->limit(20)
            ->get([
                'player_id',
                'nama',
                'gender',
                'level',
            ]);

        return response()->json($players);
    }

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'host') {
            return redirect()->route('games.index')
                ->with('error', 'Akses ditolak: Fitur ini khusus untuk akun Host Game.');
        }

        $request->validate([
            'nama_session' => 'required|string|max:255',
            'sport' => 'required|in:Padel,Tennis',
            'format' => 'required|string',
            'num_courts' => 'required|integer|min:1|max:4',
            'venue_id' => 'required|integer|exists:tb_venue,venue_id',
            'scoring_system' => 'required|string|max:100',
            'rank_by' => 'required|in:point,win',

            'players' => 'required|array|min:4',

            'players.*.player_id' => 'nullable|integer|exists:tb_player,player_id',
            'players.*.name' => 'required|string|max:255',
            'players.*.gender' => 'required|in:Male,Female',
            'players.*.level' => 'required|string|max:50',
            'players.*.type' => 'required|in:Host,Member,Guest',
        ]);

        try {
            DB::beginTransaction();

            // 1. Cari sport berdasarkan nama
            $sport = Sport::where('nama_sport', $request->sport)->first();

            if (!$sport) {
                throw new \Exception("Sport {$request->sport} tidak ditemukan.");
            }

            // 2. Cari court yang tersedia sesuai venue + sport
            $courts = Court::where('venue_id', $request->venue_id)
                ->where('sport_id', $sport->sport_id)
                ->where('status_ketersediaan', 'Available')
                ->take((int) $request->num_courts)
                ->get();

            if ($courts->count() < (int) $request->num_courts) {
                throw new \Exception(
                    "Court yang tersedia tidak mencukupi. " .
                    "Dibutuhkan {$request->num_courts} court, " .
                    "tetapi hanya tersedia {$courts->count()}."
                );
            }

            // 3. Buat session
            $session = SessionModel::create([
                'host_user_id' => Auth::id(),
                'sport_id' => $sport->sport_id,
                'venue_id' => $request->venue_id,
                'nama_session' => $request->nama_session,
                'waktu_session' => now()->format('H:i') . ' WIB',
                'datetime' => now(),
                'status_session' => 'Ready for Drawing',
                'jumlah_pemain' => (string) count($request->players),
            ]);

            // 4. Hubungkan court ke session
            $session->courts()->sync($courts->pluck('court_id'));

            // 5. Hubungkan players ke session
            $playerIds = [];

            foreach ($request->players as $playerData) {

                // HOST
                if ($playerData['type'] === 'Host') {

                    // Host harus menggunakan player milik akun yang sedang login
                    $player = Player::where('user_id', Auth::id())->first();

                    if (!$player) {
                        throw new \Exception(
                            'Data player untuk akun host belum ditemukan.'
                        );
                    }
                }

                // MEMBER
                elseif ($playerData['type'] === 'Member') {

                    // Ambil player yang dipilih dari database
                    $player = Player::find($playerData['player_id']);

                    if (!$player) {
                        throw new \Exception(
                            "Player {$playerData['name']} tidak ditemukan di database."
                        );
                    }
                }

                // GUEST
                else {
                    $player = Player::create([
                        'user_id' => null,
                        'nama' => $playerData['name'],
                        'gender' => $playerData['gender'],
                        'level' => $playerData['level'],
                    ]);
                }

                // Hindari player yang sama masuk dua kali
                if (in_array($player->player_id, $playerIds)) {
                    throw new \Exception(
                        "Player {$player->nama} tidak boleh ditambahkan lebih dari satu kali."
                    );
                }

                $playerIds[] = $player->player_id;
            }

            // 6. Hubungkan semua player ke session
            $session->players()->sync($playerIds);

            DB::commit();

            // 7. Redirect ke drawing
            return redirect()
                ->route('games.drawing', ['id' => $session->session_id])
                ->with('success', 'Game berhasil dibuat. Drawing siap dilakukan!');

        } catch (\Exception $e) {

            DB::rollBack();

            return back()
                ->withInput()
                ->withErrors([
                    'error' => 'Gagal membuat game: ' . $e->getMessage(),
                ]);
        }
    }

    public function createSchedule()
    {
        if (Auth::user()->role !== 'host') {
            return redirect()->route('games.index')->with('error', 'Akses ditolak: Fitur pembukaan sesi mabar khusus untuk akun Host Game.');
        }

        $venues = Venue::with('courts')->get();
        $sports = Sport::all();
        return view('games.schedule', compact('venues', 'sports'));
    }

    public function storeSchedule(Request $request)
    {
        if (Auth::user()->role !== 'host') {
            return redirect()->route('games.index')->with('error', 'Akses ditolak: Fitur pembukaan sesi mabar khusus untuk akun Host Game.');
        }

        $request->validate([
            'nama_session'     => 'required|string|max:255',
            'sport_id'         => 'required|integer|exists:tb_sport,sport_id',
            'venue_id'         => 'required|integer|exists:tb_venue,venue_id',
            'court_id'         => 'required|integer|exists:tb_court,court_id',
            'tanggal'          => 'required|date|after_or_equal:today',
            'jam'              => 'required|string',
            'durasi'           => 'required|string',
            'jumlah_pemain'    => 'required|integer|in:4,6,8,12',
            'level_rekomendasi'=> 'nullable|string',
            'deskripsi'        => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $waktuSession = "{$request->jam} WIB ({$request->durasi})";
            $dateTime = "{$request->tanggal} {$request->jam}:00";

            // 1. Create tb_session
            $session = SessionModel::create([
                'host_user_id'     => Auth::id(),
                'sport_id'         => $request->sport_id,
                'venue_id'         => $request->venue_id,
                'nama_session'     => $request->nama_session,
                'waktu_session'    => $waktuSession,
                'datetime'         => $dateTime,
                'status_session'   => 'Open',
                'jumlah_pemain'    => (string) $request->jumlah_pemain,
            ]);

            // 2. Attach court
            $session->courts()->attach($request->court_id);

            // 3. Attach current user/host to session players
            $player = Player::where('user_id', Auth::id())->first();
            if ($player) {
                $session->players()->attach($player->player_id);
            }

            DB::commit();

            return redirect()->route('games.index')->with('success', 'Sesi mabar baru berhasil dibuat dan dipublikasikan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'error' => 'Gagal membuat sesi mabar: ' . $e->getMessage(),
            ]);
        }
    }

    public function show($id)
    {
        $dbSession = SessionModel::with(['sport', 'venue', 'courts', 'players', 'host'])->find((int) $id);

        if (!$dbSession) {
            abort(404, 'Sesi mabar tidak ditemukan di database.');
        }

        $quota = (int) ($dbSession->jumlah_pemain ?? 6);
        $joinedCount = $dbSession->players->count();
        $slotLeft = max(0, $quota - $joinedCount);
        $status = $slotLeft === 0 ? 'Ready for Drawing' : "Open ({$slotLeft} Slot Left)";

        $game = [
            'id' => $dbSession->session_id,
            'title' => $dbSession->nama_session,
            'sport' => $dbSession->sport->nama_sport ?? 'Padel',
            'venue_id' => $dbSession->venue_id,
            'venue_name' => $dbSession->venue->nama_venue ?? 'Arena Olahraga',
            'court_name' => $dbSession->courts->first()->nama_court ?? 'Court 1',
            'date' => $dbSession->datetime ? $dbSession->datetime->format('Y-m-d') : date('Y-m-d'),
            'time' => $dbSession->waktu_session ?? '18:30 WIB',
            'duration' => '2 Jam',
            'quota' => $quota,
            'joined_count' => $joinedCount,
            'status' => $status,
            'level_recommendation' => 'All Level Welcome',
            'match_format' => 'Americano / Double',
            'scoring_system' => $dbSession->scoring_system ?? 'Total of 3',
            'host' => [
                'name' => $dbSession->host->nama ?? 'Host Matcha',
                'role' => 'Host Game',
                'level' => 'Intermediate',
                'phone' => $dbSession->host->no_hp ?? '-',
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
            ],
            'participants' => $dbSession->players->map(function ($p) {
                return [
                    'name' => $p->nama,
                    'gender' => $p->gender ?? 'Male',
                    'age' => $p->usia ?? 25,
                    'level' => $p->level ?? 'Intermediate',
                    'is_member' => !empty($p->user_id),
                    'phone' => $p->no_hp,
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ];
            })->toArray(),
            'drawing' => null,
        ];

        return view('games.show', compact('game'));
    }

    /**
     * Gabung ke sesi mabar dan hubungkan pemain ke tb_session_player di database Supabase.
     */
    public function joinSession($id, Request $request)
    {
        $session = SessionModel::with('players')->findOrFail((int) $id);
        $quota = (int) ($session->jumlah_pemain ?? 6);
        $currentJoined = $session->players->count();

        if ($currentJoined >= $quota) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Slot untuk sesi mabar ini sudah penuh!'
                ], 422);
            }
            return back()->with('error', 'Slot untuk sesi mabar ini sudah penuh!');
        }

        $request->validate([
            'nama' => 'required|string|max:255',
            'gender' => 'nullable|in:Male,Female',
            'level' => 'nullable|string|max:50',
            'no_hp' => 'nullable|string|max:30',
        ]);

        try {
            DB::beginTransaction();

            $player = null;

            if (Auth::check()) {
                $user = Auth::user();
                $player = Player::where('user_id', $user->user_id)->first();
                if (!$player) {
                    $player = Player::create([
                        'user_id' => $user->user_id,
                        'nama' => $request->nama ?: $user->nama,
                        'gender' => $request->gender ?: 'Male',
                        'level' => $request->level ?: 'Intermediate',
                        'rating' => 3.0,
                        'no_hp' => $request->no_hp ?: ($user->no_hp ?? null),
                        'email' => $user->email,
                    ]);
                }
            } else {
                // Cari atau buat player guest berdasarkan nomor HP atau Nama
                if ($request->filled('no_hp')) {
                    $player = Player::where('no_hp', $request->no_hp)->first();
                }
                if (!$player) {
                    $player = Player::create([
                        'user_id' => null,
                        'nama' => $request->nama,
                        'gender' => $request->gender ?: 'Male',
                        'level' => $request->level ?: 'Intermediate',
                        'rating' => 3.0,
                        'no_hp' => $request->no_hp,
                    ]);
                }
            }

            // Periksa apakah sudah bergabung
            if ($session->players()->where('tb_session_player.player_id', $player->player_id)->exists()) {
                DB::rollBack();
                $msg = "Pemain {$player->nama} sudah terdaftar di sesi mabar ini.";
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }
                return back()->with('error', $msg);
            }

            // Hubungkan ke session
            $session->players()->attach($player->player_id);

            // Update status sesi jika sudah penuh
            if ($session->players()->count() >= $quota) {
                $session->update(['status_session' => 'Ready for Drawing']);
            }

            DB::commit();

            $successMsg = "Berhasil bergabung ke sesi mabar: {$session->nama_session}!";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMsg,
                    'joined_count' => $session->players()->count(),
                    'quota' => $quota
                ]);
            }

            return back()->with('success', $successMsg);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Gagal bergabung: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal bergabung: ' . $e->getMessage());
        }
    }

    public function drawing($id, Request $request)
    {
        try {
            $dbSession = SessionModel::with(['sport', 'venue', 'courts', 'players', 'host'])->find((int) $id);
        } catch (\Throwable $e) {
            $dbSession = null;
        }

        if ($dbSession) {
            $quota = (int) ($dbSession->jumlah_pemain ?? 6);
            $joinedCount = $dbSession->players->count();
            $slotLeft = max(0, $quota - $joinedCount);
            $status = $slotLeft === 0 ? 'Ready for Drawing' : "Open ({$slotLeft} Slot Left)";

            $participants = $dbSession->players->map(function ($p) {
                return [
                    'id' => $p->player_id,
                    'name' => $p->nama,
                    'gender' => $p->gender ?? 'Male',
                    'age' => $p->usia ?? 25,
                    'level' => $p->level ?? 'Intermediate',
                    'is_member' => true,
                    'phone' => $p->no_hp,
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ];
            })->toArray();

            // Jika peserta kurang dari 4, lengkapi dengan dummy agar drawing bisa di-render
            if (count($participants) < 4) {
                $dummy = MatchaDummyDataService::getGames()[0]['participants'];
                $participants = array_merge($participants, array_slice($dummy, count($participants)));
            }

            $game = [
                'id' => $dbSession->session_id,
                'title' => $dbSession->nama_session,
                'sport' => $dbSession->sport->nama_sport ?? 'Padel',
                'venue_id' => $dbSession->venue_id,
                'venue_name' => $dbSession->venue->nama_venue ?? 'Arena Olahraga',
                'court_name' => $dbSession->courts->first()->nama_court ?? 'Court 1',
                'date' => $dbSession->datetime ? $dbSession->datetime->format('Y-m-d') : date('Y-m-d'),
                'time' => $dbSession->waktu_session ?? '18:30 WIB',
                'duration' => '2 Jam',
                'quota' => $quota,
                'joined_count' => count($participants),
                'status' => $status,
                'level_recommendation' => 'All Level Welcome',
                'match_format' => $request->query('format', 'Team Americano'),
                'scoring_system' => 'Americano 32 Points',
                'host' => [
                    'name' => $dbSession->host->nama ?? 'Host Matcha',
                    'role' => 'Host Game',
                    'level' => 'Intermediate',
                    'phone' => $dbSession->host->no_hp ?? '-',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ],
                'participants' => $participants,
            ];

            $courtCount = max(1, $dbSession->courts->count());
        } else {
            $games = MatchaDummyDataService::getGames();
            $game = collect($games)->firstWhere('id', (int) $id) ?? $games[0];
            $game['match_format'] = $request->query('format', $game['match_format'] ?? 'Team Americano');
            $participants = $game['participants'] ?? [];
            $courtCount = 2;
        }

        $format = strtolower($game['match_format'] ?? 'americano');

        if (str_contains($format, 'team')) {
            // Eksekusi Team Americano Engine (Fixed Pairs)
            $teamService = new TeamAmericanoService();
            $drawingData = $teamService->generateTeamRounds($participants, $courtCount);
            $rounds = $drawingData['rounds'];
        } else {
            // Eksekusi Americano Engine (Individual Rotating Pairs)
            $americanoService = new AmericanoService();
            $rounds = $americanoService->generateRounds($participants, $courtCount);
            $drawingData = [
                'format' => 'Americano',
                'total_teams' => count($participants),
                'total_rounds' => count($rounds),
                'total_matches' => array_sum(array_map(fn($r) => count($r['matches'] ?? []), $rounds)),
                'rounds' => $rounds,
            ];
        }

        return view('games.drawing', compact('game', 'drawingData', 'rounds'));
    }
}

