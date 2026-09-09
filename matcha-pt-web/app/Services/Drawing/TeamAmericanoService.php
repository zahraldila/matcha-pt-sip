<?php

namespace App\Services\Drawing;

class TeamAmericanoService
{
    /**
     * Bentuk pasangan tim tetap (Fixed Pairs) dari daftar pemain yang masuk.
     *
     * @param array $players Daftar pemain (array of string atau array associative)
     * @return array List tim tetap dengan komposisi 2 pemain per tim
     */
    public function formFixedTeams(array $players): array
    {
        // Normalisasi format pemain
        $normalizedPlayers = array_map(function ($player, $index) {
            if (is_string($player)) {
                return [
                    'id' => $index + 1,
                    'name' => $player,
                    'gender' => 'Male',
                    'level' => 'Intermediate',
                    'type' => 'Player',
                ];
            }
            return [
                'id' => $player['id'] ?? ($index + 1),
                'name' => $player['name'] ?? 'Player ' . ($index + 1),
                'gender' => $player['gender'] ?? 'Male',
                'level' => $player['level'] ?? 'Intermediate',
                'type' => $player['type'] ?? 'Player',
                'avatar' => $player['avatar'] ?? null,
            ];
        }, $players, array_keys($players));

        $teams = [];
        $teamIndex = 1;
        $teamLabels = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa'];

        for ($i = 0; $i < count($normalizedPlayers); $i += 2) {
            $p1 = $normalizedPlayers[$i];
            $p2 = $normalizedPlayers[$i + 1] ?? null;

            if ($p2) {
                $label = $teamLabels[$teamIndex - 1] ?? "Team {$teamIndex}";
                $teams[] = [
                    'id' => $teamIndex,
                    'code' => "T{$teamIndex}",
                    'name' => "Team {$label}",
                    'short_name' => "Team {$teamIndex}",
                    'players' => [$p1, $p2],
                    'player_names' => [$p1['name'], $p2['name']],
                    'display_name' => "Team {$label} [{$p1['name']} & {$p2['name']}]",
                ];
                $teamIndex++;
            }
        }

        return $teams;
    }

    /**
     * Generate jadwal pertandingan Round-Robin untuk Team Americano (Pasangan Tetap).
     * Setiap tim bertanding melawan seluruh tim lain tepat 1 kali.
     * Menggunakan Circle/Polygon Round-Robin Scheduling Algorithm.
     *
     * @param array $players    Daftar pemain
     * @param int   $courtCount Jumlah lapangan aktif
     * @return array Struktur jadwal ronde dan match
     */
    public function generateTeamRounds(array $players, int $courtCount = 1): array
    {
        $teams = $this->formFixedTeams($players);
        $totalTeams = count($teams);

        if ($totalTeams < 2) {
            return [
                'teams' => $teams,
                'total_teams' => $totalTeams,
                'total_rounds' => 0,
                'total_matches' => 0,
                'rounds' => [],
            ];
        }

        // Siapkan array tim untuk algoritma rotasi circle
        $rotationList = $teams;
        $hasBye = false;

        // Jika jumlah tim ganjil, tambahkan dummy tim 'BYE' (giliran istirahat)
        if ($totalTeams % 2 !== 0) {
            $hasBye = true;
            $rotationList[] = [
                'id' => null,
                'code' => 'BYE',
                'name' => 'BYE (Istirahat)',
                'is_bye' => true,
                'players' => [],
                'player_names' => [],
            ];
        }

        $numParticipants = count($rotationList);
        $totalRounds = $numParticipants - 1;
        $matchesPerRound = $numParticipants / 2;

        $rounds = [];
        $totalMatchesCount = 0;

        for ($roundIdx = 0; $roundIdx < $totalRounds; $roundIdx++) {
            $roundNumber = $roundIdx + 1;
            $courtMatches = [];
            $restingTeams = [];
            $restingPlayers = [];

            $activeCourt = 1;

            for ($matchIdx = 0; $matchIdx < $matchesPerRound; $matchIdx++) {
                $teamA = $rotationList[$matchIdx];
                $teamB = $rotationList[$numParticipants - 1 - $matchIdx];

                // Cek apakah ada tim yang mendapat BYE (istirahat)
                if (!empty($teamA['is_bye'])) {
                    $restingTeams[] = $teamB;
                    $restingPlayers = array_merge($restingPlayers, $teamB['player_names']);
                    continue;
                }
                if (!empty($teamB['is_bye'])) {
                    $restingTeams[] = $teamA;
                    $restingPlayers = array_merge($restingPlayers, $teamA['player_names']);
                    continue;
                }

                // Jika match masih dalam kapasitas lapangan aktif
                if ($activeCourt <= $courtCount) {
                    $courtMatches[] = [
                        'match_id' => "R{$roundNumber}-M{$activeCourt}",
                        'court_id' => $activeCourt,
                        'court_name' => "Court {$activeCourt}",
                        'team_a' => $teamA,
                        'team_b' => $teamB,
                        // Kompatibilitas untuk visualizer <x-court-visual>
                        'teamA_names' => $teamA['player_names'],
                        'teamB_names' => $teamB['player_names'],
                        'score_a' => 0,
                        'score_b' => 0,
                        'status' => 'Scheduled',
                    ];
                    $totalMatchesCount++;
                    $activeCourt++;
                } else {
                    // Jika lapangan penuh, tim pada match berlebih istirahat di ronde ini
                    $restingTeams[] = $teamA;
                    $restingTeams[] = $teamB;
                    $restingPlayers = array_merge($restingPlayers, $teamA['player_names'], $teamB['player_names']);
                }
            }

            // Fallback untuk visualizer ronde (kompatibilitas template Blade)
            $firstMatch = $courtMatches[0] ?? null;

            $rounds[$roundNumber] = [
                'round_number' => $roundNumber,
                'round_title' => "Ronde {$roundNumber}",
                'matches' => $courtMatches,
                'resting_teams' => $restingTeams,
                'resting_players' => $restingPlayers,
                // Properti ringkas untuk representasi UI lapangan utama
                'primary_match' => $firstMatch,
                'teamA' => $firstMatch ? $firstMatch['teamA_names'] : [],
                'teamB' => $firstMatch ? $firstMatch['teamB_names'] : [],
                'teamA_display' => $firstMatch ? $firstMatch['team_a']['display_name'] : '-',
                'teamB_display' => $firstMatch ? $firstMatch['team_b']['display_name'] : '-',
                'resting' => $restingPlayers,
            ];

            // Rotasi array menggunakan Circle Method (posisi 0 tetap, sisa elemen berputar searah jarum jam)
            $first = $rotationList[0];
            $tail = array_slice($rotationList, 1);
            $lastItem = array_pop($tail);
            array_unshift($tail, $lastItem);
            $rotationList = array_merge([$first], $tail);
        }

        return [
            'format' => 'Team Americano',
            'teams' => $teams,
            'total_teams' => $totalTeams,
            'total_rounds' => $totalRounds,
            'total_matches' => $totalMatchesCount,
            'rounds' => $rounds,
        ];
    }
}
