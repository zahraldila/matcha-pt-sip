import 'package:flutter_test/flutter_test.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:matcha_app/core/config/supabase_config.dart';
import 'package:matcha_app/features/match/data/match_service.dart';

void main() {
  test(
    'MatchService live queries, scoring save, and subscription test',
    () async {
      final supabase = SupabaseClient(
        SupabaseConfig.url,
        SupabaseConfig.anonKey,
      );

      final service = MatchService(supabaseClient: supabase);

      // 1. Test getSession (active session)
      final session = await service.getSession(null);
      expect(session, isNotNull);
      final sessionId = session!['session_id'];

      // 2. Test getMatchesForSession
      final matches = await service.getMatchesForSession(sessionId);
      expect(matches, isA<List<Map<String, dynamic>>>());

      if (matches.isNotEmpty) {
        final firstMatch = matches.first;
        final matchId = firstMatch['matchId'] as int;

        // 3. Test saveMatchScore (update/insert score)
        await service.saveMatchScore(
          matchId: matchId,
          scoreA: 8,
          scoreB: 5,
          sessionId: sessionId,
        );

        // Verify updated score
        final updatedMatches = await service.getMatchesForSession(sessionId);
        final updatedFirst = updatedMatches.firstWhere(
          (m) => m['matchId'] == matchId,
        );
        expect(updatedFirst['scoreA'], equals(8));
        expect(updatedFirst['scoreB'], equals(5));

        // 4. Test finishMatch
        await service.finishMatch(matchId: matchId, sessionId: sessionId);

        final finishedMatches = await service.getMatchesForSession(sessionId);
        final finishedFirst = finishedMatches.firstWhere(
          (m) => m['matchId'] == matchId,
        );
        expect(finishedFirst['status'], equals('Finished'));

        // Revert back to 'In Progress' so we don't break subsequent tests
        await supabase
            .from('tb_match')
            .update({'status_match': 'In Progress'})
            .eq('match_id', matchId);
      }

      // 5. Test getWaitingPlayers
      final waiting = await service.getWaitingPlayers(sessionId);
      expect(waiting, isA<List<String>>());

      // 6. Test subscribeLiveSession lifecycle
      final channel = service.subscribeLiveSession(
        sessionId: sessionId,
        onDataChanged: () {},
      );
      expect(channel, isNotNull);
      await service.unsubscribe(channel);
    },
  );
}
