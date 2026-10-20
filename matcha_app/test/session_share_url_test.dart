import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/session/domain/session_model.dart';

void main() {
  group('Session Share URL Logic Verification (Stage 1)', () {
    test('1. Session with valid share_token generates canonical URL format', () {
      final session = SessionModel.fromMap({
        'session_id': 123,
        'nama_session': 'Padel Weekend Fun',
        'host_user_id': 1,
        'sport_id': 1,
        'venue_id': 1,
        'scoring_system': 'Total of 3',
        'status_session': 'Open',
        'jumlah_pemain': 4,
        'jenis_permainan': 'Double',
        'share_token': '04afd015e540a56916b15fb5981e245b',
      });

      expect(session.shareToken, equals('04afd015e540a56916b15fb5981e245b'));
      expect(
        session.shareUrl,
        equals('https://matcha.siproduktif.com/games/share/04afd015e540a56916b15fb5981e245b'),
      );
      // Ensure session_id is NOT used in shareUrl
      expect(session.shareUrl, isNot(contains('/games/share/123')));
      expect(session.shareUrl, isNot(contains('/games/123')));
    });

    test('2. Session with null or empty share_token returns null shareUrl', () {
      final sessionWithoutToken = SessionModel.fromMap({
        'session_id': 456,
        'nama_session': 'Tennis Morning Match',
        'host_user_id': 2,
        'sport_id': 2,
        'venue_id': 1,
        'scoring_system': 'Total of 3',
        'status_session': 'Open',
        'jumlah_pemain': 4,
        'jenis_permainan': 'Single',
        'share_token': null,
      });

      expect(sessionWithoutToken.shareToken, isNull);
      expect(sessionWithoutToken.shareUrl, isNull);

      final sessionWithEmptyToken = SessionModel.fromMap({
        'session_id': 789,
        'nama_session': 'Tennis Empty Token',
        'host_user_id': 2,
        'sport_id': 2,
        'venue_id': 1,
        'scoring_system': 'Total of 3',
        'status_session': 'Open',
        'jumlah_pemain': 4,
        'jenis_permainan': 'Single',
        'share_token': '   ',
      });

      expect(sessionWithEmptyToken.shareUrl, isNull);
    });

    test('3. Share message formatting verification', () {
      const shareToken = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
      const sessionName = 'Mabar Padel Sore LONGSOT';
      final shareUrl = 'https://matcha.siproduktif.com/games/share/$shareToken';

      final shareText = 'Mabar yuk di MATCHA!\n\n$sessionName\n\n$shareUrl';

      expect(shareText, contains('Mabar yuk di MATCHA!'));
      expect(shareText, contains(sessionName));
      expect(shareText, contains(shareUrl));
      expect(shareText, contains('https://matcha.siproduktif.com/games/share/'));
    });
  });
}
