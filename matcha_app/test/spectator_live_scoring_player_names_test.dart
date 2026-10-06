import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/auth/data/datasource/auth_remote_data_source.dart';
import 'package:matcha_app/features/auth/domain/models/user_model.dart';
import 'package:matcha_app/features/auth/presentation/controllers/auth_controller.dart';
import 'package:matcha_app/features/match/data/match_service.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

class FakeAuthDataSource extends Fake implements AuthRemoteDataSource {}

class MockAuthController extends AuthController {
  final UserModel? _mockUser;
  MockAuthController({UserModel? user})
      : _mockUser = user,
        super(authDataSource: FakeAuthDataSource());

  @override
  UserModel? get currentUser => _mockUser;

  @override
  bool get isLoading => false;
}

class MockMatchService extends MatchService {
  final Map<String, dynamic>? sessionData;
  final List<Map<String, dynamic>> Function()? matchesProvider;
  final bool shouldThrowRoster;
  final StreamController<void> realtimeController = StreamController<void>.broadcast();

  MockMatchService({
    this.sessionData,
    this.matchesProvider,
    this.shouldThrowRoster = false,
  });

  @override
  Future<Map<String, dynamic>?> getSession(dynamic sessionId) async {
    return sessionData ??
        {
          'session_id': sessionId,
          'host_user_id': 1,
          'nama_session': 'Tournament Live Mabar',
          'scoring_system': 'Standard 30-Point Tiebreaker',
          'status_session': 'In Progress',
        };
  }

  @override
  Future<List<Map<String, dynamic>>> getMatchesForSession(dynamic sessionId) async {
    if (shouldThrowRoster) {
      throw Exception('Database participant query failed');
    }
    if (matchesProvider != null) {
      return matchesProvider!();
    }
    return [];
  }

  @override
  RealtimeChannel subscribeLiveSession({
    dynamic sessionId,
    required void Function() onDataChanged,
    void Function(Map<String, dynamic> payload)? onRoundAdvanced,
    void Function(Map<String, dynamic> payload)? onSessionFinished,
  }) {
    realtimeController.stream.listen((_) => onDataChanged());
    return FakeRealtimeChannel();
  }

  @override
  Future<void> unsubscribe(RealtimeChannel? channel) async {}
}

class FakeRealtimeChannel extends Fake implements RealtimeChannel {}

void main() {
  group('Spectator Live Scoring Player Names Verification Tests', () {
    testWidgets(
      '1. Spectator sees identical player names from DB (Single: Ahmad Subarjo vs Hilmy Ram Fahreza), NEVER Side A / Side B',
      (tester) async {
        final mockService = MockMatchService(
          matchesProvider: () => [
            {
              'matchId': 865,
              'courtNumber': 1,
              'roundNumber': 1,
              'courtName': 'Court 1',
              'sideA': 'Ahmad Subarjo',
              'sideB': 'Hilmy Ram Fahreza',
              'teamAPlayers': [
                {
                  'id': '419',
                  'playerId': 419,
                  'userId': 4,
                  'name': 'Ahmad Subarjo',
                  'level': 'Intermediate',
                }
              ],
              'teamBPlayers': [
                {
                  'id': '423',
                  'playerId': 423,
                  'userId': 11,
                  'name': 'Hilmy Ram Fahreza',
                  'level': 'Intermediate',
                }
              ],
              'scoreA': 0,
              'scoreB': 0,
              'pointScoreA': '0',
              'pointScoreB': '0',
              'version': 1,
              'status': 'In Progress',
            }
          ],
        );

        final spectatorAuth = MockAuthController(
          user: UserModel(userId: 99, email: 'spectator@matcha.id', nama: 'Spectator User'),
        );

        await tester.pumpWidget(
          MaterialApp(
            home: MatchScoringPage(
              sessionId: 100,
              isHost: false,
              hostUserId: 1,
              authController: spectatorAuth,
              matchService: mockService,
              allowFallbackRounds: false,
            ),
          ),
        );

        await tester.pumpAndSettle();

        // Must display exact names
        expect(find.textContaining('Ahmad Subarjo'), findsWidgets);
        expect(find.textContaining('Hilmy Ram Fahreza'), findsWidgets);

        // Must NOT display generic placeholders
        expect(find.text('Side A'), findsNothing);
        expect(find.text('Side B'), findsNothing);

        // Spectator mode: action buttons must be disabled / locked
        final addPointButtons = find.textContaining('+ Tambah Poin');
        expect(addPointButtons, findsNothing);
      },
    );

    testWidgets(
      '2. Spectator sees all players in Double match (Ahmad Subarjo & Budi Santoso vs Hilmy Ram Fahreza & Chandra)',
      (tester) async {
        final mockService = MockMatchService(
          matchesProvider: () => [
            {
              'matchId': 866,
              'courtNumber': 1,
              'roundNumber': 1,
              'courtName': 'Court 1',
              'sideA': 'Ahmad Subarjo · Budi Santoso',
              'sideB': 'Hilmy Ram Fahreza · Chandra Wijaya',
              'teamAPlayers': [
                {'id': '419', 'playerId': 419, 'name': 'Ahmad Subarjo'},
                {'id': '420', 'playerId': 420, 'name': 'Budi Santoso'},
              ],
              'teamBPlayers': [
                {'id': '423', 'playerId': 423, 'name': 'Hilmy Ram Fahreza'},
                {'id': '424', 'playerId': 424, 'name': 'Chandra Wijaya'},
              ],
              'scoreA': 3,
              'scoreB': 2,
              'pointScoreA': '30',
              'pointScoreB': '15',
              'version': 1,
              'status': 'In Progress',
            }
          ],
        );

        final spectatorAuth = MockAuthController(
          user: UserModel(userId: 99, email: 'spectator@matcha.id', nama: 'Spectator User'),
        );

        await tester.pumpWidget(
          MaterialApp(
            home: MatchScoringPage(
              sessionId: 100,
              isHost: false,
              hostUserId: 1,
              authController: spectatorAuth,
              matchService: mockService,
              allowFallbackRounds: false,
            ),
          ),
        );

        await tester.pumpAndSettle();

        // Check both players in Team A are visible
        expect(find.textContaining('Ahmad Subarjo & Budi Santoso'), findsWidgets);
        // Check both players in Team B are visible
        expect(find.textContaining('Hilmy Ram Fahreza & Chandra Wijaya'), findsWidgets);
      },
    );

    testWidgets(
      '3. Realtime score update retains players and does not overwrite with empty roster',
      (tester) async {
        int callCount = 0;
        final mockService = MockMatchService(
          matchesProvider: () {
            callCount++;
            return [
              {
                'matchId': 865,
                'courtNumber': 1,
                'roundNumber': 1,
                'courtName': 'Court 1',
                'sideA': 'Ahmad Subarjo',
                'sideB': 'Hilmy Ram Fahreza',
                // First call has teamAPlayers, subsequent realtime payload might only carry updated score
                'teamAPlayers': callCount == 1
                    ? [{'id': '419', 'playerId': 419, 'name': 'Ahmad Subarjo'}]
                    : <Map<String, dynamic>>[],
                'teamBPlayers': callCount == 1
                    ? [{'id': '423', 'playerId': 423, 'name': 'Hilmy Ram Fahreza'}]
                    : <Map<String, dynamic>>[],
                'scoreA': callCount == 1 ? 2 : 5,
                'scoreB': callCount == 1 ? 1 : 4,
                'pointScoreA': callCount == 1 ? '15' : '40',
                'pointScoreB': callCount == 1 ? '15' : '30',
                'version': callCount,
                'status': 'In Progress',
              }
            ];
          },
        );

        final spectatorAuth = MockAuthController(
          user: UserModel(userId: 99, email: 'spectator@matcha.id', nama: 'Spectator User'),
        );

        await tester.pumpWidget(
          MaterialApp(
            home: MatchScoringPage(
              sessionId: 100,
              isHost: false,
              hostUserId: 1,
              authController: spectatorAuth,
              matchService: mockService,
              allowFallbackRounds: false,
            ),
          ),
        );

        await tester.pumpAndSettle();

        // Initially displays players
        expect(find.textContaining('Ahmad Subarjo'), findsWidgets);
        expect(find.textContaining('Hilmy Ram Fahreza'), findsWidgets);

        // Trigger realtime score update
        mockService.realtimeController.add(null);
        await tester.pumpAndSettle();

        // Must still retain players and NOT revert to Side A or empty
        expect(find.textContaining('Ahmad Subarjo'), findsWidgets);
        expect(find.textContaining('Hilmy Ram Fahreza'), findsWidgets);
        expect(find.text('Side A'), findsNothing);
        expect(find.text('Side B'), findsNothing);
      },
    );

    testWidgets(
      '4. If roster fails to load from DB in a real session, displays error and retry option, NEVER masks as Side A/B',
      (tester) async {
        bool shouldFail = true;
        final mockService = MockMatchService(
          matchesProvider: () {
            if (shouldFail) {
              // Simulates match returned without valid participant roster
              return [
                {
                  'matchId': 865,
                  'courtNumber': 1,
                  'roundNumber': 1,
                  'courtName': 'Court 1',
                  'sideA': '',
                  'sideB': '',
                  'teamAPlayers': <Map<String, dynamic>>[],
                  'teamBPlayers': <Map<String, dynamic>>[],
                  'scoreA': 0,
                  'scoreB': 0,
                  'version': 1,
                  'status': 'In Progress',
                }
              ];
            } else {
              return [
                {
                  'matchId': 865,
                  'courtNumber': 1,
                  'roundNumber': 1,
                  'courtName': 'Court 1',
                  'sideA': 'Ahmad Subarjo',
                  'sideB': 'Hilmy Ram Fahreza',
                  'teamAPlayers': [{'id': '419', 'playerId': 419, 'name': 'Ahmad Subarjo'}],
                  'teamBPlayers': [{'id': '423', 'playerId': 423, 'name': 'Hilmy Ram Fahreza'}],
                  'scoreA': 0,
                  'scoreB': 0,
                  'version': 1,
                  'status': 'In Progress',
                }
              ];
            }
          },
        );

        final spectatorAuth = MockAuthController(
          user: UserModel(userId: 99, email: 'spectator@matcha.id', nama: 'Spectator User'),
        );

        await tester.pumpWidget(
          MaterialApp(
            home: MatchScoringPage(
              sessionId: 100,
              isHost: false,
              hostUserId: 1,
              authController: spectatorAuth,
              matchService: mockService,
              allowFallbackRounds: false,
            ),
          ),
        );

        await tester.pumpAndSettle();

        // Must display error screen with Coba Lagi
        expect(find.text('Gagal Memuat Sesi Pertandingan'), findsOneWidget);
        expect(find.text('Coba Lagi'), findsOneWidget);
        expect(find.text('Side A'), findsNothing);
        expect(find.text('Side B'), findsNothing);

        // User taps "Coba Lagi" after server recovery
        shouldFail = false;
        await tester.tap(find.text('Coba Lagi'));
        await tester.pumpAndSettle();

        // Now player names load properly
        expect(find.textContaining('Ahmad Subarjo'), findsWidgets);
        expect(find.textContaining('Hilmy Ram Fahreza'), findsWidgets);
      },
    );
  });
}
