import 'package:flutter_test/flutter_test.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:matcha_app/core/config/supabase_config.dart';
import 'package:matcha_app/features/match/data/datasource/match_remote_data_source.dart';

void main() {
  late SupabaseClient supabase;
  late MatchRemoteDataSource dataSource;

  setUpAll(() async {
    supabase = SupabaseClient(
      SupabaseConfig.url,
      SupabaseConfig.anonKey,
    );
    dataSource = MatchRemoteDataSource(supabaseClient: supabase);
  });

  group('Match Scoring Supabase Persistence Verification', () {
    int? testMatchId;
    bool shouldDeleteMatch = false;

    setUp(() async {
      // Create a dedicated test match to prevent interference with other live tests
      final existingDrawing = await supabase.from('tb_drawing').select('drawing_id').limit(1).maybeSingle();
      final drawingId = existingDrawing != null ? existingDrawing['drawing_id'] : 1;
      final existingCourt = await supabase.from('tb_court').select('court_id').limit(1).maybeSingle();
      final courtId = existingCourt != null ? existingCourt['court_id'] : 1;
      final now = DateTime.now().toIso8601String();
      final matchRes = await supabase.from('tb_match').insert({
        'drawing_id': drawingId,
        'court_id': courtId,
        'nomor_match': 99,
        'status_match': 'in_progress',
        'created_at': now,
        'updated_at': now,
      }).select().single();
      testMatchId = matchRes['match_id'] is int
          ? matchRes['match_id'] as int
          : int.parse(matchRes['match_id'].toString());
      shouldDeleteMatch = true;
    });

    tearDown(() async {
      if (testMatchId != null) {
        try {
          await supabase.from('tb_score').delete().eq('match_id', testMatchId!);
          if (shouldDeleteMatch) {
            await supabase.from('tb_match').delete().eq('match_id', testMatchId!);
          }
        } catch (_) {}
      }
    });

    test('1. Insert Score Set 1, Set 2, Set 3 & verify values', () async {
      // 1. Insert Set 1 (Side A: 6, Side B: 4)
      final set1Result = await dataSource.saveOrUpdateScore(
        matchId: testMatchId!,
        setNumber: 1,
        scoreSideA: 6,
        scoreSideB: 4,
      );
      expect(set1Result.matchId, testMatchId!);
      expect(set1Result.setNumber, 1);
      expect(set1Result.scoreSideA, 6);
      expect(set1Result.scoreSideB, 4);

      // 2. Insert Set 2 (Side A: 3, Side B: 6)
      final set2Result = await dataSource.saveOrUpdateScore(
        matchId: testMatchId!,
        setNumber: 2,
        scoreSideA: 3,
        scoreSideB: 6,
      );
      expect(set2Result.setNumber, 2);
      expect(set2Result.scoreSideA, 3);
      expect(set2Result.scoreSideB, 6);

      // 3. Insert Set 3 (Side A: 7, Side B: 5)
      final set3Result = await dataSource.saveOrUpdateScore(
        matchId: testMatchId!,
        setNumber: 3,
        scoreSideA: 7,
        scoreSideB: 5,
      );
      expect(set3Result.setNumber, 3);
      expect(set3Result.scoreSideA, 7);
      expect(set3Result.scoreSideB, 5);

      // Verify row count in Supabase
      final allScores = await dataSource.getScoresByMatchId(testMatchId!);
      expect(allScores.length, 3);
      expect(allScores[0].setNumber, 1);
      expect(allScores[0].scoreSideA, 6);
      expect(allScores[0].scoreSideB, 4);

      expect(allScores[1].setNumber, 2);
      expect(allScores[1].scoreSideA, 3);
      expect(allScores[1].scoreSideB, 6);

      expect(allScores[2].setNumber, 3);
      expect(allScores[2].scoreSideA, 7);
      expect(allScores[2].scoreSideB, 5);
    });

    test('2. Update existing score on same set without duplicate rows', () async {
      // Initial insert Set 1
      await dataSource.saveOrUpdateScore(
        matchId: testMatchId!,
        setNumber: 1,
        scoreSideA: 1,
        scoreSideB: 0,
      );

      // Update Set 1 with new score (Side A: 6, Side B: 4)
      final updatedSet1 = await dataSource.saveOrUpdateScore(
        matchId: testMatchId!,
        setNumber: 1,
        scoreSideA: 6,
        scoreSideB: 4,
      );
      expect(updatedSet1.scoreSideA, 6);
      expect(updatedSet1.scoreSideB, 4);

      // Verify row count is still exactly 1 (no duplicate)
      final scores = await dataSource.getScoresByMatchId(testMatchId!);
      expect(scores.length, 1);
      expect(scores.first.setNumber, 1);
      expect(scores.first.scoreSideA, 6);
      expect(scores.first.scoreSideB, 4);
    });

    test('3. Reload scores correctly maps to Set 1, 2, 3', () async {
      await dataSource.saveOrUpdateScore(
        matchId: testMatchId!,
        setNumber: 1,
        scoreSideA: 6,
        scoreSideB: 2,
      );
      await dataSource.saveOrUpdateScore(
        matchId: testMatchId!,
        setNumber: 2,
        scoreSideA: 4,
        scoreSideB: 6,
      );
      await dataSource.saveOrUpdateScore(
        matchId: testMatchId!,
        setNumber: 3,
        scoreSideA: 6,
        scoreSideB: 3,
      );

      // Reload
      final loaded = await dataSource.getScoresByMatchId(testMatchId!);
      final scoreMapA = {for (var s in loaded) s.setNumber: s.scoreSideA};
      final scoreMapB = {for (var s in loaded) s.setNumber: s.scoreSideB};

      expect(scoreMapA[1], 6);
      expect(scoreMapB[1], 2);
      expect(scoreMapA[2], 4);
      expect(scoreMapB[2], 6);
      expect(scoreMapA[3], 6);
      expect(scoreMapB[3], 3);
    });

    test('4. Score validation prevents negative values', () async {
      expect(
        () => dataSource.saveOrUpdateScore(
          matchId: testMatchId!,
          setNumber: 1,
          scoreSideA: -1,
          scoreSideB: 0,
        ),
        throwsA(isA<Exception>()),
      );

      expect(
        () => dataSource.saveOrUpdateScore(
          matchId: testMatchId!,
          setNumber: 1,
          scoreSideA: 0,
          scoreSideB: -5,
        ),
        throwsA(isA<Exception>()),
      );
    });
  });
}
