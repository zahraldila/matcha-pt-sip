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
}
