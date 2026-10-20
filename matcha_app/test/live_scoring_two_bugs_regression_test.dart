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
  int advanceRoundMatchesCalledCount = 0;

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
    int? courtCount,
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
  Future<List<Map<String, dynamic>>> getMatchesForSession(
    dynamic sessionId, {
    int? courtCount,
  }) async {
    return matchesData;
  }

  @override
  Future<void> advanceRoundMatches({
    required dynamic sessionId,
    required int roundNumber,
    List<int>? matchIds,
  }) async {
    advanceRoundMatchesCalledCount++;
  }

  @override
  Future<void> broadcastRoundAdvanced({
    required dynamic sessionId,
    required int currentRoundNum,
    required int nextRoundNum,
    RealtimeChannel? activeChannel,
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
  TestWidgetsFlutterBinding.ensureInitialized();

  final hostUser = const UserModel(
    userId: 1,
    nama: 'Host Marcello',
    email: 'marcello@matcha.id',
    isHost: true,
  );

  final playerUser = const UserModel(
    userId: 2,
    nama: 'Player Anton',
    email: 'anton@matcha.id',
    isHost: false,
  );

  /// Helper untuk membuat data matches 2-court, 3-ronde (total 6 matches).
  /// Database court_id menggunakan ID database non-sekuensial (e.g. 10 dan 20)
  /// untuk memverifikasi courtNum tidak tertukar dengan court_id database.
  List<Map<String, dynamic>> createTwoCourtThreeRoundMatchesData({
    String round1Status = 'Scheduled',
    int r1m1ScoreA = 0,
    int r1m1ScoreB = 0,
    int r1m2ScoreA = 0,
    int r1m2ScoreB = 0,
    String round2Status = 'Scheduled',
    int r2m1ScoreA = 0,
    int r2m1ScoreB = 0,
    int r2m2ScoreA = 0,
    int r2m2ScoreB = 0,
    String round3Status = 'Scheduled',
  }) {
    return [
      // Ronde 1, Court 1 (nomorMatch = 1)
      {
        'matchId': 101,
        'nomorMatch': 1,
        'roundNumber': 1,
        'courtNumber': 1,
        'scoreA': r1m1ScoreA,
        'scoreB': r1m1ScoreB,
        'status': round1Status,
        'courtId': 10,
        'rawMatch': <String, dynamic>{'court_id': 10, 'nomor_match': 1, 'status_match': round1Status},
        'teamAPlayers': [{'playerId': 1, 'name': 'Player 1'}],
        'teamBPlayers': [{'playerId': 2, 'name': 'Player 2'}],
      },
      // Ronde 1, Court 2 (nomorMatch = 2)
      {
        'matchId': 102,
        'nomorMatch': 2,
        'roundNumber': 1,
        'courtNumber': 2,
        'scoreA': r1m2ScoreA,
        'scoreB': r1m2ScoreB,
        'status': round1Status,
        'courtId': 20,
        'rawMatch': <String, dynamic>{'court_id': 20, 'nomor_match': 2, 'status_match': round1Status},
        'teamAPlayers': [{'playerId': 3, 'name': 'Player 3'}],
        'teamBPlayers': [{'playerId': 4, 'name': 'Player 4'}],
      },
      // Ronde 2, Court 1 (nomorMatch = 3)
      {
        'matchId': 201,
        'nomorMatch': 3,
        'roundNumber': 2,
        'courtNumber': 1,
        'scoreA': r2m1ScoreA,
        'scoreB': r2m1ScoreB,
        'status': round2Status,
        'courtId': 10,
        'rawMatch': <String, dynamic>{'court_id': 10, 'nomor_match': 3, 'status_match': round2Status},
        'teamAPlayers': [{'playerId': 1, 'name': 'Player 1'}],
        'teamBPlayers': [{'playerId': 3, 'name': 'Player 3'}],
      },
      // Ronde 2, Court 2 (nomorMatch = 4)
      {
        'matchId': 202,
        'nomorMatch': 4,
        'roundNumber': 2,
        'courtNumber': 2,
        'scoreA': r2m2ScoreA,
        'scoreB': r2m2ScoreB,
        'status': round2Status,
        'courtId': 20,
        'rawMatch': <String, dynamic>{'court_id': 20, 'nomor_match': 4, 'status_match': round2Status},
        'teamAPlayers': [{'playerId': 2, 'name': 'Player 2'}],
        'teamBPlayers': [{'playerId': 4, 'name': 'Player 4'}],
      },
      // Ronde 3, Court 1 (nomorMatch = 5)
      {
        'matchId': 301,
        'nomorMatch': 5,
        'roundNumber': 3,
        'courtNumber': 1,
        'scoreA': 0,
        'scoreB': 0,
        'status': round3Status,
        'courtId': 10,
        'rawMatch': <String, dynamic>{'court_id': 10, 'nomor_match': 5, 'status_match': round3Status},
        'teamAPlayers': [{'playerId': 1, 'name': 'Player 1'}],
        'teamBPlayers': [{'playerId': 4, 'name': 'Player 4'}],
      },
      // Ronde 3, Court 2 (nomorMatch = 6)
      {
        'matchId': 302,
        'nomorMatch': 6,
        'roundNumber': 3,
        'courtNumber': 2,
        'scoreA': 0,
        'scoreB': 0,
        'status': round3Status,
        'courtId': 20,
        'rawMatch': <String, dynamic>{'court_id': 20, 'nomor_match': 6, 'status_match': round3Status},
        'teamAPlayers': [{'playerId': 2, 'name': 'Player 2'}],
        'teamBPlayers': [{'playerId': 3, 'name': 'Player 3'}],
      },
    ];
  }

  group('BUG 1 REGRESSION: Sesi 2-Court tidak bertambah court di Ronde 3', () {
    testWidgets('Sesi 2 court, 3 ronde: Ronde 1, Ronde 2, dan Ronde 3 konsisten memiliki tepat 2 court', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matches = createTwoCourtThreeRoundMatchesData();
      final service = _MockMatchService(
        sessionData: {
          'session_id': 100,
          'host_user_id': 1,
          'court_count': 2,
          'total_court': 2,
        },
        matches: matches,
      );

      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          config: GameWizardConfig(
            sessionId: 100,
            courtCount: 2,
            players: [
              const GamePlayerItem(id: '1', name: 'Player 1', playerId: 1),
              const GamePlayerItem(id: '2', name: 'Player 2', playerId: 2),
              const GamePlayerItem(id: '3', name: 'Player 3', playerId: 3),
              const GamePlayerItem(id: '4', name: 'Player 4', playerId: 4),
            ],
          )..customTotalRounds = 3,
        ),
      ));
      await tester.pumpAndSettle();

      // Ronde 1: Verifikasi chips court
      expect(find.text('Pilih Lapangan (2 Court):'), findsOneWidget);
      expect(find.textContaining('Court 1 ('), findsOneWidget);
      expect(find.textContaining('Court 2 ('), findsOneWidget);
      expect(find.textContaining('Court 3'), findsNothing);
      expect(find.textContaining('Court 10'), findsNothing); // Tidak menggunakan raw court_id DB

      // Simulasikan host lanjut sampai ke Ronde 2
      // Maju ke Ronde 2
      service.onRoundAdvancedCallback?.call({
        'session_id': 100,
        'current_round_num': 1,
        'next_round_num': 2,
      });
      await tester.pumpAndSettle();

      expect(find.text('Pilih Lapangan (2 Court):'), findsOneWidget);
      expect(find.textContaining('Court 1 ('), findsOneWidget);
      expect(find.textContaining('Court 2 ('), findsOneWidget);
      expect(find.textContaining('Court 3'), findsNothing);

      // Maju ke Ronde 3
      service.onRoundAdvancedCallback?.call({
        'session_id': 100,
        'current_round_num': 2,
        'next_round_num': 3,
      });
      await tester.pumpAndSettle();

      // Ronde 3 HARUS tetap tepat 2 court! Tidak boleh bertambah court
      expect(find.text('Pilih Lapangan (2 Court):'), findsOneWidget);
      expect(find.textContaining('Court 1 ('), findsOneWidget);
      expect(find.textContaining('Court 2 ('), findsOneWidget);
      expect(find.textContaining('Court 3'), findsNothing);
      expect(find.textContaining('Court 4'), findsNothing);
      expect(find.textContaining('Court 5'), findsNothing);
      expect(find.textContaining('Court 6'), findsNothing);
    });
  });

  group('BUG 2 REGRESSION: Ronde selesai tidak berpindah otomatis tanpa klik Host', () {
    testWidgets('Semua match Ronde 1 selesai -> Host tetap di Ronde 1, tombol Lanjut tetap ada meski ada score_update/refetch', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matches = createTwoCourtThreeRoundMatchesData(
        round1Status: 'Completed',
        r1m1ScoreA: 6,
        r1m1ScoreB: 2,
        r1m2ScoreA: 6,
        r1m2ScoreB: 4,
        round2Status: 'Scheduled',
      );
      final service = _MockMatchService(
        sessionData: {
          'session_id': 100,
          'host_user_id': 1,
          'court_count': 2,
        },
        matches: matches,
      );

      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          config: GameWizardConfig(
            sessionId: 100,
            courtCount: 2,
          )..customTotalRounds = 3,
        ),
      ));
      await tester.pumpAndSettle();

      // Host melihat banner Ronde 1 Selesai dan tombol Lanjut ke Ronde 2
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
      expect(find.text('Lanjut ke Ronde 2'), findsOneWidget);
      expect(find.byIcon(Icons.lock_rounded), findsNWidgets(2)); // Ronde 2 & 3 terkunci

      // Trigger realtime score_update / refetch data identik
      service.onDataChangedCallback?.call();
      await tester.pumpAndSettle();

      // TOMBOL DAN STATUS RONDE 1 HARUS TETAP ADA! Tidak boleh hilang atau auto-advance
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
      expect(find.text('Lanjut ke Ronde 2'), findsOneWidget);
      expect(find.byIcon(Icons.lock_rounded), findsNWidgets(2));
    });

    testWidgets('Player tetap di Ronde 1 dan menunggu Host tanpa tombol lanjut', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matches = createTwoCourtThreeRoundMatchesData(
        round1Status: 'Completed',
        r1m1ScoreA: 6,
        r1m1ScoreB: 2,
        r1m2ScoreA: 6,
        r1m2ScoreB: 4,
        round2Status: 'Scheduled',
      );
      final service = _MockMatchService(
        sessionData: {
          'session_id': 100,
          'host_user_id': 1,
          'court_count': 2,
        },
        matches: matches,
      );

      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          config: GameWizardConfig(
            sessionId: 100,
            courtCount: 2,
          )..customTotalRounds = 3,
        ),
      ));
      await tester.pumpAndSettle();

      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
      expect(find.textContaining('Menunggu Host untuk melanjutkan ke ronde berikutnya'), findsOneWidget);
      expect(find.text('Lanjut ke Ronde 2'), findsNothing);
    });

    testWidgets('Host klik lanjut sekali: berpindah ke Ronde 2; duplicate round_advanced diabaikan (tidak lompat ke Ronde 3)', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matches = createTwoCourtThreeRoundMatchesData(
        round1Status: 'Completed',
        r1m1ScoreA: 6,
        r1m1ScoreB: 2,
        r1m2ScoreA: 6,
        r1m2ScoreB: 4,
        round2Status: 'Scheduled',
      );
      final service = _MockMatchService(
        sessionData: {
          'session_id': 100,
          'host_user_id': 1,
          'court_count': 2,
        },
        matches: matches,
      );

      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          config: GameWizardConfig(
            sessionId: 100,
            courtCount: 2,
          )..customTotalRounds = 3,
        ),
      ));
      await tester.pumpAndSettle();

      expect(find.text('Lanjut ke Ronde 2'), findsOneWidget);

      // Host klik tombol Lanjut ke Ronde 2
      await tester.tap(find.text('Lanjut ke Ronde 2'));
      await tester.pumpAndSettle();

      // Sekarang berada di Ronde 2
      expect(find.text('Ronde 1 Selesai'), findsNothing);
      expect(service.broadcastRoundAdvancedCalled, isTrue);
      expect(service.broadcastNextRound, 2);
      expect(service.advanceRoundMatchesCalledCount, 1);

      // Ronde 3 tetap terkunci
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget);

      // Simulasikan event broadcast round_advanced duplikat dengan payload ronde 2 yang sama
      service.onRoundAdvancedCallback?.call({
        'session_id': 100,
        'current_round_num': 1,
        'next_round_num': 2,
      });
      await tester.pumpAndSettle();

      // Tetap di Ronde 2! Tidak boleh melompat ke Ronde 3
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget); // Hanya Ronde 3 yang terkunci

      // Simulasikan event out-of-order atau invalid leap round 4
      service.onRoundAdvancedCallback?.call({
        'session_id': 100,
        'current_round_num': 2,
        'next_round_num': 4,
      });
      await tester.pumpAndSettle();

      // Tetap di Ronde 2!
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget);
    });

    testWidgets('Player otomatis masuk ke Ronde 2 tanpa refresh saat menerima broadcast round_advanced dari Host', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matches = createTwoCourtThreeRoundMatchesData(
        round1Status: 'Completed',
        r1m1ScoreA: 6,
        r1m1ScoreB: 2,
        r1m2ScoreA: 6,
        r1m2ScoreB: 4,
        round2Status: 'Scheduled',
      );
      final service = _MockMatchService(
        sessionData: {
          'session_id': 100,
          'host_user_id': 1,
          'court_count': 2,
        },
        matches: matches,
      );

      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          config: GameWizardConfig(
            sessionId: 100,
            courtCount: 2,
          )..customTotalRounds = 3,
        ),
      ));
      await tester.pumpAndSettle();

      // Kondisi awal Player: Ronde 1 selesai, Ronde 2 terkunci
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);
      expect(find.byIcon(Icons.lock_rounded), findsNWidgets(2)); // Ronde 2 & 3 terkunci

      // Host berhasil klik lanjut -> broadcast round_advanced dikirim ke channel
      service.onRoundAdvancedCallback?.call({
        'session_id': 100,
        'current_round_num': 1,
        'next_round_num': 2,
        'session_active_round': 2,
      });
      await tester.pumpAndSettle();

      // Player OTOMATIS membuka Ronde 2 tanpa refresh!
      expect(find.text('Ronde 1 Selesai'), findsNothing);
      expect(find.text('Pilih Lapangan (2 Court):'), findsOneWidget);
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget); // Hanya Ronde 3 yang terkunci
    });

    testWidgets('Rekonsiliasi: Jika Player melewatkan event realtime, refetch data persisten tb_match otomatis memajukan Player ke Ronde 2', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matches = createTwoCourtThreeRoundMatchesData(
        round1Status: 'Completed',
        r1m1ScoreA: 6,
        r1m1ScoreB: 2,
        r1m2ScoreA: 6,
        r1m2ScoreB: 4,
        round2Status: 'Scheduled',
      );
      final service = _MockMatchService(
        sessionData: {
          'session_id': 100,
          'host_user_id': 1,
          'court_count': 2,
        },
        matches: matches,
      );

      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          config: GameWizardConfig(
            sessionId: 100,
            courtCount: 2,
          )..customTotalRounds = 3,
        ),
      ));
      await tester.pumpAndSettle();

      // Player masih di Ronde 1
      expect(find.text('Ronde 1 Selesai'), findsOneWidget);

      // Simulasikan Host memajukan ronde di server (tb_match untuk Ronde 2 diupdate menjadi In Progress)
      // TETAPI event realtime round_advanced hilang di jaringan (onRoundAdvancedCallback TIDAK dipanggil)
      for (final m in matches) {
        final roundNum = m['roundNumber'] as int;
        if (roundNum == 2) {
          m['status'] = 'In Progress';
          m['rawMatch']['status_match'] = 'In Progress';
        }
      }

      // Player menerima score_update atau data refetch rutin (onDataChangedCallback)
      service.onDataChangedCallback?.call();
      await tester.pumpAndSettle();

      // Rekonsiliasi bekerja! Player berhasil membaca status In Progress resmi dari server dan berpindah ke Ronde 2
      expect(find.text('Ronde 1 Selesai'), findsNothing);
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget); // Hanya Ronde 3 yang terkunci
    });

    testWidgets('Player refresh / buka sesi setelah Host memajukan ke Ronde 2 -> langsung membuka Ronde 2 aktif', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      // Backend matches: Ronde 1 Completed, Ronde 2 In Progress, Ronde 3 Scheduled
      final matches = createTwoCourtThreeRoundMatchesData(
        round1Status: 'Completed',
        r1m1ScoreA: 6,
        r1m1ScoreB: 2,
        r1m2ScoreA: 6,
        r1m2ScoreB: 4,
        round2Status: 'In Progress',
      );
      final service = _MockMatchService(
        sessionData: {
          'session_id': 100,
          'host_user_id': 1,
          'court_count': 2,
        },
        matches: matches,
      );

      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          config: GameWizardConfig(
            sessionId: 100,
            courtCount: 2,
          )..customTotalRounds = 3,
        ),
      ));
      await tester.pumpAndSettle();

      // Langsung membuka Ronde 2
      expect(find.text('Ronde 1 Selesai'), findsNothing);
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget); // Ronde 3 terkunci
    });

    testWidgets('Event lama (mundur) ditolak dan tidak memundurkan ronde aktif', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matches = createTwoCourtThreeRoundMatchesData(
        round1Status: 'Completed',
        r1m1ScoreA: 6,
        r1m1ScoreB: 2,
        r1m2ScoreA: 6,
        r1m2ScoreB: 4,
        round2Status: 'In Progress',
      );
      final service = _MockMatchService(
        sessionData: {
          'session_id': 100,
          'host_user_id': 1,
          'court_count': 2,
        },
        matches: matches,
      );

      final playerAuth = _MockAuthController(initialUser: playerUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: playerAuth,
          matchService: service,
          config: GameWizardConfig(
            sessionId: 100,
            courtCount: 2,
          )..customTotalRounds = 3,
        ),
      ));
      await tester.pumpAndSettle();

      // Sedang di Ronde 2
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget);

      // Event lama (Ronde 1) diterima terlambat
      service.onRoundAdvancedCallback?.call({
        'session_id': 100,
        'current_round_num': 1,
        'next_round_num': 1,
      });
      await tester.pumpAndSettle();

      // Tetap di Ronde 2, tidak mundur ke Ronde 1
      expect(find.byIcon(Icons.lock_rounded), findsOneWidget);
    });

    testWidgets('Transisi lengkap hingga Ronde 3: Court konsisten tetap 2 dan skor sesuai match masing-masing ronde', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final matches = createTwoCourtThreeRoundMatchesData(
        round1Status: 'Completed',
        r1m1ScoreA: 6,
        r1m1ScoreB: 2,
        r1m2ScoreA: 6,
        r1m2ScoreB: 4,
        round2Status: 'Completed',
        r2m1ScoreA: 6,
        r2m1ScoreB: 3,
        r2m2ScoreA: 6,
        r2m2ScoreB: 5,
        round3Status: 'Scheduled',
      );
      final service = _MockMatchService(
        sessionData: {
          'session_id': 100,
          'host_user_id': 1,
          'court_count': 2,
        },
        matches: matches,
      );

      final hostAuth = _MockAuthController(initialUser: hostUser);
      await tester.pumpWidget(MaterialApp(
        home: MatchScoringPage(
          sessionId: 100,
          hostUserId: 1,
          authController: hostAuth,
          matchService: service,
          config: GameWizardConfig(
            sessionId: 100,
            courtCount: 2,
          )..customTotalRounds = 3,
        ),
      ));
      await tester.pumpAndSettle();

      // Host melihat Ronde 2 Selesai dan tombol Lanjut ke Ronde 3
      expect(find.text('Ronde 2 Selesai'), findsOneWidget);
      expect(find.text('Lanjut ke Ronde 3'), findsOneWidget);

      // Host klik Lanjut ke Ronde 3
      await tester.tap(find.text('Lanjut ke Ronde 3'));
      await tester.pumpAndSettle();

      // Host sekarang di Ronde 3
      expect(find.text('Ronde 2 Selesai'), findsNothing);
      expect(service.broadcastNextRound, 3);
      expect(find.text('Pilih Lapangan (2 Court):'), findsOneWidget);
      expect(find.textContaining('Court 1 ('), findsOneWidget);
      expect(find.textContaining('Court 2 ('), findsOneWidget);
      expect(find.textContaining('Court 3'), findsNothing); // Tidak ada phantom court
    });
  });
}
