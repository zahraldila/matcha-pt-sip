import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/match/domain/services/scoring_engine.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';

void main() {
  group('1. ScoringEngine Restore & Legacy Tests', () {
    test('Restore ADV-40 properly reconstructs Deuce and Advantage for Team A', () {
      final state = ScoringEngine.restoreMatchState(
        gamesA: 3,
        gamesB: 2,
        rawPointA: 'ADV',
        rawPointB: '40',
        matchStatus: 'In Progress',
        version: 5,
        lastEventId: 'evt-adv-a',
      );

      expect(state.gamesA, equals(3));
      expect(state.gamesB, equals(2));
      expect(state.pointDisplayA, equals('ADV'));
      expect(state.pointDisplayB, equals('40'));
      expect(state.idxA, equals(3));
      expect(state.idxB, equals(3));
      expect(state.isDeuce, isTrue);
      expect(state.advantage, equals('A'));
      expect(state.version, equals(5));
      expect(state.lastEventId, equals('evt-adv-a'));
      expect(state.isCompleted, isFalse);
    });

    test('Restore 40-ADV reconstructs Advantage for Team B', () {
      final state = ScoringEngine.restoreMatchState(
        gamesA: 1,
        gamesB: 1,
        rawPointA: '40',
        rawPointB: 'ADV',
        version: 3,
      );

      expect(state.pointDisplayA, equals('40'));
      expect(state.pointDisplayB, equals('ADV'));
      expect(state.isDeuce, isTrue);
      expect(state.advantage, equals('B'));
    });

    test('Restore Deuce (40 - 40)', () {
      final state = ScoringEngine.restoreMatchState(
        gamesA: 2,
        gamesB: 2,
        rawPointA: '40',
        rawPointB: '40',
      );

      expect(state.pointDisplayA, equals('40'));
      expect(state.pointDisplayB, equals('40'));
      expect(state.isDeuce, isTrue);
      expect(state.advantage, isNull);
    });

    test('Handle legacy/invalid raw values gracefully without crashing', () {
      final parsed = ScoringEngine.parseTennisPoints('invalid_val', null);
      expect(parsed.idxA, equals(0));
      expect(parsed.idxB, equals(0));
      expect(parsed.pointDisplayA, equals('0'));
      expect(parsed.pointDisplayB, equals('0'));
      expect(parsed.isDeuce, isFalse);
      expect(parsed.advantage, isNull);
    });
  });

  group('2. Presentation & Round Access Control Tests', () {
    testWidgets('Active round is active, tapping historical round locks inputs with warning banner', (tester) async {
      tester.view.physicalSize = const Size(1000, 2500);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matchRound1 = DrawingMatch(
        matchId: 101,
        courtNumber: 1,
        teamA: const [GamePlayerItem(id: '1', name: 'Player A1')],
        teamB: const [GamePlayerItem(id: '2', name: 'Player B1')],
        scoreA: 6,
        scoreB: 4,
        status: 'Completed',
        winnerTeam: 'Team A',
      );

      final matchRound2 = DrawingMatch(
        matchId: 102,
        courtNumber: 1,
        teamA: const [GamePlayerItem(id: '1', name: 'Player A1')],
        teamB: const [GamePlayerItem(id: '3', name: 'Player B2')],
        scoreA: 2,
        scoreB: 1,
        pointDisplayA: '15',
        pointDisplayB: '30',
        status: 'In Progress',
      );

      final rounds = [
        DrawingRound(roundNumber: 1, matches: [matchRound1]),
        DrawingRound(roundNumber: 2, matches: [matchRound2]),
      ];

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            rounds: rounds,
            isHost: true,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Initial active round should be Round 2 because Round 1 is Completed
      expect(find.text('AKTIF'), findsOneWidget);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
      expect(find.text('+ Tambah Poin Team B'), findsOneWidget);

      // Tap Round 1 tab to view history
      await tester.tap(find.text('Ronde 1'));
      await tester.pumpAndSettle();

      // Should show Mode Tinjau Riwayat banner
      expect(find.textContaining('Mode Tinjau Riwayat Ronde 1'), findsOneWidget);
      expect(find.textContaining('Tombol skor dikunci'), findsOneWidget);

      // Buttons on historical round should be locked
      expect(find.text('+ Tambah Poin Team A'), findsNothing);
      expect(find.text('🔒 Skor Terkunci (Selesai)'), findsNWidgets(2));

      // Switch back to Round 2
      await tester.tap(find.text('Ronde 2'));
      await tester.pumpAndSettle();

      // Mode Tinjau Riwayat banner disappears, active buttons reappear
      expect(find.textContaining('Mode Tinjau Riwayat'), findsNothing);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
    });

    testWidgets('Active round completion gives Host choices to advance or finish session', (tester) async {
      tester.view.physicalSize = const Size(1000, 2500);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matchRound1 = DrawingMatch(
        matchId: 201,
        courtNumber: 1,
        teamA: const [GamePlayerItem(id: '1', name: 'Player A1')],
        teamB: const [GamePlayerItem(id: '2', name: 'Player B1')],
        scoreA: 5,
        scoreB: 3,
        pointDisplayA: '40',
        idxA: 3,
        pointDisplayB: '0',
        idxB: 0,
        status: 'In Progress',
      );

      final matchRound2 = DrawingMatch(
        matchId: 202,
        courtNumber: 1,
        teamA: const [GamePlayerItem(id: '1', name: 'Player A1')],
        teamB: const [GamePlayerItem(id: '3', name: 'Player B2')],
        scoreA: 0,
        scoreB: 0,
        status: 'In Progress',
      );

      final rounds = [
        DrawingRound(roundNumber: 1, matches: [matchRound1]),
        DrawingRound(roundNumber: 2, matches: [matchRound2]),
      ];

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            rounds: rounds,
            isHost: true,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Win game 6 for Team A in Round 1 (target games = 6)
      await tester.tap(find.text('+ Tambah Poin Team A'));
      await tester.pumpAndSettle();

      // Round 1 is now complete!
      expect(find.textContaining('Ronde 1 Selesai'), findsOneWidget);
      // Host choice buttons should be present
      expect(find.text('Lanjut ke Ronde 2'), findsOneWidget);
      expect(find.text('Selesaikan Sesi'), findsOneWidget);

      // Tap 'Lanjut ke Ronde 2'
      await tester.tap(find.text('Lanjut ke Ronde 2'));
      await tester.pumpAndSettle();

      // Now Round 2 is viewed and active
      expect(find.text('Mode Tinjau Riwayat'), findsNothing);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
    });
  });

  group('3. Conflict Resolution UI Tests', () {
    testWidgets('Displays conflict resolution banner on scoreboard card', (tester) async {
      final match = DrawingMatch(
        matchId: 301,
        courtNumber: 1,
        teamA: const [GamePlayerItem(id: '1', name: 'A')],
        teamB: const [GamePlayerItem(id: '2', name: 'B')],
        scoreA: 1,
        scoreB: 1,
        pointDisplayA: '15',
        pointDisplayB: '15',
        version: 2,
      );

      final rounds = [
        DrawingRound(roundNumber: 1, matches: [match]),
      ];

      final key = GlobalKey<MatchScoringPageState>();

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            key: key,
            rounds: rounds,
            isHost: true,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Simulate a conflict detected in state
      key.currentState?.simulateConflictForTesting(
        matchId: 301,
        serverSnapshot: {
          'scoreA': 3,
          'scoreB': 1,
          'pointScoreA': '40',
          'pointScoreB': '15',
          'version': 4,
          'status': 'In Progress',
        },
      );
      await tester.pumpAndSettle();

      // Conflict banner should appear on UI
      expect(find.text('Konflik Skor Terdeteksi!'), findsOneWidget);
      expect(find.text('Terima Server'), findsOneWidget);
      expect(find.text('Muat Ulang Data'), findsOneWidget);

      // Tap 'Terima Server'
      await tester.tap(find.text('Terima Server'));
      await tester.pumpAndSettle();

      // Conflict should be resolved, score updated to server version
      expect(find.text('Konflik Skor Terdeteksi!'), findsNothing);
      expect(find.text('40'), findsOneWidget);
      expect(find.text('Games Won: 3 Game'), findsOneWidget);
    });
  });
}
