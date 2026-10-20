import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/core/data/mock_data_service.dart';

void main() {
  group('MockDataService Unit Test', () {
    late MockDataService dataService;

    setUp(() {
      dataService = MockDataService();
    });

    test('Initial user and sessions are populated', () {
      expect(dataService.currentUser.name, 'Marcel Santoso');
      expect(dataService.sessions.isNotEmpty, true);
      expect(dataService.upcomingSessions.isNotEmpty, true);
    });

    test('Score addition and undo test', () {
      final initialScoreA = dataService.teamAPoints;
      dataService.addPointTeamA();
      expect(dataService.teamAPoints, initialScoreA + 1);

      dataService.undoLastPoint();
      expect(dataService.teamAPoints, initialScoreA);
    });

    test('Join and Leave session toggle test', () {
      final session = dataService.sessions.first;
      final initialParticipants = session.participants.length;

      // Current user is already in session 01, so calling joinSession should toggle/leave
      dataService.joinSession(session.id);
      final updatedSession = dataService.sessions.firstWhere((s) => s.id == session.id);
      expect(updatedSession.participants.length, initialParticipants - 1);
    });

    test('Kudos toggle reaction test', () {
      final initialSmash = dataService.kudosCounts['smash'] ?? 0;
      dataService.toggleKudos('smash');
      expect(dataService.kudosCounts['smash'], initialSmash + 1);
      expect(dataService.userGivenKudos.contains('smash'), true);

      // Untoggle
      dataService.toggleKudos('smash');
      expect(dataService.kudosCounts['smash'], initialSmash);
    });
  });
}
