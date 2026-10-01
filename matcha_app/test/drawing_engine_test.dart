import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';

void main() {
  group('MatchaDrawingEngine - Comprehensive Algorithm Verification', () {
    String pairKey(String id1, String id2) =>
        id1.compareTo(id2) < 0 ? '$id1-$id2' : '$id2-$id1';

    // -------------------------------------------------------------------------
    // TEST 1: Standard 4 Players Americano Double (1 Court, 3 Rounds)
    // -------------------------------------------------------------------------
    test('1. Americano 4-player Double 2v2: 3 rounds without repeating partners', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'Player 1'),
        const GamePlayerItem(id: 'p2', name: 'Player 2'),
        const GamePlayerItem(id: 'p3', name: 'Player 3'),
        const GamePlayerItem(id: 'p4', name: 'Player 4'),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      expect(rounds.length, 3, reason: 'Must generate exactly 3 rounds for 4 players');

      final Map<String, int> partnerPairs = {};

      for (var r in rounds) {
        expect(r.matches.length, 1, reason: '1 court must have 1 match per round');
        expect(r.restingPlayers.length, 0, reason: '4 players on 1 court has 0 bench players');

        final match = r.matches.first;
        expect(match.teamA.length, 2);
        expect(match.teamB.length, 2);

        final pairA = pairKey(match.teamA[0].id, match.teamA[1].id);
        final pairB = pairKey(match.teamB[0].id, match.teamB[1].id);

        partnerPairs[pairA] = (partnerPairs[pairA] ?? 0) + 1;
        partnerPairs[pairB] = (partnerPairs[pairB] ?? 0) + 1;
      }

      // In 4-player Americano (3 rounds), there are 3 distinct pairings: (p1,p2)&(p3,p4), (p1,p3)&(p2,p4), (p1,p4)&(p2,p3)
      // Every pairing must occur exactly once across the 3 rounds
      expect(partnerPairs.length, 6, reason: '3 rounds x 2 teams = 6 unique team appearances');
      for (var entry in partnerPairs.entries) {
        expect(entry.value, 1,
            reason: 'Partner pair ${entry.key} occurred ${entry.value} times (Must be exactly 1)');
      }
    });

    // -------------------------------------------------------------------------
    // TEST 2: Fair Sit-out Bench Queue (5 Players, 1 Court, 5 Rounds)
    // -------------------------------------------------------------------------
    test('2. Americano 5-player Double 2v2: Fair Bench Sit-Out Queue', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'Player 1'),
        const GamePlayerItem(id: 'p2', name: 'Player 2'),
        const GamePlayerItem(id: 'p3', name: 'Player 3'),
        const GamePlayerItem(id: 'p4', name: 'Player 4'),
        const GamePlayerItem(id: 'p5', name: 'Player 5'),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Double',
        roundCount: 5,
      );

      expect(rounds.length, 5);

      final Map<String, int> restCountMap = {for (var p in players) p.id: 0};
      String? lastRestedId;

      for (var r in rounds) {
        expect(r.matches.length, 1);
        expect(r.restingPlayers.length, 1, reason: '5 players on 1 court has 1 bench player per round');

        final restedPlayer = r.restingPlayers.first;
        restCountMap[restedPlayer.id] = (restCountMap[restedPlayer.id] ?? 0) + 1;

        // No player should sit out 2 rounds in a row
        expect(restedPlayer.id != lastRestedId, true,
            reason: 'Player ${restedPlayer.name} sat out two consecutive rounds');
        lastRestedId = restedPlayer.id;
      }

      // Across 5 rounds with 5 players, every player must rest exactly 1 time
      for (var entry in restCountMap.entries) {
        expect(entry.value, 1,
            reason: 'Player ${entry.key} rested ${entry.value} times (Must be exactly 1)');
      }
    });

    // -------------------------------------------------------------------------
    // TEST 3: Multi-Court 8 Players Americano Double (2 Courts, 5 Rounds)
    // -------------------------------------------------------------------------
    test('3. Americano 8-player Double 2v2: 2 Courts Active', () {
      final players = List.generate(
        8,
        (i) => GamePlayerItem(id: 'p${i + 1}', name: 'Player ${i + 1}'),
      );

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 2,
        gameType: 'Americano',
        playMode: 'Double',
        roundCount: 5,
      );

      expect(rounds.length, 5);

      final Map<String, int> partnerPairs = {};

      for (var r in rounds) {
        expect(r.matches.length, 2, reason: '2 courts active with 8 players');
        expect(r.restingPlayers.length, 0);

        for (var match in r.matches) {
          final pairA = pairKey(match.teamA[0].id, match.teamA[1].id);
          final pairB = pairKey(match.teamB[0].id, match.teamB[1].id);

          partnerPairs[pairA] = (partnerPairs[pairA] ?? 0) + 1;
          partnerPairs[pairB] = (partnerPairs[pairB] ?? 0) + 1;
        }
      }

      // No partner should be repeated more than once
      for (var entry in partnerPairs.entries) {
        expect(entry.value, 1,
            reason: 'Partner pair ${entry.key} repeated (${entry.value} times)');
      }
    });

    // -------------------------------------------------------------------------
    // TEST 4: Team Americano Fixed Teams (4 Players / 2 Fixed Teams, 1 Round)
    // -------------------------------------------------------------------------
    test('4. Team Americano 4-player: 2 Fixed Teams Round-Robin', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'Player 1'),
        const GamePlayerItem(id: 'p2', name: 'Player 2'),
        const GamePlayerItem(id: 'p3', name: 'Player 3'),
        const GamePlayerItem(id: 'p4', name: 'Player 4'),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Team Americano',
        playMode: 'Double',
        roundCount: 1,
      );

      expect(rounds.length, 1);
      final match = rounds.first.matches.first;

      // Fixed pairs: [p1, p2] vs [p3, p4]
      expect(match.teamA.map((p) => p.id).toList(), ['p1', 'p2']);
      expect(match.teamB.map((p) => p.id).toList(), ['p3', 'p4']);
    });

    // -------------------------------------------------------------------------
    // TEST 5: Team Americano 6 Players / 3 Fixed Teams with BYE Rotation (3 Rounds)
    // -------------------------------------------------------------------------
    test('5. Team Americano 6-player (3 Fixed Teams): Berger Table with BYE Bench', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'P1'),
        const GamePlayerItem(id: 'p2', name: 'P2'), // Team 1: [P1, P2]
        const GamePlayerItem(id: 'p3', name: 'P3'),
        const GamePlayerItem(id: 'p4', name: 'P4'), // Team 2: [P3, P4]
        const GamePlayerItem(id: 'p5', name: 'P5'),
        const GamePlayerItem(id: 'p6', name: 'P6'), // Team 3: [P5, P6]
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Team Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      expect(rounds.length, 3);

      final Map<String, int> teamRestCounts = {'T1': 0, 'T2': 0, 'T3': 0};

      for (var r in rounds) {
        expect(r.matches.length, 1, reason: '1 court active match');
        expect(r.restingPlayers.length, 2, reason: '1 team (2 players) on bench each round');

        final restingIds = r.restingPlayers.map((p) => p.id).toSet();
        if (restingIds.contains('p1') && restingIds.contains('p2')) {
          teamRestCounts['T1'] = teamRestCounts['T1']! + 1;
        } else if (restingIds.contains('p3') && restingIds.contains('p4')) {
          teamRestCounts['T2'] = teamRestCounts['T2']! + 1;
        } else if (restingIds.contains('p5') && restingIds.contains('p6')) {
          teamRestCounts['T3'] = teamRestCounts['T3']! + 1;
        }
      }

      // Across 3 rounds, each of the 3 fixed teams rests exactly once
      expect(teamRestCounts['T1'], 1, reason: 'Team 1 rested exactly once');
      expect(teamRestCounts['T2'], 1, reason: 'Team 2 rested exactly once');
      expect(teamRestCounts['T3'], 1, reason: 'Team 3 rested exactly once');
    });

    // -------------------------------------------------------------------------
    // TEST 6: Single Americano 1v1 (4 Players, 2 Courts, 3 Rounds)
    // -------------------------------------------------------------------------
    test('6. Single Americano 1v1 4-player: Round-Robin Matches', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'Player 1'),
        const GamePlayerItem(id: 'p2', name: 'Player 2'),
        const GamePlayerItem(id: 'p3', name: 'Player 3'),
        const GamePlayerItem(id: 'p4', name: 'Player 4'),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 2,
        gameType: 'Americano',
        playMode: 'Single',
        roundCount: 3,
      );

      expect(rounds.length, 3);

      for (var r in rounds) {
        expect(r.matches.length, 2, reason: '2 courts active with 1v1 single mode');
        for (var m in r.matches) {
          expect(m.teamA.length, 1);
          expect(m.teamB.length, 1);
        }
      }
    });
  });
}
