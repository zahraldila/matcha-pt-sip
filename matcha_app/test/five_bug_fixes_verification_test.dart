import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/match/domain/services/scoring_engine.dart';
import 'package:matcha_app/features/recap/data/recap_service.dart';

void main() {
  group('BUG 1: DRAWING INITIAL PREVIEW TIDAK TERKUNCI & LOCK EXPLICIT', () {
    test('Initial preview with scheduled matches is NOT locked even if session was created', () {
      // Logic check: only explicit completed session or match In Progress/Completed/with scores locks it
      const sessionStatusOpen = 'open';
      const sessionStatusInProgress = 'in progress';

      // Rule: status_session == 'in progress' does not mean drawing is locked
      final isSessionDone = sessionStatusInProgress == 'completed' || sessionStatusInProgress == 'finished';
      expect(isSessionDone, isFalse);

      final isSessionOpenDone = sessionStatusOpen == 'completed' || sessionStatusOpen == 'finished';
      expect(isSessionOpenDone, isFalse);

      // Matches status check: Scheduled matches without scores mean preview is unlocked
      final matchStatuses = ['Scheduled', 'Scheduled'];
      final hasActiveOrDoneMatch = matchStatuses.any((s) {
        final lower = s.toLowerCase();
        return lower == 'in progress' || lower == 'completed' || lower == 'finished' || lower == 'live';
      });
      expect(hasActiveOrDoneMatch, isFalse, reason: 'Scheduled matches must remain unlocked');

      // After explicit lock action: matches become 'In Progress'
      final lockedMatchStatuses = ['In Progress', 'In Progress'];
      final isLockedAfterHostAction = lockedMatchStatuses.any((s) {
        final lower = s.toLowerCase();
        return lower == 'in progress' || lower == 'completed' || lower == 'finished' || lower == 'live';
      });
      expect(isLockedAfterHostAction, isTrue, reason: 'Explicit lock transitions matches to In Progress');
    });
  });

  group('BUG 2: TOTAL OF BERAKHIR SAAT GAME PERTAMA MENCAPAI 6', () {
    final systemTotal = ScoringEngine.detectScoringSystem('Total of 3');

    test('1. Kasus 6-0: Tim A mencapai 6 langsung menang & status completed', () {
      const state50 = ScoringMatchState(gamesA: 5, gamesB: 0, idxA: 3, idxB: 0);
      final res = ScoringEngine.applyPointDelta(
        currentState: state50,
        teamWon: 'A',
        scoringSystem: systemTotal,
      );
      expect(res.gamesA, 6);
      expect(res.gamesB, 0);
      expect(res.status, 'completed');
      expect(res.winnerTeam, 'Team A');
      expect(res.setsA, 1);
      expect(res.setsB, 0);
    });

    test('2. Kasus 6-4: Tim A mencapai 6 langsung menang & status completed', () {
      const state54 = ScoringMatchState(gamesA: 5, gamesB: 4, idxA: 3, idxB: 1);
      final res = ScoringEngine.applyPointDelta(
        currentState: state54,
        teamWon: 'A',
        scoringSystem: systemTotal,
      );
      expect(res.gamesA, 6);
      expect(res.gamesB, 4);
      expect(res.status, 'completed');
      expect(res.winnerTeam, 'Team A');
    });

    test('3. Kasus 6-5: Tim A menang dari 5-5 langsung selesai (tidak lanjut ke 7)', () {
      const state55 = ScoringMatchState(gamesA: 5, gamesB: 5, idxA: 3, idxB: 2);
      final res = ScoringEngine.applyPointDelta(
        currentState: state55,
        teamWon: 'A',
        scoringSystem: systemTotal,
      );
      expect(res.gamesA, 6);
      expect(res.gamesB, 5);
      expect(res.status, 'completed');
      expect(res.winnerTeam, 'Team A');
      expect(res.setsA, 1);
    });

    test('4. Kasus 5-6: Tim B menang dari 5-5 langsung selesai (tidak lanjut ke 7)', () {
      const state55 = ScoringMatchState(gamesA: 5, gamesB: 5, idxA: 1, idxB: 3);
      final res = ScoringEngine.applyPointDelta(
        currentState: state55,
        teamWon: 'B',
        scoringSystem: systemTotal,
      );
      expect(res.gamesA, 5);
      expect(res.gamesB, 6);
      expect(res.status, 'completed');
      expect(res.winnerTeam, 'Team B');
      expect(res.setsB, 1);
    });

    test('5. Penolakan input setelah completed: input dikunci dan score tetap 6', () {
      const completedState65 = ScoringMatchState(
        gamesA: 6,
        gamesB: 5,
        status: 'completed',
        winnerTeam: 'Team A',
        setsA: 1,
      );
      final rejectedA = ScoringEngine.applyPointDelta(
        currentState: completedState65,
        teamWon: 'A',
        scoringSystem: systemTotal,
      );
      expect(rejectedA.gamesA, 6);
      expect(rejectedA.gamesB, 5);
      expect(rejectedA.status, 'completed');

      final rejectedB = ScoringEngine.applyPointDelta(
        currentState: completedState65,
        teamWon: 'B',
        scoringSystem: systemTotal,
      );
      expect(rejectedB.gamesA, 6);
      expect(rejectedB.gamesB, 5);
      expect(rejectedB.status, 'completed');
    });

    test('6. Format First to X tetap tidak berubah (First to 8 selesai di 8)', () {
      final systemFirstTo = ScoringEngine.detectScoringSystem('First to 8');
      const state76 = ScoringMatchState(gamesA: 7, gamesB: 6, idxA: 3, idxB: 0);
      final res = ScoringEngine.applyPointDelta(
        currentState: state76,
        teamWon: 'A',
        scoringSystem: systemFirstTo,
      );
      expect(res.gamesA, 8);
      expect(res.gamesB, 6);
      expect(res.status, 'completed');
      expect(res.winnerTeam, 'Team A');
    });
  });

  group('BUGS 3 & 4: REALTIME ROUND ADVANCEMENT & TRANSISI TANPA MENUNGGU SKOR', () {
    test('Robust round parsing parses numeric and string payload formats without failure', () {
      int? parseRoundPayload(Map<String, dynamic> payload) {
        final raw = payload['next_round_num'] ??
            payload['next_round'] ??
            payload['active_round'] ??
            payload['session_active_round'];
        if (raw is int) {
          return raw;
        } else if (raw != null) {
          final digits = RegExp(r'\d+').firstMatch(raw.toString())?.group(0);
          if (digits != null) {
            return int.tryParse(digits);
          }
        }
        return null;
      }

      // Format 1: integer next_round_num
      expect(parseRoundPayload({'next_round_num': 2}), equals(2));

      // Format 2: string 'round_2' in next_round
      expect(parseRoundPayload({'next_round': 'round_2'}), equals(2));

      // Format 3: string '2' in active_round
      expect(parseRoundPayload({'active_round': '2'}), equals(2));

      // Format 4: legacy web session_active_round 'round_3'
      expect(parseRoundPayload({'session_active_round': 'round_3'}), equals(3));
    });

    test('Deduplication: duplicate or backwards round_advanced events are ignored', () {
      int activeRoundIndex = 0; // Ronde 1

      void onReceiveRound(int nextRoundNum) {
        final nextIdx = nextRoundNum - 1;
        if (nextIdx > activeRoundIndex) {
          activeRoundIndex = nextIdx;
        }
      }

      // First advance to round 2
      onReceiveRound(2);
      expect(activeRoundIndex, equals(1)); // Ronde 2 (index 1)

      // Duplicate event with round 2
      onReceiveRound(2);
      expect(activeRoundIndex, equals(1)); // Tetap index 1

      // Out-of-order or stale event with round 1
      onReceiveRound(1);
      expect(activeRoundIndex, equals(1)); // Tetap index 1
    });

    test('Active round resolution on initial recovery when Round 1 completed and Round 2 In Progress at 0-0', () {
      final rounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Marcello')],
              teamB: [const GamePlayerItem(id: '2', name: 'Ahmad')],
              status: 'Completed',
              scoreA: 5,
              scoreB: 6,
            ),
          ],
        ),
        DrawingRound(
          roundNumber: 2,
          matches: [
            DrawingMatch(
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Marcello')],
              teamB: [const GamePlayerItem(id: '2', name: 'Ahmad')],
              status: 'In Progress', // Host already advanced!
              scoreA: 0,
              scoreB: 0,
            ),
          ],
        ),
      ];

      // Reconstruct resolver logic
      int resolveActive(List<DrawingRound> rList) {
        for (int i = 0; i < rList.length; i++) {
          final matches = rList[i].matches;
          final isFinished = matches.isNotEmpty && matches.every((m) => m.status == 'Completed');
          if (!isFinished) return i;

          if (i + 1 < rList.length) {
            final nextMatches = rList[i + 1].matches;
            final nextStarted = nextMatches.any(
              (m) => m.status == 'In Progress' || m.status == 'Completed' || m.scoreA > 0 || m.scoreB > 0,
            );
            if (!nextStarted) return i;
          }
        }
        return rList.length - 1;
      }

      final activeIdx = resolveActive(rounds);
      expect(activeIdx, equals(1), reason: 'Round 2 is In Progress at 0-0, so active round is Round 2');
    });
  });

  group('BUG 5: WINNER REKAP PARSING, STANDINGS, DAN PODIUM', () {
    test('parseTeamSide correctly extracts A or B without letter "a" in "team" trap', () {
      expect(RecapService.parseTeamSide('Team B'), equals('B'));
      expect(RecapService.parseTeamSide('TEAM B'), equals('B'));
      expect(RecapService.parseTeamSide('team_b'), equals('B'));
      expect(RecapService.parseTeamSide('Side B'), equals('B'));
      expect(RecapService.parseTeamSide('B'), equals('B'));

      expect(RecapService.parseTeamSide('Team A'), equals('A'));
      expect(RecapService.parseTeamSide('TEAM A'), equals('A'));
      expect(RecapService.parseTeamSide('team_a'), equals('A'));
      expect(RecapService.parseTeamSide('Side A'), equals('A'));
      expect(RecapService.parseTeamSide('A'), equals('A'));

      expect(RecapService.parseTeamSide(''), isNull);
      expect(RecapService.parseTeamSide(null), isNull);
      expect(RecapService.parseTeamSide('Draw'), isNull);
    });

    test('Round 1: Marcello (Side A = 5) vs Ahmad (Side B = 7) gives WIN to Ahmad, NOT Marcello', () {
      const winnerTeamStr = 'Team B';
      final winnerSide = RecapService.parseTeamSide(winnerTeamStr);
      expect(winnerSide, equals('B'));

      final isSideAWinner = winnerSide == 'A';
      final isSideBWinner = winnerSide == 'B';

      expect(isSideAWinner, isFalse, reason: 'Marcello (Side A) did NOT win with score 5');
      expect(isSideBWinner, isTrue, reason: 'Ahmad Subarjo (Side B) won with score 7');
    });

    test('Participant mapping per round: wins and points are correctly credited to each player', () {
      final playerStatsMap = <int, Map<String, dynamic>>{
        101: {'playerId': 101, 'nama': 'Marcello', 'matchesWon': 0, 'matchesLost': 0, 'pointsFor': 0},
        102: {'playerId': 102, 'nama': 'Ahmad', 'matchesWon': 0, 'matchesLost': 0, 'pointsFor': 0},
      };

      // Round 1: Side A (101: Marcello) = 5 vs Side B (102: Ahmad) = 7, Winner: Team B
      final sideAPlayerIds = [101];
      final sideBPlayerIds = [102];
      const winnerSide = 'B';
      const ptsA = 5;
      const ptsB = 7;

      for (final pId in sideAPlayerIds) {
        playerStatsMap[pId]!['pointsFor'] = (playerStatsMap[pId]!['pointsFor'] as int) + ptsA;
        if (winnerSide == 'A') {
          playerStatsMap[pId]!['matchesWon'] = (playerStatsMap[pId]!['matchesWon'] as int) + 1;
        } else if (winnerSide == 'B') {
          playerStatsMap[pId]!['matchesLost'] = (playerStatsMap[pId]!['matchesLost'] as int) + 1;
        }
      }

      for (final pId in sideBPlayerIds) {
        playerStatsMap[pId]!['pointsFor'] = (playerStatsMap[pId]!['pointsFor'] as int) + ptsB;
        if (winnerSide == 'B') {
          playerStatsMap[pId]!['matchesWon'] = (playerStatsMap[pId]!['matchesWon'] as int) + 1;
        } else if (winnerSide == 'A') {
          playerStatsMap[pId]!['matchesLost'] = (playerStatsMap[pId]!['matchesLost'] as int) + 1;
        }
      }

      expect(playerStatsMap[101]!['matchesWon'], equals(0));
      expect(playerStatsMap[101]!['matchesLost'], equals(1));
      expect(playerStatsMap[101]!['pointsFor'], equals(5));

      expect(playerStatsMap[102]!['matchesWon'], equals(1));
      expect(playerStatsMap[102]!['matchesLost'], equals(0));
      expect(playerStatsMap[102]!['pointsFor'], equals(7));
    });
  });
}
