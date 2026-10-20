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

    // -------------------------------------------------------------------------
    // TEST 7: First to 8 Poin (Tuntas) Single 1v1 (2 Players, 1 Court, 1 Round)
    // -------------------------------------------------------------------------
    test('7. First to 8 Poin (Tuntas) Single 1v1: Exactly 1 Match / 1 Round', () {
      final config = GameWizardConfig(
        sport: 'Padel',
        gameType: 'Americano',
        playMode: 'Single',
        scoringSystem: 'First to 8 Poin (Tuntas)',
        courtCount: 1,
        players: [
          const GamePlayerItem(id: 'p1', name: 'Ibnu Hilmi A'),
          const GamePlayerItem(id: 'p2', name: 'Guy Herrera'),
        ],
      );

      expect(config.totalRounds, 1, reason: 'First to X must generate exactly 1 round');
      expect(config.maxTargetPoints, 8);

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: config.players,
        courtCount: config.courtCount,
        gameType: config.gameType,
        playMode: config.playMode,
        roundCount: config.totalRounds,
      );

      expect(rounds.length, 1, reason: 'First to X has 1 round only');
      expect(rounds.first.matches.length, 1);
      expect(rounds.first.matches.first.teamA.first.name, 'Ibnu Hilmi A');
      expect(rounds.first.matches.first.teamB.first.name, 'Guy Herrera');
    });

    // -------------------------------------------------------------------------
    // TEST 8: Total of 5 Poin (4 Players, 1 Court, 5 Rounds)
    // -------------------------------------------------------------------------
    test('8. Total of 5 Poin: Generates exactly 5 Rounds', () {
      final config = GameWizardConfig(
        sport: 'Padel',
        gameType: 'Americano',
        playMode: 'Double',
        scoringSystem: 'Total of 5 Poin',
        courtCount: 1,
        players: [
          const GamePlayerItem(id: 'p1', name: 'Player 1'),
          const GamePlayerItem(id: 'p2', name: 'Player 2'),
          const GamePlayerItem(id: 'p3', name: 'Player 3'),
          const GamePlayerItem(id: 'p4', name: 'Player 4'),
        ],
      );

      expect(config.totalRounds, 5, reason: 'Total of 5 must produce 5 rounds');
      expect(config.maxTargetPoints, 5);

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: config.players,
        courtCount: config.courtCount,
        gameType: config.gameType,
        playMode: config.playMode,
        roundCount: config.totalRounds,
      );

      expect(rounds.length, 5, reason: 'Must have 5 rounds generated');
    });

    // -------------------------------------------------------------------------
    // TEST 9: Team Americano 8 Players, 2 Courts, 3 Rounds: A/B Side Balance
    // -------------------------------------------------------------------------
    test('9. Team Americano 8-player, 2 courts, 3 rounds: Balanced A/B Side Placement', () {
      final players = List.generate(
        8,
        (i) => GamePlayerItem(id: 'p${i + 1}', name: 'Player ${i + 1}'),
      );

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 2,
        gameType: 'Team Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      expect(rounds.length, 3, reason: 'Must generate exactly 3 rounds');

      final expectedTeams = [
        {'p1', 'p2'},
        {'p3', 'p4'},
        {'p5', 'p6'},
        {'p7', 'p8'},
      ];

      String getTeamIdentifier(List<GamePlayerItem> team) {
        final ids = team.map((p) => p.id).toSet();
        for (int i = 0; i < expectedTeams.length; i++) {
          if (expectedTeams[i].containsAll(ids) && ids.containsAll(expectedTeams[i])) {
            return 'T${i + 1}';
          }
        }
        return 'UNKNOWN';
      }

      final Map<String, int> countA = {'T1': 0, 'T2': 0, 'T3': 0, 'T4': 0};
      final Map<String, int> countB = {'T1': 0, 'T2': 0, 'T3': 0, 'T4': 0};
      final Set<String> matchups = {};

      for (var r in rounds) {
        expect(r.matches.length, 2, reason: 'Each round must have 2 matches');
        expect(r.restingPlayers.length, 0, reason: 'No resting players with 8 players on 2 courts');

        final playingInRound = <String>{};

        for (var m in r.matches) {
          final tA = getTeamIdentifier(m.teamA);
          final tB = getTeamIdentifier(m.teamB);

          expect(tA, isNot('UNKNOWN'), reason: 'Team A must preserve fixed pair');
          expect(tB, isNot('UNKNOWN'), reason: 'Team B must preserve fixed pair');
          expect(tA, isNot(tB), reason: 'A team cannot play against itself');

          countA[tA] = countA[tA]! + 1;
          countB[tB] = countB[tB]! + 1;

          final matchPair = tA.compareTo(tB) < 0 ? '$tA vs $tB' : '$tB vs $tA';
          matchups.add(matchPair);

          for (var p in [...m.teamA, ...m.teamB]) {
            expect(playingInRound.contains(p.id), isFalse,
                reason: 'Player ${p.id} must not appear in multiple matches in round ${r.roundNumber}');
            playingInRound.add(p.id);
          }
        }
      }

      // 4 teams = 6 unique pairings, all must meet once in 3 rounds
      expect(matchups.length, 6, reason: 'All 6 team pairings must occur exactly once');

      // Every team must play both side A and B, with max difference <= 1
      for (final teamName in ['T1', 'T2', 'T3', 'T4']) {
        final a = countA[teamName]!;
        final b = countB[teamName]!;
        expect(a, greaterThan(0), reason: '$teamName must be on side A at least once');
        expect(b, greaterThan(0), reason: '$teamName must be on side B at least once');
        expect((a - b).abs(), lessThanOrEqualTo(1),
            reason: '$teamName side difference |$a - $b| must be at most 1');
      }
    });

    // -------------------------------------------------------------------------
    // TEST 10: Team Americano 4 Players, 1 Court, Multi-Round: Even A/B Alternation
    // -------------------------------------------------------------------------
    test('10. Team Americano 4-player, 1 court, 4 rounds: Balanced A/B Alternation', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'P1'),
        const GamePlayerItem(id: 'p2', name: 'P2'),
        const GamePlayerItem(id: 'p3', name: 'P3'),
        const GamePlayerItem(id: 'p4', name: 'P4'),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Team Americano',
        playMode: 'Double',
        roundCount: 4,
      );

      expect(rounds.length, 4);

      int t1CountA = 0;
      int t1CountB = 0;
      int t2CountA = 0;
      int t2CountB = 0;

      for (var r in rounds) {
        expect(r.matches.length, 1);
        final m = r.matches.first;

        final isT1OnA = m.teamA.any((p) => p.id == 'p1');
        if (isT1OnA) {
          t1CountA++;
          t2CountB++;
        } else {
          t1CountB++;
          t2CountA++;
        }
      }

      expect(t1CountA, 2, reason: 'Team 1 should be on Team A exactly 2 times across 4 rounds');
      expect(t1CountB, 2, reason: 'Team 1 should be on Team B exactly 2 times across 4 rounds');
      expect(t2CountA, 2, reason: 'Team 2 should be on Team A exactly 2 times across 4 rounds');
      expect(t2CountB, 2, reason: 'Team 2 should be on Team B exactly 2 times across 4 rounds');
    });

    // -------------------------------------------------------------------------
    // TEST 11: Team Americano Odd Teams: BYE/Resting Not Counted in A/B
    // -------------------------------------------------------------------------
    test('11. Team Americano Odd Teams (3 Teams): BYE/Resting not counted as A/B side', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'P1'),
        const GamePlayerItem(id: 'p2', name: 'P2'), // T1
        const GamePlayerItem(id: 'p3', name: 'P3'),
        const GamePlayerItem(id: 'p4', name: 'P4'), // T2
        const GamePlayerItem(id: 'p5', name: 'P5'),
        const GamePlayerItem(id: 'p6', name: 'P6'), // T3
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Team Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      expect(rounds.length, 3);

      final sideAOccurrences = <String, int>{'p1-p2': 0, 'p3-p4': 0, 'p5-p6': 0};
      final sideBOccurrences = <String, int>{'p1-p2': 0, 'p3-p4': 0, 'p5-p6': 0};

      for (var r in rounds) {
        expect(r.matches.length, 1);
        expect(r.restingPlayers.length, 2, reason: 'Exactly 1 team rests per round');

        final m = r.matches.first;
        final keyA = (m.teamA.map((p) => p.id).toList()..sort()).join('-');
        final keyB = (m.teamB.map((p) => p.id).toList()..sort()).join('-');

        sideAOccurrences[keyA] = (sideAOccurrences[keyA] ?? 0) + 1;
        sideBOccurrences[keyB] = (sideBOccurrences[keyB] ?? 0) + 1;
      }

      // Each team played 2 matches: exactly 1 as Team A and 1 as Team B
      for (final teamKey in ['p1-p2', 'p3-p4', 'p5-p6']) {
        expect(sideAOccurrences[teamKey], 1, reason: '$teamKey should have exactly 1 match on side A');
        expect(sideBOccurrences[teamKey], 1, reason: '$teamKey should have exactly 1 match on side B');
      }
    });

    // -------------------------------------------------------------------------
    // TEST 12: Team Americano Limited Courts: No Double-Booking & Stable Resting
    // -------------------------------------------------------------------------
    test('12. Team Americano Limited Courts (8 Players, 1 Court, 3 Rounds)', () {
      final players = List.generate(
        8,
        (i) => GamePlayerItem(id: 'p${i + 1}', name: 'Player ${i + 1}'),
      );

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Team Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      expect(rounds.length, 3);

      for (var r in rounds) {
        expect(r.matches.length, 1, reason: 'Only 1 court available');
        expect(r.restingPlayers.length, 4, reason: 'Remaining 4 players must rest');

        final matchPlayers = {
          ...r.matches.first.teamA.map((p) => p.id),
          ...r.matches.first.teamB.map((p) => p.id),
        };

        final restingPlayers = r.restingPlayers.map((p) => p.id).toSet();

        // No overlap between playing and resting
        expect(matchPlayers.intersection(restingPlayers).isEmpty, isTrue,
            reason: 'Players playing cannot also be resting');
        expect(matchPlayers.length + restingPlayers.length, 8,
            reason: 'Total players in round must equal 8');
      }
    });
  });
}
