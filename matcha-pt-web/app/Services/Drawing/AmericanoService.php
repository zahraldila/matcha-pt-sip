<?php

namespace App\Services\Drawing;

class AmericanoService
{
    /**
     * Generate Americano drawing rounds.
     *
     * Routes to Single (1 vs 1) or Double (2 vs 2) algorithm based on $mode.
     *
     * @param  array  $players  Array of player models, associative arrays, or player names
     * @param  int  $courtCount  Number of available courts
     * @param  int|null  $roundCount  Optional number of rounds (defaults to 3, max N-1)
     * @param  string  $mode  'Double' (default) or 'Single'
     */
    public function generateRounds(array $players, int $courtCount, ?int $roundCount = null, string $mode = 'Double'): array
    {
        if (strtolower($mode) === 'single') {
            return $this->generateSingleRounds($players, $courtCount, $roundCount);
        }

        return $this->generateDoubleRounds($players, $courtCount, $roundCount);
    }

    // =========================================================================
    // DOUBLE (2 vs 2) — Original algorithm, preserved unchanged
    // =========================================================================

    /**
     * Generate Americano individual drawing rounds with rotating partners.
     *
     * Rules:
     * - Doubles format: 2 vs 2 per court (4 players per court).
     * - Rotating partner: a player must NEVER partner with the same player more than once.
     * - Opponents are distributed as evenly as possible.
     * - If players > courtCount * 4, excess players sit out in 'resting'.
     * - Fair sit-out queue: players resting in round r-1 are prioritized to play in round r.
     *
     * @param  array  $players  Array of player models, associative arrays, or player names
     * @param  int  $courtCount  Number of available courts
     * @param  int|null  $roundCount  Optional number of rounds (defaults to 3, max N-1)
     */
    private function generateDoubleRounds(array $players, int $courtCount, ?int $roundCount = null): array
    {
        $playerCount = count($players);

        // Validation
        if ($playerCount < 4 || $courtCount < 1) {
            return [];
        }

        // 1. Normalize players to consistent structure with unique IDs
        $normalizedPlayers = $this->normalizePlayers($players);

        // 2. Capacity calculations
        $playersPerCourt = 4;
        $maxCourts = intdiv($playerCount, $playersPerCourt);
        $activeCourts = min($courtCount, $maxCourts);
        $activePerRound = $activeCourts * $playersPerCourt;
        $restingPerRound = $playerCount - $activePerRound;

        // 3. Determine number of rounds to generate
        // Keep the historical default, but honor an explicit scoring round count.
        $maxTheoreticalRounds = max(1, $playerCount - 1);
        if ($roundCount === null || $roundCount < 1) {
            $totalRounds = min(3, $maxTheoreticalRounds);
        } else {
            $totalRounds = $roundCount;
        }

        // Tracking structures
        $partnerHistory = []; // "minId-maxId" => true
        $opponentHistory = []; // "minId-maxId" => count
        $playCounts = array_fill_keys(array_keys($normalizedPlayers), 0);
        $restCounts = array_fill_keys(array_keys($normalizedPlayers), 0);
        $lastRestedRound = array_fill_keys(array_keys($normalizedPlayers), -1);

        $rounds = [];

        for ($r = 1; $r <= $totalRounds; $r++) {
            // Select active players and resting players for this round
            $selection = $this->selectActiveAndResting(
                $normalizedPlayers,
                $activePerRound,
                $restingPerRound,
                $r,
                $playCounts,
                $restCounts,
                $lastRestedRound
            );

            $activePlayers = $selection['active'];
            $restingPlayers = $selection['resting'];

            // Find valid pairs and matches where no partner repeats
            $matches = $this->findMatchesForRound(
                $activePlayers,
                $activeCourts,
                $partnerHistory,
                $opponentHistory
            );

            // Fallback: If current active subset cannot form valid pairs due to historical partner constraints,
            // try alternative resting permutations
            if ($matches === null && $restingPerRound > 0) {
                $alternateMatches = $this->findAlternativeMatches(
                    $normalizedPlayers,
                    $activePerRound,
                    $restingPerRound,
                    $activeCourts,
                    $partnerHistory,
                    $opponentHistory,
                    $selection
                );

                if ($alternateMatches !== null) {
                    $activePlayers = $alternateMatches['active'];
                    $restingPlayers = $alternateMatches['resting'];
                    $matches = $alternateMatches['matches'];
                }
            }

            // If still no valid match could be found without repeat partners,
            // we break to avoid violating the core constraint
            if ($matches === null) {
                break;
            }

            // Update partner & opponent histories, and play/rest counts
            $playingIds = [];
            foreach ($matches as &$match) {
                $match['teamA_names'] = $match['team_a_names'];
                $match['teamB_names'] = $match['team_b_names'];
                $match['status'] = 'Scheduled';

                // Team A partner
                $this->recordPair($match['team_a'][0]['id'], $match['team_a'][1]['id'], $partnerHistory);
                // Team B partner
                $this->recordPair($match['team_b'][0]['id'], $match['team_b'][1]['id'], $partnerHistory);

                // Opponents
                foreach ($match['team_a'] as $pa) {
                    $playingIds[$pa['id']] = true;
                    foreach ($match['team_b'] as $pb) {
                        $this->recordOpponent($pa['id'], $pb['id'], $opponentHistory);
                    }
                }
                foreach ($match['team_b'] as $pb) {
                    $playingIds[$pb['id']] = true;
                }
            }
            unset($match);

            // Re-identify resting players dynamically: anyone in $normalizedPlayers who is not playing in this round
            $dynamicResting = [];
            foreach ($normalizedPlayers as $np) {
                if (! isset($playingIds[$np['id']])) {
                    $dynamicResting[] = $np;
                }
            }
            $restingPlayers = $dynamicResting;

            foreach ($activePlayers as $p) {
                $playCounts[$p['_idx']]++;
            }
            foreach ($restingPlayers as $p) {
                $restCounts[$p['_idx']]++;
                $lastRestedRound[$p['_idx']] = $r;
            }

            // Construct round payload
            $teamANames = $matches[0]['team_a_names'];
            $teamBNames = $matches[0]['team_b_names'];
            $restingNames = array_map(fn ($p) => $p['name'], $restingPlayers);

            $roundName = match ($r) {
                1 => 'Ronde 1 (Pembuka)',
                2 => 'Ronde 2 (Rotasi)',
                3 => 'Ronde 3 (Final)',
                default => "Ronde {$r}",
            };

            $rounds[$r] = [
                'round' => $r,
                'round_number' => $r,
                'round_name' => $roundName,
                'round_title' => $roundName,
                'court_count' => $activeCourts,
                'matches' => $matches,
                'teamA' => $teamANames,
                'teamB' => $teamBNames,
                'team_a' => $matches[0]['team_a'] ?? [],
                'team_b' => $matches[0]['team_b'] ?? [],
                'team_a_names' => $teamANames,
                'team_b_names' => $teamBNames,
                'resting' => $restingNames,
                'primary_match' => $matches[0] ?? null,
                'resting_players' => $restingPlayers,
            ];
        }

        return $rounds;
    }

    // =========================================================================
    // SINGLE (1 vs 1) — New algorithm
    // =========================================================================

    /**
     * Generate Americano Single rounds (1 vs 1 per court).
     *
     * Rules:
     * - Single format: 1 vs 1 per court (2 players per court).
     * - Minimum 2 players, supports odd player counts with fair resting rotation.
     * - Opponents are distributed as evenly as possible; avoid repeating same opponent
     *   before all unique pairs have been used (when constraints allow).
     * - No "partner" concept — each match is strictly 1 vs 1.
     *
     * @param  array  $players  Array of player models, associative arrays, or player names
     * @param  int  $courtCount  Number of available courts
     * @param  int|null  $roundCount  Optional number of rounds
     */
    private function generateSingleRounds(array $players, int $courtCount, ?int $roundCount = null): array
    {
        $playerCount = count($players);

        // Validation: at least 2 players and 1 court
        if ($playerCount < 2 || $courtCount < 1) {
            return [];
        }

        // 1. Normalize players
        $normalizedPlayers = $this->normalizePlayers($players);

        // 2. Capacity calculations (2 players per court for Single)
        $playersPerCourt = 2;
        $maxCourts = intdiv($playerCount, $playersPerCourt);
        $activeCourts = min($courtCount, $maxCourts);
        $activePerRound = $activeCourts * $playersPerCourt;
        $restingPerRound = $playerCount - $activePerRound;

        // 3. Determine number of rounds
        // Keep the historical default, but honor an explicit scoring round count.
        $maxTheoreticalRounds = max(1, $playerCount - 1);
        if ($roundCount === null || $roundCount < 1) {
            $totalRounds = min(3, $maxTheoreticalRounds);
        } else {
            $totalRounds = $roundCount;
        }

        // Tracking structures
        $opponentHistory = []; // "minId-maxId" => count (how many times these two have faced each other)
        $playCounts = array_fill_keys(array_keys($normalizedPlayers), 0);
        $restCounts = array_fill_keys(array_keys($normalizedPlayers), 0);
        $lastRestedRound = array_fill_keys(array_keys($normalizedPlayers), -1);

        $rounds = [];

        for ($r = 1; $r <= $totalRounds; $r++) {
            // Select active players and resting players for this round
            $selection = $this->selectActiveAndResting(
                $normalizedPlayers,
                $activePerRound,
                $restingPerRound,
                $r,
                $playCounts,
                $restCounts,
                $lastRestedRound
            );

            $activePlayers = $selection['active'];
            $restingPlayers = $selection['resting'];

            // Find the best 1v1 matches for this round (minimizing repeated opponents)
            $matches = $this->findSingleMatchesForRound($activePlayers, $activeCourts, $opponentHistory);

            // Fallback: Try swapping resting players if current set yields sub-optimal results
            if ($matches === null && $restingPerRound > 0) {
                $altResult = $this->findAlternativeSingleMatches(
                    $normalizedPlayers,
                    $activePerRound,
                    $restingPerRound,
                    $activeCourts,
                    $opponentHistory,
                    $selection
                );

                if ($altResult !== null) {
                    $activePlayers = $altResult['active'];
                    $restingPlayers = $altResult['resting'];
                    $matches = $altResult['matches'];
                }
            }

            // Should not normally happen, but guard against empty results
            if ($matches === null || empty($matches)) {
                break;
            }

            // Update opponent history and play/rest counts
            $playingIds = [];
            foreach ($matches as &$match) {
                $pA = $match['team_a'][0];
                $pB = $match['team_b'][0];

                $this->recordOpponent($pA['id'], $pB['id'], $opponentHistory);
                $playingIds[$pA['id']] = true;
                $playingIds[$pB['id']] = true;

                $match['teamA_names'] = $match['team_a_names'];
                $match['teamB_names'] = $match['team_b_names'];
                $match['status'] = 'Scheduled';
            }
            unset($match);

            // Dynamically recalculate resting (anyone not playing)
            $dynamicResting = [];
            foreach ($normalizedPlayers as $np) {
                if (! isset($playingIds[$np['id']])) {
                    $dynamicResting[] = $np;
                }
            }
            $restingPlayers = $dynamicResting;

            foreach ($activePlayers as $p) {
                $playCounts[$p['_idx']]++;
            }
            foreach ($restingPlayers as $p) {
                $restCounts[$p['_idx']]++;
                $lastRestedRound[$p['_idx']] = $r;
            }

            // Construct round payload
            $primaryMatch = $matches[0];
            $teamANames = $primaryMatch['team_a_names'];
            $teamBNames = $primaryMatch['team_b_names'];
            $restingNames = array_map(fn ($p) => $p['name'], $restingPlayers);

            $roundName = match ($r) {
                1 => 'Ronde 1 (Pembuka)',
                2 => 'Ronde 2 (Rotasi)',
                3 => 'Ronde 3 (Final)',
                default => "Ronde {$r}",
            };

            $rounds[$r] = [
                'round' => $r,
                'round_number' => $r,
                'round_name' => $roundName,
                'round_title' => $roundName,
                'court_count' => $activeCourts,
                'matches' => $matches,
                'teamA' => $teamANames,
                'teamB' => $teamBNames,
                'team_a' => $primaryMatch['team_a'],
                'team_b' => $primaryMatch['team_b'],
                'team_a_names' => $teamANames,
                'team_b_names' => $teamBNames,
                'resting' => $restingNames,
                'primary_match' => $primaryMatch,
                'resting_players' => $restingPlayers,
            ];
        }

        return $rounds;
    }

    /**
     * Find the best 1v1 matches for Single mode, minimizing repeated opponents.
     *
     * Uses a greedy approach: tries all possible 1v1 pairings for active players
     * and picks the assignment with the lowest total opponent-repeat cost.
     *
     * @param  array  $activePlayers  Normalized players to match this round
     * @param  int  $courtCount  Number of active courts
     * @param  array  $opponentHistory  Mutable opponent history reference
     * @return array|null Matches array, or null if cannot form any match
     */
    private function findSingleMatchesForRound(array $activePlayers, int $courtCount, array $opponentHistory): ?array
    {
        $count = count($activePlayers);

        // Need exactly 2 players per court
        if ($count < 2 || $courtCount < 1) {
            return null;
        }

        // Generate all possible 1v1 pairings for $courtCount courts
        // from the $count active players (must use exactly $courtCount*2 players)
        $neededPlayers = $courtCount * 2;
        if ($count < $neededPlayers) {
            return null;
        }

        // Get all possible perfect matchings (partitions into 1v1 pairs)
        $allPairings = [];
        $this->partitionIntoSinglePairs($activePlayers, [], $allPairings, $courtCount);

        if (empty($allPairings)) {
            return null;
        }

        // Pick the pairing that minimizes total repeated-opponent cost
        $bestMatches = null;
        $bestScore = PHP_INT_MAX;

        foreach ($allPairings as $pairs) {
            $matches = [];
            $roundCost = 0;

            for ($c = 0; $c < $courtCount; $c++) {
                $pA = $pairs[$c * 2];
                $pB = $pairs[$c * 2 + 1];

                $key = $this->pairKey($pA['id'], $pB['id']);
                $roundCost += ($opponentHistory[$key] ?? 0);

                $courtNum = $c + 1;
                $matches[] = [
                    'court' => $courtNum,
                    'court_name' => "Court {$courtNum}",
                    'team_a' => [$pA],
                    'team_b' => [$pB],
                    'team_a_names' => [$pA['name']],
                    'team_b_names' => [$pB['name']],
                ];
            }

            if ($roundCost < $bestScore) {
                $bestScore = $roundCost;
                $bestMatches = $matches;
                if ($bestScore === 0) {
                    break; // Optimal: no repeated opponents
                }
            }
        }

        return $bestMatches;
    }

    /**
     * Recursively partition players into ordered pairs for Single (1v1) matching.
     *
     * Each "pairing" is an ordered flat array of 2*$courtCount players where
     * [0] vs [1] is court 1, [2] vs [3] is court 2, etc.
     *
     * @param  array  $remaining  Players not yet assigned
     * @param  array  $current  Current flat assignment list
     * @param  array  &$results  Collected valid pairings
     * @param  int  $courtCount  How many pairs are needed
     */
    private function partitionIntoSinglePairs(array $remaining, array $current, array &$results, int $courtCount): void
    {
        if (count($results) >= 20) {
            // Cap search to keep performance predictable
            return;
        }

        $neededPairs = $courtCount * 2;
        if (count($current) === $neededPairs) {
            $results[] = $current;

            return;
        }

        if (empty($remaining)) {
            return;
        }

        // Pick the first remaining player as Side A for the next court
        $pA = array_shift($remaining);
        $remainingCount = count($remaining);

        for ($i = 0; $i < $remainingCount; $i++) {
            $pB = $remaining[$i];

            $newRemaining = $remaining;
            unset($newRemaining[$i]);
            $newRemaining = array_values($newRemaining);

            $newCurrent = $current;
            $newCurrent[] = $pA;
            $newCurrent[] = $pB;

            $this->partitionIntoSinglePairs($newRemaining, $newCurrent, $results, $courtCount);
        }
    }

    /**
     * If the initial active selection produces suboptimal Single matches,
     * try swapping one active player with one resting player.
     */
    private function findAlternativeSingleMatches(
        array $allPlayers,
        int $activeCount,
        int $restingCount,
        int $courtCount,
        array $opponentHistory,
        array $currentSelection
    ): ?array {
        $active = $currentSelection['active'];
        $resting = $currentSelection['resting'];

        for ($a = count($active) - 1; $a >= 0; $a--) {
            for ($r = 0; $r < count($resting); $r++) {
                $newActive = $active;
                $newResting = $resting;

                $temp = $newActive[$a];
                $newActive[$a] = $newResting[$r];
                $newResting[$r] = $temp;

                $matches = $this->findSingleMatchesForRound($newActive, $courtCount, $opponentHistory);
                if ($matches !== null) {
                    return [
                        'active' => $newActive,
                        'resting' => $newResting,
                        'matches' => $matches,
                    ];
                }
            }
        }

        return null;
    }

    // =========================================================================
    // SHARED HELPERS
    // =========================================================================

    /**
     * Normalize players array to consistent structure.
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
                $avatar = $p->avatar ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80';
            } elseif (is_array($p)) {
                $id = $p['player_id'] ?? $p['id'] ?? ($index + 1);
                $name = $p['nama'] ?? $p['name'] ?? "Player {$id}";
                $level = $p['level'] ?? 'Intermediate';
                $gender = $p['gender'] ?? 'Male';
                $phone = $p['no_hp'] ?? $p['phone'] ?? '-';
                $avatar = $p['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80';
            } else {
                $id = $index + 1;
                $name = (string) $p;
                $level = 'Intermediate';
                $gender = 'Male';
                $phone = '-';
                $avatar = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80';
            }

            $normalized[] = [
                '_idx' => $index,
                'id' => $id,
                'name' => $name,
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
     * Fair sit-out queue: prioritizes players who rested previously to play.
     */
    private function selectActiveAndResting(
        array $players,
        int $activeCount,
        int $restingCount,
        int $currentRound,
        array $playCounts,
        array $restCounts,
        array $lastRestedRound
    ): array {
        if ($restingCount <= 0) {
            return [
                'active' => $players,
                'resting' => [],
            ];
        }

        $indices = array_keys($players);

        usort($indices, function ($a, $b) use ($currentRound, $playCounts, $restCounts, $lastRestedRound) {
            // Players who rested last round are prioritized to play this round
            $aRestedLast = ($lastRestedRound[$a] === $currentRound - 1);
            $bRestedLast = ($lastRestedRound[$b] === $currentRound - 1);
            if ($aRestedLast && ! $bRestedLast) {
                return -1;
            }
            if (! $aRestedLast && $bRestedLast) {
                return 1;
            }

            // Prioritize lowest play count
            if ($playCounts[$a] !== $playCounts[$b]) {
                return $playCounts[$a] <=> $playCounts[$b];
            }

            // Prioritize highest rest count
            if ($restCounts[$a] !== $restCounts[$b]) {
                return $restCounts[$b] <=> $restCounts[$a];
            }

            // Cyclic shift tie-breaker based on round
            return ($a + $currentRound) <=> ($b + $currentRound);
        });

        $activeIndices = array_slice($indices, 0, $activeCount);
        $restingIndices = array_slice($indices, $activeCount);

        $active = [];
        foreach ($activeIndices as $idx) {
            $active[] = $players[$idx];
        }

        $resting = [];
        foreach ($restingIndices as $idx) {
            $resting[] = $players[$idx];
        }

        return [
            'active' => $active,
            'resting' => $resting,
        ];
    }

    /**
     * Find valid matches for active players where no pair has partnered before (Double).
     */
    private function findMatchesForRound(
        array $activePlayers,
        int $courtCount,
        array $partnerHistory,
        array $opponentHistory
    ): ?array {
        // Step A: Find all valid pair partitions where no partner repeats
        $allPairings = [];
        $this->partitionIntoPairs($activePlayers, [], $partnerHistory, $allPairings);

        if (empty($allPairings)) {
            // Explicit Total of N configurations may outlive all unique partner combinations.
            $this->partitionIntoPairs($activePlayers, [], [], $allPairings);
        }

        // Step B: From the valid pairings, find the court assignment that minimizes repeated opponents
        $bestMatches = null;
        $bestScore = PHP_INT_MAX;

        foreach ($allPairings as $pairs) {
            // $pairs has $courtCount * 2 pairs.
            // Assign pairs into courts: Pair (2*c) vs Pair (2*c + 1)
            $matches = [];
            $roundOpponentCost = 0;

            for ($c = 0; $c < $courtCount; $c++) {
                $teamA = $pairs[$c * 2];
                $teamB = $pairs[$c * 2 + 1];

                // Compute opponent repeat cost
                foreach ($teamA as $pa) {
                    foreach ($teamB as $pb) {
                        $key = $this->pairKey($pa['id'], $pb['id']);
                        $roundOpponentCost += ($opponentHistory[$key] ?? 0);
                    }
                }

                $courtNum = $c + 1;
                $matches[] = [
                    'court' => $courtNum,
                    'court_name' => "Court {$courtNum}",
                    'team_a' => $teamA,
                    'team_b' => $teamB,
                    'team_a_names' => [$teamA[0]['name'], $teamA[1]['name']],
                    'team_b_names' => [$teamB[0]['name'], $teamB[1]['name']],
                ];
            }

            if ($roundOpponentCost < $bestScore) {
                $bestScore = $roundOpponentCost;
                $bestMatches = $matches;
                if ($bestScore === 0) {
                    break; // Optimal found
                }
            }
        }

        return $bestMatches;
    }

    /**
     * Deterministic recursive backtracking to partition players into pairs of 2
     * without any repeated partners.
     */
    private function partitionIntoPairs(
        array $remaining,
        array $currentPairs,
        array $partnerHistory,
        array &$results
    ): void {
        if (count($results) >= 15) {
            // Cap search breadth to maintain high performance
            return;
        }

        if (empty($remaining)) {
            $results[] = $currentPairs;

            return;
        }

        // Pick the first player
        $p1 = array_shift($remaining);

        // Try pairing with each of the other remaining players
        $remainingCount = count($remaining);
        for ($i = 0; $i < $remainingCount; $i++) {
            $p2 = $remaining[$i];
            $key = $this->pairKey($p1['id'], $p2['id']);

            // Constraint: Player must not have partnered with this player before
            if (! isset($partnerHistory[$key])) {
                $newRemaining = $remaining;
                unset($newRemaining[$i]);
                $newRemaining = array_values($newRemaining);

                $newPairs = $currentPairs;
                $newPairs[] = [$p1, $p2];

                $this->partitionIntoPairs($newRemaining, $newPairs, $partnerHistory, $results);
            }
        }
    }

    /**
     * If the initial active selection cannot form non-repeating pairs (Double),
     * try swapping resting players to find a viable active set.
     */
    private function findAlternativeMatches(
        array $allPlayers,
        int $activeCount,
        int $restingCount,
        int $courtCount,
        array $partnerHistory,
        array $opponentHistory,
        array $currentSelection
    ): ?array {
        $active = $currentSelection['active'];
        $resting = $currentSelection['resting'];

        // Try swapping 1 active player with 1 resting player
        for ($a = count($active) - 1; $a >= 0; $a--) {
            for ($r = 0; $r < count($resting); $r++) {
                $newActive = $active;
                $newResting = $resting;

                $temp = $newActive[$a];
                $newActive[$a] = $newResting[$r];
                $newResting[$r] = $temp;

                $matches = $this->findMatchesForRound($newActive, $courtCount, $partnerHistory, $opponentHistory);
                if ($matches !== null) {
                    return [
                        'active' => $newActive,
                        'resting' => $newResting,
                        'matches' => $matches,
                    ];
                }
            }
        }

        return null;
    }

    private function recordPair(mixed $id1, mixed $id2, array &$history): void
    {
        $key = $this->pairKey($id1, $id2);
        $history[$key] = true;
    }

    private function recordOpponent(mixed $id1, mixed $id2, array &$history): void
    {
        $key = $this->pairKey($id1, $id2);
        $history[$key] = ($history[$key] ?? 0) + 1;
    }

    private function pairKey(mixed $id1, mixed $id2): string
    {
        return strcmp((string) $id1, (string) $id2) < 0
            ? "{$id1}-{$id2}"
            : "{$id2}-{$id1}";
    }
}
