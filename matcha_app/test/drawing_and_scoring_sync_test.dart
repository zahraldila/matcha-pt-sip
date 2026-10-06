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
import 'package:matcha_app/features/match/domain/models/match_model.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';

class FakeAuthDataSource extends Fake implements AuthRemoteDataSource {}

class MockAuthController extends AuthController {
  UserModel? _mockUser;
  bool _mockIsLoading = false;

  MockAuthController({UserModel? initialUser, bool initialLoading = false})
      : super(authDataSource: FakeAuthDataSource()) {
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

class MockMatchServiceForSync extends MatchService {
  Map<String, dynamic>? sessionData;
  List<Map<String, dynamic>> matchesData = [];
  List<DrawingRound> savedDrawing = [];
  bool isDrawingLockedResult = false;
  bool saveDrawingCalled = false;
  bool broadcastCalled = false;
  bool lockCalled = false;

  MockMatchServiceForSync({
    this.sessionData,
    List<Map<String, dynamic>>? matches,
    List<DrawingRound>? drawing,
    this.isDrawingLockedResult = false,
  }) {
    if (matches != null) matchesData = matches;
    if (drawing != null) savedDrawing = drawing;
  }

  @override
  Future<Map<String, dynamic>?> getSession(dynamic sessionId) async {
    return sessionData;
  }

  @override
  Future<List<Map<String, dynamic>>> getMatchesForSession(dynamic sessionId) async {
    return matchesData;
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

  String? lastSavedMatchStatus;
  int saveCallCount = 0;
  bool shouldFailSave = false;

  @override
  Future<List<DrawingRound>> saveDrawingMatches({
    required dynamic sessionId,
    required List<DrawingRound> rounds,
    List<GamePlayerItem>? allPlayers,
    int? courtCount,
    String? matchStatus,
  }) async {
    if (shouldFailSave) {
      throw Exception('PostgrestException: Simpan gagal');
    }
    saveDrawingCalled = true;
    saveCallCount++;
    lastSavedMatchStatus = matchStatus;
    savedDrawing = rounds;
    return rounds;
  }

  @override
  Future<void> broadcastDrawingUpdate(dynamic sessionId) async {
    broadcastCalled = true;
  }

  bool shouldFailLock = false;

  @override
  Future<List<DrawingRound>> lockDrawingSession(
    dynamic sessionId, {
    required List<DrawingRound> rounds,
    List<GamePlayerItem>? allPlayers,
  }) async {
    if (shouldFailLock) {
      throw Exception('Database connection failed');
    }
    lockCalled = true;
    isDrawingLockedResult = true;
    savedDrawing = rounds;
    return rounds;
  }

  void Function()? onDrawingChangedCallback;
  void Function()? onDrawingLockedCallback;

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
  Future<void> unsubscribe(RealtimeChannel? channel) async {}
}

void main() {
  const hostUser = UserModel(
    userId: 10,
    email: 'host@matcha.com',
    nama: 'Host Marcello',
    isHost: true,
  );

  const spectatorUser = UserModel(
    userId: 20,
    email: 'spectator@matcha.com',
    nama: 'Penonton Ahmad',
    isHost: false,
  );

  group('1. Drawing Rights and Realtime Broadcast Tests', () {
    testWidgets('Host sees Acak Ulang and Kunci Tim & Mulai Scoring Live', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: hostUser);
      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 100,
          'host_user_id': 10,
          'status_session': 'In Progress',
        },
      );

      final config = GameWizardConfig(
        sessionId: 100,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Marcello Este Camaro'),
          const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 100,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Host must see Acak Ulang
      expect(find.text('Acak Ulang'), findsOneWidget);
      // Host must see Kunci Tim & Mulai Scoring Live
      expect(find.text('Kunci Tim & Mulai Scoring Live'), findsOneWidget);

      // Tap Acak Ulang: triggers save & broadcast
      await tester.tap(find.text('Acak Ulang'));
      await tester.pumpAndSettle();

      expect(matchService.saveDrawingCalled, isTrue);
      expect(matchService.broadcastCalled, isTrue);
    });

    testWidgets('Non-Host cannot see Acak Ulang, sees Penonton badge, no scoring button before lock, and Lihat Live Scoring once locked', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: spectatorUser);
      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 100,
          'host_user_id': 10,
          'status_session': 'In Progress',
        },
        drawing: [
          DrawingRound(
            roundNumber: 1,
            matches: [
              DrawingMatch(
                courtNumber: 1,
                teamA: [const GamePlayerItem(id: '1', name: 'Marcello Este Camaro')],
                teamB: [const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza')],
              ),
            ],
          ),
        ],
        isDrawingLockedResult: false,
      );

      final config = GameWizardConfig(
        sessionId: 100,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Marcello Este Camaro'),
          const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 100,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Non-host must NOT see Acak Ulang or Kunci Tim
      expect(find.text('Acak Ulang'), findsNothing);
      expect(find.text('Kunci Tim & Mulai Scoring Live'), findsNothing);
      // Non-host must see Penonton badge
      expect(find.text('Penonton'), findsOneWidget);
      // Before lock, spectator cannot see scoring button, sees waiting card
      expect(find.text('Lihat Live Scoring'), findsNothing);
      expect(find.text('Menunggu Host Mengunci Drawing & Memulai Scoring'), findsOneWidget);

      // Now simulate Host locking drawing via realtime
      matchService.isDrawingLockedResult = true;
      matchService.onDrawingLockedCallback?.call();
      await tester.pumpAndSettle();

      // Once locked, spectator gets 'Lihat Live Scoring' button
      expect(find.text('Lihat Live Scoring'), findsOneWidget);
    });
  });

  group('2. Scoring Single 1v1 and No-Dummy Tests', () {
    testWidgets('Real session restores Single 1v1 without Aku/Kamu/Dia/Kita dummy', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: hostUser);
      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 200,
          'host_user_id': 10,
          'status_session': 'Live',
          'nama_session': 'Mabar Single Tournament',
          'jenis_permainan': 'Single',
          'sport': 'Padel',
          'scoring_system': 'First to 21 Poin (Tuntas)',
        },
        matches: [
          {
            'matchId': 501,
            'courtId': 1,
            'nomorMatch': 1,
            'courtName': 'Court 1',
            'sideA': 'Marcello Este Camaro',
            'sideB': 'Hilmy Ram Fahreza',
            'teamAPlayers': [
              {
                'id': '101',
                'playerId': 101,
                'name': 'Marcello Este Camaro',
                'level': 'Intermediate',
              },
            ],
            'teamBPlayers': [
              {
                'id': '102',
                'playerId': 102,
                'name': 'Hilmy Ram Fahreza',
                'level': 'Advanced',
              },
            ],
            'scoreA': 5,
            'scoreB': 3,
            'pointScoreA': '5',
            'pointScoreB': '3',
            'status': 'In Progress',
            'version': 1,
            'nomorMatch': 1,
            'roundNumber': 1,
            'courtNumber': 1,
            'rawMatch': {'nomor_match': 1, 'round_number': 1, 'court_id': 1},
          },
        ],
      );

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            sessionId: 200,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Ensure no dummy names exist on UI
      expect(find.text('Aku'), findsNothing);
      expect(find.text('Kamu'), findsNothing);
      expect(find.text('Dia'), findsNothing);
      expect(find.text('Kita'), findsNothing);

      // Verify real players are rendered
      expect(find.text('Marcello Este Camaro'), findsWidgets);
      expect(find.text('Hilmy Ram Fahreza'), findsWidgets);
    });

    testWidgets('Empty session matches show Drawing Belum Dikunci instead of dummy scores', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: spectatorUser);
      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 300,
          'host_user_id': 10,
          'status_session': 'In Progress',
        },
        matches: [],
        drawing: [],
      );

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            sessionId: 300,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            allowFallbackRounds: false,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Must NOT display dummy scores or dummy players
      expect(find.text('Aku'), findsNothing);
      expect(find.text('Kamu'), findsNothing);

      // Must display informative state
      expect(find.text('Drawing Belum Dikunci'), findsOneWidget);
      expect(find.text('Lihat Drawing Tim'), findsOneWidget);
    });
  });

  group('3. Host Start Scoring and Spectator Realtime Sync Tests', () {
    testWidgets('Host starts scoring: awaits lockDrawingSession and navigates to MatchScoringPage', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: hostUser);
      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 100,
          'host_user_id': 10,
          'status_session': 'Scheduled',
        },
      );

      final config = GameWizardConfig(
        sessionId: 100,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Marcello Este Camaro'),
          const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 100,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Kunci Tim & Mulai Scoring Live'), findsOneWidget);

      await tester.tap(find.text('Kunci Tim & Mulai Scoring Live'));
      await tester.pumpAndSettle();

      // lockDrawingSession must have been called
      expect(matchService.lockCalled, isTrue);
      // Host must now be on MatchScoringPage
      expect(find.byType(MatchScoringPage), findsOneWidget);
    });

    testWidgets('Save failure prevents Host from navigating to scoring and shows error', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: hostUser);
      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 100,
          'host_user_id': 10,
          'status_session': 'Scheduled',
        },
      )..shouldFailLock = true;

      final config = GameWizardConfig(
        sessionId: 100,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Marcello Este Camaro'),
          const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 100,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Kunci Tim & Mulai Scoring Live'));
      await tester.pumpAndSettle();

      // Must NOT navigate to MatchScoringPage
      expect(find.byType(MatchScoringPage), findsNothing);
      expect(find.byType(DrawingResultPage), findsOneWidget);

      // Must show error message
      expect(find.textContaining('Gagal mengunci drawing'), findsOneWidget);
    });

    testWidgets('Spectator entering after match start recovers state on initial load', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: spectatorUser);
      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 100,
          'host_user_id': 10,
          'status_session': 'In Progress',
        },
        isDrawingLockedResult: true,
        drawing: [
          DrawingRound(
            roundNumber: 1,
            matches: [
              DrawingMatch(
                courtNumber: 1,
                teamA: [const GamePlayerItem(id: '1', name: 'Marcello Este Camaro')],
                teamB: [const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza')],
              ),
            ],
          ),
        ],
      );

      final config = GameWizardConfig(
        sessionId: 100,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Marcello Este Camaro'),
          const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 100,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Non-host spectator automatically gets access to scoring and does not see host action buttons
      expect(find.text('Acak Ulang'), findsNothing);
      expect(find.text('Kunci Tim & Mulai Scoring Live'), findsNothing);
    });

    testWidgets('Spectator viewing empty drawing automatically updates when Host starts match', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: spectatorUser);
      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 100,
          'host_user_id': 10,
          'status_session': 'Scheduled',
        },
        isDrawingLockedResult: false,
        drawing: [],
      );

      final config = GameWizardConfig(
        sessionId: 100,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Marcello Este Camaro'),
          const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 100,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Before start, spectator sees drawing empty message
      expect(find.text('Drawing Belum Tersedia'), findsOneWidget);

      // Now Host starts match: DB updates and realtime event fires
      matchService.isDrawingLockedResult = true;
      matchService.savedDrawing = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Marcello Este Camaro')],
              teamB: [const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza')],
            ),
          ],
        ),
      ];

      // Simulate Realtime broadcast event received by spectator
      matchService.onDrawingLockedCallback?.call();
      await tester.pumpAndSettle();

      // Spectator must automatically recover from "Drawing Belum Tersedia"
      expect(find.text('Drawing Belum Tersedia'), findsNothing);
    });
  });

  group('4. Multi-client Preview, Realtime Reshuffle, and Lock Validation Tests', () {
    testWidgets('1. Player can see preview drawing before Host locks with matching pairings', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: spectatorUser);
      final previewRounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              matchId: 101,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Marcello Este Camaro')],
              teamB: [const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza')],
            ),
          ],
        ),
      ];

      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 300,
          'host_user_id': 10,
          'status_session': 'Scheduled',
        },
        drawing: previewRounds,
        isDrawingLockedResult: false,
      );

      final config = GameWizardConfig(
        sessionId: 300,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Marcello Este Camaro'),
          const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 300,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Spectator can see preview drawing without error
      expect(find.text('Drawing Belum Tersedia'), findsNothing);
      expect(find.text('Marcello Este Camaro'), findsWidgets);
      expect(find.text('Hilmy Ram Fahreza'), findsWidgets);

      // Status indicator reflects preview
      expect(find.text('Preview'), findsOneWidget);
      expect(find.text('Penonton'), findsOneWidget);

      // Spectator cannot see host-only buttons
      expect(find.text('Acak Ulang'), findsNothing);
      expect(find.text('Kunci Tim & Mulai Scoring Live'), findsNothing);
    });

    testWidgets('2. Host Acak Ulang updates Player preview in realtime without page reload', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: spectatorUser);
      final initialRounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              matchId: 201,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Marcello Este Camaro')],
              teamB: [const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza')],
            ),
          ],
        ),
      ];

      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 301,
          'host_user_id': 10,
          'status_session': 'Scheduled',
        },
        drawing: initialRounds,
        isDrawingLockedResult: false,
      );

      final config = GameWizardConfig(
        sessionId: 301,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Marcello Este Camaro'),
          const GamePlayerItem(id: '2', name: 'Hilmy Ram Fahreza'),
          const GamePlayerItem(id: '3', name: 'Budi Santoso'),
          const GamePlayerItem(id: '4', name: 'Citra Dewi'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      // Player opens the drawing page
      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 301,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Marcello Este Camaro'), findsWidgets);
      expect(find.text('Budi Santoso'), findsNothing);

      // Host reshuffles and updates the server drawing
      final reshuffledRounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              matchId: 202,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '3', name: 'Budi Santoso')],
              teamB: [const GamePlayerItem(id: '4', name: 'Citra Dewi')],
            ),
          ],
        ),
      ];
      matchService.savedDrawing = reshuffledRounds;

      // Realtime event drawing_updated triggers
      matchService.onDrawingChangedCallback?.call();
      await tester.pumpAndSettle();

      // Player UI now reflects the reshuffled preview without manual refresh
      expect(find.text('Budi Santoso'), findsWidgets);
      expect(find.text('Citra Dewi'), findsWidgets);
      expect(find.text('Marcello Este Camaro'), findsNothing);
    });

    testWidgets('3. Late-joining player fetches latest preview via initial load', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: spectatorUser);
      // Host had already reshuffled earlier:
      final latestPreview = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              matchId: 305,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Player One')],
              teamB: [const GamePlayerItem(id: '2', name: 'Player Two')],
            ),
          ],
        ),
      ];

      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 302,
          'host_user_id': 10,
          'status_session': 'Scheduled',
        },
        drawing: latestPreview,
        isDrawingLockedResult: false,
      );

      final config = GameWizardConfig(
        sessionId: 302,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Player One'),
          const GamePlayerItem(id: '2', name: 'Player Two'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      // Player opens page late
      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 302,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Latest preview is directly visible
      expect(find.text('Player One'), findsWidgets);
      expect(find.text('Player Two'), findsWidgets);
      expect(find.text('Preview'), findsOneWidget);
    });

    testWidgets('4. Save failure on Acak Ulang reverts state and prevents false success notification', (tester) async {
      tester.view.physicalSize = const Size(1080, 2400);
      tester.view.devicePixelRatio = 2.0;
      addTearDown(tester.view.resetPhysicalSize);

      final auth = MockAuthController(initialUser: hostUser);
      final initialRounds = [
        DrawingRound(
          roundNumber: 1,
          matches: [
            DrawingMatch(
              matchId: 401,
              courtNumber: 1,
              teamA: [const GamePlayerItem(id: '1', name: 'Alpha Player')],
              teamB: [const GamePlayerItem(id: '2', name: 'Beta Player')],
            ),
          ],
        ),
      ];

      final matchService = MockMatchServiceForSync(
        sessionData: {
          'session_id': 303,
          'host_user_id': 10,
          'status_session': 'Scheduled',
        },
        drawing: initialRounds,
        isDrawingLockedResult: false,
      );

      final config = GameWizardConfig(
        sessionId: 303,
        hostUserId: 10,
        players: [
          const GamePlayerItem(id: '1', name: 'Alpha Player'),
          const GamePlayerItem(id: '2', name: 'Beta Player'),
          const GamePlayerItem(id: '3', name: 'Gamma Player'),
          const GamePlayerItem(id: '4', name: 'Delta Player'),
        ],
        courtCount: 1,
        playMode: 'Single',
        gameType: 'Americano',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: DrawingResultPage(
            sessionId: 303,
            hostUserId: 10,
            authController: auth,
            matchService: matchService,
            config: config,
            initialRounds: initialRounds,
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Simulate DB error when Host reshuffles
      matchService.shouldFailSave = true;

      // Host taps Acak Ulang
      await tester.tap(find.text('Acak Ulang'));
      await tester.pumpAndSettle();

      // Error message is displayed to Host
      expect(find.textContaining('Gagal menyimpan hasil acak ke server'), findsOneWidget);

      // Success message must NOT be shown
      expect(find.textContaining('berhasil diacak ulang'), findsNothing);

      // State is reverted to original initial rounds
      expect(find.text('Alpha Player'), findsWidgets);
      expect(find.text('Beta Player'), findsWidgets);
    });

    test('5. Explicit mapping between Dart roundNumber and database column nomor_match (No round_number in tb_match)', () {
      // Web parity formula: nomorMatch = (roundNumber - 1) * courtCount + courtNumber
      const courtCount = 2;

      // 1. Forward mapping: Dart (roundNumber, courtNumber) -> Database nomor_match
      int calculateNomorMatch(int roundNumber, int courtNumber) {
        return (roundNumber - 1) * courtCount + courtNumber;
      }

      expect(calculateNomorMatch(1, 1), 1);
      expect(calculateNomorMatch(1, 2), 2);
      expect(calculateNomorMatch(2, 1), 3);
      expect(calculateNomorMatch(2, 2), 4);
      expect(calculateNomorMatch(3, 1), 5);
      expect(calculateNomorMatch(3, 2), 6);

      // 2. Reverse mapping: Database nomor_match -> Dart (roundNumber, courtNumber)
      int calculateRoundNumber(int nomorMatch) {
        return ((nomorMatch - 1) ~/ courtCount) + 1;
      }
      int calculateCourtNumber(int nomorMatch) {
        return ((nomorMatch - 1) % courtCount) + 1;
      }

      expect(calculateRoundNumber(1), 1);
      expect(calculateCourtNumber(1), 1);

      expect(calculateRoundNumber(2), 1);
      expect(calculateCourtNumber(2), 2);

      expect(calculateRoundNumber(3), 2);
      expect(calculateCourtNumber(3), 1);

      expect(calculateRoundNumber(4), 2);
      expect(calculateCourtNumber(4), 2);

      expect(calculateRoundNumber(5), 3);
      expect(calculateCourtNumber(5), 1);

      expect(calculateRoundNumber(6), 3);
      expect(calculateCourtNumber(6), 2);

      // 3. MatchModel serialization does not send round_number or session_id to database
      final matchModel = MatchModel.fromJson({
        'match_id': 100,
        'drawing_id': 10,
        'nomor_match': 3,
        'status_match': 'In Progress',
      });

      expect(matchModel.roundNumber, 3); // Mapped from nomor_match fallback
      expect(matchModel.nomorMatch, 3);

      final jsonPayload = matchModel.toJson();
      expect(jsonPayload.containsKey('round_number'), isFalse,
          reason: 'tb_match does not have round_number column');
      expect(jsonPayload.containsKey('session_id'), isFalse,
          reason: 'tb_match does not have session_id column');
      expect(jsonPayload['nomor_match'], 3);
    });
  });
}
