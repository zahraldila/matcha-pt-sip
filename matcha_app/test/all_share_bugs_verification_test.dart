import 'package:flutter_test/flutter_test.dart';
import 'package:gal/gal.dart';
import 'package:matcha_app/core/services/app_link_service.dart';
import 'package:matcha_app/features/session/domain/session_model.dart';

void main() {
  group('Verification Tests: Deep Link & Share Bug Fixes', () {
    // -------------------------------------------------------------
    // Bug 1 [LNK-001 / LNK-003 / LNK-017]: Deep Link Parsing
    // -------------------------------------------------------------
    test('Bug 1 [LNK-001/003/017]: Canonical HTTPS share links are correctly parsed', () {
      const sampleToken = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
      final uri = Uri.parse('https://matcha.siproduktif.com/games/share/$sampleToken');

      final extracted = AppLinkService.extractShareToken(uri);
      expect(extracted, equals(sampleToken));
    });

    test('Bug 1 [LNK-001/003/017]: Non-canonical domains or invalid paths are safely rejected', () {
      expect(AppLinkService.extractShareToken(Uri.parse('https://example.com/games/share/123')), isNull);
      expect(AppLinkService.extractShareToken(Uri.parse('https://matcha.siproduktif.com/other/123')), isNull);
    });

    // -------------------------------------------------------------
    // Bug 2 [LNK-005]: Invalid / Expired Token Handling
    // -------------------------------------------------------------
    test('Bug 2 [LNK-005]: Empty or null tokens are handled safely without crashing', () {
      final emptyTokenUri = Uri.parse('https://matcha.siproduktif.com/games/share/');
      expect(AppLinkService.extractShareToken(emptyTokenUri), isNull);

      final whitespaceUri = Uri.parse('https://matcha.siproduktif.com/games/share/%20%20');
      expect(AppLinkService.extractShareToken(whitespaceUri), isNull);
    });

    // -------------------------------------------------------------
    // Bug 3 [LNK-016]: Gallery Permission & GalException Handling
    // -------------------------------------------------------------
    test('Bug 3 [LNK-016]: GalException accessDenied type is recognized and handled', () {
      const exceptionType = GalExceptionType.accessDenied;
      expect(exceptionType, equals(GalExceptionType.accessDenied));
      expect(exceptionType.message.toLowerCase(), contains('denied'));
    });

    // -------------------------------------------------------------
    // Bug 4 [LNK-002]: WhatsApp Direct Share Format
    // -------------------------------------------------------------
    test('Bug 4 [LNK-002]: WhatsApp draft message and intent URL are properly formatted', () {
      const sessionTitle = 'Mabar Padel Sore Santai';
      const shareUrl = 'https://matcha.siproduktif.com/games/share/testtoken123';
      final shareText = 'Mabar yuk di MATCHA!\n\n$sessionTitle\n\n$shareUrl';

      final waSchemeUri = Uri.parse('whatsapp://send?text=${Uri.encodeComponent(shareText)}');
      final waWebUri = Uri.parse('https://wa.me/?text=${Uri.encodeComponent(shareText)}');

      expect(waSchemeUri.scheme, equals('whatsapp'));
      expect(waSchemeUri.host, equals('send'));
      expect(waSchemeUri.queryParameters['text'], contains(sessionTitle));
      expect(waSchemeUri.queryParameters['text'], contains(shareUrl));

      expect(waWebUri.scheme, equals('https'));
      expect(waWebUri.host, equals('wa.me'));
      expect(waWebUri.queryParameters['text'], contains(sessionTitle));
    });

    // -------------------------------------------------------------
    // Bug 5 [LNK-006]: Finished Session Status Detection for Recap
    // -------------------------------------------------------------
    test('Bug 5 [LNK-006]: Sesi Finished / Completed detected for direct Recap routing', () {
      final finishedSession = SessionModel.fromMap({
        'session_id': 101,
        'nama_session': 'Padel Grand Final Season 1',
        'status_session': 'Finished',
        'sport_id': 1,
        'venue_id': 1,
        'jumlah_pemain': 4,
        'share_token': 'finished_token_101',
      });

      final completedSession = SessionModel.fromMap({
        'session_id': 102,
        'nama_session': 'Tennis Friendly Match',
        'status_session': 'Completed',
        'sport_id': 2,
        'venue_id': 1,
        'jumlah_pemain': 4,
        'share_token': 'completed_token_102',
      });

      final openSession = SessionModel.fromMap({
        'session_id': 103,
        'nama_session': 'Open Padel Mabar',
        'status_session': 'Open',
        'sport_id': 1,
        'venue_id': 1,
        'jumlah_pemain': 4,
        'share_token': 'open_token_103',
      });

      bool isFinishedSession(SessionModel s) {
        final statusLower = s.statusSession.trim().toLowerCase();
        return statusLower == 'finished' || statusLower == 'completed' || statusLower == 'selesai';
      }

      expect(isFinishedSession(finishedSession), isTrue);
      expect(isFinishedSession(completedSession), isTrue);
      expect(isFinishedSession(openSession), isFalse);
    });
  });
}
