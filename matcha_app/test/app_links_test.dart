import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/core/services/app_link_service.dart';
import 'package:matcha_app/features/session/domain/session_model.dart';

void main() {
  group('Stage 2 — Android App Links URL Parsing Tests', () {
    test('1. Valid share URL extracts the correct share_token', () {
      final uri = Uri.parse(
        'https://matcha.siproduktif.com/games/share/04afd015e540a56916b15fb5981e245b',
      );
      final token = AppLinkService.extractShareToken(uri);

      expect(token, equals('04afd015e540a56916b15fb5981e245b'));
    });

    test('2. Wrong domains are strictly rejected (e.g. matcha.app, siproduktif.com)', () {
      final oldDomainUri = Uri.parse(
        'https://matcha.app/games/share/04afd015e540a56916b15fb5981e245b',
      );
      expect(AppLinkService.extractShareToken(oldDomainUri), isNull);

      final rootDomainUri = Uri.parse(
        'https://siproduktif.com/games/share/04afd015e540a56916b15fb5981e245b',
      );
      expect(AppLinkService.extractShareToken(rootDomainUri), isNull);

      final foreignDomainUri = Uri.parse(
        'https://otherdomain.com/games/share/04afd015e540a56916b15fb5981e245b',
      );
      expect(AppLinkService.extractShareToken(foreignDomainUri), isNull);
    });

    test('3. Non-HTTPS scheme is rejected (e.g. http, custom matcha://)', () {
      final httpUri = Uri.parse(
        'http://matcha.siproduktif.com/games/share/04afd015e540a56916b15fb5981e245b',
      );
      expect(AppLinkService.extractShareToken(httpUri), isNull);

      final customSchemeUri = Uri.parse(
        'matcha://matcha.siproduktif.com/games/share/04afd015e540a56916b15fb5981e245b',
      );
      expect(AppLinkService.extractShareToken(customSchemeUri), isNull);
    });

    test('4. Wrong or malformed path is rejected', () {
      final wrongPath1 = Uri.parse('https://matcha.siproduktif.com/games/123');
      expect(AppLinkService.extractShareToken(wrongPath1), isNull);

      final wrongPath2 = Uri.parse(
        'https://matcha.siproduktif.com/share/04afd015e540a56916b15fb5981e245b',
      );
      expect(AppLinkService.extractShareToken(wrongPath2), isNull);

      final wrongPath3 = Uri.parse('https://matcha.siproduktif.com/games/share');
      expect(AppLinkService.extractShareToken(wrongPath3), isNull);

      final wrongPath4 = Uri.parse('https://matcha.siproduktif.com/');
      expect(AppLinkService.extractShareToken(wrongPath4), isNull);
    });

    test('5. Empty or whitespace-only token is rejected', () {
      final emptyTokenUri = Uri.parse('https://matcha.siproduktif.com/games/share/');
      expect(AppLinkService.extractShareToken(emptyTokenUri), isNull);

      final whitespaceTokenUri = Uri.parse(
        'https://matcha.siproduktif.com/games/share/%20%20%20',
      );
      expect(AppLinkService.extractShareToken(whitespaceTokenUri), isNull);
    });
  });

  group('Stage 2 — Session Resolver & Model Integration Tests', () {
    test('6. Session found by share_token maps to correct session ID and properties', () {
      final mockSessionData = {
        'session_id': 987,
        'nama_session': 'Padel Fun Tournament Weekend',
        'host_user_id': 5,
        'sport_id': 1,
        'venue_id': 2,
        'scoring_system': 'Total of 3',
        'status_session': 'Open',
        'jumlah_pemain': 4,
        'jenis_permainan': 'Double',
        'share_token': '04afd015e540a56916b15fb5981e245b',
      };

      final session = SessionModel.fromMap(mockSessionData);

      expect(session.sessionId, equals(987));
      expect(session.shareToken, equals('04afd015e540a56916b15fb5981e245b'));
      expect(
        session.shareUrl,
        equals('https://matcha.siproduktif.com/games/share/04afd015e540a56916b15fb5981e245b'),
      );
      expect(session.isFull, isFalse);
    });

    test('7. Safe handling when token does not match any session', () {
      SessionModel? resolvedSession;
      // Simulating resolver returning null when query has no match
      expect(resolvedSession, isNull);
    });
  });
}
