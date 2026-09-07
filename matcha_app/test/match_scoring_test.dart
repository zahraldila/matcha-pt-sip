import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/match/domain/models/match_model.dart';
import 'package:matcha_app/features/match/domain/models/playing_history_model.dart';
import 'package:matcha_app/features/match/domain/models/score_model.dart';
import 'package:matcha_app/features/match/presentation/match_scoring_page.dart';

void main() {
  group('Match & Scoring Models Test', () {
    test('ScoreModel json serialization & deserialization', () {
      final json = {
        'score_id': 10,
        'match_id': 1,
        'set_number': 1,
        'score_side_a': 6,
        'score_side_b': 4,
      };

      final score = ScoreModel.fromJson(json);
      expect(score.scoreId, 10);
      expect(score.matchId, 1);
      expect(score.setNumber, 1);
      expect(score.scoreSideA, 6);
      expect(score.scoreSideB, 4);

      final encoded = score.toJson();
      expect(encoded['match_id'], 1);
      expect(encoded['set_number'], 1);
      expect(encoded['score_side_a'], 6);
      expect(encoded['score_side_b'], 4);
    });

    test('MatchModel json serialization & deserialization', () {
      final json = {
        'match_id': 1,
        'drawing_id': 2,
        'court_id': 3,
        'round_number': 1,
        'side_a_player1': 101,
        'side_a_player2': 102,
        'side_b_player1': 201,
        'side_b_player2': 202,
        'status_match': 'in_progress',
      };

      final match = MatchModel.fromJson(json);
      expect(match.matchId, 1);
      expect(match.sideAPlayer1, 101);
      expect(match.sideAPlayer2, 102);
      expect(match.sideBPlayer1, 201);
      expect(match.sideBPlayer2, 202);
      expect(match.isFinished, false);

      final finishedMatch = MatchModel.fromJson({
        'match_id': 1,
        'status_match': 'Finished',
        'hasil_pertandingan': 'Side A Win',
        'waktu_selesai': '2026-09-07T10:00:00.000Z',
      });
      expect(finishedMatch.isFinished, true);
      expect(finishedMatch.hasilPertandingan, 'Side A Win');
      expect(finishedMatch.waktuSelesai, isNotNull);
      final jsonFinished = finishedMatch.toJson();
      expect(jsonFinished['status_match'], 'Finished');
      expect(jsonFinished['hasil_pertandingan'], 'Side A Win');
      expect(jsonFinished['waktu_selesai'], '2026-09-07T10:00:00.000Z');
    });

    test('PlayingHistoryModel serialization and deserialization with actual tb_playing_history schema', () {
      final now = DateTime.parse('2026-09-08T04:00:00.000Z');
      final history = PlayingHistoryModel(
        historyId: 10,
        playerId: 101,
        matchId: 1,
        sessionId: 5,
        courtId: 2,
        score: 21,
        partnerPlayerId: 102,
        opponentPlayerId: 201,
        jumlahPermainan: 1,
        createdAt: now,
      );

      expect(history.historyId, 10);
      expect(history.playerId, 101);
      expect(history.matchId, 1);
      expect(history.sessionId, 5);
      expect(history.courtId, 2);
      expect(history.score, 21);
      expect(history.partnerPlayerId, 102);
      expect(history.opponentPlayerId, 201);
      expect(history.jumlahPermainan, 1);
      expect(history.createdAt, now);

      final json = history.toJson();
      expect(json['history_id'], 10);
      expect(json['player_id'], 101);
      expect(json['match_id'], 1);
      expect(json['session_id'], 5);
      expect(json['court_id'], 2);
      expect(json['score'], 21);
      expect(json['partner_player_id'], 102);
      expect(json['opponent_player_id'], 201);
      expect(json['jumlah_permainan'], 1);
      expect(json['created_at'], '2026-09-08T04:00:00.000Z');

      final fromJsonObj = PlayingHistoryModel.fromJson(json);
      expect(fromJsonObj.historyId, 10);
      expect(fromJsonObj.playerId, 101);
      expect(fromJsonObj.matchId, 1);
      expect(fromJsonObj.sessionId, 5);
      expect(fromJsonObj.courtId, 2);
      expect(fromJsonObj.score, 21);
      expect(fromJsonObj.partnerPlayerId, 102);
      expect(fromJsonObj.opponentPlayerId, 201);
      expect(fromJsonObj.jumlahPermainan, 1);
      expect(fromJsonObj.createdAt, now);
    });

    test('Aggregate score and is_win calculation logic', () {
      final scores = [
        const ScoreModel(matchId: 1, setNumber: 1, scoreSideA: 6, scoreSideB: 4),
        const ScoreModel(matchId: 1, setNumber: 2, scoreSideA: 4, scoreSideB: 6),
        const ScoreModel(matchId: 1, setNumber: 3, scoreSideA: 7, scoreSideB: 5),
      ];

      final totalScoreA = scores.fold<int>(0, (sum, s) => sum + s.scoreSideA);
      final totalScoreB = scores.fold<int>(0, (sum, s) => sum + s.scoreSideB);

      expect(totalScoreA, 17);
      expect(totalScoreB, 15);
      expect(totalScoreA > totalScoreB, true);
    });

    test('MatchScoringPage handles null and non-null matchId / nomorMatch correctly', () {
      const pageWithNull = MatchScoringPage();
      expect(pageWithNull.matchId, isNull);
      expect(pageWithNull.nomorMatch, isNull);

      const pageWithValues = MatchScoringPage(
        matchId: 8,
        nomorMatch: 1,
        sessionName: 'Saturday Morning',
      );
      expect(pageWithValues.matchId, 8);
      expect(pageWithValues.nomorMatch, 1);
    });

    test('ScoreModel handles null set_number without defaulting to Set 1', () {
      final jsonLegacy = {
        'score_id': 99,
        'match_id': 1,
        'set_number': null,
        'score_side_a': 5,
        'score_side_b': 3,
      };

      final score = ScoreModel.fromJson(jsonLegacy);
      expect(score.setNumber, isNull);
      expect(score.setNumber == 1, isFalse);
    });

    test('tb_match_participant fields map correctly to PlayingHistoryModel with partner, opponent & score', () {
      final participants = [
        {'match_id': 1, 'player_id': 101, 'side': 'Side A', 'group_no': 1},
        {'match_id': 1, 'player_id': 102, 'side': 'A', 'group_no': 1},
        {'match_id': 1, 'player_id': 201, 'side': 'Side B', 'group_no': 2},
        {'match_id': 1, 'player_id': 202, 'side': 'B', 'group_no': 2},
      ];

      const totalScoreA = 21;
      const totalScoreB = 18;
      const sessionId = 3;
      const courtId = 1;

      final List<int> sideAPlayers = [];
      final List<int> sideBPlayers = [];

      for (final p in participants) {
        final rawId = p['player_id'];
        final pId = rawId is int ? rawId : int.tryParse(rawId.toString());
        expect(pId, isNotNull);

        final side = (p['side'] ?? '').toString().toLowerCase().trim();
        final groupNo = p['group_no'];
        final isSideB = side.contains('b') || groupNo == 2;

        if (isSideB) {
          sideBPlayers.add(pId!);
        } else {
          sideAPlayers.add(pId!);
        }
      }

      final List<PlayingHistoryModel> histories = [];

      for (final pId in sideAPlayers) {
        int? partnerId;
        for (final other in sideAPlayers) {
          if (other != pId) {
            partnerId = other;
            break;
          }
        }
        final opponentId = sideBPlayers.isNotEmpty ? sideBPlayers.first : null;

        histories.add(
          PlayingHistoryModel(
            playerId: pId,
            matchId: 1,
            sessionId: sessionId,
            courtId: courtId,
            score: totalScoreA,
            partnerPlayerId: partnerId,
            opponentPlayerId: opponentId,
            jumlahPermainan: 1,
          ),
        );
      }

      for (final pId in sideBPlayers) {
        int? partnerId;
        for (final other in sideBPlayers) {
          if (other != pId) {
            partnerId = other;
            break;
          }
        }
        final opponentId = sideAPlayers.isNotEmpty ? sideAPlayers.first : null;

        histories.add(
          PlayingHistoryModel(
            playerId: pId,
            matchId: 1,
            sessionId: sessionId,
            courtId: courtId,
            score: totalScoreB,
            partnerPlayerId: partnerId,
            opponentPlayerId: opponentId,
            jumlahPermainan: 1,
          ),
        );
      }

      expect(histories.length, 4);

      // Side A player 1
      expect(histories[0].playerId, 101);
      expect(histories[0].score, 21);
      expect(histories[0].partnerPlayerId, 102);
      expect(histories[0].opponentPlayerId, 201);
      expect(histories[0].sessionId, 3);
      expect(histories[0].courtId, 1);
      expect(histories[0].jumlahPermainan, 1);

      // Side A player 2
      expect(histories[1].playerId, 102);
      expect(histories[1].score, 21);
      expect(histories[1].partnerPlayerId, 101);
      expect(histories[1].opponentPlayerId, 201);

      // Side B player 1
      expect(histories[2].playerId, 201);
      expect(histories[2].score, 18);
      expect(histories[2].partnerPlayerId, 202);
      expect(histories[2].opponentPlayerId, 101);

      // Side B player 2
      expect(histories[3].playerId, 202);
      expect(histories[3].score, 18);
      expect(histories[3].partnerPlayerId, 201);
      expect(histories[3].opponentPlayerId, 101);
    });
  });
}
