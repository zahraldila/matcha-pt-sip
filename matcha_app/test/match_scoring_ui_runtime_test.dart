import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';

void main() {
  group('MatchScoringPage UI Runtime Tests', () {
    testWidgets('1. Displays CURRENT POINT (TENNIS) and initial 0-0 points', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: MatchScoringPage(isHost: true),
        ),
      );
      await tester.pumpAndSettle();

      // Verify header and tennis point labels
      expect(find.text('Live Match Scoring'), findsOneWidget);
      expect(find.text('CURRENT POINT (TENNIS)'), findsNWidgets(2)); // Team A and Team B
      expect(find.text('0'), findsNWidgets(2)); // Initial points 0 and 0
      expect(find.text('Games Won: 0 Game'), findsNWidgets(2)); // Initial games won
      expect(find.text('Round score'), findsOneWidget);
      expect(find.text('0 – 0'), findsOneWidget);
      expect(find.text('Skor game'), findsOneWidget);
    });

    testWidgets('2. Host wins one game: Point 0-0, Games Won 1-0', (tester) async {
      tester.view.physicalSize = const Size(800, 1200);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      final match = DrawingMatch(
        courtNumber: 1,
        teamA: const [GamePlayerItem(id: '1', name: 'Aku')],
        teamB: const [GamePlayerItem(id: '2', name: 'Kamu')],
        pointDisplayA: '40',
        idxA: 3,
        pointDisplayB: '15',
        idxB: 1,
        scoreA: 0,
        scoreB: 0,
      );

      final round = DrawingRound(
        roundNumber: 1,
        matches: [match],
      );

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            rounds: [round],
            isHost: true,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('40'), findsOneWidget);
      expect(find.text('15'), findsOneWidget);

      // Tap + Tambah Poin Team A
      final btnTeamA = find.text('+ Tambah Poin Team A');
      expect(btnTeamA, findsOneWidget);
      await tester.tap(btnTeamA);
      await tester.pumpAndSettle();

      // Team A wins game: points reset to 0-0, Games Won advances to 1
      expect(find.text('0'), findsNWidgets(2)); // Points reset to 0 - 0
      expect(find.text('Games Won: 1 Game'), findsOneWidget);
      expect(find.text('Games Won: 0 Game'), findsOneWidget);
      expect(find.text('1 – 0'), findsOneWidget);
    });

    testWidgets('3. Deuce and Advantage banners displayed correctly on UI', (tester) async {
      tester.view.physicalSize = const Size(800, 1200);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      final deuceMatch = DrawingMatch(
        courtNumber: 1,
        teamA: const [GamePlayerItem(id: '1', name: 'Aku')],
        teamB: const [GamePlayerItem(id: '2', name: 'Kamu')],
        pointDisplayA: '40',
        idxA: 3,
        pointDisplayB: '40',
        idxB: 3,
        isDeuce: true,
      );

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            rounds: [DrawingRound(roundNumber: 1, matches: [deuceMatch])],
            isHost: true,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('⚡ DEUCE (40 - 40) ⚡'), findsOneWidget);

      // Tap + Tambah Poin Team A -> Advantage Team A
      final btnTeamA = find.text('+ Tambah Poin Team A');
      await tester.tap(btnTeamA);
      await tester.pumpAndSettle();

      expect(find.text('⚡ ADVANTAGE TEAM A ⚡'), findsOneWidget);
      expect(find.text('ADV'), findsOneWidget);
    });

    testWidgets('4. Spectator mode (isHost: false) disables scoring and shows Read-Only', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: MatchScoringPage(isHost: false),
        ),
      );
      await tester.pumpAndSettle();

      // Verify Read-Only indicators
      expect(find.text('Mode Penonton (Read-Only)'), findsNWidgets(2));
      expect(find.text('+ Tambah Poin Team A'), findsNothing);
      expect(find.text('+ Tambah Poin Team B'), findsNothing);
      expect(find.text('Deklarasi Walkover (WO)'), findsNothing);
    });

    testWidgets('5. Court Selector tabs appear when multiple courts exist', (tester) async {
      final match1 = DrawingMatch(
        courtNumber: 1,
        teamA: const [GamePlayerItem(id: '1', name: 'Player 1')],
        teamB: const [GamePlayerItem(id: '2', name: 'Player 2')],
        scoreA: 2,
        scoreB: 1,
      );
      final match2 = DrawingMatch(
        courtNumber: 2,
        teamA: const [GamePlayerItem(id: '3', name: 'Player 3')],
        teamB: const [GamePlayerItem(id: '4', name: 'Player 4')],
        scoreA: 3,
        scoreB: 0,
      );

      final round = DrawingRound(roundNumber: 1, matches: [match1, match2]);

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            rounds: [round],
            isHost: true,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.textContaining('Pilih Lapangan (2 Court):'), findsOneWidget);
      expect(find.text('Court 1 (2 - 1)'), findsOneWidget);
      expect(find.text('Court 2 (3 - 0)'), findsOneWidget);
    });
  });
}
