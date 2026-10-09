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
  bool _mockIsLoading = false;

  _MockAuthController({UserModel? initialUser, bool initialLoading = false})
      : super(authDataSource: _FakeAuthDataSource()) {
    _mockUser = initialUser;
    _mockIsLoading = initialLoading;
  }

  @override
  UserModel? get currentUser => _mockUser;

  @override
  bool get isLoading => _mockIsLoading;

  void setUser(UserModel? user) {
    _mockUser = user;
    _mockIsLoading = false;
    notifyListeners();
  }
}

class _MockMatchServiceForDrawingFlow extends MatchService {
  Map<String, dynamic>? sessionData;
  List<DrawingRound> savedDrawing = [];
  Map<String, dynamic>? activeDrawingFormatData;
  bool isDrawingLockedResult = false;

  bool saveDrawingCalled = false;
  int saveCallCount = 0;
  int? lastSavedMatchFormatId;
  List<DrawingRound>? lastSavedRounds;
  bool broadcastCalled = false;
  bool lockCalled = false;

  _MockMatchServiceForDrawingFlow({
    this.sessionData,
    List<DrawingRound>? drawing,
    this.activeDrawingFormatData,
    this.isDrawingLockedResult = false,
  }) {
    if (drawing != null) savedDrawing = drawing;
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
  Future<Map<String, dynamic>?> getActiveDrawingFormat(dynamic sessionId) async {
    return activeDrawingFormatData;
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
    saveCallCount++;
    lastSavedMatchFormatId = matchFormatId;
    lastSavedRounds = rounds;
    savedDrawing = rounds;
    return rounds;
  }

  @override
  Future<void> broadcastDrawingUpdate(dynamic sessionId) async {
    broadcastCalled = true;
  }

  @override
  Future<List<DrawingRound>> lockDrawingSession(
    dynamic sessionId, {
    required List<DrawingRound> rounds,
    List<GamePlayerItem>? allPlayers,
    int? matchFormatId,
  }) async {
    lockCalled = true;
    lastSavedMatchFormatId = matchFormatId;
    isDrawingLockedResult = true;
    savedDrawing = rounds;
    return rounds;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  final dummyHost = UserModel(
    userId: 10,
    email: 'host@matcha.com',
    nama: 'Host Turnamen',
    role: 'Host',
  );

  final players8 = [
    const GamePlayerItem(id: '1', name: 'Rahma', level: 'Intermediate', isGuest: false),
    const GamePlayerItem(id: '2', name: 'Zahra', level: 'Intermediate', isGuest: false),
    const GamePlayerItem(id: '3', name: 'Adya', level: 'Intermediate', isGuest: false),
    const GamePlayerItem(id: '4', name: 'Hilmy', level: 'Intermediate', isGuest: false),
    const GamePlayerItem(id: '5', name: 'Bambang', level: 'Beginner', isGuest: false),
    const GamePlayerItem(id: '6', name: 'Budi', level: 'Beginner', isGuest: false),
    const GamePlayerItem(id: '7', name: 'Cici', level: 'Intermediate', isGuest: false),
    const GamePlayerItem(id: '8', name: 'Citra', level: 'Intermediate', isGuest: false),
  ];

  group('Team Americano Drawing & Acak Ulang Flow Tests', () {
    test('Format mapping bidirectional verification (1-6)', () {
      expect(MatchService.formatNameToId('Americano'), 1);
      expect(MatchService.formatNameToId('americano'), 1);
      expect(MatchService.formatNameToId('Mexicano'), 2);
      expect(MatchService.formatNameToId('Mix Americano'), 3);
      expect(MatchService.formatNameToId('Team Americano'), 4);
      expect(MatchService.formatNameToId('team americano'), 4);
      expect(MatchService.formatNameToId('Tennis Single / Double'), 5);
      expect(MatchService.formatNameToId('King of the Court'), 6);
      expect(MatchService.formatNameToId(''), isNull);
      expect(MatchService.formatNameToId('Unknown Format'), isNull);

      expect(MatchService.formatIdToName(1), 'Americano');
      expect(MatchService.formatIdToName(2), 'Mexicano');
      expect(MatchService.formatIdToName(3), 'Mix Americano');
      expect(MatchService.formatIdToName(4), 'Team Americano');
      expect(MatchService.formatIdToName(5), 'Tennis Single / Double');
      expect(MatchService.formatIdToName(6), 'King of the Court');
      expect(MatchService.formatIdToName(99), isNull);
    });

    testWidgets('1. Team Americano dari wizard dapat menyimpan preview dan Acak Ulang dengan matchFormatId 4', (tester) async {
      final auth = _MockAuthController()..setUser(dummyHost);
      final service = _MockMatchServiceForDrawingFlow(
        sessionData: {'session_id': 101, 'host_user_id': 10},
        drawing: [], // DB awal belum ada drawing
      );

      final initialRounds = MatchaDrawingEngine.generateDrawing(
        players: players8,
        courtCount: 2,
        gameType: 'Team Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      final config = GameWizardConfig(
        sessionId: 101,
        hostUserId: 10,
        activityName: 'Mabar Team Americano',
        venueName: 'Barong Arena',
        sport: 'Padel',
        gameType: 'Team Americano',
        scoringSystem: 'Total of 3',
        playMode: 'Double',
        courtCount: 2,
        players: players8,
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 101,
            config: config,
            initialRounds: initialRounds,
            authController: auth,
            isHost: true,
            hostUserId: 10,
            matchService: service,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Preview awal disimpan dengan format ID 4 (Team Americano)
      expect(service.saveDrawingCalled, isTrue);
      expect(service.lastSavedMatchFormatId, equals(4));

      // Reset tracker untuk Acak Ulang
      service.saveCallCount = 0;
      service.lastSavedMatchFormatId = null;

      // Cari tombol Acak Ulang dan tap
      final shuffleBtn = find.text('Acak Ulang');
      expect(shuffleBtn, findsOneWidget);
      await tester.tap(shuffleBtn);
      await tester.pumpAndSettle();

      // Acak Ulang berhasil menyimpan ke server dengan matchFormatId 4
      expect(service.saveCallCount, equals(1));
      expect(service.lastSavedMatchFormatId, equals(4));
      expect(service.lastSavedRounds, isNotNull);

      // Verifikasi snackbar sukses muncul, bukan exception format tidak dikenali
      expect(find.text('Jadwal dan rotasi pemain berhasil diacak ulang! 🔀'), findsOneWidget);
      expect(find.textContaining('Format pertandingan tidak dikenali'), findsNothing);
    });

    testWidgets('2. Membuka drawing tersimpan dari detail sesi memulihkan format Team Americano dan Acak Ulang retains format ID 4', (tester) async {
      final auth = _MockAuthController()..setUser(dummyHost);

      final existingRounds = MatchaDrawingEngine.generateDrawing(
        players: players8,
        courtCount: 2,
        gameType: 'Team Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      final service = _MockMatchServiceForDrawingFlow(
        sessionData: {'session_id': 202, 'host_user_id': 10},
        drawing: existingRounds,
        activeDrawingFormatData: {
          'drawing_id': 55,
          'match_format_id': 4,
          'nama_format': 'Team Americano',
        },
      );

      // Membuka drawing dari detail sesi di mana config.gameType kosong
      final configWithoutGameType = GameWizardConfig(
        sessionId: 202,
        hostUserId: 10,
        activityName: 'Mabar Detail Sesi',
        venueName: 'Barong Arena',
        sport: 'Padel',
        gameType: '', // Kosong saat dibuka dari session detail
        scoringSystem: 'Total of 3',
        playMode: 'Double',
        courtCount: 2,
        players: players8,
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 202,
            config: configWithoutGameType,
            authController: auth,
            isHost: true,
            hostUserId: 10,
            matchService: service,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Tombol Acak Ulang tersedia
      final shuffleBtn = find.text('Acak Ulang');
      expect(shuffleBtn, findsOneWidget);

      // Reset tracker
      service.saveCallCount = 0;
      service.lastSavedMatchFormatId = null;

      // Tap Acak Ulang
      await tester.tap(shuffleBtn);
      await tester.pumpAndSettle();

      // Format tersimpan berhasil dipulihkan menjadi Team Americano (ID 4)
      expect(service.saveCallCount, equals(1));
      expect(service.lastSavedMatchFormatId, equals(4));
      expect(find.textContaining('Format pertandingan tidak dikenali'), findsNothing);
      expect(find.text('Jadwal dan rotasi pemain berhasil diacak ulang! 🔀'), findsOneWidget);
    });

    testWidgets('3. Format lain (Americano ID 1, Mexicano ID 2, Tennis ID 5) tetap menyimpan ID yang benar', (tester) async {
      final formatsToTest = {
        'Americano': 1,
        'Mexicano': 2,
        'Mix Americano': 3,
        'Tennis Single / Double': 5,
        'King of the Court': 6,
      };

      for (final entry in formatsToTest.entries) {
        final sessId = 300 + entry.value;
        final auth = _MockAuthController()..setUser(dummyHost);
        final service = _MockMatchServiceForDrawingFlow(
          sessionData: {'session_id': sessId, 'host_user_id': 10},
          drawing: [],
        );

        final config = GameWizardConfig(
          sessionId: sessId,
          hostUserId: 10,
          activityName: 'Format Test ${entry.key}',
          sport: 'Padel',
          gameType: entry.key,
          courtCount: 1,
          players: players8.sublist(0, 4),
        );

        await tester.pumpWidget(
          MaterialApp(
            home: DrawingResultPage(
              key: ValueKey(entry.key),
              sessionId: sessId,
              config: config,
              authController: auth,
              isHost: true,
              hostUserId: 10,
              matchService: service,
            ),
          ),
        );
        await tester.pumpAndSettle();

        final shuffleBtn = find.text('Acak Ulang');
        expect(shuffleBtn, findsOneWidget);

        service.saveCallCount = 0;
        service.lastSavedMatchFormatId = null;

        await tester.tap(shuffleBtn);
        await tester.pumpAndSettle();

        expect(service.lastSavedMatchFormatId, equals(entry.value),
            reason: 'Format ${entry.key} harus menyimpan matchFormatId ${entry.value}');
      }
    });

    testWidgets('4. Config kosong tanpa format di DB tidak fallback otomatis dan tidak melakukan write', (tester) async {
      final auth = _MockAuthController()..setUser(dummyHost);
      final service = _MockMatchServiceForDrawingFlow(
        sessionData: {'session_id': 404, 'host_user_id': 10},
        drawing: [],
        activeDrawingFormatData: null, // Tidak ada format di DB
      );

      final emptyConfig = GameWizardConfig(
        sessionId: 404,
        hostUserId: 10,
        activityName: 'No Format Session',
        gameType: '', // Kosong sama sekali
        courtCount: 1,
        players: players8.sublist(0, 4),
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 404,
            config: emptyConfig,
            authController: auth,
            isHost: true,
            hostUserId: 10,
            matchService: service,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Reset save tracker
      service.saveDrawingCalled = false;
      service.saveCallCount = 0;

      // Coba tap tombol Acak Ulang
      final shuffleBtn = find.text('Acak Ulang');
      expect(shuffleBtn, findsOneWidget);

      await tester.tap(shuffleBtn);
      await tester.pumpAndSettle();

      // Tidak ada write ke database!
      expect(service.saveDrawingCalled, isFalse);
      expect(service.saveCallCount, equals(0));

      // Menampilkan pesan yang jelas
      expect(find.text('Format pertandingan belum ditentukan. Tidak dapat mengacak ulang jadwal.'), findsOneWidget);
    });

    testWidgets('5. Drawing terkunci mencegah Acak Ulang', (tester) async {
      final auth = _MockAuthController()..setUser(dummyHost);
      final service = _MockMatchServiceForDrawingFlow(
        sessionData: {'session_id': 505, 'host_user_id': 10},
        drawing: [
          DrawingRound(
            roundNumber: 1,
            matches: [
              DrawingMatch(
                courtNumber: 1,
                teamA: [players8[0], players8[1]],
                teamB: [players8[2], players8[3]],
              ),
            ],
          ),
        ],
        activeDrawingFormatData: {
          'drawing_id': 88,
          'match_format_id': 4,
          'nama_format': 'Team Americano',
        },
        isDrawingLockedResult: true, // Sesi terkunci
      );

      final config = GameWizardConfig(
        sessionId: 505,
        hostUserId: 10,
        gameType: 'Team Americano',
        courtCount: 1,
        players: players8.sublist(0, 4),
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 505,
            config: config,
            authController: auth,
            isHost: true,
            hostUserId: 10,
            matchService: service,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Tombol bertuliskan "Terkunci" dan disabled
      expect(find.text('Terkunci'), findsOneWidget);
      expect(find.text('Acak Ulang'), findsNothing);

      service.saveDrawingCalled = false;
      final lockedBtn = find.widgetWithText(OutlinedButton, 'Terkunci');
      final outlinedBtn = tester.widget<OutlinedButton>(lockedBtn);
      expect(outlinedBtn.onPressed, isNull); // Disabled!
      expect(service.saveDrawingCalled, isFalse);
    });
  });
}
