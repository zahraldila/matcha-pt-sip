<?php

namespace App\Services\Drawing;

use InvalidArgumentException;
use RuntimeException;

class TeamMexicanoService
{
    /**
     * Bentuk pasangan tim tetap (Fixed Pairs) dari daftar pemain yang masuk.
     *
     * @param array $players Daftar pemain (array of string atau array associative)
     * @return array List tim tetap dengan komposisi 2 pemain per tim
     * @throws InvalidArgumentException Jika jumlah pemain < 4 atau ganjil
     */
    public function formFixedTeams(array $players): array
    {
        // 1. Reset array keys
        $players = array_values($players);
        $totalPlayers = count($players);

        if ($totalPlayers < 4) {
            throw new InvalidArgumentException(
                'Team Mexicano membutuhkan minimal 4 pemain (2 tim).'
            );
        }

        if ($totalPlayers % 2 !== 0) {
            throw new InvalidArgumentException(
                'Jumlah pemain Team Mexicano harus genap karena setiap team terdiri dari 2 pemain.'
            );
        }

        // 2. Normalisasi format pemain
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

        // 3. Validasi duplikasi pemain
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
     * Generate Ronde 1 (Initial Drawing) untuk Team Mexicano.
     * Karena belum ada klasemen di awal, tim dipasangkan secara acak / seeding awal.
     *
     * @param array $players Daftar pemain
     * @param int|array $courts Jumlah court (int) atau array database courts
     * @param bool $shuffle Apakah urutan tim diacak di awal
     * @return array Struktur Ronde 1 lengkap
     */
    public function generateInitialRound(array $players, int|array $courts = 1, bool $shuffle = false): array
    {
        $teams = $this->formFixedTeams($players);
        $totalTeams = count($teams);

        $courtList = is_array($courts) ? array_values($courts) : [];
        $courtCount = is_array($courts) ? count($courtList) : max(1, (int) $courts);

        $initialTeams = $teams;
        if ($shuffle) {
            shuffle($initialTeams);
        }

        // Handle kasus jumlah tim ganjil (BYE)
        $byeTeams = [];
        $byePlayers = [];
        if ($totalTeams % 2 !== 0) {
            // Tim terakhir mendapat giliran istirahat (BYE) di Ronde 1
            $byeTeam = array_pop($initialTeams);
            $byeTeams[] = $byeTeam;
            $byePlayers = $byeTeam['player_names'];
        }

        $roundPairings = [];
        for ($i = 0; $i < count($initialTeams); $i += 2) {
            $roundPairings[] = [
                'team_a' => $initialTeams[$i],
                'team_b' => $initialTeams[$i + 1],
            ];
        }

        $round1 = $this->buildRoundStructure(
            roundNumber: 1,
            pairings: $roundPairings,
            courtCount: $courtCount,
            courtList: $courtList,
            byeTeams: $byeTeams,
            byePlayers: $byePlayers,
            allActiveTeams: $teams
        );

        return [
            'format' => 'Team Mexicano',
            'teams' => $teams,
            'total_teams' => $totalTeams,
            'current_round' => 1,
            'rounds' => [
                1 => $round1,
            ],
        ];
    }

    /**
     * Hitung Leaderboard terkini dari match-match yang telah selesai.
     *
     * @param array $teams Daftar semua tim terdaftar
     * @param array $completedMatches Daftar match yang sudah memiliki skor
     * @return array Leaderboard terurut dari peringkat 1 hingga N
     */
    public function calculateLeaderboard(array $teams, array $completedMatches): array
    {
        $stats = [];

        foreach ($teams as $team) {
            $teamId = $team['id'];
            $stats[$teamId] = [
                'team_id' => $teamId,
                'team' => $team,
                'team_name' => $team['name'],
                'display_name' => $team['display_name'],
                'player_names' => $team['player_names'],
                'matches_played' => 0,
                'matches_won' => 0,
                'matches_drawn' => 0,
                'matches_lost' => 0,
                'points_won' => 0,
                'points_lost' => 0,
                'point_diff' => 0,
                'bye_count' => 0,
            ];
        }

        foreach ($completedMatches as $match) {
            $scoreA = (int) ($match['score_a'] ?? 0);
            $scoreB = (int) ($match['score_b'] ?? 0);
            
            // Skip match yang belum ada skor atau belum dimainkan
            if (!isset($match['score_a']) && !isset($match['score_b'])) {
                continue;
            }

            $teamAId = $match['team_a']['id'] ?? $match['team_a_id'] ?? null;
            $teamBId = $match['team_b']['id'] ?? $match['team_b_id'] ?? null;

            if ($teamAId && isset($stats[$teamAId])) {
                $stats[$teamAId]['matches_played']++;
                $stats[$teamAId]['points_won'] += $scoreA;
                $stats[$teamAId]['points_lost'] += $scoreB;

                if ($scoreA > $scoreB) {
                    $stats[$teamAId]['matches_won']++;
                } elseif ($scoreA < $scoreB) {
                    $stats[$teamAId]['matches_lost']++;
                } else {
                    $stats[$teamAId]['matches_drawn']++;
                }
            }

            if ($teamBId && isset($stats[$teamBId])) {
                $stats[$teamBId]['matches_played']++;
                $stats[$teamBId]['points_won'] += $scoreB;
                $stats[$teamBId]['points_lost'] += $scoreA;

                if ($scoreB > $scoreA) {
                    $stats[$teamBId]['matches_won']++;
                } elseif ($scoreB < $scoreA) {
                    $stats[$teamBId]['matches_lost']++;
                } else {
                    $stats[$teamBId]['matches_drawn']++;
                }
            }
        }

        // Hitung selisih poin
        foreach ($stats as &$item) {
            $item['point_diff'] = $item['points_won'] - $item['points_lost'];
        }
        unset($item);

        // Urutkan klasemen Mexicano:
        // 1. Points Won (Tertinggi)
        // 2. Point Difference (Tertinggi)
        // 3. Matches Won (Tertinggi)
        usort($stats, function ($a, $b) {
            if ($b['points_won'] !== $a['points_won']) {
                return $b['points_won'] <=> $a['points_won'];
            }
            if ($b['point_diff'] !== $a['point_diff']) {
                return $b['point_diff'] <=> $a['point_diff'];
            }
            return $b['matches_won'] <=> $a['matches_won'];
        });

        // Tetapkan nomor Rank (1..N)
        $rankedLeaderboard = [];
        $rank = 1;
        foreach ($stats as $row) {
            $row['rank'] = $rank++;
            $rankedLeaderboard[] = $row;
        }

        return $rankedLeaderboard;
    }

    /**
     * Generate Ronde Berikutnya (Ronde 2, 3, dst.) untuk Team Mexicano.
     * Menggunakan Swiss-System Ranking: Rank 1 vs Rank 2 (Court 1), Rank 3 vs Rank 4 (Court 2), dst.
     *
     * @param int $roundNumber Nomor ronde yang akan di-generate (misal 2, 3, dst.)
     * @param array $leaderboard Hasil klasemen dari calculateLeaderboard()
     * @param int|array $courts Jumlah court (int) atau array database courts
     * @param array $matchHistory Riwayat pairing yang sudah terjadi (opsional untuk rematch prevention)
     * @return array Struktur ronde baru
     */
    public function generateNextRound(
        int $roundNumber,
        array $leaderboard,
        int|array $courts = 1,
        array $matchHistory = []
    ): array {
        if ($roundNumber < 2) {
            throw new InvalidArgumentException('Gunakan generateInitialRound() untuk Ronde 1.');
        }

        $totalTeams = count($leaderboard);
        if ($totalTeams < 2) {
            throw new InvalidArgumentException('Leaderboard membutuhkan minimal 2 tim.');
        }

        $courtList = is_array($courts) ? array_values($courts) : [];
        $courtCount = is_array($courts) ? count($courtList) : max(1, (int) $courts);

        $sortedTeams = array_map(fn($item) => $item['team'], $leaderboard);

        // 1. Tangani tim ganjil (BYE)
        $byeTeams = [];
        $byePlayers = [];
        if ($totalTeams % 2 !== 0) {
            $byeIndex = count($sortedTeams) - 1;
            $byeTeam = $sortedTeams[$byeIndex];
            array_splice($sortedTeams, $byeIndex, 1);

            $byeTeams[] = $byeTeam;
            $byePlayers = $byeTeam['player_names'];
        }

        // 2. Dynamic Swiss Pairing dengan Rematch Avoidance (Best Effort)
        $pairings = $this->createRankedPairings($sortedTeams, $matchHistory);

        // 3. Bangun struktur ronde lengkap
        $allActiveTeams = array_map(fn($item) => $item['team'], $leaderboard);

        return $this->buildRoundStructure(
            roundNumber: $roundNumber,
            pairings: $pairings,
            courtCount: $courtCount,
            courtList: $courtList,
            byeTeams: $byeTeams,
            byePlayers: $byePlayers,
            allActiveTeams: $allActiveTeams
        );
    }

    /**
     * Buat pasangan pertandingan berdasarkan peringkat dengan pencegahan rematch beruntun.
     */
    protected function createRankedPairings(array $rankedTeams, array $matchHistory = []): array
    {
        $pairings = [];
        $used = [];
        $count = count($rankedTeams);

        for ($i = 0; $i < $count; $i++) {
            if (isset($used[$i])) {
                continue;
            }

            $teamA = $rankedTeams[$i];
            $used[$i] = true;

            // Cari lawan terbaik
            $bestOpponentIdx = null;

            for ($j = $i + 1; $j < $count; $j++) {
                if (isset($used[$j])) {
                    continue;
                }

                $teamB = $rankedTeams[$j];
                $pairKey = $this->getPairKey($teamA['id'], $teamB['id']);

                if (!isset($matchHistory[$pairKey])) {
                    $bestOpponentIdx = $j;
                    break;
                }

                if ($bestOpponentIdx === null) {
                    $bestOpponentIdx = $j;
                }
            }

            if ($bestOpponentIdx === null) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (!isset($used[$j])) {
                        $bestOpponentIdx = $j;
                        break;
                    }
                }
            }

            if ($bestOpponentIdx !== null) {
                $teamB = $rankedTeams[$bestOpponentIdx];
                $used[$bestOpponentIdx] = true;

                $pairings[] = [
                    'team_a' => $teamA,
                    'team_b' => $teamB,
                ];
            }
        }

        return $pairings;
    }

    /**
     * Helper pembuat struktur data ronde yang seragam untuk Blade UI & GameController.
     */
    protected function buildRoundStructure(
        int $roundNumber,
        array $pairings,
        int $courtCount,
        array $courtList,
        array $byeTeams,
        array $byePlayers,
        array $allActiveTeams
    ): array {
        $slotChunks = array_chunk($pairings, $courtCount);
        $slots = [];
        $roundMatchesFlat = [];
        $matchCounter = 1;

        $nonByeTeams = array_filter($allActiveTeams, function ($t) use ($byeTeams) {
            foreach ($byeTeams as $bt) {
                if ($t['id'] === $bt['id']) {
                    return false;
                }
            }
            return true;
        });

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
                    'match_id' => null,
                    'match_number' => $matchCounter++,
                    'round_number' => $roundNumber,
                    'slot_number' => $slotNumber,
                    'court_number' => $courtNumber,
                    'court_id' => $actualCourtId,
                    'court_name' => $actualCourtName,
                    'team_a' => $teamA,
                    'team_b' => $teamB,
                    'teamA_names' => $teamA['player_names'],
                    'teamB_names' => $teamB['player_names'],
                    'score_a' => 0,
                    'score_b' => 0,
                    'status' => 'Scheduled',
                ];

                $slotMatches[] = $matchData;
                $roundMatchesFlat[] = $matchData;
            }

            $waitingTeamsInSlot = array_values(array_filter(
                $nonByeTeams,
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

        return [
            'round_number' => $roundNumber,
            'round_title' => "Ronde {$roundNumber}",
            'slots' => $slots,
            'matches' => $roundMatchesFlat,
            'bye_teams' => $byeTeams,
            'bye_players' => $byePlayers,
            'primary_match' => $primaryMatch,
            'teamA' => $primaryMatch ? $primaryMatch['teamA_names'] : [],
            'teamB' => $primaryMatch ? $primaryMatch['teamB_names'] : [],
            'teamA_display' => $primaryMatch ? $primaryMatch['team_a']['display_name'] : '-',
            'teamB_display' => $primaryMatch ? $primaryMatch['team_b']['display_name'] : '-',
            'resting' => $byePlayers,
            'resting_teams' => $byeTeams,
        ];
    }

    /**
     * Helper key pasangan untuk pencatatan riwayat pertandingan.
     */
    protected function getPairKey(int|string $teamAId, int|string $teamBId): string
    {
        $ids = [(string) $teamAId, (string) $teamBId];
        sort($ids);
        return implode('vs', $ids);
    }
}
