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
  List<DrawingRound> savedDrawing = [];
  bool isLocked = true;

  _MockMatchService({required this.savedDrawing, this.isLocked = true});

  @override
  Future<Map<String, dynamic>?> getSession(dynamic sessionId) async {
    return {
      'id': sessionId,
      'host_user_id': 1,
      'status': 'ongoing',
      'sport': 'Padel',
      'game_type': 'Americano',
      'match_format_id': 1,
    };
  }

  @override
  Future<bool> isSessionDrawingLocked(dynamic sessionId) async => isLocked;

  @override
  Future<List<DrawingRound>?> loadSavedDrawing({
    required dynamic sessionId,
    List<GamePlayerItem>? registeredPlayers,
    int? courtCount,
  }) async {
    return savedDrawing;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  final dummyPlayers = [
    const GamePlayerItem(id: '1', name: 'Budi Santoso', gender: 'male', level: 'Intermediate'),
    const GamePlayerItem(id: '2', name: 'Siti Rahma', gender: 'female', level: 'Beginner'),
    const GamePlayerItem(id: '3', name: 'Andi Wijaya', gender: 'male', level: 'Intermediate'),
    const GamePlayerItem(id: '4', name: 'Dewi Lestari', gender: 'female', level: 'Beginner'),
    const GamePlayerItem(id: '5', name: 'Eko Pratama', gender: 'male', level: 'Intermediate'),
  ];

  final wizardConfig = GameWizardConfig(
    sessionId: 99,
    sport: 'Padel',
    gameType: 'Americano',
    playMode: 'Double',
    courtCount: 1,
    players: dummyPlayers,
    activityName: 'Matcha Surprise Cup',
  );

  List<DrawingRound> createMockRounds({bool round1Finished = false, bool round2Finished = false}) {
    return [
      DrawingRound(
        roundNumber: 1,
        matches: [
          DrawingMatch(
            matchId: 101,
            courtNumber: 1,
            teamA: [dummyPlayers[0], dummyPlayers[1]],
            teamB: [dummyPlayers[2], dummyPlayers[3]],
            status: round1Finished ? 'completed' : 'ongoing',
          ),
        ],
        restingPlayers: [dummyPlayers[4]],
      ),
      DrawingRound(
        roundNumber: 2,
        matches: [
          DrawingMatch(
            matchId: 102,
            courtNumber: 1,
            teamA: [dummyPlayers[0], dummyPlayers[2]],
            teamB: [dummyPlayers[1], dummyPlayers[4]],
            status: round2Finished ? 'completed' : 'pending',
          ),
        ],
        restingPlayers: [dummyPlayers[3]],
      ),
      DrawingRound(
        roundNumber: 3,
        matches: [
          DrawingMatch(
            matchId: 103,
            courtNumber: 1,
            teamA: [dummyPlayers[1], dummyPlayers[3]],
            teamB: [dummyPlayers[2], dummyPlayers[4]],
            status: 'pending',
          ),
        ],
        restingPlayers: [dummyPlayers[0]],
      ),
    ];
  }

  Widget createTestWidget(List<DrawingRound> rounds) {
    final mockAuth = _MockAuthController(
      initialUser: const UserModel(
        userId: 1,
        nama: 'Host User',
        email: 'host@matcha.app',
      ),
    );
    final mockService = _MockMatchService(savedDrawing: rounds, isLocked: true);

    return MaterialApp(
      home: DrawingResultPage(
        sessionId: 99,
        config: wizardConfig,
        initialRounds: rounds,
        authController: mockAuth,
        matchService: mockService,
        isHost: true,
      ),
    );
  }

  group('Drawing Surprise Mechanism (Progressive Round Unlocking)', () {
    testWidgets('1. Ronde 1 terbuka sejak awal dan menampilkan susunan lapangan', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final rounds = createMockRounds(round1Finished: false);

      await tester.pumpWidget(createTestWidget(rounds));
      await tester.pumpAndSettle();

      // Ronde 1 harus aktif dan terbuka
      expect(find.text('LIVE PREVIEW'), findsOneWidget);
      expect(find.text('Court 1 (Sesi Game)'), findsOneWidget);
      expect(find.textContaining('Budi Santoso'), findsAtLeastNWidgets(1));
      expect(find.text('BANGKU ISTIRAHAT (BENCH)'), findsOneWidget);
      expect(find.text('Eko Pratama'), findsAtLeastNWidgets(1));

      // Tab selector check
      expect(find.text('Ronde 1'), findsOneWidget);
      expect(find.text('Ronde 2'), findsOneWidget);
      expect(find.text('Terkunci'), findsAtLeastNWidgets(1));
    });

    testWidgets('2. Ronde 2 terkunci saat Ronde 1 belum selesai dan menampilkan Locked State Card', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final rounds = createMockRounds(round1Finished: false);

      await tester.pumpWidget(createTestWidget(rounds));
      await tester.pumpAndSettle();

      // Klik tab Ronde 2
      final tabRonde2 = find.byType(ChoiceChip).at(1);
      await tester.ensureVisible(tabRonde2);
      await tester.tap(tabRonde2);
      await tester.pumpAndSettle();

      // Harusnya menampilkan Locked State Card
      expect(find.text('Ronde Terkunci'), findsOneWidget);
      expect(find.text('Ronde 2 Belum Dimulai'), findsOneWidget);
      expect(
        find.textContaining('Bagan drawing dan susunan pemain untuk Ronde 2 akan terbuka secara otomatis setelah Ronde 1 selesai dimainkan.'),
        findsOneWidget,
      );

      // Bagan lapangan dan pemain Ronde 2 TIDAK boleh bocor / tampil
      expect(find.text('LIVE PREVIEW'), findsNothing);
      expect(find.text('Court 1 (Sesi Game)'), findsNothing);

      // Tombol pintas ke Ronde Aktif
      final shortcutButton = find.text('Buka Ronde 1 (Aktif)');
      expect(shortcutButton, findsOneWidget);

      // Klik tombol pintas harus mengembalikan ke Ronde 1
      await tester.ensureVisible(shortcutButton);
      await tester.tap(shortcutButton);
      await tester.pumpAndSettle();

      expect(find.text('LIVE PREVIEW'), findsOneWidget);
      expect(find.text('Court 1 (Sesi Game)'), findsOneWidget);
    });

    testWidgets('3. Ronde 2 otomatis terbuka setelah Ronde 1 selesai dimainkan', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      // Setup ronde 1 sudah selesai (completed)
      final rounds = createMockRounds(round1Finished: true, round2Finished: false);

      await tester.pumpWidget(createTestWidget(rounds));
      await tester.pumpAndSettle();

      // Tab Ronde 1 menunjukkan badge Selesai
      expect(find.text('Selesai'), findsAtLeastNWidgets(1));

      // Klik tab Ronde 2
      final tabRonde2 = find.byType(ChoiceChip).at(1);
      await tester.ensureVisible(tabRonde2);
      await tester.tap(tabRonde2);
      await tester.pumpAndSettle();

      // Ronde 2 sekarang terbuka!
      expect(find.text('LIVE PREVIEW'), findsOneWidget);
      expect(find.text('Court 1 (Sesi Game)'), findsOneWidget);
      expect(find.text('BANGKU ISTIRAHAT (BENCH)'), findsOneWidget);
      expect(find.text('Dewi Lestari'), findsAtLeastNWidgets(1));

      // Namun Ronde 3 masih harus tetap terkunci
      final tabRonde3 = find.byType(ChoiceChip).at(2);
      await tester.ensureVisible(tabRonde3);
      await tester.tap(tabRonde3);
      await tester.pumpAndSettle();

      expect(find.text('Ronde Terkunci'), findsOneWidget);
      expect(find.text('Ronde 3 Belum Dimulai'), findsOneWidget);
      expect(find.text('Buka Ronde 2 (Aktif)'), findsOneWidget);
    });

    testWidgets('4. Membuka DrawingResultPage dengan initialRoundIndex Ronde 2 langsung menampilkan Court & Roster (bukan gembok)', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final rounds = createMockRounds(round1Finished: true, round2Finished: false);
      final mockAuth = _MockAuthController(
        initialUser: const UserModel(userId: 1, nama: 'Host User', email: 'host@matcha.app'),
      );
      final mockService = _MockMatchService(savedDrawing: rounds, isLocked: true);

      await tester.pumpWidget(MaterialApp(
        home: DrawingResultPage(
          sessionId: 99,
          config: wizardConfig,
          initialRounds: rounds,
          initialRoundIndex: 1,
          authController: mockAuth,
          matchService: mockService,
          isHost: true,
        ),
      ));
      await tester.pumpAndSettle();

      // Tab Ronde 1 berlabel Selesai
      expect(find.text('Selesai'), findsAtLeastNWidgets(1));

      // Ronde 2 langsung terpilih dan TERBUKA tanpa kartu gembok
      expect(find.text('Ronde 2 Belum Dimulai'), findsNothing);
      expect(find.text('Ronde Terkunci'), findsNothing);
      expect(find.text('LIVE PREVIEW'), findsOneWidget);
      expect(find.text('Court 1 (Sesi Game)'), findsOneWidget);
      expect(find.text('BANGKU ISTIRAHAT (BENCH)'), findsOneWidget);

      // Tombol bawah menampilkan 'Mulai Scoring Ronde 2'
      expect(find.textContaining('Mulai Scoring Ronde 2'), findsOneWidget);
    });
  });
}
