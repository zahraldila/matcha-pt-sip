<?php

namespace App\Services\Scoring;

use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\MatchParticipant;
use App\Models\Player;
use App\Models\SessionModel;
use App\Services\Drawing\AmericanoService;
use App\Services\Drawing\TeamAmericanoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScoringService
{
    /**
     * Parse scoring system string dari game data.
     * Sistem scoring yang tersedia HANYA:
     * 1. Total of 3 (Target 2 Set)
     * 2. Total of 4 (Target 3 Set)
     * 3. Total of 5 (Target 3 Set)
     * 4. Total of 6 (Target 4 Set)
     * 5. Total of 7 (Target 4 Set)
     * 6. First to 8 (Target 8 Game)
     * 7. First to 11 (Target 11 Game)
     * 8. First to 15 (Target 15 Game)
     * 9. First to 21 (Target 21 Game)
     */
    /**
     * Daftar opsi Total of yang dapat dipilih untuk sesi baru (3 sampai 10, inklusif).
     */
    public static function getSelectableTotalOfOptions(): array
    {
        return [3, 4, 5, 6, 7, 8, 9, 10];
    }

    /**
     * Daftar opsi First to yang dapat dipilih untuk sesi baru (2 sampai 10, inklusif).
     */
    public static function getSelectableFirstToOptions(): array
    {
        return [2, 3, 4, 5, 6, 7, 8, 9, 10];
    }

    /**
     * Daftar opsi First to legacy untuk kompatibilitas sesi lama (11, 15, 21).
     */
    public static function getLegacyFirstToOptions(): array
    {
        return [11, 15, 21];
    }

    /**
     * Daftar string sistem skor yang valid untuk pembuatan sesi baru.
     */
    public static function getAllowedNewScoringSystems(): array
    {
        $options = [];
        foreach (self::getSelectableTotalOfOptions() as $sets) {
            $options[] = "Total of {$sets}";
        }
        foreach (self::getSelectableFirstToOptions() as $games) {
            $options[] = "First to {$games}";
        }

        return $options;
    }

    /**
     * Periksa apakah suatu nilai string merupakan sistem skor yang sah untuk sesi baru.
     */
    public static function isValidNewScoringOption(?string $scoringSystem): bool
    {
        if ($scoringSystem === null || trim($scoringSystem) === '') {
            return false;
        }

        return in_array(trim($scoringSystem), self::getAllowedNewScoringSystems(), true);
    }

    /**
     * Deteksi dan parse konfigurasi scoring system.
     * Mendukung opsi baru (Total of 3-10, First to 2-10) serta opsi legacy (First to 11, 15, 21).
     */
    public static function detectScoringSystem(string $scoringSystem = ''): array
    {
        $str = trim($scoringSystem);

        // Grup Total of X (Total Games gabungan kedua tim dalam satu pertandingan)
        if (preg_match('/total\s*of\s*(\d+)/i', $str, $matches)) {
            $total = (int) $matches[1];
            if (! in_array($total, self::getSelectableTotalOfOptions(), true)) {
                $total = 3;
            }

            return [
                'type' => 'total_of_sets',
                'category' => 'total_of',
                'max_sets' => $total,
                'target_sets' => $total,
                'target_games' => $total,
                'target_total_games' => $total,
                'label' => "Total of {$total}",
                'is_sets' => true,
                'is_total_of' => true,
            ];
        }

        // Grup First to X (Games) - mendukung opsi baru 2..10 dan legacy 11, 15, 21
        if (preg_match('/first\s*to\s*(\d+)/i', $str, $matches)) {
            $games = (int) $matches[1];
            $allowedGames = array_merge(self::getSelectableFirstToOptions(), self::getLegacyFirstToOptions());
            if (! in_array($games, $allowedGames, true)) {
                $games = 8;
            }

            return [
                'type' => 'first_to_games',
                'category' => 'games',
                'max_sets' => 1,
                'target_sets' => 1,
                'target_games' => $games,
                'target_total_games' => $games,
                'label' => "First to {$games}",
                'is_sets' => false,
                'is_total_of' => false,
            ];
        }

        // Default fallback ke Total of 3
        return [
            'type' => 'total_of_sets',
            'category' => 'total_of',
            'max_sets' => 3,
            'target_sets' => 3,
            'target_games' => 3,
            'target_total_games' => 3,
            'label' => 'Total of 3',
            'is_sets' => true,
            'is_total_of' => true,
        ];
    }

    /**
     * Menghitung jumlah ronde yang tepat untuk sesi pertandingan.
     *
     * - Untuk Total of X: selalu mengikuti aturan Total of (null agar drawing fleksibel / tidak membatasi rotasi).
     * - Untuk First to X: mendukung rotasi multi-ronde sesuai jumlah pemain, format, dan durasi bermain.
     *
     * @param  int  $playerCount  Jumlah pemain
     * @param  bool  $isTeam  Apakah format Team Americano (pasangan tetap)
     * @param  bool  $isSingle  Apakah format Americano Single (1 vs 1)
     * @param  string|null  $waktuSession  Misal: "19:00 WIB (2 Jam)"
     * @param  int  $courtCount  Jumlah court aktif
     * @return int|null Jumlah ronde yang dihasilkan
     */
    public static function determineSessionRoundCount(
        array|string $scoringSystem,
        int $playerCount,
        bool $isTeam = false,
        bool $isSingle = false,
        ?string $waktuSession = null,
        int $courtCount = 1
    ): ?int {
        $system = is_array($scoringSystem) ? $scoringSystem : self::detectScoringSystem($scoringSystem);

        // 1. Total of N: Jumlah set/ronde yang dimainkan adalah N
        if (! empty($system['is_total_of']) || ($system['category'] ?? '') === 'total_of' || ! empty($system['is_sets'])) {
            return (int) ($system['max_sets'] ?? 3);
        }

        // 2. First to X: Hitung estimasi ronde rotasi
        $durationHours = null;
        if (! empty($waktuSession) && preg_match('/(\d+)\s*jam/i', $waktuSession, $matches)) {
            $durationHours = (int) $matches[1];
        }

        if ($isSingle) {
            // Americano Single (1v1)
            if ($playerCount <= 2) {
                return 3; // 2 pemain: 3 ronde pertandingan (best of 3 sets)
            }
            $completeRotation = ($playerCount % 2 === 0) ? ($playerCount - 1) : $playerCount;
            if ($durationHours !== null && $durationHours > 0) {
                $maxDurationRounds = max(3, $durationHours * 3);

                return min($completeRotation, $maxDurationRounds);
            }

            return max(3, min(7, $completeRotation));
        }

        if ($isTeam) {
            // Team Americano (2 pemain per tim tetap)
            $teams = max(2, intdiv($playerCount, 2));
            if ($teams <= 2) {
                return 3; // 2 tim: 3 ronde pertandingan (Set 1, 2, 3)
            }
            $completeRotation = ($teams % 2 === 0) ? ($teams - 1) : $teams;
            if ($durationHours !== null && $durationHours > 0) {
                $maxDurationRounds = max(3, $durationHours * 2);

                return min($completeRotation, $maxDurationRounds);
            }

            return max(3, min(5, $completeRotation));
        }

        // Americano Double (2v2 berganti partner)
        if ($playerCount <= 4) {
            return 3; // 4 pemain: tepat 3 ronde rotasi unik
        }

        $completeRotation = ($playerCount % 2 === 0) ? ($playerCount - 1) : $playerCount;
        if ($durationHours !== null && $durationHours > 0) {
            $estRounds = max(3, (int) round(($durationHours * 60) / 25 * max(1, $courtCount)));

            return min($completeRotation, $estRounds);
        }

        return max(3, min(7, $completeRotation));
    }

    /**
     * Menentukan apakah suatu pertandingan per set/ronde telah selesai sesuai aturan scoring system.
     *
     * Aturan Total Of N:
     * - N merupakan total gabungan skor besar kedua sisi dalam satu pertandingan (games_a + games_b = N).
     * - Pertandingan selesai jika dan hanya jika: games_a + games_b = N.
     * - Seluruh N game tetap dimainkan; tidak berhenti dini hanya karena mayoritas telah tercapai.
     * - Jika games_a + games_b < N: pertandingan masih aktif (in_progress).
     *
     * Aturan First To X:
     * - Sisi pertama yang mencapai target skor besar memenangkan pertandingan.
     * - Selesai jika salah satu tim mencapai target_games (games_a >= X || games_b >= X).
     */
    public static function isMatchCompleted(int $gamesA, int $gamesB, array $scoringSystem): bool
    {
        $isTotalOf = ($scoringSystem['category'] ?? '') === 'total_of'
            || ($scoringSystem['is_total_of'] ?? false)
            || preg_match('/total\s*of\s*(\d+)/i', $scoringSystem['label'] ?? '');

        if ($isTotalOf) {
            $target = (int) ($scoringSystem['target_total_games'] ?? ($scoringSystem['target_games'] ?? ($scoringSystem['max_sets'] ?? 3)));

            return ($gamesA + $gamesB) === $target;
        }

        // First to games
        $target = (int) ($scoringSystem['target_games'] ?? 8);
        if ($target <= 0) {
            return false;
        }

        return $gamesA >= $target || $gamesB >= $target;
    }

    /**
     * Menentukan tim pemenang match ('Team A' | 'Team B' | null).
     *
     * Total Of N:
     * - Selesai jika games_a + games_b = N.
     * - Jika games_a > games_b: 'Team A'
     * - Jika games_b > games_a: 'Team B'
     * - Jika games_a === games_b (hasil genap seri, misal 2-2 pada Total Of 4, 5-5 pada Total of 10):
     *   null (selesai tanpa pemenang tunggal).
     *
     * First To X:
     * - games_a >= X dan games_a > games_b: 'Team A'
     * - games_b >= X dan games_b > games_a: 'Team B'
     * - Jika keduanya >= X: sisi dengan skor lebih tinggi menang.
     */
    public static function determineMatchWinner(int $gamesA, int $gamesB, array $scoringSystem): ?string
    {
        $isTotalOf = ($scoringSystem['category'] ?? '') === 'total_of'
            || ($scoringSystem['is_total_of'] ?? false)
            || preg_match('/total\s*of\s*(\d+)/i', $scoringSystem['label'] ?? '');

        if ($isTotalOf) {
            $target = (int) ($scoringSystem['target_total_games'] ?? ($scoringSystem['target_games'] ?? ($scoringSystem['max_sets'] ?? 3)));
            if (($gamesA + $gamesB) !== $target) {
                return null;
            }

            if ($gamesA > $gamesB) {
                return 'Team A';
            }
            if ($gamesB > $gamesA) {
                return 'Team B';
            }

            // Seri (Draw): Selesai tanpa pemenang tunggal
            return null;
        }

        // First to games
        $target = (int) ($scoringSystem['target_games'] ?? 8);
        if ($target > 0) {
            if ($gamesA >= $target && $gamesA > $gamesB) {
                return 'Team A';
            }
            if ($gamesB >= $target && $gamesB > $gamesA) {
                return 'Team B';
            }
            if ($gamesA >= $target && $gamesB >= $target) {
                return $gamesA >= $gamesB ? 'Team A' : 'Team B';
            }
        }

        return null;
    }

    /**
     * Helper sentralistik untuk membangun format key skor yang konsisten di semua layer.
     */
    public static function buildMatchKey(string $roundKey, int $courtNumber, int $totalCourtCount): string
    {
        return $totalCourtCount > 1 ? "{$roundKey}_court_{$courtNumber}" : $roundKey;
    }

    /**
     * Pastikan setiap round pada drawing memiliki skor yang sinkron dan konsisten.
     * Menggabungkan skor dari session cache dengan skor dummy deterministik jika belum ada.
     */
    public static function getEffectiveScores(array $game, array $sessionScores = []): array
    {
        $drawing = $game['drawing'] ?? [];
        $effective = $sessionScores;
        unset($effective['_meta']);
        $scoringSystem = $game['scoring_system'] ?? '';
        $system = self::detectScoringSystem($scoringSystem);

        // Cek apakah ada minimal 1 skor riil yang berstatus completed atau sesi berstatus finished
        $hasRealCompleted = false;
        $isFinishedSession = ($sessionScores['_meta']['status'] ?? '') === 'finished';

        foreach ($sessionScores as $k => $v) {
            if ($k !== '_meta' && is_array($v)) {
                if (($v['status'] ?? '') === 'completed') {
                    $hasRealCompleted = true;
                    break;
                }
                if ($isFinishedSession && (
                    ($v['games_a'] ?? 0) > 0 || ($v['games_b'] ?? 0) > 0 ||
                    ($v['score_a'] ?? 0) > 0 || ($v['score_b'] ?? 0) > 0 ||
                    ($v['sets_a'] ?? 0) > 0 || ($v['sets_b'] ?? 0) > 0
                )) {
                    $hasRealCompleted = true;
                    break;
                }
            }
        }

        foreach ($drawing as $roundKey => $round) {
            $courtCount = $round['court_count'] ?? 1;
            $matches = $round['matches'] ?? [];
            $totalCountForSuffix = max(1, count($matches), $courtCount);

            for ($i = 0; $i < $totalCountForSuffix; $i++) {
                $courtNumber = $matches[$i]['court'] ?? ($i + 1);
                $matchKey = self::buildMatchKey($roundKey, $courtNumber, $totalCountForSuffix);

                $candidateKeys = [
                    $matchKey,
                    "{$roundKey}_court_{$courtNumber}",
                    $roundKey,
                    "{$roundKey}_match_{$i}",
                ];

                $matchedScore = null;
                foreach ($candidateKeys as $cKey) {
                    if (isset($effective[$cKey]) && is_array($effective[$cKey])) {
                        $candidate = $effective[$cKey];
                        if (($candidate['status'] ?? '') === 'completed' ||
                            ($isFinishedSession && (
                                ($candidate['games_a'] ?? 0) > 0 ||
                                ($candidate['games_b'] ?? 0) > 0 ||
                                ($candidate['sets_a'] ?? 0) > 0 ||
                                ($candidate['sets_b'] ?? 0) > 0 ||
                                ($candidate['score_a'] ?? 0) > 0 ||
                                ($candidate['score_b'] ?? 0) > 0
                            ))
                        ) {
                            $matchedScore = $candidate;
                            break;
                        }
                    }
                }

                if ($matchedScore === null) {
                    foreach ($candidateKeys as $cKey) {
                        if (isset($effective[$cKey]) && is_array($effective[$cKey])) {
                            $matchedScore = $effective[$cKey];
                            break;
                        }
                    }
                }

                if ($matchedScore !== null) {
                    $effective[$matchKey] = $matchedScore;
                }

                $isCompleted = isset($effective[$matchKey]) && (
                    ($effective[$matchKey]['status'] ?? '') === 'completed' ||
                    ($isFinishedSession && (
                        ($effective[$matchKey]['games_a'] ?? 0) > 0 ||
                        ($effective[$matchKey]['games_b'] ?? 0) > 0 ||
                        ($effective[$matchKey]['sets_a'] ?? 0) > 0 ||
                        ($effective[$matchKey]['sets_b'] ?? 0) > 0 ||
                        ($effective[$matchKey]['score_a'] ?? 0) > 0 ||
                        ($effective[$matchKey]['score_b'] ?? 0) > 0
                    ))
                );

                if ($isCompleted) {
                    $effective[$matchKey]['status'] = 'completed';
                    if (empty($effective[$matchKey]['team_a'])) {
                        $effective[$matchKey]['team_a'] = $matches[$i]['team_a'] ?? ($round['team_a'] ?? []);
                    }
                    if (empty($effective[$matchKey]['team_b'])) {
                        $effective[$matchKey]['team_b'] = $matches[$i]['team_b'] ?? ($round['team_b'] ?? []);
                    }
                    $effective[$matchKey]['round_title'] = ucfirst(str_replace('_', ' ', $roundKey));
                    $effective[$matchKey]['scoring_type'] = $system['type'];

                    // Pastikan set_history dan sets_a/b tidak kosong jika format Total of Sets
                    if ($system['type'] === 'total_of_sets') {
                        $eSetsA = (int) ($effective[$matchKey]['sets_a'] ?? 0);
                        $eSetsB = (int) ($effective[$matchKey]['sets_b'] ?? 0);
                        $eGamesA = (int) ($effective[$matchKey]['games_a'] ?? 0);
                        $eGamesB = (int) ($effective[$matchKey]['games_b'] ?? 0);
                        $eHist = $effective[$matchKey]['set_history'] ?? [];

                        if (! empty($eHist)) {
                            $lastSet = $eHist[array_key_last($eHist)];
                            $lastGameA = (int) ($lastSet['score_a'] ?? 0);
                            $lastGameB = (int) ($lastSet['score_b'] ?? 0);

                            if (($eGamesA === 0 && $eGamesB === 0) && ($lastGameA > 0 || $lastGameB > 0)) {
                                $eGamesA = $lastGameA;
                                $eGamesB = $lastGameB;
                            }

                            if (($eSetsA === 0 && $eSetsB === 0) || empty($eHist)) {
                                $eSetsA = 0;
                                $eSetsB = 0;
                                foreach ($eHist as $set) {
                                    $ga = (int) ($set['score_a'] ?? 0);
                                    $gb = (int) ($set['score_b'] ?? 0);
                                    if ($ga > $gb) {
                                        $eSetsA++;
                                    } elseif ($gb > $ga) {
                                        $eSetsB++;
                                    }
                                }
                            }
                        } elseif ($eGamesA > 0 || $eGamesB > 0) {
                            $eHist = [
                                ['set' => 1, 'score_a' => $eGamesA, 'score_b' => $eGamesB],
                            ];
                            if ($eSetsA === 0 && $eSetsB === 0) {
                                $eSetsA = $eGamesA >= $eGamesB ? 1 : 0;
                                $eSetsB = $eGamesB > $eGamesA ? 1 : 0;
                            }
                        } elseif ($eSetsA > 0 || $eSetsB > 0) {
                            $eHist = [];
                            for ($j = 1; $j <= ($eSetsA + $eSetsB); $j++) {
                                $aWins = ($j <= $eSetsA);
                                $eHist[] = [
                                    'set' => $j,
                                    'score_a' => $aWins ? 6 : 3,
                                    'score_b' => $aWins ? 3 : 6,
                                ];
                            }
                        }

                        $effective[$matchKey]['set_history'] = $eHist;
                        $effective[$matchKey]['sets_a'] = $eSetsA;
                        $effective[$matchKey]['sets_b'] = $eSetsB;
                        $effective[$matchKey]['games_a'] = $eGamesA;
                        $effective[$matchKey]['games_b'] = $eGamesB;
                        $effective[$matchKey]['score_a'] = $eGamesA;
                        $effective[$matchKey]['score_b'] = $eGamesB;
                    }
                } else {
                    // Round yang belum dimainkan / selesai berstatus pending dengan skor 0
                    $effective[$matchKey] = [
                        'score_a' => 0,
                        'score_b' => 0,
                        'sets_a' => 0,
                        'sets_b' => 0,
                        'games_a' => 0,
                        'games_b' => 0,
                        'set_history' => [],
                        'status' => 'pending',
                        'team_a' => $matches[$i]['team_a'] ?? ($round['team_a'] ?? []),
                        'team_b' => $matches[$i]['team_b'] ?? ($round['team_b'] ?? []),
                        'round_title' => ucfirst(str_replace('_', ' ', $roundKey)),
                        'scoring_type' => $system['type'],
                    ];
                }
            }
        }

        return $effective;
    }

    /**
     * Hitung akumulasi poin/game/set setiap pemain dari data rounds drawing + skor session.
     * Mengembalikan array player stats, sudah diurutkan dari pemenang tertinggi.
     */
    public static function calculateRecap(array $game, array $sessionScores = []): array
    {
        $players = $game['participants'] ?? [];
        $drawing = $game['drawing'] ?? [];
        $scoringSystem = $game['scoring_system'] ?? '';
        $system = self::detectScoringSystem($scoringSystem);

        // Fallback jika belum ada drawing tapi ada participants
        if (empty($drawing) && ! empty($players)) {
            $names = array_map(fn ($p) => self::cleanPlayerName($p['name'] ?? ''), $players);
            $half = (int) ceil(count($names) / 2);
            $drawing = [
                'round_1' => [
                    'team_a' => array_slice($names, 0, min(2, $half)),
                    'team_b' => array_slice($names, min(2, $half), 2),
                    'resting' => array_slice($names, 4),
                ],
            ];
            $game['drawing'] = $drawing;
        }

        $effectiveScores = self::getEffectiveScores($game, $sessionScores);

        // Check if format is team-based
        $format = strtolower(trim($game['match_format'] ?? $game['format'] ?? ''));
        $isPerTeam = in_array($format, ['team americano', 'team mexicano', 'mixicano']);

        // Init stats
        $stats = [];
        if ($isPerTeam) {
            foreach ($drawing as $rKey => $round) {
                if ($rKey === 'teams') {
                    continue;
                }
                $roundMatches = $round['matches'] ?? [$round];
                foreach ($roundMatches as $m) {
                    foreach (['a', 'b'] as $side) {
                        $team = $m["team_{$side}"] ?? [];
                        if (! empty($team)) {
                            if (is_array($team) && isset($team['name'])) {
                                $name = $team['display_name'] ?? $team['name'] ?? 'Tim';
                                $teamIdentity = $team['name'] ?? null;
                            } else {
                                $mTeam = $m["team_{$side}_names"] ?? ($m['team'.strtoupper($side).'_names'] ?? $team);
                                $name = implode(' & ', is_array($mTeam) ? $mTeam : [$mTeam]);
                                if (empty($name)) {
                                    $name = "Tim {$side}";
                                }
                                $teamIdentity = $name;
                            }
                            if (! isset($stats[$name])) {
                                $stats[$name] = [
                                    'name' => $name,
                                    'team_identity' => $teamIdentity,
                                    'avatar' => null,
                                    'level' => '-',
                                    'is_member' => false,
                                    'matches' => 0,
                                    'wins' => 0,
                                    'losses' => 0,
                                    'sets_won' => 0,
                                    'sets_lost' => 0,
                                    'games_won' => 0,
                                    'games_lost' => 0,
                                    'points_for' => 0,
                                    'points_against' => 0,
                                    'point_diff' => 0,
                                    'game_diff' => 0,
                                    'set_diff' => 0,
                                ];
                            }
                        }
                    }
                }
            }
        } else {
            foreach ($players as $p) {
                $name = $p['name'];
                $cleanName = self::cleanPlayerName($name);
                $stats[$cleanName] = [
                    'name' => $name,
                    'avatar' => $p['avatar'] ?? null,
                    'level' => $p['level'] ?? '-',
                    'is_member' => $p['is_member'] ?? false,
                    'matches' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'sets_won' => 0,
                    'sets_lost' => 0,
                    'games_won' => 0,
                    'games_lost' => 0,
                    'points_for' => 0,   // Sets won (Total of Sets) atau Games won (First to Games)
                    'points_against' => 0,
                    'point_diff' => 0,
                    'game_diff' => 0,
                    'set_diff' => 0,
                ];
            }
        }
        if (empty($drawing)) {
            return array_values($stats);
        }

        // Iterasi tiap round menggunakan $effectiveScores yang sudah sinkron
        foreach ($drawing as $roundKey => $round) {
            $roundMatches = $round['matches'] ?? [
                [
                    'court' => 1,
                    'court_name' => 'Court 1',
                    'team_a' => $round['team_a'] ?? [],
                    'team_b' => $round['team_b'] ?? [],
                    'team_a_names' => $round['team_a'] ?? [],
                    'team_b_names' => $round['team_b'] ?? [],
                ],
            ];

            $courtCount = $round['court_count'] ?? 1;
            $totalCountForSuffix = max(1, count($roundMatches), $courtCount);

            foreach ($roundMatches as $mIdx => $m) {
                $mCourt = $m['court'] ?? ($mIdx + 1);
                $matchScoreKey = self::buildMatchKey($roundKey, $mCourt, $totalCountForSuffix);

                $matchScore = $effectiveScores[$matchScoreKey] ?? [];

                // Fallback (legacy single key atau versi match / court)
                if (empty($matchScore) || ($matchScore['status'] ?? '') !== 'completed') {
                    $candidateAltKeys = [
                        "{$roundKey}_court_{$mCourt}",
                        $roundKey,
                        "{$roundKey}_match_{$mIdx}",
                    ];
                    foreach ($candidateAltKeys as $altKey) {
                        if (isset($effectiveScores[$altKey]) && ($effectiveScores[$altKey]['status'] ?? '') === 'completed') {
                            $matchScore = $effectiveScores[$altKey];
                            break;
                        }
                    }
                }

                $status = $matchScore['status'] ?? ($effectiveScores[$roundKey]['status'] ?? 'pending');

                // Hanya hitung match yang sudah selesai
                if ($status !== 'completed') {
                    continue;
                }

                $scoreA = (int) ($matchScore['score_a'] ?? 0);
                $scoreB = (int) ($matchScore['score_b'] ?? 0);
                $setsA = (int) ($matchScore['sets_a'] ?? 0);
                $setsB = (int) ($matchScore['sets_b'] ?? 0);
                $gamesA = (int) ($matchScore['games_a'] ?? 0);
                $gamesB = (int) ($matchScore['games_b'] ?? 0);

                if ($system['type'] === 'total_of_sets') {
                    if (! empty($matchScore['set_history'])) {
                        $shSetsA = 0;
                        $shSetsB = 0;
                        $shGamesA = 0;
                        $shGamesB = 0;
                        foreach ($matchScore['set_history'] as $sh) {
                            $ga = (int) ($sh['score_a'] ?? 0);
                            $gb = (int) ($sh['score_b'] ?? 0);
                            $shGamesA += $ga;
                            $shGamesB += $gb;
                            if ($ga > $gb) {
                                $shSetsA++;
                            } elseif ($gb > $ga) {
                                $shSetsB++;
                            }
                        }
                        if ($setsA === 0 && $setsB === 0) {
                            $setsA = $shSetsA;
                            $setsB = $shSetsB;
                        }
                        if ($gamesA === 0 && $gamesB === 0) {
                            $gamesA = $shGamesA;
                            $gamesB = $shGamesB;
                        }
                    }
                    if ($setsA === 0 && $setsB === 0 && ($gamesA > 0 || $gamesB > 0)) {
                        $setsA = $gamesA > $gamesB ? 1 : 0;
                        $setsB = $gamesB > $gamesA ? 1 : 0;
                    }

                    $winnerSide = $setsA > $setsB ? 'A' : ($setsB > $setsA ? 'B' : ($gamesA > $gamesB ? 'A' : ($gamesB > $gamesA ? 'B' : ($scoreA > $scoreB ? 'A' : ($scoreB > $scoreA ? 'B' : null)))));
                    $ptsForA = $gamesA > 0 ? $gamesA : ($scoreA > 0 ? $scoreA : $setsA);
                    $ptsForB = $gamesB > 0 ? $gamesB : ($scoreB > 0 ? $scoreB : $setsB);
                } else {
                    $winnerSide = $gamesA > $gamesB ? 'A' : ($gamesB > $gamesA ? 'B' : ($scoreA > $scoreB ? 'A' : ($scoreB > $scoreA ? 'B' : null)));
                    $ptsForA = $gamesA > 0 ? $gamesA : $scoreA;
                    $ptsForB = $gamesB > 0 ? $gamesB : $scoreB;
                }

                $teamA = $m['team_a_names'] ?? ($m['teamA_names'] ?? ($m['team_a'] ?? []));
                $teamB = $m['team_b_names'] ?? ($m['teamB_names'] ?? ($m['team_b'] ?? []));

                if ($isPerTeam) {
                    $teamAObj = $m['team_a'] ?? [];
                    if (is_array($teamAObj) && isset($teamAObj['name'])) {
                        $teamAName = $teamAObj['display_name'] ?? $teamAObj['name'];
                    } else {
                        $teamAName = implode(' & ', is_array($teamA) ? $teamA : [$teamA]);
                        if (empty($teamAName)) {
                            $teamAName = 'Tim a';
                        }
                    }

                    if (isset($stats[$teamAName])) {
                        $stats[$teamAName]['matches']++;
                        $stats[$teamAName]['sets_won'] += $setsA;
                        $stats[$teamAName]['sets_lost'] += $setsB;
                        $stats[$teamAName]['games_won'] += $gamesA;
                        $stats[$teamAName]['games_lost'] += $gamesB;
                        $stats[$teamAName]['points_for'] += $ptsForA;
                        $stats[$teamAName]['points_against'] += $ptsForB;

                        if ($winnerSide === 'A') {
                            $stats[$teamAName]['wins']++;
                        } elseif ($winnerSide === 'B') {
                            $stats[$teamAName]['losses']++;
                        }
                    }

                    $teamBObj = $m['team_b'] ?? [];
                    if (is_array($teamBObj) && isset($teamBObj['name'])) {
                        $teamBName = $teamBObj['display_name'] ?? $teamBObj['name'];
                    } else {
                        $teamBName = implode(' & ', is_array($teamB) ? $teamB : [$teamB]);
                        if (empty($teamBName)) {
                            $teamBName = 'Tim b';
                        }
                    }

                    if (isset($stats[$teamBName])) {
                        $stats[$teamBName]['matches']++;
                        $stats[$teamBName]['sets_won'] += $setsB;
                        $stats[$teamBName]['sets_lost'] += $setsA;
                        $stats[$teamBName]['games_won'] += $gamesB;
                        $stats[$teamBName]['games_lost'] += $gamesA;
                        $stats[$teamBName]['points_for'] += $ptsForB;
                        $stats[$teamBName]['points_against'] += $ptsForA;

                        if ($winnerSide === 'B') {
                            $stats[$teamBName]['wins']++;
                        } elseif ($winnerSide === 'A') {
                            $stats[$teamBName]['losses']++;
                        }
                    }
                } else {
                    foreach ($teamA as $playerItem) {
                        $playerName = is_array($playerItem) ? ($playerItem['name'] ?? $playerItem['nama'] ?? '') : (is_object($playerItem) ? ($playerItem->name ?? $playerItem->nama ?? '') : (string) $playerItem);
                        $cleanName = self::cleanPlayerName($playerName);
                        if (! isset($stats[$cleanName])) {
                            continue;
                        }

                        $stats[$cleanName]['matches']++;
                        $stats[$cleanName]['sets_won'] += $setsA;
                        $stats[$cleanName]['sets_lost'] += $setsB;
                        $stats[$cleanName]['games_won'] += $gamesA;
                        $stats[$cleanName]['games_lost'] += $gamesB;
                        $stats[$cleanName]['points_for'] += $ptsForA;
                        $stats[$cleanName]['points_against'] += $ptsForB;

                        if ($winnerSide === 'A') {
                            $stats[$cleanName]['wins']++;
                        } elseif ($winnerSide === 'B') {
                            $stats[$cleanName]['losses']++;
                        }
                    }

                    foreach ($teamB as $playerItem) {
                        $playerName = is_array($playerItem) ? ($playerItem['name'] ?? $playerItem['nama'] ?? '') : (is_object($playerItem) ? ($playerItem->name ?? $playerItem->nama ?? '') : (string) $playerItem);
                        $cleanName = self::cleanPlayerName($playerName);
                        if (! isset($stats[$cleanName])) {
                            continue;
                        }

                        $stats[$cleanName]['matches']++;
                        $stats[$cleanName]['sets_won'] += $setsB;
                        $stats[$cleanName]['sets_lost'] += $setsA;
                        $stats[$cleanName]['games_won'] += $gamesB;
                        $stats[$cleanName]['games_lost'] += $gamesA;
                        $stats[$cleanName]['points_for'] += $ptsForB;
                        $stats[$cleanName]['points_against'] += $ptsForA;

                        if ($winnerSide === 'B') {
                            $stats[$cleanName]['wins']++;
                        } elseif ($winnerSide === 'A') {
                            $stats[$cleanName]['losses']++;
                        }
                    }
                }
            }
        }

        // Hitung selisih
        foreach ($stats as &$s) {
            $s['point_diff'] = $s['points_for'] - $s['points_against'];
            $s['game_diff'] = $s['games_won'] - $s['games_lost'];
            $s['set_diff'] = $s['sets_won'] - $s['sets_lost'];
        }

        // Sort: Di Americano prioritaskan akumulasi Total Poin/Games, lalu selisih poin, lalu kemenangan, lalu selisih game, lalu abjad (deterministic tie-break)
        $ranked = array_values($stats);
        usort($ranked, function ($a, $b) {
            if ($b['points_for'] !== $a['points_for']) {
                return $b['points_for'] - $a['points_for'];
            }
            if ($b['point_diff'] !== $a['point_diff']) {
                return $b['point_diff'] - $a['point_diff'];
            }
            if ($b['wins'] !== $a['wins']) {
                return $b['wins'] - $a['wins'];
            }
            if ($b['games_won'] !== $a['games_won']) {
                return $b['games_won'] - $a['games_won'];
            }
            if ($b['game_diff'] !== $a['game_diff']) {
                return $b['game_diff'] - $a['game_diff'];
            }

            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        // Tambahkan rank & medal
        foreach ($ranked as $i => &$player) {
            $player['rank'] = $i + 1;
            $player['medal'] = match ($i) {
                0 => ['emoji' => '🥇', 'label' => 'Juara 1', 'color' => 'amber'],
                1 => ['emoji' => '🥈', 'label' => 'Juara 2', 'color' => 'slate'],
                2 => ['emoji' => '🥉', 'label' => 'Juara 3', 'color' => 'orange'],
                default => ['emoji' => '', 'label' => "Rank #{$player['rank']}", 'color' => 'gray'],
            };
        }

        return $ranked;
    }

    /**
     * Bersihkan nama pemain dari suffix "(Host)", "(Guest)", dll.
     */
    public static function cleanPlayerName(string $name): string
    {
        return trim(preg_replace('/\s*\([^)]+\)\s*/', '', $name));
    }

    /**
     * Hasilkan skor dummy yang deterministik berdasarkan nama round.
     */
    public static function generateDummyScore(string $roundKey, string $scoringSystem): array
    {
        $system = self::detectScoringSystem($scoringSystem);

        $seed = crc32($roundKey);
        srand($seed);

        $flip = $seed % 2 === 0;

        if ($system['type'] === 'total_of_sets' || ($system['is_total_of'] ?? false)) {
            $targetTotal = (int) ($system['target_total_games'] ?? ($system['target_games'] ?? 3));
            $winnerGames = rand((int) ceil($targetTotal / 2), $targetTotal);
            $loserGames = max(0, $targetTotal - $winnerGames);
            $gA = $flip ? $winnerGames : $loserGames;
            $gB = $flip ? $loserGames : $winnerGames;

            srand();

            return [
                'score_a' => $gA,
                'score_b' => $gB,
                'sets_a' => ($gA > $gB) ? 1 : 0,
                'sets_b' => ($gB > $gA) ? 1 : 0,
                'games_a' => $gA,
                'games_b' => $gB,
                'set_history' => [
                    ['set' => 1, 'score_a' => $gA, 'score_b' => $gB],
                ],
            ];
        }

        // First to games
        $target = $system['target_games'] ?? 8;
        $winnerGames = $target;
        $loserGames = rand((int) max(1, $target * 0.5), $target - 1);

        srand();
        $gamesA = $flip ? $winnerGames : $loserGames;
        $gamesB = $flip ? $loserGames : $winnerGames;

        return [
            'score_a' => $gamesA,
            'score_b' => $gamesB,
            'sets_a' => $gamesA > $gamesB ? 1 : 0,
            'sets_b' => $gamesB > $gamesA ? 1 : 0,
            'games_a' => $gamesA,
            'games_b' => $gamesB,
            'set_history' => [],
        ];
    }

    /**
     * Ambil konteks round aktif.
     */
    public static function buildMatchContext(array $game, string $activeRound = 'round_1', int $courtIndex = 0): array
    {
        $drawing = $game['drawing'] ?? [];

        if (empty($drawing) && ! empty($game['participants'])) {
            $names = array_map(fn ($p) => self::cleanPlayerName($p['name'] ?? ''), $game['participants']);
            $half = (int) ceil(count($names) / 2);
            $drawing = [
                'round_1' => [
                    'team_a' => array_slice($names, 0, min(2, $half)),
                    'team_b' => array_slice($names, min(2, $half), 2),
                    'resting' => array_slice($names, 4),
                    'matches' => [
                        [
                            'court' => 1,
                            'court_name' => 'Court 1',
                            'team_a_names' => array_slice($names, 0, min(2, $half)),
                            'team_b_names' => array_slice($names, min(2, $half), 2),
                        ],
                    ],
                ],
            ];
        }

        $round = $drawing[$activeRound]
            ?? ($drawing['round_1']
            ?? ($drawing[1]
            ?? (reset($drawing) ?: null)));

        if (! $round) {
            return [
                'team_a' => [],
                'team_b' => [],
                'team_a_display' => 'Team A',
                'team_b_display' => 'Team B',
                'resting' => [],
                'matches' => [],
                'active_match' => null,
                'court_index' => 0,
                'court_name' => 'Court 1',
                'active_round' => $activeRound,
                'total_rounds' => 0,
                'all_rounds' => ['round_1'],
            ];
        }

        $matches = $round['matches'] ?? [];
        if (empty($matches)) {
            $matches = [
                [
                    'court' => 1,
                    'court_name' => 'Court 1',
                    'team_a_names' => $round['team_a'] ?? [],
                    'team_b_names' => $round['team_b'] ?? [],
                    'team_a' => $round['team_a'] ?? [],
                    'team_b' => $round['team_b'] ?? [],
                ],
            ];
        }

        $rawCourtNames = array_map(fn ($m) => is_array($m) ? trim((string) ($m['court_name'] ?? '')) : '', $matches);
        $nonEmptyCourtNames = array_values(array_filter($rawCourtNames));
        $hasDuplicateCourtNames = count($nonEmptyCourtNames) !== count(array_unique($nonEmptyCourtNames));

        $normalizedMatches = [];
        foreach ($matches as $mIdx => $m) {
            $cNum = $mIdx + 1;
            if ($hasDuplicateCourtNames || empty($m['court_name'])) {
                $m['court_name'] = 'Court '.$cNum;
            }
            $m['court'] = $cNum;
            $m['court_number'] = $cNum;

            $teamARaw = $m['team_a_names'] ?? ($m['teamA_names'] ?? ($m['team_a']['player_names'] ?? ($m['team_a'] ?? [])));
            $teamBRaw = $m['team_b_names'] ?? ($m['teamB_names'] ?? ($m['team_b']['player_names'] ?? ($m['team_b'] ?? [])));

            if (! is_array($teamARaw)) {
                $teamARaw = [$teamARaw];
            }
            if (! is_array($teamBRaw)) {
                $teamBRaw = [$teamBRaw];
            }

            $mTeamAPlayers = array_values(array_filter(array_map(
                fn ($p) => is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (is_object($p) ? ($p->name ?? $p->nama ?? '') : (string) $p),
                $teamARaw
            )));
            $mTeamBPlayers = array_values(array_filter(array_map(
                fn ($p) => is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (is_object($p) ? ($p->name ?? $p->nama ?? '') : (string) $p),
                $teamBRaw
            )));

            $mTeamADisplay = $m['team_a_name'] ?? ($m['team_a']['display_name'] ?? ($m['team_a']['name'] ?? ($m['teamA_display'] ?? (count($matches) > 1 ? 'Court '.($mIdx + 1).' - Team A' : 'TEAM A'))));
            $mTeamBDisplay = $m['team_b_name'] ?? ($m['team_b']['display_name'] ?? ($m['team_b']['name'] ?? ($m['teamB_display'] ?? (count($matches) > 1 ? 'Court '.($mIdx + 1).' - Team B' : 'TEAM B'))));

            $m['team_a_names'] = $mTeamAPlayers;
            $m['team_b_names'] = $mTeamBPlayers;
            $m['team_a_name'] = $mTeamADisplay;
            $m['team_b_name'] = $mTeamBDisplay;

            $normalizedMatches[] = $m;
        }

        $targetMatch = $normalizedMatches[$courtIndex] ?? ($normalizedMatches[0] ?? []);
        $teamAPlayers = $targetMatch['team_a_names'] ?? [];
        $teamBPlayers = $targetMatch['team_b_names'] ?? [];
        $teamADisplay = $targetMatch['team_a_name'] ?? 'TEAM A';
        $teamBDisplay = $targetMatch['team_b_name'] ?? 'TEAM B';

        return [
            'team_a' => $teamAPlayers,
            'team_b' => $teamBPlayers,
            'team_a_display' => $teamADisplay,
            'team_b_display' => $teamBDisplay,
            'resting' => $round['resting'] ?? [],
            'matches' => $normalizedMatches,
            'active_match' => $targetMatch,
            'court_index' => $courtIndex,
            'court_name' => $targetMatch['court_name'] ?? ('Court '.($courtIndex + 1)),
            'court_count' => count($normalizedMatches),
            'active_round' => $activeRound,
            'total_rounds' => count($drawing),
            'all_rounds' => array_keys($drawing),
        ];
    }

    /**
     * Pastikan record tb_match dan tb_match_participant ada untuk round dan court tertentu.
     * Menggunakan record yang sudah ada (REUSE) untuk mencegah duplikasi tb_match.
     * Satu match pada satu round + court hanya memiliki satu match_id unik.
     */
    public static function ensureMatchAndParticipants($session, string $round, int $courtIndex = 0, ?array $matchContext = null): ?GameMatch
    {
        try {
            if (! $session instanceof SessionModel) {
                $session = SessionModel::with(['courts', 'players'])->find((int) $session);
            }
            if (! $session) {
                return null;
            }

            $drawing = Drawing::firstOrCreate(
                ['session_id' => $session->session_id],
                [
                    'match_format_id' => str_contains(strtolower($session->match_format ?? ''), 'team') ? 4 : 1,
                    'tanggal_drawing' => now()->toDateString(),
                    'jam_drawing' => now()->format('H:i:s'),
                ]
            );

            preg_match('/(\d+)/', $round, $roundNumMatch);
            $rNum = isset($roundNumMatch[1]) ? (int) $roundNumMatch[1] : 1;

            $sessionCourts = $session->courts;
            $courtCount = max(1, $sessionCourts->count());
            $court = $sessionCourts->get($courtIndex) ?? $sessionCourts->first();
            $courtId = $court?->court_id;

            // Nomor match deterministik dan unik per round + court:
            // Round 1 Court 1 => 1, Round 1 Court 2 => 2, Round 2 Court 1 => 3, dst.
            $nomorMatch = ($rNum - 1) * $courtCount + ($courtIndex + 1);

            $match = GameMatch::where('drawing_id', $drawing->drawing_id)
                ->where('nomor_match', $nomorMatch)
                ->first();

            if (! $match) {
                $match = GameMatch::create([
                    'drawing_id' => $drawing->drawing_id,
                    'court_id' => $courtId,
                    'nomor_match' => $nomorMatch,
                    'status_match' => 'In Progress',
                    'waktu_mulai' => now(),
                ]);
            } elseif ($courtId && ! $match->court_id) {
                $match->court_id = $courtId;
                $match->save();
            }

            // Daftarkan participant jika belum terdaftar untuk match_id ini
            if (! MatchParticipant::where('match_id', $match->match_id)->exists()) {
                if (! $matchContext) {
                    $participants = $session->players->map(fn ($p) => [
                        'id' => $p->player_id,
                        'name' => $p->nama,
                        'gender' => $p->gender ?? 'Male',
                        'level' => $p->level ?? 'Intermediate',
                    ])->toArray();

                    $cachedSchedule = Cache::get("drawing.schedule_{$session->session_id}");
                    if ($cachedSchedule && ! empty($cachedSchedule['rounds'])) {
                        $rounds = $cachedSchedule['rounds'];
                    } else {
                        $sessionJenisPermainan = $session->jenis_permainan ?? 'Double';
                        $format = strtolower($session->match_format ?? 'americano');
                        $scoringSystem = self::detectScoringSystem($session->scoring_system ?? 'Total of 3');
                        $isTeamFormat = str_contains($format, 'team');
                        $isSingleMode = strtolower($sessionJenisPermainan) === 'single';
                        $roundCount = self::determineSessionRoundCount(
                            $scoringSystem,
                            count($participants),
                            $isTeamFormat,
                            $isSingleMode,
                            $session->waktu_session,
                            $courtCount
                        );
                        if (str_contains($format, 'team') && count($participants) >= 4 && count($participants) % 2 === 0) {
                            $teamService = new TeamAmericanoService;
                            $drawingData = $teamService->generateTeamRounds($participants, $courtCount, null, $roundCount);
                            $rounds = $drawingData['rounds'] ?? [];
                        } else {
                            $americanoService = new AmericanoService;
                            $rounds = $americanoService->generateRounds($participants, $courtCount, $roundCount, $sessionJenisPermainan);
                        }
                    }

                    $drawingMap = [];
                    foreach ($rounds as $rN => $rD) {
                        $drawingMap["round_{$rN}"] = [
                            'round_number' => $rN,
                            'team_a' => $rD['teamA'] ?? ($rD['team_a'] ?? []),
                            'team_b' => $rD['teamB'] ?? ($rD['team_b'] ?? []),
                            'team_a_names' => $rD['teamA_names'] ?? ($rD['team_a_names'] ?? []),
                            'team_b_names' => $rD['teamB_names'] ?? ($rD['team_b_names'] ?? []),
                            'resting' => $rD['resting'] ?? [],
                            'matches' => $rD['matches'] ?? [],
                            'court_count' => $courtCount,
                        ];
                    }

                    $game = [
                        'id' => $session->session_id,
                        'scoring_system' => $session->scoring_system ?? 'Total of 3',
                        'drawing' => $drawingMap,
                        'participants' => $participants,
                    ];
                    $matchContext = self::buildMatchContext($game, $round, $courtIndex);
                }

                $targetMatch = $matchContext['matches'][$courtIndex] ?? ($matchContext['matches'][0] ?? null);
                if ($targetMatch) {
                    $extractPlayers = function ($match, $side) {
                        $team = $match["team_{$side}"] ?? [];
                        if (isset($team['players']) && is_array($team['players'])) {
                            return $team['players'];
                        }
                        if (isset($match["team_{$side}_names"]) && is_array($match["team_{$side}_names"])) {
                            return $match["team_{$side}_names"];
                        }
                        $sideUpper = strtoupper($side);
                        if (isset($match["team{$sideUpper}_names"]) && is_array($match["team{$sideUpper}_names"])) {
                            return $match["team{$sideUpper}_names"];
                        }

                        return is_array($team) ? $team : [];
                    };

                    $teamAPlayers = $extractPlayers($targetMatch, 'a');
                    $teamBPlayers = $extractPlayers($targetMatch, 'b');

                    $sessionPlayerMapById = $session->players->keyBy('player_id');
                    $sessionPlayerMapByName = $session->players->keyBy(fn ($p) => self::cleanPlayerName($p->nama));

                    $resolvePlayer = function ($pItem) use ($sessionPlayerMapById, $sessionPlayerMapByName) {
                        if (is_array($pItem) && ! empty($pItem['id'])) {
                            $found = $sessionPlayerMapById->get($pItem['id']);
                            if ($found) {
                                return $found;
                            }
                        }
                        if (is_array($pItem) && ! empty($pItem['player_id'])) {
                            $found = $sessionPlayerMapById->get($pItem['player_id']);
                            if ($found) {
                                return $found;
                            }
                        }
                        $pName = is_array($pItem) ? ($pItem['name'] ?? $pItem['nama'] ?? '') : (is_object($pItem) ? ($pItem->name ?? $pItem->nama ?? '') : (string) $pItem);

                        return $sessionPlayerMapByName->get(self::cleanPlayerName($pName));
                    };

                    foreach ($teamAPlayers as $pItem) {
                        $player = $resolvePlayer($pItem);
                        if ($player) {
                            MatchParticipant::firstOrCreate(
                                ['match_id' => $match->match_id, 'player_id' => $player->player_id],
                                ['side' => 'A']
                            );
                        }
                    }

                    foreach ($teamBPlayers as $pItem) {
                        $player = $resolvePlayer($pItem);
                        if ($player) {
                            MatchParticipant::firstOrCreate(
                                ['match_id' => $match->match_id, 'player_id' => $player->player_id],
                                ['side' => 'B']
                            );
                        }
                    }
                }
            }

            return $match;
        } catch (\Throwable $e) {
            Log::warning("Failed to ensure match and participants: {$e->getMessage()}");

            return null;
        }
    }
}
