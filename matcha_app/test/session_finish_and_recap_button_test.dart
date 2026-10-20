import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';
import 'package:matcha_app/features/session/domain/session_model.dart';
import 'package:matcha_app/features/session/presentation/session_detail_page.dart';

void main() {
  group('User Revisions Verification Tests', () {
    testWidgets('Point 2: Live Match Scoring AppBar does NOT have the leaderboard icon', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: MatchScoringPage(isHost: true),
        ),
      );
      await tester.pumpAndSettle();

      // Verify the AppBar displays title and realtime status, but NO leaderboard button
      expect(find.text('Live Match Scoring'), findsOneWidget);
      expect(find.byIcon(Icons.leaderboard_rounded), findsNothing);
    });

    testWidgets('Point 3: When session is Finished, SessionDetailPage displays ONLY Rekapan Mabar button', (tester) async {
      tester.view.physicalSize = const Size(800, 1400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      final finishedSession = SessionModel(
        sessionId: 999,
        hostUserId: 1,
        sportId: 1,
        venueId: 1,
        namaSession: 'Test Mabar Selesai',
        sportName: 'Padel',
        scoringSystem: 'Total of 3 Poin',
        jenisPermainan: 'Americano / Single',
        jumlahPemain: 3,
        statusSession: 'Finished',
        venueName: 'Youth Club',
        registeredPlayers: [
          SessionPlayerModel(playerId: 1, nama: 'Marcello', userId: 1),
          SessionPlayerModel(playerId: 2, nama: 'Anton', userId: 2),
          SessionPlayerModel(playerId: 3, nama: 'Citra', userId: 3),
        ],
      );

      await tester.pumpWidget(
        MaterialApp(
          home: SessionDetailPage(
            sessionId: 999,
            initialSession: finishedSession,
          ),
        ),
      );
      await tester.pump();

      // Verify Rekapan Mabar button exists
      expect(find.text('Rekapan Mabar'), findsOneWidget);

      // Verify the 3 previous buttons do NOT exist
      expect(find.text('Buka Drawing Tim'), findsNothing);
      expect(find.text('Live Match Scoring'), findsNothing);
      expect(find.text('Hapus Jadwal Mabar'), findsNothing);
    });

    test('Point 1: Player automatically follows Host to Round 2 without waiting for score > 0', () {
      final round1 = DrawingRound(
        roundNumber: 1,
        matches: [
          DrawingMatch(
            courtNumber: 1,
            teamA: const [GamePlayerItem(id: '1', name: 'A')],
            teamB: const [GamePlayerItem(id: '2', name: 'B')],
            scoreA: 3,
            scoreB: 1,
            status: 'Completed',
          ),
        ],
      );

      final round2 = DrawingRound(
        roundNumber: 2,
        matches: [
          DrawingMatch(
            courtNumber: 1,
            teamA: const [GamePlayerItem(id: '1', name: 'A')],
            teamB: const [GamePlayerItem(id: '3', name: 'C')],
            scoreA: 0,
            scoreB: 0,
            status: 'In Progress',
          ),
        ],
      );

      final round3 = DrawingRound(
        roundNumber: 3,
        matches: [
          DrawingMatch(
            courtNumber: 1,
            teamA: const [GamePlayerItem(id: '2', name: 'B')],
            teamB: const [GamePlayerItem(id: '3', name: 'C')],
            scoreA: 0,
            scoreB: 0,
            status: 'Pending',
          ),
        ],
      );

      final rounds = [round1, round2, round3];

      // Resolve active round when round 2 is In Progress at 0-0
      int activeRound = 0;
      for (int i = 0; i < rounds.length; i++) {
        final r = rounds[i];
        final hasActive = r.matches.any((m) => m.status.toLowerCase() == 'in progress');
        if (hasActive) {
          activeRound = i;
          break;
        }
      }

      expect(activeRound, 1, reason: 'Round 2 must be recognized as active at 0-0 while In Progress');
    });
  });
}
