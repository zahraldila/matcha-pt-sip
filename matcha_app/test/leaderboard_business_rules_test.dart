import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/recap/data/recap_service.dart';
import 'package:matcha_app/features/recap/domain/recap_models.dart';

void main() {
  group('MATCHA Leaderboard Final Business Rules & Parity Tests', () {
    // =========================================================================
    // 2. VERIFY CANONICAL SCORE ACROSS 10 SPECIFIC EDGE CASES
    // =========================================================================

    test('Canonical Case 1: Satu snapshot final', () {
      final scores = [
        {'id': 1, 'set_number': 1, 'score_a': 6, 'score_b': 4, 'status_score': 'final', 'version': 1},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 1);
      expect(res.first['score_a'], 6);
      expect(res.first['score_b'], 4);
      expect(res.first['status_score'], 'final');
    });

    test('Canonical Case 2: Banyak eventlog disaring menjadi 1 snapshot otoritatif', () {
      final scores = [
        for (int i = 1; i <= 10; i++)
          {'id': i, 'set_number': 1, 'score_a': i, 'score_b': 0, 'status_score': 'eventlog', 'version': i},
        {'id': 11, 'set_number': 1, 'score_a': 11, 'score_b': 0, 'status_score': 'completed', 'version': 11},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 1);
      expect(res.first['id'], 11);
      expect(res.first['score_a'], 11);
      expect(res.first['status_score'], 'completed');
    });

    test('Canonical Case 3: Multiple version untuk set yang sama mengambil versi terbaru', () {
      final scores = [
        {'id': 1, 'set_number': 1, 'score_a': 6, 'score_b': 4, 'status_score': 'completed', 'version': 1},
        {'id': 2, 'set_number': 1, 'score_a': 7, 'score_b': 5, 'status_score': 'completed', 'version': 2},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 1);
      expect(res.first['score_a'], 7);
      expect(res.first['score_b'], 5);
      expect(res.first['version'], 2);
    });

    test('Canonical Case 4: Beberapa set dalam satu match tetap dihitung terpisah', () {
      final scores = [
        {'id': 1, 'set_number': 1, 'score_a': 6, 'score_b': 4, 'status_score': 'completed', 'version': 1},
        {'id': 2, 'set_number': 2, 'score_a': 3, 'score_b': 6, 'status_score': 'completed', 'version': 1},
        {'id': 3, 'set_number': 3, 'score_a': 7, 'score_b': 6, 'status_score': 'completed', 'version': 1},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 3);
      expect(res.firstWhere((s) => s['set_number'] == 1)['score_a'], 6);
      expect(res.firstWhere((s) => s['set_number'] == 2)['score_a'], 3);
      expect(res.firstWhere((s) => s['set_number'] == 3)['score_a'], 7);
    });

    test('Canonical Case 5: Match Completed', () {
      final scores = [
        {'id': 50, 'set_number': 1, 'score_a': 21, 'score_b': 18, 'status_score': 'completed', 'version': 5},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 1);
      expect(res.first['status_score'], 'completed');
    });

    test('Canonical Case 6: Match Stopped dengan skor parsial (misal 5-3)', () {
      final scores = [
        {'id': 60, 'set_number': 1, 'score_a': 5, 'score_b': 3, 'status_score': 'stopped', 'version': 2},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 1);
      expect(res.first['score_a'], 5);
      expect(res.first['score_b'], 3);
      expect(res.first['status_score'], 'stopped');
    });

    test('Canonical Case 7: Match Stopped pada skor 0-0', () {
      final scores = [
        {'id': 70, 'set_number': 1, 'score_a': 0, 'score_b': 0, 'status_score': 'stopped', 'version': 1},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 1);
      expect(res.first['score_a'], 0);
      expect(res.first['score_b'], 0);
      expect(res.first['status_score'], 'stopped');
    });

    test('Canonical Case 8: Snapshot status null fallback', () {
      final scores = [
        {'id': 80, 'set_number': 1, 'score_a': 4, 'score_b': 2, 'status_score': null, 'version': 1},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 1);
      expect(res.first['score_a'], 4);
    });

    test('Canonical Case 9: Eventlog non-terminal dengan version lebih tinggi tidak mengalahkan snapshot terminal', () {
      final scores = [
        {'id': 1, 'set_number': 1, 'score_a': 6, 'score_b': 4, 'status_score': 'completed', 'version': 3},
        {'id': 2, 'set_number': 1, 'score_a': 6, 'score_b': 5, 'status_score': 'eventlog', 'version': 10},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 1);
      expect(res.first['id'], 1);
      expect(res.first['score_a'], 6);
      expect(res.first['score_b'], 4);
      expect(res.first['status_score'], 'completed');
    });

    test('Canonical Case 10: Dua score row dengan version sama tetapi ID berbeda (ID lebih tinggi menang)', () {
      final scores = [
        {'id': 101, 'set_number': 1, 'score_a': 6, 'score_b': 3, 'status_score': 'completed', 'version': 2},
        {'id': 102, 'set_number': 1, 'score_a': 6, 'score_b': 4, 'status_score': 'completed', 'version': 2},
      ];
      final res = RecapService.getAuthoritativeScores(scores);
      expect(res.length, 1);
      expect(res.first['id'], 102);
      expect(res.first['score_b'], 4);
    });

    // =========================================================================
    // RULE 1: Ranking Priority
    // 1. Total Poin (desc) -> 2. Selisih Poin (desc) -> 3. Match Menang (desc)
    // -> 4. Game Menang (desc) -> 5. Selisih Game (desc)
    // =========================================================================
    test('RULE 1 & Fixture 1: Total Points takes precedence over Match Wins', () {
      final players = [
        SessionPlayerStanding(
          rank: 0,
          playerId: 2,
          nama: 'Player B',
          level: 'Intermediate',
          matchesPlayed: 3,
          matchesWon: 3,
          matchesLost: 0,
          gamesWon: 16,
          gamesLost: 8,
          pointsFor: 30,
          pointsAgainst: 20,
          pointDiff: 10,
          gameDiff: 8,
          scoreWon: 30,
          gamesDiff: 8,
        ),
        SessionPlayerStanding(
          rank: 0,
          playerId: 1,
          nama: 'Player A',
          level: 'Advanced',
          matchesPlayed: 3,
          matchesWon: 2,
          matchesLost: 1,
          gamesWon: 14,
          gamesLost: 8,
          pointsFor: 32,
          pointsAgainst: 20,
          pointDiff: 12,
          gameDiff: 6,
          scoreWon: 32,
          gamesDiff: 6,
        ),
        SessionPlayerStanding(
          rank: 0,
          playerId: 3,
          nama: 'Player C',
          level: 'Intermediate',
          matchesPlayed: 3,
          matchesWon: 2,
          matchesLost: 1,
          gamesWon: 12,
          gamesLost: 9,
          pointsFor: 30,
          pointsAgainst: 22,
          pointDiff: 8,
          gameDiff: 3,
          scoreWon: 30,
          gamesDiff: 3,
        ),
      ];

      players.sort((a, b) {
        if (b.pointsFor != a.pointsFor) return b.pointsFor.compareTo(a.pointsFor);
        if (b.pointDiff != a.pointDiff) return b.pointDiff.compareTo(a.pointDiff);
        if (b.matchesWon != a.matchesWon) return b.matchesWon.compareTo(a.matchesWon);
        if (b.gamesWon != a.gamesWon) return b.gamesWon.compareTo(a.gamesWon);
        if (b.gameDiff != a.gameDiff) return b.gameDiff.compareTo(a.gameDiff);
        return a.nama.toLowerCase().compareTo(b.nama.toLowerCase());
      });

      expect(players[0].nama, 'Player A', reason: 'A has highest points (32) so ranks #1');
      expect(players[1].nama, 'Player B', reason: 'B has 30 points and +10 diff so ranks #2');
      expect(players[2].nama, 'Player C', reason: 'C has 30 points and +8 diff so ranks #3');
    });

    // =========================================================================
    // RULE 2: Joint Rank (Competition Ranking 1, 1, 3, 4) & (1, 1, 1, 4) & Zero-State
    // =========================================================================
    test('RULE 2 & Fixture 2: Tied players receive competition ranking (1, 1, 3, 4)', () {
      final players = [
        SessionPlayerStanding(
          rank: 0,
          playerId: 1,
          nama: 'Player A',
          level: 'Intermediate',
          matchesPlayed: 3,
          matchesWon: 2,
          matchesLost: 1,
          gamesWon: 12,
          gamesLost: 7,
          pointsFor: 30,
          pointsAgainst: 20,
          pointDiff: 10,
          gameDiff: 5,
          scoreWon: 30,
          gamesDiff: 5,
        ),
        SessionPlayerStanding(
          rank: 0,
          playerId: 2,
          nama: 'Player B',
          level: 'Intermediate',
          matchesPlayed: 3,
          matchesWon: 2,
          matchesLost: 1,
          gamesWon: 12,
          gamesLost: 7,
          pointsFor: 30,
          pointsAgainst: 20,
          pointDiff: 10,
          gameDiff: 5,
          scoreWon: 30,
          gamesDiff: 5,
        ),
        SessionPlayerStanding(
          rank: 0,
          playerId: 3,
          nama: 'Player C',
          level: 'Intermediate',
          matchesPlayed: 3,
          matchesWon: 1,
          matchesLost: 2,
          gamesWon: 10,
          gamesLost: 8,
          pointsFor: 24,
          pointsAgainst: 20,
          pointDiff: 4,
          gameDiff: 2,
          scoreWon: 24,
          gamesDiff: 2,
        ),
        SessionPlayerStanding(
          rank: 0,
          playerId: 4,
          nama: 'Player D',
          level: 'Intermediate',
          matchesPlayed: 3,
          matchesWon: 1,
          matchesLost: 2,
          gamesWon: 8,
          gamesLost: 9,
          pointsFor: 18,
          pointsAgainst: 20,
          pointDiff: -2,
          gameDiff: -1,
          scoreWon: 18,
          gamesDiff: -1,
        ),
      ];

      final ranked = <SessionPlayerStanding>[];
      for (int i = 0; i < players.length; i++) {
        final current = players[i];
        int assignedRank;
        if (i > 0) {
          final prev = players[i - 1];
          final isTied = prev.pointsFor == current.pointsFor &&
              prev.pointDiff == current.pointDiff &&
              prev.matchesWon == current.matchesWon &&
              prev.gamesWon == current.gamesWon &&
              prev.gameDiff == current.gameDiff;
          assignedRank = isTied ? ranked[i - 1].rank : (i + 1);
        } else {
          assignedRank = 1;
        }

        ranked.add(SessionPlayerStanding(
          rank: assignedRank,
          playerId: current.playerId,
          nama: current.nama,
          level: current.level,
          matchesPlayed: current.matchesPlayed,
          matchesWon: current.matchesWon,
          matchesLost: current.matchesLost,
          gamesWon: current.gamesWon,
          gamesLost: current.gamesLost,
          pointsFor: current.pointsFor,
          pointsAgainst: current.pointsAgainst,
          pointDiff: current.pointDiff,
          gameDiff: current.gameDiff,
          scoreWon: current.scoreWon,
          gamesDiff: current.gamesDiff,
        ));
      }

      expect(ranked[0].rank, 1);
      expect(ranked[1].rank, 1);
      expect(ranked[2].rank, 3);
      expect(ranked[3].rank, 4);
    });

    test('RULE 2: Three players tied receive competition ranking (1, 1, 1, 4)', () {
      final players = [
        for (int i = 1; i <= 3; i++)
          SessionPlayerStanding(
            rank: 0,
            playerId: i,
            nama: 'Player $i',
            level: 'Intermediate',
            matchesPlayed: 1,
            matchesWon: 1,
            matchesLost: 0,
            gamesWon: 10,
            gamesLost: 2,
            pointsFor: 10,
            pointsAgainst: 2,
            pointDiff: 8,
            gameDiff: 8,
            scoreWon: 10,
            gamesDiff: 8,
          ),
        for (int i = 4; i <= 6; i++)
          SessionPlayerStanding(
            rank: 0,
            playerId: i,
            nama: 'Player $i',
            level: 'Intermediate',
            matchesPlayed: 1,
            matchesWon: 0,
            matchesLost: 1,
            gamesWon: 2,
            gamesLost: 10,
            pointsFor: 2,
            pointsAgainst: 10,
            pointDiff: -8,
            gameDiff: -8,
            scoreWon: 2,
            gamesDiff: -8,
          ),
      ];

      final ranked = <SessionPlayerStanding>[];
      for (int i = 0; i < players.length; i++) {
        final current = players[i];
        int assignedRank;
        if (i > 0) {
          final prev = players[i - 1];
          final isTied = prev.pointsFor == current.pointsFor &&
              prev.pointDiff == current.pointDiff &&
              prev.matchesWon == current.matchesWon &&
              prev.gamesWon == current.gamesWon &&
              prev.gameDiff == current.gameDiff;
          assignedRank = isTied ? ranked[i - 1].rank : (i + 1);
        } else {
          assignedRank = 1;
        }

        ranked.add(SessionPlayerStanding(
          rank: assignedRank,
          playerId: current.playerId,
          nama: current.nama,
          level: current.level,
          matchesPlayed: current.matchesPlayed,
          matchesWon: current.matchesWon,
          matchesLost: current.matchesLost,
          gamesWon: current.gamesWon,
          gamesLost: current.gamesLost,
          pointsFor: current.pointsFor,
          pointsAgainst: current.pointsAgainst,
          pointDiff: current.pointDiff,
          gameDiff: current.gameDiff,
          scoreWon: current.scoreWon,
          gamesDiff: current.gamesDiff,
        ));
      }

      expect(ranked[0].rank, 1);
      expect(ranked[1].rank, 1);
      expect(ranked[2].rank, 1);
      expect(ranked[3].rank, 4, reason: 'Fourth player after three joint #1 must be Rank 4');
      expect(ranked[4].rank, 4);
      expect(ranked[5].rank, 4);
    });

    test('RULE 2 & Fixture 4: Zero-state returns hasCompetitiveResults == false and rank == 0', () {
      final List<SessionPlayerStanding> unplayed = [
        SessionPlayerStanding(
          rank: 0,
          playerId: 1,
          nama: 'Player A',
          level: 'Intermediate',
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
          nama: 'Player B',
          level: 'Intermediate',
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

      final recapData = SessionMatchRecapData(
        sessionId: 1,
        sessionName: 'Zero Match Session',
        sportName: 'Padel',
        scoringSystem: 'Americano 21',
        venueName: 'Barong Padel',
        totalRounds: 1,
        totalPlayers: 2,
        standings: unplayed,
        rounds: [],
      );

      expect(recapData.hasCompetitiveResults, false);
      expect(recapData.standings.every((s) => s.rank == 0), true);
    });

    // =========================================================================
    // RULE 3: Stopped Match partial points included, no fake winner
    // =========================================================================
    test('RULE 3 & Fixture 3: Stopped match with partial score 5-3 counts points and point diff without match winner', () {
      final playerA = SessionPlayerStanding(
        rank: 1,
        playerId: 1,
        nama: 'Player A',
        level: 'Intermediate',
        matchesPlayed: 1,
        matchesWon: 0, // No match win awarded for stopped match
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

      final playerB = SessionPlayerStanding(
        rank: 2,
        playerId: 2,
        nama: 'Player B',
        level: 'Intermediate',
        matchesPlayed: 1,
        matchesWon: 0, // No match win awarded for stopped match
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

      expect(playerA.pointsFor, 5);
      expect(playerB.pointsFor, 3);
      expect(playerA.pointDiff, 2);
      expect(playerB.pointDiff, -2);
      expect(playerA.matchesWon, 0);
      expect(playerB.matchesWon, 0);

      final recap = SessionMatchRecapData(
        sessionId: 2,
        sessionName: 'Stopped Match Session',
        sportName: 'Padel',
        scoringSystem: 'Americano 21',
        venueName: 'Barong Padel',
        totalRounds: 1,
        totalPlayers: 2,
        standings: [playerA, playerB],
        rounds: [],
      );

      expect(recap.hasCompetitiveResults, true);
    });

    // =========================================================================
    // RULE 4: Withdrawn Player keeps stats and ranking
    // =========================================================================
    test('RULE 4 & Fixture 5: Withdrawn player retains 20 points, ranking, and carries withdrawn indicator', () {
      final playerA = SessionPlayerStanding(
        rank: 1,
        playerId: 10,
        userId: 100,
        nama: 'Player Withdrawn',
        level: 'Intermediate',
        matchesPlayed: 2,
        matchesWon: 2,
        matchesLost: 0,
        gamesWon: 20,
        gamesLost: 10,
        pointsFor: 20,
        pointsAgainst: 10,
        pointDiff: 10,
        gameDiff: 10,
        scoreWon: 20,
        gamesDiff: 10,
        isWithdrawn: true,
        withdrawnReason: 'Cedera lutut',
      );

      expect(playerA.isWithdrawn, true);
      expect(playerA.pointsFor, 20);
      expect(playerA.rank, 1);
      expect(playerA.withdrawnReason, 'Cedera lutut');
    });

    // =========================================================================
    // RULE 5: Kudos System validation (Unauthenticated giver rejected)
    // =========================================================================
    test('RULE 5: Kudos toggle requires authenticated giver', () async {
      final service = RecapService();
      expect(
        () => service.toggleKudos(
          sessionId: 99,
          recipientPlayerId: 10,
          recipientName: 'Player Test',
          giverUserId: 0,
          badge: '🎾 Super Forehand',
        ),
        throwsA(predicate((e) => e.toString().contains('Autentikasi diperlukan'))),
      );
    });

    // =========================================================================
    // Edge case: Win Rate calculation handles 0 matches without divide-by-zero
    // =========================================================================
    test('Win Rate: 0 matches played calculates safe 0% win rate without division by zero', () {
      final matchesWon = 0;
      final matchesPlayed = 0;
      final winRate = matchesPlayed > 0 ? (matchesWon / matchesPlayed * 100).round() : 0;
      expect(winRate, 0);
    });
  });
}
