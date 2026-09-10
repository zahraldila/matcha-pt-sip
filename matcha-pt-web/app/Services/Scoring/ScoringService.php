<?php

namespace App\Services\Scoring;

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
    public static function detectScoringSystem(string $scoringSystem = ''): array
    {
        $str = trim($scoringSystem);

        // Grup Total of X (Sets)
        if (preg_match('/total\s*of\s*(\d+)/i', $str, $matches)) {
            $sets = (int) $matches[1];
            if (!in_array($sets, [3, 4, 5, 6, 7])) {
                $sets = 3;
            }
            $targetSets = (int) ceil(($sets + 1) / 2);
            return [
                'type'         => 'total_of_sets',
                'category'     => 'sets',
                'max_sets'     => $sets,
                'target_sets'  => $targetSets,
                'target_games' => 6,
                'label'        => "Total of {$sets}",
                'is_sets'      => true,
            ];
        }

        // Grup First to X (Games)
        if (preg_match('/first\s*to\s*(\d+)/i', $str, $matches)) {
            $games = (int) $matches[1];
            if (!in_array($games, [8, 11, 15, 21])) {
                $games = 8;
            }
            return [
                'type'         => 'first_to_games',
                'category'     => 'games',
                'max_sets'     => 1,
                'target_sets'  => 1,
                'target_games' => $games,
                'label'        => "First to {$games}",
                'is_sets'      => false,
            ];
        }

        // Default fallback ke Total of 3
        return [
            'type'         => 'total_of_sets',
            'category'     => 'sets',
            'max_sets'     => 3,
            'target_sets'  => 2,
            'target_games' => 6,
            'label'        => 'Total of 3',
            'is_sets'      => true,
        ];
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
                    ($v['sets_a'] ?? 0) > 0  || ($v['sets_b'] ?? 0) > 0
                )) {
                    $hasRealCompleted = true;
                    break;
                }
            }
        }

        foreach ($drawing as $roundKey => $round) {
            $isCompleted = isset($effective[$roundKey]) && (
                ($effective[$roundKey]['status'] ?? '') === 'completed' ||
                ($isFinishedSession && (
                    ($effective[$roundKey]['games_a'] ?? 0) > 0 ||
                    ($effective[$roundKey]['games_b'] ?? 0) > 0 ||
                    ($effective[$roundKey]['sets_a'] ?? 0) > 0 ||
                    ($effective[$roundKey]['sets_b'] ?? 0) > 0 ||
                    ($effective[$roundKey]['score_a'] ?? 0) > 0 ||
                    ($effective[$roundKey]['score_b'] ?? 0) > 0
                ))
            );

            if ($isCompleted) {
                $effective[$roundKey]['status'] = 'completed';
                if (empty($effective[$roundKey]['team_a'])) {
                    $effective[$roundKey]['team_a'] = $round['team_a'] ?? [];
                }
                if (empty($effective[$roundKey]['team_b'])) {
                    $effective[$roundKey]['team_b'] = $round['team_b'] ?? [];
                }
                $effective[$roundKey]['round_title'] = ucfirst(str_replace('_', ' ', $roundKey));
                $effective[$roundKey]['scoring_type'] = $system['type'];

                // Pastikan set_history dan sets_a/b tidak kosong jika format Total of Sets
                if ($system['type'] === 'total_of_sets') {
                    $eSetsA  = (int) ($effective[$roundKey]['sets_a'] ?? 0);
                    $eSetsB  = (int) ($effective[$roundKey]['sets_b'] ?? 0);
                    $eGamesA = (int) ($effective[$roundKey]['games_a'] ?? 0);
                    $eGamesB = (int) ($effective[$roundKey]['games_b'] ?? 0);
                    $eHist   = $effective[$roundKey]['set_history'] ?? [];

                    if (empty($eHist)) {
                        if ($eGamesA > 0 || $eGamesB > 0) {
                            $eHist = [
                                ['set' => 1, 'score_a' => $eGamesA, 'score_b' => $eGamesB]
                            ];
                            if ($eSetsA === 0 && $eSetsB === 0) {
                                $eSetsA = $eGamesA >= $eGamesB ? 1 : 0;
                                $eSetsB = $eGamesB > $eGamesA ? 1 : 0;
                            }
                        } elseif ($eSetsA > 0 || $eSetsB > 0) {
                            $eHist = [];
                            for ($i = 1; $i <= ($eSetsA + $eSetsB); $i++) {
                                $aWins = ($i <= $eSetsA);
                                $eHist[] = [
                                    'set'     => $i,
                                    'score_a' => $aWins ? 6 : 3,
                                    'score_b' => $aWins ? 3 : 6,
                                ];
                            }
                        }
                    }
                    $effective[$roundKey]['set_history'] = $eHist;
                    $effective[$roundKey]['sets_a'] = $eSetsA;
                    $effective[$roundKey]['sets_b'] = $eSetsB;
                    $effective[$roundKey]['score_a'] = $eSetsA;
                    $effective[$roundKey]['score_b'] = $eSetsB;
                }
            } elseif (!$hasRealCompleted) {
                // Hanya generate skor dummy jika TIDAK ADA satu pun match riil yang selesai
                $dummy = self::generateDummyScore($roundKey, $scoringSystem);
                $effective[$roundKey] = array_merge([
                    'status'      => 'completed',
                    'team_a'      => $round['team_a'] ?? [],
                    'team_b'      => $round['team_b'] ?? [],
                    'round_title' => ucfirst(str_replace('_', ' ', $roundKey)),
                    'scoring_type'=> $system['type'],
                ], $dummy);
            } else {
                // Ada match riil yang selesai, maka round yang belum dimainkan berstatus pending
                $effective[$roundKey] = [
                    'score_a'     => 0,
                    'score_b'     => 0,
                    'sets_a'      => 0,
                    'sets_b'      => 0,
                    'games_a'     => 0,
                    'games_b'     => 0,
                    'set_history' => [],
                    'status'      => 'pending',
                    'team_a'      => $round['team_a'] ?? [],
                    'team_b'      => $round['team_b'] ?? [],
                    'round_title' => ucfirst(str_replace('_', ' ', $roundKey)),
                    'scoring_type'=> $system['type'],
                ];
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
        if (empty($drawing) && !empty($players)) {
            $names = array_map(fn($p) => self::cleanPlayerName($p['name'] ?? ''), $players);
            $half  = (int) ceil(count($names) / 2);
            $drawing = [
                'round_1' => [
                    'team_a'  => array_slice($names, 0, min(2, $half)),
                    'team_b'  => array_slice($names, min(2, $half), 2),
                    'resting' => array_slice($names, 4),
                ],
            ];
            $game['drawing'] = $drawing;
        }

        $effectiveScores = self::getEffectiveScores($game, $sessionScores);

        // Init stats tiap pemain
        $stats = [];
        foreach ($players as $p) {
            $name = $p['name'];
            $stats[$name] = [
                'name'           => $name,
                'avatar'         => $p['avatar'] ?? null,
                'level'          => $p['level'] ?? '-',
                'is_member'      => $p['is_member'] ?? false,
                'matches'        => 0,
                'wins'           => 0,
                'losses'         => 0,
                'sets_won'       => 0,
                'sets_lost'      => 0,
                'games_won'      => 0,
                'games_lost'     => 0,
                'points_for'     => 0,   // Sets won (Total of Sets) atau Games won (First to Games)
                'points_against' => 0,
                'point_diff'     => 0,
                'game_diff'      => 0,
                'set_diff'       => 0,
            ];
        }

        if (empty($drawing)) {
            return array_values($stats);
        }

        // Iterasi tiap round menggunakan $effectiveScores yang sudah sinkron
        foreach ($drawing as $roundKey => $round) {
            $teamA   = $round['team_a'] ?? [];
            $teamB   = $round['team_b'] ?? [];

            $roundScore = $effectiveScores[$roundKey] ?? [];
            $status = $roundScore['status'] ?? 'pending';

            // Hanya hitung round yang sudah selesai
            if ($status !== 'completed') {
                continue;
            }

            $scoreA = (int) ($roundScore['score_a'] ?? 0);
            $scoreB = (int) ($roundScore['score_b'] ?? 0);
            $setsA  = (int) ($roundScore['sets_a'] ?? 0);
            $setsB  = (int) ($roundScore['sets_b'] ?? 0);
            $gamesA = (int) ($roundScore['games_a'] ?? 0);
            $gamesB = (int) ($roundScore['games_b'] ?? 0);

            if ($system['type'] === 'total_of_sets') {
                if (!empty($roundScore['set_history'])) {
                    $shSetsA  = 0;
                    $shSetsB  = 0;
                    $shGamesA = 0;
                    $shGamesB = 0;
                    foreach ($roundScore['set_history'] as $sh) {
                        $ga = (int) ($sh['score_a'] ?? 0);
                        $gb = (int) ($sh['score_b'] ?? 0);
                        $shGamesA += $ga;
                        $shGamesB += $gb;
                        if ($ga > $gb) $shSetsA++;
                        elseif ($gb > $ga) $shSetsB++;
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
                    $setsA = $gamesA >= $gamesB ? 1 : 0;
                    $setsB = $gamesB > $gamesA ? 1 : 0;
                }

                // Di format sets, winner adalah peraih set terbanyak
                $winnerSide = $setsA > $setsB ? 'A' : ($setsB > $setsA ? 'B' : ($scoreA > $scoreB ? 'A' : ($scoreB > $scoreA ? 'B' : null)));
                $ptsForA = $setsA;
                $ptsForB = $setsB;
            } else {
                // Di format first to games, winner adalah peraih game terbanyak
                $winnerSide = $gamesA > $gamesB ? 'A' : ($gamesB > $gamesA ? 'B' : ($scoreA > $scoreB ? 'A' : ($scoreB > $scoreA ? 'B' : null)));
                $ptsForA = $gamesA > 0 ? $gamesA : $scoreA;
                $ptsForB = $gamesB > 0 ? $gamesB : $scoreB;
            }

            foreach ($teamA as $playerName) {
                $cleanName = self::cleanPlayerName($playerName);
                if (!isset($stats[$cleanName])) continue;

                $stats[$cleanName]['matches']++;
                $stats[$cleanName]['sets_won']       += $setsA;
                $stats[$cleanName]['sets_lost']      += $setsB;
                $stats[$cleanName]['games_won']      += $gamesA;
                $stats[$cleanName]['games_lost']     += $gamesB;
                $stats[$cleanName]['points_for']     += $ptsForA;
                $stats[$cleanName]['points_against'] += $ptsForB;

                if ($winnerSide === 'A') {
                    $stats[$cleanName]['wins']++;
                } elseif ($winnerSide === 'B') {
                    $stats[$cleanName]['losses']++;
                }
            }

            foreach ($teamB as $playerName) {
                $cleanName = self::cleanPlayerName($playerName);
                if (!isset($stats[$cleanName])) continue;

                $stats[$cleanName]['matches']++;
                $stats[$cleanName]['sets_won']       += $setsB;
                $stats[$cleanName]['sets_lost']      += $setsA;
                $stats[$cleanName]['games_won']      += $gamesB;
                $stats[$cleanName]['games_lost']     += $gamesA;
                $stats[$cleanName]['points_for']     += $ptsForB;
                $stats[$cleanName]['points_against'] += $ptsForA;

                if ($winnerSide === 'B') {
                    $stats[$cleanName]['wins']++;
                } elseif ($winnerSide === 'A') {
                    $stats[$cleanName]['losses']++;
                }
            }
        }

        // Hitung selisih
        foreach ($stats as &$s) {
            $s['point_diff'] = $s['points_for'] - $s['points_against'];
            $s['game_diff']  = $s['games_won'] - $s['games_lost'];
            $s['set_diff']   = $s['sets_won'] - $s['sets_lost'];
        }

        // Sort: wins DESC, points_for DESC, diff DESC
        $ranked = array_values($stats);
        usort($ranked, function ($a, $b) use ($system) {
            if ($b['wins'] !== $a['wins']) return $b['wins'] - $a['wins'];

            if ($system['type'] === 'total_of_sets') {
                if ($b['sets_won'] !== $a['sets_won']) return $b['sets_won'] - $a['sets_won'];
                if ($b['set_diff'] !== $a['set_diff']) return $b['set_diff'] - $a['set_diff'];
                if ($b['games_won'] !== $a['games_won']) return $b['games_won'] - $a['games_won'];
                return $b['game_diff'] - $a['game_diff'];
            }

            // First to games:
            if ($b['games_won'] !== $a['games_won']) return $b['games_won'] - $a['games_won'];
            return $b['game_diff'] - $a['game_diff'];
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

        if ($system['type'] === 'total_of_sets') {
            $targetSets = $system['target_sets'];
            $winnerSets = $targetSets;
            $loserSets  = rand(0, max(0, $targetSets - 1));

            $setHistory = [];
            $totalGamesA = 0;
            $totalGamesB = 0;

            for ($s = 1; $s <= ($winnerSets + $loserSets); $s++) {
                $isWinnerSet = ($s <= $winnerSets);
                $gWin = 6;
                $gLose = rand(2, 4);
                $sA = $isWinnerSet ? $gWin : $gLose;
                $sB = $isWinnerSet ? $gLose : $gWin;

                if ($flip) {
                    $setHistory[] = ['set' => $s, 'score_a' => $sA, 'score_b' => $sB];
                    $totalGamesA += $sA;
                    $totalGamesB += $sB;
                } else {
                    $setHistory[] = ['set' => $s, 'score_a' => $sB, 'score_b' => $sA];
                    $totalGamesA += $sB;
                    $totalGamesB += $sA;
                }
            }

            srand();
            $setsA = $flip ? $winnerSets : $loserSets;
            $setsB = $flip ? $loserSets : $winnerSets;

            return [
                'score_a'     => $setsA,
                'score_b'     => $setsB,
                'sets_a'      => $setsA,
                'sets_b'      => $setsB,
                'games_a'     => $totalGamesA,
                'games_b'     => $totalGamesB,
                'set_history' => $setHistory,
            ];
        }

        // First to games
        $target = $system['target_games'] ?? 8;
        $winnerGames = $target;
        $loserGames  = rand((int) max(1, $target * 0.5), $target - 1);

        srand();
        $gamesA = $flip ? $winnerGames : $loserGames;
        $gamesB = $flip ? $loserGames : $winnerGames;

        return [
            'score_a'     => $gamesA,
            'score_b'     => $gamesB,
            'sets_a'      => $gamesA > $gamesB ? 1 : 0,
            'sets_b'      => $gamesB > $gamesA ? 1 : 0,
            'games_a'     => $gamesA,
            'games_b'     => $gamesB,
            'set_history' => [],
        ];
    }

    /**
     * Ambil konteks round aktif.
     */
    public static function buildMatchContext(array $game, string $activeRound = 'round_1'): array
    {
        $drawing = $game['drawing'] ?? [];

        if (empty($drawing) && !empty($game['participants'])) {
            $names = array_map(fn($p) => self::cleanPlayerName($p['name'] ?? ''), $game['participants']);
            $half  = (int) ceil(count($names) / 2);
            $drawing = [
                'round_1' => [
                    'team_a'  => array_slice($names, 0, min(2, $half)),
                    'team_b'  => array_slice($names, min(2, $half), 2),
                    'resting' => array_slice($names, 4),
                ],
            ];
        }

        $round = $drawing[$activeRound] ?? ($drawing['round_1'] ?? null);

        if (!$round) {
            return [
                'team_a'       => [],
                'team_b'       => [],
                'resting'      => [],
                'active_round' => $activeRound,
                'total_rounds' => 0,
                'all_rounds'   => ['round_1'],
            ];
        }

        return [
            'team_a'       => $round['team_a'] ?? [],
            'team_b'       => $round['team_b'] ?? [],
            'resting'      => $round['resting'] ?? [],
            'active_round' => $activeRound,
            'total_rounds' => count($drawing),
            'all_rounds'   => array_keys($drawing),
        ];
    }
}
