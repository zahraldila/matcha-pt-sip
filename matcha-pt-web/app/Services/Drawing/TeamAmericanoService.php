<?php

namespace App\Services\Drawing;

use InvalidArgumentException;
use RuntimeException;

class TeamAmericanoService
{
    /**
     * Bentuk pasangan tim tetap (Fixed Pairs) dari daftar pemain yang masuk.
     *
     * @param array $players Daftar pemain (array of string atau array associative)
     * @return array List tim tetap dengan komposisi 2 pemain per tim
     * @throws InvalidArgumentException Jika jumlah pemain < 4, ganjil, atau ada duplikat
     */
    public function formFixedTeams(array $players): array
    {
        // 1. Reset array keys untuk mencegah error jika input memiliki non-numeric key
        $players = array_values($players);
        $totalPlayers = count($players);

        if ($totalPlayers < 4) {
            throw new InvalidArgumentException(
                'Team Americano membutuhkan minimal 4 pemain (2 tim).'
            );
        }

        if ($totalPlayers % 2 !== 0) {
            throw new InvalidArgumentException(
                'Jumlah pemain Team Americano harus genap karena setiap team terdiri dari 2 pemain.'
            );
        }

        // 2. Normalisasi format pemain (kompatibel dengan database Matcha & array sederhana)
        $normalizedPlayers = array_map(function ($player, $index) {
            if (is_string($player)) {
                return [
                    'id' => $index + 1,
                    'name' => trim($player),
                    'gender' => null,
                    'level' => null,
                    'type' => 'Player',
                    'avatar' => null,
                ];
            }

            return [
                'id' => $player['id'] ?? $player['player_id'] ?? ($index + 1),
                'name' => trim($player['name'] ?? $player['nama'] ?? 'Player ' . ($index + 1)),
                'gender' => $player['gender'] ?? null,
                'level' => $player['level'] ?? null,
                'type' => $player['type'] ?? 'Player',
                'avatar' => $player['avatar'] ?? null,
            ];
        }, $players, array_keys($players));

        // 3. Validasi duplikasi pemain (berdasarkan identifier atau nama)
        $identifiers = [];
        foreach ($normalizedPlayers as $p) {
            $identifierKey = !empty($p['id']) && !is_numeric($p['id']) 
                ? 'id:' . $p['id'] 
                : 'name:' . strtolower($p['name']);

            if (isset($identifiers[$identifierKey])) {
                throw new InvalidArgumentException(
                    "Terdapat pemain duplikat terdaftar lebih dari satu kali: '{$p['name']}'."
                );
            }
            $identifiers[$identifierKey] = true;
        }

        // 4. Pembentukan Fixed Teams
        $teams = [];
        $teamIndex = 1;
        $teamLabels = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa'];

        for ($i = 0; $i < $totalPlayers; $i += 2) {
            $p1 = $normalizedPlayers[$i];
            $p2 = $normalizedPlayers[$i + 1];

            $label = $teamLabels[$teamIndex - 1] ?? (string) $teamIndex;

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

        return $teams;
    }

    /**
     * Generate jadwal pertandingan Round-Robin untuk Team Americano (Pasangan Tetap).
     * Setiap tim bertanding melawan seluruh tim lain tepat 1 kali.
     * Menggunakan Circle/Polygon Round-Robin Scheduling Algorithm dengan sistem Time Slot per Court.
     *
     * @param array $players Daftar pemain
     * @param int|array $courts Jumlah lapangan aktif (int) atau array database courts
     * @return array Struktur jadwal ronde, time slot, dan match
     * @throws InvalidArgumentException|RuntimeException Jika parameter tidak valid atau schedule tidak lengkap
     */
    public function generateTeamRounds(array $players, int|array $courts = 1): array
    {
        // Dukung input integer (courtCount) atau array database courts
        $courtList = [];
        if (is_array($courts)) {
            $courtList = array_values($courts);
            $courtCount = count($courtList);
        } else {
            $courtCount = (int) $courts;
        }

        if ($courtCount < 1) {
            throw new InvalidArgumentException(
                'Jumlah court minimal 1.'
            );
        }

        $teams = $this->formFixedTeams($players);
        $totalTeams = count($teams);

        // Siapkan array tim untuk algoritma rotasi circle
        $rotationList = $teams;

        // Jika jumlah tim ganjil, tambahkan dummy tim 'BYE' (giliran istirahat)
        if ($totalTeams % 2 !== 0) {
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
        $matchesPerRound = intdiv($numParticipants, 2);

        $rounds = [];
        $totalMatchesCount = 0;
        $globalMatchCounter = 1;

        for ($roundIdx = 0; $roundIdx < $totalRounds; $roundIdx++) {
            $roundNumber = $roundIdx + 1;
            $roundPairings = [];
            $byeTeams = [];
            $byePlayers = [];

            // 1. Kumpulkan seluruh pairing pertandingan untuk ronde ini (Circle Method)
            for ($matchIdx = 0; $matchIdx < $matchesPerRound; $matchIdx++) {
                $teamA = $rotationList[$matchIdx];
                $teamB = $rotationList[$numParticipants - 1 - $matchIdx];

                // Tangani kasus BYE (hanya terjadi jika total tim ganjil)
                if (!empty($teamA['is_bye'])) {
                    $byeTeams[] = $teamB;
                    $byePlayers = array_merge($byePlayers, $teamB['player_names']);
                    continue;
                }
                if (!empty($teamB['is_bye'])) {
                    $byeTeams[] = $teamA;
                    $byePlayers = array_merge($byePlayers, $teamA['player_names']);
                    continue;
                }

                $roundPairings[] = [
                    'team_a' => $teamA,
                    'team_b' => $teamB,
                ];
            }

            // 2. Distribusikan match ke Time Slot berdasarkan kapasitas court
            $slotChunks = array_chunk($roundPairings, $courtCount);
            $slots = [];
            $roundMatchesFlat = [];

            // Kumpulkan semua tim yang bermain di ronde ini untuk perhitungan waiting_teams per slot
            $allActiveTeamsInRound = array_filter($rotationList, fn($t) => empty($t['is_bye']));

            foreach ($slotChunks as $chunkIdx => $chunkMatches) {
                $slotNumber = $chunkIdx + 1;
                $slotMatches = [];
                $playingTeamIdsInSlot = [];

                foreach ($chunkMatches as $courtIdx => $pairing) {
                    $courtNumber = $courtIdx + 1;
                    $actualCourt = $courtList[$courtIdx] ?? null;
                    $actualCourtId = is_array($actualCourt) 
                        ? ($actualCourt['id'] ?? $actualCourt['court_id'] ?? $courtNumber) 
                        : $courtNumber;
                    $actualCourtName = is_array($actualCourt)
                        ? ($actualCourt['name'] ?? $actualCourt['nama_court'] ?? "Court {$courtNumber}")
                        : "Court {$courtNumber}";

                    $teamA = $pairing['team_a'];
                    $teamB = $pairing['team_b'];

                    $playingTeamIdsInSlot[] = $teamA['id'];
                    $playingTeamIdsInSlot[] = $teamB['id'];

                    $matchData = [
                        'schedule_key' => "R{$roundNumber}-S{$slotNumber}-C{$courtNumber}",
                        'match_id' => null, // Placeholder untuk primary key database saat persistence
                        'match_number' => $globalMatchCounter++,
                        'round_number' => $roundNumber,
                        'slot_number' => $slotNumber,
                        'court_number' => $courtNumber,
                        'court_id' => $actualCourtId,
                        'court_name' => $actualCourtName,
                        'team_a' => $teamA,
                        'team_b' => $teamB,
                        // Kompatibilitas untuk visualizer <x-court-visual> & Blade
                        'teamA_names' => $teamA['player_names'],
                        'teamB_names' => $teamB['player_names'],
                        'score_a' => 0,
                        'score_b' => 0,
                        'status' => 'Scheduled',
                    ];

                    $slotMatches[] = $matchData;
                    $roundMatchesFlat[] = $matchData;
                    $totalMatchesCount++;
                }

                // Hitung tim dan pemain yang sedang menunggu di slot ini (Waiting List per Slot)
                $waitingTeamsInSlot = array_values(array_filter(
                    $allActiveTeamsInRound,
                    fn($t) => !in_array($t['id'], $playingTeamIdsInSlot, true)
                ));

                $waitingPlayersInSlot = [];
                foreach ($waitingTeamsInSlot as $wt) {
                    $waitingPlayersInSlot = array_merge($waitingPlayersInSlot, $wt['player_names']);
                }

                $slots[] = [
                    'slot_number' => $slotNumber,
                    'slot_title' => "Slot {$slotNumber}",
                    'matches' => $slotMatches,
                    'waiting_teams' => $waitingTeamsInSlot,
                    'waiting_players' => $waitingPlayersInSlot,
                ];
            }

            $primaryMatch = $roundMatchesFlat[0] ?? null;

            $rounds[$roundNumber] = [
                'round_number' => $roundNumber,
                'round_title' => "Ronde {$roundNumber}",
                'slots' => $slots,
                'matches' => $roundMatchesFlat,
                'bye_teams' => $byeTeams,
                'bye_players' => $byePlayers,
                // Backward compatibility untuk Blade UI
                'primary_match' => $primaryMatch,
                'teamA' => $primaryMatch ? $primaryMatch['teamA_names'] : [],
                'teamB' => $primaryMatch ? $primaryMatch['teamB_names'] : [],
                'teamA_display' => $primaryMatch ? $primaryMatch['team_a']['display_name'] : '-',
                'teamB_display' => $primaryMatch ? $primaryMatch['team_b']['display_name'] : '-',
                'resting' => $byePlayers,
                'resting_teams' => $byeTeams,
            ];

            // Rotasi array Circle Method (elemen index 0 tetap, sisa elemen berputar searah jarum jam)
            $first = $rotationList[0];
            $tail = array_slice($rotationList, 1);
            $lastItem = array_pop($tail);
            array_unshift($tail, $lastItem);
            $rotationList = array_merge([$first], $tail);
        }

        // 5. Safeguard Runtime Assertion
        $expectedTotalMatches = (int) (($totalTeams * ($totalTeams - 1)) / 2);
        if ($totalMatchesCount !== $expectedTotalMatches) {
            throw new RuntimeException(
                "Schedule Team Americano tidak lengkap. Expected {$expectedTotalMatches} match, generated {$totalMatchesCount}."
            );
        }

        return [
            'format' => 'Team Americano',
            'teams' => $teams,
            'total_teams' => $totalTeams,
            'total_rounds' => $totalRounds,
            'total_matches' => $totalMatchesCount,
            'expected_matches' => $expectedTotalMatches,
            'is_schedule_complete' => true,
            'rounds' => $rounds,
        ];
    }
}
