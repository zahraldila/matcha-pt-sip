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

  @override
  Future<void> broadcastDrawingUpdate(dynamic sessionId) async {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  final testPlayers = [
    const GamePlayerItem(id: '1', playerId: 1, name: 'Budi Santoso', level: 'Intermediate'),
    const GamePlayerItem(id: '2', playerId: 2, name: 'Siti Aminah', level: 'Intermediate'),
    const GamePlayerItem(id: '3', playerId: 3, name: 'Agus Wijaya', level: 'Beginner'),
    const GamePlayerItem(id: '4', playerId: 4, name: 'Dewi Lestari', level: 'Beginner'),
    const GamePlayerItem(id: '5', playerId: 5, name: 'Eko Pratama', level: 'Advanced'),
  ];

  final wizardConfig = GameWizardConfig(
    sessionId: 99,
    sport: 'Tennis',
    gameType: 'Americano',
    playMode: 'Single',
    courtCount: 1,
    players: List.from(testPlayers),
    activityName: 'Turnamen Americano',
  );

  List<DrawingRound> createMockRounds() {
    return [
      DrawingRound(
        roundNumber: 1,
        matches: [
          DrawingMatch(
            courtNumber: 1,
            teamA: [testPlayers[0]],
            teamB: [testPlayers[1]],
            status: 'Scheduled',
          ),
        ],
        restingPlayers: [testPlayers[2], testPlayers[3], testPlayers[4]],
      ),
    ];
  }

  group('DrawingResultPage - Player Status Management Web Parity', () {
    testWidgets('1. Host melihat tombol Istirahat dan Keluar pada Court Roster dan Bench', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final rounds = createMockRounds();
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

      final rounds = createMockRounds();
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

      final rounds = createMockRounds();
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
  });
}
