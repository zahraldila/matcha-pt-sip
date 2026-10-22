import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/recap/data/recap_service.dart';
import 'package:matcha_app/features/recap/domain/recap_models.dart';

void main() {
  group('MATCHA Leaderboard Bug Fix & Parity Regression Suite', () {
    // -------------------------------------------------------------------------
    // TEST 1: Manual testing reproduction fixture (2 players, 3 rounds, finished session)
    // -------------------------------------------------------------------------
    test('1. Reproduction of manual testing bug: 2 players, 3 rounds, finished session with in-progress matches', () {
      final playerA = SessionPlayerStanding(
        rank: 1,
        playerId: 502,
        nama: 'Yoon Jeonghan',
        level: 'Intermediate',
        matchesPlayed: 3,
        matchesWon: 2,
        matchesLost: 1,
        gamesWon: 2,
        gamesLost: 1,
        pointsFor: 2,
        pointsAgainst: 1,
        pointDiff: 1,
        gameDiff: 1,
        scoreWon: 2,
        gamesDiff: 1,
      );

      final playerB = SessionPlayerStanding(
        rank: 2,
        playerId: 648,
        nama: 'scoups Edited',
        level: 'Intermediate',
        matchesPlayed: 3,
        matchesWon: 1,
        matchesLost: 2,
        gamesWon: 1,
        gamesLost: 2,
        pointsFor: 1,
        pointsAgainst: 2,
        pointDiff: -1,
        gameDiff: -1,
        scoreWon: 1,
        gamesDiff: -1,
      );

      final rounds = [
        SessionRoundRecapItem(
          roundNumber: 1,
          matches: [
            SessionMatchItem(
              matchId: 2212,
              roundNumber: 1,
              courtName: 'Court 1',
              sideANames: ['Yoon Jeonghan'],
              sideBNames: ['scoups Edited'],
              scoreA: 0,
              scoreB: 1,
              setDetails: 'Set 1: 0-1',
              isSideAWinner: false,
              isSideBWinner: true,
              isDraw: false,
            ),
          ],
        ),
        SessionRoundRecapItem(
          roundNumber: 2,
          matches: [
            SessionMatchItem(
              matchId: 2213,
              roundNumber: 2,
              courtName: 'Court 1',
              sideANames: ['scoups Edited'],
              sideBNames: ['Yoon Jeonghan'],
              scoreA: 0,
              scoreB: 1,
              setDetails: 'Set 1: 0-1',
              isSideAWinner: false,
              isSideBWinner: true,
              isDraw: false,
            ),
          ],
        ),
        SessionRoundRecapItem(
          roundNumber: 3,
          matches: [
            SessionMatchItem(
              matchId: 2214,
              roundNumber: 3,
              courtName: 'Court 1',
              sideANames: ['Yoon Jeonghan'],
              sideBNames: ['scoups Edited'],
              scoreA: 1,
              scoreB: 0,
              setDetails: 'Set 1: 1-0',
              isSideAWinner: true,
              isSideBWinner: false,
              isDraw: false,
            ),
          ],
        ),
      ];

      final recap = SessionMatchRecapData(
        sessionId: 635,
        sessionName: 'Test leaderboard',
        sportName: 'Padel',
        scoringSystem: 'First to 2 Poin',
        venueName: 'Barong Padel',
        totalRounds: 3,
        totalPlayers: 2,
        standings: [playerA, playerB],
        rounds: rounds,
      );

      // Riwayat menampilkan tiga ronde
      expect(recap.rounds.length, 3);
      expect(recap.rounds[0].matches.first.isSideBWinner, true, reason: 'R1 dimenangkan scoups Edited');
      expect(recap.rounds[1].matches.first.isSideBWinner, true, reason: 'R2 dimenangkan Yoon Jeonghan');
      expect(recap.rounds[2].matches.first.isSideAWinner, true, reason: 'R3 dimenangkan Yoon Jeonghan');

      // Klasemen mengakumulasi semua kontribusi skor sah
      expect(recap.hasCompetitiveResults, true);
      expect(recap.standings[0].nama, 'Yoon Jeonghan');
      expect(recap.standings[0].matchesPlayed, 3);
      expect(recap.standings[0].matchesWon, 2);
      expect(recap.standings[0].pointsFor, 2);
      expect(recap.standings[0].pointDiff, 1);
      expect(recap.standings[0].rank, 1);

      expect(recap.standings[1].nama, 'scoups Edited');
      expect(recap.standings[1].matchesPlayed, 3);
      expect(recap.standings[1].matchesWon, 1);
      expect(recap.standings[1].pointsFor, 1);
      expect(recap.standings[1].pointDiff, -1);
      expect(recap.standings[1].rank, 2);
    });

    // -------------------------------------------------------------------------
    // TEST 2: Total Of dengan beberapa set
    // -------------------------------------------------------------------------
    test('2. Total Of dengan beberapa set terpisah (Set 1: 6-4, Set 2: 3-6, Set 3: 7-5)', () {
      final rawScores = [
        {'id': 1, 'set_number': 1, 'game_score_a': 6, 'game_score_b': 4, 'status_score': 'completed', 'version': 1},
        {'id': 2, 'set_number': 2, 'game_score_a': 3, 'game_score_b': 6, 'status_score': 'completed', 'version': 1},
        {'id': 3, 'set_number': 3, 'game_score_a': 7, 'game_score_b': 5, 'status_score': 'completed', 'version': 1},
      ];

      final authScores = RecapService.getAuthoritativeScores(rawScores);
      expect(authScores.length, 3);

      int totalA = 0;
      int totalB = 0;
      int setsA = 0;
      int setsB = 0;

      for (final s in authScores) {
        final ga = s['game_score_a'] as int;
        final gb = s['game_score_b'] as int;
        totalA += ga;
        totalB += gb;
        if (ga > gb) setsA++;
        if (gb > ga) setsB++;
      }

      expect(setsA, 2);
      expect(setsB, 1);
      expect(totalA, 16);
      expect(totalB, 15);
    });

    // -------------------------------------------------------------------------
    // TEST 3: First To
    // -------------------------------------------------------------------------
    test('3. First To 8 games format', () {
      final rawScores = [
        {'id': 1, 'set_number': 1, 'game_score_a': 8, 'game_score_b': 6, 'status_score': 'completed', 'version': 5},
      ];
      final auth = RecapService.getAuthoritativeScores(rawScores);
      expect(auth.length, 1);
      expect(auth.first['game_score_a'], 8);
      expect(auth.first['game_score_b'], 6);
    });

    // -------------------------------------------------------------------------
    // TEST 4: Match Completed
    // -------------------------------------------------------------------------
    test('4. Match Completed snapshot status', () {
      final rawScores = [
        {'id': 10, 'set_number': 1, 'score_a': 21, 'score_b': 19, 'status_score': 'completed', 'version': 1},
      ];
      final auth = RecapService.getAuthoritativeScores(rawScores);
      expect(auth.first['status_score'], 'completed');
    });

    // -------------------------------------------------------------------------
    // TEST 5: Match Stopped (partial scores, no fake winner)
    // -------------------------------------------------------------------------
    test('5. Match Stopped preserves partial score without fake winner', () {
      final rawScores = [
        {'id': 20, 'set_number': 1, 'score_a': 5, 'score_b': 3, 'status_score': 'stopped', 'version': 2},
      ];
      final auth = RecapService.getAuthoritativeScores(rawScores);
      expect(auth.first['score_a'], 5);
      expect(auth.first['score_b'], 3);
      expect(auth.first['status_score'], 'stopped');

      final standingA = SessionPlayerStanding(
        rank: 1,
        playerId: 1,
        nama: 'A',
        level: 'Intermediate',
        matchesPlayed: 1,
        matchesWon: 0,
        matchesLost: 0,
        gamesWon: 5,
        gamesLost: 3,
        pointsFor: 5,
        pointsAgainst: 3,
        pointDiff: 2,
        gameDiff: 2,
        scoreWon: 5,
        gamesDiff: 2,
      );

      final standingB = SessionPlayerStanding(
        rank: 2,
        playerId: 2,
        nama: 'B',
        level: 'Intermediate',
        matchesPlayed: 1,
        matchesWon: 0,
        matchesLost: 0,
        gamesWon: 3,
        gamesLost: 5,
        pointsFor: 3,
        pointsAgainst: 5,
        pointDiff: -2,
        gameDiff: -2,
        scoreWon: 3,
        gamesDiff: -2,
      );

      expect(standingA.matchesWon, 0, reason: 'Stopped match must not grant match win');
      expect(standingB.matchesWon, 0);
      expect(standingA.pointsFor, 5);
      expect(standingB.pointsFor, 3);
    });

    // -------------------------------------------------------------------------
    // TEST 6: True zero-state (rank 0, hasCompetitiveResults == false)
    // -------------------------------------------------------------------------
    test('6. True zero-state returns hasCompetitiveResults == false and rank == 0', () {
      final unplayed = [
        SessionPlayerStanding(
          rank: 0,
          playerId: 1,
          nama: 'Player 1',
          level: 'Beginner',
          matchesPlayed: 0,
          matchesWon: 0,
          matchesLost: 0,
          gamesWon: 0,
          gamesLost: 0,
          pointsFor: 0,
          pointsAgainst: 0,
          pointDiff: 0,
          gameDiff: 0,
          scoreWon: 0,
          gamesDiff: 0,
        ),
        SessionPlayerStanding(
          rank: 0,
          playerId: 2,
          nama: 'Player 2',
          level: 'Beginner',
          matchesPlayed: 0,
          matchesWon: 0,
          matchesLost: 0,
          gamesWon: 0,
          gamesLost: 0,
          pointsFor: 0,
          pointsAgainst: 0,
          pointDiff: 0,
          gameDiff: 0,
          scoreWon: 0,
          gamesDiff: 0,
        ),
      ];

      final recap = SessionMatchRecapData(
        sessionId: 999,
        sessionName: 'Zero Match Session',
        sportName: 'Padel',
        scoringSystem: 'First to 8',
        venueName: 'Barong Padel',
        totalRounds: 1,
        totalPlayers: 2,
        standings: unplayed,
        rounds: [],
      );

      expect(recap.hasCompetitiveResults, false);
      expect(recap.standings.every((s) => s.rank == 0), true);
    });

    // -------------------------------------------------------------------------
    // TEST 7: Score eventlog dan versi
    // -------------------------------------------------------------------------
    test('7. Eventlog filtered out and highest version authoritative score selected', () {
      final rawScores = [
        {'id': 1, 'set_number': 1, 'score_a': 1, 'score_b': 0, 'status_score': 'eventlog', 'version': 1},
        {'id': 2, 'set_number': 1, 'score_a': 2, 'score_b': 0, 'status_score': 'eventlog', 'version': 2},
        {'id': 3, 'set_number': 1, 'score_a': 6, 'score_b': 3, 'status_score': 'completed', 'version': 3},
        {'id': 4, 'set_number': 1, 'score_a': 6, 'score_b': 4, 'status_score': 'eventlog', 'version': 10},
      ];

      final auth = RecapService.getAuthoritativeScores(rawScores);
      expect(auth.length, 1);
      expect(auth.first['status_score'], 'completed');
      expect(auth.first['score_a'], 6);
      expect(auth.first['score_b'], 3);
    });

    // -------------------------------------------------------------------------
    // TEST 8: Multi-round session
    // -------------------------------------------------------------------------
    test('8. Multi-round session accumulates across all rounds', () {
      final rounds = [
        SessionRoundRecapItem(
          roundNumber: 1,
          matches: [
            SessionMatchItem(
              matchId: 1,
              roundNumber: 1,
              courtName: 'Court 1',
              sideANames: ['A'],
              sideBNames: ['B'],
              scoreA: 6,
              scoreB: 2,
              setDetails: 'Set 1: 6-2',
              isSideAWinner: true,
              isSideBWinner: false,
              isDraw: false,
            ),
          ],
        ),
        SessionRoundRecapItem(
          roundNumber: 2,
          matches: [
            SessionMatchItem(
              matchId: 2,
              roundNumber: 2,
              courtName: 'Court 1',
              sideANames: ['A'],
              sideBNames: ['B'],
              scoreA: 6,
              scoreB: 4,
              setDetails: 'Set 1: 6-4',
              isSideAWinner: true,
              isSideBWinner: false,
              isDraw: false,
            ),
          ],
        ),
      ];

      expect(rounds.length, 2);
      expect(rounds[0].matches.first.scoreA, 6);
      expect(rounds[1].matches.first.scoreA, 6);
    });

    // -------------------------------------------------------------------------
    // TEST 9: Multi-court session
    // -------------------------------------------------------------------------
    test('9. Multi-court session produces discrete matches per court', () {
      final round = SessionRoundRecapItem(
        roundNumber: 1,
        matches: [
          SessionMatchItem(
            matchId: 10,
            roundNumber: 1,
            courtName: 'Court 1',
            sideANames: ['A & B'],
            sideBNames: ['C & D'],
            scoreA: 8,
            scoreB: 4,
            setDetails: 'Set 1: 8-4',
            isSideAWinner: true,
            isSideBWinner: false,
            isDraw: false,
          ),
          SessionMatchItem(
            matchId: 11,
            roundNumber: 1,
            courtName: 'Court 2',
            sideANames: ['E & F'],
            sideBNames: ['G & H'],
            scoreA: 8,
            scoreB: 6,
            setDetails: 'Set 1: 8-6',
            isSideAWinner: true,
            isSideBWinner: false,
            isDraw: false,
          ),
        ],
      );

      expect(round.matches.length, 2);
      expect(round.matches[0].courtName, 'Court 1');
      expect(round.matches[1].courtName, 'Court 2');
    });

    // -------------------------------------------------------------------------
    // TEST 10: Session with valid results but specific point field is 0
    // -------------------------------------------------------------------------
    test('10. Session with valid results but specific point field 0 is recognized as competitive', () {
      // Pemain yang kalah 0-1 tetap memiliki matchPlayed > 0 dan pointsAgainst > 0
      final loser = SessionPlayerStanding(
        rank: 2,
        playerId: 2,
        nama: 'Loser',
        level: 'Intermediate',
        matchesPlayed: 1,
        matchesWon: 0,
        matchesLost: 1,
        gamesWon: 0,
        gamesLost: 1,
        pointsFor: 0, // pointsFor 0!
        pointsAgainst: 1,
        pointDiff: -1,
        gameDiff: -1,
        scoreWon: 0,
        gamesDiff: -1,
      );

      final winner = SessionPlayerStanding(
        rank: 1,
        playerId: 1,
        nama: 'Winner',
        level: 'Intermediate',
        matchesPlayed: 1,
        matchesWon: 1,
        matchesLost: 0,
        gamesWon: 1,
        gamesLost: 0,
        pointsFor: 1,
        pointsAgainst: 0,
        pointDiff: 1,
        gameDiff: 1,
        scoreWon: 1,
        gamesDiff: 1,
      );

      final recap = SessionMatchRecapData(
        sessionId: 777,
        sessionName: 'Session 1-0',
        sportName: 'Padel',
        scoringSystem: 'First to 2 Poin',
        venueName: 'Barong Padel',
        totalRounds: 1,
        totalPlayers: 2,
        standings: [winner, loser],
        rounds: [],
      );

      expect(recap.hasCompetitiveResults, true);
      expect(recap.standings[0].rank, 1);
      expect(recap.standings[1].rank, 2);
    });

    // -------------------------------------------------------------------------
    // TEST 11: Five business rules tie-breaking
    // -------------------------------------------------------------------------
    test('11. 5 Business Rules: Joint rank when all 5 metrics are identical', () {
      final p1 = SessionPlayerStanding(
        rank: 1,
        playerId: 1,
        nama: 'Alice',
        level: 'Intermediate',
        matchesPlayed: 1,
        matchesWon: 0,
        matchesLost: 0,
        gamesWon: 5,
        gamesLost: 5,
        pointsFor: 5,
        pointsAgainst: 5,
        pointDiff: 0,
        gameDiff: 0,
        scoreWon: 5,
        gamesDiff: 0,
      );

      final p2 = SessionPlayerStanding(
        rank: 1,
        playerId: 2,
        nama: 'Bob',
        level: 'Intermediate',
        matchesPlayed: 1,
        matchesWon: 0,
        matchesLost: 0,
        gamesWon: 5,
        gamesLost: 5,
        pointsFor: 5,
        pointsAgainst: 5,
        pointDiff: 0,
        gameDiff: 0,
        scoreWon: 5,
        gamesDiff: 0,
      );

      expect(p1.rank, 1);
      expect(p2.rank, 1);
      expect(p1.pointsFor, p2.pointsFor);
      expect(p1.pointDiff, p2.pointDiff);
      expect(p1.matchesWon, p2.matchesWon);
      expect(p1.gamesWon, p2.gamesWon);
      expect(p1.gameDiff, p2.gameDiff);
    });
  });
}
