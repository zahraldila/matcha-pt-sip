import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';

void main() {
  group('MatchaDrawingEngine Algorithm Tests', () {
    test('Americano 4-player Double 2v2 generates 3 rounds without repeating partners', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'Player 1'),
        const GamePlayerItem(id: 'p2', name: 'Player 2'),
        const GamePlayerItem(id: 'p3', name: 'Player 3'),
        const GamePlayerItem(id: 'p4', name: 'Player 4'),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      expect(rounds.length, 3);

      final Map<String, int> partnerPairs = {};
      String pairKey(String id1, String id2) => id1.compareTo(id2) < 0 ? '$id1-$id2' : '$id2-$id1';

      for (var r in rounds) {
        expect(r.matches.length, 1);
        final match = r.matches.first;
        expect(match.teamA.length, 2);
        expect(match.teamB.length, 2);

        final pairA = pairKey(match.teamA[0].id, match.teamA[1].id);
        final pairB = pairKey(match.teamB[0].id, match.teamB[1].id);

        partnerPairs[pairA] = (partnerPairs[pairA] ?? 0) + 1;
        partnerPairs[pairB] = (partnerPairs[pairB] ?? 0) + 1;
      }

      // Every pair in 4-player Americano should only occur exactly once
      for (var count in partnerPairs.values) {
        expect(count, 1, reason: 'Partner should not repeat in standard 4-player Americano');
      }
    });

    test('Americano 5 players has fair bench rotation', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'Player 1'),
        const GamePlayerItem(id: 'p2', name: 'Player 2'),
        const GamePlayerItem(id: 'p3', name: 'Player 3'),
        const GamePlayerItem(id: 'p4', name: 'Player 4'),
        const GamePlayerItem(id: 'p5', name: 'Player 5'),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      expect(rounds.length, 3);
      for (var r in rounds) {
        expect(r.matches.length, 1);
        expect(r.restingPlayers.length, 1);
      }
    });

    test('Team Americano generates fixed pairs schedule', () {
      final players = [
        const GamePlayerItem(id: 'p1', name: 'Player 1'),
        const GamePlayerItem(id: 'p2', name: 'Player 2'),
        const GamePlayerItem(id: 'p3', name: 'Player 3'),
        const GamePlayerItem(id: 'p4', name: 'Player 4'),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Team Americano',
        playMode: 'Double',
        roundCount: 1,
      );

      expect(rounds.length, 1);
      expect(rounds.first.matches.length, 1);
      expect(rounds.first.matches.first.teamA.map((p) => p.id), ['p1', 'p2']);
      expect(rounds.first.matches.first.teamB.map((p) => p.id), ['p3', 'p4']);
    });
  });
}
