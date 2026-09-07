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

    test('PlayingHistoryModel winner determination & serialization', () {
      final history = PlayingHistoryModel(
        playerId: 101,
        matchId: 1,
        totalScore: 12,
        isWin: true,
      );

      expect(history.playerId, 101);
      expect(history.matchId, 1);
      expect(history.totalScore, 12);
      expect(history.isWin, true);

      final json = history.toJson();
      expect(json['player_id'], 101);
      expect(json['total_score'], 12);
      expect(json['is_win'], true);
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

    test('tb_match_participant fields (match_id, player_id, side, group_no) map correctly to PlayingHistoryModel', () {
      final participants = [
        {'match_id': 1, 'player_id': 101, 'side': 'Side A', 'group_no': 1},
        {'match_id': 1, 'player_id': 102, 'side': 'A', 'group_no': 1},
        {'match_id': 1, 'player_id': 201, 'side': 'Side B', 'group_no': 2},
        {'match_id': 1, 'player_id': 202, 'side': 'B', 'group_no': 2},
      ];

      const totalScoreA = 21;
      const totalScoreB = 18;
      final isSideAWin = totalScoreA > totalScoreB;
      final isSideBWin = totalScoreB > totalScoreA;

      final List<PlayingHistoryModel> histories = [];
      for (final p in participants) {
        final rawId = p['player_id'];
        final pId = rawId is int ? rawId : int.tryParse(rawId.toString());
        expect(pId, isNotNull);

        final side = (p['side'] ?? '').toString().toLowerCase().trim();
        final groupNo = p['group_no'];
        final isSideB = side.contains('b') || groupNo == 2;

        histories.add(
          PlayingHistoryModel(
            playerId: pId!,
            matchId: p['match_id'] as int,
            totalScore: isSideB ? totalScoreB : totalScoreA,
            isWin: isSideB ? isSideBWin : isSideAWin,
          ),
        );
      }

      expect(histories.length, 4);

      // Side A players
      expect(histories[0].playerId, 101);
      expect(histories[0].totalScore, 21);
      expect(histories[0].isWin, true);

      expect(histories[1].playerId, 102);
      expect(histories[1].totalScore, 21);
      expect(histories[1].isWin, true);

      // Side B players
      expect(histories[2].playerId, 201);
      expect(histories[2].totalScore, 18);
      expect(histories[2].isWin, false);

      expect(histories[3].playerId, 202);
      expect(histories[3].totalScore, 18);
      expect(histories[3].isWin, false);
    });
  });
}
