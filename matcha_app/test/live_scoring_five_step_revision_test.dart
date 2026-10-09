import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:matcha_app/features/auth/data/datasource/auth_remote_data_source.dart';
import 'package:matcha_app/features/auth/domain/models/user_model.dart';
import 'package:matcha_app/features/auth/presentation/controllers/auth_controller.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/drawing/presentation/drawing_result_page.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/match/data/match_service.dart';
import 'package:matcha_app/features/match/domain/services/scoring_engine.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';
import 'package:matcha_app/features/recap/presentation/session_match_recap_page.dart';

class _FakeAuthDataSource extends Fake implements AuthRemoteDataSource {}

class _MockAuthController extends AuthController {
  UserModel? _mockUser;
  _MockAuthController({UserModel? initialUser})
      : super(authDataSource: _FakeAuthDataSource()) {
    _mockUser = initialUser;
  }

  @override
  UserModel? get currentUser => _mockUser;

  @override
  bool get isLoading => false;
}

class _MockMatchServiceForFiveSteps extends MatchService {
  Map<String, dynamic>? sessionData;
  List<DrawingRound> savedDrawing = [];
  List<Map<String, dynamic>> matchesData = [];
  bool isDrawingLockedResult = false;
  bool lockCalled = false;
  bool saveDrawingCalled = false;
  bool broadcastDrawingUpdateCalled = false;
  bool broadcastRoundAdvancedCalled = false;
  bool finishSessionCalled = false;

  void Function()? onDrawingChangedCallback;
  void Function()? onDrawingLockedCallback;

  void Function()? onDataChangedCallback;
  void Function(Map<String, dynamic> payload)? onRoundAdvancedCallback;
  void Function(Map<String, dynamic> payload)? onSessionFinishedCallback;

  _MockMatchServiceForFiveSteps({
    this.sessionData,
    List<DrawingRound>? drawing,
    List<Map<String, dynamic>>? matches,
    this.isDrawingLockedResult = false,
  }) {
    if (drawing != null) savedDrawing = drawing;
    if (matches != null) matchesData = matches;
  }

  @override
  Future<Map<String, dynamic>?> getSession(dynamic sessionId) async {
    return sessionData;
  }

  @override
  Future<bool> isSessionDrawingLocked(dynamic sessionId) async {
    return isDrawingLockedResult;
  }

  @override
  Future<List<DrawingRound>?> loadSavedDrawing({
    required dynamic sessionId,
    List<GamePlayerItem>? registeredPlayers,
  }) async {
    return savedDrawing;
  }

  @override
  Future<List<DrawingRound>> saveDrawingMatches({
    required dynamic sessionId,
    required List<DrawingRound> rounds,
    List<GamePlayerItem>? allPlayers,
    int? courtCount,
    String? matchStatus,
    int? matchFormatId,
  }) async {
    saveDrawingCalled = true;
    savedDrawing = rounds;
    return rounds;
  }

  @override
  Future<void> broadcastDrawingUpdate(dynamic sessionId) async {
    broadcastDrawingUpdateCalled = true;
  }

  @override
  Future<List<DrawingRound>> lockDrawingSession(
    dynamic sessionId, {
    required List<DrawingRound> rounds,
    List<GamePlayerItem>? allPlayers,
    int? matchFormatId,
  }) async {
    lockCalled = true;
    isDrawingLockedResult = true;
    savedDrawing = rounds;
    return rounds;
  }

  @override
  RealtimeChannel? subscribeDrawingSession({
    required dynamic sessionId,
    required void Function() onDrawingChanged,
    void Function()? onDrawingLocked,
  }) {
    onDrawingChangedCallback = onDrawingChanged;
    onDrawingLockedCallback = onDrawingLocked;
    return null;
  }

  @override
  RealtimeChannel subscribeLiveSession({
    dynamic sessionId,
    required void Function() onDataChanged,
    void Function(Map<String, dynamic> payload)? onRoundAdvanced,
    void Function(Map<String, dynamic> payload)? onSessionFinished,
  }) {
    onDataChangedCallback = onDataChanged;
    onRoundAdvancedCallback = onRoundAdvanced;
    onSessionFinishedCallback = onSessionFinished;
    return FakeRealtimeChannel();
  }

  @override
  Future<List<Map<String, dynamic>>> getMatchesForSession(dynamic sessionId) async {
    return matchesData;
  }

  @override
  Future<void> broadcastRoundAdvanced({
    required dynamic sessionId,
    required int currentRoundNum,
    required int nextRoundNum,
  }) async {
    broadcastRoundAdvancedCalled = true;
  }

  @override
  Future<void> finishSession(dynamic sessionId) async {
    finishSessionCalled = true;
  }

  @override
  Future<void> unsubscribe(RealtimeChannel? channel) async {}
}

class FakeRealtimeChannel extends Fake implements RealtimeChannel {}

void main() {
  const hostUser = UserModel(
    userId: 1,
    nama: 'Host Bambang',
    email: 'bambang@matcha.com',
    isHost: true,
  );

  const playerUser = UserModel(
    userId: 2,
    nama: 'Player Anton',
    email: 'anton@matcha.com',
    isHost: false,
  );

  final sampleRounds = [
    DrawingRound(
      roundNumber: 1,
      matches: [
        DrawingMatch(
          matchId: 101,
          courtNumber: 1,
          teamA: [const GamePlayerItem(id: '1', name: 'Bambang')],
          teamB: [const GamePlayerItem(id: '2', name: 'Anton')],
          scoreA: 0,
          scoreB: 0,
          status: 'Scheduled',
        ),
        DrawingMatch(
          matchId: 102,
          courtNumber: 2,
          teamA: [const GamePlayerItem(id: '3', name: 'Citra')],
          teamB: [const GamePlayerItem(id: '4', name: 'Dodi')],
          scoreA: 0,
          scoreB: 0,
          status: 'Scheduled',
        ),
      ],
    ),
    DrawingRound(
      roundNumber: 2,
      matches: [
        DrawingMatch(
          matchId: 201,
          courtNumber: 1,
          teamA: [const GamePlayerItem(id: '1', name: 'Bambang')],
          teamB: [const GamePlayerItem(id: '3', name: 'Citra')],
          scoreA: 0,
          scoreB: 0,
          status: 'Scheduled',
        ),
        DrawingMatch(
          matchId: 202,
          courtNumber: 2,
          teamA: [const GamePlayerItem(id: '2', name: 'Anton')],
          teamB: [const GamePlayerItem(id: '4', name: 'Dodi')],
          scoreA: 0,
          scoreB: 0,
          status: 'Scheduled',
        ),
      ],
    ),
  ];

  final sampleConfig = GameWizardConfig(
    sessionId: 999,
    hostUserId: 1,
    players: [
      const GamePlayerItem(id: '1', name: 'Bambang'),
      const GamePlayerItem(id: '2', name: 'Anton'),
      const GamePlayerItem(id: '3', name: 'Citra'),
      const GamePlayerItem(id: '4', name: 'Dodi'),
    ],
    courtCount: 2,
    playMode: 'Single',
    gameType: 'Americano',
  );

  group('REVISI 1: DRAWING PREVIEW REALTIME', () {
    testWidgets('Host and Player see identical preview; only Host has Acak Ulang / Kunci Tim', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final service = _MockMatchServiceForFiveSteps(
        sessionData: {'session_id': 999, 'host_user_id': 1},
        drawing: sampleRounds,
        isDrawingLockedResult: false,
      );

      // 1. Host loads drawing
      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 999,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          config: sampleConfig,
        ),
      ));
      await tester.pumpAndSettle();

      expect(find.text('Acak Ulang'), findsOneWidget);
      expect(find.text('Kunci Tim & Mulai Scoring Live'), findsOneWidget);
      expect(find.text('Bambang'), findsWidgets);
      expect(find.text('Anton'), findsWidgets);

      // 2. Player loads drawing
      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 999,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          config: sampleConfig,
        ),
      ));
      await tester.pumpAndSettle();

      // Player must see players, but NOT see Acak Ulang or Kunci Tim
      expect(find.text('Bambang'), findsWidgets);
      expect(find.text('Anton'), findsWidgets);
      expect(find.text('Acak Ulang'), findsNothing);
      expect(find.text('Kunci Tim & Mulai Scoring Live'), findsNothing);
      expect(find.text('Penonton'), findsOneWidget);
    });

    testWidgets('Realtime Acak Ulang updates Player preview without page reload', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final service = _MockMatchServiceForFiveSteps(
        sessionData: {'session_id': 999, 'host_user_id': 1},
        drawing: sampleRounds,
        isDrawingLockedResult: false,
      );

      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 999,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          config: sampleConfig,
        ),
      ));
      await tester.pumpAndSettle();

      // Update mock drawing to simulate reshuffled teams
      final reshuffledRounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              matchId: 101,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Bambang')],
              teamB: [const GamePlayerItem(id: '3', name: 'Citra Reshuffled')],
            ),
          ],
        ),
      ];
      service.savedDrawing = reshuffledRounds;

      // Trigger realtime broadcast
      service.onDrawingChangedCallback?.call();
      await tester.pumpAndSettle();

      expect(find.text('Citra Reshuffled'), findsOneWidget);
    });
  });

  group('REVISI 2: AKSES SCORING SETELAH DRAWING DIKUNCI', () {
    testWidgets('Player has no scoring access before lock, gets "Lihat Live Scoring" only after lock', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final service = _MockMatchServiceForFiveSteps(
        sessionData: {'session_id': 999, 'host_user_id': 1},
        drawing: sampleRounds,
        isDrawingLockedResult: false,
      );

      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 999,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          config: sampleConfig,
        ),
      ));
      await tester.pumpAndSettle();

      // Before lock: waiting card, NO scoring button
      expect(find.text('Menunggu Host Mengunci Drawing & Memulai Scoring'), findsOneWidget);
      expect(find.text('Lihat Live Scoring'), findsNothing);

      // Lock occurs in server and broadcast fires
      service.isDrawingLockedResult = true;
      service.onDrawingLockedCallback?.call();
      await tester.pumpAndSettle();

      // Now spectator sees "Lihat Live Scoring"
      expect(find.text('Lihat Live Scoring'), findsOneWidget);
    });
  });

  group('REVISI 3: TRANSISI RONDE UNTUK TOTAL OF', () {
    testWidgets('Active round completion shows "Ronde X Selesai", only Host has "Lanjut ke Ronde X+1"', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final roundsWithCompletedRound1 = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              matchId: 101,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Bambang')],
              teamB: [const GamePlayerItem(id: '2', name: 'Anton')],
              scoreA: 6,
              scoreB: 2,
              status: 'Completed',
            ),
            DrawingMatch(
              matchId: 102,
              courtNumber: 2,
              teamA: [const GamePlayerItem(id: '3', name: 'Citra')],
              teamB: [const GamePlayerItem(id: '4', name: 'Dodi')],
              scoreA: 6,
              scoreB: 4,
              status: 'Completed',
            ),
          ],
        ),
        DrawingRound(
          roundNumber: 2,
          matches: [
            DrawingMatch(
              matchId: 201,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Bambang')],
              teamB: [const GamePlayerItem(id: '3', name: 'Citra')],
              scoreA: 0,
              scoreB: 0,
              status: 'Scheduled',
            ),
            DrawingMatch(
              matchId: 202,
              courtNumber: 2,
              teamA: [const GamePlayerItem(id: '2', name: 'Anton')],
              teamB: [const GamePlayerItem(id: '4', name: 'Dodi')],
              scoreA: 0,
              scoreB: 0,
              status: 'Scheduled',
            ),
          ],
        ),
      ];

      final service = _MockMatchServiceForFiveSteps(
        sessionData: {'session_id': 999, 'host_user_id': 1},
        drawing: roundsWithCompletedRound1,
      );

      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 999,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          rounds: roundsWithCompletedRound1,
        ),
      ));
      await tester.pumpAndSettle();

      // Host must see "Ronde 1 Selesai" and button "Lanjut ke Ronde 2"
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
      expect(find.text('Lanjut ke Ronde 2'), findsOneWidget);

      // Now test as Player:
      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 999,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          rounds: roundsWithCompletedRound1,
        ),
      ));
      await tester.pumpAndSettle();

      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
      expect(find.text('Lanjut ke Ronde 2'), findsNothing);
      expect(find.textContaining('Menunggu Host untuk melanjutkan ke ronde berikutnya'), findsOneWidget);

      // Realtime round advanced triggers transition to Round 2 for player
      service.onRoundAdvancedCallback?.call({
        'current_round_num': 1,
        'next_round_num': 2,
      });
      await tester.pumpAndSettle();

      // Now Round 2 is active and banner for Round 1 is gone
      expect(find.text('Ronde 1 Selesai'), findsNothing);
    });

    testWidgets('Do NOT mark round completed when only one court is completed', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final oneCourtFinishedRounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              matchId: 101,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Bambang')],
              teamB: [const GamePlayerItem(id: '2', name: 'Anton')],
              scoreA: 6,
              scoreB: 2,
              status: 'Completed',
            ),
            DrawingMatch(
              matchId: 102,
              courtNumber: 2,
              teamA: [const GamePlayerItem(id: '3', name: 'Citra')],
              teamB: [const GamePlayerItem(id: '4', name: 'Dodi')],
              scoreA: 3,
              scoreB: 2,
              status: 'In Progress',
            ),
          ],
        ),
      ];

      final service = _MockMatchServiceForFiveSteps(
        sessionData: {'session_id': 999, 'host_user_id': 1},
        drawing: oneCourtFinishedRounds,
      );

      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 999,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          rounds: oneCourtFinishedRounds,
        ),
      ));
      await tester.pumpAndSettle();

      // Banner "Ronde 1 Selesai" must NOT appear because court 2 is still In Progress
      expect(find.text('Ronde 1 Selesai'), findsNothing);
      expect(find.text('Lanjut ke Ronde 2'), findsNothing);
    });
  });

  group('REVISI 4: TOTAL OF BERAKHIR SAAT GAME PERTAMA MENCAPAI 6', () {
    test('6-0, 6-4, 6-5, 5-6 completes immediately and locks input (no continuation to 7)', () {
      final config = ScoringEngine.detectScoringSystem('Total of 3');

      // 1. Kasus 6-0: Tim A mencapai 6 dari 5-0
      const state50 = ScoringMatchState(gamesA: 5, gamesB: 0, idxA: 3, idxB: 0);
      final state60 = ScoringEngine.applyPointDelta(
        currentState: state50,
        teamWon: 'A',
        scoringSystem: config,
      );
      expect(state60.gamesA, equals(6));
      expect(state60.gamesB, equals(0));
      expect(state60.isCompleted, isTrue);
      expect(state60.winnerTeam, equals('Team A'));

      // 2. Kasus 6-4: Tim A mencapai 6 dari 5-4
      const state54 = ScoringMatchState(gamesA: 5, gamesB: 4, idxA: 3, idxB: 1);
      final state64 = ScoringEngine.applyPointDelta(
        currentState: state54,
        teamWon: 'A',
        scoringSystem: config,
      );
      expect(state64.gamesA, equals(6));
      expect(state64.gamesB, equals(4));
      expect(state64.isCompleted, isTrue);
      expect(state64.winnerTeam, equals('Team A'));

      // 3. Kasus 6-5: Tim A menang dari 5-5
      const state55A = ScoringMatchState(gamesA: 5, gamesB: 5, idxA: 3, idxB: 2);
      final state65 = ScoringEngine.applyPointDelta(
        currentState: state55A,
        teamWon: 'A',
        scoringSystem: config,
      );
      expect(state65.gamesA, equals(6));
      expect(state65.gamesB, equals(5));
      expect(state65.isCompleted, isTrue);
      expect(state65.winnerTeam, equals('Team A'));

      // 4. Kasus 5-6: Tim B menang dari 5-5
      const state55B = ScoringMatchState(gamesA: 5, gamesB: 5, idxA: 1, idxB: 3);
      final state56 = ScoringEngine.applyPointDelta(
        currentState: state55B,
        teamWon: 'B',
        scoringSystem: config,
      );
      expect(state56.gamesA, equals(5));
      expect(state56.gamesB, equals(6));
      expect(state56.isCompleted, isTrue);
      expect(state56.winnerTeam, equals('Team B'));

      // 5. Penolakan input setelah completed (tidak boleh berlanjut ke 7)
      final rejectedA = ScoringEngine.applyPointDelta(
        currentState: state65,
        teamWon: 'A',
        scoringSystem: config,
      );
      expect(rejectedA.gamesA, equals(6));
      expect(rejectedA.gamesB, equals(5));
      expect(rejectedA.isCompleted, isTrue);

      final rejectedB = ScoringEngine.applyPointDelta(
        currentState: state56,
        teamWon: 'B',
        scoringSystem: config,
      );
      expect(rejectedB.gamesA, equals(5));
      expect(rejectedB.gamesB, equals(6));
      expect(rejectedB.isCompleted, isTrue);
    });
  });

  group('REVISI 5: LEADERBOARD, REKAP, DAN PODIUM', () {
    testWidgets('AppBar contains Leaderboard button, and Player gets "Lihat Rekap & Podium" after session finishes', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final finishedRounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              matchId: 101,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Bambang')],
              teamB: [const GamePlayerItem(id: '2', name: 'Anton')],
              scoreA: 6,
              scoreB: 2,
              status: 'Completed',
            ),
          ],
        ),
      ];

      final service = _MockMatchServiceForFiveSteps(
        sessionData: {
          'session_id': 999,
          'host_user_id': 1,
          'status_session': 'Finished',
        },
        drawing: finishedRounds,
      );

      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 999,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          rounds: finishedRounds,
        ),
      ));
      await tester.pumpAndSettle();

      // 1. Leaderboard icon button exists in AppBar
      expect(find.byIcon(Icons.leaderboard_rounded), findsOneWidget);

      // 2. Player sees "Lihat Rekap & Podium" button
      expect(find.text('Lihat Rekap & Podium'), findsOneWidget);
    });
  });
}
