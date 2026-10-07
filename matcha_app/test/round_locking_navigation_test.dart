import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:matcha_app/features/auth/data/datasource/auth_remote_data_source.dart';
import 'package:matcha_app/features/auth/domain/models/user_model.dart';
import 'package:matcha_app/features/auth/presentation/controllers/auth_controller.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/match/data/match_service.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';

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

class _MockMatchService extends MatchService {
  Map<String, dynamic>? sessionData;
  List<DrawingRound> savedDrawing = [];
  List<Map<String, dynamic>> matchesData = [];
  bool broadcastRoundAdvancedCalled = false;
  int? broadcastCurrentRound;
  int? broadcastNextRound;

  void Function()? onDataChangedCallback;
  void Function(Map<String, dynamic> payload)? onRoundAdvancedCallback;

  _MockMatchService({
    this.sessionData,
    List<DrawingRound>? drawing,
    List<Map<String, dynamic>>? matches,
  }) {
    if (drawing != null) savedDrawing = drawing;
    if (matches != null) matchesData = matches;
  }

  @override
  Future<Map<String, dynamic>?> getSession(dynamic sessionId) async {
    return sessionData;
  }

  @override
  Future<List<DrawingRound>?> loadSavedDrawing({
    required dynamic sessionId,
    List<GamePlayerItem>? registeredPlayers,
  }) async {
    return savedDrawing;
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
    return FakeRealtimeChannel();
  }

  @override
  Future<List<Map<String, dynamic>>> getMatchesForSession(dynamic sessionId) async {
    return matchesData;
  }

  @override
  Future<void> advanceRoundMatches({
    required dynamic sessionId,
    required int roundNumber,
    List<int>? matchIds,
  }) async {}

  @override
  Future<void> broadcastRoundAdvanced({
    required dynamic sessionId,
    required int currentRoundNum,
    required int nextRoundNum,
  }) async {
    broadcastRoundAdvancedCalled = true;
    broadcastCurrentRound = currentRoundNum;
    broadcastNextRound = nextRoundNum;
  }

  @override
  Future<void> unsubscribe(RealtimeChannel? channel) async {}
}

class FakeRealtimeChannel extends Fake implements RealtimeChannel {}

void main() {
  const hostUser = UserModel(
    userId: 1,
    nama: 'Host Bambang',
    email: 'host@matcha.com',
  );

  const playerUser = UserModel(
    userId: 2,
    nama: 'Player Anton',
    email: 'player@matcha.com',
  );

  List<DrawingRound> createThreeRoundSetup({
    String round1Status = 'In Progress',
    int round1ScoreA = 0,
    int round1ScoreB = 0,
    String round2Status = 'Scheduled',
    int round2ScoreA = 0,
    int round2ScoreB = 0,
  }) {
    return [
      DrawingRound(
        roundNumber: 1,
        matches: [
          DrawingMatch(
            matchId: 101,
            courtNumber: 1,
            teamA: [const GamePlayerItem(id: '1', name: 'Bambang')],
            teamB: [const GamePlayerItem(id: '2', name: 'Anton')],
            scoreA: round1ScoreA,
            scoreB: round1ScoreB,
            status: round1Status,
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
            scoreA: round2ScoreA,
            scoreB: round2ScoreB,
            status: round2Status,
          ),
        ],
      ),
      DrawingRound(
        roundNumber: 3,
        matches: [
          DrawingMatch(
            matchId: 301,
            courtNumber: 1,
            teamA: [const GamePlayerItem(id: '2', name: 'Anton')],
            teamB: [const GamePlayerItem(id: '3', name: 'Citra')],
            scoreA: 0,
            scoreB: 0,
            status: 'Scheduled',
          ),
        ],
      ),
    ];
  }

  group('Kunci Akses Ronde: Host dan Player Validasi', () {
    testWidgets('1. Ronde 1 aktif -> ronde 2 dan 3 tidak bisa dibuka (disabled dengan ikon kunci)', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final rounds = createThreeRoundSetup();
      final service = _MockMatchService(
        sessionData: {'session_id': 100, 'host_user_id': 1},
        drawing: rounds,
      );

      // --- Sisi Host ---
      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          rounds: rounds,
        ),
      ));
      await tester.pumpAndSettle();

      // Tab Ronde 1 aktif
      expect(find.text('AKTIF'), findsOneWidget);
      // Ikon kunci tampil pada tab ronde 2 dan 3
      expect(find.byIcon(Icons.lock_rounded), findsNWidgets(2));

      // Host mencoba tap Ronde 2 dan Ronde 3 -> tetap di Ronde 1
      await tester.tap(find.text('Ronde 2'));
      await tester.pumpAndSettle();
      expect(find.text('AKTIF'), findsOneWidget); // Masih Ronde 1
      expect(find.textContaining('Mode Tinjau Riwayat'), findsNothing);

      await tester.tap(find.text('Ronde 3'));
      await tester.pumpAndSettle();
      expect(find.text('AKTIF'), findsOneWidget); // Masih Ronde 1

      // --- Sisi Player ---
      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          rounds: rounds,
        ),
      ));
      await tester.pumpAndSettle();

      // Player melihat kunci yang sama
      expect(find.byIcon(Icons.lock_rounded), findsNWidgets(2));

      // Player tap Ronde 2 dan 3 -> tidak berpindah
      await tester.tap(find.text('Ronde 2'));
      await tester.pumpAndSettle();
      expect(find.text('AKTIF'), findsOneWidget);

      await tester.tap(find.text('Ronde 3'));
      await tester.pumpAndSettle();
      expect(find.text('AKTIF'), findsOneWidget);
    });

    testWidgets('2. Ronde 1 selesai, belum dilanjutkan -> ronde 2 tetap terkunci', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final rounds = createThreeRoundSetup(
        round1Status: 'Completed',
        round1ScoreA: 6,
        round1ScoreB: 3,
        round2Status: 'Scheduled',
      );
      final service = _MockMatchService(
        sessionData: {'session_id': 100, 'host_user_id': 1},
        drawing: rounds,
      );

      // --- Host ---
      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          rounds: rounds,
        ),
      ));
      await tester.pumpAndSettle();

      // Host melihat Ronde 1 Selesai dan tombol Lanjut ke Ronde 2
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
      expect(find.text('Lanjut ke Ronde 2'), findsOneWidget);
      // Ronde 2 & 3 tetap terkunci dengan ikon kunci
      expect(find.byIcon(Icons.lock_rounded), findsNWidgets(2));

      // Host mencoba tap tab Ronde 2 -> tidak bisa dibuka (disabled)
      await tester.tap(find.text('Ronde 2'));
      await tester.pumpAndSettle();
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);

      // --- Player ---
      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          rounds: rounds,
        ),
      ));
      await tester.pumpAndSettle();

      // Player melihat Ronde 1 Selesai, menunggu Host, tanpa tombol Lanjut
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
      expect(find.textContaining('Menunggu Host untuk melanjutkan ke ronde berikutnya'), findsOneWidget);
      expect(find.text('Lanjut ke Ronde 2'), findsNothing);
      // Ronde 2 & 3 tetap terkunci
      expect(find.byIcon(Icons.lock_rounded), findsNWidgets(2));

      // Player tap Ronde 2 -> tetap terkunci
      await tester.tap(find.text('Ronde 2'));
      await tester.pumpAndSettle();
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
    });

    testWidgets('3. Host Lanjut Ronde 2 -> kedua client langsung menampilkan ronde 2 pada 0-0', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final rounds = createThreeRoundSetup(
        round1Status: 'Completed',
        round1ScoreA: 6,
        round1ScoreB: 3,
        round2Status: 'Scheduled',
      );
      final service = _MockMatchService(
        sessionData: {'session_id': 100, 'host_user_id': 1},
        drawing: rounds,
      );

      // Host membuka page
      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          rounds: rounds,
        ),
      ));
      await tester.pumpAndSettle();

      expect(find.text('Lanjut ke Ronde 2'), findsOneWidget);

      // Host tap "Lanjut ke Ronde 2"
      await tester.tap(find.text('Lanjut ke Ronde 2'));
      await tester.pumpAndSettle();

      // Host langsung melihat Ronde 2 aktif pada 0-0
      expect(find.text('Ronde 1 Selesai'), findsNothing);
      expect(find.text('Mode Tinjau Riwayat'), findsNothing);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
      expect(service.broadcastRoundAdvancedCalled, isTrue);
      expect(service.broadcastNextRound, equals(2));

      // Hanya Ronde 3 yang terkunci sekarang
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget);

      // Player di sisi lain menerima realtime onRoundAdvanced
      final playerRounds = createThreeRoundSetup(
        round1Status: 'Completed',
        round1ScoreA: 6,
        round1ScoreB: 3,
        round2Status: 'Scheduled',
      );
      final playerService = _MockMatchService(
        sessionData: {'session_id': 100, 'host_user_id': 1},
        drawing: playerRounds,
      );
      final playerAuth = _MockAuthController(initialUser: playerUser);

      // Clear widget tree to simulate distinct player device
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();

      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          key: const ValueKey('player_client'),
          sessionId: 100,
          hostUserId: 1,
          authController: playerAuth,
          matchService: playerService,
          rounds: playerRounds,
        ),
      ));
      await tester.pumpAndSettle();

      // Player initially at completed round 1
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);

      // Broadcast realtime tiba pada player
      playerService.onRoundAdvancedCallback?.call({
        'current_round_num': 1,
        'next_round_num': 2,
      });
      await tester.pumpAndSettle();

      // Player langsung berpindah dan menampilkan ronde 2 pada 0-0 tanpa tombol input
      expect(find.text('Ronde 1 Selesai'), findsNothing);
      expect(find.text('+ Tambah Poin Team A'), findsNothing); // Player tidak punya input
      // Ronde 3 tetap terkunci untuk player
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget);
    });

    testWidgets('4. Ronde 1 dapat ditinjau tanpa akses input; ronde 3 tetap terkunci', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      // Setup di mana Ronde 2 sudah aktif pada 0-0
      final rounds = createThreeRoundSetup(
        round1Status: 'Completed',
        round1ScoreA: 6,
        round1ScoreB: 4,
        round2Status: 'In Progress',
        round2ScoreA: 0,
        round2ScoreB: 0,
      );
      final service = _MockMatchService(
        sessionData: {'session_id': 100, 'host_user_id': 1},
        drawing: rounds,
      );

      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          rounds: rounds,
        ),
      ));
      await tester.pumpAndSettle();

      // Ronde 2 aktif
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
      // Ronde 3 terkunci
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget);

      // Host tap Ronde 1 untuk meninjau riwayat
      await tester.tap(find.text('Ronde 1'));
      await tester.pumpAndSettle();

      // Mode Tinjau Riwayat aktif
      expect(find.textContaining('Mode Tinjau Riwayat Ronde 1'), findsOneWidget);
      expect(find.textContaining('Tombol skor dikunci'), findsOneWidget);
      // Tombol input skor terkunci/tidak tampil
      expect(find.text('+ Tambah Poin Team A'), findsNothing);

      // Coba tap Ronde 3 yang terkunci -> tidak berpindah ke Ronde 3
      await tester.tap(find.text('Ronde 3'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Mode Tinjau Riwayat Ronde 1'), findsOneWidget);

      // Kembali ke Ronde 2 -> tombol input skor aktif kembali
      await tester.tap(find.text('Ronde 2'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Mode Tinjau Riwayat'), findsNothing);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
    });

    testWidgets('5. Refresh memulihkan penguncian yang sama dari backend', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      // Skenario A: Ronde 1 completed, Ronde 2 Scheduled (belum dilanjutkan)
      final matchesDataUnadvanced = [
        {
          'matchId': 101,
          'roundNumber': 1,
          'courtNumber': 1,
          'scoreA': 6,
          'scoreB': 2,
          'status': 'Finished',
          'winnerTeam': 'Team A',
          'teamAPlayers': [{'playerId': 1, 'name': 'Bambang'}],
          'teamBPlayers': [{'playerId': 2, 'name': 'Anton'}],
        },
        {
          'matchId': 201,
          'roundNumber': 2,
          'courtNumber': 1,
          'scoreA': 0,
          'scoreB': 0,
          'status': 'Scheduled',
          'teamAPlayers': [{'playerId': 1, 'name': 'Bambang'}],
          'teamBPlayers': [{'playerId': 3, 'name': 'Citra'}],
        },
        {
          'matchId': 301,
          'roundNumber': 3,
          'courtNumber': 1,
          'scoreA': 0,
          'scoreB': 0,
          'status': 'Scheduled',
          'teamAPlayers': [{'playerId': 2, 'name': 'Anton'}],
          'teamBPlayers': [{'playerId': 3, 'name': 'Citra'}],
        },
      ];

      final service = _MockMatchService(
        sessionData: {'session_id': 100, 'host_user_id': 1},
        matches: matchesDataUnadvanced,
      );

      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
        ),
      ));
      await tester.pumpAndSettle();

      // Memastikan bahwa setelah load backend (refresh), Ronde 1 adalah active session round,
      // Ronde 2 & 3 tetap terkunci dengan ikon gembok!
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
      expect(find.text('Lanjut ke Ronde 2'), findsOneWidget);
      expect(find.byIcon(Icons.lock_rounded), findsNWidgets(2));

      // Skenario B: Host sudah advance ronde 2 -> status_match di backend menjadi 'In Progress' pada 0-0
      final matchesDataAdvanced = [
        {
          'matchId': 101,
          'roundNumber': 1,
          'courtNumber': 1,
          'scoreA': 6,
          'scoreB': 2,
          'status': 'Finished',
          'winnerTeam': 'Team A',
          'teamAPlayers': [{'playerId': 1, 'name': 'Bambang'}],
          'teamBPlayers': [{'playerId': 2, 'name': 'Anton'}],
        },
        {
          'matchId': 201,
          'roundNumber': 2,
          'courtNumber': 1,
          'scoreA': 0,
          'scoreB': 0,
          'status': 'In Progress',
          'teamAPlayers': [{'playerId': 1, 'name': 'Bambang'}],
          'teamBPlayers': [{'playerId': 3, 'name': 'Citra'}],
        },
        {
          'matchId': 301,
          'roundNumber': 3,
          'courtNumber': 1,
          'scoreA': 0,
          'scoreB': 0,
          'status': 'Scheduled',
          'teamAPlayers': [{'playerId': 2, 'name': 'Anton'}],
          'teamBPlayers': [{'playerId': 3, 'name': 'Citra'}],
        },
      ];

      final service2 = _MockMatchService(
        sessionData: {'session_id': 100, 'host_user_id': 1},
        matches: matchesDataAdvanced,
      );

      // Clear widget tree to simulate re-opening / refreshing the app
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();

      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          key: const ValueKey('refresh_scenario_b'),
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service2,
        ),
      ));
      await tester.pumpAndSettle();

      // Ronde 2 langsung aktif pada 0-0, Ronde 1 terbuka sebagai riwayat, Ronde 3 tetap terkunci
      expect(find.text('Ronde 1 Selesai'), findsNothing);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget);
    });
  });
}
