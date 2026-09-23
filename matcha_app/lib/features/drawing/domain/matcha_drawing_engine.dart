import 'dart:math';
import '../../games/domain/game_wizard_model.dart';

class DrawingMatch {
  final int courtNumber;
  final List<GamePlayerItem> teamA;
  final List<GamePlayerItem> teamB;
  String status; // 'Scheduled', 'In Progress', 'Completed'
  int scoreA;
  int scoreB;
  int gamesWonA;
  int gamesWonB;

  DrawingMatch({
    required this.courtNumber,
    required this.teamA,
    required this.teamB,
    this.status = 'Scheduled',
    this.scoreA = 0,
    this.scoreB = 0,
    this.gamesWonA = 0,
    this.gamesWonB = 0,
  });

  String get teamANames => teamA.map((p) => p.name).join(' & ');
  String get teamBNames => teamB.map((p) => p.name).join(' & ');
}

class DrawingRound {
  final int roundNumber;
  final List<DrawingMatch> matches;
  final List<GamePlayerItem> restingPlayers;

  DrawingRound({
    required this.roundNumber,
    required this.matches,
    this.restingPlayers = const [],
  });
}

class MatchaDrawingEngine {
  /// Generates tournament drawing rounds based on game type, mode, court count, and players.
  static List<DrawingRound> generateDrawing({
    required List<GamePlayerItem> players,
    required int courtCount,
    required String gameType,
    required String playMode,
    int? roundCount,
    bool shufflePlayers = false,
  }) {
    if (players.isEmpty || courtCount < 1) return [];

    List<GamePlayerItem> playerPool = List.from(players);
    if (shufflePlayers) {
      playerPool.shuffle(Random());
    }

    if (playMode.toLowerCase() == 'single') {
      return _generateSingleRounds(playerPool, courtCount, roundCount);
    }

    if (gameType.toLowerCase().contains('team americano')) {
      return _generateTeamAmericanoRounds(playerPool, courtCount, roundCount);
    }

    // Default: Americano Double (2 vs 2) with rotating partners
    return _generateAmericanoDoubleRounds(playerPool, courtCount, roundCount);
  }

  /// Americano Double (2 vs 2) Partner Rotation algorithm (matches PHP AmericanoService)
  static List<DrawingRound> _generateAmericanoDoubleRounds(
    List<GamePlayerItem> players,
    int courtCount,
    int? requestedRounds,
  ) {
    final int playerCount = players.length;
    if (playerCount < 4) return [];

    final int playersPerCourt = 4;
    final int maxCourts = playerCount ~/ playersPerCourt;
    final int activeCourts = min(courtCount, max(1, maxCourts));
    final int activePerRound = activeCourts * playersPerCourt;

    final int totalRounds = requestedRounds ?? (playerCount <= 4 ? 3 : min(playerCount - 1, 5));

    final Map<String, bool> partnerHistory = {};
    final Map<String, int> playCounts = {for (var p in players) p.id: 0};
    final Map<String, int> restCounts = {for (var p in players) p.id: 0};

    String pairKey(String id1, String id2) {
      return id1.compareTo(id2) < 0 ? '$id1-$id2' : '$id2-$id1';
    }

    List<DrawingRound> rounds = [];

    for (int r = 1; r <= totalRounds; r++) {
      // 1. Sort players to select active & resting based on fair rest queue
      List<GamePlayerItem> sortedByRest = List.from(players)
        ..sort((a, b) {
          int restDiff = (restCounts[a.id] ?? 0).compareTo(restCounts[b.id] ?? 0);
          if (restDiff != 0) return -restDiff; // highest rest count played first
          return (playCounts[a.id] ?? 0).compareTo(playCounts[b.id] ?? 0);
        });

      List<GamePlayerItem> active = sortedByRest.take(activePerRound).toList();
      List<GamePlayerItem> resting = sortedByRest.skip(activePerRound).toList();

      for (var p in resting) {
        restCounts[p.id] = (restCounts[p.id] ?? 0) + 1;
      }
      for (var p in active) {
        playCounts[p.id] = (playCounts[p.id] ?? 0) + 1;
      }

      // 2. Generate 4-player Americano pairings for each court
      List<DrawingMatch> matches = [];

      // For 4 players (1 Court standard Americano):
      // Round 1: [P0, P1] vs [P2, P3]
      // Round 2: [P0, P2] vs [P1, P3]
      // Round 3: [P0, P3] vs [P1, P2]
      if (active.length == 4) {
        final p0 = active[0];
        final p1 = active[1];
        final p2 = active[2];
        final p3 = active[3];

        if (r % 3 == 1) {
          matches.add(DrawingMatch(courtNumber: 1, teamA: [p0, p1], teamB: [p2, p3]));
          partnerHistory[pairKey(p0.id, p1.id)] = true;
          partnerHistory[pairKey(p2.id, p3.id)] = true;
        } else if (r % 3 == 2) {
          matches.add(DrawingMatch(courtNumber: 1, teamA: [p0, p2], teamB: [p1, p3]));
          partnerHistory[pairKey(p0.id, p2.id)] = true;
          partnerHistory[pairKey(p1.id, p3.id)] = true;
        } else {
          matches.add(DrawingMatch(courtNumber: 1, teamA: [p0, p3], teamB: [p1, p2]));
          partnerHistory[pairKey(p0.id, p3.id)] = true;
          partnerHistory[pairKey(p1.id, p2.id)] = true;
        }
      } else {
        // Multi-court or > 4 players: dynamic pairing heuristic avoiding partner repeats
        List<GamePlayerItem> remainingActive = List.from(active);
        for (int c = 1; c <= activeCourts; c++) {
          if (remainingActive.length < 4) break;

          GamePlayerItem pA1 = remainingActive.removeAt(0);

          // Find best partner for pA1 who hasn't partnered before
          int pA2Idx = remainingActive.indexWhere((cand) => !partnerHistory.containsKey(pairKey(pA1.id, cand.id)));
          if (pA2Idx == -1) pA2Idx = 0;
          GamePlayerItem pA2 = remainingActive.removeAt(pA2Idx);

          GamePlayerItem pB1 = remainingActive.removeAt(0);
          int pB2Idx = remainingActive.indexWhere((cand) => !partnerHistory.containsKey(pairKey(pB1.id, cand.id)));
          if (pB2Idx == -1) pB2Idx = 0;
          GamePlayerItem pB2 = remainingActive.removeAt(pB2Idx);

          partnerHistory[pairKey(pA1.id, pA2.id)] = true;
          partnerHistory[pairKey(pB1.id, pB2.id)] = true;

          matches.add(DrawingMatch(courtNumber: c, teamA: [pA1, pA2], teamB: [pB1, pB2]));
        }
      }

      rounds.add(DrawingRound(roundNumber: r, matches: matches, restingPlayers: resting));
    }

    return rounds;
  }

  /// Team Americano (Fixed Pairs) round robin algorithm
  static List<DrawingRound> _generateTeamAmericanoRounds(
    List<GamePlayerItem> players,
    int courtCount,
    int? requestedRounds,
  ) {
    if (players.length < 4) return [];

    // Form fixed pairs of 2 players
    List<List<GamePlayerItem>> fixedTeams = [];
    for (int i = 0; i < players.length - 1; i += 2) {
      fixedTeams.add([players[i], players[i + 1]]);
    }

    int numTeams = fixedTeams.length;
    if (numTeams < 2) return [];

    int totalRounds = requestedRounds ?? max(1, numTeams - 1);
    List<DrawingRound> rounds = [];

    // Round-robin schedule generator using Berger table
    List<List<GamePlayerItem>> teamList = List.from(fixedTeams);
    if (teamList.length % 2 != 0) {
      teamList.add([
        const GamePlayerItem(id: 'dummy', name: 'BYE / Istirahat', isGuest: true),
        const GamePlayerItem(id: 'dummy', name: 'BYE / Istirahat', isGuest: true),
      ]);
    }

    int n = teamList.length;
    for (int r = 1; r <= totalRounds; r++) {
      List<DrawingMatch> matches = [];
      List<GamePlayerItem> resting = [];

      int court = 1;
      for (int i = 0; i < n ~/ 2; i++) {
        var t1 = teamList[i];
        var t2 = teamList[n - 1 - i];

        if (t1.first.id == 'dummy') {
          resting.addAll(t2);
        } else if (t2.first.id == 'dummy') {
          resting.addAll(t1);
        } else {
          if (court <= courtCount) {
            matches.add(DrawingMatch(courtNumber: court++, teamA: t1, teamB: t2));
          } else {
            resting.addAll(t1);
            resting.addAll(t2);
          }
        }
      }

      rounds.add(DrawingRound(roundNumber: r, matches: matches, restingPlayers: resting));

      // Rotate teams fixing the first element
      var last = teamList.removeLast();
      teamList.insert(1, last);
    }

    return rounds;
  }

  /// Single (1 vs 1) round robin algorithm
  static List<DrawingRound> _generateSingleRounds(
    List<GamePlayerItem> players,
    int courtCount,
    int? requestedRounds,
  ) {
    if (players.length < 2) return [];

    List<GamePlayerItem> pool = List.from(players);
    if (pool.length % 2 != 0) {
      pool.add(const GamePlayerItem(id: 'dummy', name: 'BYE', isGuest: true));
    }

    int n = pool.length;
    int totalRounds = requestedRounds ?? (n - 1);
    List<DrawingRound> rounds = [];

    for (int r = 1; r <= totalRounds; r++) {
      List<DrawingMatch> matches = [];
      List<GamePlayerItem> resting = [];

      int court = 1;
      for (int i = 0; i < n ~/ 2; i++) {
        var p1 = pool[i];
        var p2 = pool[n - 1 - i];

        if (p1.id == 'dummy') {
          resting.add(p2);
        } else if (p2.id == 'dummy') {
          resting.add(p1);
        } else {
          if (court <= courtCount) {
            matches.add(DrawingMatch(courtNumber: court++, teamA: [p1], teamB: [p2]));
          } else {
            resting.add(p1);
            resting.add(p2);
          }
        }
      }

      rounds.add(DrawingRound(roundNumber: r, matches: matches, restingPlayers: resting));

      var last = pool.removeLast();
      pool.insert(1, last);
    }

    return rounds;
  }
}
