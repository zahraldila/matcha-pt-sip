import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/auth/data/datasource/auth_remote_data_source.dart';
import 'package:matcha_app/features/auth/domain/models/user_model.dart';
import 'package:matcha_app/features/auth/presentation/controllers/auth_controller.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/match/data/match_service.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';

class FakeAuthDataSource extends Fake implements AuthRemoteDataSource {}

class TestAuthController extends AuthController {
  UserModel? _mockUser;
  bool _mockIsLoading = false;

  TestAuthController({UserModel? initialUser, bool initialLoading = false})
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

  void setLoading(bool loading) {
    _mockIsLoading = loading;
    notifyListeners();
  }
}

class TestMatchService extends MatchService {
  final Map<String, dynamic>? sessionDataToReturn;
  final bool shouldThrow;
  final Completer<Map<String, dynamic>?>? sessionCompleter;

  TestMatchService({
    this.sessionDataToReturn,
    this.shouldThrow = false,
    this.sessionCompleter,
  });

  @override
  Future<Map<String, dynamic>?> getSession(dynamic sessionId) async {
    if (sessionCompleter != null) {
      return sessionCompleter!.future;
    }
    if (shouldThrow) {
      throw Exception('Network connection error');
    }
    return sessionDataToReturn;
  }

  @override
  Future<List<Map<String, dynamic>>> getMatchesForSession(dynamic sessionId) async {
    return [];
  }
}

void main() {
  group('MatchScoringPage Host Access & Verification Tests', () {
    testWidgets('1. Host session opens scoring: displays Host badge and point buttons are enabled', (tester) async {
      final hostUser = const UserModel(
        userId: 10,
        nama: 'Host Budi',
        email: 'budi@matcha.id',
        isHost: true,
      );
      final auth = TestAuthController(initialUser: hostUser);

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            sessionId: 101,
            hostUserId: 10,
            authController: auth,
            matchService: TestMatchService(
              sessionDataToReturn: {
                'session_id': 101,
                'host_user_id': 10,
                'nama_session': 'Mabar Padel Host',
              },
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Host banner & indicators
      expect(find.text('Mode Host: Akses penuh untuk input skor dan kelola match'), findsOneWidget);
      expect(find.text('Mode Host'), findsOneWidget);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
      expect(find.text('+ Tambah Poin Team B'), findsOneWidget);
      expect(find.text('Deklarasi Walkover (WO)'), findsOneWidget);
    });

    testWidgets('2. Normal player opens the same session: locked to spectator mode without scoring buttons', (tester) async {
      final playerUser = const UserModel(
        userId: 99,
        nama: 'Player Anton',
        email: 'anton@matcha.id',
      );
      final auth = TestAuthController(initialUser: playerUser);

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            sessionId: 101,
            hostUserId: 10,
            authController: auth,
            matchService: TestMatchService(
              sessionDataToReturn: {
                'session_id': 101,
                'host_user_id': 10,
              },
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Spectator banner & indicators
      expect(find.text('Mode Penonton: Skor diperbarui otomatis via Realtime'), findsOneWidget);
      expect(find.text('Mode Penonton'), findsOneWidget);
      expect(find.text('Mode Penonton (Read-Only)'), findsNWidgets(2));
      expect(find.text('+ Tambah Poin Team A'), findsNothing);
      expect(find.text('+ Tambah Poin Team B'), findsNothing);
      expect(find.text('Deklarasi Walkover (WO)'), findsNothing);
    });

    testWidgets('3. Host opens scoring without widget.hostUserId: verifies from database and unlocks Host mode', (tester) async {
      final hostUser = const UserModel(
        userId: 10,
        nama: 'Host Budi',
        email: 'budi@matcha.id',
        isHost: true,
      );
      final auth = TestAuthController(initialUser: hostUser);
      final completer = Completer<Map<String, dynamic>?>();

      final matchService = TestMatchService(sessionCompleter: completer);

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            sessionId: 101,
            authController: auth,
            matchService: matchService,
          ),
        ),
      );
      await tester.pump();

      // While verifying, should NOT declare user as spectator
      expect(find.text('Memverifikasi hak akses host sesi...'), findsOneWidget);
      expect(find.text('Memverifikasi...'), findsNWidgets(2));
      expect(find.text('Mode Penonton (Read-Only)'), findsNothing);
      expect(find.text('+ Tambah Poin Team A'), findsNothing);

      // Now database finishes loading session data with host_user_id: 10
      completer.complete({
        'session_id': 101,
        'host_user_id': 10,
        'nama_session': 'Sesi Mabar Verified',
      });
      await tester.pumpAndSettle();

      // Access resolved: unlocked as Host!
      expect(find.text('Mode Host: Akses penuh untuk input skor dan kelola match'), findsOneWidget);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
      expect(find.text('+ Tambah Poin Team B'), findsOneWidget);
    });

    testWidgets('4. Late auth loading: UI updates access rights automatically when auth completes', (tester) async {
      final auth = TestAuthController(initialLoading: true);

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            sessionId: 101,
            hostUserId: 10,
            authController: auth,
            matchService: TestMatchService(
              sessionDataToReturn: {'session_id': 101, 'host_user_id': 10},
            ),
          ),
        ),
      );
      await tester.pump();

      // Auth is loading: shows verifying state
      expect(find.text('Memverifikasi hak akses host sesi...'), findsOneWidget);
      expect(find.text('Memverifikasi...'), findsNWidgets(2));

      // User session finishes loading late
      auth.setUser(const UserModel(
        userId: 10,
        nama: 'Host Budi',
        email: 'budi@matcha.id',
        isHost: true,
      ));
      await tester.pumpAndSettle();

      // UI re-evaluates: Host unlocked
      expect(find.text('Mode Host: Akses penuh untuk input skor dan kelola match'), findsOneWidget);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
    });

    testWidgets('5. Host on completed match: controls stay locked with status indicator', (tester) async {
      final hostUser = const UserModel(
        userId: 10,
        nama: 'Host Budi',
        email: 'budi@matcha.id',
        isHost: true,
      );
      final auth = TestAuthController(initialUser: hostUser);

      final completedMatch = DrawingMatch(
        courtNumber: 1,
        teamA: const [GamePlayerItem(id: '1', name: 'Aku')],
        teamB: const [GamePlayerItem(id: '2', name: 'Kamu')],
        status: 'Completed',
        scoreA: 3,
        scoreB: 1,
        winnerTeam: 'Team A',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            sessionId: 101,
            hostUserId: 10,
            authController: auth,
            rounds: [DrawingRound(roundNumber: 1, matches: [completedMatch])],
            matchService: TestMatchService(
              sessionDataToReturn: {'session_id': 101, 'host_user_id': 10},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Host badge is displayed
      expect(find.text('Mode Host: Akses penuh untuk input skor dan kelola match'), findsOneWidget);

      // Scoring buttons are locked with '🔒 Skor Terkunci (Selesai)' and disabled
      final lockedBtns = find.text('🔒 Skor Terkunci (Selesai)');
      expect(lockedBtns, findsNWidgets(2));

      // Walkover is not available for completed matches
      expect(find.text('Deklarasi Walkover (WO)'), findsNothing);
    });

    testWidgets('6. Admin policy: Admin is verified as Host and can input scores', (tester) async {
      final adminUser = const UserModel(
        userId: 999,
        nama: 'Super Admin',
        email: 'admin@matcha.id',
        role: 'admin',
      );
      final auth = TestAuthController(initialUser: adminUser);

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            sessionId: 101,
            hostUserId: 10, // Different from admin userId 999
            authController: auth,
            matchService: TestMatchService(
              sessionDataToReturn: {'session_id': 101, 'host_user_id': 10},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Admin has full Host control
      expect(find.text('Mode Host: Akses penuh untuk input skor dan kelola match'), findsOneWidget);
      expect(find.text('+ Tambah Poin Team A'), findsOneWidget);
      expect(find.text('+ Tambah Poin Team B'), findsOneWidget);
    });

    testWidgets('7. Verification error: shows error banner and locked controls without assuming spectator', (tester) async {
      final hostUser = const UserModel(
        userId: 10,
        nama: 'Host Budi',
        email: 'budi@matcha.id',
      );
      final auth = TestAuthController(initialUser: hostUser);

      await tester.pumpWidget(
        MaterialApp(
          home: MatchScoringPage(
            sessionId: 101,
            authController: auth,
            matchService: TestMatchService(shouldThrow: true),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Error state displayed, not spectator
      expect(find.text('Gagal memverifikasi identitas host sesi. Kontrol skor dikunci.'), findsOneWidget);
      expect(find.text('Verifikasi Gagal'), findsNWidgets(2));
      expect(find.text('Mode Penonton (Read-Only)'), findsNothing);
    });
  });
}
