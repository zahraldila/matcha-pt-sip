import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../storage/matcha_hive_service.dart';
import '../../features/match/data/datasource/match_remote_data_source.dart';

/// Engine latar belakang yang mendeteksi status jaringan
/// dan memproses antrean sinkronisasi Hive (Outbox) ke Supabase secara otomatis.
class MatchaSyncEngine {
  static final MatchaSyncEngine _instance = MatchaSyncEngine._internal();
  static MatchaSyncEngine get instance => _instance;

  MatchaSyncEngine._internal();

  final Connectivity _connectivity = Connectivity();
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  final ValueNotifier<bool> isOnlineNotifier = ValueNotifier<bool>(true);
  final ValueNotifier<int> pendingCountNotifier = ValueNotifier<int>(0);

  bool _isSyncing = false;
  bool get isSyncing => _isSyncing;

  SupabaseClient get _supabase => Supabase.instance.client;
  late final MatchRemoteDataSource _remoteDataSource =
      MatchRemoteDataSource(supabaseClient: _supabase);

  /// Inisialisasi listener koneksi dan cek status awal
  void init() {
    _connectivitySubscription = _connectivity.onConnectivityChanged.listen((results) {
      final isOnline = results.any((r) => r != ConnectivityResult.none);
      isOnlineNotifier.value = isOnline;
      debugPrint('[MatchaSyncEngine] Connectivity changed: isOnline = $isOnline');
      if (isOnline) {
        syncNow();
      }
    });

    _connectivity.checkConnectivity().then((results) {
      final isOnline = results.any((r) => r != ConnectivityResult.none);
      isOnlineNotifier.value = isOnline;
      pendingCountNotifier.value = MatchaHiveService.getPendingSyncCount();
      if (isOnline) {
        syncNow();
      }
    });
  }

  void dispose() {
    _connectivitySubscription?.cancel();
  }

  /// Menjalankan proses sinkronisasi antrean Hive ke Supabase
  Future<void> syncNow() async {
    if (_isSyncing) return;
    if (!isOnlineNotifier.value) {
      pendingCountNotifier.value = MatchaHiveService.getPendingSyncCount();
      return;
    }

    final pendingActions = MatchaHiveService.getAllPendingSyncActions();
    if (pendingActions.isEmpty) {
      pendingCountNotifier.value = 0;
      return;
    }

    _isSyncing = true;
    debugPrint('[MatchaSyncEngine] Starting background sync for ${pendingActions.length} action(s)...');

    try {
      for (final action in pendingActions) {
        final actionId = action['actionId']?.toString() ?? '';
        final actionType = action['actionType']?.toString() ?? '';
        final payload = Map<String, dynamic>.from(action['payload'] as Map? ?? {});

        try {
          if (actionType == 'SAVE_SCORE') {
            await _syncScoreAction(payload);
          } else if (actionType == 'FINISH_MATCH') {
            await _syncFinishMatchAction(payload);
          } else if (actionType == 'FINISH_SESSION') {
            await _syncFinishSessionAction(payload);
          }

          // Hapus aksi dari Hive setelah sukses dikirim
          await MatchaHiveService.removeSyncAction(actionId);
        } catch (e) {
          debugPrint('[MatchaSyncEngine] Error syncing action $actionId: $e');
          // Jika terjadi error jaringan di tengah jalan, hentikan sementara antrean
          break;
        }
      }
    } finally {
      _isSyncing = false;
      pendingCountNotifier.value = MatchaHiveService.getPendingSyncCount();
      debugPrint('[MatchaSyncEngine] Sync run completed. Remaining pending: ${pendingCountNotifier.value}');
    }
  }

  Future<void> _syncScoreAction(Map<String, dynamic> payload) async {
    final matchId = int.tryParse(payload['matchId']?.toString() ?? '') ?? 0;
    if (matchId <= 0) return;

    final setNumber = int.tryParse(payload['setNumber']?.toString() ?? '1') ?? 1;
    final scoreA = int.tryParse(payload['scoreA']?.toString() ?? '0') ?? 0;
    final scoreB = int.tryParse(payload['scoreB']?.toString() ?? '0') ?? 0;
    final gameScoreA = int.tryParse(payload['gameScoreA']?.toString() ?? scoreA.toString()) ?? scoreA;
    final gameScoreB = int.tryParse(payload['gameScoreB']?.toString() ?? scoreB.toString()) ?? scoreB;
    final setScoreA = int.tryParse(payload['setScoreA']?.toString() ?? '');
    final setScoreB = int.tryParse(payload['setScoreB']?.toString() ?? '');
    final pointScoreA = payload['pointScoreA']?.toString();
    final pointScoreB = payload['pointScoreB']?.toString();
    final scoringSystem = payload['scoringSystem']?.toString();
    final statusScore = payload['statusScore']?.toString();
    final winnerTeam = payload['winnerTeam']?.toString();
    final version = int.tryParse(payload['version']?.toString() ?? '1');
    final lastEventId = payload['lastEventId']?.toString();
    final sessionId = payload['sessionId'];

    // 1. Simpan ke database Supabase via RemoteDataSource
    final savedScore = await _remoteDataSource.saveOrUpdateScore(
      matchId: matchId,
      setNumber: setNumber,
      scoreSideA: scoreA,
      scoreSideB: scoreB,
      gameScoreA: gameScoreA,
      gameScoreB: gameScoreB,
      setScoreA: setScoreA,
      setScoreB: setScoreB,
      pointScoreA: pointScoreA,
      pointScoreB: pointScoreB,
      scoringSystem: scoringSystem,
      statusScore: statusScore,
      version: version,
      lastEventId: lastEventId,
    );

    final finalVersion = savedScore.version ?? version ?? 1;

    // 2. Update tb_match jika status selesai
    if (statusScore != null &&
        (statusScore.toLowerCase().contains('final') ||
            statusScore.toLowerCase().contains('completed'))) {
      final now = DateTime.now();
      final nowTime =
          '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';
      await _supabase.from('tb_match').update({
        'status_match': 'Completed',
        'hasil_pertandingan': 'Game Score $scoreA - $scoreB',
        'winner_team': ?winnerTeam,
        'version': finalVersion,
        'last_event_id': ?lastEventId,
        'waktu_selesai': nowTime,
        'updated_at': now.toIso8601String(),
      }).eq('match_id', matchId);
    }

    // 3. Kirim Realtime broadcast jika ada session
    try {
      final channelName = sessionId != null
          ? 'session_$sessionId'
          : 'match_arena_live';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'score_update',
        payload: {
          'match_id': matchId,
          'session_id': sessionId,
          'set_number': setNumber,
          'score_a': scoreA,
          'score_b': scoreB,
          'game_score_a': gameScoreA,
          'game_score_b': gameScoreB,
          'point_score_a': pointScoreA,
          'point_score_b': pointScoreB,
          'set_score_a': setScoreA,
          'set_score_b': setScoreB,
          'scoring_system': scoringSystem,
          'status_score': statusScore,
          'winner_team': winnerTeam,
          'version': finalVersion,
          'last_event_id': lastEventId,
        },
      );
    } catch (_) {}
  }

  Future<void> _syncFinishMatchAction(Map<String, dynamic> payload) async {
    final matchId = int.tryParse(payload['matchId']?.toString() ?? '') ?? 0;
    if (matchId <= 0) return;

    final winnerTeam = payload['winnerTeam']?.toString();
    final hasilPertandingan = payload['hasilPertandingan']?.toString() ?? 'Completed';
    final now = DateTime.now();
    final nowTime =
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';

    await _supabase.from('tb_match').update({
      'status_match': 'Completed',
      'hasil_pertandingan': hasilPertandingan,
      'winner_team': ?winnerTeam,
      'waktu_selesai': nowTime,
      'updated_at': now.toIso8601String(),
    }).eq('match_id', matchId);
  }

  Future<void> _syncFinishSessionAction(Map<String, dynamic> payload) async {
    final sessionId = payload['sessionId'];
    if (sessionId == null) return;
    final parsedSessionId = int.tryParse(sessionId.toString()) ?? sessionId;

    await _supabase.from('tb_session').update({
      'status_session': 'Completed',
      'updated_at': DateTime.now().toIso8601String(),
    }).eq('session_id', parsedSessionId);
  }
}
