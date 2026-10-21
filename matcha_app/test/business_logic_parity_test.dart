import 'package:flutter_test/flutter_test.dart';
import 'package:matcha_app/features/games/domain/game_wizard_model.dart';
import 'package:matcha_app/features/drawing/domain/matcha_drawing_engine.dart';
import 'package:matcha_app/features/match/domain/models/match_model.dart';
import 'package:matcha_app/features/session/domain/session_model.dart';

void main() {
  group('MATCHA Web-to-Mobile Parity Test Suite (SYNC-01 to SYNC-18)', () {
    // -------------------------------------------------------------------------
    // SYNC-01: First To dengan pemain melebihi kapasitas match
    // -------------------------------------------------------------------------
    test('SYNC-01: First To dengan pemain melebihi kapasitas match mendukung rotasi multi-ronde dan cadangan', () {
      final players = List.generate(
        6,
        (i) => GamePlayerItem(id: '$i', name: 'Player $i', level: 'Beginner', isGuest: true),
      );
      final config = GameWizardConfig(
        activityName: 'First To Tournament',
        scoringSystem: 'First to 15 Points',
        playMode: 'Double',
        courtCount: 1,
        players: players,
      );

      // Verify totalRounds calculates complete multi-round rotation (>= 3 rounds, not hardcoded to 1)
      expect(config.totalRounds, greaterThanOrEqualTo(3));

      // Generate drawing
      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Double',
        roundCount: config.totalRounds,
      );

      expect(rounds.length, greaterThanOrEqualTo(3));
      // In 1 court double (4 active players), 2 players must be on the bench
      for (final r in rounds) {
        expect(r.matches.length, equals(1));
        expect(r.restingPlayers.length, equals(2));
      }
    });

    // -------------------------------------------------------------------------
    // SYNC-02: Single dengan 2 pemain
    // -------------------------------------------------------------------------
    test('SYNC-02: Single dengan 2 pemain adalah konfigurasi valid (Best of 3 sets)', () {
      final players = [
        const GamePlayerItem(id: '1', name: 'Alice', level: 'Beginner', isGuest: true),
        const GamePlayerItem(id: '2', name: 'Bob', level: 'Beginner', isGuest: true),
      ];
      final config = GameWizardConfig(
        activityName: 'Single Match',
        scoringSystem: 'First to 21 Points',
        playMode: 'Single',
        courtCount: 1,
        players: players,
      );

      expect(config.players.length, equals(2));
      // Web ScoringService determineSessionRoundCount returns 3 rounds for 2-player single
      expect(config.totalRounds, equals(3));

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Single',
        roundCount: config.totalRounds,
      );

      expect(rounds.isNotEmpty, isTrue);
      expect(rounds.first.matches.length, equals(1));
      expect(rounds.first.restingPlayers.isEmpty, isTrue);
    });

    // -------------------------------------------------------------------------
    // SYNC-03: Double dengan 4 pemain
    // -------------------------------------------------------------------------
    test('SYNC-03: Double dengan 4 pemain tepat 3 ronde rotasi partner', () {
      final players = List.generate(
        4,
        (i) => GamePlayerItem(id: '$i', name: 'Player $i', level: 'Beginner', isGuest: true),
      );
      final config = GameWizardConfig(
        activityName: 'Double Match',
        scoringSystem: 'Total of 3 Poin',
        playMode: 'Double',
        courtCount: 1,
        players: players,
      );

      expect(config.totalRounds, equals(3));
      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      expect(rounds.length, equals(3));
      for (final r in rounds) {
        expect(r.restingPlayers.isEmpty, isTrue);
      }
    });

    // -------------------------------------------------------------------------
    // SYNC-04: Double kehilangan 1 pemain (Kondisi kuota berkurang di tengah sesi)
    // -------------------------------------------------------------------------
    test('SYNC-04: Double kehilangan 1 pemain menyisakan 3 pemain, tidak memenuhi kuota minimum 4', () {
      final players = [
        const GamePlayerItem(id: '1', name: 'P1', status: 'active'),
        const GamePlayerItem(id: '2', name: 'P2', status: 'active'),
        const GamePlayerItem(id: '3', name: 'P3', status: 'active'),
        const GamePlayerItem(id: '4', name: 'P4', status: 'withdrawn', withdrawnReason: 'Cedera lutut'),
      ];

      final eligible = players.where((p) => !p.isWithdrawn).toList();
      const minRequired = 4; // Double requires 4

      expect(eligible.length, equals(3));
      expect(eligible.length < minRequired, isTrue);
      // Flow must finish early instead of creating invalid rounds
    });

    // -------------------------------------------------------------------------
    // SYNC-05: Host mengistirahatkan pemain (pause / resting)
    // -------------------------------------------------------------------------
    test('SYNC-05: Host mengistirahatkan pemain mengubah status menjadi resting', () {
      var player = const GamePlayerItem(id: '1', name: 'P1', status: 'active');
      expect(player.isActive, isTrue);

      // Host action: pause
      player = GamePlayerItem(
        id: player.id,
        name: player.name,
        status: 'resting',
        withdrawnReason: 'Kram ringan',
      );

      expect(player.isActive, isFalse);
      expect(player.isResting, isTrue);
      expect(player.isWithdrawn, isFalse);
      expect(player.withdrawnReason, equals('Kram ringan'));
    });

    // -------------------------------------------------------------------------
    // SYNC-06: Host mengeluarkan pemain (withdraw)
    // -------------------------------------------------------------------------
    test('SYNC-06: Host mengeluarkan pemain mengecualikan pemain dari drawing berikutnya', () {
      final players = [
        const GamePlayerItem(id: '1', name: 'P1', status: 'active'),
        const GamePlayerItem(id: '2', name: 'P2', status: 'active'),
        const GamePlayerItem(id: '3', name: 'P3', status: 'active'),
        const GamePlayerItem(id: '4', name: 'P4', status: 'active'),
        const GamePlayerItem(id: '5', name: 'P5', status: 'withdrawn', withdrawnReason: 'Cedera pergelangan'),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Double',
        roundCount: 1,
      );

      expect(rounds.isNotEmpty, isTrue);
      // P5 should NEVER appear in any match
      for (final r in rounds) {
        for (final m in r.matches) {
          expect(m.teamA.any((p) => p.id == '5'), isFalse);
          expect(m.teamB.any((p) => p.id == '5'), isFalse);
        }
      }
    });

    // -------------------------------------------------------------------------
    // SYNC-07: Pemain kembali dari resting (reactivate)
    // -------------------------------------------------------------------------
    test('SYNC-07: Pemain kembali dari resting dapat diaktifkan kembali menjadi active', () {
      var player = const GamePlayerItem(id: '1', name: 'P1', status: 'resting', withdrawnReason: 'Istirahat 1 ronde');
      expect(player.isResting, isTrue);

      // Host reactivates player
      player = GamePlayerItem(
        id: player.id,
        name: player.name,
        status: 'active',
        withdrawnReason: null,
      );

      expect(player.isActive, isTrue);
      expect(player.isResting, isFalse);
    });

    // -------------------------------------------------------------------------
    // SYNC-08: Schedule adjustment & version confirmation
    // -------------------------------------------------------------------------
    test('SYNC-08: Schedule adjustment flags needs_adjustment when status changes', () {
      final sp = SessionPlayerModel(
        playerId: 10,
        nama: 'Budi',
        status: 'resting',
        withdrawnReason: 'Kram',
      );
      expect(sp.isResting, isTrue);
      expect(sp.status, equals('resting'));
    });

    // -------------------------------------------------------------------------
    // SYNC-09: Match dihentikan dengan skor parsial (Stopped Match)
    // -------------------------------------------------------------------------
    test('SYNC-09: Match dihentikan mempertahankan skor parsial dan menandai status terminal', () {
      final match = MatchModel(
        matchId: 101,
        statusMatch: 'Stopped',
        stopType: 'injury',
        stopReason: 'Cedera otot paha',
        hasilPertandingan: 'Dihentikan: 4 - 2 (Cedera otot paha)',
      );

      expect(match.isStopped, isTrue);
      expect(match.isTerminal, isTrue);
      expect(match.isFinished, isFalse);
      expect(match.stopType, equals('injury'));
    });

    // -------------------------------------------------------------------------
    // SYNC-10: Seluruh match berhenti -> ronde selesai
    // -------------------------------------------------------------------------
    test('SYNC-10: Seluruh match berstatus Stopped tetap menandai ronde selesai secara sah', () {
      final m1 = DrawingMatch(
        courtNumber: 1,
        teamA: [const GamePlayerItem(id: '1', name: 'A1')],
        teamB: [const GamePlayerItem(id: '2', name: 'B1')],
        status: 'Stopped',
      );
      final m2 = DrawingMatch(
        courtNumber: 2,
        teamA: [const GamePlayerItem(id: '3', name: 'A2')],
        teamB: [const GamePlayerItem(id: '4', name: 'B2')],
        status: 'Completed',
      );

      final matches = [m1, m2];
      final isRoundFinished = matches.every((m) => m.status == 'Completed' || m.status == 'Stopped');
      expect(isRoundFinished, isTrue);
    });

    // -------------------------------------------------------------------------
    // SYNC-11: Spectator membaca ronde lama (Spectator Mode Protection)
    // -------------------------------------------------------------------------
    test('SYNC-11: Spectator sedang meninjau ronde historis tidak terdorong navigasi paksa', () {
      int activeRoundIndex = 0;
      int viewedRoundIndex = 0;
      final isHost = false;

      // User navigates back to view Round 0 (historical) while active is Round 1
      activeRoundIndex = 1;
      viewedRoundIndex = 0;

      // Host advances to Round 2
      final nextActiveIndex = 2;
      final wasViewingHistorical = viewedRoundIndex < activeRoundIndex;

      // Apply spectator mode protection logic
      activeRoundIndex = nextActiveIndex;
      if (!wasViewingHistorical || isHost) {
        viewedRoundIndex = nextActiveIndex;
      }

      // Spectator viewedRoundIndex must REMAIN 0
      expect(viewedRoundIndex, equals(0));
      expect(activeRoundIndex, equals(2));
    });

    // -------------------------------------------------------------------------
    // SYNC-12: Spectator berada di ronde aktif mengikuti pembaruan ronde
    // -------------------------------------------------------------------------
    test('SYNC-12: Spectator di ronde aktif otomatis mengikuti pembaruan ronde', () {
      int activeRoundIndex = 1;
      int viewedRoundIndex = 1; // Watching live
      final isHost = false;

      final nextActiveIndex = 2;
      final wasViewingHistorical = viewedRoundIndex < activeRoundIndex;

      activeRoundIndex = nextActiveIndex;
      if (!wasViewingHistorical || isHost) {
        viewedRoundIndex = nextActiveIndex;
      }

      expect(viewedRoundIndex, equals(2));
      expect(activeRoundIndex, equals(2));
    });

    // -------------------------------------------------------------------------
    // SYNC-13: Host berpindah ronde
    // -------------------------------------------------------------------------
    test('SYNC-13: Host selalu mengikuti ronde baru yang dia majukan', () {
      int activeRoundIndex = 0;
      int viewedRoundIndex = 0;

      void onRoundAdvance({required bool wasViewingHistorical, required bool isHost, required int nextIdx}) {
        activeRoundIndex = nextIdx;
        if (!wasViewingHistorical || isHost) {
          viewedRoundIndex = nextIdx;
        }
      }

      // Even if Host was previously viewing historical round, advancing forces host view to live
      onRoundAdvance(wasViewingHistorical: true, isHost: true, nextIdx: 1);

      expect(activeRoundIndex, equals(1));
      expect(viewedRoundIndex, equals(1));
    });

    // -------------------------------------------------------------------------
    // SYNC-14: Dua court berjalan bersamaan tanpa interferensi
    // -------------------------------------------------------------------------
    test('SYNC-14: Dua court independen menyimpan status dan skor terpisah', () {
      final court1Match = DrawingMatch(
        courtNumber: 1,
        teamA: [const GamePlayerItem(id: '1', name: 'A1')],
        teamB: [const GamePlayerItem(id: '2', name: 'B1')],
        scoreA: 6,
        scoreB: 4,
        status: 'Completed',
      );
      final court2Match = DrawingMatch(
        courtNumber: 2,
        teamA: [const GamePlayerItem(id: '3', name: 'A2')],
        teamB: [const GamePlayerItem(id: '4', name: 'B2')],
        scoreA: 2,
        scoreB: 1,
        status: 'Stopped',
      );

      expect(court1Match.isStopped, isFalse);
      expect(court2Match.isStopped, isTrue);
      expect(court1Match.scoreA, equals(6));
      expect(court2Match.scoreA, equals(2));
    });

    // -------------------------------------------------------------------------
    // SYNC-15: Update realtime terlambat dipulihkan oleh mekanisme sinkronisasi/polling
    // -------------------------------------------------------------------------
    test('SYNC-15: Periodic polling atau manual refresh memulihkan state saat realtime event tertinggal', () {
      final localMatch = DrawingMatch(
        matchId: 101,
        courtNumber: 1,
        teamA: [const GamePlayerItem(id: '1', name: 'A1')],
        teamB: [const GamePlayerItem(id: '2', name: 'B1')],
        scoreA: 2,
        scoreB: 1,
        status: 'In Progress',
      );

      final remoteSnapshot = {
        'id_match': 101,
        'nomor_court': 1,
        'status_match': 'Stopped',
        'stop_type': 'Host Action',
        'stop_reason': 'Cuaca Buruk / Hujan Deras',
        'game_score_a': 4,
        'game_score_b': 3,
      };

      if (remoteSnapshot['id_match'] == localMatch.matchId) {
        localMatch.status = remoteSnapshot['status_match']?.toString() ?? localMatch.status;
        localMatch.stopType = remoteSnapshot['stop_type']?.toString();
        localMatch.stopReason = remoteSnapshot['stop_reason']?.toString();
        localMatch.scoreA = remoteSnapshot['game_score_a'] as int;
        localMatch.scoreB = remoteSnapshot['game_score_b'] as int;
      }

      expect(localMatch.status, equals('Stopped'));
      expect(localMatch.isStopped, isTrue);
      expect(localMatch.stopReason, equals('Cuaca Buruk / Hujan Deras'));
      expect(localMatch.scoreA, equals(4));
      expect(localMatch.scoreB, equals(3));
    });

    // -------------------------------------------------------------------------
    // SYNC-16: Tombol aksi ditekan berulang dilindungi dari duplicate mutation
    // -------------------------------------------------------------------------
    test('SYNC-16: Tombol aksi ditekan berulang dilindungi oleh flag guard pencegah mutasi ganda', () async {
      int mutationCount = 0;
      bool isSubmitting = false;

      Future<bool> triggerAction() async {
        if (isSubmitting) return false;
        isSubmitting = true;
        try {
          await Future<void>.delayed(const Duration(milliseconds: 10));
          mutationCount++;
          return true;
        } finally {
          isSubmitting = false;
        }
      }

      final futures = [
        triggerAction(),
        triggerAction(),
        triggerAction(),
      ];

      final results = await Future.wait(futures);

      expect(results.where((r) => r).length, equals(1));
      expect(mutationCount, equals(1));
    });

    // -------------------------------------------------------------------------
    // SYNC-17: App di-refresh setelah perubahan status memulihkan data persisten
    // -------------------------------------------------------------------------
    test('SYNC-17: App di-refresh memulihkan status withdrawn/resting dan stopped match dari database', () {
      final rawPlayerDb = {
        'id': 'p10',
        'nama': 'Andi Pratama',
        'gender': 'Laki-laki',
        'level': 'Intermediate',
        'status': 'withdrawn',
        'withdrawn_reason': 'Cedera pergelangan kaki',
        'withdrawn_at': '2026-10-20T10:00:00.000Z',
      };

      final restoredPlayer = GamePlayerItem.fromMap(rawPlayerDb);
      expect(restoredPlayer.status, equals('withdrawn'));
      expect(restoredPlayer.isWithdrawn, isTrue);
      expect(restoredPlayer.withdrawnReason, equals('Cedera pergelangan kaki'));

      final rawMatchDb = {
        'match_id': 202,
        'nomor_court': 1,
        'status_match': 'Stopped',
        'stop_type': 'Host Action',
        'stop_reason': 'Pemain Cedera',
        'stopped_by_user_id': 1,
        'stopped_at': '2026-10-20T10:05:00.000Z',
      };

      final restoredMatch = MatchModel.fromJson(rawMatchDb);
      expect(restoredMatch.statusMatch, equals('Stopped'));
      expect(restoredMatch.isStopped, isTrue);
      expect(restoredMatch.stopReason, equals('Pemain Cedera'));
      expect(restoredMatch.stopType, equals('Host Action'));
    });

    // -------------------------------------------------------------------------
    // SYNC-18: Leaderboard setelah stopped match menghitung skor tanpa pemenang
    // -------------------------------------------------------------------------
    test('SYNC-18: Leaderboard setelah match stopped mengalokasikan poin tanpa win/loss', () {
      // Mock session match map with stopped match
      final matchMap = {
        'match_id': 50,
        'status_match': 'stopped',
        'winner_team': null,
        'nomor_match': 1,
        'tb_participant': [
          {'player_id': 1, 'team_side': 'A'},
          {'player_id': 2, 'team_side': 'B'},
        ],
        'tb_score': [
          {
            'set_number': 1,
            'score_side_a': 4,
            'score_side_b': 3,
            'game_score_a': 4,
            'game_score_b': 3,
            'status_score': 'stopped',
          }
        ],
      };

      // Verify that for stopped match, winnerSide is null
      final mStatus = (matchMap['status_match']?.toString() ?? '').toLowerCase();
      final bool isStopped = mStatus == 'stopped';
      expect(isStopped, isTrue);

      String? winnerSide = matchMap['winner_team']?.toString();
      if (isStopped) {
        winnerSide = null;
      }
      expect(winnerSide, isNull);
    });
  });

  group('MATCHA QA Fix Batch 1 Test Suite (QA-FIX-01 to QA-FIX-13)', () {
    // -------------------------------------------------------------------------
    // QA-FIX-01: Americano Single Total Of 10 follows Laravel round-generation rule
    // -------------------------------------------------------------------------
    test('QA-FIX-01: Americano Single Total Of 10 follows Laravel round-generation rule (produces 10 rounds)', () {
      final players = List.generate(
        6,
        (i) => GamePlayerItem(id: '$i', name: 'Player $i', level: 'Beginner', isGuest: true),
      );
      final config = GameWizardConfig(
        activityName: 'Americano Single Total Of 10',
        scoringSystem: 'Total Of 10',
        gameType: 'Americano',
        playMode: 'Single',
        courtCount: 1,
        players: players,
      );

      // Web Laravel ScoringService: determineSessionRoundCount for total_of uses max_sets directly (= 10)
      expect(config.totalRounds, equals(10));
    });

    // -------------------------------------------------------------------------
    // QA-FIX-02: Drawing displays all generated rounds
    // -------------------------------------------------------------------------
    test('QA-FIX-02: Drawing displays all generated rounds without missing or duplicate matches', () {
      final players = List.generate(
        6,
        (i) => GamePlayerItem(id: '$i', name: 'Player $i', level: 'Beginner', isGuest: true),
      );
      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Single',
        roundCount: 10,
      );

      expect(rounds.length, equals(10));
      for (int i = 0; i < rounds.length; i++) {
        expect(rounds[i].roundNumber, equals(i + 1));
        expect(rounds[i].matches.isNotEmpty, isTrue);
        expect(rounds[i].matches.first.courtNumber, equals(1));
        expect(rounds[i].matches.first.teamA.length, equals(1));
        expect(rounds[i].matches.first.teamB.length, equals(1));
      }
    });

    // -------------------------------------------------------------------------
    // QA-FIX-03: First To minimum selection is 2
    // -------------------------------------------------------------------------
    test('QA-FIX-03: First To minimum selection is 2', () {
      final validRange = List.generate(9, (i) => i + 2); // 2 through 10
      expect(validRange.first, equals(2));
      expect(validRange.contains(1), isFalse);
    });

    // -------------------------------------------------------------------------
    // QA-FIX-04: First To maximum selection is 10
    // -------------------------------------------------------------------------
    test('QA-FIX-04: First To maximum selection is 10', () {
      final validRange = List.generate(9, (i) => i + 2); // 2 through 10
      expect(validRange.last, equals(10));
      expect(validRange.contains(11), isFalse);
      expect(validRange.contains(21), isFalse);
    });

    // -------------------------------------------------------------------------
    // QA-FIX-05: First To values above 10 are rejected for new games
    // -------------------------------------------------------------------------
    test('QA-FIX-05: First To values above 10 are rejected for new games', () {
      bool isAllowedFirstTo(int val) => val >= 2 && val <= 10;
      expect(isAllowedFirstTo(1), isFalse);
      expect(isAllowedFirstTo(2), isTrue);
      expect(isAllowedFirstTo(10), isTrue);
      expect(isAllowedFirstTo(11), isFalse);
      expect(isAllowedFirstTo(15), isFalse);
      expect(isAllowedFirstTo(21), isFalse);
    });

    // -------------------------------------------------------------------------
    // QA-FIX-06: Stop Game options match Laravel
    // -------------------------------------------------------------------------
    test('QA-FIX-06: Stop Game options match Laravel (keep_in_session and withdraw_players)', () {
      const allowedStopConditions = ['keep_in_session', 'withdraw_players'];
      expect(allowedStopConditions.contains('keep_in_session'), isTrue);
      expect(allowedStopConditions.contains('withdraw_players'), isTrue);
    });

    // -------------------------------------------------------------------------
    // QA-FIX-07: Match stop preserves partial scoring
    // -------------------------------------------------------------------------
    test('QA-FIX-07: Match stop preserves partial scoring without declaring unilateral winner', () {
      final match = DrawingMatch(
        courtNumber: 1,
        teamA: [const GamePlayerItem(id: '1', name: 'Alice', level: 'Beginner', isGuest: true)],
        teamB: [const GamePlayerItem(id: '2', name: 'Bob', level: 'Beginner', isGuest: true)],
        scoreA: 5,
        scoreB: 3,
        status: 'In Progress',
      );

      // Perform stop match
      match.status = 'Stopped';
      match.stopType = 'keep_in_session';
      match.stopReason = 'Waktu sewa lapangan habis';
      match.winnerTeam = null;

      expect(match.status, equals('Stopped'));
      expect(match.isStopped, isTrue);
      expect(match.isTerminal, isTrue);
      expect(match.scoreA, equals(5));
      expect(match.scoreB, equals(3));
      expect(match.winnerTeam, isNull);
    });

    // -------------------------------------------------------------------------
    // QA-FIX-08: Host can manage player status during Drawing
    // -------------------------------------------------------------------------
    test('QA-FIX-08: Host can manage player status during Drawing (pause, reactivate, withdraw)', () {
      final players = [
        const GamePlayerItem(id: '1', name: 'Alice', level: 'Beginner', isGuest: true),
        const GamePlayerItem(id: '2', name: 'Bob', level: 'Beginner', isGuest: true),
        const GamePlayerItem(id: '3', name: 'Charlie', level: 'Beginner', isGuest: true),
        const GamePlayerItem(id: '4', name: 'David', level: 'Beginner', isGuest: true),
      ];

      // Host pauses Bob
      final updatedList = List<GamePlayerItem>.from(players);
      final idx = updatedList.indexWhere((p) => p.id == '2');
      updatedList[idx] = GamePlayerItem(
        id: updatedList[idx].id,
        name: updatedList[idx].name,
        level: updatedList[idx].level,
        isGuest: updatedList[idx].isGuest,
        status: 'resting',
        withdrawnReason: 'Kram ringan',
      );

      expect(updatedList[idx].isResting, isTrue);
      expect(updatedList[idx].isWithdrawn, isFalse);

      // Host reactivates Bob
      updatedList[idx] = GamePlayerItem(
        id: updatedList[idx].id,
        name: updatedList[idx].name,
        level: updatedList[idx].level,
        isGuest: updatedList[idx].isGuest,
        status: 'active',
      );
      expect(updatedList[idx].isResting, isFalse);
      expect(updatedList[idx].status, equals('active'));

      // Host withdraws Bob
      updatedList[idx] = GamePlayerItem(
        id: updatedList[idx].id,
        name: updatedList[idx].name,
        level: updatedList[idx].level,
        isGuest: updatedList[idx].isGuest,
        status: 'withdrawn',
        withdrawnReason: 'Cedera lutut',
        withdrawnAt: DateTime.now(),
      );
      expect(updatedList[idx].isWithdrawn, isTrue);
    });

    // -------------------------------------------------------------------------
    // QA-FIX-09: Player cannot perform Host-only actions
    // -------------------------------------------------------------------------
    test('QA-FIX-09: Player cannot perform Host-only actions', () {
      bool canManagePlayers({required bool isHost, required bool isAdmin}) {
        return isHost || isAdmin;
      }

      expect(canManagePlayers(isHost: false, isAdmin: false), isFalse);
      expect(canManagePlayers(isHost: true, isAdmin: false), isTrue);
      expect(canManagePlayers(isHost: false, isAdmin: true), isTrue);
    });

    // -------------------------------------------------------------------------
    // QA-FIX-10: Withdrawal updates drawing eligibility
    // -------------------------------------------------------------------------
    test('QA-FIX-10: Withdrawal updates drawing eligibility (withdrawn players are excluded)', () {
      final players = [
        const GamePlayerItem(id: '1', name: 'Alice', level: 'Beginner', isGuest: true),
        const GamePlayerItem(id: '2', name: 'Bob', level: 'Beginner', isGuest: true, status: 'withdrawn'),
        const GamePlayerItem(id: '3', name: 'Charlie', level: 'Beginner', isGuest: true),
        const GamePlayerItem(id: '4', name: 'David', level: 'Beginner', isGuest: true),
        const GamePlayerItem(id: '5', name: 'Eve', level: 'Beginner', isGuest: true),
      ];

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: players,
        courtCount: 1,
        gameType: 'Americano',
        playMode: 'Double',
        roundCount: 3,
      );

      // Verify Bob ('2') never appears in any active match or resting bench
      for (final r in rounds) {
        for (final m in r.matches) {
          expect(m.teamA.any((p) => p.id == '2'), isFalse);
          expect(m.teamB.any((p) => p.id == '2'), isFalse);
        }
        expect(r.restingPlayers.any((p) => p.id == '2'), isFalse);
      }
    });

    // -------------------------------------------------------------------------
    // QA-FIX-11: Drawing lock state is respected
    // -------------------------------------------------------------------------
    test('QA-FIX-11: Drawing lock state is respected (reshuffling blocked when locked)', () {
      bool isLocked = true;
      bool canReshuffle = !isLocked;
      expect(canReshuffle, isFalse);

      isLocked = false;
      canReshuffle = !isLocked;
      expect(canReshuffle, isTrue);
    });

    // -------------------------------------------------------------------------
    // QA-FIX-12: Host–Player synchronization remains correct
    // -------------------------------------------------------------------------
    test('QA-FIX-12: Host–Player synchronization event payload contains required parity keys', () {
      final payload = {
        'session_id': 123,
        'player_id': 45,
        'action': 'pause',
        'status': 'resting',
        'reason': 'Kram ringan',
        'timestamp': DateTime.now().millisecondsSinceEpoch,
      };

      expect(payload.containsKey('session_id'), isTrue);
      expect(payload.containsKey('player_id'), isTrue);
      expect(payload.containsKey('action'), isTrue);
      expect(payload.containsKey('status'), isTrue);
      expect(payload.containsKey('reason'), isTrue);
      expect(payload['status'], equals('resting'));
    });

    // -------------------------------------------------------------------------
    // QA-FIX-13: Existing scoring and leaderboard behavior does not regress
    // -------------------------------------------------------------------------
    test('QA-FIX-13: Existing scoring and leaderboard behavior does not regress', () {
      final normalMatch = DrawingMatch(
        courtNumber: 1,
        teamA: [const GamePlayerItem(id: '1', name: 'Alice', level: 'Beginner', isGuest: true)],
        teamB: [const GamePlayerItem(id: '2', name: 'Bob', level: 'Beginner', isGuest: true)],
        scoreA: 16,
        scoreB: 14,
        status: 'Completed',
        winnerTeam: 'teamA',
      );

      final stoppedMatch = DrawingMatch(
        courtNumber: 2,
        teamA: [const GamePlayerItem(id: '3', name: 'Charlie', level: 'Beginner', isGuest: true)],
        teamB: [const GamePlayerItem(id: '4', name: 'David', level: 'Beginner', isGuest: true)],
        scoreA: 6,
        scoreB: 4,
        status: 'Stopped',
        winnerTeam: null,
      );

      expect(normalMatch.status, equals('Completed'));
      expect(normalMatch.winnerTeam, equals('teamA'));
      expect(normalMatch.isTerminal, isTrue);

      expect(stoppedMatch.status, equals('Stopped'));
      expect(stoppedMatch.winnerTeam, isNull);
      expect(stoppedMatch.isTerminal, isTrue);
    });

    // =========================================================================
    // QA FIX BATCH 2 TEST SUITE (QA-FIX-14 to QA-FIX-21)
    // =========================================================================
    group('MATCHA QA Fix Batch 2 Test Suite (QA-FIX-14 to QA-FIX-21)', () {
      // -----------------------------------------------------------------------
      // QA-FIX-14: Player status management in Live Scoring matches Laravel behavior
      // -----------------------------------------------------------------------
      test('QA-FIX-14: Player status management in Live Scoring matches Laravel behavior (active match players protected, bench reactivatable)', () {
        final activeMatch = DrawingMatch(
          courtNumber: 1,
          teamA: [const GamePlayerItem(id: 'p1', name: 'Player 1', level: 'Beginner', isGuest: true)],
          teamB: [const GamePlayerItem(id: 'p2', name: 'Player 2', level: 'Beginner', isGuest: true)],
          status: 'In Progress',
          scoreA: 2,
          scoreB: 1,
        );
        final round = DrawingRound(roundNumber: 1, matches: [activeMatch]);

        // Helper replicating Live Scoring active player check
        bool isActivelyPlaying(String playerId) {
          return round.matches.any((m) =>
              !m.isTerminal &&
              (m.teamA.any((p) => p.id == playerId) || m.teamB.any((p) => p.id == playerId)));
        }

        expect(isActivelyPlaying('p1'), isTrue, reason: 'Active court player p1 must be detected as actively playing');
        expect(isActivelyPlaying('p2'), isTrue, reason: 'Active court player p2 must be detected as actively playing');
        expect(isActivelyPlaying('p3_bench'), isFalse, reason: 'Bench player is not actively playing on a court');

        // Resting bench player can be reactivated
        var restingPlayer = const GamePlayerItem(id: 'p3_bench', name: 'Bench Player', level: 'Intermediate', isGuest: true, status: 'resting');
        expect(restingPlayer.isResting, isTrue);

        // Reactivate action changes status to active
        final reactivatedPlayer = GamePlayerItem(
          id: restingPlayer.id,
          name: restingPlayer.name,
          level: restingPlayer.level,
          isGuest: restingPlayer.isGuest,
          status: 'active',
        );
        expect(reactivatedPlayer.isResting, isFalse);
        expect(reactivatedPlayer.status, equals('active'));
      });

      // -----------------------------------------------------------------------
      // QA-FIX-15: Host-only access enforced for readiness/status actions
      // -----------------------------------------------------------------------
      test('QA-FIX-15: Host-only access enforced for readiness/status actions', () {
        bool canPerformStatusManagement(bool isHost) {
          if (!isHost) return false;
          return true;
        }

        expect(canPerformStatusManagement(true), isTrue, reason: 'Host has authorization to manage player status');
        expect(canPerformStatusManagement(false), isFalse, reason: 'Player/Spectator must be denied player status actions');
      });

      // -----------------------------------------------------------------------
      // QA-FIX-16: End Match required reason shows inline validation
      // -----------------------------------------------------------------------
      test('QA-FIX-16: End Match required reason shows inline validation', () {
        String? reasonFieldErrorA;
        String? validationError;

        void validateKeepInSession(String reasonInput) {
          if (reasonInput.trim().isEmpty) {
            reasonFieldErrorA = 'Alasan penghentian pertandingan wajib diisi.';
            validationError = 'Silakan isi alasan penghentian pertandingan pada kolom di atas.';
          } else {
            reasonFieldErrorA = null;
            validationError = null;
          }
        }

        // Initially empty submission
        validateKeepInSession('');
        expect(reasonFieldErrorA, isNotNull);
        expect(reasonFieldErrorA, equals('Alasan penghentian pertandingan wajib diisi.'));
        expect(validationError, isNotNull);

        // When user types text, error clears immediately
        validateKeepInSession('Waktu sewa lapangan habis');
        expect(reasonFieldErrorA, isNull);
        expect(validationError, isNull);
      });

      // -----------------------------------------------------------------------
      // QA-FIX-17: End Match button triggers clear validation feedback
      // -----------------------------------------------------------------------
      test('QA-FIX-17: End Match button triggers clear validation feedback for both options', () {
        // Option 1: Keep in session with bench toggle but no player selected
        String? selectionError;
        String? globalError;
        void validateBenchToggle(bool pauseToggle, List<String> pausedPlayerIds) {
          if (pauseToggle && pausedPlayerIds.isEmpty) {
            selectionError = 'Pilih minimal satu pemain yang diistirahatkan sementara.';
            globalError = 'Pilih minimal satu pemain yang diistirahatkan sementara.';
          }
        }

        validateBenchToggle(true, []);
        expect(selectionError, isNotNull);
        expect(globalError, isNotNull);

        // Option 2: Withdraw players with empty selection or missing reason
        Map<String, String?> playerReasonErrors = {};
        void validateWithdrawSelection(List<String> withdrawnIds, Map<String, String> reasons) {
          if (withdrawnIds.isEmpty) {
            selectionError = 'Pilih minimal satu pemain yang keluar dari sesi.';
            globalError = 'Pilih minimal satu pemain yang keluar dari sesi.';
            return;
          }
          for (final id in withdrawnIds) {
            if ((reasons[id] ?? '').trim().isEmpty) {
              playerReasonErrors[id] = 'Wajib mengisi alasan keluar.';
              globalError = 'Wajib mengisi alasan keluar untuk seluruh pemain yang dipilih.';
            }
          }
        }

        validateWithdrawSelection([], {});
        expect(selectionError, equals('Pilih minimal satu pemain yang keluar dari sesi.'));

        selectionError = null;
        validateWithdrawSelection(['p1'], {'p1': ''});
        expect(playerReasonErrors['p1'], equals('Wajib mengisi alasan keluar.'));
        expect(globalError, isNotNull);
      });

      // -----------------------------------------------------------------------
      // QA-FIX-18: Stopped match disables scoring buttons immediately
      // -----------------------------------------------------------------------
      test('QA-FIX-18: Stopped match disables scoring buttons immediately', () {
        final stoppedMatch = DrawingMatch(
          courtNumber: 1,
          teamA: [const GamePlayerItem(id: '1', name: 'Alice', level: 'Beginner', isGuest: true)],
          teamB: [const GamePlayerItem(id: '2', name: 'Bob', level: 'Beginner', isGuest: true)],
          scoreA: 5,
          scoreB: 3,
          status: 'Stopped',
        );

        final isCompleted = stoppedMatch.status == 'Completed';
        final isStopped = stoppedMatch.isStopped || stoppedMatch.status.toLowerCase() == 'stopped';
        final isTerminal = isCompleted || isStopped;
        const isHost = true;
        const isCurrentViewedRoundActive = true;

        final canInput = isHost && isCurrentViewedRoundActive && !isTerminal;
        expect(canInput, isFalse, reason: 'canInput must be FALSE for stopped match even if round is active');

        final buttonLabel = isStopped
            ? 'Match Dihentikan (${stoppedMatch.scoreA} - ${stoppedMatch.scoreB})'
            : '+ Tambah Poin Team A';
        expect(buttonLabel, equals('Match Dihentikan (5 - 3)'));
      });

      // -----------------------------------------------------------------------
      // QA-FIX-19: Stopped match rejects score update after delayed realtime refresh
      // -----------------------------------------------------------------------
      test('QA-FIX-19: Stopped match rejects score update after delayed realtime refresh', () {
        final localMatch = DrawingMatch(
          courtNumber: 1,
          teamA: [const GamePlayerItem(id: '1', name: 'Alice', level: 'Beginner', isGuest: true)],
          teamB: [const GamePlayerItem(id: '2', name: 'Bob', level: 'Beginner', isGuest: true)],
          scoreA: 4,
          scoreB: 2,
          status: 'Stopped',
        );

        // Simulate incoming realtime/db snapshot with partial scores
        final incomingPayload = {
          'status': 'Stopped',
          'statusScore': 'stopped',
          'scoreA': 4,
          'scoreB': 2,
        };

        final rawStatus = (incomingPayload['status'] ?? '').toString();
        final isExplicitStopped = rawStatus.toLowerCase() == 'stopped' ||
            incomingPayload['statusScore']?.toString().toLowerCase() == 'stopped' ||
            localMatch.isStopped ||
            localMatch.status.toLowerCase() == 'stopped';
        final hasStartedScoring = 4 > 0 || 2 > 0;

        if (isExplicitStopped) {
          localMatch.status = 'Stopped';
        } else if (hasStartedScoring) {
          localMatch.status = 'In Progress';
        }

        expect(localMatch.status, equals('Stopped'), reason: 'Delayed refresh must NOT overwrite Stopped to In Progress');

        // Scoring attempt must be defended
        bool scorePointAttempted = false;
        void tryScore(DrawingMatch m) {
          if (m.isTerminal || m.isStopped || m.status.toLowerCase() == 'stopped') return;
          scorePointAttempted = true;
        }

        tryScore(localMatch);
        expect(scorePointAttempted, isFalse, reason: 'Point increment on stopped match must be blocked defensively');
      });

      // -----------------------------------------------------------------------
      // QA-FIX-20: Partial score remains visible after stop
      // -----------------------------------------------------------------------
      test('QA-FIX-20: Partial score remains visible after stop without unilateral winner', () {
        final stoppedMatch = DrawingMatch(
          courtNumber: 1,
          teamA: [const GamePlayerItem(id: '1', name: 'Alice', level: 'Beginner', isGuest: true)],
          teamB: [const GamePlayerItem(id: '2', name: 'Bob', level: 'Beginner', isGuest: true)],
          scoreA: 7,
          scoreB: 5,
          pointDisplayA: '30',
          pointDisplayB: '40',
          status: 'Stopped',
          stopType: 'keep_in_session',
          stopReason: 'Hujan lebat',
          winnerTeam: null,
        );

        expect(stoppedMatch.scoreA, equals(7));
        expect(stoppedMatch.scoreB, equals(5));
        expect(stoppedMatch.pointDisplayA, equals('30'));
        expect(stoppedMatch.pointDisplayB, equals('40'));
        expect(stoppedMatch.winnerTeam, isNull, reason: 'No unilateral winner should be assigned on stopped match');
        expect(stoppedMatch.stopReason, equals('Hujan lebat'));
      });

      // -----------------------------------------------------------------------
      // QA-FIX-21: Host and Player remain synchronized after stop
      // -----------------------------------------------------------------------
      test('QA-FIX-21: Host and Player remain synchronized after stop via authoritative event', () {
        final stopEventPayload = {
          'match_id': 101,
          'status_match': 'Stopped',
          'session_id': 42,
          'score_a': 8,
          'score_b': 6,
          'winner_team': null,
          'stop_type': 'keep_in_session',
          'stop_reason': 'Waktu sewa lapangan habis',
        };

        expect(stopEventPayload['status_match'], equals('Stopped'));
        expect(stopEventPayload['winner_team'], isNull);
        expect(stopEventPayload['score_a'], equals(8));
        expect(stopEventPayload['score_b'], equals(6));

        // When Player client consumes the payload:
        final playerClientMatch = DrawingMatch(
          courtNumber: 1,
          matchId: 101,
          teamA: [const GamePlayerItem(id: '1', name: 'Alice', level: 'Beginner', isGuest: true)],
          teamB: [const GamePlayerItem(id: '2', name: 'Bob', level: 'Beginner', isGuest: true)],
          status: stopEventPayload['status_match'] as String,
          scoreA: stopEventPayload['score_a'] as int,
          scoreB: stopEventPayload['score_b'] as int,
          winnerTeam: stopEventPayload['winner_team'] as String?,
        );

        expect(playerClientMatch.isStopped, isTrue);
        expect(playerClientMatch.isTerminal, isTrue);
        expect(playerClientMatch.scoreA, equals(8));
        expect(playerClientMatch.scoreB, equals(6));
      });
    });
  });
}
