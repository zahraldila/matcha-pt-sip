<?php

namespace App\Services\Scoring;

class ScoringService
{
    /**
     * Parse scoring system string dari game data.
     * Contoh: "Americano 32 Points" => ['type' => 'americano', 'target' => 32]
     *         "Tennis System (15, 30, 40, Deuce, Adv, Game)" => ['type' => 'tennis', 'target' => null]
     *         "Points (1, 2, 3... / 24 Points per game)" => ['type' => 'points', 'target' => 24]
     */
    public static function detectScoringSystem(string $scoringSystem): array
    {
        $lower = strtolower($scoringSystem);

        if (str_contains($lower, 'tennis')) {
            return ['type' => 'tennis', 'target' => null, 'label' => 'Sistem Tennis (15, 30, 40, Deuce)'];
        }

        // Cari angka target (misal: "32 Points", "24 Points per game")
        preg_match('/(\d+)\s*points?/i', $scoringSystem, $matches);
        $target = isset($matches[1]) ? (int) $matches[1] : 32;

        if (str_contains($lower, 'americano')) {
            return ['type' => 'americano', 'target' => $target, 'label' => "Americano — First to {$target} Points"];
        }

        // Default fallback: Tennis system
        return ['type' => 'tennis', 'target' => null, 'label' => 'Sistem Tennis (15, 30, 40, Deuce)'];
    }

    /**
     * Hitung akumulasi poin setiap pemain dari data rounds drawing + skor session.
     * Mengembalikan array player stats, sudah diurutkan dari poin tertinggi.
     *
     * $game         : array dari MatchaDummyDataService::getGames() (atau DB)
     * $sessionScores: array skor dari session, format:
     *   ['round_1' => ['score_a' => 32, 'score_b' => 20, 'status' => 'completed'], ...]
     */
    public static function calculateRecap(array $game, array $sessionScores = []): array
    {
        $players = $game['participants'] ?? [];
        $drawing  = $game['drawing'] ?? [];

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
        }

        // Init stats tiap pemain
        $stats = [];
        foreach ($players as $p) {
            $name = $p['name'];
            $stats[$name] = [
                'name'          => $name,
                'avatar'        => $p['avatar'] ?? null,
                'level'         => $p['level'] ?? '-',
                'is_member'     => $p['is_member'] ?? false,
                'matches'       => 0,
                'wins'          => 0,
                'losses'        => 0,
                'points_for'    => 0,   // Poin menang (dicetak)
                'points_against'=> 0,   // Poin kebobolan
                'point_diff'    => 0,   // Selisih
            ];
        }

        if (empty($drawing)) {
            return array_values($stats);
        }

        // Iterasi tiap round
        foreach ($drawing as $roundKey => $round) {
            $teamA   = $round['team_a'] ?? [];
            $teamB   = $round['team_b'] ?? [];

            // Ambil skor dari session jika ada, fallback ke skor dummy
            $scoreA = 0;
            $scoreB = 0;
            $status = 'pending';

            if (isset($sessionScores[$roundKey])) {
                $scoreA = (int) ($sessionScores[$roundKey]['score_a'] ?? 0);
                $scoreB = (int) ($sessionScores[$roundKey]['score_b'] ?? 0);
                $status = $sessionScores[$roundKey]['status'] ?? 'pending';
            } else {
                // Dummy fallback: generate skor acak berdasarkan seed round
                [$scoreA, $scoreB] = self::generateDummyScore($roundKey, $game['scoring_system'] ?? '');
                $status = 'completed';
            }

            // Hanya hitung round yang sudah selesai
            if ($status !== 'completed') {
                continue;
            }

            $winnerSide = $scoreA > $scoreB ? 'A' : ($scoreB > $scoreA ? 'B' : null);

            foreach ($teamA as $playerName) {
                $cleanName = self::cleanPlayerName($playerName);
                if (!isset($stats[$cleanName])) continue;

                $stats[$cleanName]['matches']++;
                $stats[$cleanName]['points_for']     += $scoreA;
                $stats[$cleanName]['points_against'] += $scoreB;

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
                $stats[$cleanName]['points_for']     += $scoreB;
                $stats[$cleanName]['points_against'] += $scoreA;

                if ($winnerSide === 'B') {
                    $stats[$cleanName]['wins']++;
                } elseif ($winnerSide === 'A') {
                    $stats[$cleanName]['losses']++;
                }
            }
        }

        // Hitung selisih poin
        foreach ($stats as &$s) {
            $s['point_diff'] = $s['points_for'] - $s['points_against'];
        }

        // Sort: wins DESC, points_for DESC, point_diff DESC
        $ranked = array_values($stats);
        usort($ranked, function ($a, $b) {
            if ($b['wins'] !== $a['wins']) return $b['wins'] - $a['wins'];
            if ($b['points_for'] !== $a['points_for']) return $b['points_for'] - $a['points_for'];
            return $b['point_diff'] - $a['point_diff'];
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
     * Dipakai sebagai fallback saat tidak ada session score.
     */
    private static function generateDummyScore(string $roundKey, string $scoringSystem): array
    {
        $system = self::detectScoringSystem($scoringSystem);
        $target = $system['target'] ?? 32;

        // Seed dari nama round
        $seed = crc32($roundKey);
        srand($seed);

        if ($system['type'] === 'tennis') {
            // Return games won (1-6)
            $a = rand(3, 6);
            $b = rand(0, $a - 1);
            srand(); // reset seed
            return [$a, $b];
        }

        // Americano / Points: winner mencapai target, loser random lebih rendah
        $winner = $target;
        $loser  = rand((int) ($target * 0.4), $target - 1);
        $flip   = $seed % 2 === 0;
        srand(); // reset seed

        return $flip ? [$winner, $loser] : [$loser, $winner];
    }

    /**
     * Ambil skor aktif (per round) dari match yang sedang berlangsung.
     * Mengembalikan skor current round dari game session.
     */
    public static function buildMatchContext(array $game, string $activeRound = 'round_1'): array
    {
        $drawing = $game['drawing'] ?? [];

        // Fallback jika belum ada drawing tapi ada participants
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
