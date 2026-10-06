/// ScoringEngine is the source-of-truth scoring domain service for Matcha Mobile,
/// ported directly from web/develop (ScoringService.php & ScoringController.php).
class ScoringSystemConfig {
  final String type; // 'total_of_sets' | 'first_to_games'
  final String category; // 'sets' | 'games'
  final int maxSets;
  final int targetSets;
  final int targetGames;
  final String label;
  final bool isSets;

  const ScoringSystemConfig({
    required this.type,
    required this.category,
    required this.maxSets,
    required this.targetSets,
    required this.targetGames,
    required this.label,
    required this.isSets,
  });

  Map<String, dynamic> toMap() => {
    'type': type,
    'category': category,
    'max_sets': maxSets,
    'target_sets': targetSets,
    'target_games': targetGames,
    'label': label,
    'is_sets': isSets,
  };
}

class ScoringMatchState {
  final int gamesA;
  final int gamesB;
  final int idxA; // 0: 0, 1: 15, 2: 30, 3: 40
  final int idxB; // 0: 0, 1: 15, 2: 30, 3: 40
  final String pointDisplayA;
  final String pointDisplayB;
  final bool isDeuce;
  final String? advantage; // 'A' | 'B' | null
  final int setNumber;
  final int setsA;
  final int setsB;
  final String status; // 'in_progress' | 'completed'
  final String? winnerTeam; // 'Team A' | 'Team B' | null
  final int version;
  final String? lastEventId;
  final List<Map<String, dynamic>> setHistory;

  const ScoringMatchState({
    this.gamesA = 0,
    this.gamesB = 0,
    this.idxA = 0,
    this.idxB = 0,
    this.pointDisplayA = '0',
    this.pointDisplayB = '0',
    this.isDeuce = false,
    this.advantage,
    this.setNumber = 1,
    this.setsA = 0,
    this.setsB = 0,
    this.status = 'in_progress',
    this.winnerTeam,
    this.version = 0,
    this.lastEventId,
    this.setHistory = const [],
  });

  bool get isCompleted => status == 'completed';

  ScoringMatchState copyWith({
    int? gamesA,
    int? gamesB,
    int? idxA,
    int? idxB,
    String? pointDisplayA,
    String? pointDisplayB,
    bool? isDeuce,
    String? advantage,
    bool clearAdvantage = false,
    int? setNumber,
    int? setsA,
    int? setsB,
    String? status,
    String? winnerTeam,
    int? version,
    String? lastEventId,
    List<Map<String, dynamic>>? setHistory,
  }) {
    return ScoringMatchState(
      gamesA: gamesA ?? this.gamesA,
      gamesB: gamesB ?? this.gamesB,
      idxA: idxA ?? this.idxA,
      idxB: idxB ?? this.idxB,
      pointDisplayA: pointDisplayA ?? this.pointDisplayA,
      pointDisplayB: pointDisplayB ?? this.pointDisplayB,
      isDeuce: isDeuce ?? this.isDeuce,
      advantage: clearAdvantage ? null : (advantage ?? this.advantage),
      setNumber: setNumber ?? this.setNumber,
      setsA: setsA ?? this.setsA,
      setsB: setsB ?? this.setsB,
      status: status ?? this.status,
      winnerTeam: winnerTeam ?? this.winnerTeam,
      version: version ?? this.version,
      lastEventId: lastEventId ?? this.lastEventId,
      setHistory: setHistory ?? this.setHistory,
    );
  }

  Map<String, dynamic> toMap() => {
    'score_a': gamesA,
    'score_b': gamesB,
    'games_a': gamesA,
    'games_b': gamesB,
    'idx_a': idxA,
    'idx_b': idxB,
    'point_display_a': pointDisplayA,
    'point_display_b': pointDisplayB,
    'is_deuce': isDeuce,
    'advantage': advantage,
    'set_number': setNumber,
    'sets_a': setsA,
    'sets_b': setsB,
    'status': status,
    'winner_team': winnerTeam,
    'version': version,
    'last_event_id': lastEventId,
    'set_history': setHistory,
  };

  factory ScoringMatchState.fromMap(Map<String, dynamic> map) {
    return ScoringMatchState(
      gamesA: (map['games_a'] ?? map['score_a'] ?? 0) as int,
      gamesB: (map['games_b'] ?? map['score_b'] ?? 0) as int,
      idxA: (map['idx_a'] ?? 0) as int,
      idxB: (map['idx_b'] ?? 0) as int,
      pointDisplayA: (map['point_display_a'] ?? '0').toString(),
      pointDisplayB: (map['point_display_b'] ?? '0').toString(),
      isDeuce: (map['is_deuce'] ?? false) as bool,
      advantage: map['advantage'] as String?,
      setNumber: (map['set_number'] ?? 1) as int,
      setsA: (map['sets_a'] ?? 0) as int,
      setsB: (map['sets_b'] ?? 0) as int,
      status: (map['status'] ?? 'in_progress').toString(),
      winnerTeam: map['winner_team'] as String?,
      version: (map['version'] ?? 0) as int,
      lastEventId: map['last_event_id'] as String?,
      setHistory: (map['set_history'] as List<dynamic>?)
              ?.map((e) => Map<String, dynamic>.from(e as Map))
              .toList() ??
          [],
    );
  }
}

class ScoringEngine {
  ScoringEngine._();

  static const List<String> tennisPoints = ['0', '15', '30', '40'];

  /// Restore and parse tennis point ladder indices, deuce, advantage, and display labels
  /// from stored database string representations ('0', '15', '30', '40', 'ADV', 'Game').
  static ({
    int idxA,
    int idxB,
    String pointDisplayA,
    String pointDisplayB,
    bool isDeuce,
    String? advantage,
  }) parseTennisPoints(String? rawA, String? rawB) {
    final cleanA = (rawA ?? '0').trim().toUpperCase();
    final cleanB = (rawB ?? '0').trim().toUpperCase();

    if (cleanA == 'ADV') {
      return (
        idxA: 3,
        idxB: 3,
        pointDisplayA: 'ADV',
        pointDisplayB: '40',
        isDeuce: true,
        advantage: 'A',
      );
    }
    if (cleanB == 'ADV') {
      return (
        idxA: 3,
        idxB: 3,
        pointDisplayA: '40',
        pointDisplayB: 'ADV',
        isDeuce: true,
        advantage: 'B',
      );
    }
    if (cleanA == '40' && cleanB == '40') {
      return (
        idxA: 3,
        idxB: 3,
        pointDisplayA: '40',
        pointDisplayB: '40',
        isDeuce: true,
        advantage: null,
      );
    }

    int getIdx(String p) {
      final idx = tennisPoints.indexOf(p);
      return idx >= 0 ? idx : 0;
    }

    final idxA = getIdx(cleanA);
    final idxB = getIdx(cleanB);
    final displayA = tennisPoints.contains(cleanA)
        ? cleanA
        : (cleanA == 'GAME' ? 'Game' : '0');
    final displayB = tennisPoints.contains(cleanB)
        ? cleanB
        : (cleanB == 'GAME' ? 'Game' : '0');

    return (
      idxA: idxA,
      idxB: idxB,
      pointDisplayA: displayA,
      pointDisplayB: displayB,
      isDeuce: false,
      advantage: null,
    );
  }

  /// Reconstructs full match state from stored database row values.
  static ScoringMatchState restoreMatchState({
    required int gamesA,
    required int gamesB,
    String? rawPointA,
    String? rawPointB,
    int? setScoreA,
    int? setScoreB,
    String? matchStatus,
    String? statusScore,
    String? winnerTeam,
    int version = 0,
    String? lastEventId,
    int setNumber = 1,
  }) {
    final tennis = parseTennisPoints(rawPointA, rawPointB);
    final isDone = (matchStatus ?? '').toLowerCase() == 'completed' ||
        (matchStatus ?? '').toLowerCase() == 'finished' ||
        (statusScore ?? '').toLowerCase() == 'final' ||
        (statusScore ?? '').toLowerCase() == 'completed';

    return ScoringMatchState(
      gamesA: gamesA,
      gamesB: gamesB,
      idxA: tennis.idxA,
      idxB: tennis.idxB,
      pointDisplayA: tennis.pointDisplayA,
      pointDisplayB: tennis.pointDisplayB,
      isDeuce: tennis.isDeuce,
      advantage: tennis.advantage,
      setNumber: setNumber,
      setsA: setScoreA ?? (isDone && winnerTeam == 'Team A' ? 1 : 0),
      setsB: setScoreB ?? (isDone && winnerTeam == 'Team B' ? 1 : 0),
      status: isDone ? 'completed' : 'in_progress',
      winnerTeam: winnerTeam,
      version: version,
      lastEventId: lastEventId,
    );
  }

  /// Parse scoring system string according to Web's ScoringService::detectScoringSystem.
  /// Supported systems:
  /// Total of 3, 4, 5, 6, 7 (Sets)
  /// First to 8, 11, 15, 21 (Games)
  static ScoringSystemConfig detectScoringSystem([String? scoringSystem]) {
    final str = (scoringSystem ?? '').trim();

    // Group Total of X (Sets)
    final totalOfRegex = RegExp(r'total\s*of\s*(\d+)', caseSensitive: false);
    final totalOfMatch = totalOfRegex.firstMatch(str);
    if (totalOfMatch != null) {
      int sets = int.tryParse(totalOfMatch.group(1) ?? '3') ?? 3;
      if (![3, 4, 5, 6, 7].contains(sets)) {
        sets = 3;
      }
      final targetSets = (sets ~/ 2) + 1;
      return ScoringSystemConfig(
        type: 'total_of_sets',
        category: 'sets',
        maxSets: sets,
        targetSets: targetSets,
        targetGames: 6,
        label: 'Total of $sets',
        isSets: true,
      );
    }

    // Group First to X (Games)
    final firstToRegex = RegExp(r'first\s*to\s*(\d+)', caseSensitive: false);
    final firstToMatch = firstToRegex.firstMatch(str);
    if (firstToMatch != null) {
      int games = int.tryParse(firstToMatch.group(1) ?? '8') ?? 8;
      if (![8, 11, 15, 21].contains(games)) {
        games = 8;
      }
      return ScoringSystemConfig(
        type: 'first_to_games',
        category: 'games',
        maxSets: 1,
        targetSets: 1,
        targetGames: games,
        label: 'First to $games',
        isSets: false,
      );
    }

    // Default fallback to Total of 3
    return const ScoringSystemConfig(
      type: 'total_of_sets',
      category: 'sets',
      maxSets: 3,
      targetSets: 2,
      targetGames: 6,
      label: 'Total of 3',
      isSets: true,
    );
  }

  /// Mutates 1 point ('A' or 'B') to match state.
  /// Exactly mirrors web's ScoringController::applyPointDeltaToState.
  static ScoringMatchState applyPointDelta({
    required ScoringMatchState currentState,
    required String teamWon, // 'A' | 'B'
    required ScoringSystemConfig scoringSystem,
    String? eventId,
  }) {
    if (currentState.status == 'completed') {
      return currentState;
    }

    int idxA = currentState.idxA;
    int idxB = currentState.idxB;
    bool isDeuce = currentState.isDeuce;
    String? advantage = currentState.advantage;
    int gamesA = currentState.gamesA;
    int gamesB = currentState.gamesB;
    int setsA = currentState.setsA;
    int setsB = currentState.setsB;
    String status = currentState.status;
    String? winnerTeam = currentState.winnerTeam;

    final isSets = scoringSystem.isSets;
    final targetGames = scoringSystem.targetGames;

    // Mutation with tennis ladder (0 -> 15 -> 30 -> 40 -> Game Win)
    if (teamWon == 'A') {
      if (isDeuce) {
        if (advantage == 'A') {
          // Game won by Team A
          gamesA++;
          idxA = 0;
          idxB = 0;
          isDeuce = false;
          advantage = null;
        } else if (advantage == 'B') {
          // Return to Deuce
          advantage = null;
        } else {
          advantage = 'A';
        }
      } else {
        if (idxA < 3) {
          idxA++;
          if (idxA == 3 && idxB == 3) {
            isDeuce = true;
            advantage = null;
          }
        } else if (idxA == 3 && idxB < 3) {
          // Game won by Team A
          gamesA++;
          idxA = 0;
          idxB = 0;
          isDeuce = false;
          advantage = null;
        }
      }
    } else if (teamWon == 'B') {
      if (isDeuce) {
        if (advantage == 'B') {
          // Game won by Team B
          gamesB++;
          idxA = 0;
          idxB = 0;
          isDeuce = false;
          advantage = null;
        } else if (advantage == 'A') {
          // Return to Deuce
          advantage = null;
        } else {
          advantage = 'B';
        }
      } else {
        if (idxB < 3) {
          idxB++;
          if (idxB == 3 && idxA == 3) {
            isDeuce = true;
            advantage = null;
          }
        } else if (idxB == 3 && idxA < 3) {
          // Game won by Team B
          gamesB++;
          idxA = 0;
          idxB = 0;
          isDeuce = false;
          advantage = null;
        }
      }
    }

    // Resolve point displays
    String pointDisplayA;
    String pointDisplayB;
    if (isDeuce) {
      pointDisplayA = (advantage == 'A') ? 'ADV' : '40';
      pointDisplayB = (advantage == 'B') ? 'ADV' : '40';
    } else {
      pointDisplayA = (idxA >= 0 && idxA < tennisPoints.length)
          ? tennisPoints[idxA]
          : '0';
      pointDisplayB = (idxB >= 0 && idxB < tennisPoints.length)
          ? tennisPoints[idxB]
          : '0';
    }

    // Check set/match completion condition
    if (!isSets) {
      if (targetGames > 0 && gamesA >= targetGames) {
        status = 'completed';
        winnerTeam = 'Team A';
        setsA = 1;
      } else if (targetGames > 0 && gamesB >= targetGames) {
        status = 'completed';
        winnerTeam = 'Team B';
        setsB = 1;
      }
    } else {
      // Requirement terbaru: untuk ronde Total of (Sets), tim pertama mencapai 6 game langsung menang,
      // termasuk 6-0, 6-4, 6-5, atau 5-6, tanpa selisih 2 game/tie-break dan tidak boleh lanjut ke 7.
      if (gamesA >= 6) {
        status = 'completed';
        winnerTeam = 'Team A';
        setsA = 1;
      } else if (gamesB >= 6) {
        status = 'completed';
        winnerTeam = 'Team B';
        setsB = 1;
      }
    }

    final newVersion = currentState.version + 1;

    return currentState.copyWith(
      gamesA: gamesA,
      gamesB: gamesB,
      idxA: idxA,
      idxB: idxB,
      pointDisplayA: pointDisplayA,
      pointDisplayB: pointDisplayB,
      isDeuce: isDeuce,
      advantage: advantage,
      clearAdvantage: advantage == null,
      setsA: setsA,
      setsB: setsB,
      status: status,
      winnerTeam: winnerTeam,
      version: newVersion,
      lastEventId: eventId ?? currentState.lastEventId,
      setHistory: [
        {
          'set': currentState.setNumber,
          'score_a': gamesA,
          'score_b': gamesB,
        }
      ],
    );
  }

  /// Force/manual completion or walkover.
  /// Mirrors web's ScoringController::applyCompletionToState.
  static ScoringMatchState applyCompletion({
    required ScoringMatchState currentState,
    required ScoringSystemConfig scoringSystem,
    String? explicitWinner, // 'Team A' | 'Team B' | 'A' | 'B'
    int? incomingGamesA,
    int? incomingGamesB,
    String? eventId,
  }) {
    final gamesA = incomingGamesA ?? currentState.gamesA;
    final gamesB = incomingGamesB ?? currentState.gamesB;

    String? winner = explicitWinner;
    if (winner == 'A') winner = 'Team A';
    if (winner == 'B') winner = 'Team B';
    winner ??= (gamesA >= gamesB ? 'Team A' : 'Team B');

    final setsA = (winner == 'Team A') ? 1 : 0;
    final setsB = (winner == 'Team B') ? 1 : 0;
    final newVersion = currentState.version + 1;

    return currentState.copyWith(
      gamesA: gamesA,
      gamesB: gamesB,
      idxA: 0,
      idxB: 0,
      pointDisplayA: 'Game',
      pointDisplayB: '0',
      isDeuce: false,
      clearAdvantage: true,
      status: 'completed',
      winnerTeam: winner,
      setsA: setsA,
      setsB: setsB,
      version: newVersion,
      lastEventId: eventId ?? currentState.lastEventId,
      setHistory: [
        {
          'set': currentState.setNumber,
          'score_a': gamesA,
          'score_b': gamesB,
        }
      ],
    );
  }

  /// Score downgrade regression guard.
  /// Checks whether an incoming completion request would downgrade an already official completed score.
  /// Mirrors web's $staleCompletionWouldDowngrade check.
  static bool isStaleCompletionDowngrade({
    required ScoringMatchState officialCompletedState,
    required int incomingGamesA,
    required int incomingGamesB,
  }) {
    if (officialCompletedState.status != 'completed') return false;

    final officialA = officialCompletedState.gamesA;
    final officialB = officialCompletedState.gamesB;

    return (incomingGamesA + incomingGamesB < officialA + officialB) ||
        (incomingGamesA < officialA && incomingGamesB <= officialB) ||
        (incomingGamesB < officialB && incomingGamesA <= officialA);
  }
}
