import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/recap/data/recap_service.dart';
import 'package:matcha_app/features/recap/domain/recap_models.dart';

void main() {
  group('Recap Models and Logic Tests', () {
    test('RecapService date formatting test', () {
      final dt = DateTime(2026, 9, 23);
      final formatted = RecapService.formatDate(dt);
      expect(formatted, '23 Sep 2026');
    });

    test('HostRecapData and HostedSessionItem instantiations', () {
      final item = HostedSessionItem(
        sessionId: 10,
        title: 'Mabar Badminton Pagi',
        sport: 'Badminton',
        venue: 'GOR Bulutangkis',
        court: 'Lapangan 1',
        date: '23 Sep 2026',
        time: '08:00 WIB',
        quota: 4,
        joinedCount: 4,
        status: 'completed',
        scoringSystem: 'Americano 21 Poin',
        players: [
          const HostedSessionPlayer(
            playerId: 1,
            name: 'Andi',
            gender: 'Laki-laki',
            level: 'Intermediate',
          ),
        ],
      );

      final recap = HostRecapData(
        totalSessions: 1,
        totalPlayers: 4,
        completedSessions: 1,
        favoriteVenue: 'GOR Bulutangkis',
        sessions: [item],
      );

      expect(recap.totalSessions, 1);
      expect(recap.favoriteVenue, 'GOR Bulutangkis');
      expect(recap.sessions.first.status, 'completed');
    });

    test('PlayerCareerRecapData instantiations and stats validation', () {
      const match = PlayerMatchHistoryItem(
        sport: 'Badminton',
        venue: 'GOR Bulutangkis',
        matchDate: '23 Sep 2026',
        timestamp: 1727059200000,
        result: 'WIN',
        score: '21 - 18',
        partner: 'Andi',
        opponents: ['Budi', 'Citra'],
      );

      const h2h = HeadToHeadItem(
        opponent: 'Budi',
        win: 3,
        lose: 1,
        played: 4,
        winRatePercent: 75.0,
      );

      const career = PlayerCareerRecapData(
        playerName: 'Marcello',
        username: 'marcello',
        role: 'Host',
        level: 'Intermediate',
        avatar: 'https://example.com/avatar.jpg',
        totalMatches: 4,
        wins: 3,
        losses: 1,
        winRate: '75%',
        totalHours: '2 Jam',
        streak: '3W',
        hasMatches: true,
        recentMatches: [match],
        headToHead: [h2h],
      );

      expect(career.winRate, '75%');
      expect(career.streak, '3W');
      expect(career.recentMatches.first.result, 'WIN');
      expect(career.headToHead.first.winRatePercent, 75.0);
    });
  });
}
