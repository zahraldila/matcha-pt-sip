<?php

namespace App\Services\Scoring;

use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\MatchParticipant;
use App\Models\Player;
use App\Models\Score;
use App\Models\SessionModel;
use App\Services\Drawing\AmericanoService;
use App\Services\Drawing\TeamAmericanoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ScoringService
{
    /**
     * Helper bersama: cek apakah status pertandingan terminal (memenuhi syarat progres ronde).
     * Status yang didukung aplikasi: 'completed', 'finished', 'stopped'.
     */
    public static function isMatchTerminal(?string $status): bool
    {
        $normalized = strtolower(trim((string) $status));

        return in_array($normalized, ['completed', 'finished', 'stopped'], true);
    }

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
                'target_games' => 6,
                'target_total_games' => 6,
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
            'target_games' => 6,
            'target_total_games' => 6,
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
     * Aturan Total Of (Sets):
     * - Total Of N menentukan jumlah set/ronde yang dimainkan (seluruh N ronde dimainkan tanpa early stopping).
     * - Batas skor besar setiap set:
     *   * Normal: sisi pertama mencapai 6 game menang, selama lawan maksimal 4 (6-0..6-4 atau kebalikannya).
     *   * Jika mencapai 5–5, lanjut hingga salah satu sisi mencapai 7 (7-5, 7-6 atau kebalikannya; tidak wajib selisih dua).
     *   * 5–5, 6–5, 5–6, dan 6–6 masih aktif (belum selesai).
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
            // Normal: sisi pertama mencapai 6 game menang, selama lawan maksimal 4
            if (($gamesA === 6 && $gamesB <= 4) || ($gamesB === 6 && $gamesA <= 4)) {
                return true;
            }

            // Jika mencapai 5–5, lanjut hingga salah satu sisi mencapai 7 (7–5 atau 7–6)
            if (($gamesA === 7 && ($gamesB === 5 || $gamesB === 6)) || ($gamesB === 7 && ($gamesA === 5 || $gamesA === 6))) {
                return true;
            }

            // Toleransi jika skor terisi > 7
            if (($gamesA > 7 && $gamesA > $gamesB) || ($gamesB > 7 && $gamesB > $gamesA)) {
                return true;
            }

            return false;
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
     * Total Of:
     * - Menggunakan isMatchCompleted() untuk memastikan set benar-benar selesai.
     * - Jika games_a > games_b: 'Team A'
     * - Jika games_b > games_a: 'Team B'
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
            if (! self::isMatchCompleted($gamesA, $gamesB, $scoringSystem)) {
                return null;
            }

            if ($gamesA > $gamesB) {
                return 'Team A';
            }
            if ($gamesB > $gamesA) {
                return 'Team B';
            }

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
     * Dapatkan baris skor otoritatif untuk setiap set pada match.
     * Mengeliminasi EventLog atau duplikasi snapshot progres, memprioritaskan
     * status Final/Completed dan versi tertinggi.
     */
    public static function getAuthoritativeScoresForMatch($scores): Collection
    {
        if ($scores instanceof \Illuminate\Database\Eloquent\Collection || $scores instanceof Collection) {
            $scoresColl = $scores;
        } else {
            $scoresColl = collect($scores);
        }

        if ($scoresColl->isEmpty()) {
            return collect();
        }

        // Kelompokkan per nomor set
        return $scoresColl->groupBy(function ($sc) {
            return (int) ($sc->set_number ?? 1);
        })->map(function ($setScores) {
            // 1. Prioritaskan baris non-EventLog
            $nonEventLogs = $setScores->filter(function ($sc) {
                return strtolower($sc->status_score ?? '') !== 'eventlog';
            });

            $candidates = $nonEventLogs->isNotEmpty() ? $nonEventLogs : $setScores;

            // 2. Prioritaskan status 'Final' atau 'Completed'
            $finals = $candidates->filter(function ($sc) {
                $st = strtolower($sc->status_score ?? '');

                return in_array($st, ['final', 'completed']);
            });

            $pool = $finals->isNotEmpty() ? $finals : $candidates;

            // 3. Urutkan berdasarkan version desc, lalu score_id desc
            return $pool->sort(function ($a, $b) {
                $vA = (int) ($a->version ?? 0);
                $vB = (int) ($b->version ?? 0);
                if ($vA !== $vB) {
                    return $vB <=> $vA;
                }
                $idA = (int) ($a->score_id ?? ($a->id ?? 0));
                $idB = (int) ($b->score_id ?? ($b->id ?? 0));

                return $idB <=> $idA;
            })->first();
        })->sortBy(function ($sc) {
            return (int) ($sc->set_number ?? 1);
        })->values();
    }

    /**
     * Sanitasi set_history agar hanya menyimpan satu hasil final per nomor set.
     * Menghilangkan duplikasi set berulang dari akumulasi snapshot progres.
     */
    public static function sanitizeSetHistory(array $setHistory): array
    {
        if (empty($setHistory)) {
            return [];
        }

        $bySet = [];
        foreach ($setHistory as $item) {
            if (! is_array($item)) {
                continue;
            }
            $setNum = (int) ($item['set'] ?? ($item['set_number'] ?? 1));
            $ga = (int) ($item['score_a'] ?? ($item['score_side_a'] ?? ($item['game_score_a'] ?? 0)));
            $gb = (int) ($item['score_b'] ?? ($item['score_side_b'] ?? ($item['game_score_b'] ?? 0)));

            if (! isset($bySet[$setNum])) {
                $bySet[$setNum] = [
                    'set' => $setNum,
                    'score_a' => $ga,
                    'score_b' => $gb,
                ];
            } else {
                // Jika sudah ada kandidat untuk set ini, prioritaskan skor dengan total game lebih besar
                // (karena snapshot progres biasanya 0-0, 0-1, lalu final 2-1)
                $currentTotal = $bySet[$setNum]['score_a'] + $bySet[$setNum]['score_b'];
                $newTotal = $ga + $gb;
                if ($newTotal >= $currentTotal) {
                    $bySet[$setNum] = [
                        'set' => $setNum,
                        'score_a' => $ga,
                        'score_b' => $gb,
                    ];
                }
            }
        }

        ksort($bySet);

        return array_values($bySet);
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

                $isStoppedMatch = isset($effective[$matchKey]) && (($effective[$matchKey]['status'] ?? '') === 'stopped');

                if ($isStoppedMatch) {
                    $effective[$matchKey]['status'] = 'stopped';
                    if (empty($effective[$matchKey]['team_a'])) {
                        $effective[$matchKey]['team_a'] = $matches[$i]['team_a'] ?? ($round['team_a'] ?? []);
                    }
                    if (empty($effective[$matchKey]['team_b'])) {
                        $effective[$matchKey]['team_b'] = $matches[$i]['team_b'] ?? ($round['team_b'] ?? []);
                    }
                    $effective[$matchKey]['round_title'] = ucfirst(str_replace('_', ' ', $roundKey));
                    $effective[$matchKey]['scoring_type'] = $system['type'];

                    continue;
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
                        $eHist = self::sanitizeSetHistory($effective[$matchKey]['set_history'] ?? []);

                        if (! empty($eHist)) {
                            $calcGamesA = 0;
                            $calcGamesB = 0;
                            $calcSetsA = 0;
                            $calcSetsB = 0;
                            foreach ($eHist as $set) {
                                $ga = (int) ($set['score_a'] ?? 0);
                                $gb = (int) ($set['score_b'] ?? 0);
                                $calcGamesA += $ga;
                                $calcGamesB += $gb;
                                if ($ga > $gb) {
                                    $calcSetsA++;
                                } elseif ($gb > $ga) {
                                    $calcSetsB++;
                                }
                            }

                            // Hindari inflasi games_a/b dari akumulasi snapshot progres
                            if (count($eHist) === 1 || ($eGamesA === 0 && $eGamesB === 0) || ($eGamesA > $calcGamesA || $eGamesB > $calcGamesB)) {
                                $eGamesA = $calcGamesA;
                                $eGamesB = $calcGamesB;
                            }

                            if ($eSetsA === 0 && $eSetsB === 0) {
                                $eSetsA = $calcSetsA;
                                $eSetsB = $calcSetsB;
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
        $teamAvatarFor = static function (array $teamMembers) use ($players): ?string {
            foreach ($teamMembers as $teamMember) {
                $memberName = is_array($teamMember)
                    ? ($teamMember['name'] ?? $teamMember['nama'] ?? '')
                    : (is_object($teamMember) ? ($teamMember->name ?? $teamMember->nama ?? '') : $teamMember);
                $cleanMemberName = self::cleanPlayerName((string) $memberName);
                if ($cleanMemberName === '') {
                    continue;
                }

                foreach ($players as $player) {
                    if (self::cleanPlayerName($player['name'] ?? '') === $cleanMemberName && ! empty($player['avatar'])) {
                        return $player['avatar'];
                    }
                }
            }

            return null;
        };

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
                                $teamMembers = $m["team_{$side}_names"] ?? ($m['team'.strtoupper($side).'_names'] ?? ($team['player_names'] ?? ($team['players'] ?? [])));
                            } else {
                                $mTeam = $m["team_{$side}_names"] ?? ($m['team'.strtoupper($side).'_names'] ?? $team);
                                $name = implode(' & ', is_array($mTeam) ? $mTeam : [$mTeam]);
                                if (empty($name)) {
                                    $name = "Tim {$side}";
                                }
                                $teamIdentity = $name;
                                $teamMembers = $mTeam;
                            }
                            if (! isset($stats[$name])) {
                                $stats[$name] = [
                                    'name' => $name,
                                    'team_identity' => $teamIdentity,
                                    'avatar' => $teamAvatarFor(is_array($teamMembers) ? $teamMembers : [$teamMembers]),
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
                $isStopped = $status === 'stopped';

                // Skor parsial stopped tetap masuk akumulasi; status ini tidak menentukan pemenang.
                if ($status !== 'completed' && ! $isStopped) {
                    continue;
                }

                $scoreA = (int) ($matchScore['score_a'] ?? 0);
                $scoreB = (int) ($matchScore['score_b'] ?? 0);
                $setsA = (int) ($matchScore['sets_a'] ?? 0);
                $setsB = (int) ($matchScore['sets_b'] ?? 0);
                $gamesA = (int) ($matchScore['games_a'] ?? 0);
                $gamesB = (int) ($matchScore['games_b'] ?? 0);

                if ($system['type'] === 'total_of_sets') {
                    $sanitizedHist = self::sanitizeSetHistory($matchScore['set_history'] ?? []);
                    if (! empty($sanitizedHist)) {
                        $shSetsA = 0;
                        $shSetsB = 0;
                        $shGamesA = 0;
                        $shGamesB = 0;
                        foreach ($sanitizedHist as $sh) {
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
                        if (! $isStopped && $setsA === 0 && $setsB === 0) {
                            $setsA = $shSetsA;
                            $setsB = $shSetsB;
                        }
                        // Hindari inflasi games_a/b dari akumulasi snapshot progres
                        if (count($sanitizedHist) === 1 || ($gamesA === 0 && $gamesB === 0) || ($gamesA > $shGamesA || $gamesB > $shGamesB)) {
                            $gamesA = $shGamesA;
                            $gamesB = $shGamesB;
                        }
                    }
                    if (! $isStopped && $setsA === 0 && $setsB === 0 && ($gamesA > 0 || $gamesB > 0)) {
                        $setsA = $gamesA > $gamesB ? 1 : 0;
                        $setsB = $gamesB > $gamesA ? 1 : 0;
                    }

                    $winnerSide = $isStopped ? null : ($setsA > $setsB ? 'A' : ($setsB > $setsA ? 'B' : ($gamesA > $gamesB ? 'A' : ($gamesB > $gamesA ? 'B' : ($scoreA > $scoreB ? 'A' : ($scoreB > $scoreA ? 'B' : null))))));
                    $ptsForA = $gamesA > 0 ? $gamesA : ($scoreA > 0 ? $scoreA : $setsA);
                    $ptsForB = $gamesB > 0 ? $gamesB : ($scoreB > 0 ? $scoreB : $setsB);
                } else {
                    $sanitizedHist = self::sanitizeSetHistory($matchScore['set_history'] ?? []);
                    if (! empty($sanitizedHist)) {
                        $shGamesA = 0;
                        $shGamesB = 0;
                        foreach ($sanitizedHist as $sh) {
                            $shGamesA += (int) ($sh['score_a'] ?? 0);
                            $shGamesB += (int) ($sh['score_b'] ?? 0);
                        }
                        if (count($sanitizedHist) === 1 || ($gamesA === 0 && $gamesB === 0) || ($gamesA > $shGamesA || $gamesB > $shGamesB)) {
                            $gamesA = $shGamesA;
                            $gamesB = $shGamesB;
                        }
                    }
                    $winnerSide = $isStopped ? null : ($gamesA > $gamesB ? 'A' : ($gamesB > $gamesA ? 'B' : ($scoreA > $scoreB ? 'A' : ($scoreB > $scoreA ? 'B' : null))));
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
            $validOutcomes = [
                [6, 0], [6, 1], [6, 2], [6, 3], [6, 4], [7, 5], [7, 6],
            ];
            $choice = $validOutcomes[abs($seed) % count($validOutcomes)];
            $winnerGames = $choice[0];
            $loserGames = $choice[1];
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

            $teamARaw = $m['team_a_names'] ?? ($m['teamA_names'] ?? ($m['team_a']['player_names'] ?? ($m['team_a']['players'] ?? ($m['team_a'] ?? []))));
            $teamBRaw = $m['team_b_names'] ?? ($m['teamB_names'] ?? ($m['team_b']['player_names'] ?? ($m['team_b']['players'] ?? ($m['team_b'] ?? []))));

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

            $drawing = self::getAuthoritativeDrawing($session);
            if (! $drawing) {
                $drawing = Drawing::firstOrCreate(
                    ['session_id' => $session->session_id],
                    [
                        'match_format_id' => str_contains(strtolower($session->match_format ?? ''), 'team') ? 4 : 1,
                        'tanggal_drawing' => now()->toDateString(),
                        'jam_drawing' => now()->format('H:i:s'),
                    ]
                );
            }

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

    /**
     * Tentukan status dan aksesibilitas setiap ronde untuk drawing bertahap.
     * Ronde 1 selalu terbuka (accessible).
     * Ronde N (N > 1) terbuka hanya jika Ronde N - 1 telah tuntas dimainkan (completed).
     *
     * @return array{
     *     access: array<int, bool>,
     *     status: array<int, string>,
     *     active_round_num: int,
     *     completed_count: int,
     *     total_rounds: int
     * }
     */
    public static function resolveRoundsProgress(int $gameId, array $rounds, array $savedScores = [], int $courtCount = 1): array
    {
        $roundAccess = [];
        $roundStatus = [];
        $prevRoundCompleted = true;
        $completedCount = 0;

        $dbMatches = collect();
        if (Schema::hasTable('tb_drawing') && Schema::hasTable('tb_match')) {
            try {
                $drawing = Drawing::where('session_id', $gameId)->first();
                if ($drawing) {
                    $dbMatches = GameMatch::where('drawing_id', $drawing->drawing_id)->get()->keyBy('nomor_match');
                }
            } catch (\Throwable $e) {
                // Fallback to cache without error
            }
        }

        // Normalisasi $rounds agar setiap nomor ronde unik bertipe integer 1..N
        $normalizedRounds = [];
        foreach ($rounds as $k => $rData) {
            if (! is_array($rData)) {
                continue;
            }
            $rNum = (int) ($rData['round'] ?? ($rData['round_number'] ?? (preg_match('/(\d+)/', (string) $k, $m) ? $m[1] : $k)));
            if ($rNum > 0 && ! isset($normalizedRounds[$rNum])) {
                $normalizedRounds[$rNum] = $rData;
            }
        }
        ksort($normalizedRounds);
        $rounds = $normalizedRounds;

        foreach ($rounds as $rIdx => $rData) {
            $rNum = (int) ($rData['round'] ?? ($rData['round_number'] ?? $rIdx));
            $isAccessible = $prevRoundCompleted;
            $roundAccess[$rNum] = $isAccessible;

            $matches = $rData['matches'] ?? [];
            $matchCount = max(1, count($matches));
            $allMatchesDone = true;
            $hasAnyActivity = false;

            for ($mIdx = 0; $mIdx < $matchCount; $mIdx++) {
                $matchKey = $matchCount > 1 ? "round_{$rNum}_court_".($mIdx + 1) : "round_{$rNum}";
                $score = $savedScores[$matchKey] ?? null;
                if (! $score && $matchCount === 1 && isset($savedScores["round_{$rNum}_court_1"])) {
                    $score = $savedScores["round_{$rNum}_court_1"];
                }

                $nomorMatch = ($rNum - 1) * $courtCount + ($mIdx + 1);
                $dbMatch = $dbMatches->get($nomorMatch);

                $isMatchDone = false;
                if ($score && self::isMatchTerminal($score['status'] ?? '')) {
                    $isMatchDone = true;
                    $hasAnyActivity = true;
                } elseif ($dbMatch && self::isMatchTerminal($dbMatch->status_match ?? '')) {
                    $isMatchDone = true;
                    $hasAnyActivity = true;
                } else {
                    $allMatchesDone = false;
                    if ($score && (
                        ($score['status'] ?? '') === 'in_progress' ||
                        ($score['games_a'] ?? 0) > 0 || ($score['games_b'] ?? 0) > 0 ||
                        ($score['score_a'] ?? 0) > 0 || ($score['score_b'] ?? 0) > 0 ||
                        ($score['sets_a'] ?? 0) > 0 || ($score['sets_b'] ?? 0) > 0
                    )) {
                        $hasAnyActivity = true;
                    } elseif ($dbMatch && strtolower($dbMatch->status_match ?? '') === 'in progress') {
                        $hasAnyActivity = true;
                    }
                }
            }

            if ($allMatchesDone) {
                // Pastikan seluruh match wajib pada ronde ini benar-benar terminal (bukan sekadar skor kosong)
                $hasRecordedCompletion = false;
                for ($mIdx = 0; $mIdx < $matchCount; $mIdx++) {
                    $matchKey = $matchCount > 1 ? "round_{$rNum}_court_".($mIdx + 1) : "round_{$rNum}";
                    $score = $savedScores[$matchKey] ?? ($savedScores["round_{$rNum}_court_1"] ?? null);
                    $nomorMatch = ($rNum - 1) * $courtCount + ($mIdx + 1);
                    $dbMatch = $dbMatches->get($nomorMatch);
                    if (($score && self::isMatchTerminal($score['status'] ?? '')) ||
                        ($dbMatch && self::isMatchTerminal($dbMatch->status_match ?? ''))) {
                        $hasRecordedCompletion = true;
                    } else {
                        $hasRecordedCompletion = false;
                        break;
                    }
                }

                if ($hasRecordedCompletion) {
                    $roundStatus[$rNum] = 'completed';
                    $completedCount++;
                    $prevRoundCompleted = true;

                    continue;
                }
            }

            $prevRoundCompleted = false;

            if (! $isAccessible) {
                $roundStatus[$rNum] = 'locked';
            } elseif ($hasAnyActivity) {
                $roundStatus[$rNum] = 'in_progress';
            } else {
                $roundStatus[$rNum] = 'ready';
            }
        }

        // Tentukan ronde aktif default
        $activeRoundNum = 1;
        $metaActive = $savedScores['_meta']['active_round'] ?? null;
        if (! $metaActive && Schema::hasColumn('tb_session', 'active_round')) {
            $metaActive = DB::table('tb_session')->where('session_id', $gameId)->value('active_round');
        }
        if ($metaActive) {
            $parsedNum = (int) preg_replace('/[^0-9]/', '', $metaActive);
            if ($parsedNum > 0 && ($roundAccess[$parsedNum] ?? false)) {
                $activeRoundNum = $parsedNum;
            }
        }

        if ($activeRoundNum === 1 && ($roundStatus[1] ?? '') === 'completed') {
            $lastActiveOrCompleted = 1;
            foreach ($rounds as $rIdx => $rData) {
                $rNum = (int) ($rData['round'] ?? ($rData['round_number'] ?? $rIdx));
                if (($roundStatus[$rNum] ?? '') === 'completed') {
                    $lastActiveOrCompleted = $rNum;
                } elseif (($roundStatus[$rNum] ?? '') === 'in_progress') {
                    $lastActiveOrCompleted = $rNum;
                    break;
                } else {
                    // Ronde berikutnya berstatus 'ready' (sudah boleh diakses, tetapi belum diaktifkan host).
                    // Tetap di ronde sebelumnya agar tombol Lanjut tetap tampil bagi host.
                    break;
                }
            }
            $activeRoundNum = $lastActiveOrCompleted;
        }

        return [
            'access' => $roundAccess,
            'status' => $roundStatus,
            'active_round_num' => $activeRoundNum,
            'completed_count' => $completedCount,
            'total_rounds' => count($rounds),
        ];
    }

    /**
     * Dapatkan daftar ID pemain yang telah keluar/withdrawn dari suatu sesi.
     * Menggabungkan informasi persisten dari tb_session_player, tb_match, dan Cache.
     *
     * @param  SessionModel|int  $session
     * @return array<int>
     */
    public static function getWithdrawnPlayerIds($session): array
    {
        $sessionId = ($session instanceof SessionModel) ? $session->session_id : (int) $session;
        $withdrawnIds = [];

        // 1. Dari basis data tb_session_player jika kolom status tersedia
        try {
            if (Schema::hasTable('tb_session_player') && Schema::hasColumn('tb_session_player', 'status')) {
                $dbIds = DB::table('tb_session_player')
                    ->where('session_id', $sessionId)
                    ->where('status', 'withdrawn')
                    ->pluck('player_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $withdrawnIds = array_merge($withdrawnIds, $dbIds);
            }
        } catch (\Throwable $e) {
            // Ignore DB check error in tests with partial schemas
        }

        // 2. Dari tb_match yang berstatus Stopped dengan withdrawn_player_ids
        try {
            if (Schema::hasTable('tb_match') && Schema::hasTable('tb_drawing') && Schema::hasColumn('tb_match', 'withdrawn_player_ids')) {
                $drawingIds = DB::table('tb_drawing')->where('session_id', $sessionId)->pluck('drawing_id');
                if ($drawingIds->isNotEmpty()) {
                    $query = DB::table('tb_match')
                        ->whereIn('drawing_id', $drawingIds)
                        ->where('status_match', 'Stopped')
                        ->whereNotNull('withdrawn_player_ids');

                    if (Schema::hasColumn('tb_match', 'stop_type')) {
                        $query->where(function ($q) {
                            $q->whereNull('stop_type')
                                ->orWhereNotIn('stop_type', ['temporary_pause', 'pause_players', 'keep_in_session']);
                        });
                    }

                    $matchWithdrawnRaw = $query->pluck('withdrawn_player_ids');

                    foreach ($matchWithdrawnRaw as $raw) {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            foreach ($decoded as $dId) {
                                $withdrawnIds[] = (int) $dId;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        // 3. Dari Cache
        $cacheMeta = Cache::get("scoring.game_{$sessionId}", []);
        $cachedWithdrawn = $cacheMeta['_meta']['withdrawn_players'] ?? [];
        if (is_array($cachedWithdrawn)) {
            foreach ($cachedWithdrawn as $cId) {
                $withdrawnIds[] = (int) $cId;
            }
        }

        // Pemain yang berstatus active atau resting di tb_session_player secara mutlak TIDAK BOLEH dianggap withdrawn
        try {
            if (Schema::hasTable('tb_session_player') && Schema::hasColumn('tb_session_player', 'status')) {
                $activeOrRestingIds = DB::table('tb_session_player')
                    ->where('session_id', $sessionId)
                    ->whereIn('status', ['active', 'resting'])
                    ->pluck('player_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $withdrawnIds = array_values(array_diff($withdrawnIds, $activeOrRestingIds));
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return array_values(array_unique(array_filter($withdrawnIds, fn ($id) => (int) $id > 0)));
    }

    /**
     * Dapatkan daftar ID pemain yang sedang istirahat sementara (paused / bench) dalam sesi.
     *
     * @param  SessionModel|int  $session
     * @return array<int>
     */
    public static function getPausedPlayerIds($session): array
    {
        $sessionId = ($session instanceof SessionModel) ? $session->session_id : (int) $session;
        $pausedIds = [];

        // 1. Dari basis data tb_session_player jika kolom status tersedia
        try {
            if (Schema::hasTable('tb_session_player') && Schema::hasColumn('tb_session_player', 'status')) {
                $dbIds = DB::table('tb_session_player')
                    ->where('session_id', $sessionId)
                    ->where('status', 'resting')
                    ->pluck('player_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $pausedIds = array_merge($pausedIds, $dbIds);
            }
        } catch (\Throwable $e) {
            // Ignore DB check error in tests with partial schemas
        }

        // 2. Dari Cache scoring.game_{sessionId}
        $cacheMeta = Cache::get("scoring.game_{$sessionId}", []);
        $cachedPaused = $cacheMeta['_meta']['paused_players'] ?? [];
        if (is_array($cachedPaused)) {
            foreach ($cachedPaused as $cId) {
                $pausedIds[] = (int) $cId;
            }
        }

        // 3. Dari Cache drawing.schedule_{sessionId}
        $scheduleCache = Cache::get("drawing.schedule_{$sessionId}", []);
        $schedPaused = $scheduleCache['paused_player_ids'] ?? [];
        if (is_array($schedPaused)) {
            foreach ($schedPaused as $sId) {
                $pausedIds[] = (int) $sId;
            }
        }

        // Pemain yang sudah kembali active atau keluar di tb_session_player tidak boleh dianggap resting
        try {
            if (Schema::hasTable('tb_session_player') && Schema::hasColumn('tb_session_player', 'status')) {
                $nonRestingIds = DB::table('tb_session_player')
                    ->where('session_id', $sessionId)
                    ->whereIn('status', ['active', 'withdrawn'])
                    ->pluck('player_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $pausedIds = array_values(array_diff($pausedIds, $nonRestingIds));
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return array_values(array_unique(array_filter($pausedIds, fn ($id) => (int) $id > 0)));
    }

    /**
     * Dapatkan drawing otoritatif sesi (bukan sekadar first() acak).
     * Memprioritaskan drawing Locked atau yang memiliki relasi pertandingan aktif.
     */
    public static function getAuthoritativeDrawing($session): ?Drawing
    {
        $sessionId = ($session instanceof SessionModel) ? $session->session_id : (int) $session;
        if (! Schema::hasTable('tb_drawing')) {
            return null;
        }

        $query = Drawing::where('session_id', $sessionId);

        // 1. Jika ada kolom status_drawing dan terdapat drawing Locked, ambil yang terbaru
        if (Schema::hasColumn('tb_drawing', 'status_drawing')) {
            $locked = (clone $query)->where('status_drawing', 'Locked')->orderByDesc('drawing_id')->first();
            if ($locked) {
                return $locked;
            }
        }

        // 2. Prioritaskan drawing yang memiliki relasi pertandingan di tb_match
        if (Schema::hasTable('tb_match')) {
            $withMatches = (clone $query)
                ->whereHas('matches')
                ->orderByDesc('drawing_id')
                ->first();
            if ($withMatches) {
                return $withMatches;
            }
        }

        // 3. Fallback ke drawing terbaru yang terdaftar untuk sesi ini
        return $query->orderByDesc('drawing_id')->first();
    }

    /**
     * Validasi bahwa match_id terbukti sah dan milik drawing otoritatif dari sesi yang dimaksud.
     */
    public static function validateMatchSession(int $matchId, int $sessionId): ?GameMatch
    {
        if ($matchId <= 0 || ! Schema::hasTable('tb_match') || ! Schema::hasTable('tb_drawing')) {
            return null;
        }

        $authDrawing = self::getAuthoritativeDrawing($sessionId);
        if (! $authDrawing) {
            return null;
        }

        return GameMatch::where('match_id', $matchId)
            ->where('drawing_id', $authDrawing->drawing_id)
            ->first();
    }

    /**
     * Cek apakah pertandingan dilindungi dari penyesuaian jadwal / pergantian peserta:
     * - Status In Progress atau Terminal (Completed / Stopped)
     * - ATAU sudah memiliki rekaman skor di tb_score (poin/game/event)
     */
    public static function isMatchProtectedFromAdjustment(GameMatch $match, ?int $matchRound = null, ?int $lockedRound = null): bool
    {
        $status = strtolower($match->status_match ?? '');
        if (in_array($status, ['completed', 'stopped'])) {
            return true;
        }

        // Match In Progress atau Live dilindungi meskipun skor masih 0-0 dan waktu_mulai kosong
        if (in_array($status, ['in progress', 'in_progress', 'live'])) {
            return true;
        }

        // Match yang memiliki tanda event / waktu mulai aktif dilindungi
        if (! empty($match->waktu_mulai) || ! empty($match->last_event_id)) {
            return true;
        }

        // Match yang pernah memiliki event skor juga tetap dilindungi meskipun skor saat ini kembali 0-0
        if (Schema::hasTable('tb_score')) {
            $hasScoreOrEvent = Score::where('match_id', $match->match_id)
                ->where(function ($q) {
                    $q->where('game_score_a', '>', 0)
                        ->orWhere('game_score_b', '>', 0)
                        ->orWhere('score_side_a', '>', 0)
                        ->orWhere('score_side_b', '>', 0)
                        ->orWhere(function ($sq) {
                            $sq->whereNotNull('point_score_a')
                                ->whereNotIn('point_score_a', ['0', '', '0-0']);
                        })
                        ->orWhere(function ($sq) {
                            $sq->whereNotNull('point_score_b')
                                ->whereNotIn('point_score_b', ['0', '', '0-0']);
                        })
                        ->orWhereNotNull('last_event_id')
                        ->orWhere('status_score', 'EventLog')
                        ->orWhere('version', '>', 1);
                })
                ->exists();
            if ($hasScoreOrEvent) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ambil metadata versi jadwal dan status konfirmasi dari penyimpanan persisten (tb_match.player_notes).
     */
    public static function getPersistentScheduleMeta($session): array
    {
        $sessionId = ($session instanceof SessionModel) ? $session->session_id : (int) $session;

        // 1. Coba baca dari match pertama pada drawing sesi ini di basis data
        if (Schema::hasTable('tb_match') && Schema::hasTable('tb_drawing') && Schema::hasColumn('tb_match', 'player_notes')) {
            try {
                $drawingIds = DB::table('tb_drawing')->where('session_id', $sessionId)->pluck('drawing_id');
                if ($drawingIds->isNotEmpty()) {
                    $firstMatch = GameMatch::whereIn('drawing_id', $drawingIds)->orderBy('nomor_match')->first();
                    if ($firstMatch && ! empty($firstMatch->player_notes)) {
                        $notes = is_array($firstMatch->player_notes) ? $firstMatch->player_notes : json_decode($firstMatch->player_notes, true);
                        if (is_array($notes) && isset($notes['_schedule_meta']) && is_array($notes['_schedule_meta'])) {
                            return $notes['_schedule_meta'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fallback to cache
            }
        }

        // 2. Fallback ke Cache
        $cachedSchedule = Cache::get("drawing.schedule_{$sessionId}", []);
        $version = (int) ($cachedSchedule['schedule_version'] ?? 1);
        $needsAdjustment = (bool) Cache::get("scoring.needs_adjustment_{$sessionId}", $cachedSchedule['needs_schedule_adjustment'] ?? false);
        $confirmedAt = $cachedSchedule['confirmed_at'] ?? null;

        return [
            'version' => max(1, $version),
            'needs_adjustment' => $needsAdjustment,
            'confirmed_at' => $confirmedAt,
        ];
    }

    /**
     * Simpan metadata versi jadwal dan status konfirmasi ke penyimpanan persisten (tb_match.player_notes) dan Cache.
     */
    public static function setPersistentScheduleMeta($session, array $meta): void
    {
        $sessionId = ($session instanceof SessionModel) ? $session->session_id : (int) $session;

        // 1. Simpan ke database persisten tb_match
        if (Schema::hasTable('tb_match') && Schema::hasTable('tb_drawing') && Schema::hasColumn('tb_match', 'player_notes')) {
            try {
                $drawingIds = DB::table('tb_drawing')->where('session_id', $sessionId)->pluck('drawing_id');
                if ($drawingIds->isNotEmpty()) {
                    $firstMatch = GameMatch::whereIn('drawing_id', $drawingIds)->orderBy('nomor_match')->first();
                    if ($firstMatch) {
                        $notes = [];
                        if (! empty($firstMatch->player_notes)) {
                            $decoded = is_array($firstMatch->player_notes) ? $firstMatch->player_notes : json_decode($firstMatch->player_notes, true);
                            $notes = is_array($decoded) ? $decoded : [];
                        }
                        $notes['_schedule_meta'] = array_merge($notes['_schedule_meta'] ?? [], $meta);
                        $firstMatch->player_notes = json_encode($notes);
                        $firstMatch->save();
                    }
                }
            } catch (\Throwable $e) {
                // Ignore DB write errors
            }
        }

        // 2. Sinkronkan ke Cache
        $cachedSchedule = Cache::get("drawing.schedule_{$sessionId}", []);
        if (isset($meta['version'])) {
            $cachedSchedule['schedule_version'] = (int) $meta['version'];
        }
        if (isset($meta['needs_adjustment'])) {
            $cachedSchedule['needs_schedule_adjustment'] = (bool) $meta['needs_adjustment'];
            if ($meta['needs_adjustment']) {
                Cache::put("scoring.needs_adjustment_{$sessionId}", true, now()->addHours(6));
            } else {
                Cache::forget("scoring.needs_adjustment_{$sessionId}");
            }
        }
        if (isset($meta['confirmed_at'])) {
            $cachedSchedule['confirmed_at'] = $meta['confirmed_at'];
        }
        Cache::put("drawing.schedule_{$sessionId}", $cachedSchedule, now()->addHours(12));
    }

    /**
     * Ekstrak snapshot historis per ronde dari player_notes tb_match jika tersedia.
     */
    public static function extractRoundSnapshot(?GameMatch $match): ?array
    {
        if (! $match || empty($match->player_notes)) {
            return null;
        }

        $decoded = is_array($match->player_notes) ? $match->player_notes : json_decode($match->player_notes, true);
        if (! is_array($decoded)) {
            return null;
        }

        if (isset($decoded['round_snapshot']) && is_array($decoded['round_snapshot'])) {
            return $decoded['round_snapshot'];
        }

        return null;
    }

    /**
     * Simpan snapshot historis per ronde ke player_notes tb_match secara persisten.
     */
    public static function saveRoundSnapshotToMatch(GameMatch $match, array $snapshot): void
    {
        if (! Schema::hasColumn('tb_match', 'player_notes')) {
            return;
        }

        $existing = [];
        if (! empty($match->player_notes)) {
            $decoded = is_array($match->player_notes) ? $match->player_notes : json_decode($match->player_notes, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $existing['round_snapshot'] = $snapshot;
        $match->player_notes = json_encode($existing);
        $match->save();
    }

    /**
     * Periksa apakah status pemain telah berubah namun jadwal penyesuaian belum dikonfirmasi host.
     */
    public static function sessionNeedsScheduleAdjustment($session): bool
    {
        $sessionId = ($session instanceof SessionModel) ? $session->session_id : (int) $session;
        $persistent = self::getPersistentScheduleMeta($sessionId);

        return (bool) ($persistent['needs_adjustment'] ?? Cache::get("scoring.needs_adjustment_{$sessionId}", false));
    }

    /**
     * Set status kebutuhan konfirmasi penyesuaian jadwal.
     */
    public static function setSessionNeedsScheduleAdjustment($session, bool $needed = true): void
    {
        $sessionId = ($session instanceof SessionModel) ? $session->session_id : (int) $session;
        if ($needed) {
            Cache::put("scoring.needs_adjustment_{$sessionId}", true, now()->addHours(6));
        } else {
            Cache::forget("scoring.needs_adjustment_{$sessionId}");
        }

        // Persist ke database tb_match jika tersedia
        self::setPersistentScheduleMeta($sessionId, ['needs_adjustment' => $needed]);
    }

    /**
     * Periksa apakah sesi pernah mengalami penghentian pertandingan atau penyesuaian jadwal.
     */
    public static function hasScheduleBeenAdjusted($session): bool
    {
        $sessionId = ($session instanceof SessionModel) ? $session->session_id : (int) $session;

        // 1. Cek dari cache
        $cachedSchedule = Cache::get("drawing.schedule_{$sessionId}", []);
        if (! empty($cachedSchedule['is_adjusted']) || ! empty($cachedSchedule['last_adjusted_at'])) {
            return true;
        }

        // 2. Cek dari database tb_match (apakah ada match yang dihentikan)
        try {
            if (Schema::hasTable('tb_match') && Schema::hasTable('tb_drawing')) {
                $drawingIds = DB::table('tb_drawing')->where('session_id', $sessionId)->pluck('drawing_id');
                if ($drawingIds->isNotEmpty()) {
                    $hasStoppedMatch = DB::table('tb_match')
                        ->whereIn('drawing_id', $drawingIds)
                        ->where(function ($q) {
                            $q->where('status_match', 'Stopped')
                                ->orWhereNotNull('stop_type');
                        })
                        ->exists();

                    if ($hasStoppedMatch) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        // 3. Cek tb_session_player (apakah ada pemain resting atau withdrawn)
        try {
            if (Schema::hasTable('tb_session_player') && Schema::hasColumn('tb_session_player', 'status')) {
                $hasRestingOrWithdrawn = DB::table('tb_session_player')
                    ->where('session_id', $sessionId)
                    ->whereIn('status', ['resting', 'withdrawn'])
                    ->exists();

                if ($hasRestingOrWithdrawn) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return false;
    }

    /**
     * Sesuaikan jadwal ronde mendatang (r > currentRound) saat ada pemain yang keluar dari sesi.
     * Mempertahankan seluruh ronde lalu dan match yang sudah berjalan (termasuk skor & peserta).
     * Mempertahankan total jumlah ronde (tanpa menambah/mengurangi jumlah ronde).
     */
    public static function adjustFutureScheduleOnWithdrawal(SessionModel $session, array $newlyWithdrawnPlayerIds, int $currentRoundNumber): array
    {
        $allWithdrawnIds = array_values(array_unique(array_merge(
            self::getWithdrawnPlayerIds($session),
            array_map('intval', $newlyWithdrawnPlayerIds)
        )));
        $allPausedIds = self::getPausedPlayerIds($session);

        return self::adjustFutureScheduleCore($session, $allWithdrawnIds, $allPausedIds, $currentRoundNumber);
    }

    /**
     * Sesuaikan jadwal ronde mendatang saat ada pemain yang istirahat sementara (masuk bangku cadangan / toilet).
     */
    public static function adjustFutureScheduleOnPause(SessionModel $session, array $newlyPausedPlayerIds, int $currentRoundNumber): array
    {
        $allWithdrawnIds = self::getWithdrawnPlayerIds($session);
        $allPausedIds = array_values(array_unique(array_merge(
            self::getPausedPlayerIds($session),
            array_map('intval', $newlyPausedPlayerIds)
        )));

        return self::adjustFutureScheduleCore($session, $allWithdrawnIds, $allPausedIds, $currentRoundNumber);
    }

    /**
     * Sesuaikan jadwal ronde mendatang saat pemain yang sebelumnya istirahat sementara telah siap main lagi.
     */
    public static function adjustFutureScheduleOnReactivation(SessionModel $session, int $reactivatedPlayerId, int $currentRoundNumber): array
    {
        $allWithdrawnIds = self::getWithdrawnPlayerIds($session);
        $allPausedIds = array_values(array_diff(self::getPausedPlayerIds($session), [(int) $reactivatedPlayerId]));

        return self::adjustFutureScheduleCore($session, $allWithdrawnIds, $allPausedIds, $currentRoundNumber);
    }

    /**
     * Logika inti penyesuaian jadwal ronde mendatang berdasarkan status pemain aktif, withdrawn, dan resting/paused.
     */
    public static function adjustFutureScheduleCore(SessionModel $session, array $allWithdrawnIds, array $allPausedIds, int $currentRoundNumber): array
    {
        $sessionId = $session->session_id;
        $lock = Cache::lock("drawing.lock_{$sessionId}", 5);
        $lock->block(3);

        try {
            $allWithdrawnIds = array_values(array_unique(array_map('intval', $allWithdrawnIds)));
            $allPausedIds = array_values(array_unique(array_map('intval', $allPausedIds)));
            $allExcludedIds = array_values(array_unique(array_merge($allWithdrawnIds, $allPausedIds)));

            $session->loadMissing(['players', 'courts']);
            $allPlayers = $session->players;
            $activePlayers = $allPlayers->filter(fn ($p) => ! in_array((int) $p->player_id, $allExcludedIds, true))->values();
            $pausedPlayers = $allPlayers->filter(fn ($p) => in_array((int) $p->player_id, $allPausedIds, true))->values();
            $pausedPlayerNames = $pausedPlayers->pluck('nama')->toArray();

            $scheduleCacheKey = "drawing.schedule_{$sessionId}";
            $cachedSchedule = Cache::get($scheduleCacheKey);

            $format = strtolower($session->match_format ?? 'americano');
            $isTeamFormat = str_contains($format, 'team');
            $drawingMode = $session->jenis_permainan ?? 'Double';
            $isSingle = strtolower($drawingMode) === 'single';
            $courtCount = max(1, $session->courts->count());

            $currentRounds = [];
            if (is_array($cachedSchedule)) {
                $rawRounds = (isset($cachedSchedule['rounds']) && is_array($cachedSchedule['rounds']))
                    ? $cachedSchedule['rounds']
                    : $cachedSchedule;

                foreach ($rawRounds as $k => $val) {
                    if (! is_array($val)) {
                        continue;
                    }
                    $rNum = (int) ($val['round'] ?? ($val['round_number'] ?? (preg_match('/(\d+)/', (string) $k, $m) ? $m[1] : $k)));
                    if ($rNum > 0 && ! isset($currentRounds[$rNum])) {
                        $currentRounds[$rNum] = $val;
                    }
                }
                ksort($currentRounds);
            }

            $scoringSystem = self::detectScoringSystem($session->scoring_system ?? 'Total of 3');
            $sessionRoundCount = self::determineSessionRoundCount(
                $scoringSystem,
                count($allPlayers),
                $isTeamFormat,
                $isSingle,
                $session->waktu_session,
                $courtCount
            );

            $authoritativeDrawing = self::getAuthoritativeDrawing($session);

            // 1. Jika belum ada jadwal di cache, rekonstruksi dari basis data persisten (tb_match & tb_match_participant)
            if (empty($currentRounds) && $authoritativeDrawing && Schema::hasTable('tb_match') && Schema::hasTable('tb_match_participant')) {
                $dbMatches = GameMatch::where('drawing_id', $authoritativeDrawing->drawing_id)
                    ->with(['participants.player'])
                    ->orderBy('nomor_match')
                    ->get();
                if ($dbMatches->isNotEmpty()) {
                    $sessionCourts = $session->courts;
                    $sCourtCount = max(1, $sessionCourts->count());
                    $groupedRounds = [];
                    foreach ($dbMatches as $dm) {
                        $rNum = intdiv(max(0, (int) $dm->nomor_match - 1), $sCourtCount) + 1;
                        if (! isset($groupedRounds[$rNum])) {
                            $snapshot = self::extractRoundSnapshot($dm);
                            $groupedRounds[$rNum] = [
                                'round' => $rNum,
                                'matches' => [],
                                'rotation_bench' => [],
                                'paused_players' => [],
                                'resting' => [],
                            ];
                            if ($snapshot) {
                                $snapPausedIds = $snapshot['paused_player_ids'] ?? [];
                                $snapBenchIds = $snapshot['rotation_bench_ids'] ?? [];
                                $groupedRounds[$rNum]['paused_players'] = $allPlayers->whereIn('player_id', $snapPausedIds)->pluck('nama')->toArray();
                                $groupedRounds[$rNum]['rotation_bench'] = $allPlayers->whereIn('player_id', $snapBenchIds)->pluck('nama')->toArray();
                                $groupedRounds[$rNum]['resting'] = array_values(array_unique(array_merge(
                                    $groupedRounds[$rNum]['rotation_bench'],
                                    $groupedRounds[$rNum]['paused_players']
                                )));
                            }
                        }

                        $teamA = [];
                        $teamB = [];
                        foreach ($dm->participants as $part) {
                            $pInfo = [
                                'id' => (int) $part->player_id,
                                'player_id' => (int) $part->player_id,
                                'name' => $part->player->nama ?? ($part->player->name ?? "Player {$part->player_id}"),
                            ];
                            if ($part->team === 'Tim A' || $part->team === 'team_a' || $part->team === 'A') {
                                $teamA[] = $pInfo;
                            } else {
                                $teamB[] = $pInfo;
                            }
                        }
                        $courtNum = ($dm->nomor_match - 1) % $sCourtCount + 1;
                        $groupedRounds[$rNum]['matches'][] = [
                            'court' => $courtNum,
                            'court_id' => $dm->court_id,
                            'match_id' => $dm->match_id,
                            'team_a' => $teamA,
                            'team_b' => $teamB,
                            'status' => $dm->status_match,
                        ];
                    }
                    if (! empty($groupedRounds)) {
                        $currentRounds = $groupedRounds;
                        $persistentMeta = self::getPersistentScheduleMeta($session);
                        $cachedSchedule = [
                            'rounds' => $currentRounds,
                            'court_count' => $sCourtCount,
                            'schedule_version' => (int) ($persistentMeta['version'] ?? 1),
                        ];
                    }
                }
            }

            // 2. Jika belum ada jadwal di cache maupun di database, generate terlebih dahulu dari data awal
            if (empty($currentRounds)) {
                $roundCount = $sessionRoundCount;

                if ($isTeamFormat && count($allPlayers) >= 4 && count($allPlayers) % 2 === 0) {
                    $teamService = new TeamAmericanoService;
                    $drawingData = $teamService->generateTeamRounds($allPlayers->toArray(), $courtCount, null, $roundCount);
                    $rounds = $drawingData['rounds'] ?? [];
                } else {
                    $americanoService = new AmericanoService;
                    $rounds = $americanoService->generateRounds($allPlayers->toArray(), $courtCount, $roundCount, $drawingMode);
                }
                $currentRounds = [];
                foreach ($rounds as $k => $val) {
                    $rNum = (int) ($val['round'] ?? ($val['round_number'] ?? (preg_match('/(\d+)/', (string) $k, $m) ? $m[1] : $k)));
                    if ($rNum > 0 && ! isset($currentRounds[$rNum])) {
                        $currentRounds[$rNum] = $val;
                    }
                }
                ksort($currentRounds);
                $cachedSchedule = [
                    'rounds' => $currentRounds,
                    'court_count' => $courtCount,
                    'schedule_version' => 1,
                ];
            }

            $totalRounds = $sessionRoundCount ? max(count($currentRounds), $sessionRoundCount) : count($currentRounds);
            if ($totalRounds < $currentRoundNumber) {
                $totalRounds = $currentRoundNumber;
            }

            // Cari ronde terakhir yang terkunci (memiliki match Completed/Stopped, atau skor/event)
            $lastLockedRoundNumber = 0;
            if ($authoritativeDrawing && Schema::hasTable('tb_match')) {
                $sessionCourts = $session->courts;
                $sessionCourtCount = max(1, $sessionCourts->count());
                $allMatches = GameMatch::where('drawing_id', $authoritativeDrawing->drawing_id)->get();
                foreach ($allMatches as $m) {
                    $mRound = intdiv(max(0, (int) $m->nomor_match - 1), $sessionCourtCount) + 1;
                    if (self::isMatchProtectedFromAdjustment($m, $mRound, $currentRoundNumber)) {
                        $lastLockedRoundNumber = max($lastLockedRoundNumber, $mRound);
                    }
                }
            }
            $freezeUpToRound = max(0, min($currentRoundNumber, $lastLockedRoundNumber));

            $updatedRounds = [];
            // 1. Pertahankan ronde lampau & yang sedang terkunci secara utuh
            for ($r = 1; $r <= $freezeUpToRound; $r++) {
                if (isset($currentRounds[$r])) {
                    $updatedRounds[$r] = $currentRounds[$r];
                } elseif (isset($currentRounds["round_{$r}"])) {
                    $updatedRounds[$r] = $currentRounds["round_{$r}"];
                }
            }

            $remainingRoundCount = max(0, $totalRounds - $freezeUpToRound);
            $minRequiredPlayers = $isSingle ? 2 : 4;

            // 2. Format Team Americano dengan pasangan tetap:
            if ($isTeamFormat) {
                // Jangan memasangkan partner dengan orang lain secara diam-diam.
                // Jika tim tidak lengkap karena partner keluar/istirahat, tandai tim dan match tersebut sebagai invalid/incomplete.
                for ($r = $freezeUpToRound + 1; $r <= $totalRounds; $r++) {
                    $origRound = $currentRounds[$r] ?? ($currentRounds["round_{$r}"] ?? []);
                    $matches = $origRound['matches'] ?? [];
                    $adjustedMatches = [];

                    foreach ($matches as $m) {
                        $mCopy = $m;
                        $teamAPlayerIds = array_column($m['team_a'] ?? ($m['teamA'] ?? []), 'id');
                        $teamBPlayerIds = array_column($m['team_b'] ?? ($m['teamB'] ?? []), 'id');

                        $teamAHasExcluded = ! empty(array_intersect($teamAPlayerIds, $allExcludedIds));
                        $teamBHasExcluded = ! empty(array_intersect($teamBPlayerIds, $allExcludedIds));

                        if ($teamAHasExcluded || $teamBHasExcluded) {
                            $mCopy['can_activate'] = false;
                            $mCopy['is_valid'] = false;
                            $mCopy['incomplete_team'] = true;
                            $mCopy['invalidation_reason'] = 'Pasangan tim tidak lengkap karena ada anggota tim yang keluar/istirahat. Memerlukan penataan tim oleh Host.';
                        } else {
                            $mCopy['can_activate'] = true;
                            $mCopy['is_valid'] = true;
                            $mCopy['incomplete_team'] = false;
                            unset($mCopy['invalidation_reason']);
                        }
                        $adjustedMatches[] = $mCopy;
                    }

                    $origRound['matches'] = $adjustedMatches;
                    $origResting = $origRound['resting'] ?? [];
                    $origRound['rotation_bench'] = $origResting;
                    $origRound['paused_players'] = $pausedPlayerNames;
                    $origRound['paused_player_ids'] = $allPausedIds;
                    $origRound['withdrawn_players'] = $allWithdrawnIds;
                    $origRound['resting'] = array_values(array_unique(array_merge($origResting, $pausedPlayerNames)));
                    $updatedRounds[$r] = $origRound;
                }
            } else {
                // Americano Individu:
                if ($activePlayers->count() < $minRequiredPlayers) {
                    // Pemain aktif tidak mencukupi: Jangan membuat dummy atau match tidak valid.
                    for ($r = $freezeUpToRound + 1; $r <= $totalRounds; $r++) {
                        $allRestingNames = array_values(array_unique(array_merge(
                            $activePlayers->pluck('nama')->toArray(),
                            $pausedPlayerNames
                        )));
                        $updatedRounds[$r] = [
                            'round' => $r,
                            'matches' => [],
                            'resting' => $allRestingNames,
                            'teamA' => [],
                            'teamB' => [],
                            'teamA_names' => [],
                            'teamB_names' => [],
                            'has_insufficient_players' => true,
                            'insufficient_reason' => "Jumlah pemain aktif tidak mencukupi (tersisa {$activePlayers->count()} pemain, minimal {$minRequiredPlayers} pemain). Ronde berikutnya belum dapat dimulai tanpa penyesuaian oleh Host.",
                        ];
                    }
                } else {
                    // Pemain aktif mencukupi: Regenerasi sisa jadwal dari pemain aktif
                    $normalizedActive = $activePlayers->map(fn ($p) => [
                        'id' => $p->player_id,
                        'name' => $p->nama,
                        'gender' => $p->gender ?? 'Male',
                        'level' => $p->level ?? 'Intermediate',
                    ])->toArray();

                    $americanoService = new AmericanoService;
                    $previousOpponentHistory = [];
                    $resolvePlayerId = static function ($player, string $fallbackName) use ($allPlayers): ?int {
                        $playerId = is_array($player)
                            ? ($player['id'] ?? $player['player_id'] ?? null)
                            : (is_object($player) ? ($player->id ?? $player->player_id ?? null) : null);
                        if (is_numeric($playerId) && (int) $playerId > 0) {
                            return (int) $playerId;
                        }

                        $playerName = is_array($player)
                            ? ($player['name'] ?? $player['nama'] ?? $fallbackName)
                            : (is_object($player) ? ($player->name ?? $player->nama ?? $fallbackName) : $fallbackName);
                        $cleanName = self::cleanPlayerName((string) $playerName);
                        $matchedPlayer = $allPlayers->first(fn ($candidate) => self::cleanPlayerName((string) $candidate->nama) === $cleanName);

                        return $matchedPlayer ? (int) $matchedPlayer->player_id : null;
                    };

                    foreach ($updatedRounds as $historyRoundNumber => $historyRound) {
                        if ((int) $historyRoundNumber > $freezeUpToRound) {
                            continue;
                        }

                        foreach ($historyRound['matches'] ?? [] as $historyMatch) {
                            $teamAPlayers = $historyMatch['team_a'] ?? ($historyMatch['teamA'] ?? []);
                            $teamBPlayers = $historyMatch['team_b'] ?? ($historyMatch['teamB'] ?? []);
                            $teamANames = $historyMatch['team_a_names'] ?? ($historyMatch['teamA_names'] ?? []);
                            $teamBNames = $historyMatch['team_b_names'] ?? ($historyMatch['teamB_names'] ?? []);
                            $teamAPlayer = is_array($teamAPlayers) ? ($teamAPlayers[0] ?? null) : $teamAPlayers;
                            $teamBPlayer = is_array($teamBPlayers) ? ($teamBPlayers[0] ?? null) : $teamBPlayers;
                            $teamAName = is_array($teamANames) ? (string) ($teamANames[0] ?? '') : (string) $teamANames;
                            $teamBName = is_array($teamBNames) ? (string) ($teamBNames[0] ?? '') : (string) $teamBNames;
                            $teamAPlayerId = $resolvePlayerId($teamAPlayer, $teamAName);
                            $teamBPlayerId = $resolvePlayerId($teamBPlayer, $teamBName);

                            if ($teamAPlayerId === null || $teamBPlayerId === null || $teamAPlayerId === $teamBPlayerId) {
                                continue;
                            }

                            $firstId = min($teamAPlayerId, $teamBPlayerId);
                            $secondId = max($teamAPlayerId, $teamBPlayerId);
                            $pairKey = "{$firstId}-{$secondId}";
                            $previousOpponentHistory[$pairKey] = ($previousOpponentHistory[$pairKey] ?? 0) + 1;
                        }
                    }

                    $generatedFuture = $americanoService->generateRounds(
                        $normalizedActive,
                        $courtCount,
                        $remainingRoundCount,
                        $drawingMode,
                        $previousOpponentHistory
                    );
                    $futureList = array_values($generatedFuture);

                    $genIdx = 0;
                    for ($r = $freezeUpToRound + 1; $r <= $totalRounds; $r++) {
                        if (isset($futureList[$genIdx])) {
                            $rData = $futureList[$genIdx];
                            $rData['round'] = $r;
                            $curResting = $rData['resting'] ?? [];
                            $rData['rotation_bench'] = $curResting;
                            $rData['paused_players'] = $pausedPlayerNames;
                            $rData['paused_player_ids'] = $allPausedIds;
                            $rData['withdrawn_players'] = $allWithdrawnIds;
                            $rData['resting'] = array_values(array_unique(array_merge($curResting, $pausedPlayerNames)));
                            $updatedRounds[$r] = $rData;
                        } else {
                            $allRestingNames = array_values(array_unique(array_merge(
                                $activePlayers->pluck('nama')->toArray(),
                                $pausedPlayerNames
                            )));
                            $updatedRounds[$r] = [
                                'round' => $r,
                                'matches' => [],
                                'rotation_bench' => $activePlayers->pluck('nama')->toArray(),
                                'paused_players' => $pausedPlayerNames,
                                'paused_player_ids' => $allPausedIds,
                                'withdrawn_players' => $allWithdrawnIds,
                                'resting' => $allRestingNames,
                                'teamA' => [],
                                'teamB' => [],
                                'teamA_names' => [],
                                'teamB_names' => [],
                            ];
                        }
                        $genIdx++;
                    }
                }
            }

            $newScheduleVersion = ((int) ($cachedSchedule['schedule_version'] ?? 1)) + 1;
            if (! is_array($cachedSchedule)) {
                $cachedSchedule = [];
            }
            $cachedSchedule['rounds'] = $updatedRounds;
            $cachedSchedule['schedule_version'] = $newScheduleVersion;
            $cachedSchedule['court_count'] = $courtCount;
            $cachedSchedule['withdrawn_player_ids'] = $allWithdrawnIds;
            $cachedSchedule['paused_player_ids'] = $allPausedIds;
            $cachedSchedule['is_adjusted'] = true;
            $cachedSchedule['last_adjusted_at'] = now()->toDateTimeString();

            $drawingDataRounds = [];
            foreach ($updatedRounds as $rNum => $rData) {
                $cachedSchedule["round_{$rNum}"] = $rData;
                $drawingDataRounds["round_{$rNum}"] = $rData;
            }
            $cachedSchedule['drawingData'] = [
                'format' => $format,
                'mode' => $drawingMode,
                'total_teams' => count($allPlayers),
                'total_rounds' => count($updatedRounds),
                'total_matches' => array_sum(array_map(fn ($r) => count($r['matches'] ?? []), $updatedRounds)),
                'rounds' => $drawingDataRounds,
                'court_count' => $courtCount,
            ];

            Cache::put($scheduleCacheKey, $cachedSchedule, now()->addHours(12));
            self::setPersistentScheduleMeta($session, [
                'version' => $newScheduleVersion,
                'needs_adjustment' => true,
                'last_adjusted_at' => now()->toDateTimeString(),
            ]);

            // Sync database untuk match masa depan (tb_match dan tb_match_participant)
            try {
                $drawing = self::getAuthoritativeDrawing($session);
                if ($drawing && Schema::hasTable('tb_match') && Schema::hasTable('tb_match_participant')) {
                    DB::transaction(function () use ($drawing, $session, $updatedRounds, $freezeUpToRound, $newScheduleVersion, $allPausedIds, $allWithdrawnIds, $allPlayers) {
                        $sessionCourts = $session->courts;
                        $sessionCourtCount = max(1, $sessionCourts->count());

                        foreach ($updatedRounds as $rNum => $rData) {
                            $roundMatches = $rData['matches'] ?? [];
                            $roundBenchNames = $rData['rotation_bench'] ?? [];
                            $roundBenchIds = $allPlayers->whereIn('nama', $roundBenchNames)->pluck('player_id')->map('intval')->values()->toArray();

                            $roundPlayingPlayerIds = [];
                            foreach ($roundMatches as $rm) {
                                foreach (($rm['team_a'] ?? []) as $p) {
                                    $roundPlayingPlayerIds[] = (int) ($p['id'] ?? $p['player_id'] ?? 0);
                                }
                                foreach (($rm['team_b'] ?? []) as $p) {
                                    $roundPlayingPlayerIds[] = (int) ($p['id'] ?? $p['player_id'] ?? 0);
                                }
                            }
                            $roundPlayingPlayerIds = array_values(array_filter(array_unique($roundPlayingPlayerIds)));

                            $snapshotPayload = [
                                'round' => $rNum,
                                'paused_player_ids' => array_values(array_diff($allPausedIds, $roundPlayingPlayerIds)),
                                'rotation_bench_ids' => $roundBenchIds,
                                'withdrawn_player_ids' => array_values(array_diff($allWithdrawnIds, $roundPlayingPlayerIds)),
                                'court_count' => $sessionCourtCount,
                                'version' => $newScheduleVersion,
                                'updated_at' => now()->toIso8601String(),
                            ];

                            if ($rNum <= $freezeUpToRound) {
                                // Pertahankan ronde lampau secara utuh; jangan ubah snapshot historis jika sudah ada
                                for ($cIdx = 0; $cIdx < $sessionCourtCount; $cIdx++) {
                                    $nomorMatch = ($rNum - 1) * $sessionCourtCount + ($cIdx + 1);
                                    $existingMatch = GameMatch::where('drawing_id', $drawing->drawing_id)
                                        ->where('nomor_match', $nomorMatch)
                                        ->first();
                                    if ($existingMatch && ! self::extractRoundSnapshot($existingMatch)) {
                                        self::saveRoundSnapshotToMatch($existingMatch, $snapshotPayload);
                                    }
                                }

                                continue;
                            }

                            for ($cIdx = 0; $cIdx < $sessionCourtCount; $cIdx++) {
                                $nomorMatch = ($rNum - 1) * $sessionCourtCount + ($cIdx + 1);
                                $court = $sessionCourts->get($cIdx) ?? $sessionCourts->first();
                                $courtId = $court?->court_id;

                                $dbMatch = GameMatch::where('drawing_id', $drawing->drawing_id)
                                    ->where('nomor_match', $nomorMatch)
                                    ->first();

                                // Jika match dilindungi (In Progress, Terminal, atau memiliki skor/event), jangan utak-atik pesertanya!
                                if ($dbMatch && self::isMatchProtectedFromAdjustment($dbMatch, $rNum, $freezeUpToRound)) {
                                    self::saveRoundSnapshotToMatch($dbMatch, $snapshotPayload);

                                    continue;
                                }

                                if (isset($roundMatches[$cIdx])) {
                                    $mInfo = $roundMatches[$cIdx];
                                    if (! $dbMatch) {
                                        $dbMatch = GameMatch::create([
                                            'drawing_id' => $drawing->drawing_id,
                                            'court_id' => $courtId,
                                            'nomor_match' => $nomorMatch,
                                            'status_match' => 'Scheduled',
                                            'version' => $newScheduleVersion,
                                        ]);
                                    } else {
                                        $dbMatch->court_id = $courtId;
                                        $dbMatch->version = $newScheduleVersion;
                                        $dbMatch->status_match = 'Scheduled';
                                        $dbMatch->save();
                                    }

                                    // Simpan snapshot historis ronde pada match
                                    self::saveRoundSnapshotToMatch($dbMatch, $snapshotPayload);

                                    // Update participants secara aman
                                    MatchParticipant::where('match_id', $dbMatch->match_id)->delete();
                                    foreach ($mInfo['team_a'] ?? ($mInfo['teamA'] ?? []) as $pA) {
                                        $pId = is_array($pA) ? ($pA['id'] ?? null) : (is_object($pA) ? ($pA->id ?? $pA->player_id ?? null) : $pA);
                                        if ($pId) {
                                            MatchParticipant::create([
                                                'match_id' => $dbMatch->match_id,
                                                'player_id' => (int) $pId,
                                                'side' => 'A',
                                            ]);
                                        }
                                    }
                                    foreach ($mInfo['team_b'] ?? ($mInfo['teamB'] ?? []) as $pB) {
                                        $pId = is_array($pB) ? ($pB['id'] ?? null) : (is_object($pB) ? ($pB->id ?? $pB->player_id ?? null) : $pB);
                                        if ($pId) {
                                            MatchParticipant::create([
                                                'match_id' => $dbMatch->match_id,
                                                'player_id' => (int) $pId,
                                                'side' => 'B',
                                            ]);
                                        }
                                    }
                                } else {
                                    // Court ini tidak digunakan di ronde ini (misal kapasitas court berkurang)
                                    if ($dbMatch) {
                                        if (self::isMatchProtectedFromAdjustment($dbMatch, $rNum, $freezeUpToRound)) {
                                            // Jangan hapus jika ada riwayat, tandai Cancelled jika belum selesai
                                            if (strtolower($dbMatch->status_match ?? '') === 'scheduled') {
                                                $dbMatch->status_match = 'Cancelled';
                                                $dbMatch->save();
                                            }
                                        } else {
                                            // Hapus match kosong tanpa riwayat agar tidak menjadi penghalang nextRound
                                            MatchParticipant::where('match_id', $dbMatch->match_id)->delete();
                                            $dbMatch->delete();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            } catch (\Throwable $e) {
                // Ignore DB sync error
            }

            return [
                'success' => true,
                'schedule_version' => $newScheduleVersion,
                'rounds' => $updatedRounds,
                'active_players_count' => $activePlayers->count(),
                'drawingData' => [
                    'rounds' => $drawingDataRounds,
                    'court_count' => $courtCount,
                ],
                'summary' => [
                    'active_players' => $activePlayers->pluck('nama')->toArray(),
                    'withdrawn_players' => $allWithdrawnIds,
                    'paused_players' => $allPausedIds,
                    'insufficient' => $activePlayers->count() < $minRequiredPlayers,
                ],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Rekonstruksi jadwal turnamen dari basis data persisten (tb_drawing, tb_match, tb_match_participant, tb_session_player).
     * Metode ini bersifat READ-ONLY (tidak memanggil generator acak dan tidak melakukan mutasi database).
     *
     * @param  SessionModel|int  $session
     */
    public static function buildScheduleFromDatabase($session): ?array
    {
        try {
            if (! ($session instanceof SessionModel)) {
                $session = SessionModel::with(['courts', 'players'])->find((int) $session);
            }
            if (! $session || ! Schema::hasTable('tb_drawing') || ! Schema::hasTable('tb_match')) {
                return null;
            }

            $drawing = self::getAuthoritativeDrawing($session);
            if (! $drawing) {
                return null;
            }

            $matches = GameMatch::where('drawing_id', $drawing->drawing_id)
                ->orderBy('nomor_match')
                ->get();

            if ($matches->isEmpty()) {
                return null;
            }

            $sessionCourts = $session->courts;
            $sessionCourtCount = max(1, $sessionCourts->count());
            $courtsById = $sessionCourts->keyBy('court_id');
            $allPlayers = $session->players;
            $playersById = $allPlayers->keyBy('player_id');

            $allMatchIds = $matches->pluck('match_id')->toArray();
            $participants = Schema::hasTable('tb_match_participant')
                ? MatchParticipant::whereIn('match_id', $allMatchIds)->get()->groupBy('match_id')
                : collect();

            $pausedPlayerIds = self::getPausedPlayerIds($session);
            $pausedPlayerNames = $allPlayers->whereIn('player_id', $pausedPlayerIds)->pluck('nama')->toArray();
            $withdrawnPlayerIds = self::getWithdrawnPlayerIds($session);

            $format = $session->match_format ?? 'Americano';
            $drawingMode = $session->jenis_permainan ?? 'Double';

            $rounds = [];
            $maxNomorMatch = $matches->max('nomor_match') ?: 1;
            $totalRounds = max(1, (int) ceil($maxNomorMatch / $sessionCourtCount));

            $matchesByNomor = $matches->keyBy('nomor_match');

            for ($r = 1; $r <= $totalRounds; $r++) {
                $roundMatches = [];
                $roundPlayerIds = [];

                for ($cIdx = 0; $cIdx < $sessionCourtCount; $cIdx++) {
                    $nomorMatch = ($r - 1) * $sessionCourtCount + ($cIdx + 1);
                    $m = $matchesByNomor->get($nomorMatch);
                    if (! $m) {
                        continue;
                    }

                    $court = $courtsById->get($m->court_id) ?? ($sessionCourts->get($cIdx) ?? $sessionCourts->first());
                    $mParts = $participants->get($m->match_id) ?? collect();

                    $teamAParticipants = $mParts->where('side', 'A');
                    $teamBParticipants = $mParts->where('side', 'B');

                    // Jika tidak ada participant di kedua sisi dan status bukan stopped/terminal, court ini tidak aktif
                    if ($teamAParticipants->isEmpty() && $teamBParticipants->isEmpty() && ! in_array(strtolower($m->status_match ?? ''), ['stopped', 'completed', 'in progress'])) {
                        continue;
                    }

                    $teamA = [];
                    $teamANames = [];
                    foreach ($teamAParticipants as $tp) {
                        $p = $playersById->get($tp->player_id);
                        $pName = $p?->nama ?? "Player {$tp->player_id}";
                        $teamA[] = ['id' => $tp->player_id, 'name' => $pName];
                        $teamANames[] = $pName;
                        $roundPlayerIds[] = (int) $tp->player_id;
                    }

                    $teamB = [];
                    $teamBNames = [];
                    foreach ($teamBParticipants as $tp) {
                        $p = $playersById->get($tp->player_id);
                        $pName = $p?->nama ?? "Player {$tp->player_id}";
                        $teamB[] = ['id' => $tp->player_id, 'name' => $pName];
                        $teamBNames[] = $pName;
                        $roundPlayerIds[] = (int) $tp->player_id;
                    }

                    $roundMatches[] = [
                        'match_id' => $m->match_id,
                        'court_id' => $m->court_id,
                        'court_name' => $court?->nama_court ?? 'Court '.($cIdx + 1),
                        'court' => $cIdx + 1,
                        'nomor_match' => $m->nomor_match,
                        'status' => $m->status_match,
                        'team_a' => $teamA,
                        'team_b' => $teamB,
                        'team_a_names' => $teamANames,
                        'team_b_names' => $teamBNames,
                    ];
                }

                $roundPlayerIds = array_unique($roundPlayerIds);

                // Cek apakah ada snapshot historis di salah satu match ronde ini
                $roundSnapshot = null;
                for ($cIdx = 0; $cIdx < $sessionCourtCount; $cIdx++) {
                    $nomorMatch = ($r - 1) * $sessionCourtCount + ($cIdx + 1);
                    $m = $matchesByNomor->get($nomorMatch);
                    if ($m) {
                        $snap = self::extractRoundSnapshot($m);
                        if ($snap) {
                            $roundSnapshot = $snap;
                            break;
                        }
                    }
                }

                $roundPausedIds = $roundSnapshot['paused_player_ids'] ?? $pausedPlayerIds;
                $roundWithdrawnIds = $roundSnapshot['withdrawn_player_ids'] ?? $withdrawnPlayerIds;

                if ($roundSnapshot && isset($roundSnapshot['rotation_bench_ids'])) {
                    $rotationBenchIds = $roundSnapshot['rotation_bench_ids'];
                    $rotationBenchNames = [];
                    foreach ($rotationBenchIds as $bId) {
                        $pl = $playersById->get($bId);
                        if ($pl) {
                            $rotationBenchNames[] = $pl->nama;
                        }
                    }
                } else {
                    // Bench rotasi fallback: Pemain aktif yang tidak bermain di ronde ini
                    $rotationBenchNames = [];
                    foreach ($allPlayers as $pl) {
                        if (! in_array($pl->player_id, $roundPausedIds) && ! in_array($pl->player_id, $roundWithdrawnIds)) {
                            if (! in_array($pl->player_id, $roundPlayerIds)) {
                                $rotationBenchNames[] = $pl->nama;
                            }
                        }
                    }
                }

                $roundPausedNames = [];
                foreach ($roundPausedIds as $pId) {
                    $pl = $playersById->get($pId);
                    if ($pl) {
                        $roundPausedNames[] = $pl->nama;
                    }
                }

                $allRestingNames = array_values(array_unique(array_merge($rotationBenchNames, $roundPausedNames)));

                $rounds[$r] = [
                    'round' => $r,
                    'round_number' => $r,
                    'matches' => $roundMatches,
                    'rotation_bench' => $rotationBenchNames,
                    'paused_players' => $roundPausedNames,
                    'paused_player_ids' => $roundPausedIds,
                    'withdrawn_players' => $roundWithdrawnIds,
                    'resting' => $allRestingNames,
                    'teamA' => $roundMatches[0]['team_a'] ?? [],
                    'teamB' => $roundMatches[0]['team_b'] ?? [],
                    'teamA_names' => $roundMatches[0]['team_a_names'] ?? [],
                    'teamB_names' => $roundMatches[0]['team_b_names'] ?? [],
                ];
            }

            $drawingDataRounds = [];
            foreach ($rounds as $rNum => $rData) {
                $drawingDataRounds["round_{$rNum}"] = $rData;
            }

            return [
                'rounds' => $rounds,
                'court_count' => $sessionCourtCount,
                'withdrawn_player_ids' => $withdrawnPlayerIds,
                'paused_player_ids' => $pausedPlayerIds,
                'is_adjusted' => self::hasScheduleBeenAdjusted($session),
                'drawingData' => [
                    'format' => $format,
                    'mode' => $drawingMode,
                    'total_teams' => count($allPlayers),
                    'total_rounds' => count($rounds),
                    'total_matches' => array_sum(array_map(fn ($r) => count($r['matches'] ?? []), $rounds)),
                    'rounds' => $drawingDataRounds,
                    'court_count' => $sessionCourtCount,
                ],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
