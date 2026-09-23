<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\MatchParticipant;
use App\Models\Player;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\Venue;
use App\Services\Drawing\AmericanoService;
use App\Services\Drawing\TeamAmericanoService;
use App\Services\MatchaDummyDataService;
use App\Services\Scoring\ScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GameController extends Controller
{
    public static function resolveSessionDisplayTime(SessionModel $session): string
    {
        $raw = trim((string) ($session->waktu_session ?? ''));
        if ($raw === '') {
            return '18:30';
        }

        $normalized = preg_replace('/\s*\([^)]*\)\s*$/', '', $raw);
        $normalized = preg_replace('/\s*WIB\s*$/i', '', $normalized ?? $raw);

        return trim($normalized !== null && $normalized !== '' ? $normalized : $raw);
    }

    public static function resolveSessionStatus(SessionModel $session, int $slotLeft = 0): string
    {
        $status = strtolower(trim((string) ($session->status_session ?? '')));

        if (in_array($status, ['finished', 'completed'], true)) {
            return 'Selesai Mabar';
        }

        // Jika kuota pemain masih tersisa, sesi harus berstatus Open agar pemain lain dapat mendaftar
        if ($slotLeft > 0) {
            return "Open ({$slotLeft} Slot Left)";
        }

        if (in_array($status, ['in_progress', 'in progress'], true)) {
            return 'Sedang Berlangsung';
        }

        return 'Ready for Drawing';
    }

    public static function resolveSessionDuration(SessionModel $session): string
    {
        $status = strtolower(trim((string) ($session->status_session ?? '')));
        if (! in_array($status, ['finished', 'completed'], true)) {
            return '-';
        }

        $drawingIds = Drawing::where('session_id', $session->session_id)
            ->pluck('drawing_id')
            ->all();

        if (! empty($drawingIds)) {
            $matches = GameMatch::whereIn('drawing_id', $drawingIds)
                ->whereIn('status_match', ['Completed', 'Finished'])
                ->get();

            $startedAt = null;
            $finishedAt = null;

            foreach ($matches as $match) {
                if (empty($match->waktu_mulai) || empty($match->waktu_selesai)) {
                    continue;
                }

                $matchStartedAt = Carbon::parse($match->waktu_mulai);
                $matchFinishedAt = Carbon::parse($match->waktu_selesai);

                if ($matchFinishedAt->lessThanOrEqualTo($matchStartedAt)) {
                    continue;
                }

                if ($startedAt === null || $matchStartedAt->lessThan($startedAt)) {
                    $startedAt = $matchStartedAt;
                }

                if ($finishedAt === null || $matchFinishedAt->greaterThan($finishedAt)) {
                    $finishedAt = $matchFinishedAt;
                }
            }

            if ($startedAt && $finishedAt) {
                $totalMinutes = (int) $startedAt->diffInMinutes($finishedAt, false);
                if ($totalMinutes > 0) {
                    return self::formatDurationMinutes($totalMinutes);
                }
            }
        }

        $scheduledValue = trim((string) ($session->waktu_session ?? ''));
        if ($scheduledValue !== '') {
            if (preg_match('/\(([^)]+)\)/', $scheduledValue, $match)) {
                $parsed = trim($match[1]);
                if ($parsed !== '') {
                    return $parsed;
                }
            }
        }

        return '-';
    }

    private static function formatDurationMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return $hours.' Jam '.$remainingMinutes.' Menit';
        }

        if ($hours > 0) {
            return $hours.' Jam';
        }

        return $remainingMinutes.' Menit';
    }

    public function index(Request $request)
    {
        $selectedSport = $request->query('sport', 'all');
        $activeTab = $request->query('tab', 'all'); // 'all', 'joined', 'hosted', 'venue'
        $search = trim($request->query('q', $request->query('search', '')));

        $user = Auth::user();
        $userId = $user ? $user->user_id : null;
        $userEmail = $user ? strtolower(trim($user->email ?? '')) : null;
        $userName = $user ? strtolower(trim($user->nama ?? '')) : null;

        $ownedVenueIds = [];
        if ($user) {
            $ownedVenueIds = Venue::where('owner_user_id', $userId)->pluck('venue_id')->toArray();
        }

        $startOfToday = now()->startOfDay()->toDateTimeString();

        // Fetch 100% real sessions from Supabase database (diurutkan dari terdekat: hari ini, besok, lusa, dst)
        $sessionQuery = SessionModel::with(['sport', 'venue', 'courts', 'players.user', 'host'])
            ->orderByRaw("CASE WHEN status_session NOT IN ('Finished', 'Completed') AND datetime >= ? THEN 0 ELSE 1 END", [$startOfToday])
            ->orderByRaw("CASE WHEN status_session NOT IN ('Finished', 'Completed') AND datetime >= ? THEN datetime END ASC", [$startOfToday])
            ->orderBy('datetime', 'desc');

        if ($selectedSport !== 'all') {
            $sessionQuery->whereHas('sport', function ($q) use ($selectedSport) {
                $q->whereRaw('LOWER(nama_sport) = ?', [strtolower($selectedSport)]);
            });
        }

        $allDbSessions = $sessionQuery->get();

        $allMappedGames = $allDbSessions->map(function ($s) use ($userId, $userEmail, $userName, $ownedVenueIds) {
            $joinedCount = $s->players->count();
            $quota = (int) ($s->jumlah_pemain ?? 6);
            $slotLeft = max(0, $quota - $joinedCount);
            $isFinished = in_array(strtolower(trim((string) $s->status_session)), ['finished', 'completed'], true);
            $status = self::resolveSessionStatus($s, $slotLeft);

            // Check if hosted by logged-in user
            $isHostedByMe = false;
            if ($userId && $s->host_user_id == $userId) {
                $isHostedByMe = true;
            } elseif ($userName && $s->host && strtolower(trim($s->host->nama ?? '')) === $userName) {
                $isHostedByMe = true;
            }

            // Check if joined by logged-in user
            $isJoinedByMe = false;
            if ($userId || $userEmail || $userName) {
                $isJoinedByMe = $s->players->contains(function ($p) use ($userId, $userEmail, $userName) {
                    if ($userId && $p->user_id && $p->user_id == $userId) {
                        return true;
                    }
                    if ($userEmail && $p->email && strtolower(trim($p->email)) === $userEmail) {
                        return true;
                    }
                    if ($userName && $p->nama && strtolower(trim($p->nama)) === $userName) {
                        return true;
                    }

                    return false;
                });
            }

            // Check if match session takes place in a venue owned by this user
            $isAtMyVenue = in_array($s->venue_id, $ownedVenueIds);

            $formatString = 'Americano';
            $dbDrawing = Drawing::where('session_id', $s->session_id)->with('matchFormat')->first();
            if ($dbDrawing && $dbDrawing->matchFormat) {
                $formatString = $dbDrawing->matchFormat->nama_format;
            }
            if (! str_contains(strtolower($formatString), 'team')) {
                $jenis = $s->jenis_permainan ?? 'Double';
                $formatString .= ' / '.$jenis;
            }

            return [
                'id' => $s->session_id,
                'title' => $s->nama_session,
                'sport' => $s->sport->nama_sport ?? 'Padel',
                'venue_id' => $s->venue_id,
                'venue_name' => $s->venue->nama_venue ?? 'Arena Olahraga',
                'court_name' => $s->courts->first()->nama_court ?? 'Court 1',
                'date' => $s->datetime ? $s->datetime->format('Y-m-d') : date('Y-m-d'),
                'time' => self::resolveSessionDisplayTime($s),
                'duration' => self::resolveSessionDuration($s),
                'quota' => $quota,
                'joined_count' => $joinedCount,
                'status' => $status,
                'is_finished' => $isFinished,
                'level_recommendation' => 'All Level Welcome',
                'match_format' => $formatString,
                'scoring_system' => $s->scoring_system ?? 'Total of 3',
                'is_hosted_by_me' => $isHostedByMe,
                'is_joined_by_me' => $isJoinedByMe,
                'is_at_my_venue' => $isAtMyVenue,
                'host' => [
                    'name' => $s->host->nama ?? 'Host Matcha',
                    'role' => 'Host Game',
                    'level' => 'Intermediate',
                    'phone' => $s->host->no_hp ?? '-',
                    'avatar' => $s->host->foto ?? null,
                ],
                'participants' => $s->players->map(function ($p) {
                    return [
                        'name' => $p->nama,
                        'gender' => $p->gender ?? 'Male',
                        'age' => $p->usia,
                        'level' => $p->level ?? 'Intermediate',
                        'is_member' => ! empty($p->user_id),
                        'phone' => $p->no_hp,
                        'avatar' => $p->user->foto ?? ($p->foto ?? null),
                    ];
                })->toArray(),
                'drawing' => null,
            ];
        });

        // Search filtering across title, venue, court, and host name
        if ($search !== '') {
            $searchLower = strtolower($search);
            $allMappedGames = $allMappedGames->filter(function ($g) use ($searchLower) {
                $inTitle = str_contains(strtolower($g['title'] ?? ''), $searchLower);
                $inVenue = str_contains(strtolower($g['venue_name'] ?? ''), $searchLower);
                $inCourt = str_contains(strtolower($g['court_name'] ?? ''), $searchLower);
                $inHost = str_contains(strtolower($g['host']['name'] ?? ''), $searchLower);
                $inSport = str_contains(strtolower($g['sport'] ?? ''), $searchLower);

                return $inTitle || $inVenue || $inCourt || $inHost || $inSport;
            });
        }

        // Tab counts (reflecting search results if search is active)
        $countAll = $allMappedGames->count();
        $countJoined = $allMappedGames->where('is_joined_by_me', true)->count();
        $countHosted = $allMappedGames->where('is_hosted_by_me', true)->count();
        $countVenue = $allMappedGames->where('is_at_my_venue', true)->count();

        // Apply active tab filter
        if ($activeTab === 'joined') {
            $filteredGames = $allMappedGames->where('is_joined_by_me', true)->values();
        } elseif ($activeTab === 'hosted') {
            $filteredGames = $allMappedGames->where('is_hosted_by_me', true)->values();
        } elseif ($activeTab === 'venue') {
            $filteredGames = $allMappedGames->where('is_at_my_venue', true)->values();
        } else {
            $filteredGames = $allMappedGames->values();
        }

        // Pagination: 6 items per page
        $perPage = 6;
        $currentPage = Paginator::resolveCurrentPage('page') ?: 1;
        $totalItems = $filteredGames->count();
        $currentItems = $filteredGames->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $games = new LengthAwarePaginator(
            $currentItems,
            $totalItems,
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
        );
        $games->withQueryString();

        return view('games.index', compact(
            'games',
            'search',
            'selectedSport',
            'activeTab',
            'countAll',
            'countJoined',
            'countHosted',
            'countVenue',
            'ownedVenueIds'
        ));
    }

    public function create()
    {
        // PENYESUAIAN: Cek flag is_host
        if (! Auth::user()->is_host) {
            return redirect()->route('player.profile', ['notice' => 'host_required'])
                ->with('info', 'Silakan aktifkan Mode Host pada kartu di bawah ini untuk mulai membuat sesi mabar.');
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
        // PENYESUAIAN: Cek flag is_host
        if (! Auth::user()->is_host) {
            return redirect()->route('player.profile')
                ->with('error', 'Akses ditolak: Silakan aktifkan Mode Host di halaman profil Anda terlebih dahulu.');
        }

        // Tentukan mode Single/Double untuk Americano
        $jenisPermainan = $request->input('jenis_permainan', 'Double');
        $isSingleMode = strtolower($jenisPermainan) === 'single'
                          && str_contains(strtolower($request->input('format', '')), 'americano');
        $minPlayers = $isSingleMode ? 2 : 4;

        $request->validate([
            'nama_session' => 'required|string|max:255',
            'sport' => 'required|in:Padel,Tennis',
            'format' => 'required|string',
            'num_courts' => 'required|integer|min:1|max:4',
            'venue_id' => 'required|integer|exists:tb_venue,venue_id',
            'scoring_system' => 'required|string|max:100',
            'rank_by' => 'required|in:point,win',
            'jenis_permainan' => 'nullable|in:Single,Double',

            'players' => "required|array|min:{$minPlayers}",

            'players.*.player_id' => 'nullable|integer|exists:tb_player,player_id',
            'players.*.name' => 'required|string|max:255',
            'players.*.gender' => 'required|in:Male,Female',
            'players.*.level' => 'required|string|max:50',
            'players.*.type' => 'required|in:Host,Member,Guest',
        ]);

        // Validasi format Team Americano: Wajib Genap (2 pemain per tim)
        if (str_contains(strtolower($request->format), 'team') && count($request->players) % 2 !== 0) {
            $msg = 'Jumlah pemain Team Americano harus genap (4, 6, 8, dst) karena setiap tim terdiri dari 2 orang pasangan tetap.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->withInput()->withErrors(['players' => $msg]);
        }

        // Validasi format First to X (Single & Double): Jumlah pemain harus tepat sesuai kapasitas court
        $scoringSystemInput = $request->input('scoring_system', '');
        $isFirstToSystem = str_starts_with(strtolower(trim($scoringSystemInput)), 'first to');
        $numCourts = (int) $request->input('num_courts', 1);
        $playerCount = count($request->input('players', []));

        if ($isFirstToSystem) {
            $requiredPlayers = $isSingleMode ? ($numCourts * 2) : ($numCourts * 4);
            $modeLabel = $isSingleMode ? 'Single (1 vs 1)' : 'Double (2 vs 2)';

            if ($playerCount !== $requiredPlayers) {
                $msg = "Untuk format {$scoringSystemInput} ({$modeLabel}) dengan {$numCourts} court, jumlah pemain harus tepat {$requiredPlayers} orang (tidak boleh kurang atau lebih).";
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }

                return back()->withInput()->withErrors(['players' => $msg]);
            }
        }

        try {
            DB::beginTransaction();

            // 1. Cari sport berdasarkan nama
            $sport = Sport::where('nama_sport', $request->sport)->first();

            if (! $sport) {
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
                    'Court yang tersedia tidak mencukupi. '.
                    "Dibutuhkan {$request->num_courts} court, ".
                    "tetapi hanya tersedia {$courts->count()}."
                );
            }

            $scheduledDate = $request->input('tanggal') ?: now()->toDateString();
            $scheduledTime = $request->input('jam') ?: now()->format('H:i');

            // 3. Buat session
            $session = SessionModel::create([
                'host_user_id' => Auth::id(),
                'sport_id' => $sport->sport_id,
                'venue_id' => $request->venue_id,
                'nama_session' => $request->nama_session,
                'scoring_system' => $request->scoring_system ?? 'Total of 3',
                'waktu_session' => $scheduledTime.' WIB',
                'datetime' => $scheduledDate.' '.$scheduledTime.':00',
                'status_session' => 'Ready for Drawing',
                'jumlah_pemain' => (string) count($request->players),
                'jenis_permainan' => $jenisPermainan,
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

                    if (! $player) {
                        throw new \Exception(
                            'Data player untuk akun host belum ditemukan.'
                        );
                    }
                }

                // MEMBER
                elseif ($playerData['type'] === 'Member') {

                    // Ambil player yang dipilih dari database
                    $player = Player::find($playerData['player_id']);

                    if (! $player) {
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
            Drawing::create([
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
                    'message' => 'Gagal membuat game: '.$e->getMessage(),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'error' => 'Gagal membuat game: '.$e->getMessage(),
                ]);
        }
    }

    public function createSchedule()
    {
        // PENYESUAIAN: Cek flag is_host
        if (! Auth::user()->is_host) {
            return redirect()->route('player.profile', ['notice' => 'host_required'])
                ->with('info', 'Silakan aktifkan Mode Host pada kartu di bawah ini untuk mulai membuat sesi mabar.');
        }

        $venues = Venue::with(['courts.sport', 'availabilities'])->get();
        $sports = Sport::all();

        return view('games.schedule', compact('venues', 'sports'));
    }

    public function storeSchedule(Request $request)
    {
        // PENYESUAIAN: Cek flag is_host
        if (! Auth::user()->is_host) {
            return redirect()->route('player.profile')
                ->with('error', 'Akses ditolak: Silakan aktifkan Mode Host di halaman profil Anda terlebih dahulu.');
        }

        $request->validate([
            'nama_session' => 'required|string|max:255',
            'sport_id' => 'required|integer|exists:tb_sport,sport_id',
            'format' => 'nullable|in:Americano,Team Americano',
            'scoring_system' => 'nullable|string|max:50',
            'venue_id' => 'required|integer|exists:tb_venue,venue_id',
            'court_id' => 'required|integer|exists:tb_court,court_id',
            'tanggal' => 'required|date|after_or_equal:today',
            'jam' => 'required|string',
            'durasi' => 'required|string',
            'jumlah_pemain' => 'required|integer|min:2',
            'jenis_permainan' => 'nullable|in:Single,Double',
            'level_rekomendasi' => 'nullable|string',
            'deskripsi' => 'nullable|string',
        ]);

        $format = $request->input('format', 'Americano');
        $isTeamAmericano = str_contains(strtolower($format), 'team');
        $scoringSystem = $request->input('scoring_system', 'Total of 3');
        $isFirstTo = str_starts_with(strtolower(trim($scoringSystem)), 'first to');
        $jenisPermainan = $isTeamAmericano ? 'Double' : $request->input('jenis_permainan', 'Double');
        $isSingle = strtolower($jenisPermainan) === 'single';
        $jumlahPemain = (int) $request->input('jumlah_pemain');

        // Constraint Validations:
        // 1. Team Americano: Wajib Genap dan minimal 4
        if ($isTeamAmericano && ($jumlahPemain % 2 !== 0 || $jumlahPemain < 4)) {
            return back()->withInput()->withErrors([
                'jumlah_pemain' => 'Kuota pemain Team Americano harus berjumlah genap (minimal 4 pemain) karena setiap tim terdiri dari 2 orang pasangan tetap.',
            ]);
        }

        // 2. First to X constraints (1 court)
        if ($isFirstTo) {
            if ($isSingle && $jumlahPemain !== 2) {
                return back()->withInput()->withErrors([
                    'jumlah_pemain' => "Untuk format {$scoringSystem} (Single 1v1), kuota pemain harus tepat 2 orang (tidak boleh kurang atau lebih).",
                ]);
            }
            if (! $isSingle && $jumlahPemain !== 4) {
                return back()->withInput()->withErrors([
                    'jumlah_pemain' => "Untuk format {$scoringSystem} (Double 2v2), kuota pemain harus tepat 4 orang (tidak boleh kurang atau lebih).",
                ]);
            }
        }

        // 3. Validasi Jam Operasional & Hari Buka Venue
        $venue = Venue::find($request->venue_id);
        if ($venue) {
            if (! empty($venue->jam_operasional) && preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $venue->jam_operasional, $matches)) {
                $pad = function ($t) {
                    $parts = explode(':', trim($t));

                    return sprintf('%02d:%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0));
                };
                $jamBuka = $pad($matches[1]);
                $jamTutup = $pad($matches[2]);
                $jamInput = $pad($request->jam);

                if ($jamBuka <= $jamTutup) {
                    if ($jamInput < $jamBuka || $jamInput > $jamTutup) {
                        return back()->withInput()->withErrors([
                            'jam' => "Jam mulai ({$jamInput}) harus berada dalam jam operasional venue ({$jamBuka} - {$jamTutup} WIB).",
                        ]);
                    }
                }
            }

            if (! empty($venue->hari_buka)) {
                $hariBuka = strtolower($venue->hari_buka);
                $dayOfWeek = Carbon::parse($request->tanggal)->dayOfWeek;

                if (str_contains($hariBuka, 'senin - jumat') && ($dayOfWeek === 0 || $dayOfWeek === 6)) {
                    return back()->withInput()->withErrors([
                        'tanggal' => "Venue {$venue->nama_venue} hanya beroperasi pada hari kerja ({$venue->hari_buka}). Tanggal yang Anda pilih adalah akhir pekan.",
                    ]);
                }

                if (str_contains($hariBuka, 'senin - sabtu') && $dayOfWeek === 0) {
                    return back()->withInput()->withErrors([
                        'tanggal' => "Venue {$venue->nama_venue} libur pada hari Minggu ({$venue->hari_buka}).",
                    ]);
                }

                if (str_contains($hariBuka, 'sabtu & minggu') && ($dayOfWeek >= 1 && $dayOfWeek <= 5)) {
                    return back()->withInput()->withErrors([
                        'tanggal' => "Venue {$venue->nama_venue} hanya beroperasi pada akhir pekan ({$venue->hari_buka}).",
                    ]);
                }
            }
        }

        try {
            DB::beginTransaction();

            $waktuSession = "{$request->jam} WIB ({$request->durasi})";
            $dateTime = "{$request->tanggal} {$request->jam}:00";

            $session = SessionModel::create([
                'host_user_id' => Auth::id(),
                'sport_id' => $request->sport_id,
                'venue_id' => $request->venue_id,
                'nama_session' => $request->nama_session,
                'scoring_system' => $scoringSystem,
                'waktu_session' => $waktuSession,
                'datetime' => $dateTime,
                'status_session' => 'Open',
                'jumlah_pemain' => (string) $jumlahPemain,
                'jenis_permainan' => $jenisPermainan,
            ]);

            // 2. Attach court
            $session->courts()->attach($request->court_id);

            // 3. Attach host ke session players jika opsi (+ Add Yourself) dicentang
            if ($request->boolean('include_host_as_player')) {
                $player = Player::where('user_id', Auth::id())
                    ->orWhere('email', Auth::user()->email)
                    ->first();

                if (! $player) {
                    $player = Player::create([
                        'user_id' => Auth::id(),
                        'nama' => Auth::user()->nama,
                        'gender' => 'Male',
                        'level' => 'Intermediate',
                        'rating' => 3.0,
                        'no_hp' => Auth::user()->no_hp ?? null,
                        'email' => Auth::user()->email,
                    ]);
                }

                $session->players()->attach($player->player_id);
            }

            // 4. Inisialisasi tb_drawing dengan match_format_id yang sesuai (1 = Americano, 4 = Team Americano)
            $formatId = $isTeamAmericano ? 4 : 1;
            Drawing::create([
                'session_id' => $session->session_id,
                'match_format_id' => $formatId,
                'tanggal_drawing' => now()->toDateString(),
                'jam_drawing' => now()->format('H:i:s'),
            ]);

            DB::commit();

            return redirect()->route('games.index')->with('toast', 'Sesi mabar baru berhasil dibuat dan dipublikasikan! 🎉');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->withErrors([
                'error' => 'Gagal membuat sesi mabar: '.$e->getMessage(),
            ]);
        }
    }

    public function show($id)
    {
        $dbSession = SessionModel::with(['sport', 'venue', 'courts', 'players.user', 'host'])->findOrFail((int) $id);

        $quota = (int) ($dbSession->jumlah_pemain ?? 6);
        $joinedCount = $dbSession->players->count();
        $slotLeft = max(0, $quota - $joinedCount);
        $status = self::resolveSessionStatus($dbSession, $slotLeft);

        $formatString = 'Americano';
        $dbDrawing = Drawing::where('session_id', $dbSession->session_id)->with('matchFormat')->first();
        if ($dbDrawing && $dbDrawing->matchFormat) {
            $formatString = $dbDrawing->matchFormat->nama_format;
        }
        if (! str_contains(strtolower($formatString), 'team')) {
            $jenis = $dbSession->jenis_permainan ?? 'Double';
            $formatString .= ' / '.$jenis;
        }

        $isFinished = ($dbSession->status_session === 'Finished') || str_contains(strtolower($status), 'selesai');

        $isHost = false;
        $isJoinedByMe = false;
        if (Auth::check()) {
            $user = Auth::user();
            $isHost = ((int) $user->user_id === (int) $dbSession->host_user_id || $user->role === 'admin');
            $userId = $user->user_id;
            $userEmail = strtolower(trim($user->email ?? ''));
            $userName = strtolower(trim($user->nama ?? ''));

            $isJoinedByMe = $dbSession->players->contains(function ($p) use ($userId, $userEmail, $userName) {
                if ($userId && $p->user_id && $p->user_id == $userId) {
                    return true;
                }
                if ($userEmail && $p->email && strtolower(trim($p->email)) === $userEmail) {
                    return true;
                }
                if ($userName && $p->nama && strtolower(trim($p->nama)) === $userName) {
                    return true;
                }

                return false;
            });
        }

        $hasDrawingStarted = in_array(strtolower($dbSession->status_session ?? ''), ['in progress', 'in_progress', 'live', 'playing', 'finished', 'completed', 'selesai']);
        if (! $hasDrawingStarted && Schema::hasTable('tb_match') && Schema::hasTable('tb_drawing')) {
            try {
                $hasDrawingStarted = GameMatch::whereHas('drawing', function ($q) use ($dbSession) {
                    $q->where('session_id', $dbSession->session_id);
                })->exists();
            } catch (\Throwable $e) {
                $hasDrawingStarted = false;
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
            'time' => self::resolveSessionDisplayTime($dbSession),
            'duration' => self::resolveSessionDuration($dbSession),
            'quota' => $quota,
            'joined_count' => $joinedCount,
            'status' => $status,
            'is_finished' => $isFinished,
            'is_host' => $isHost,
            'has_drawing_started' => $hasDrawingStarted,
            'level_recommendation' => 'All Level Welcome',
            'match_format' => $formatString,
            'scoring_system' => $dbSession->scoring_system ?? 'Total of 3',
            'host' => [
                'name' => $dbSession->host->nama ?? 'Host Matcha',
                'role' => 'Host Game',
                'level' => 'Intermediate',
                'phone' => $dbSession->host->no_hp ?? '-',
                'avatar' => $dbSession->host->foto ?? null,
            ],
            'participants' => $dbSession->players->map(function ($p) {
                return [
                    'name' => $p->nama,
                    'gender' => $p->gender ?? 'Male',
                    'age' => $p->usia,
                    'level' => $p->level ?? 'Intermediate',
                    'is_member' => ! empty($p->user_id),
                    'phone' => $p->no_hp,
                    'avatar' => $p->user->foto ?? ($p->foto ?? null),
                ];
            })->toArray(),
            'drawing' => null,
            'is_joined_by_me' => $isJoinedByMe,
        ];

        return view('games.show', compact('game', 'isFinished', 'isHost', 'hasDrawingStarted', 'isJoinedByMe'));
    }

    /**
     * Gabung ke sesi mabar dan hubungkan pemain ke tb_session_player di database Supabase.
     */
    public function joinSession($id, Request $request)
    {
        $session = null;
        try {
            $session = SessionModel::with('players')->find((int) $id);
        } catch (\Throwable $e) {
            $session = null;
        }

        if (! $session) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi mabar tidak ditemukan atau ID sesi tidak valid.',
                ], 404);
            }

            return redirect()->route('games.index')->with('error', 'Sesi mabar tidak ditemukan atau ID sesi tidak valid.');
        }

        $statusLower = strtolower($session->status_session ?? '');
        $quota = (int) ($session->jumlah_pemain ?? 6);
        $currentJoined = $session->players->count();
        $slotLeft = max(0, $quota - $currentJoined);

        // Tutup pendaftaran jika sesi sudah selesai atau pertandingan sudah berlangsung dan kuota sudah penuh
        if (in_array($statusLower, ['completed', 'finished']) || ($slotLeft === 0 && in_array($statusLower, ['in progress', 'in_progress']))) {
            $statusMsg = 'Pendaftaran ditutup: Sesi mabar ini sudah berlangsung atau telah selesai.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $statusMsg,
                ], 422);
            }

            return back()->with('error', $statusMsg);
        }

        if ($currentJoined >= $quota) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Slot untuk sesi mabar ini sudah penuh!',
                ], 422);
            }

            return back()->with('error', 'Slot untuk sesi mabar ini sudah penuh!');
        }

        $request->validate([
            'nama' => Auth::check() ? 'nullable|string|max:255' : 'required|string|max:255',
            'gender' => 'nullable|in:Male,Female',
            'usia' => 'nullable|integer|min:10|max:90',
            'level' => 'nullable|string|max:50',
        ], [
            'nama.required' => 'Nama pemain wajib diisi untuk bergabung ke sesi mabar.',
            'usia.integer' => 'Usia harus berupa angka.',
            'usia.min' => 'Usia minimal adalah 10 tahun.',
            'usia.max' => 'Usia maksimal adalah 90 tahun.',
        ]);

        try {
            DB::beginTransaction();

            if (Auth::check()) {
                $user = Auth::user();
                $player = Player::where('user_id', $user->user_id)
                    ->orWhere('email', $user->email)
                    ->first();

                if (! $player) {
                    $player = Player::create([
                        'user_id' => $user->user_id,
                        'nama' => $request->nama ?: $user->nama,
                        'gender' => $request->gender ?: 'Male',
                        'usia' => $request->filled('usia') ? (int) $request->usia : null,
                        'level' => $request->level ?: 'Intermediate',
                        'rating' => 3.0,
                        'no_hp' => $user->no_hp ?? null,
                        'email' => $user->email,
                    ]);
                }
            } else {
                // Guest Player Mode (Tamu) - tanpa nomor WA unik
                $player = Player::create([
                    'user_id' => null,
                    'nama' => trim($request->nama),
                    'gender' => $request->gender ?: 'Male',
                    'usia' => $request->filled('usia') ? (int) $request->usia : null,
                    'level' => $request->level ?: 'Intermediate',
                    'rating' => 3.0,
                    'no_hp' => null,
                    'email' => null,
                ]);
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

            $successMsg = Auth::check()
                ? "Berhasil bergabung ke sesi mabar: {$session->nama_session}!"
                : "Berhasil bergabung sebagai Guest Player ke sesi mabar: {$session->nama_session}!";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMsg,
                    'joined_count' => $session->players()->count(),
                    'quota' => $quota,
                ]);
            }

            return back()->with('success', $successMsg);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Gagal bergabung ke sesi mabar: '.$e->getMessage()], 500);
            }

            return back()->with('error', 'Gagal bergabung ke sesi mabar: '.$e->getMessage());
        }
    }

    public function drawing($id, Request $request)
    {
        $dbSession = SessionModel::with(['sport', 'venue', 'courts', 'players.user', 'host'])->findOrFail((int) $id);

        // Jika sesi sudah berstatus Finished, arahkan langsung ke halaman recap & podium
        if ($dbSession->status_session === 'Finished') {
            if ($request->wantsJson() || $request->ajax() || $request->has('json')) {
                return response()->json([
                    'success' => true,
                    'isFinished' => true,
                    'isLocked' => true,
                    'recap_url' => route('scoring.recap', $dbSession->session_id),
                    'message' => 'Pertandingan ini telah selesai. Menampilkan hasil akhir dan podium.',
                ]);
            }

            return redirect()->route('scoring.recap', $dbSession->session_id)
                ->with('info', 'Pertandingan ini telah selesai. Menampilkan hasil akhir dan podium juara.');
        }

        // PENYESUAIAN: Cek flag is_host dan kepemilikan host_user_id
        $isHost = Auth::check() && ((int) Auth::user()->user_id === (int) $dbSession->host_user_id);

        $hasDrawingStarted = in_array(strtolower($dbSession->status_session ?? ''), ['in progress', 'in_progress', 'live', 'playing', 'finished', 'completed', 'selesai']);
        if (! $hasDrawingStarted && Schema::hasTable('tb_match') && Schema::hasTable('tb_drawing')) {
            try {
                $hasDrawingStarted = GameMatch::whereHas('drawing', function ($q) use ($dbSession) {
                    $q->where('session_id', $dbSession->session_id);
                })->exists();
            } catch (\Throwable $e) {
                $hasDrawingStarted = false;
            }
        }

        // Non-host tidak boleh membuka drawing jika Host belum memulainya
        if (! $isHost && ! $hasDrawingStarted) {
            if ($request->wantsJson() || $request->ajax() || $request->has('json')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi drawing belum dimulai oleh Host.',
                ], 403);
            }

            return redirect()->route('games.show', $dbSession->session_id)
                ->with('error', 'Sesi drawing belum dimulai oleh Host.');
        }

        if (($request->has('shuffle') || $request->has('seed')) && ! $isHost) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Host sesi ini yang dapat mengacak drawing.',
            ], Auth::check() ? 403 : 401);
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
                    'age' => $p->usia,
                    'level' => $p->level ?? 'Intermediate',
                    'is_member' => true,
                    'avatar' => $p->user->foto ?? ($p->foto ?? null),
                ];
            })->toArray();

            // Ambil jenis_permainan (Single/Double) dari session
            $jenisPermainan = $dbSession->jenis_permainan ?? 'Double';
            $isSingleMode = strtolower($jenisPermainan) === 'single';

            $formatQuery = $isHost ? $request->query('format') : null;
            if (! $formatQuery) {
                $dbDrawing = Drawing::where('session_id', $dbSession->session_id)->with('matchFormat')->first();
                if ($dbDrawing && $dbDrawing->matchFormat) {
                    $formatQuery = $dbDrawing->matchFormat->nama_format;
                }
            }

            $game = [
                'id' => $dbSession->session_id,
                'title' => $dbSession->nama_session,
                'nama_session' => $dbSession->nama_session,
                'sport' => $dbSession->sport->nama_sport ?? 'Padel',
                'venue_id' => $dbSession->venue_id,
                'venue_name' => $dbSession->venue->nama_venue ?? 'Arena Olahraga',
                'court_name' => $dbSession->courts->first()->nama_court ?? 'Court 1',
                'date' => $dbSession->datetime ? $dbSession->datetime->format('Y-m-d') : date('Y-m-d'),
                'time' => self::resolveSessionDisplayTime($dbSession),
                'duration' => self::resolveSessionDuration($dbSession),
                'quota' => $quota,
                'joined_count' => count($participants),
                'status' => $status,
                'level_recommendation' => 'All Level Welcome',
                'match_format' => $formatQuery ?: 'Americano',
                'jenis_permainan' => $jenisPermainan,
                'scoring_system' => $dbSession->scoring_system ?? 'Total of 3',
                'host' => [
                    'name' => $dbSession->host->nama ?? 'Host Matcha',
                    'role' => 'Host Game',
                    'level' => 'Intermediate',
                    'avatar' => $dbSession->host->foto ?? null,
                ],
                'participants' => $participants,
            ];

            $courtCount = max(1, $dbSession->courts->count());
        } else {
            abort(404);
        }

        $participantsMap = [];
        foreach ($participants as $p) {
            $pName = is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (is_object($p) ? ($p->nama ?? $p->name ?? '') : (string) $p);
            $pGender = is_array($p) ? ($p['gender'] ?? 'Male') : (is_object($p) ? ($p->gender ?? 'Male') : 'Male');
            if ($pName) {
                $participantData = [
                    'name' => $pName,
                    'gender' => $pGender,
                ];
                $participantsMap[$pName] = $participantData;
                $participantsMap[strtolower(trim($pName))] = $participantData;
            }
        }

        // Cek apakah pertandingan sudah dimulai / scoring live sudah berjalan
        $isLocked = false;
        if (Cache::get("drawing.locked_{$game['id']}", false)) {
            $isLocked = true;
        }
        if (isset($dbSession->status_session) && in_array(strtolower($dbSession->status_session), ['in progress', 'in_progress', 'completed', 'finished', 'selesai'])) {
            $isLocked = true;
        }
        $cacheKey = "scoring.game_{$game['id']}";
        $savedScores = Cache::get($cacheKey, []);
        if (! empty($savedScores) && is_array($savedScores)) {
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
        $savedSchedule = Cache::get($scheduleCacheKey);

        // Periksa apakah cached schedule memuat pemain yang berbeda/dummy lama (stale cache)
        $currentParticipantNames = array_values(array_filter(array_map(function ($p) {
            $name = is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (is_object($p) ? ($p->nama ?? $p->name ?? '') : (string) $p);

            return trim($name);
        }, $participants)));
        sort($currentParticipantNames);

        $isCacheStale = false;
        if ($savedSchedule && ! empty($savedSchedule['rounds'])) {
            $cachedPlayers = [];
            foreach ($savedSchedule['rounds'] as $r) {
                if (! empty($r['matches'])) {
                    foreach ($r['matches'] as $m) {
                        foreach (($m['team_a_names'] ?? []) as $name) {
                            if (! empty($name)) {
                                $cachedPlayers[trim($name)] = true;
                            }
                        }
                        foreach (($m['team_b_names'] ?? []) as $name) {
                            if (! empty($name)) {
                                $cachedPlayers[trim($name)] = true;
                            }
                        }
                    }
                }
                foreach (($r['resting'] ?? []) as $name) {
                    if (! empty($name)) {
                        $cachedPlayers[trim($name)] = true;
                    }
                }
            }
            $cachedPlayerNames = array_keys($cachedPlayers);
            sort($cachedPlayerNames);

            if (! $isLocked && $currentParticipantNames !== $cachedPlayerNames) {
                $isCacheStale = true;
            }
        }

        $needsGeneration = false;
        if (! $savedSchedule || empty($savedSchedule['rounds']) || $isCacheStale) {
            $needsGeneration = true;
        } elseif (! $isLocked && ($request->has('shuffle') || $request->has('seed'))) {
            $needsGeneration = true;
        }

        if ($needsGeneration) {
            $seed = (int) $request->query('seed', rand(1000, 999999));
            try {
                if ($isTeam) {
                    // Team Americano Engine (Fixed Pairs). Pengacakan hanya mengacak urutan tim, bukan anggota tim!
                    $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? 'Total of 3');
                    $roundCount = $scoringSystem['is_sets'] ? $scoringSystem['max_sets'] : 1;
                    $teamService = new TeamAmericanoService;
                    $drawingData = $teamService->generateTeamRounds($participants, $courtCount, $seed, $roundCount);
                    $rounds = $drawingData['rounds'] ?? [];
                } else {
                    // Americano Engine (Individual Rotating Pairs) — Single atau Double
                    mt_srand($seed);
                    $pKeys = array_keys($participants);
                    shuffle($pKeys);
                    $shuffled = [];
                    foreach ($pKeys as $k) {
                        $shuffled[] = $participants[$k];
                    }
                    // Ambil jenis_permainan dari session (Single/Double), default Double
                    $drawingMode = isset($jenisPermainan) ? $jenisPermainan : ($dbSession->jenis_permainan ?? 'Double');
                    $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? 'Total of 3');
                    $roundCount = $scoringSystem['is_sets'] ? $scoringSystem['max_sets'] : 1;
                    $americanoService = new AmericanoService;
                    $rounds = $americanoService->generateRounds($shuffled, $courtCount, $roundCount, $drawingMode);
                    $drawingData = [
                        'format' => 'Americano '.$drawingMode,
                        'mode' => $drawingMode,
                        'total_teams' => count($participants),
                        'total_rounds' => count($rounds),
                        'total_matches' => array_sum(array_map(fn ($r) => count($r['matches'] ?? []), $rounds)),
                        'rounds' => $rounds,
                    ];
                }
            } catch (\Throwable $e) {
                $americanoService = new AmericanoService;
                $rounds = $americanoService->generateRounds($participants, $courtCount);
                $drawingData = [
                    'format' => 'Americano',
                    'mode' => 'Double',
                    'total_teams' => count($participants),
                    'total_rounds' => count($rounds),
                    'total_matches' => array_sum(array_map(fn ($r) => count($r['matches'] ?? []), $rounds)),
                    'rounds' => $rounds,
                ];
            }

            if ($isHost) {
                Cache::put($scheduleCacheKey, [
                    'drawingData' => $drawingData,
                    'rounds' => $rounds,
                ], now()->addHours(12));

                // Clear unstarted match participants if Host shuffled so stale pre-shuffle rosters don't linger
                if ($request->has('shuffle') || $request->has('seed')) {
                    $drawingModel = Drawing::where('session_id', $game['id'])->first();
                    if ($drawingModel) {
                        $unstartedMatchIds = GameMatch::where('drawing_id', $drawingModel->drawing_id)
                            ->where('status_match', '!=', 'Completed')
                            ->pluck('match_id')
                            ->all();
                        if (! empty($unstartedMatchIds)) {
                            MatchParticipant::whereIn('match_id', $unstartedMatchIds)->delete();
                        }
                    }
                }

                // Broadcast shuffled drawing to all connected spectators/players via Supabase Realtime
                if ($request->has('shuffle') || $request->has('seed')) {
                    $this->broadcastDrawingShuffledRealtime((int) $game['id'], $drawingData, $rounds, $participantsMap ?? []);
                }
            } else {
                // Ensure initial drawing schedule is also cached if first viewed by a non-host
                Cache::put($scheduleCacheKey, [
                    'drawingData' => $drawingData,
                    'rounds' => $rounds,
                ], now()->addHours(12));
            }
        } else {
            $drawingData = $savedSchedule['drawingData'] ?? [];
            $rounds = $savedSchedule['rounds'] ?? [];
        }

        // Jika request via AJAX / Fetch JSON
        if ($request->wantsJson() || $request->ajax() || $request->query('json')) {
            return response()->json([
                'success' => true,
                'isLocked' => $isLocked,
                'drawingData' => $drawingData,
                'rounds' => $rounds,
                'participantsMap' => $participantsMap,
                'redirect_url' => route('scoring.live', ['id' => $dbSession->session_id, 'format' => $game['match_format'] ?? 'Americano']),
            ]);
        }

        return view('games.drawing', compact('game', 'drawingData', 'rounds', 'participantsMap', 'isLocked', 'isHost'));
    }

    public function lockDrawing($id, Request $request)
    {
        // Cek autentikasi dan status host / admin
        if (! Auth::check() || (! Auth::user()->is_host && Auth::user()->role !== 'admin')) {
            abort(403);
        }

        $session = SessionModel::findOrFail((int) $id);
        if (Auth::user()->role !== 'admin' && (int) $session->host_user_id !== (int) Auth::user()->user_id) {
            abort(403);
        }

        $format = $request->input('format', 'Americano');

        // Kunci status drawing di cache
        Cache::put("drawing.locked_{$id}", true, now()->addHours(12));

        try {
            $session->status_session = 'In Progress';
            $session->save();

            $formatId = str_contains(strtolower($format), 'team') ? 4 : 1;
            $drawing = Drawing::firstOrCreate(
                ['session_id' => $session->session_id],
                [
                    'match_format_id' => $formatId,
                    'tanggal_drawing' => now()->toDateString(),
                    'jam_drawing' => now()->format('H:i:s'),
                ]
            );
            if ($drawing->match_format_id !== $formatId) {
                $drawing->match_format_id = $formatId;
                $drawing->save();
            }
            // Broadcast lock event via Supabase Realtime
            $this->broadcastDrawingLockedRealtime((int) $id, $format);
        } catch (\Throwable $e) {
            throw $e;
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

    /**
     * Broadcast status drawing diacak ulang ke channel Supabase Realtime.
     */
    protected function broadcastDrawingShuffledRealtime(int $gameId, array $drawingData, array $rounds, array $participantsMap): void
    {
        try {
            $url = rtrim(config('services.supabase.url', ''), '/').'/realtime/v1/api/broadcast';
            $key = config('services.supabase.key');
            if (empty($url) || empty($key)) {
                return;
            }

            Http::withoutVerifying()
                ->withHeaders([
                    'apikey' => $key,
                    'Authorization' => 'Bearer '.$key,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(2)
                ->post($url, [
                    'messages' => [
                        [
                            'topic' => "session_{$gameId}",
                            'event' => 'drawing_shuffled',
                            'payload' => [
                                'session_id' => $gameId,
                                'drawingData' => $drawingData,
                                'rounds' => $rounds,
                                'participantsMap' => $participantsMap,
                                'timestamp' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::debug("Supabase realtime drawing shuffled broadcast skipped: {$e->getMessage()}");
        }
    }

    /**
     * Broadcast status drawing terkunci ke channel Supabase Realtime.
     */
    protected function broadcastDrawingLockedRealtime(int $gameId, string $format): void
    {
        try {
            $url = rtrim(config('services.supabase.url', ''), '/').'/realtime/v1/api/broadcast';
            $key = config('services.supabase.key');
            if (empty($url) || empty($key)) {
                return;
            }

            Http::withoutVerifying()
                ->withHeaders([
                    'apikey' => $key,
                    'Authorization' => 'Bearer '.$key,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(2)
                ->post($url, [
                    'messages' => [
                        [
                            'topic' => "session_{$gameId}",
                            'event' => 'drawing_locked',
                            'payload' => [
                                'session_id' => $gameId,
                                'is_locked' => true,
                                'status_session' => 'In Progress',
                                'format' => $format,
                                'redirect_url' => route('scoring.live', ['id' => $gameId, 'format' => $format]),
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::debug("Supabase realtime drawing locked broadcast skipped: {$e->getMessage()}");
        }
    }

    /**
     * Batalkan sesi mabar secara aman (status_session = 'Cancelled').
     * Route: POST /games/{id}/cancel
     */
    public function cancelSession($id)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $session = SessionModel::findOrFail((int) $id);

        if (Auth::user()->role !== 'admin' && (int) $session->host_user_id !== (int) Auth::id()) {
            abort(403, 'Akses ditolak: Hanya Host atau Administrator yang dapat membatalkan sesi mabar ini.');
        }

        $status = strtolower(trim((string) $session->status_session));
        if (in_array($status, ['finished', 'completed'])) {
            return redirect()->route('games.show', $session->session_id)
                ->with('error', 'Sesi mabar yang telah selesai tidak dapat dibatalkan.');
        }

        $session->status_session = 'Cancelled';
        $session->save();

        return redirect()->route('games.show', $session->session_id)
            ->with('success', 'Sesi mabar berhasil dibatalkan. Riwayat data dan peserta tetap tersimpan dengan aman.');
    }
}
