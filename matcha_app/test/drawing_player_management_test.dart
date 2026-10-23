import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/auth/data/datasource/auth_remote_data_source.dart';
import 'package:matcha_app/features/auth/domain/models/user_model.dart';
import 'package:matcha_app/features/auth/presentation/controllers/auth_controller.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/drawing/presentation/drawing_result_page.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/match/data/match_service.dart';

class _FakeAuthDataSource extends Fake implements AuthRemoteDataSource {}

class _MockAuthController extends AuthController {
  UserModel? _mockUser;
  _MockAuthController({UserModel? initialUser})
      : super(authDataSource: _FakeAuthDataSource()) {
    _mockUser = initialUser;
  }

  @override
  UserModel? get currentUser => _mockUser;
}

class _MockMatchService extends MatchService {
  final List<DrawingRound>? savedDrawing;
  final bool isLocked;
  String? lastAction;
  int? lastPlayerId;
  String? lastReason;

  _MockMatchService({
    this.savedDrawing,
    this.isLocked = false,
  });

  @override
  Future<Map<String, dynamic>?> getSession(dynamic sessionId) async {
    return {
      'session_id': sessionId,
      'host_user_id': 1,
      'active_round': 1,
      'status_session': isLocked ? 'In Progress' : 'Scheduled',
    };
  }

  @override
  Future<bool> isSessionDrawingLocked(dynamic sessionId) async => isLocked;

  @override
  Future<List<DrawingRound>?> loadSavedDrawing({
    required dynamic sessionId,
    List<GamePlayerItem>? registeredPlayers,
    int? courtCount,
  }) async => savedDrawing;

  @override
  Future<Map<String, dynamic>?> getActiveDrawingFormat(dynamic sessionId) async {
    return {'nama_format': 'Americano', 'match_format_id': 1};
  }

  @override
  Future<Map<String, dynamic>> managePlayerStatus({
    required dynamic sessionId,
    required int playerId,
    required String action,
    String? reason,
  }) async {
    lastAction = action;
    lastPlayerId = playerId;
    lastReason = reason;
    return {'success': true, 'status': action == 'pause' ? 'resting' : (action == 'withdraw' ? 'withdrawn' : 'active')};
  }

  @override
  Future<List<DrawingRound>> saveDrawingMatches({
    required dynamic sessionId,
    required List<DrawingRound> rounds,
    List<GamePlayerItem>? allPlayers,
    int? courtCount,
    String matchStatus = 'Scheduled',
    int? matchFormatId,
  }) async => rounds;

  bool stoppedDueToInsufficient = false;
  String? stoppedReason;
  int? substitutedOldPlayerId;
  int? substitutedNewPlayerId;
  int? substitutedMatchId;
  int? adjustedTargetPlayerId;
  String? adjustedAction;
  int? adjustedCurrentRoundNumber;

  @override
  Future<void> stopSessionDueToInsufficientPlayers({
    required dynamic sessionId,
    required String reason,
    int? stopPlayerId,
  }) async {
    stoppedDueToInsufficient = true;
    stoppedReason = reason;
  }

  @override
  Future<void> substituteMatchParticipant({
    required int matchId,
    required int oldPlayerId,
    required int newPlayerId,
    required String side,
  }) async {
    substitutedMatchId = matchId;
    substitutedOldPlayerId = oldPlayerId;
    substitutedNewPlayerId = newPlayerId;
    if (savedDrawing != null) {
      for (int r = 0; r < savedDrawing!.length; r++) {
        final round = savedDrawing![r];
        final updatedMatches = <DrawingMatch>[];
        final updatedResting = List<GamePlayerItem>.from(round.restingPlayers);
        final subPlayerIdx = updatedResting.indexWhere((p) => p.playerId == newPlayerId);
        GamePlayerItem? subPlayer;
        if (subPlayerIdx != -1) {
          subPlayer = updatedResting.removeAt(subPlayerIdx);
        }
        for (final m in round.matches) {
          if (m.matchId == matchId) {
            final newA = List<GamePlayerItem>.from(m.teamA);
            final newB = List<GamePlayerItem>.from(m.teamB);
            if (side == 'A' && subPlayer != null) {
              final idx = newA.indexWhere((p) => p.playerId == oldPlayerId);
              if (idx != -1) newA[idx] = subPlayer;
            } else if (side == 'B' && subPlayer != null) {
              final idx = newB.indexWhere((p) => p.playerId == oldPlayerId);
              if (idx != -1) newB[idx] = subPlayer;
            }
            updatedMatches.add(DrawingMatch(
              courtNumber: m.courtNumber,
              teamA: newA,
              teamB: newB,
              status: m.status,
              matchId: m.matchId,
            ));
          } else {
            updatedMatches.add(m);
          }
        }
        savedDrawing![r] = DrawingRound(
          roundNumber: round.roundNumber,
          matches: updatedMatches,
          restingPlayers: updatedResting,
        );
      }
    }
  }

  @override
  Future<void> cancelOrStopScheduledMatch({
    required int matchId,
    required String reason,
  }) async {}

  @override
  Future<void> setMatchParticipants({
    required int matchId,
    required List<GamePlayerItem> teamA,
    required List<GamePlayerItem> teamB,
    List<GamePlayerItem>? allPlayers,
  }) async {}

  @override
  Future<void> adjustFutureScheduleOnPlayerStatusChange({
    required dynamic sessionId,
    required int currentRoundNumber,
    required int targetPlayerId,
    required String action,
    List<GamePlayerItem>? allPlayers,
  }) async {
    adjustedTargetPlayerId = targetPlayerId;
    adjustedAction = action;
    adjustedCurrentRoundNumber = currentRoundNumber;
  }

  @override
  Future<void> broadcastDrawingUpdate(dynamic sessionId) async {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  List<GamePlayerItem> getFreshPlayers() => [
    const GamePlayerItem(id: '1', playerId: 1, name: 'Budi Santoso', level: 'Intermediate'),
    const GamePlayerItem(id: '2', playerId: 2, name: 'Siti Aminah', level: 'Intermediate'),
    const GamePlayerItem(id: '3', playerId: 3, name: 'Agus Wijaya', level: 'Beginner'),
    const GamePlayerItem(id: '4', playerId: 4, name: 'Dewi Lestari', level: 'Beginner'),
    const GamePlayerItem(id: '5', playerId: 5, name: 'Eko Pratama', level: 'Advanced'),
  ];

  GameWizardConfig createWizardConfig({List<GamePlayerItem>? players}) => GameWizardConfig(
    sessionId: 99,
    sport: 'Tennis',
    gameType: 'Americano',
    playMode: 'Single',
    courtCount: 1,
    players: players ?? getFreshPlayers(),
    activityName: 'Turnamen Americano',
  );

  List<DrawingRound> createMockRounds([List<GamePlayerItem>? players]) {
    final p = players ?? getFreshPlayers();
    return [
      DrawingRound(
        roundNumber: 1,
        matches: [
          DrawingMatch(
            courtNumber: 1,
            teamA: [p[0]],
            teamB: [p[1]],
            status: 'Scheduled',
          ),
        ],
        restingPlayers: [p[2], p[3], p[4]],
      ),
    ];
  }

  group('DrawingResultPage - Player Status Management Web Parity', () {
    testWidgets('1. Host melihat tombol Istirahat dan Keluar pada Court Roster dan Bench', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final testPlayers = getFreshPlayers();
      final wizardConfig = createWizardConfig(players: testPlayers);
      final rounds = createMockRounds(testPlayers);
      final mockAuth = _MockAuthController(
        initialUser: const UserModel(userId: 1, nama: 'Host User', email: 'host@matcha.app'),
      );
      final mockService = _MockMatchService(savedDrawing: rounds, isLocked: false);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 99,
          config: wizardConfig,
          initialRounds: rounds,
          authController: mockAuth,
          matchService: mockService,
          isHost: true,
        ),
      ));
      await tester.pumpAndSettle();

      // Memastikan header ROSTER PERTANDINGAN dan format Americano ada
      expect(find.text('ROSTER PERTANDINGAN'), findsOneWidget);
      expect(find.text('Americano'), findsOneWidget);

      // Court 1 ditampilkan
      expect(find.text('COURT 1'), findsAtLeastNWidgets(1));
      expect(find.text('Tim A'), findsOneWidget);
      expect(find.text('Tim B'), findsOneWidget);

      // Tombol aksi Istirahat dan Keluar harus muncul untuk Host
      expect(find.text('Istirahat'), findsWidgets);
      expect(find.text('Keluar'), findsWidgets);

      // Bangku cadangan menampilkan 3 pemain
      expect(find.text('Bangku Cadangan / Istirahat'), findsOneWidget);
      expect(find.text('3 Pemain'), findsOneWidget);
    });

    testWidgets('2. Non-host tidak melihat tombol aksi Istirahat dan Keluar', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final testPlayers = getFreshPlayers();
      final wizardConfig = createWizardConfig(players: testPlayers);
      final rounds = createMockRounds(testPlayers);
      final mockAuth = _MockAuthController(
        initialUser: const UserModel(userId: 2, nama: 'Regular Player', email: 'player@matcha.app'),
      );
      final mockService = _MockMatchService(savedDrawing: rounds, isLocked: false);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 99,
          config: wizardConfig,
          initialRounds: rounds,
          authController: mockAuth,
          matchService: mockService,
          isHost: false,
        ),
      ));
      await tester.pumpAndSettle();

      // Non-host tetap bisa melihat susunan roster dan bangku cadangan
      expect(find.text('ROSTER PERTANDINGAN'), findsOneWidget);
      expect(find.text('COURT 1'), findsAtLeastNWidgets(1));
      expect(find.text('Bangku Cadangan / Istirahat'), findsOneWidget);

      // Tapi tombol Istirahat dan Keluar TIDAK boleh tampil
      expect(find.text('Istirahat'), findsNothing);
      expect(find.text('Keluar'), findsNothing);
    });

    testWidgets('3. Klik Istirahat memunculkan dialog modal sesuai UI Web, validasi alasan, dan menyimpan perubahan', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final testPlayers = getFreshPlayers();
      final wizardConfig = createWizardConfig(players: testPlayers);
      final rounds = createMockRounds(testPlayers);
      final mockAuth = _MockAuthController(
        initialUser: const UserModel(userId: 1, nama: 'Host User', email: 'host@matcha.app'),
      );
      final mockService = _MockMatchService(savedDrawing: rounds, isLocked: false);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 99,
          config: wizardConfig,
          initialRounds: rounds,
          authController: mockAuth,
          matchService: mockService,
          isHost: true,
        ),
      ));
      await tester.pumpAndSettle();

      // Klik tombol Istirahat pertama (pada Budi Santoso di Court 1 Tim A)
      final btnIstirahat = find.text('Istirahat').first;
      await tester.ensureVisible(btnIstirahat);
      await tester.tap(btnIstirahat);
      await tester.pumpAndSettle();

      // Memastikan modal dialog "Istirahatkan Pemain Sementara" muncul
      expect(find.text('Istirahatkan Pemain Sementara'), findsOneWidget);
      expect(
        find.byWidgetPredicate((w) => w is RichText && w.text.toPlainText().contains('Istirahatkan sementara Budi Santoso')),
        findsOneWidget,
      );
      expect(find.text('Alasan diperlukan untuk transparansi dan catatan sesi pertandingan.'), findsOneWidget);
      expect(find.text('Ya, Istirahatkan Pemain'), findsOneWidget);
      expect(find.text('Batal'), findsOneWidget);

      // Klik tombol konfirmasi tanpa mengisi alasan -> harus muncul pesan error validasi
      await tester.tap(find.text('Ya, Istirahatkan Pemain'));
      await tester.pumpAndSettle();
      expect(find.text('Alasan wajib diisi sebelum menyimpan perubahan.'), findsOneWidget);

      // Isi alasan
      await tester.enterText(find.byType(TextField), 'Cedera kram betis');
      await tester.pumpAndSettle();

      // Klik simpan
      await tester.tap(find.text('Ya, Istirahatkan Pemain'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      // Dialog harus tertutup
      expect(find.text('Istirahatkan Pemain Sementara'), findsNothing);

      // Service harus dipanggil dengan parameter yang benar
      expect(mockService.lastAction, 'pause');
      expect(mockService.lastPlayerId, 1);
      expect(mockService.lastReason, 'Cedera kram betis');

      // SnackBar notifikasi sukses muncul
      expect(find.textContaining('Budi Santoso berhasil diistirahatkan ke bangku cadangan'), findsOneWidget);
    });

    testWidgets('Pemain yang diistirahatkan dari match In Progress hanya muncul di bench', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final players = getFreshPlayers();
      final config = createWizardConfig(players: players);
      final rounds = createMockRounds(players);
      rounds[0].matches[0].status = 'In Progress';
      final mockService = _MockMatchService(savedDrawing: rounds);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 99,
          config: config,
          initialRounds: rounds,
          authController: _MockAuthController(
            initialUser: const UserModel(userId: 1, nama: 'Host User', email: 'host@matcha.app'),
          ),
          matchService: mockService,
          isHost: true,
        ),
      ));
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.text('Istirahat').first);
      await tester.tap(find.text('Istirahat').first);
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'Perlu istirahat');
      await tester.tap(find.text('Ya, Istirahatkan Pemain'));
      await tester.pumpAndSettle();

      expect(find.text('Budi Santoso'), findsNothing);
      expect(find.text('4. Budi Santoso'), findsOneWidget);
      expect(find.text('Siap Main'), findsOneWidget);
    });

    testWidgets('Pemain yang dikeluarkan dari match In Progress hanya muncul di daftar Pemain Keluar', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final players = getFreshPlayers();
      final config = createWizardConfig(players: players);
      final rounds = createMockRounds(players);
      rounds[0].matches[0].status = 'In Progress';
      final mockService = _MockMatchService(savedDrawing: rounds);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 99,
          config: config,
          initialRounds: rounds,
          authController: _MockAuthController(
            initialUser: const UserModel(userId: 1, nama: 'Host User', email: 'host@matcha.app'),
          ),
          matchService: mockService,
          isHost: true,
        ),
      ));
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.text('Keluar').first);
      await tester.tap(find.text('Keluar').first);
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'Tidak dapat melanjutkan');
      await tester.tap(find.text('Ya, Keluarkan Pemain'));
      await tester.pumpAndSettle();

      expect(find.text('Budi Santoso'), findsNothing);
      expect(find.text('1. Budi Santoso'), findsOneWidget);
    });

    testWidgets('4. Mengistirahatkan pemain saat pemain pas-pasan (2 pemain single) mengosongkan court, memindahkan ke bench, dan mendisable tombol scoring serta otomatis assign saat Siap Main', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final twoPlayers = [
        const GamePlayerItem(id: '1', playerId: 1, name: 'Ocean Abelvan', level: 'Intermediate'),
        const GamePlayerItem(id: '2', playerId: 2, name: 'Hilmy Ram Fahreza', level: 'Intermediate'),
      ];

      final twoPlayerConfig = GameWizardConfig(
        sessionId: 101,
        sport: 'Padel',
        gameType: 'First To',
        playMode: 'Single',
        courtCount: 1,
        players: List.from(twoPlayers),
        activityName: 'Single 1v1',
      );

      final rounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              courtNumber: 1,
              teamA: [twoPlayers[0]],
              teamB: [twoPlayers[1]],
              status: 'Scheduled',
            ),
          ],
          restingPlayers: const [],
        ),
      ];

      final mockAuth = _MockAuthController(
        initialUser: const UserModel(userId: 1, nama: 'Host User', email: 'host@matcha.app'),
      );
      final mockService = _MockMatchService(savedDrawing: rounds, isLocked: false);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 101,
          config: twoPlayerConfig,
          initialRounds: rounds,
          authController: mockAuth,
          matchService: mockService,
          isHost: true,
        ),
      ));
      await tester.pumpAndSettle();

      // Klik tombol Istirahat pada Ocean Abelvan
      final btnIstirahat = find.text('Istirahat').first;
      await tester.ensureVisible(btnIstirahat);
      await tester.tap(btnIstirahat);
      await tester.pumpAndSettle();

      // Isi alasan istirahat
      await tester.enterText(find.byType(TextField), 'Capek butuh minum');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ya, Istirahatkan Pemain'));
      await tester.pumpAndSettle();

      // managePlayerStatus dipanggil
      expect(mockService.lastAction, 'pause');
      expect(mockService.lastPlayerId, 1);

      // Court menampilkan Belum ada pemain
      expect(find.text('Belum ada pemain'), findsWidgets);

      // Di Bangku Cadangan ada tombol Siap Main untuk Ocean Abelvan
      expect(find.text('Siap Main'), findsOneWidget);

      // Muncul banner informasi bahwa semua pemain berada di bangku cadangan
      expect(find.textContaining('Semua pemain berada di bangku cadangan / istirahat'), findsOneWidget);

      // Tombol scoring menjadi disabled dengan teks Belum Ada Pemain Bertanding
      expect(find.text('Belum Ada Pemain Bertanding'), findsOneWidget);

      // TEST REAKTIVASI (Siap Main):
      final btnSiapMain = find.text('Siap Main');
      await tester.ensureVisible(btnSiapMain);
      await tester.tap(btnSiapMain);
      await tester.pumpAndSettle();

      // Dialog konfirmasi Siap Main muncul
      expect(find.text('Ya, Siap Main'), findsOneWidget);
      await tester.tap(find.text('Ya, Siap Main'));
      await tester.pumpAndSettle();

      // managePlayerStatus dipanggil dengan reactivate
      expect(mockService.lastAction, 'reactivate');

      // Kedua pemain otomatis masuk kembali ke Tim A dan Tim B di Court 1
      expect(find.text('Kunci Tim & Mulai Scoring Live'), findsOneWidget);
      expect(find.text('Belum Ada Pemain Bertanding'), findsNothing);
    });

    testWidgets('5. Mengeluarkan pemain saat pemain pas-pasan (2 pemain single) memicu dialog konfirmasi penghentian sesi', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final twoPlayers = [
        const GamePlayerItem(id: '1', playerId: 1, name: 'Ocean Abelvan', level: 'Intermediate'),
        const GamePlayerItem(id: '2', playerId: 2, name: 'Hilmy Ram Fahreza', level: 'Intermediate'),
      ];

      final twoPlayerConfig = GameWizardConfig(
        sessionId: 102,
        sport: 'Padel',
        gameType: 'First To',
        playMode: 'Single',
        courtCount: 1,
        players: List.from(twoPlayers),
        activityName: 'Single 1v1',
      );

      final rounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              courtNumber: 1,
              teamA: [twoPlayers[0]],
              teamB: [twoPlayers[1]],
              status: 'Scheduled',
            ),
          ],
          restingPlayers: const [],
        ),
      ];

      final mockAuth = _MockAuthController(
        initialUser: const UserModel(userId: 1, nama: 'Host User', email: 'host@matcha.app'),
      );
      final mockService = _MockMatchService(savedDrawing: rounds, isLocked: false);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 102,
          config: twoPlayerConfig,
          initialRounds: rounds,
          authController: mockAuth,
          matchService: mockService,
          isHost: true,
        ),
      ));
      await tester.pumpAndSettle();

      // Klik tombol Keluar pada Ocean Abelvan
      final btnKeluar = find.text('Keluar').first;
      await tester.ensureVisible(btnKeluar);
      await tester.tap(btnKeluar);
      await tester.pumpAndSettle();

      // Isi alasan keluar
      await tester.enterText(find.byType(TextField), 'Ada keperluan mendadak');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ya, Keluarkan Pemain'));
      await tester.pumpAndSettle();

      // Harus muncul dialog peringatan "Pertandingan Tidak Cukup Pemain"
      expect(find.text('Pertandingan Tidak Cukup Pemain'), findsOneWidget);
      expect(find.textContaining('Pertandingan tidak dapat dilanjutkan dan sesi pertandingan akan dihentikan'), findsOneWidget);
      expect(find.text('Hentikan Pertandingan & Keluarkan'), findsOneWidget);

      // Konfirmasi penghentian
      await tester.tap(find.text('Hentikan Pertandingan & Keluarkan'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));

      // Service harus dipanggil untuk mengeluarkan pemain dan menghentikan sesi
      expect(mockService.lastAction, 'withdraw');
      expect(mockService.lastPlayerId, 1);
      expect(mockService.stoppedDueToInsufficient, isTrue);
    });

    testWidgets('6. Mengistirahatkan pemain di court saat ada pemain di bench akan menggantikan pemain court dengan pemain bench dan memindahkan pemain ke bench', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final testPlayers = getFreshPlayers();
      final wizardConfig = createWizardConfig(players: testPlayers);
      final rounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              courtNumber: 1,
              teamA: [testPlayers[0]], // Budi Santoso (1)
              teamB: [testPlayers[1]], // Siti Aminah (2)
              status: 'Scheduled',
              matchId: 201,
            ),
          ],
          restingPlayers: [testPlayers[2], testPlayers[3], testPlayers[4]], // Agus (3), Dewi (4), Eko (5)
        ),
      ];

      final mockAuth = _MockAuthController(
        initialUser: const UserModel(userId: 1, nama: 'Host User', email: 'host@matcha.app'),
      );
      final mockService = _MockMatchService(savedDrawing: rounds, isLocked: false);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 99,
          config: wizardConfig,
          initialRounds: rounds,
          authController: mockAuth,
          matchService: mockService,
          isHost: true,
        ),
      ));
      await tester.pumpAndSettle();

      // Budi Santoso awalnya tampil di Court 1 Tim A
      expect(find.text('1. Budi Santoso'), findsOneWidget);
      expect(find.text('3 Pemain'), findsOneWidget);

      // Klik tombol Istirahat pada Budi Santoso
      final btnIstirahat = find.text('Istirahat').first;
      await tester.ensureVisible(btnIstirahat);
      await tester.tap(btnIstirahat);
      await tester.pumpAndSettle();

      // Isi alasan dan submit
      await tester.enterText(find.byType(TextField), 'Kram otot');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ya, Istirahatkan Pemain'));
      await tester.pumpAndSettle();

      // Service harus dipanggil dengan parameter yang benar
      expect(mockService.lastAction, 'pause');
      expect(mockService.lastPlayerId, 1);
      expect(mockService.substitutedOldPlayerId, 1);
      expect(mockService.substitutedNewPlayerId, 3); // Agus Wijaya menggantikan Budi

      // Di Court 1 Tim A, Agus Wijaya sekarang bertanding menggantikan Budi Santoso
      expect(find.text('1. Agus Wijaya'), findsOneWidget);

      // Budi Santoso kini berada di bangku cadangan dengan badge 'Istirahat' dan tombol 'Siap Main'
      expect(find.text('Bangku Cadangan / Istirahat'), findsOneWidget);
      expect(find.text('Siap Main'), findsOneWidget);

      // adjustFutureScheduleOnPlayerStatusChange juga dipanggil
      expect(mockService.adjustedTargetPlayerId, 1);
      expect(mockService.adjustedAction, 'pause');
    });

    testWidgets('7. Mengeluarkan pemain di court saat ada pemain di bench akan menggantikan pemain court dan menghapus pemain dari rotasi aktif', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final testPlayers = getFreshPlayers();
      final wizardConfig = createWizardConfig(players: testPlayers);
      final rounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              courtNumber: 1,
              teamA: [testPlayers[0]], // Budi Santoso (1)
              teamB: [testPlayers[1]], // Siti Aminah (2)
              status: 'Scheduled',
              matchId: 202,
            ),
          ],
          restingPlayers: [testPlayers[2], testPlayers[3], testPlayers[4]], // Agus (3), Dewi (4), Eko (5)
        ),
      ];

      final mockAuth = _MockAuthController(
        initialUser: const UserModel(userId: 1, nama: 'Host User', email: 'host@matcha.app'),
      );
      final mockService = _MockMatchService(savedDrawing: rounds, isLocked: false);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 99,
          config: wizardConfig,
          initialRounds: rounds,
          authController: mockAuth,
          matchService: mockService,
          isHost: true,
        ),
      ));
      await tester.pumpAndSettle();

      // Klik tombol Keluar pada Budi Santoso di Court 1 Tim A
      final btnKeluar = find.text('Keluar').first;
      await tester.ensureVisible(btnKeluar);
      await tester.tap(btnKeluar);
      await tester.pumpAndSettle();

      // Isi alasan dan konfirmasi keluar
      await tester.enterText(find.byType(TextField), 'Harus pulang cepat');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Ya, Keluarkan Pemain'));
      await tester.pumpAndSettle();

      // Service harus dipanggil
      expect(mockService.lastAction, 'withdraw');
      expect(mockService.lastPlayerId, 1);
      expect(mockService.substitutedOldPlayerId, 1);
      expect(mockService.substitutedNewPlayerId, 3); // Agus menggantikan Budi

      // Di Court 1 Tim A, Agus Wijaya sekarang bertanding
      expect(find.text('1. Agus Wijaya'), findsOneWidget);

      // adjustFutureScheduleOnPlayerStatusChange dipanggil
      expect(mockService.adjustedTargetPlayerId, 1);
      expect(mockService.adjustedAction, 'withdraw');
    });
  });
}
