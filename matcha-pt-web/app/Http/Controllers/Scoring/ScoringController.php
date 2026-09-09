<?php

namespace App\Http\Controllers\Scoring;

use App\Http\Controllers\Controller;
use App\Services\MatchaDummyDataService;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ScoringController extends Controller
{
    /**
     * Live Match Scoring Console.
     * Menampilkan scoring board aktif berdasarkan data game & round yang dipilih.
     */
    public function live($id = 1)
    {
        $games = MatchaDummyDataService::getGames();
        $game  = collect($games)->firstWhere('id', (int) $id) ?? $games[0];

        // Tentukan round aktif (bisa dari query param atau default round_1)
        $activeRound = request('round', 'round_1');

        // Bangun konteks match (tim A, tim B, istirahat) untuk round aktif
        $matchContext = ScoringService::buildMatchContext($game, $activeRound);

        // Deteksi scoring system
        $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? '');

        // Ambil skor tersimpan dari Cache (shared antar semua user)
        $cacheKey    = "scoring.game_{$game['id']}";
        $savedScores = Cache::get($cacheKey, []);
        $defaultScore = [
            'score_a'         => 0,
            'score_b'         => 0,
            'point_display_a' => '0',
            'point_display_b' => '0',
            'games_a'         => 0,
            'games_b'         => 0,
            'idx_a'           => 0,
            'idx_b'           => 0,
            'is_deuce'        => false,
            'advantage'       => null,
            'status'          => 'in_progress',
        ];
        $currentScore = array_merge($defaultScore, $savedScores[$activeRound] ?? []);

        // Deteksi role: hanya 'host' yang bisa mengedit skor
        $userRole = Auth::check() ? Auth::user()->role : 'guest';
        $isHost   = ($userRole === 'host');

        return view('scoring.live', compact(
            'game',
            'matchContext',
            'scoringSystem',
            'currentScore',
            'activeRound',
            'savedScores',
            'isHost',
            'userRole',
            'cacheKey'
        ));
    }

    /**
     * JSON endpoint: ambil skor terkini dari Cache.
     * Dipakai oleh member untuk polling realtime via fetch().
     */
    public function getScore($gameId, $round = 'round_1')
    {
        $cacheKey = "scoring.game_{$gameId}";
        $scores   = Cache::get($cacheKey, []);
        $score    = $scores[$round] ?? [];

        return response()->json([
            'game_id'         => (int) $gameId,
            'round'           => $round,
            'score_a'         => (int) ($score['score_a'] ?? 0),
            'score_b'         => (int) ($score['score_b'] ?? 0),
            'point_display_a' => (string) ($score['point_display_a'] ?? ($score['score_a'] ?? '0')),
            'point_display_b' => (string) ($score['point_display_b'] ?? ($score['score_b'] ?? '0')),
            'games_a'         => (int) ($score['games_a'] ?? 0),
            'games_b'         => (int) ($score['games_b'] ?? 0),
            'idx_a'           => (int) ($score['idx_a'] ?? 0),
            'idx_b'           => (int) ($score['idx_b'] ?? 0),
            'is_deuce'        => (bool) ($score['is_deuce'] ?? false),
            'advantage'       => $score['advantage'] ?? null,
            'scoring_type'    => $score['scoring_type'] ?? 'tennis',
            'status'          => $score['status'] ?? 'in_progress',
        ]);
    }

    /**
     * Simpan/update skor pertandingan (dipanggil via AJAX fetch dari live view).
     * Menyimpan ke Laravel Cache.
     */
    public function updateScore(Request $request)
    {
        // Hanya host yang boleh update skor
        if (!Auth::check() || Auth::user()->role !== 'host') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Host yang dapat mencatat skor.',
            ], 403);
        }

        $request->validate([
            'game_id'         => 'required|integer',
            'round'           => 'required|string',
            'score_a'         => 'required|integer|min:0',
            'score_b'         => 'required|integer|min:0',
            'point_display_a' => 'nullable|string',
            'point_display_b' => 'nullable|string',
            'games_a'         => 'nullable|integer|min:0',
            'games_b'         => 'nullable|integer|min:0',
            'idx_a'           => 'nullable|integer|min:0',
            'idx_b'           => 'nullable|integer|min:0',
            'is_deuce'        => 'nullable|boolean',
            'advantage'       => 'nullable|string',
            'scoring_type'    => 'nullable|string',
            'status'          => 'nullable|string|in:in_progress,completed',
        ]);

        $gameId = $request->integer('game_id');
        $round  = $request->string('round')->toString();
        $status = $request->input('status', 'in_progress');

        // Simpan ke Cache (TTL 4 jam) agar bisa dibaca semua user
        $cacheKey = "scoring.game_{$gameId}";
        $scores   = Cache::get($cacheKey, []);

        $scores[$round] = [
            'score_a'         => $request->integer('score_a'),
            'score_b'         => $request->integer('score_b'),
            'point_display_a' => (string) $request->input('point_display_a', (string) $request->integer('score_a')),
            'point_display_b' => (string) $request->input('point_display_b', (string) $request->integer('score_b')),
            'games_a'         => $request->integer('games_a', 0),
            'games_b'         => $request->integer('games_b', 0),
            'idx_a'           => $request->integer('idx_a', 0),
            'idx_b'           => $request->integer('idx_b', 0),
            'is_deuce'        => (bool) $request->input('is_deuce', false),
            'advantage'       => $request->input('advantage', null),
            'scoring_type'    => $request->input('scoring_type', 'tennis'),
            'status'          => $status,
            'updated_at'      => now()->toDateTimeString(),
        ];

        Cache::put($cacheKey, $scores, now()->addHours(4));

        return response()->json([
            'success' => true,
            'message' => 'Skor berhasil disimpan.',
            'saved'   => $scores[$round],
        ]);
    }

    /**
     * Tandai sesi sebagai selesai dan redirect ke halaman recap.
     */
    public function finishSession(Request $request)
    {
        // Hanya host yang boleh menyelesaikan sesi
        if (!Auth::check() || Auth::user()->role !== 'host') {
            return redirect()->back()->withErrors(['auth' => 'Akses ditolak. Hanya Host yang dapat menyelesaikan sesi.']);
        }

        $request->validate([
            'game_id'  => 'required|integer',
            'round'    => 'required|string',
            'score_a'  => 'required|integer|min:0',
            'score_b'  => 'required|integer|min:0',
        ]);

        $gameId = $request->integer('game_id');
        $round  = $request->string('round')->toString();

        // Simpan skor final ke Cache (shared)
        $cacheKey = "scoring.game_{$gameId}";
        $scores   = Cache::get($cacheKey, []);

        $prev = $scores[$round] ?? [];
        $scores[$round] = array_merge($prev, [
            'score_a'    => $request->integer('score_a'),
            'score_b'    => $request->integer('score_b'),
            'status'     => 'completed',
            'updated_at' => now()->toDateTimeString(),
        ]);

        // Tandai game sebagai finished
        $scores['_meta'] = [
            'finished_at' => now()->toDateTimeString(),
            'status'      => 'finished',
        ];

        Cache::put($cacheKey, $scores, now()->addHours(4));

        return redirect()
            ->route('scoring.recap', $gameId)
            ->with('success', 'Pertandingan selesai! Berikut rekap hasil akhir.');
    }

    /**
     * Podium & Klasemen Akhir.
     * Menghitung akumulasi poin semua pemain dari rounds yang sudah selesai.
     */
    public function recap($id = 1)
    {
        $games = MatchaDummyDataService::getGames();
        $game  = collect($games)->firstWhere('id', (int) $id) ?? $games[0];

        // Ambil skor tersimpan dari Cache (shared)
        $cacheKey    = "scoring.game_{$game['id']}";
        $savedScores = Cache::get($cacheKey, []);

        // Hitung ranking semua pemain
        $rankedPlayers = ScoringService::calculateRecap($game, $savedScores);

        // Tentukan winner & scoreboard match terakhir
        $lastRoundKey  = null;
        $lastScore     = null;
        $drawing       = $game['drawing'] ?? [];

        if (!empty($drawing)) {
            $lastRoundKey = array_key_last($drawing);
            $lastRound    = $drawing[$lastRoundKey];

            if (isset($savedScores[$lastRoundKey])) {
                $lastScore = $savedScores[$lastRoundKey];
            } else {
                // Fallback dummy
                [$sa, $sb] = [32, 20]; // default dummy final score
                $lastScore = ['score_a' => $sa, 'score_b' => $sb, 'status' => 'completed'];
            }

            $lastScore['team_a'] = $lastRound['team_a'] ?? [];
            $lastScore['team_b'] = $lastRound['team_b'] ?? [];
        }

        // Data untuk player recap card (Strava-like)
        $playerRecap = MatchaDummyDataService::getPlayerRecap();

        return view('scoring.recap', compact(
            'game',
            'rankedPlayers',
            'lastScore',
            'playerRecap',
            'savedScores'
        ));
    }
}
