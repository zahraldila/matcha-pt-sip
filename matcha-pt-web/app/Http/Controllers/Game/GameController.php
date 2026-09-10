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

        // Fetch real sessions from Supabase
        $dbSessions = SessionModel::with(['sport', 'venue', 'courts', 'players', 'host'])->latest('created_at')->get();
        
        $formattedDbGames = $dbSessions->map(function ($s) {
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
                'joined_count' => $joinedCount > 0 ? $joinedCount : 1,
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
                        'is_member' => true,
                        'phone' => $p->no_hp,
                        'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                    ];
                })->toArray(),
                'drawing' => null,
            ];
        })->toArray();

        // Merge with dummy games so there's rich data, prioritizing real DB sessions
        $dummyGames = MatchaDummyDataService::getGames();
        $games = array_merge($formattedDbGames, $dummyGames);

        if ($selectedSport !== 'all') {
            $games = array_filter($games, fn($g) => strtolower($g['sport']) === strtolower($selectedSport));
        }

        return view('games.index', compact('games', 'selectedSport'));
    }

    public function create()
    {
        if (Auth::user()->role !== 'host') {
            return redirect()->route('games.index')->with('error', 'Akses ditolak: Fitur ini khusus untuk akun Host Game.');
        }

        $venues = Venue::with('courts.sport')->get();

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
                'scoring_system' => $request->scoring_system ?? 'Total of 3',
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

            // 7. Simpan drawing awal ke tb_drawing dengan match_format_id yang sesuai
            $formatId = str_contains(strtolower($request->format), 'team') ? 4 : 1;
            \App\Models\Drawing::create([
                'session_id' => $session->session_id,
                'match_format_id' => $formatId,
                'tanggal_drawing' => now()->toDateString(),
                'jam_drawing' => now()->format('H:i:s'),
            ]);

            DB::commit();

            // 8. Redirect ke drawing (support JSON untuk form wizard AJAX)
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Game berhasil dibuat. Drawing siap dilakukan!',
                    'redirect' => route('games.drawing', [
                        'id' => $session->session_id,
                        'format' => $request->format,
                    ]),
                ]);
            }

            return redirect()
                ->route('games.drawing', [
                    'id' => $session->session_id,
                    'format' => $request->format,
                ])
                ->with('success', 'Game berhasil dibuat. Drawing siap dilakukan!');

        } catch (\Exception $e) {

            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat game: ' . $e->getMessage(),
                ], 422);
            }

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

        $venues = Venue::with('courts.sport')->get();
        $sports = Sport::all();
        return view('games.schedule', compact('venues', 'sports'));
    }

    public function storeSchedule(Request $request)
    {
        if (Auth::user()->role !== 'host') {
            return redirect()->route('games.index')->with('error', 'Akses ditolak: Fitur pembukaan sesi mabar khusus untuk akun Host Game.');
        }

        $request->validate([
            'nama_session' => 'required|string|max:255',
            'sport_id' => 'required|integer',
            'venue_id' => 'required|integer',
            'court_id' => 'required|integer',
            'tanggal' => 'required|date',
            'jam' => 'required|string',
            'durasi' => 'required|string',
            'jumlah_pemain' => 'required|integer|min:4|max:20',
            'level_rekomendasi' => 'nullable|string',
            'deskripsi' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $waktuSession = "{$request->jam} WIB ({$request->durasi})";
            $dateTime = "{$request->tanggal} {$request->jam}:00";

            // 1. Create tb_session
            $session = SessionModel::create([
                'host_user_id' => Auth::id() ?? 1,
                'sport_id' => $request->sport_id,
                'venue_id' => $request->venue_id,
                'nama_session' => $request->nama_session,
                'waktu_session' => $waktuSession,
                'datetime' => $dateTime,
                'status_session' => 'Open',
                'jumlah_pemain' => (string) $request->jumlah_pemain,
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

        if ($dbSession) {
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
                'joined_count' => $joinedCount > 0 ? $joinedCount : 1,
                'status' => $status,
                'level_recommendation' => 'All Level Welcome',
                'match_format' => 'Americano / Double',
                'scoring_system' => 'Tennis System (15, 30, 40, Game)',
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
                        'is_member' => true,
                        'phone' => $p->no_hp,
                        'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                    ];
                })->toArray(),
                'drawing' => null,
            ];
        } else {
            $games = MatchaDummyDataService::getGames();
            $game = collect($games)->firstWhere('id', (int) $id) ?? $games[0];
        }

        return view('games.show', compact('game'));
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

            $formatQuery = $request->query('format');
            if (!$formatQuery && $dbSession) {
                $dbDrawing = \App\Models\Drawing::where('session_id', $dbSession->session_id)->with('matchFormat')->first();
                if ($dbDrawing && $dbDrawing->matchFormat) {
                    $formatQuery = $dbDrawing->matchFormat->nama_format;
                }
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
                    'match_format' => $formatQuery ?: 'Americano',
                    'scoring_system' => $dbSession->scoring_system ?? 'Total of 3',
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
                $game['match_format'] = $request->query('format', $game['match_format'] ?? 'Americano');
                $participants = $game['participants'] ?? [];
                $courtCount = 2;
            }

            // Cek apakah pertandingan sudah dimulai / scoring live sudah berjalan
            $isLocked = false;
            if (\Illuminate\Support\Facades\Cache::get("drawing.locked_{$game['id']}", false)) {
                $isLocked = true;
            }
            if (isset($dbSession->status_session) && in_array(strtolower($dbSession->status_session), ['in_progress', 'completed', 'finished'])) {
                $isLocked = true;
            }
            $cacheKey = "scoring.game_{$game['id']}";
            $savedScores = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
            if (!empty($savedScores) && is_array($savedScores)) {
                foreach ($savedScores as $k => $v) {
                    if ($k !== '_meta' && is_array($v) && (
                        ($v['status'] ?? '') === 'in_progress' || 
                        ($v['status'] ?? '') === 'completed' ||
                        ($v['games_a'] ?? 0) > 0 || ($v['games_b'] ?? 0) > 0 ||
                        ($v['score_a'] ?? 0) > 0 || ($v['score_b'] ?? 0) > 0 ||
                        ($v['sets_a'] ?? 0) > 0 || ($v['sets_b'] ?? 0) > 0
                    )) {
                        $isLocked = true;
                        break;
                    }
                }
            }

            // Cegah pengacakan ulang jika match sudah terkunci
            if ($isLocked && ($request->has('shuffle') || $request->has('seed'))) {
                if ($request->wantsJson() || $request->ajax() || $request->query('json')) {
                    return response()->json([
                        'success' => false,
                        'isLocked' => true,
                        'message' => 'Pertandingan sudah berjalan! Jadwal tim terkunci dan tidak dapat diacak ulang.',
                    ], 422);
                }
            }

            $format = strtolower($game['match_format'] ?? 'americano');
            $isTeam = str_contains($format, 'team') && count($participants) >= 4 && count($participants) % 2 === 0;

            // Cek apakah sudah ada jadwal tersimpan di cache
            $scheduleCacheKey = "drawing.schedule_{$game['id']}";
            $savedSchedule = \Illuminate\Support\Facades\Cache::get($scheduleCacheKey);

            $needsGeneration = false;
            if (!$savedSchedule || empty($savedSchedule['rounds'])) {
                $needsGeneration = true;
            } elseif (!$isLocked && ($request->has('shuffle') || $request->has('seed'))) {
                $needsGeneration = true;
            }

            if ($needsGeneration) {
                $seed = (int) $request->query('seed', rand(1000, 999999));
                try {
                    if ($isTeam) {
                        // Team Americano Engine (Fixed Pairs). Pengacakan hanya mengacak urutan tim, bukan anggota tim!
                        $teamService = new TeamAmericanoService();
                        $drawingData = $teamService->generateTeamRounds($participants, $courtCount, $seed);
                        $rounds = $drawingData['rounds'] ?? [];
                    } else {
                        // Americano Engine (Individual Rotating Pairs)
                        mt_srand($seed);
                        $pKeys = array_keys($participants);
                        shuffle($pKeys);
                        $shuffled = [];
                        foreach ($pKeys as $k) {
                            $shuffled[] = $participants[$k];
                        }
                        $americanoService = new AmericanoService();
                        $rounds = $americanoService->generateRounds($shuffled, $courtCount);
                        $drawingData = [
                            'format' => 'Americano',
                            'total_teams' => count($participants),
                            'total_rounds' => count($rounds),
                            'total_matches' => array_sum(array_map(fn($r) => count($r['matches'] ?? []), $rounds)),
                            'rounds' => $rounds,
                        ];
                    }
                } catch (\Throwable $e) {
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

                \Illuminate\Support\Facades\Cache::put($scheduleCacheKey, [
                    'drawingData' => $drawingData,
                    'rounds' => $rounds,
                ], now()->addHours(12));
            } else {
                $drawingData = $savedSchedule['drawingData'] ?? [];
                $rounds = $savedSchedule['rounds'] ?? [];
            }

            $participantsMap = [];
            foreach ($participants as $p) {
                $pName = is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (is_object($p) ? ($p->nama ?? $p->name ?? '') : (string)$p);
                $pGender = is_array($p) ? ($p['gender'] ?? 'Male') : (is_object($p) ? ($p->gender ?? 'Male') : 'Male');
                if ($pName) {
                    $participantsMap[$pName] = [
                        'name' => $pName,
                        'gender' => $pGender,
                    ];
                }
            }

            // Jika request via AJAX / Fetch JSON
            if ($request->wantsJson() || $request->ajax() || $request->query('json')) {
                return response()->json([
                    'success' => true,
                    'isLocked' => $isLocked,
                    'drawingData' => $drawingData,
                    'rounds' => $rounds,
                    'participantsMap' => $participantsMap,
                ]);
            }

            return view('games.drawing', compact('game', 'drawingData', 'rounds', 'participantsMap', 'isLocked'));
        }

        public function lockDrawing($id, Request $request)
        {
            $format = $request->input('format', 'Americano');

            // Kunci status drawing di cache
            \Illuminate\Support\Facades\Cache::put("drawing.locked_{$id}", true, now()->addHours(12));

            try {
                $session = SessionModel::find((int) $id);
                if ($session) {
                    $session->status_session = 'In Progress';
                    $session->save();

                    $formatId = str_contains(strtolower($format), 'team') ? 4 : 1;
                    $drawing = \App\Models\Drawing::firstOrCreate(
                        ['session_id' => $session->session_id],
                        [
                            'match_format_id' => $formatId,
                            'tanggal_drawing' => now()->toDateString(),
                            'jam_drawing'     => now()->format('H:i:s'),
                        ]
                    );
                    if ($drawing && $drawing->match_format_id !== $formatId) {
                        $drawing->match_format_id = $formatId;
                        $drawing->save();
                    }
                }
            } catch (\Throwable $e) {
                // Ignore DB error for dummy sessions
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'isLocked' => true,
                    'message' => 'Jadwal pertandingan berhasil dikunci. Membuka Live Scoring...',
                    'redirect' => route('scoring.live', ['id' => $id, 'format' => $format]),
                ]);
            }

            return redirect()->route('scoring.live', ['id' => $id, 'format' => $format])
                ->with('success', 'Jadwal pertandingan berhasil dikunci!');
        }
}

