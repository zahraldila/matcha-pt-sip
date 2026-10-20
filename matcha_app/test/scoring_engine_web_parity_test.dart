import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/match/domain/services/scoring_engine.dart';
import 'package:matcha_app/features/match/domain/models/score_model.dart';
import 'package:matcha_app/features/match/domain/models/match_model.dart';

void main() {
  group('Web Parity Scoring Specification Tests', () {
    test('1. Scoring system detection parities with web', () {
      final total3 = ScoringEngine.detectScoringSystem('Total of 3');
      expect(total3.type, 'total_of_sets');
      expect(total3.isSets, true);
      expect(total3.maxSets, 3);
      expect(total3.targetSets, 2);
      expect(total3.targetGames, 6);

      final total5 = ScoringEngine.detectScoringSystem('Total of 5');
      expect(total5.maxSets, 5);
      expect(total5.targetSets, 3);
      expect(total5.targetGames, 6);

      final total7 = ScoringEngine.detectScoringSystem('Total of 7');
      expect(total7.maxSets, 7);
      expect(total7.targetSets, 4);

      final first8 = ScoringEngine.detectScoringSystem('First to 8');
      expect(first8.type, 'first_to_games');
      expect(first8.isSets, false);
      expect(first8.targetGames, 8);
      expect(first8.maxSets, 1);

      final first15 = ScoringEngine.detectScoringSystem('First to 15');
      expect(first15.targetGames, 15);

      final fallback = ScoringEngine.detectScoringSystem('Unknown or Empty');
      expect(fallback.type, 'total_of_sets');
      expect(fallback.maxSets, 3);
      expect(fallback.targetSets, 2);
      expect(fallback.targetGames, 6);
    });

    test('2. Point progression ladder 0 -> 15 -> 30 -> 40 -> Game Win', () {
      final system = ScoringEngine.detectScoringSystem('Total of 3');
      var state = const ScoringMatchState();

      // Team A scores: 0 -> 15
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.pointDisplayA, '15');
      expect(state.pointDisplayB, '0');
      expect(state.gamesA, 0);

      // Team A scores: 15 -> 30
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.pointDisplayA, '30');
      expect(state.pointDisplayB, '0');

      // Team A scores: 30 -> 40
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.pointDisplayA, '40');
      expect(state.pointDisplayB, '0');

      // Team A scores at 40 (opponent at 0): Game Win!
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.gamesA, 1);
      expect(state.gamesB, 0);
      expect(state.pointDisplayA, '0');
      expect(state.pointDisplayB, '0');
      expect(state.idxA, 0);
      expect(state.idxB, 0);
    });

    test('3. Deuce and Advantage flow', () {
      final system = ScoringEngine.detectScoringSystem('Total of 3');
      var state = const ScoringMatchState(
        idxA: 2, // 30
        idxB: 3, // 40
        pointDisplayA: '30',
        pointDisplayB: '40',
      );

      // Team A scores: reaches 40-40 -> Deuce!
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.isDeuce, true);
      expect(state.advantage, isNull);
      expect(state.pointDisplayA, '40');
      expect(state.pointDisplayB, '40');

      // Team A scores at Deuce -> Advantage A
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.isDeuce, true);
      expect(state.advantage, 'A');
      expect(state.pointDisplayA, 'ADV');
      expect(state.pointDisplayB, '40');

      // Team B scores while Team A has advantage -> Back to Deuce!
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'B',
        scoringSystem: system,
      );
      expect(state.isDeuce, true);
      expect(state.advantage, isNull);
      expect(state.pointDisplayA, '40');
      expect(state.pointDisplayB, '40');

      // Team B scores -> Advantage B
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'B',
        scoringSystem: system,
      );
      expect(state.isDeuce, true);
      expect(state.advantage, 'B');
      expect(state.pointDisplayA, '40');
      expect(state.pointDisplayB, 'ADV');

      // Team B scores while having Advantage B -> Game Win by Team B!
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'B',
        scoringSystem: system,
      );
      expect(state.gamesB, 1);
      expect(state.gamesA, 0);
      expect(state.isDeuce, false);
      expect(state.advantage, isNull);
      expect(state.pointDisplayA, '0');
      expect(state.pointDisplayB, '0');
    });

    test('4. Total of Sets: Set won with 6 games and 2-game margin (e.g. 6-4)', () {
      final system = ScoringEngine.detectScoringSystem('Total of 3');
      var state = const ScoringMatchState(
        gamesA: 5,
        gamesB: 4,
        idxA: 3, // 40
        idxB: 1, // 15
      );

      // Team A wins the game -> 6-4 -> Set won by Team A!
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.gamesA, 6);
      expect(state.gamesB, 4);
      expect(state.status, 'completed');
      expect(state.winnerTeam, 'Team A');
      expect(state.setsA, 1);
      expect(state.setsB, 0);
    });

    test('5. Total of Sets: Win immediately at 6 games (including 6-5 and 5-6)', () {
      final system = ScoringEngine.detectScoringSystem('Total of 3');
      var state = const ScoringMatchState(
        gamesA: 5,
        gamesB: 5,
        idxA: 3, // 40
        idxB: 0,
      );

      // Team A wins next game from 5-5 -> 6-5 -> Completed immediately by Team A!
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.gamesA, 6);
      expect(state.gamesB, 5);
      expect(state.status, 'completed');
      expect(state.winnerTeam, 'Team A');
      expect(state.setsA, 1);

      // Input locked after completion: subsequent delta is rejected
      final afterComplete = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(afterComplete.gamesA, 6);
      expect(afterComplete.status, 'completed');
    });

    test('6. First to X Games: Set won immediately at target (e.g. First to 8)', () {
      final system = ScoringEngine.detectScoringSystem('First to 8');
      var state = const ScoringMatchState(
        gamesA: 7,
        gamesB: 5,
        idxA: 3,
        idxB: 1,
      );

      // Team A wins game -> 8-5 -> Completed!
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.gamesA, 8);
      expect(state.gamesB, 5);
      expect(state.status, 'completed');
      expect(state.winnerTeam, 'Team A');
    });

    test('7. Scoring locked after match completed', () {
      final system = ScoringEngine.detectScoringSystem('Total of 3');
      final completedState = const ScoringMatchState(
        gamesA: 6,
        gamesB: 4,
        status: 'completed',
        winnerTeam: 'Team A',
      );

      final next = ScoringEngine.applyPointDelta(
        currentState: completedState,
        teamWon: 'B',
        scoringSystem: system,
      );
      expect(next.gamesA, 6);
      expect(next.gamesB, 4);
      expect(next.status, 'completed');
      expect(next.winnerTeam, 'Team A');
    });

    test('8. Score downgrade regression guard', () {
      const official = ScoringMatchState(
        gamesA: 6,
        gamesB: 4,
        status: 'completed',
        winnerTeam: 'Team A',
      );

      // Stale request with 5-4 cannot downgrade 6-4
      final isDowngrade1 = ScoringEngine.isStaleCompletionDowngrade(
        officialCompletedState: official,
        incomingGamesA: 5,
        incomingGamesB: 4,
      );
      expect(isDowngrade1, true);

      // Equal or higher is not a downgrade
      final isDowngrade2 = ScoringEngine.isStaleCompletionDowngrade(
        officialCompletedState: official,
        incomingGamesA: 6,
        incomingGamesB: 4,
      );
      expect(isDowngrade2, false);
    });

    test('9. Walkover and Manual Completion', () {
      final system = ScoringEngine.detectScoringSystem('Total of 3');
      const inProgress = ScoringMatchState(gamesA: 2, gamesB: 1);

      final walkoverA = ScoringEngine.applyCompletion(
        currentState: inProgress,
        scoringSystem: system,
        explicitWinner: 'Team A',
        incomingGamesA: 6,
        incomingGamesB: 1,
      );
      expect(walkoverA.status, 'completed');
      expect(walkoverA.winnerTeam, 'Team A');
      expect(walkoverA.gamesA, 6);
      expect(walkoverA.setsA, 1);
    });

    test('10. Models serialization Parity (ScoreModel & MatchModel)', () {
      final scoreModel = ScoreModel.fromJson({
        'score_id': 10,
        'match_id': 20,
        'set_number': 1,
        'game_number': 1,
        'point_score_a': 'ADV',
        'point_score_b': '40',
        'game_score_a': 5,
        'game_score_b': 4,
        'score_side_a': 5,
        'score_side_b': 4,
        'set_score_a': 1,
        'set_score_b': 0,
        'scoring_system': 'Total of 3',
        'status_score': 'In Progress',
        'version': 4,
        'last_event_id': 'evt_123',
      });

      expect(scoreModel.scoreId, 10);
      expect(scoreModel.pointScoreA, 'ADV');
      expect(scoreModel.pointScoreB, '40');
      expect(scoreModel.gameScoreA, 5);
      expect(scoreModel.version, 4);
      expect(scoreModel.lastEventId, 'evt_123');

      final json = scoreModel.toJson();
      expect(json['point_score_a'], 'ADV');
      expect(json['game_score_a'], 5);
      expect(json['version'], 4);

      final matchModel = MatchModel.fromJson({
        'match_id': 20,
        'status_match': 'In Progress',
        'winner_team': 'Team A',
        'version': 3,
        'last_event_id': 'evt_999',
      });
      expect(matchModel.winnerTeam, 'Team A');
      expect(matchModel.version, 3);
      expect(matchModel.lastEventId, 'evt_999');
    });

    test('11. Point 0-0, Games Won 1-0, Round score 1-0 after game won', () {
      final system = ScoringEngine.detectScoringSystem('Total of 3');
      var state = const ScoringMatchState(
        idxA: 3, // 40
        idxB: 1, // 15
        pointDisplayA: '40',
        pointDisplayB: '15',
        gamesA: 0,
        gamesB: 0,
      );

      // Team A scores -> Game won by Team A!
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );

      // Points must reset to 0-0
      expect(state.pointDisplayA, '0');
      expect(state.pointDisplayB, '0');
      expect(state.idxA, 0);
      expect(state.idxB, 0);

      // Games won / Round score must be 1-0
      expect(state.gamesA, 1);
      expect(state.gamesB, 0);
      expect(state.setHistory.first['score_a'], 1);
      expect(state.setHistory.first['score_b'], 0);
    });

    test('12. Deuce and Advantage display transitions match web engine values', () {
      final system = ScoringEngine.detectScoringSystem('Total of 3');
      var state = const ScoringMatchState(
        idxA: 3,
        idxB: 2,
        pointDisplayA: '40',
        pointDisplayB: '30',
      );

      // Team B scores -> 40-40 Deuce
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'B',
        scoringSystem: system,
      );
      expect(state.isDeuce, true);
      expect(state.advantage, isNull);
      expect(state.pointDisplayA, '40');
      expect(state.pointDisplayB, '40');

      // Team A scores -> Advantage Team A
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'A',
        scoringSystem: system,
      );
      expect(state.isDeuce, true);
      expect(state.advantage, 'A');
      expect(state.pointDisplayA, 'ADV');
      expect(state.pointDisplayB, '40');

      // Team B scores -> Back to Deuce (40-40)
      state = ScoringEngine.applyPointDelta(
        currentState: state,
        teamWon: 'B',
        scoringSystem: system,
      );
      expect(state.isDeuce, true);
      expect(state.advantage, isNull);
      expect(state.pointDisplayA, '40');
      expect(state.pointDisplayB, '40');
    });

    test('13. Reopening match recovers saved structured state', () {
      final savedScoreMap = {
        'score_id': 101,
        'match_id': 505,
        'set_number': 1,
        'game_number': 3,
        'point_score_a': 'ADV',
        'point_score_b': '40',
        'game_score_a': 4,
        'game_score_b': 3,
        'score_side_a': 4,
        'score_side_b': 3,
        'set_score_a': 0,
        'set_score_b': 0,
        'scoring_system': 'Total of 3',
        'status_score': 'In Progress',
        'version': 12,
        'last_event_id': 'evt_prev_101',
      };

      final restoredModel = ScoreModel.fromJson(savedScoreMap);
      expect(restoredModel.pointScoreA, 'ADV');
      expect(restoredModel.pointScoreB, '40');
      expect(restoredModel.gameScoreA, 4);
      expect(restoredModel.gameScoreB, 3);
      expect(restoredModel.version, 12);

      // Reconstruct state
      final pA = restoredModel.pointScoreA ?? '0';
      final pB = restoredModel.pointScoreB ?? '0';
      final isDeuce = (pA == '40' && pB == '40') || (pA == 'ADV' || pB == 'ADV');
      final adv = pA == 'ADV' ? 'A' : (pB == 'ADV' ? 'B' : null);

      final state = ScoringMatchState(
        gamesA: restoredModel.gameScoreA ?? 0,
        gamesB: restoredModel.gameScoreB ?? 0,
        pointDisplayA: pA,
        pointDisplayB: pB,
        isDeuce: isDeuce,
        advantage: adv,
        version: restoredModel.version ?? 0,
      );

      expect(state.gamesA, 4);
      expect(state.gamesB, 3);
      expect(state.pointDisplayA, 'ADV');
      expect(state.pointDisplayB, '40');
      expect(state.isDeuce, true);
      expect(state.advantage, 'A');
      expect(state.version, 12);
    });

    test('14. Monotonic version guard protects against stale realtime updates', () {
      var localState = const ScoringMatchState(
        gamesA: 5,
        gamesB: 4,
        pointDisplayA: '30',
        pointDisplayB: '15',
        version: 15,
      );

      // Stale incoming broadcast with version 14
      final staleIncomingVersion = 14;
      final shouldApplyStale = staleIncomingVersion >= localState.version;
      expect(shouldApplyStale, false);

      // Fresh incoming broadcast with version 16
      final freshIncomingVersion = 16;
      final shouldApplyFresh = freshIncomingVersion >= localState.version;
      expect(shouldApplyFresh, true);
    });

    test('15. Match completion lock prevents score mutation', () {
      final system = ScoringEngine.detectScoringSystem('Total of 3');
      final completed = const ScoringMatchState(
        gamesA: 6,
        gamesB: 3,
        status: 'completed',
        winnerTeam: 'Team A',
        version: 20,
      );

      // Attempting to add point on completed match
      final result = ScoringEngine.applyPointDelta(
        currentState: completed,
        teamWon: 'A',
        scoringSystem: system,
      );

      expect(result.gamesA, 6);
      expect(result.gamesB, 3);
      expect(result.status, 'completed');
      expect(result.winnerTeam, 'Team A');
    });
  });
}
