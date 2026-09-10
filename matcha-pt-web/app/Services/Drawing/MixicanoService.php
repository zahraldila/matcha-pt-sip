<?php

namespace App\Services\Drawing;

use InvalidArgumentException;
use RuntimeException;

class MixicanoService
{
    /**
     * Bentuk fixed mixed teams.
     *
     * Setiap team harus terdiri dari:
     * - 1 Male
     * - 1 Female
     *
     * Jumlah Male harus sama dengan jumlah Female.
     *
     * @param array $players
     * @return array
     * @throws InvalidArgumentException
     */
    public function formFixedTeams(array $players): array
    {
        $players = array_values($players);
        $totalPlayers = count($players);

        if ($totalPlayers < 4) {
            throw new InvalidArgumentException(
                'Mixicano membutuhkan minimal 4 pemain (2 tim).'
            );
        }

        if ($totalPlayers % 2 !== 0) {
            throw new InvalidArgumentException(
                'Jumlah pemain Mixicano harus genap karena setiap team terdiri dari 2 pemain.'
            );
        }

        // Normalisasi pemain
        $normalizedPlayers = array_map(function ($player, $index) {
            if (is_string($player)) {
                return [
                    'id' => 'guest_' . ($index + 1),
                    'raw_id' => null,
                    'has_real_id' => false,
                    'name' => trim($player),
                    'gender' => null,
                    'level' => null,
                    'type' => 'Guest',
                    'avatar' => null,
                ];
            }

            if (is_object($player)) {
                $hasRealId = !empty($player->player_id)
                    || (!empty($player->id) && is_numeric($player->id));

                $canonicalId = $player->player_id
                    ?? $player->id
                    ?? ('guest_' . ($index + 1));

                return [
                    'id' => (string) $canonicalId,
                    'raw_id' => $hasRealId ? $canonicalId : null,
                    'has_real_id' => $hasRealId,
                    'name' => trim(
                        $player->name
                        ?? $player->nama
                        ?? 'Player ' . ($index + 1)
                    ),
                    'gender' => $player->gender ?? null,
                    'level' => $player->level ?? null,
                    'type' => $player->type
                        ?? ($hasRealId ? 'Member' : 'Player'),
                    'avatar' => $player->avatar ?? null,
                ];
            }

            $hasRealId = !empty($player['player_id'])
                || (!empty($player['id']) && is_numeric($player['id']));

            $canonicalId = $player['player_id']
                ?? $player['id']
                ?? ('guest_' . ($index + 1));

            return [
                'id' => (string) $canonicalId,
                'raw_id' => $hasRealId ? $canonicalId : null,
                'has_real_id' => $hasRealId,
                'name' => trim(
                    $player['name']
                    ?? $player['nama']
                    ?? 'Player ' . ($index + 1)
                ),
                'gender' => $player['gender'] ?? null,
                'level' => $player['level'] ?? null,
                'type' => $player['type']
                    ?? ($hasRealId ? 'Member' : 'Player'),
                'avatar' => $player['avatar'] ?? null,
            ];
        }, $players, array_keys($players));

        // Validasi duplikasi
        $seenRealIds = [];
        $seenGuestNames = [];

        foreach ($normalizedPlayers as $player) {
            if ($player['has_real_id']) {
                $realId = (string) $player['raw_id'];

                if (isset($seenRealIds[$realId])) {
                    throw new InvalidArgumentException(
                        "Terdapat pemain dengan ID duplikat: ID '{$realId}'."
                    );
                }

                $seenRealIds[$realId] = true;
            } else {
                $guestName = strtolower(trim($player['name']));

                if (
                    $guestName !== ''
                    && isset($seenGuestNames[$guestName])
                ) {
                    throw new InvalidArgumentException(
                        "Terdapat pemain guest duplikat: '{$player['name']}'."
                    );
                }

                $seenGuestNames[$guestName] = true;
            }
        }

        // Pisahkan berdasarkan gender
        $malePlayers = [];
        $femalePlayers = [];

        foreach ($normalizedPlayers as $player) {
            $gender = strtolower(trim((string) $player['gender']));

            if (in_array($gender, ['male', 'm', 'laki-laki', 'l'], true)) {
                $malePlayers[] = $player;
            } elseif (
                in_array($gender, ['female', 'f', 'perempuan', 'p'], true)
            ) {
                $femalePlayers[] = $player;
            } else {
                throw new InvalidArgumentException(
                    "Gender pemain '{$player['name']}' harus Male atau Female untuk Mixicano."
                );
            }
        }

        // Constraint utama Mixicano:
        // jumlah Male harus sama dengan Female
        if (count($malePlayers) !== count($femalePlayers)) {
            throw new InvalidArgumentException(
                'Mixicano membutuhkan jumlah pemain Male dan Female yang sama.'
            );
        }

        $teamCount = count($malePlayers);

        if ($teamCount < 2) {
            throw new InvalidArgumentException(
                'Mixicano membutuhkan minimal 2 tim.'
            );
        }

        /*
         * Bentuk fixed mixed teams.
         *
         * Male ke-i dipasangkan dengan Female ke-i.
         *
         * Contoh:
         * Male 1 + Female 1
         * Male 2 + Female 2
         * Male 3 + Female 3
         *
         * Setelah terbentuk, pasangan ini TETAP.
         */
        $teams = [];

        $teamLabels = [
            'Alpha',
            'Beta',
            'Gamma',
            'Delta',
            'Epsilon',
            'Zeta',
            'Eta',
            'Theta',
            'Iota',
            'Kappa',
            'Lambda',
            'Mu',
        ];

        // Randomize pasangan Male + Female
        shuffle($malePlayers);
        shuffle($femalePlayers);

        for ($i = 0; $i < $teamCount; $i++) {
            $male = $malePlayers[$i];
            $female = $femalePlayers[$i];

            // Stable team ID berdasarkan dua player ID
            $playerIds = [
                (string) $male['id'],
                (string) $female['id'],
            ];

            sort($playerIds, SORT_STRING);

            $stableTeamId = 'TEAM_' . implode('_', $playerIds);

            $teamIndex = $i + 1;
            $label = $teamLabels[$i] ?? (string) $teamIndex;

            $teams[] = [
                'id' => $stableTeamId,
                'team_id' => $stableTeamId,
                'index' => $teamIndex,
                'stable_id' => $stableTeamId,
                'code' => "T{$teamIndex}",
                'name' => "Team {$label}",
                'short_name' => "Team {$teamIndex}",

                'players' => [$male, $female],

                'player_names' => [
                    $male['name'],
                    $female['name'],
                ],

                'display_name' =>
                    "Team {$label} [{$male['name']} & {$female['name']}]",

                'gender_composition' => 'Male + Female',
            ];
        }

        return $teams;
    }

    /**
     * Generate Ronde 1 Mixicano.
     *
     * Tim sudah fixed mixed (Male + Female).
     * Yang diacak hanya urutan TEAM untuk menentukan matchup.
     *
     * @param array $players
     * @param int|array $courts
     * @param bool $shuffle
     * @return array
     */
    public function generateInitialRound(
        array $players,
        int|array $courts = 1,
        bool $shuffle = true
    ): array {
        $courtInfo = $this->resolveCourtParameters($courts);

        $courtCount = $courtInfo['count'];
        $courtList = $courtInfo['list'];

        // Bentuk fixed mixed teams
        $teams = $this->formFixedTeams($players);
        $totalTeams = count($teams);

        // Ronde 1: random drawing antar TEAM
        $initialTeams = $teams;

        if ($shuffle) {
            shuffle($initialTeams);
        }

        // Jika jumlah tim ganjil, satu tim BYE
        $byeTeams = [];
        $byePlayers = [];

        if ($totalTeams % 2 !== 0) {
            $byeTeam = array_pop($initialTeams);

            $byeTeams[] = $byeTeam;
            $byePlayers = $byeTeam['player_names'];
        }

        // Bentuk pairing team
        $roundPairings = [];

        for ($i = 0; $i < count($initialTeams); $i += 2) {
            $roundPairings[] = [
                'team_a' => $initialTeams[$i],
                'team_b' => $initialTeams[$i + 1],
            ];
        }

        // Validasi integritas ronde
        $this->validateRoundIntegrity(
            $roundPairings,
            $byeTeams,
            $teams
        );

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
            'format' => 'Mixicano',
            'teams' => $teams,
            'total_teams' => $totalTeams,
            'current_round' => 1,

            'rounds' => [
                1 => $round1,
            ],
        ];
    }

    /**
     * Hitung leaderboard berdasarkan hasil pertandingan yang sudah selesai.
     *
     * Ranking dilakukan di level TEAM karena partner Mixicano fixed.
     *
     * @param array $teams
     * @param array $completedMatches
     * @param string $rankBy
     * @param array $roundHistory
     * @return array
     */
    public function calculateLeaderboard(
        array $teams,
        array $completedMatches = [],
        string $rankBy = 'points',
        array $roundHistory = []
    ): array {
        if (!in_array($rankBy, ['points', 'wins'], true)) {
            throw new InvalidArgumentException(
                "rankBy harus bernilai 'points' atau 'wins'."
            );
        }

        $stats = [];

        foreach ($teams as $team) {
            $teamId = (string) (
                $team['id']
                ?? $team['team_id']
                ?? $team['stable_id']
            );

            $stats[$teamId] = [
                'team_id' => $teamId,
                'team' => $team,
                'team_name' => $team['name'],
                'display_name' =>
                    $team['display_name'] ?? $team['name'],

                'player_names' =>
                    $team['player_names'] ?? [],

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
            $status = strtolower($match['status'] ?? '');

            $isFinishedExplicit =
                !empty($match['is_completed'])
                || in_array(
                    $status,
                    ['finished', 'completed', 'final', 'done'],
                    true
                );

            $hasExplicitScores =
                isset($match['score_a'], $match['score_b'])
                && $match['score_a'] !== null
                && $match['score_b'] !== null;

            // Hanya match final yang dihitung
            if (!$isFinishedExplicit || !$hasExplicitScores) {
                continue;
            }

            if (
                in_array(
                    $status,
                    [
                        'scheduled',
                        'pending',
                        'ongoing',
                        'open',
                        'in_progress',
                    ],
                    true
                )
            ) {
                continue;
            }

            $teamAId = (string) (
                $match['team_a']['id']
                ?? $match['team_a']['team_id']
                ?? $match['team_a_id']
                ?? ''
            );

            $teamBId = (string) (
                $match['team_b']['id']
                ?? $match['team_b']['team_id']
                ?? $match['team_b_id']
                ?? ''
            );

            if (
                $teamAId === ''
                || $teamBId === ''
                || !isset($stats[$teamAId])
                || !isset($stats[$teamBId])
            ) {
                continue;
            }

            $scoreA = (int) $match['score_a'];
            $scoreB = (int) $match['score_b'];

            // Team A
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

            // Team B
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

        // Hitung riwayat BYE
        $byeCounts = $this->calculateByeCounts($roundHistory);

        foreach ($byeCounts as $teamId => $count) {
            if (isset($stats[$teamId])) {
                $stats[$teamId]['bye_count'] = $count;
            }
        }

        // Hitung point difference
        foreach ($stats as &$item) {
            $item['point_diff'] =
                $item['points_won'] - $item['points_lost'];
        }

        unset($item);

        // Ranking
        usort($stats, function ($a, $b) use ($rankBy) {
            if ($rankBy === 'wins') {
                if ($b['matches_won'] !== $a['matches_won']) {
                    return $b['matches_won']
                        <=> $a['matches_won'];
                }

                if ($b['points_won'] !== $a['points_won']) {
                    return $b['points_won']
                        <=> $a['points_won'];
                }

                return $b['point_diff']
                    <=> $a['point_diff'];
            }

            // Default: points
            if ($b['points_won'] !== $a['points_won']) {
                return $b['points_won']
                    <=> $a['points_won'];
            }

            if ($b['point_diff'] !== $a['point_diff']) {
                return $b['point_diff']
                    <=> $a['point_diff'];
            }

            return $b['matches_won']
                <=> $a['matches_won'];
        });

        // Tambahkan rank
        $rankedLeaderboard = [];

        foreach ($stats as $index => $row) {
            $row['rank'] = $index + 1;
            $rankedLeaderboard[] = $row;
        }

        return $rankedLeaderboard;
    }

    /**
     * Generate Ronde 2, 3, dst.
     *
     * Default:
     * Rank 1 vs Rank 2
     * Rank 3 vs Rank 4
     * Rank 5 vs Rank 6
     * dst.
     *
     * Tim tetap fixed.
     *
     * @param int $roundNumber
     * @param array $leaderboard
     * @param int|array $courts
     * @param array $matchHistory
     * @param array $roundHistory
     * @param array|null $previousRoundMatches
     * @param bool $preventRematch
     * @param int|null $maxRounds
     * @return array
     */
    public function generateNextRound(
        int $roundNumber,
        array $leaderboard,
        int|array $courts = 1,
        array $matchHistory = [],
        array $roundHistory = [],
        ?array $previousRoundMatches = null,
        bool $preventRematch = false,
        ?int $maxRounds = null
    ): array {
        if ($roundNumber < 2) {
            throw new InvalidArgumentException(
                'Gunakan generateInitialRound() untuk Ronde 1.'
            );
        }

        if (
            $maxRounds !== null
            && $roundNumber > $maxRounds
        ) {
            throw new RuntimeException(
                "Sesi telah mencapai batas maksimal ronde ({$maxRounds} ronde)."
            );
        }

        // Ronde sebelumnya harus selesai
        if ($previousRoundMatches !== null) {
            $this->assertPreviousRoundCompleted(
                $previousRoundMatches
            );
        }

        $courtInfo = $this->resolveCourtParameters($courts);

        $courtCount = $courtInfo['count'];
        $courtList = $courtInfo['list'];

        $totalTeams = count($leaderboard);

        if ($totalTeams < 2) {
            throw new InvalidArgumentException(
                'Leaderboard membutuhkan minimal 2 tim.'
            );
        }

        $normalizedHistory =
            $this->normalizeMatchHistory($matchHistory);

        $sortedLeaderboard = array_values($leaderboard);

        // Handle jumlah team ganjil
        $byeTeams = [];
        $byePlayers = [];

        if ($totalTeams % 2 !== 0) {
            $lastByeTeamId =
                $this->extractLastByeTeamId($roundHistory);

            $byeIndex = $this->selectFairByeTeamIndex(
                $sortedLeaderboard,
                $lastByeTeamId
            );

            $byeTeamEntry = $sortedLeaderboard[$byeIndex];

            array_splice(
                $sortedLeaderboard,
                $byeIndex,
                1
            );

            $byeTeam = $byeTeamEntry['team'];

            $byeTeams[] = $byeTeam;

            $byePlayers =
                $byeTeam['player_names'] ?? [];
        }

        // Ambil team sesuai ranking
        $activeRankedTeams = array_map(
            fn($item) => $item['team'],
            $sortedLeaderboard
        );

        // Pairing berdasarkan ranking
        $pairings = $preventRematch
            ? $this->createPairingsWithRematchAvoidance(
                $activeRankedTeams,
                $normalizedHistory['pairings']
            )
            : $this->createStrictRankedPairings(
                $activeRankedTeams
            );

        // Validasi integritas
        $allTeams = array_map(
            fn($item) => $item['team'],
            $leaderboard
        );

        $this->validateRoundIntegrity(
            $pairings,
            $byeTeams,
            $allTeams
        );

        return $this->buildRoundStructure(
            roundNumber: $roundNumber,
            pairings: $pairings,
            courtCount: $courtCount,
            courtList: $courtList,
            byeTeams: $byeTeams,
            byePlayers: $byePlayers,
            allActiveTeams: $allTeams
        );
    }

    /**
     * Pairing standar:
     *
     * Rank 1 vs 2
     * Rank 3 vs 4
     * Rank 5 vs 6
     * dst.
     */
    protected function createStrictRankedPairings(
        array $rankedTeams
    ): array {
        $pairings = [];

        $count = count($rankedTeams);

        for ($i = 0; $i < $count; $i += 2) {
            if (isset($rankedTeams[$i + 1])) {
                $pairings[] = [
                    'team_a' => $rankedTeams[$i],
                    'team_b' => $rankedTeams[$i + 1],
                ];
            }
        }

        return $pairings;
    }

    /**
     * Pairing dengan opsi menghindari rematch beruntun.
     *
     * Best effort:
     * team akan mencari lawan dengan ranking terdekat
     * yang tidak bertemu pada pairing sebelumnya.
     */
    protected function createPairingsWithRematchAvoidance(
        array $rankedTeams,
        array $historyPairs
    ): array {
        $pairings = [];
        $used = [];

        $count = count($rankedTeams);

        for ($i = 0; $i < $count; $i++) {
            if (isset($used[$i])) {
                continue;
            }

            $teamA = $rankedTeams[$i];
            $used[$i] = true;

            $bestOpponentIdx = null;

            for ($j = $i + 1; $j < $count; $j++) {
                if (isset($used[$j])) {
                    continue;
                }

                $teamB = $rankedTeams[$j];

                $key = $this->getPairKey(
                    $teamA['id'] ?? $teamA['team_id'],
                    $teamB['id'] ?? $teamB['team_id']
                );

                if (!isset($historyPairs[$key])) {
                    $bestOpponentIdx = $j;
                    break;
                }
            }

            // Kalau tidak ada lawan yang belum pernah bertemu,
            // gunakan lawan ranking terdekat yang tersisa.
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
     * Pilih BYE secara adil.
     *
     * Prioritas:
     * 1. Team dengan bye paling sedikit.
     * 2. Hindari team yang baru saja BYE.
     * 3. Jika masih sama, pilih ranking paling bawah.
     */
    protected function selectFairByeTeamIndex(
        array $leaderboard,
        int|string|null $lastByeTeamId = null
    ): int {
        $minByeCount = PHP_INT_MAX;

        foreach ($leaderboard as $entry) {
            $count = $entry['bye_count'] ?? 0;

            if ($count < $minByeCount) {
                $minByeCount = $count;
            }
        }

        $candidateIndices = [];

        foreach ($leaderboard as $index => $entry) {
            if (($entry['bye_count'] ?? 0) === $minByeCount) {
                $candidateIndices[] = $index;
            }
        }

        // Hindari BYE berturut-turut
        if (
            count($candidateIndices) > 1
            && $lastByeTeamId !== null
        ) {
            $filtered = array_filter(
                $candidateIndices,
                function ($index) use (
                    $leaderboard,
                    $lastByeTeamId
                ) {
                    $teamId = (string) (
                        $leaderboard[$index]['team_id']
                        ?? $leaderboard[$index]['team']['id']
                        ?? ''
                    );

                    return $teamId !== (string) $lastByeTeamId;
                }
            );

            if (!empty($filtered)) {
                $candidateIndices = array_values($filtered);
            }
        }

        // Ranking paling bawah
        return end($candidateIndices);
    }

    /**
     * Validasi ronde sebelumnya sudah selesai.
     */
    public function assertPreviousRoundCompleted(
        array $previousRoundMatches
    ): void {
        foreach ($previousRoundMatches as $match) {
            $status = strtolower($match['status'] ?? '');

            $isFinished =
                !empty($match['is_completed'])
                || in_array(
                    $status,
                    ['finished', 'completed', 'final', 'done'],
                    true
                );

            $hasScores =
                isset($match['score_a'], $match['score_b'])
                && $match['score_a'] !== null
                && $match['score_b'] !== null;

            if (
                !$isFinished
                || !$hasScores
                || in_array(
                    $status,
                    [
                        'scheduled',
                        'ongoing',
                        'pending',
                        'open',
                        'in_progress',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Ronde berikutnya tidak dapat dibuat karena ronde sebelumnya belum selesai.'
                );
            }
        }
    }

    /**
     * Validasi setiap team hanya muncul satu kali
     * dalam satu ronde.
     */
    public function validateRoundIntegrity(
        array $pairings,
        array $byeTeams,
        array $allTeams
    ): void {
        $teamOccurrences = [];

        foreach ($pairings as $pair) {
            $teamAId = (string) (
                $pair['team_a']['id']
                ?? $pair['team_a']['team_id']
                ?? ''
            );

            $teamBId = (string) (
                $pair['team_b']['id']
                ?? $pair['team_b']['team_id']
                ?? ''
            );

            if ($teamAId === '' || $teamBId === '') {
                throw new RuntimeException(
                    'Pairing match tidak memiliki identitas tim yang valid.'
                );
            }

            if ($teamAId === $teamBId) {
                throw new RuntimeException(
                    "Sebuah tim tidak dapat bertanding melawan dirinya sendiri: '{$teamAId}'."
                );
            }

            $teamOccurrences[$teamAId] =
                ($teamOccurrences[$teamAId] ?? 0) + 1;

            $teamOccurrences[$teamBId] =
                ($teamOccurrences[$teamBId] ?? 0) + 1;
        }

        foreach ($byeTeams as $byeTeam) {
            $byeId = (string) (
                $byeTeam['id']
                ?? $byeTeam['team_id']
                ?? ''
            );

            if ($byeId !== '') {
                $teamOccurrences[$byeId] =
                    ($teamOccurrences[$byeId] ?? 0) + 1;
            }
        }

        foreach ($allTeams as $team) {
            $teamId = (string) (
                $team['id']
                ?? $team['team_id']
                ?? ''
            );

            $count = $teamOccurrences[$teamId] ?? 0;

            if ($count === 0) {
                throw new RuntimeException(
                    "Integritas ronde gagal: Tim '{$team['name']}' tidak terdaftar dalam match maupun BYE."
                );
            }

            if ($count > 1) {
                throw new RuntimeException(
                    "Integritas ronde gagal: Tim '{$team['name']}' muncul lebih dari 1 kali dalam ronde yang sama."
                );
            }
        }
    }

    /**
     * Resolve parameter court.
     */
    protected function resolveCourtParameters(
        int|array $courts
    ): array {
        if (is_array($courts)) {
            $courtList = array_values($courts);
            $courtCount = count($courtList);
        } else {
            $courtList = [];
            $courtCount = (int) $courts;
        }

        if ($courtCount < 1) {
            throw new InvalidArgumentException(
                'Jumlah court minimal 1 untuk menjalankan drawing pertandingan.'
            );
        }

        return [
            'count' => $courtCount,
            'list' => $courtList,
        ];
    }

    /**
     * Normalisasi match history.
     */
    public function normalizeMatchHistory(
        array $matchHistory
    ): array {
        $pairings = [];

        foreach ($matchHistory as $key => $item) {
            if (is_array($item)) {
                $teamAId = $item['team_a']['id']
                    ?? $item['team_a']['team_id']
                    ?? $item['team_a_id']
                    ?? null;

                $teamBId = $item['team_b']['id']
                    ?? $item['team_b']['team_id']
                    ?? $item['team_b_id']
                    ?? null;

                if (
                    $teamAId !== null
                    && $teamBId !== null
                ) {
                    $pairings[
                        $this->getPairKey(
                            $teamAId,
                            $teamBId
                        )
                    ] = true;
                }

                continue;
            }

            if (is_string($key)) {
                $pairings[$key] = true;
            }
        }

        return [
            'pairings' => $pairings,
        ];
    }

    /**
     * Hitung jumlah BYE per team.
     */
    public function calculateByeCounts(
        array $roundHistory
    ): array {
        $counts = [];

        foreach ($roundHistory as $round) {
            if (
                !empty($round['bye_teams'])
                && is_array($round['bye_teams'])
            ) {
                foreach ($round['bye_teams'] as $byeTeam) {
                    $teamId = (string) (
                        $byeTeam['id']
                        ?? $byeTeam['team_id']
                        ?? $byeTeam['stable_id']
                        ?? ''
                    );

                    if ($teamId !== '') {
                        $counts[$teamId] =
                            ($counts[$teamId] ?? 0) + 1;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * Ambil ID team yang mendapat BYE pada ronde terakhir.
     */
    protected function extractLastByeTeamId(
        array $roundHistory
    ): int|string|null {
        if (empty($roundHistory)) {
            return null;
        }

        $lastRound = end($roundHistory);

        if (
            !empty($lastRound['bye_teams'])
            && is_array($lastRound['bye_teams'])
        ) {
            $firstBye = reset($lastRound['bye_teams']);

            return $firstBye['id']
                ?? $firstBye['team_id']
                ?? null;
        }

        return null;
    }

    /**
     * Membentuk struktur ronde, slot, dan match.
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
        $slotChunks = array_chunk(
            $pairings,
            $courtCount
        );

        $slots = [];
        $roundMatchesFlat = [];
        $matchCounter = 1;

        foreach ($slotChunks as $chunkIndex => $chunkMatches) {
            $slotNumber = $chunkIndex + 1;
            $slotMatches = [];

            foreach (
                $chunkMatches as $courtIndex => $pairing
            ) {
                $courtNumber = $courtIndex + 1;

                $actualCourt =
                    $courtList[$courtIndex] ?? null;

                $actualCourtId = is_array($actualCourt)
                    ? (
                        $actualCourt['id']
                        ?? $actualCourt['court_id']
                        ?? $courtNumber
                    )
                    : $courtNumber;

                $actualCourtName = is_array($actualCourt)
                    ? (
                        $actualCourt['name']
                        ?? $actualCourt['nama_court']
                        ?? "Court {$courtNumber}"
                    )
                    : "Court {$courtNumber}";

                $teamA = $pairing['team_a'];
                $teamB = $pairing['team_b'];

                $matchData = [
                    'schedule_key' =>
                        "R{$roundNumber}-S{$slotNumber}-C{$courtNumber}",

                    'match_id' => null,

                    'match_number' => $matchCounter++,

                    'round' => $roundNumber,
                    'round_number' => $roundNumber,

                    'slot' => $slotNumber,
                    'slot_number' => $slotNumber,

                    'court' => $courtNumber,
                    'court_number' => $courtNumber,
                    'court_id' => $actualCourtId,
                    'court_name' => $actualCourtName,

                    'team_a' => $teamA,
                    'team_b' => $teamB,

                    'teamA' => $teamA['player_names'] ?? [],
                    'teamB' => $teamB['player_names'] ?? [],

                    'team_a_names' =>
                        $teamA['player_names'] ?? [],

                    'team_b_names' =>
                        $teamB['player_names'] ?? [],

                    'teamA_names' =>
                        $teamA['player_names'] ?? [],

                    'teamB_names' =>
                        $teamB['player_names'] ?? [],

                    'status' => 'scheduled',
                    'score_a' => null,
                    'score_b' => null,
                ];

                $slotMatches[] = $matchData;
                $roundMatchesFlat[] = $matchData;
            }

            // Team yang menunggu di slot ini
            $playingTeamIds = [];

            foreach ($chunkMatches as $pairing) {
                $playingTeamIds[] =
                    $pairing['team_a']['id']
                    ?? $pairing['team_a']['team_id'];

                $playingTeamIds[] =
                    $pairing['team_b']['id']
                    ?? $pairing['team_b']['team_id'];
            }

            $waitingTeams = array_values(
                array_filter(
                    $allActiveTeams,
                    fn($team) =>
                        !in_array(
                            $team['id'],
                            $playingTeamIds,
                            true
                        )
                )
            );

            $waitingPlayers = [];

            foreach ($waitingTeams as $waitingTeam) {
                $waitingPlayers = array_merge(
                    $waitingPlayers,
                    $waitingTeam['player_names'] ?? []
                );
            }

            $slots[] = [
                'slot_number' => $slotNumber,
                'slot_title' => "Slot {$slotNumber}",
                'matches' => $slotMatches,
                'waiting_teams' => $waitingTeams,
                'waiting_players' => $waitingPlayers,
            ];
        }

        $primaryMatch =
            $roundMatchesFlat[0] ?? null;

        return [
            'round_number' => $roundNumber,
            'round_title' => "Ronde {$roundNumber}",

            'slots' => $slots,

            'matches' => $roundMatchesFlat,

            'bye_teams' => $byeTeams,
            'bye_players' => $byePlayers,

            // Backward compatibility
            'primary_match' => $primaryMatch,

            'teamA' =>
                $primaryMatch['teamA_names'] ?? [],

            'teamB' =>
                $primaryMatch['teamB_names'] ?? [],

            'teamA_display' =>
                $primaryMatch['team_a']['display_name']
                ?? '-',

            'teamB_display' =>
                $primaryMatch['team_b']['display_name']
                ?? '-',

            'resting' => $byePlayers,
            'resting_teams' => $byeTeams,
        ];
    }

    /**
     * Buat pair key yang tidak bergantung urutan team.
     */
    protected function getPairKey(
        int|string $teamAId,
        int|string $teamBId
    ): string {
        $ids = [
            (string) $teamAId,
            (string) $teamBId,
        ];

        sort($ids, SORT_STRING);

        return implode('-', $ids);
    }
}