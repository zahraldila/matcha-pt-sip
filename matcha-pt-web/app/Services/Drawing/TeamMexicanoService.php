<?php

namespace App\Services\Drawing;

use InvalidArgumentException;
use RuntimeException;

class TeamMexicanoService
{
    /**
     * Bentuk pasangan tim tetap (Fixed Pairs) dari daftar pemain yang masuk.
     * Identitas team dibuat kanonikal dan stabil (tidak bergantung pada urutan input pemain).
     *
     * @param array $players Daftar pemain (array of string atau array associative)
     * @return array List tim tetap dengan komposisi 2 pemain per tim
     * @throws InvalidArgumentException Jika jumlah pemain < 4, ganjil, atau ada duplikat
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

            $hasRealId = !empty($player['player_id']) || (!empty($player['id']) && is_numeric($player['id']));
            $canonicalId = $player['player_id'] ?? $player['id'] ?? ('guest_' . ($index + 1));

            return [
                'id' => (string) $canonicalId,
                'raw_id' => $hasRealId ? $canonicalId : null,
                'has_real_id' => $hasRealId,
                'name' => trim($player['name'] ?? $player['nama'] ?? 'Player ' . ($index + 1)),
                'gender' => $player['gender'] ?? null,
                'level' => $player['level'] ?? null,
                'type' => $player['type'] ?? ($hasRealId ? 'Member' : 'Player'),
                'avatar' => $player['avatar'] ?? null,
            ];
        }, $players, array_keys($players));

        // 3. Validasi duplikasi pemain:
        // - Registered player (memiliki database ID): divalidasi via ID (nama sama diperbolehkan jika beda ID).
        // - Guest / Unregistered player: divalidasi via normalized name.
        $seenRealIds = [];
        $seenGuestNames = [];

        foreach ($normalizedPlayers as $p) {
            if ($p['has_real_id']) {
                $realId = (string) $p['raw_id'];
                if (isset($seenRealIds[$realId])) {
                    throw new InvalidArgumentException(
                        "Terdapat pemain dengan ID duplikat terdaftar lebih dari satu kali: ID '{$realId}'."
                    );
                }
                $seenRealIds[$realId] = true;
            } else {
                $guestName = strtolower($p['name']);
                if (!empty($guestName) && isset($seenGuestNames[$guestName])) {
                    throw new InvalidArgumentException(
                        "Terdapat pemain guest duplikat terdaftar lebih dari satu kali: '{$p['name']}'."
                    );
                }
                $seenGuestNames[$guestName] = true;
            }
        }

        // 4. Pembentukan Fixed Teams dengan Stable Canonical Identity
        $teams = [];
        $teamIndex = 1;
        $teamLabels = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa', 'Lambda', 'Mu'];

        for ($i = 0; $i < $totalPlayers; $i += 2) {
            $p1 = $normalizedPlayers[$i];
            $p2 = $normalizedPlayers[$i + 1];

            // Canonical Stable Team ID: tidak bergantung urutan p1/p2 maupun urutan index array
            $playerIds = [(string) $p1['id'], (string) $p2['id']];
            sort($playerIds, SORT_STRING);
            $stableTeamId = 'TEAM_' . implode('_', $playerIds);

            $label = $teamLabels[$teamIndex - 1] ?? (string) $teamIndex;

            $teams[] = [
                'id' => $stableTeamId,
                'team_id' => $stableTeamId,
                'index' => $teamIndex,
                'stable_id' => $stableTeamId,
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
     * Ronde 1 diacak secara random/initial draw ($shuffle = true).
     *
     * @param array $players Daftar pemain
     * @param int|array $courts Jumlah court (int) atau array database courts
     * @param bool $shuffle Apakah urutan tim diacak di awal (default: true)
     * @return array Struktur Ronde 1 lengkap
     * @throws InvalidArgumentException Jika court < 1
     */
    public function generateInitialRound(array $players, int|array $courts = 1, bool $shuffle = true): array
    {
        $courtInfo = $this->resolveCourtParameters($courts);
        $courtCount = $courtInfo['count'];
        $courtList = $courtInfo['list'];

        $teams = $this->formFixedTeams($players);
        $totalTeams = count($teams);

        $initialTeams = $teams;
        if ($shuffle) {
            shuffle($initialTeams);
        }

        // Handle kasus jumlah tim ganjil (BYE di Ronde 1)
        $byeTeams = [];
        $byePlayers = [];
        if ($totalTeams % 2 !== 0) {
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

        // Validasi integritas ronde 1
        $this->validateRoundIntegrity($roundPairings, $byeTeams, $teams);

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
     * Hitung Leaderboard terkini dari match-match yang telah SELESAI (Completed/Finished/Final).
     * Match status 'scheduled', 'ongoing', 'pending', atau match tanpa skor final TIDAK dihitung.
     *
     * @param array $teams Daftar semua tim terdaftar
     * @param array $completedMatches Daftar match yang sudah selesai
     * @param string $rankBy Kriteria perankingan: 'points' (default) atau 'wins'
     * @param array $roundHistory Riwayat ronde sebelumnya untuk menghitung BYE history
     * @return array Leaderboard terurut dari peringkat 1 hingga N
     * @throws InvalidArgumentException Jika rankBy tidak valid
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
            $teamId = (string) ($team['id'] ?? $team['team_id'] ?? $team['stable_id']);
            $stats[$teamId] = [
                'team_id' => $teamId,
                'team' => $team,
                'team_name' => $team['name'],
                'display_name' => $team['display_name'] ?? $team['name'],
                'player_names' => $team['player_names'] ?? [],
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
            $isFinishedExplicit = !empty($match['is_completed']) || in_array($status, ['finished', 'completed', 'final', 'done'], true);
            $hasExplicitScores = isset($match['score_a'], $match['score_b']) && $match['score_a'] !== null && $match['score_b'] !== null;

            // Strict Filter: Match ongoing, scheduled, pending, atau belum ada skor final tidak dihitung
            if (!$isFinishedExplicit || !$hasExplicitScores) {
                continue;
            }

            if (in_array($status, ['scheduled', 'pending', 'ongoing', 'open', 'in_progress'], true)) {
                continue;
            }

            $scoreA = (int) $match['score_a'];
            $scoreB = (int) $match['score_b'];

            $teamAId = (string) ($match['team_a']['id'] ?? $match['team_a']['team_id'] ?? $match['team_a_id'] ?? '');
            $teamBId = (string) ($match['team_b']['id'] ?? $match['team_b']['team_id'] ?? $match['team_b_id'] ?? '');

            if ($teamAId !== '' && isset($stats[$teamAId])) {
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

            if ($teamBId !== '' && isset($stats[$teamBId])) {
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

        // Hitung riwayat BYE dari struktur round history
        $byeCounts = $this->calculateByeCounts($roundHistory);
        foreach ($byeCounts as $bTeamId => $count) {
            if (isset($stats[$bTeamId])) {
                $stats[$bTeamId]['bye_count'] = $count;
            }
        }

        // Hitung selisih poin
        foreach ($stats as &$item) {
            $item['point_diff'] = $item['points_won'] - $item['points_lost'];
        }
        unset($item);

        // Sorting leaderboard:
        // Mode 'points' (Standar Mexicano): Points Won -> Point Diff -> Matches Won
        // Mode 'wins': Matches Won -> Points Won -> Point Diff
        usort($stats, function ($a, $b) use ($rankBy) {
            if ($rankBy === 'wins') {
                if ($b['matches_won'] !== $a['matches_won']) {
                    return $b['matches_won'] <=> $a['matches_won'];
                }
                if ($b['points_won'] !== $a['points_won']) {
                    return $b['points_won'] <=> $a['points_won'];
                }
                return $b['point_diff'] <=> $a['point_diff'];
            }

            if ($b['points_won'] !== $a['points_won']) {
                return $b['points_won'] <=> $a['points_won'];
            }
            if ($b['point_diff'] !== $a['point_diff']) {
                return $b['point_diff'] <=> $a['point_diff'];
            }
            return $b['matches_won'] <=> $a['matches_won'];
        });

        // Tetapkan nomor peringkat (Rank 1..N)
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
     * Menggunakan Default Matcha Pairing Strategy (Rank 1 vs 2 di Court 1, Rank 3 vs 4 di Court 2, dst.).
     *
     * @param int $roundNumber Nomor ronde yang akan dibuat (>= 2)
     * @param array $leaderboard Hasil klasemen dari calculateLeaderboard()
     * @param int|array $courts Jumlah court (int) atau array database courts
     * @param array $matchHistory Riwayat match (array DB atau pair key list)
     * @param array $roundHistory Riwayat ronde sebelumnya (untuk validasi & fair BYE)
     * @param array|null $previousRoundMatches Daftar match dari ronde sebelumnya untuk verifikasi kelengkapan skor
     * @param bool $preventRematch Opsi pencegahan rematch beruntun (default: false)
     * @param int|null $maxRounds Batas maksimal ronde turnamen (jika ada)
     * @return array Struktur ronde baru
     * @throws InvalidArgumentException|RuntimeException
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
            throw new InvalidArgumentException('Gunakan generateInitialRound() untuk Ronde 1.');
        }

        if ($maxRounds !== null && $roundNumber > $maxRounds) {
            throw new RuntimeException("Sesi telah mencapai batas maksimal ronde ({$maxRounds} ronde).");
        }

        // Validasi: Ronde sebelumnya HARUS selesai secara final sebelum ronde berikutnya dapat dibuat
        if ($previousRoundMatches !== null) {
            $this->assertPreviousRoundCompleted($previousRoundMatches);
        }

        $courtInfo = $this->resolveCourtParameters($courts);
        $courtCount = $courtInfo['count'];
        $courtList = $courtInfo['list'];

        $totalTeams = count($leaderboard);
        if ($totalTeams < 2) {
            throw new InvalidArgumentException('Leaderboard membutuhkan minimal 2 tim.');
        }

        $normalizedHistory = $this->normalizeMatchHistory($matchHistory);
        $sortedLeaderboard = $leaderboard;

        // 1. Tangani Tim Ganjil (Fair BYE Rotation)
        $byeTeams = [];
        $byePlayers = [];
        if ($totalTeams % 2 !== 0) {
            $lastByeTeamId = $this->extractLastByeTeamId($roundHistory);
            $byeIndex = $this->selectFairByeTeamIndex($sortedLeaderboard, $lastByeTeamId);
            $byeTeamEntry = $sortedLeaderboard[$byeIndex];
            array_splice($sortedLeaderboard, $byeIndex, 1);

            $byeTeam = $byeTeamEntry['team'];
            $byeTeams[] = $byeTeam;
            $byePlayers = $byeTeam['player_names'] ?? [];
        }

        $activeRankedTeams = array_map(fn($item) => $item['team'], $sortedLeaderboard);

        // 2. Default Matcha Team Mexicano Pairing Strategy (Rank 1 vs 2, Rank 3 vs 4, dst.)
        $pairings = $preventRematch
            ? $this->createPairingsWithRematchAvoidance($activeRankedTeams, $normalizedHistory['pairings'])
            : $this->createStrictRankedPairings($activeRankedTeams);

        // 3. Validasi Integritas Ronde
        $allTeams = array_map(fn($item) => $item['team'], $leaderboard);
        $this->validateRoundIntegrity($pairings, $byeTeams, $allTeams);

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
     * Default Matcha Team Mexicano pairing strategy:
     * Rank 1 vs Rank 2 (Court 1), Rank 3 vs Rank 4 (Court 2), Rank 5 vs Rank 6 (Court 3), dst.
     */
    protected function createStrictRankedPairings(array $rankedTeams): array
    {
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
     * Pairing Mexicano dengan pencegahan rematch beruntun (opsional/best-effort).
     */
    protected function createPairingsWithRematchAvoidance(array $rankedTeams, array $historyPairs): array
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

            $bestOpponentIdx = null;

            for ($j = $i + 1; $j < $count; $j++) {
                if (isset($used[$j])) {
                    continue;
                }

                $teamB = $rankedTeams[$j];
                $pairKey = $this->getPairKey($teamA['id'], $teamB['id']);

                if (!isset($historyPairs[$pairKey])) {
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
     * Pilih tim yang berhak mendapatkan BYE secara adil:
     * 1. Pilih tim dengan bye_count paling sedikit.
     * 2. Hindari tim yang baru saja mendapat BYE pada ronde sebelumnya ($lastByeTeamId) jika masih ada kandidat lain.
     * 3. Jika masih ada beberapa kandidat, pilih tim dengan peringkat paling bawah.
     */
    protected function selectFairByeTeamIndex(array $leaderboard, int|string|null $lastByeTeamId = null): int
    {
        $minByeCount = PHP_INT_MAX;
        foreach ($leaderboard as $entry) {
            $count = $entry['bye_count'] ?? 0;
            if ($count < $minByeCount) {
                $minByeCount = $count;
            }
        }

        // Kumpulkan semua index kandidat dengan bye_count minimum
        $candidateIndices = [];
        foreach ($leaderboard as $idx => $entry) {
            if (($entry['bye_count'] ?? 0) === $minByeCount) {
                $candidateIndices[] = $idx;
            }
        }

        // Jika terdapat lebih dari 1 kandidat dan ada lastByeTeamId, eliminasi tim yang baru saja BYE
        if (count($candidateIndices) > 1 && $lastByeTeamId !== null) {
            $filtered = array_filter($candidateIndices, function ($idx) use ($leaderboard, $lastByeTeamId) {
                $tId = (string) ($leaderboard[$idx]['team_id'] ?? $leaderboard[$idx]['team']['id'] ?? '');
                return $tId !== (string) $lastByeTeamId;
            });

            if (!empty($filtered)) {
                $candidateIndices = array_values($filtered);
            }
        }

        // Dari kandidat tersisa, pilih yang peringkatnya paling bawah (index terbesar)
        return end($candidateIndices);
    }

    /**
     * Validasi bahwa seluruh match di ronde sebelumnya sudah selesai.
     *
     * @throws RuntimeException Jika masih ada match ongoing/scheduled/belum ada skor final
     */
    public function assertPreviousRoundCompleted(array $previousRoundMatches): void
    {
        foreach ($previousRoundMatches as $match) {
            $status = strtolower($match['status'] ?? '');
            $isFinished = !empty($match['is_completed']) || in_array($status, ['finished', 'completed', 'final', 'done'], true);
            $hasScores = isset($match['score_a'], $match['score_b']) && $match['score_a'] !== null && $match['score_b'] !== null;

            if (!$isFinished || !$hasScores || in_array($status, ['scheduled', 'ongoing', 'pending', 'open', 'in_progress'], true)) {
                throw new RuntimeException(
                    'Ronde berikutnya tidak dapat dibuat karena ronde sebelumnya belum selesai.'
                );
            }
        }
    }

    /**
     * Validasi Integritas Ronde (Safeguard).
     * Memastikan setiap tim non-BYE bermain tepat 1 kali dan tim BYE tepat 1 kali.
     *
     * @throws RuntimeException
     */
    public function validateRoundIntegrity(array $pairings, array $byeTeams, array $allTeams): void
    {
        $teamOccurrences = [];

        foreach ($pairings as $pair) {
            $tA = (string) ($pair['team_a']['id'] ?? $pair['team_a']['team_id'] ?? '');
            $tB = (string) ($pair['team_b']['id'] ?? $pair['team_b']['team_id'] ?? '');

            if ($tA === '' || $tB === '') {
                throw new RuntimeException('Pairing match tidak memiliki identitas tim yang valid.');
            }

            if ($tA === $tB) {
                throw new RuntimeException("Sebuah tim tidak dapat bertanding melawan dirinya sendiri: '{$tA}'.");
            }

            $teamOccurrences[$tA] = ($teamOccurrences[$tA] ?? 0) + 1;
            $teamOccurrences[$tB] = ($teamOccurrences[$tB] ?? 0) + 1;
        }

        foreach ($byeTeams as $byeTeam) {
            $bId = (string) ($byeTeam['id'] ?? $byeTeam['team_id'] ?? '');
            if ($bId !== '') {
                $teamOccurrences[$bId] = ($teamOccurrences[$bId] ?? 0) + 1;
            }
        }

        // Periksa bahwa setiap tim dari total tim muncul tepat 1 kali
        foreach ($allTeams as $team) {
            $teamId = (string) ($team['id'] ?? $team['team_id'] ?? '');
            $count = $teamOccurrences[$teamId] ?? 0;

            if ($count === 0) {
                throw new RuntimeException("Integritas ronde gagal: Tim '{$team['name']}' ({$teamId}) tidak terdaftar dalam match maupun BYE.");
            }

            if ($count > 1) {
                throw new RuntimeException("Integritas ronde gagal: Tim '{$team['name']}' ({$teamId}) muncul lebih dari 1 kali dalam ronde yang sama.");
            }
        }
    }

    /**
     * Ekstraksi parameter court dengan validasi ketat.
     */
    protected function resolveCourtParameters(int|array $courts): array
    {
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
     * Normalisasi match history baik dari associative array database maupun format list biasa.
     */
    public function normalizeMatchHistory(array $matchHistory): array
    {
        $pairings = [];

        foreach ($matchHistory as $key => $item) {
            if (is_array($item)) {
                $teamAId = $item['team_a']['id'] ?? $item['team_a']['team_id'] ?? $item['team_a_id'] ?? null;
                $teamBId = $item['team_b']['id'] ?? $item['team_b']['team_id'] ?? $item['team_b_id'] ?? null;

                if ($teamAId !== null && $teamBId !== null) {
                    $pairings[$this->getPairKey($teamAId, $teamBId)] = true;
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
     * Ekstraksi total BYE per tim dari list struktur round history.
     */
    public function calculateByeCounts(array $roundHistory): array
    {
        $counts = [];

        foreach ($roundHistory as $round) {
            if (!empty($round['bye_teams']) && is_array($round['bye_teams'])) {
                foreach ($round['bye_teams'] as $bt) {
                    $bId = (string) ($bt['id'] ?? $bt['team_id'] ?? $bt['stable_id'] ?? '');
                    if ($bId !== '') {
                        $counts[$bId] = ($counts[$bId] ?? 0) + 1;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * Dapatkan ID tim yang mendapatkan BYE pada ronde paling terakhir.
     */
    protected function extractLastByeTeamId(array $roundHistory): int|string|null
    {
        if (empty($roundHistory)) {
            return null;
        }

        $lastRound = end($roundHistory);
        if (!empty($lastRound['bye_teams']) && is_array($lastRound['bye_teams'])) {
            $firstBye = reset($lastRound['bye_teams']);
            return $firstBye['id'] ?? $firstBye['team_id'] ?? null;
        }

        return null;
    }

    /**
     * Bangun struktur data ronde seragam untuk Blade UI & GameController.
     * Status waiting dihitung secara presisi (hanya tim di slot-slot berikutnya).
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
        $totalSlots = count($slotChunks);
        $slots = [];
        $roundMatchesFlat = [];
        $matchCounter = 1;

        foreach ($slotChunks as $chunkIdx => $chunkMatches) {
            $slotNumber = $chunkIdx + 1;
            $slotMatches = [];

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

            // Waiting teams pada slot ini HANYA tim yang bermain pada slot-slot BERIKUTNYA
            $teamsInUpcomingSlots = [];
            for ($futureSlot = $chunkIdx + 1; $futureSlot < $totalSlots; $futureSlot++) {
                foreach ($slotChunks[$futureSlot] as $futurePair) {
                    $teamsInUpcomingSlots[] = $futurePair['team_a'];
                    $teamsInUpcomingSlots[] = $futurePair['team_b'];
                }
            }

            $waitingPlayersInSlot = [];
            foreach ($teamsInUpcomingSlots as $wt) {
                $waitingPlayersInSlot = array_merge($waitingPlayersInSlot, $wt['player_names']);
            }

            $slots[] = [
                'slot_number' => $slotNumber,
                'slot_title' => "Slot {$slotNumber}",
                'matches' => $slotMatches,
                'waiting_teams' => $teamsInUpcomingSlots,
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
     * Canonical key pasangan untuk pencatatan riwayat pertandingan.
     */
    public function getPairKey(int|string $teamAId, int|string $teamBId): string
    {
        $ids = [(string) $teamAId, (string) $teamBId];
        sort($ids, SORT_STRING);
        return implode('vs', $ids);
    }
}
