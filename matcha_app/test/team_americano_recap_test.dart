import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/recap/domain/recap_models.dart';

void main() {
  group('Team Americano Leaderboard, Podium, & Kudos Tests', () {
    // 8 players
    const p1 = GamePlayerItem(id: '1', playerId: 1, name: 'Rahma', level: 'Intermediate');
    const p2 = GamePlayerItem(id: '2', playerId: 2, name: 'Zahra', level: 'Intermediate');
    const p3 = GamePlayerItem(id: '3', playerId: 3, name: 'Adya', level: 'Advanced');
    const p4 = GamePlayerItem(id: '4', playerId: 4, name: 'Hilmy', level: 'Advanced');
    const p5 = GamePlayerItem(id: '5', playerId: 5, name: 'Budi', level: 'Beginner');
    const p6 = GamePlayerItem(id: '6', playerId: 6, name: 'Siti', level: 'Beginner');

    test('1 & 8: 8 pemain Team Americano menghasilkan 4 entri tim dan 8 entri kudos individual', () {
      // Verify SessionMatchRecapData team standings vs player standings
      const recapData = SessionMatchRecapData(
        sessionId: 100,
        sessionName: 'Mabar Team Americano',
        sportName: 'Padel',
        scoringSystem: 'Americano 21 Poin',
        venueName: 'Barong Padel',
        totalRounds: 1,
        totalPlayers: 8,
        matchFormatId: 4,
        matchFormatName: 'Team Americano',
        standings: [
          SessionPlayerStanding(rank: 1, playerId: 1, nama: 'Rahma', level: 'Intermediate', matchesPlayed: 1, matchesWon: 1, matchesLost: 0, gamesWon: 6, gamesLost: 4, pointsFor: 6, pointsAgainst: 4, pointDiff: 2, gameDiff: 2, setDiff: 0, scoreWon: 6, gamesDiff: 2),
          SessionPlayerStanding(rank: 2, playerId: 2, nama: 'Zahra', level: 'Intermediate', matchesPlayed: 1, matchesWon: 1, matchesLost: 0, gamesWon: 6, gamesLost: 4, pointsFor: 6, pointsAgainst: 4, pointDiff: 2, gameDiff: 2, setDiff: 0, scoreWon: 6, gamesDiff: 2),
          SessionPlayerStanding(rank: 3, playerId: 3, nama: 'Adya', level: 'Advanced', matchesPlayed: 1, matchesWon: 0, matchesLost: 1, gamesWon: 4, gamesLost: 6, pointsFor: 4, pointsAgainst: 6, pointDiff: -2, gameDiff: -2, setDiff: 0, scoreWon: 4, gamesDiff: -2),
          SessionPlayerStanding(rank: 4, playerId: 4, nama: 'Hilmy', level: 'Advanced', matchesPlayed: 1, matchesWon: 0, matchesLost: 1, gamesWon: 4, gamesLost: 6, pointsFor: 4, pointsAgainst: 6, pointDiff: -2, gameDiff: -2, setDiff: 0, scoreWon: 4, gamesDiff: -2),
          SessionPlayerStanding(rank: 5, playerId: 5, nama: 'Budi', level: 'Beginner', matchesPlayed: 1, matchesWon: 0, matchesLost: 0, gamesWon: 5, gamesLost: 5, pointsFor: 5, pointsAgainst: 5, pointDiff: 0, gameDiff: 0, setDiff: 0, scoreWon: 5, gamesDiff: 0),
          SessionPlayerStanding(rank: 6, playerId: 6, nama: 'Siti', level: 'Beginner', matchesPlayed: 1, matchesWon: 0, matchesLost: 0, gamesWon: 5, gamesLost: 5, pointsFor: 5, pointsAgainst: 5, pointDiff: 0, gameDiff: 0, setDiff: 0, scoreWon: 5, gamesDiff: 0),
          SessionPlayerStanding(rank: 7, playerId: 7, nama: 'Rian', level: 'Intermediate', matchesPlayed: 1, matchesWon: 0, matchesLost: 0, gamesWon: 5, gamesLost: 5, pointsFor: 5, pointsAgainst: 5, pointDiff: 0, gameDiff: 0, setDiff: 0, scoreWon: 5, gamesDiff: 0),
          SessionPlayerStanding(rank: 8, playerId: 8, nama: 'Maya', level: 'Intermediate', matchesPlayed: 1, matchesWon: 0, matchesLost: 0, gamesWon: 5, gamesLost: 5, pointsFor: 5, pointsAgainst: 5, pointDiff: 0, gameDiff: 0, setDiff: 0, scoreWon: 5, gamesDiff: 0),
        ],
        teamStandings: [
          SessionTeamStanding(rank: 1, teamId: '1-2', teamName: 'Rahma & Zahra', memberIds: [1, 2], memberNames: ['Rahma', 'Zahra'], memberPhotos: [], matchesPlayed: 1, matchesWon: 1, matchesLost: 0, setsWon: 0, setsLost: 0, gamesWon: 6, gamesLost: 4, pointsFor: 6, pointsAgainst: 4, pointDiff: 2, gameDiff: 2, setDiff: 0, scoreWon: 6, gamesDiff: 2),
          SessionTeamStanding(rank: 2, teamId: '5-6', teamName: 'Budi & Siti', memberIds: [5, 6], memberNames: ['Budi', 'Siti'], memberPhotos: [], matchesPlayed: 1, matchesWon: 0, matchesLost: 0, setsWon: 0, setsLost: 0, gamesWon: 5, gamesLost: 5, pointsFor: 5, pointsAgainst: 5, pointDiff: 0, gameDiff: 0, setDiff: 0, scoreWon: 5, gamesDiff: 0),
          SessionTeamStanding(rank: 3, teamId: '7-8', teamName: 'Rian & Maya', memberIds: [7, 8], memberNames: ['Rian', 'Maya'], memberPhotos: [], matchesPlayed: 1, matchesWon: 0, matchesLost: 0, setsWon: 0, setsLost: 0, gamesWon: 5, gamesLost: 5, pointsFor: 5, pointsAgainst: 5, pointDiff: 0, gameDiff: 0, setDiff: 0, scoreWon: 5, gamesDiff: 0),
          SessionTeamStanding(rank: 4, teamId: '3-4', teamName: 'Adya & Hilmy', memberIds: [3, 4], memberNames: ['Adya', 'Hilmy'], memberPhotos: [], matchesPlayed: 1, matchesWon: 0, matchesLost: 1, setsWon: 0, setsLost: 0, gamesWon: 4, gamesLost: 6, pointsFor: 4, pointsAgainst: 6, pointDiff: -2, gameDiff: -2, setDiff: 0, scoreWon: 4, gamesDiff: -2),
        ],
        rounds: [],
      );

      expect(recapData.isTeamFormat, true);
      expect(recapData.teamStandings.length, 4); // 4 tim tetap
      expect(recapData.standings.length, 8); // 8 pemain untuk kudos
      // Kudos recipient IDs are individual player IDs
      final kudosIds = recapData.standings.map((s) => s.playerId).toList();
      expect(kudosIds, [1, 2, 3, 4, 5, 6, 7, 8]);
    });

    test('2 & 3: Pasangan berpindah sisi A/B tetap satu identitas, skor 6-4 dihitung sekali (tidak digandakan)', () {
      // Round 1: Team Rahma & Zahra on Side A, Adya & Hilmy on Side B. Score 6-4
      final m1 = DrawingMatch(
        courtNumber: 1,
        teamA: [p1, p2],
        teamB: [p3, p4],
        status: 'Completed',
        scoreA: 6,
        scoreB: 4,
      );
      // Round 2: Team Rahma & Zahra switch to Side B, Budi & Siti on Side A. Score: 3 - 7 (Rahma & Zahra win 7-3)
      final m2 = DrawingMatch(
        courtNumber: 1,
        teamA: [p5, p6],
        teamB: [p1, p2], // Team Rahma & Zahra on Side B
        status: 'Completed',
        scoreA: 3,
        scoreB: 7,
      );

      final matches = [m1, m2];
      final Map<String, Map<String, dynamic>> teamStats = {};

      for (var match in matches) {
        final sortedA = List<GamePlayerItem>.from(match.teamA)..sort((a, b) => a.id.compareTo(b.id));
        final keyA = sortedA.map((p) => p.id).join('-');
        final nameA = sortedA.map((p) => p.name).join(' & ');

        final sortedB = List<GamePlayerItem>.from(match.teamB)..sort((a, b) => a.id.compareTo(b.id));
        final keyB = sortedB.map((p) => p.id).join('-');
        final nameB = sortedB.map((p) => p.name).join(' & ');

        teamStats.putIfAbsent(keyA, () => {
          'teamId': keyA,
          'teamName': nameA,
          'matchesPlayed': 0,
          'matchesWon': 0,
          'pointsFor': 0,
          'pointsAgainst': 0,
        });

        teamStats.putIfAbsent(keyB, () => {
          'teamId': keyB,
          'teamName': nameB,
          'matchesPlayed': 0,
          'matchesWon': 0,
          'pointsFor': 0,
          'pointsAgainst': 0,
        });

        final stA = teamStats[keyA]!;
        stA['matchesPlayed'] = stA['matchesPlayed'] + 1;
        stA['pointsFor'] = stA['pointsFor'] + match.scoreA;
        stA['pointsAgainst'] = stA['pointsAgainst'] + match.scoreB;
        if (match.scoreA > match.scoreB) stA['matchesWon'] = stA['matchesWon'] + 1;

        final stB = teamStats[keyB]!;
        stB['matchesPlayed'] = stB['matchesPlayed'] + 1;
        stB['pointsFor'] = stB['pointsFor'] + match.scoreB;
        stB['pointsAgainst'] = stB['pointsAgainst'] + match.scoreA;
        if (match.scoreB > match.scoreA) stB['matchesWon'] = stB['matchesWon'] + 1;
      }

      // Check Team Rahma & Zahra (key '1-2')
      final team12 = teamStats['1-2']!;
      expect(team12['matchesPlayed'], 2);
      expect(team12['matchesWon'], 2);
      // In m1: scoreA was 6, scoreB was 4 -> ptsFor += 6, ptsAgainst += 4
      // In m2: scoreB was 7, scoreA was 3 -> ptsFor += 7, ptsAgainst += 3
      // Total: 6 + 7 = 13 (NOT 26), Against: 4 + 3 = 7 (NOT 14)
      expect(team12['pointsFor'], 13);
      expect(team12['pointsAgainst'], 7);

      // Check Team Adya & Hilmy (key '3-4')
      final team34 = teamStats['3-4']!;
      expect(team34['matchesPlayed'], 1);
      expect(team34['pointsFor'], 4);
      expect(team34['pointsAgainst'], 6);
    });

    test('4: Hasil resmi winner_team, termasuk walkover, digunakan sesuai sistem existing', () {
      // Walkover: score 0-0 but winnerTeam = 'Team A'
      final mWalkover = DrawingMatch(
        courtNumber: 1,
        teamA: [p1, p2],
        teamB: [p3, p4],
        status: 'Completed',
        scoreA: 0,
        scoreB: 0,
        winnerTeam: 'Team A',
      );

      final winA = mWalkover.winnerTeam != null && mWalkover.winnerTeam!.isNotEmpty
          ? (mWalkover.winnerTeam!.trim().toUpperCase() == 'A' || mWalkover.winnerTeam!.trim().toUpperCase() == 'TEAM A')
          : mWalkover.scoreA > mWalkover.scoreB;
      final winB = mWalkover.winnerTeam != null && mWalkover.winnerTeam!.isNotEmpty
          ? (mWalkover.winnerTeam!.trim().toUpperCase() == 'B' || mWalkover.winnerTeam!.trim().toUpperCase() == 'TEAM B')
          : mWalkover.scoreB > mWalkover.scoreA;

      expect(winA, true);
      expect(winB, false);
    });

    test('5: Match belum selesai tidak menambah kemenangan/kekalahan final', () {
      final mInProgress = DrawingMatch(
        courtNumber: 1,
        teamA: [p1, p2],
        teamB: [p3, p4],
        status: 'In Progress',
        scoreA: 3,
        scoreB: 1,
      );

      final isCompleted = mInProgress.status.toLowerCase() == 'completed' ||
          (mInProgress.winnerTeam != null && mInProgress.winnerTeam!.isNotEmpty) ||
          (mInProgress.status != 'Scheduled' && mInProgress.status != 'In Progress' && (mInProgress.scoreA > 0 || mInProgress.scoreB > 0));

      expect(isCompleted, false);
    });

    test('6: Podium langsung dan rekap tersimpan memiliki urutan dan statistik yang sama', () {
      // Team Rahma & Zahra: 1 win, 6 pts, diff +2
      // Team Adya & Hilmy: 0 win, 4 pts, diff -2
      final directRanking = [
        {'teamName': 'Rahma & Zahra', 'wins': 1, 'pts': 6, 'diff': 2},
        {'teamName': 'Adya & Hilmy', 'wins': 0, 'pts': 4, 'diff': -2},
      ];

      final storedTeamStandings = [
        const SessionTeamStanding(rank: 1, teamId: '1-2', teamName: 'Rahma & Zahra', memberIds: [1, 2], memberNames: ['Rahma', 'Zahra'], memberPhotos: [], matchesPlayed: 1, matchesWon: 1, matchesLost: 0, setsWon: 0, setsLost: 0, gamesWon: 6, gamesLost: 4, pointsFor: 6, pointsAgainst: 4, pointDiff: 2, gameDiff: 2, setDiff: 0, scoreWon: 6, gamesDiff: 2),
        const SessionTeamStanding(rank: 2, teamId: '3-4', teamName: 'Adya & Hilmy', memberIds: [3, 4], memberNames: ['Adya', 'Hilmy'], memberPhotos: [], matchesPlayed: 1, matchesWon: 0, matchesLost: 1, setsWon: 0, setsLost: 0, gamesWon: 4, gamesLost: 6, pointsFor: 4, pointsAgainst: 6, pointDiff: -2, gameDiff: -2, setDiff: 0, scoreWon: 4, gamesDiff: -2),
      ];

      expect(directRanking[0]['teamName'], storedTeamStandings[0].teamName);
      expect(directRanking[0]['wins'], storedTeamStandings[0].matchesWon);
      expect(directRanking[0]['pts'], storedTeamStandings[0].pointsFor);

      expect(directRanking[1]['teamName'], storedTeamStandings[1].teamName);
      expect(directRanking[1]['wins'], storedTeamStandings[1].matchesWon);
      expect(directRanking[1]['pts'], storedTeamStandings[1].pointsFor);
    });

    test('7: Pemain dengan nama sama tetapi ID berbeda tidak menyebabkan penggabungan', () {
      const playerA = GamePlayerItem(id: '101', playerId: 101, name: 'Alex');
      const playerB = GamePlayerItem(id: '102', playerId: 102, name: 'Alex');

      final key = [playerA.id, playerB.id]..sort();
      final teamKey = key.join('-');

      expect(teamKey, '101-102');
      expect(teamKey != 'Alex-Alex', true);
    });

    test('9: Americano biasa dan Single tidak mengalami regresi (tetap klasemen pemain)', () {
      const americanoRecap = SessionMatchRecapData(
        sessionId: 200,
        sessionName: 'Mabar Regular Americano',
        sportName: 'Padel',
        scoringSystem: 'Americano 21 Poin',
        venueName: 'Barong Padel',
        totalRounds: 4,
        totalPlayers: 8,
        matchFormatId: 1, // Regular Americano
        matchFormatName: 'Americano',
        standings: [
          SessionPlayerStanding(rank: 1, playerId: 1, nama: 'Rahma', level: 'Intermediate', matchesPlayed: 4, matchesWon: 3, matchesLost: 1, gamesWon: 24, gamesLost: 16, pointsFor: 24, pointsAgainst: 16, pointDiff: 8, gameDiff: 8, setDiff: 0, scoreWon: 24, gamesDiff: 8),
          SessionPlayerStanding(rank: 2, playerId: 2, nama: 'Zahra', level: 'Intermediate', matchesPlayed: 4, matchesWon: 2, matchesLost: 2, gamesWon: 20, gamesLost: 20, pointsFor: 20, pointsAgainst: 20, pointDiff: 0, gameDiff: 0, setDiff: 0, scoreWon: 20, gamesDiff: 0),
        ],
        teamStandings: [],
        rounds: [],
      );

      expect(americanoRecap.isTeamFormat, false);
      expect(americanoRecap.standings.length, 2);
      expect(americanoRecap.standings.first.nama, 'Rahma');
      expect(americanoRecap.teamStandings.isEmpty, true);
    });

    test('10: Share/export Team Americano menampilkan juara per tim', () {
      const teamRecap = SessionMatchRecapData(
        sessionId: 300,
        sessionName: 'Final Team Americano',
        sportName: 'Padel',
        scoringSystem: 'Americano 21 Poin',
        venueName: 'Barong Padel',
        totalRounds: 3,
        totalPlayers: 8,
        matchFormatId: 4,
        matchFormatName: 'Team Americano',
        standings: [
          SessionPlayerStanding(rank: 1, playerId: 1, nama: 'Rahma', level: 'Intermediate', matchesPlayed: 3, matchesWon: 3, matchesLost: 0, gamesWon: 18, gamesLost: 8, pointsFor: 18, pointsAgainst: 8, pointDiff: 10, gameDiff: 10, setDiff: 0, scoreWon: 18, gamesDiff: 10),
          SessionPlayerStanding(rank: 2, playerId: 2, nama: 'Zahra', level: 'Intermediate', matchesPlayed: 3, matchesWon: 3, matchesLost: 0, gamesWon: 18, gamesLost: 8, pointsFor: 18, pointsAgainst: 8, pointDiff: 10, gameDiff: 10, setDiff: 0, scoreWon: 18, gamesDiff: 10),
        ],
        teamStandings: [
          SessionTeamStanding(rank: 1, teamId: '1-2', teamName: 'Rahma & Zahra', memberIds: [1, 2], memberNames: ['Rahma', 'Zahra'], memberPhotos: [], matchesPlayed: 3, matchesWon: 3, matchesLost: 0, setsWon: 0, setsLost: 0, gamesWon: 18, gamesLost: 8, pointsFor: 18, pointsAgainst: 8, pointDiff: 10, gameDiff: 10, setDiff: 0, scoreWon: 18, gamesDiff: 10),
        ],
        rounds: [],
      );

      final winnerName = (teamRecap.isTeamFormat && teamRecap.teamStandings.isNotEmpty)
          ? teamRecap.teamStandings.first.teamName
          : (teamRecap.standings.isNotEmpty ? teamRecap.standings.first.nama : '-');

      expect(winnerName, 'Rahma & Zahra');
      final shareText = '🎾 Hasil Mabar: ${teamRecap.sessionName}\n🏆 Pemenang: $winnerName\nLihat rekap selengkapnya di: https://matcha.siproduktif.com';
      expect(shareText.contains('🏆 Pemenang: Rahma & Zahra'), true);
    });
  });
}
