<?php

namespace App\Services\Drawing;

class MexicanoService
{
    /**
     * Generate Mexicano rounds.
     *
     * Round 1 uses random doubles drawing.
     * Round 2 and beyond use temporary ranking (formula: Rank 1+4 vs 2+3, Rank 5+8 vs 6+7, etc.).
     *
     * @param array $players Array of player names, arrays, or objects
     * @param int $courtCount Number of available courts
     * @param array|null $ranking Optional initial or temporary ranking
     * @param int $roundCount Number of rounds to generate (default: 3)
     * @param array $roundScores Optional scores per round to simulate multi-round progression
     * @return array
     */
    public function generateRounds(
        array $players,
        int $courtCount,
        ?array $ranking = null,
        int $roundCount = 3,
        array $roundScores = []
    ): array {
        $playerCount = count($players);

        if ($playerCount < 4 || $courtCount < 1 || $roundCount < 1) {
            return [];
        }

        $normalizedPlayers = $this->normalizePlayers($players);
        $rounds = [];
        $currentRanking = $ranking;
        $history = [
            'play_counts' => array_fill_keys(array_keys($normalizedPlayers), 0),
            'rest_counts' => array_fill_keys(array_keys($normalizedPlayers), 0),
            'last_rested_round' => array_fill_keys(array_keys($normalizedPlayers), -1),
            'resting_per_round' => [],
        ];

        for ($r = 1; $r <= $roundCount; $r++) {
            $roundData = $this->generateRound(
                $normalizedPlayers,
                $courtCount,
                $r,
                $currentRanking,
                $history
            );

            if (empty($roundData)) {
                break;
            }

            $rounds[] = $roundData;

            // Update history
            $restingNames = $roundData['resting'] ?? [];
            $restingPlayers = $roundData['resting_players'] ?? [];
            $restingIdxs = [];
            foreach ($restingPlayers as $rp) {
                if (isset($rp['_idx'])) {
                    $restingIdxs[] = $rp['_idx'];
                }
            }

            foreach ($normalizedPlayers as $idx => $p) {
                if (in_array($idx, $restingIdxs, true)) {
                    $history['rest_counts'][$idx]++;
                    $history['last_rested_round'][$idx] = $r;
                } else {
                    $history['play_counts'][$idx]++;
                }
            }
            $history['resting_per_round'][$r] = $restingNames;

            // If match scores for this round are provided, recalculate temporary ranking for next round
            if (isset($roundScores[$r]) && is_array($roundScores[$r])) {
                $currentRanking = $this->calculateRanking($roundScores[$r], $currentRanking, $normalizedPlayers);
            }
        }

        return $rounds;
    }

    /**
     * Generate a single Mexicano round.
     *
     * Round 1: random drawing.
     * Round 2+: ranking-based pairing (Rank 1+4 vs 2+3, Rank 5+8 vs 6+7, etc.).
     *
     * @param array $players
     * @param int $courtCount
     * @param int $roundNumber
     * @param array|null $ranking
     * @param array $history Optional history to maintain fair resting queue
     * @return array
     */
    public function generateRound(
        array $players,
        int $courtCount,
        int $roundNumber = 1,
        ?array $ranking = null,
        array $history = []
    ): array {
        $playerCount = count($players);

        if ($playerCount < 4 || $courtCount < 1 || $roundNumber < 1) {
            return [];
        }

        $normalizedPlayers = $this->normalizePlayers($players);
        $totalPlayers = count($normalizedPlayers);

        // Capacity calculation
        $playersPerCourt = 4;
        $maxCourts = intdiv($totalPlayers, $playersPerCourt);
        $activeCourts = min($courtCount, $maxCourts);

        if ($activeCourts < 1) {
            return [];
        }

        $activeCount = $activeCourts * $playersPerCourt;
        $restingCount = $totalPlayers - $activeCount;

        // Round 1 selalu menggunakan random drawing
        if ($roundNumber === 1) {
            return $this->generateRandomRound($normalizedPlayers, $activeCourts, $activeCount, $restingCount, $roundNumber);
        }

        // Round 2 dan seterusnya membutuhkan ranking sementara yang sudah tersedia.
        // Jangan membuat ranking palsu jika ranking belum diberikan.
        if (empty($ranking)) {
            return [];
        }

        // Round 2+ uses ranking
        return $this->generateRankingRound(
            $normalizedPlayers,
            $activeCourts,
            $activeCount,
            $restingCount,
            $roundNumber,
            $ranking,
            $history
        );
    }

    /**
     * Calculate or update individual ranking based on match results and scores.
     *
     * Individual Mexicano scoring: each player on a team receives the points scored by that team.
     * Players who rested receive 0 points for that round (or maintain accumulated points from previous ranking).
     *
     * @param array $matchesWithScores List of matches with team_a, team_b, score_a, score_b
     * @param array|null $previousRanking Existing ranking to accumulate, or list of all participants
     * @param array $allPlayers Optional list of all participants to ensure resting players are included
     * @return array Ranked list sorted descending by points
     */
    public function calculateRanking(
        array $matchesWithScores,
        ?array $previousRanking = null,
        array $allPlayers = []
    ): array {
        $playerStats = [];

        // 1. Seed from previous ranking or player list if available
        if ($previousRanking !== null) {
            foreach ($previousRanking as $entry) {
                // If it's a simple string or player array/object without points, seed with 0 points
                if (is_string($entry) || (is_array($entry) && !isset($entry['points']) && !isset($entry['score']))) {
                    $id = $this->extractPlayerIdentifier($entry);
                    $name = $this->extractPlayerName($entry);
                    $key = (string) $id;
                    if (!isset($playerStats[$key])) {
                        $playerStats[$key] = [
                            'id' => $id,
                            'name' => $name,
                            'points' => 0,
                            'points_conceded' => 0,
                            'matches_played' => 0,
                            'matches_won' => 0,
                            'matches_lost' => 0,
                            'matches_drawn' => 0,
                            'original' => $entry,
                        ];
                    }
                    continue;
                }

                $id = $entry['id'] ?? $entry['player_id'] ?? $entry['player'] ?? $entry['name'] ?? null;
                $name = $entry['name'] ?? $entry['player'] ?? (string) $id;
                if ($id === null) {
                    continue;
                }
                $key = (string) $id;
                $playerStats[$key] = [
                    'id' => $id,
                    'name' => $name,
                    'points' => (int) ($entry['points'] ?? 0),
                    'points_conceded' => (int) ($entry['points_conceded'] ?? 0),
                    'matches_played' => (int) ($entry['matches_played'] ?? 0),
                    'matches_won' => (int) ($entry['matches_won'] ?? $entry['wins'] ?? 0),
                    'matches_lost' => (int) ($entry['matches_lost'] ?? $entry['losses'] ?? 0),
                    'matches_drawn' => (int) ($entry['matches_drawn'] ?? $entry['draws'] ?? 0),
                    'original' => $entry['original'] ?? $entry,
                ];
            }
        }

        // 2. Seed from $allPlayers if provided to ensure resting players appear on the leaderboard
        if (!empty($allPlayers)) {
            foreach ($allPlayers as $p) {
                $id = $this->extractPlayerIdentifier($p);
                $name = $this->extractPlayerName($p);
                $key = (string) $id;

                if (!isset($playerStats[$key])) {
                    $playerStats[$key] = [
                        'id' => $id,
                        'name' => $name,
                        'points' => 0,
                        'points_conceded' => 0,
                        'matches_played' => 0,
                        'matches_won' => 0,
                        'matches_lost' => 0,
                        'matches_drawn' => 0,
                        'original' => $p,
                    ];
                }
            }
        }

        // 3. Support if round data structure with 'resting' is passed
        $restingInMatches = $matchesWithScores['resting'] ?? ($matchesWithScores['resting_players'] ?? null);
        if ($restingInMatches !== null && is_array($restingInMatches)) {
            foreach ($restingInMatches as $rp) {
                $id = $this->extractPlayerIdentifier($rp);
                $name = $this->extractPlayerName($rp);
                $key = (string) $id;
                if (!isset($playerStats[$key])) {
                    $playerStats[$key] = [
                        'id' => $id,
                        'name' => $name,
                        'points' => 0,
                        'points_conceded' => 0,
                        'matches_played' => 0,
                        'matches_won' => 0,
                        'matches_lost' => 0,
                        'matches_drawn' => 0,
                        'original' => $rp,
                    ];
                }
            }
        }

        // If a round array containing 'courts' is passed directly as $matchesWithScores
        $matchesList = $matchesWithScores['courts'] ?? ($matchesWithScores['matches'] ?? $matchesWithScores);
        if (is_array($matchesList)) {
            foreach ($matchesList as $match) {
                if (!is_array($match)) continue;
                $teamA = $match['team_a'] ?? $match['teamA'] ?? [];
                $teamB = $match['team_b'] ?? $match['teamB'] ?? [];
                $scoreA = (int) ($match['score_a'] ?? $match['scoreA'] ?? $match['score_team_a'] ?? 0);
                $scoreB = (int) ($match['score_b'] ?? $match['scoreB'] ?? $match['score_team_b'] ?? 0);

            // Record Team A players
            foreach ($teamA as $p) {
                $id = $this->extractPlayerIdentifier($p);
                $name = $this->extractPlayerName($p);
                $key = (string) $id;

                if (!isset($playerStats[$key])) {
                    $playerStats[$key] = [
                        'id' => $id,
                        'name' => $name,
                        'points' => 0,
                        'points_conceded' => 0,
                        'matches_played' => 0,
                        'matches_won' => 0,
                        'matches_lost' => 0,
                        'matches_drawn' => 0,
                        'original' => $p,
                    ];
                }

                $playerStats[$key]['points'] += $scoreA;
                $playerStats[$key]['points_conceded'] += $scoreB;
                $playerStats[$key]['matches_played'] += 1;
                if ($scoreA > $scoreB) {
                    $playerStats[$key]['matches_won'] += 1;
                } elseif ($scoreA < $scoreB) {
                    $playerStats[$key]['matches_lost'] += 1;
                } else {
                    $playerStats[$key]['matches_drawn'] += 1;
                }
            }

            // Record Team B players
            foreach ($teamB as $p) {
                $id = $this->extractPlayerIdentifier($p);
                $name = $this->extractPlayerName($p);
                $key = (string) $id;

                if (!isset($playerStats[$key])) {
                    $playerStats[$key] = [
                        'id' => $id,
                        'name' => $name,
                        'points' => 0,
                        'points_conceded' => 0,
                        'matches_played' => 0,
                        'matches_won' => 0,
                        'matches_lost' => 0,
                        'matches_drawn' => 0,
                        'original' => $p,
                    ];
                }

                $playerStats[$key]['points'] += $scoreB;
                $playerStats[$key]['points_conceded'] += $scoreA;
                $playerStats[$key]['matches_played'] += 1;
                if ($scoreB > $scoreA) {
                    $playerStats[$key]['matches_won'] += 1;
                } elseif ($scoreB < $scoreA) {
                    $playerStats[$key]['matches_lost'] += 1;
                } else {
                    $playerStats[$key]['matches_drawn'] += 1;
                }
            }
        }
    }

        // Mexicano: Ranking utama HANYA berdasarkan TOTAL INDIVIDUAL POINTS.
        // Fallback teknis: jika total poin sama persis, urutan nama digunakan murni demi stabilitas
        // pengurutan teknis / testing (bukan aturan ranking resmi Mexicano).
        $rankingList = array_values($playerStats);
        usort($rankingList, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            // Fallback teknis semata untuk stabilitas testing (bukan aturan ranking resmi Mexicano)
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        // Add 1-based rank position
        foreach ($rankingList as $i => &$entry) {
            $entry['rank'] = $i + 1;
            $entry['point_diff'] = $entry['points'] - $entry['points_conceded'];
            $entry['player'] = $entry['name'];
        }
        unset($entry);

        return $rankingList;
    }

    /**
     * Sort normalized players array to match ranking order.
     * Supports:
     * - Ordered array of player names or IDs: ['A', 'D', 'B', 'C']
     * - Array of ranking entries with 'points': [['player' => 'A', 'points' => 16], ...]
     * - Associative map: ['A' => 16, 'D' => 16, 'B' => 8, 'C' => 8]
     */
    public function sortPlayersByRanking(array $players, array $ranking): array
    {
        $normalized = $this->normalizePlayers($players);

        // If ranking is associative map of id/name => points, convert to structured array
        $isAssocMap = !empty($ranking) && !array_is_list($ranking) && is_numeric(reset($ranking));
        if ($isAssocMap) {
            $converted = [];
            foreach ($ranking as $key => $pts) {
                $converted[] = ['player' => $key, 'id' => $key, 'name' => (string)$key, 'points' => (int)$pts];
            }
            $ranking = $converted;
        }

        // If entries have 'points', sort by points descending first
        $hasPoints = false;
        foreach ($ranking as $entry) {
            if (is_array($entry) && isset($entry['points'])) {
                $hasPoints = true;
                break;
            }
        }

        if ($hasPoints) {
            usort($ranking, function ($a, $b) {
                $pA = is_array($a) ? ($a['points'] ?? 0) : 0;
                $pB = is_array($b) ? ($b['points'] ?? 0) : 0;
                if ($pA !== $pB) {
                    return $pB <=> $pA;
                }
                // Fallback teknis jika poin sama untuk stabilitas urutan testing
                $nameA = $this->extractPlayerName($a);
                $nameB = $this->extractPlayerName($b);
                return strcmp($nameA, $nameB);
            });
        }

        $orderMap = [];
        $rankIndex = 0;

        foreach ($ranking as $entry) {
            $id = $this->extractPlayerIdentifier($entry);
            $key = (string) $id;
            if (!isset($orderMap[$key])) {
                $orderMap[$key] = $rankIndex++;
            }
            // Also map by name if available
            $name = $this->extractPlayerName($entry);
            $nameKey = strtolower(trim($name));
            if (!isset($orderMap[$nameKey])) {
                $orderMap[$nameKey] = $orderMap[$key];
            }
        }

        usort($normalized, function ($a, $b) use ($orderMap) {
            $aKey = (string) $a['id'];
            $bKey = (string) $b['id'];
            $aNameKey = strtolower(trim($a['name']));
            $bNameKey = strtolower(trim($b['name']));

            $orderA = $orderMap[$aKey] ?? ($orderMap[$aNameKey] ?? 999999);
            $orderB = $orderMap[$bKey] ?? ($orderMap[$bNameKey] ?? 999999);

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return $a['_idx'] <=> $b['_idx'];
        });

        return $normalized;
    }

    /**
     * Generate Round 1 with random drawing.
     */
    private function generateRandomRound(
        array $normalizedPlayers,
        int $activeCourts,
        int $activeCount,
        int $restingCount,
        int $roundNumber
    ): array {
        $shuffled = $normalizedPlayers;
        shuffle($shuffled);

        $activePlayers = array_slice($shuffled, 0, $activeCount);
        $restingPlayers = array_slice($shuffled, $activeCount);

        // Form random doubles for each court
        $courts = [];
        for ($c = 0; $c < $activeCourts; $c++) {
            $courtNumber = $c + 1;
            $base = $c * 4;

            $p1 = $activePlayers[$base];
            $p2 = $activePlayers[$base + 1];
            $p3 = $activePlayers[$base + 2];
            $p4 = $activePlayers[$base + 3];

            $teamA = [$p1, $p2];
            $teamB = [$p3, $p4];

            $teamANames = [$p1['name'], $p2['name']];
            $teamBNames = [$p3['name'], $p4['name']];

            $courts[] = [
                'court' => $courtNumber,
                'court_number' => $courtNumber,
                'court_name' => "Court {$courtNumber}",
                'team_a' => $teamA,
                'team_b' => $teamB,
                'team_a_names' => $teamANames,
                'team_b_names' => $teamBNames,
                'teamA' => $teamANames,
                'teamB' => $teamBNames,
                'teamA_names' => $teamANames,
                'teamB_names' => $teamBNames,
            ];
        }

        $restingNames = array_map(fn($p) => $p['name'], $restingPlayers);

        return [
            'round' => $roundNumber,
            'round_number' => $roundNumber,
            'round_name' => "Ronde {$roundNumber}",
            'court_count' => $activeCourts,
            'courts' => $courts,
            'matches' => $courts,
            'resting' => $restingNames,
            'resting_players' => $restingPlayers,
            'teamA' => $courts[0]['team_a_names'] ?? [],
            'teamB' => $courts[0]['team_b_names'] ?? [],
            'team_a' => $courts[0]['team_a'] ?? [],
            'team_b' => $courts[0]['team_b'] ?? [],
        ];
    }

    /**
     * Generate Round 2 and beyond using ranking and Mexicano formula:
     * Rank 1 + Rank 4 vs Rank 2 + Rank 3
     * Rank 5 + Rank 8 vs Rank 6 + Rank 7
     * Rank 9 + Rank 12 vs Rank 10 + Rank 11
     * etc.
     */
    private function generateRankingRound(
        array $normalizedPlayers,
        int $activeCourts,
        int $activeCount,
        int $restingCount,
        int $roundNumber,
        ?array $ranking,
        array $history
    ): array {
        // 1. Sort all players by temporary ranking if provided
        $rankedPlayers = $ranking !== null
            ? $this->sortPlayersByRanking($normalizedPlayers, $ranking)
            : $normalizedPlayers;

        // 2. Separate into active and resting players
        $selection = $this->selectActiveAndResting(
            $rankedPlayers,
            $activeCount,
            $restingCount,
            $roundNumber,
            $history
        );

        $activePlayers = $selection['active'];
        $restingPlayers = $selection['resting'];

        // Re-ensure active players are strictly in ranking order
        if ($ranking !== null) {
            $activePlayers = $this->sortPlayersByRanking($activePlayers, $ranking);
        }

        // 3. Apply Mexicano pairing formula
        $courts = $this->applyMexicanoPairing($activePlayers, $activeCourts);
        $restingNames = array_map(fn($p) => $p['name'], $restingPlayers);

        return [
            'round' => $roundNumber,
            'round_number' => $roundNumber,
            'round_name' => "Ronde {$roundNumber}",
            'court_count' => $activeCourts,
            'courts' => $courts,
            'matches' => $courts,
            'resting' => $restingNames,
            'resting_players' => $restingPlayers,
            'teamA' => $courts[0]['team_a_names'] ?? [],
            'teamB' => $courts[0]['team_b_names'] ?? [],
            'team_a' => $courts[0]['team_a'] ?? [],
            'team_b' => $courts[0]['team_b'] ?? [],
        ];
    }

    /**
     * Apply default Mexicano pairing formula:
     * Court 1: Rank 1 + Rank 4 vs Rank 2 + Rank 3
     * Court 2: Rank 5 + Rank 8 vs Rank 6 + Rank 7
     * Court 3: Rank 9 + Rank 12 vs Rank 10 + Rank 11
     * ...
     */
    private function applyMexicanoPairing(array $activePlayers, int $activeCourts): array
    {
        $courts = [];

        for ($c = 0; $c < $activeCourts; $c++) {
            $courtNumber = $c + 1;
            $base = $c * 4;

            $p1 = $activePlayers[$base];     // Rank 1 of this court
            $p2 = $activePlayers[$base + 1]; // Rank 2 of this court
            $p3 = $activePlayers[$base + 2]; // Rank 3 of this court
            $p4 = $activePlayers[$base + 3]; // Rank 4 of this court

            // Formula: Rank 1 + Rank 4 vs Rank 2 + Rank 3
            $teamA = [$p1, $p4];
            $teamB = [$p2, $p3];

            $teamANames = [$p1['name'], $p4['name']];
            $teamBNames = [$p2['name'], $p3['name']];

            $courts[] = [
                'court' => $courtNumber,
                'court_number' => $courtNumber,
                'court_name' => "Court {$courtNumber}",
                'team_a' => $teamA,
                'team_b' => $teamB,
                'team_a_names' => $teamANames,
                'team_b_names' => $teamBNames,
                'teamA' => $teamANames,
                'teamB' => $teamBNames,
                'teamA_names' => $teamANames,
                'teamB_names' => $teamBNames,
            ];
        }

        return $courts;
    }

    /**
     * Fair sit-out queue: prioritizes players who rested in previous round to play.
     */
    private function selectActiveAndResting(
        array $rankedPlayers,
        int $activeCount,
        int $restingCount,
        int $currentRound,
        array $history
    ): array {
        if ($restingCount <= 0) {
            return [
                'active' => $rankedPlayers,
                'resting' => [],
            ];
        }

        $playCounts = $history['play_counts'] ?? [];
        $restCounts = $history['rest_counts'] ?? [];
        $lastRestedRound = $history['last_rested_round'] ?? [];

        // Support convenient history inputs like ['resting' => ['E', 'F']] or ['last_rested' => ['E', 'F']]
        $explicitRested = $history['resting'] 
            ?? $history['last_rested'] 
            ?? $history['previous_round']['resting'] 
            ?? null;

        if ($explicitRested !== null && is_array($explicitRested)) {
            $restedKeys = array_map(fn($v) => strtolower(trim($this->extractPlayerName($v))), $explicitRested);
            $restedIds = array_map(fn($v) => (string) $this->extractPlayerIdentifier($v), $explicitRested);
            foreach ($rankedPlayers as $p) {
                $pIdx = $p['_idx'] ?? null;
                if ($pIdx === null) continue;
                $pName = strtolower(trim($p['name'] ?? ''));
                $pId = (string) ($p['id'] ?? '');
                if (in_array($pName, $restedKeys, true) || in_array($pId, $restedIds, true)) {
                    $lastRestedRound[$pIdx] = $currentRound - 1;
                }
            }
        }

        // If no history is provided, the lowest-ranked players rest
        if (empty($lastRestedRound) && empty($playCounts)) {
            return [
                'active' => array_slice($rankedPlayers, 0, $activeCount),
                'resting' => array_slice($rankedPlayers, $activeCount),
            ];
        }

        // When history is available:
        // Priority to PLAY:
        // 1. Those who rested in immediately preceding round (currentRound - 1)
        // 2. Lowest play count
        // 3. Highest rest count
        // 4. Higher ranking position in $rankedPlayers
        $indexedPlayers = $rankedPlayers;

        usort($indexedPlayers, function ($a, $b) use ($currentRound, $playCounts, $restCounts, $lastRestedRound) {
            $aIdx = $a['_idx'] ?? null;
            $bIdx = $b['_idx'] ?? null;

            if ($aIdx !== null && $bIdx !== null) {
                $aRestedLast = (($lastRestedRound[$aIdx] ?? -1) === $currentRound - 1);
                $bRestedLast = (($lastRestedRound[$bIdx] ?? -1) === $currentRound - 1);

                if ($aRestedLast && !$bRestedLast) {
                    return -1;
                }
                if (!$aRestedLast && $bRestedLast) {
                    return 1;
                }

                $aPlay = $playCounts[$aIdx] ?? 0;
                $bPlay = $playCounts[$bIdx] ?? 0;
                if ($aPlay !== $bPlay) {
                    return $aPlay <=> $bPlay;
                }

                $aRest = $restCounts[$aIdx] ?? 0;
                $bRest = $restCounts[$bIdx] ?? 0;
                if ($aRest !== $bRest) {
                    return $bRest <=> $aRest;
                }
            }

            return 0;
        });

        $active = array_slice($indexedPlayers, 0, $activeCount);
        $resting = array_slice($indexedPlayers, $activeCount);

        return [
            'active' => $active,
            'resting' => $resting,
        ];
    }

    /**
     * Normalize players to a consistent structure.
     */
    private function normalizePlayers(array $players): array
    {
        $normalized = [];
        $index = 0;

        foreach ($players as $p) {
            if (is_object($p)) {
                $id = $p->player_id ?? $p->id ?? ($index + 1);
                $name = $p->nama ?? $p->name ?? "Player {$id}";
                $level = $p->level ?? 'Intermediate';
                $gender = $p->gender ?? 'Male';
                $phone = $p->no_hp ?? $p->phone ?? '-';
                $avatar = $p->avatar ?? null;
            } elseif (is_array($p)) {
                $id = $p['player_id'] ?? $p['id'] ?? ($index + 1);
                $name = $p['nama'] ?? $p['name'] ?? "Player {$id}";
                $level = $p['level'] ?? 'Intermediate';
                $gender = $p['gender'] ?? 'Male';
                $phone = $p['no_hp'] ?? $p['phone'] ?? '-';
                $avatar = $p['avatar'] ?? null;
            } else {
                $id = (string) $p;
                $name = (string) $p;
                $level = 'Intermediate';
                $gender = 'Male';
                $phone = '-';
                $avatar = null;
            }

            $normalized[] = [
                '_idx' => $index,
                'id' => $id,
                'name' => (string) $name,
                'level' => $level,
                'gender' => $gender,
                'phone' => $phone,
                'avatar' => $avatar,
                'original' => $p,
            ];
            $index++;
        }

        return $normalized;
    }

    /**
     * Extract player identifier from mixed input.
     */
    private function extractPlayerIdentifier(mixed $player): mixed
    {
        if (is_object($player)) {
            return $player->player_id ?? $player->id ?? ($player->nama ?? $player->name ?? null);
        }
        if (is_array($player)) {
            return $player['player_id'] ?? $player['id'] ?? $player['player'] ?? ($player['nama'] ?? $player['name'] ?? null);
        }
        return $player;
    }

    /**
     * Extract player name from mixed input.
     */
    private function extractPlayerName(mixed $player): string
    {
        if (is_object($player)) {
            return (string) ($player->nama ?? $player->name ?? $player->player_id ?? $player->id ?? '');
        }
        if (is_array($player)) {
            return (string) ($player['nama'] ?? $player['name'] ?? $player['player'] ?? $player['player_id'] ?? $player['id'] ?? '');
        }
        return (string) $player;
    }
}
