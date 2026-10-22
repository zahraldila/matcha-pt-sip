import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:hive_flutter/hive_flutter.dart';

/// Service lokal berbasis Hive untuk mendukung arsitektur Offline-First
/// pada fitur Drawing, Scoring, dan Recap.
class MatchaHiveService {
  static const String boxDrawingsName = 'matcha_drawings';
  static const String boxMatchesName = 'matcha_matches';
  static const String boxScoresName = 'matcha_scores';
  static const String boxSyncQueueName = 'matcha_sync_queue';
  static const String boxRecapsName = 'matcha_recaps';
  static const String boxSessionsName = 'matcha_sessions';

  static late Box _boxDrawings;
  static late Box _boxMatches;
  static late Box _boxScores;
  static late Box _boxSyncQueue;
  static late Box _boxRecaps;
  static late Box _boxSessions;

  static bool _isInitialized = false;

  /// Inisialisasi Hive dan buka seluruh Box yang diperlukan
  static Future<void> init() async {
    if (_isInitialized) return;
    try {
      await Hive.initFlutter();

      _boxDrawings = await Hive.openBox(boxDrawingsName);
      _boxMatches = await Hive.openBox(boxMatchesName);
      _boxScores = await Hive.openBox(boxScoresName);
      _boxSyncQueue = await Hive.openBox(boxSyncQueueName);
      _boxRecaps = await Hive.openBox(boxRecapsName);
      _boxSessions = await Hive.openBox(boxSessionsName);

      _isInitialized = true;
      debugPrint('[MatchaHiveService] Hive initialized successfully.');
    } catch (e) {
      debugPrint('[MatchaHiveService] Error initializing Hive: $e');
    }
  }

  // ==========================================
  // DRAWING OFFLINE CACHE
  // ==========================================

  /// Menyimpan hasil drawing sesi ke Hive lokal
  static Future<void> saveDrawingRounds(dynamic sessionId, List<Map<String, dynamic>> roundsJson) async {
    if (!_isInitialized || sessionId == null) return;
    try {
      final key = 'drawing_${sessionId.toString()}';
      await _boxDrawings.put(key, jsonEncode(roundsJson));
    } catch (e) {
      debugPrint('[MatchaHiveService] saveDrawingRounds error: $e');
    }
  }

  /// Membaca hasil drawing sesi dari Hive lokal
  static List<Map<String, dynamic>>? getDrawingRounds(dynamic sessionId) {
    if (!_isInitialized || sessionId == null) return null;
    try {
      final key = 'drawing_${sessionId.toString()}';
      final raw = _boxDrawings.get(key);
      if (raw == null) return null;
      final decoded = jsonDecode(raw.toString()) as List<dynamic>;
      return decoded.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    } catch (e) {
      debugPrint('[MatchaHiveService] getDrawingRounds error: $e');
      return null;
    }
  }

  // ==========================================
  // SCORING OFFLINE CACHE & COALESCING
  // ==========================================

  /// Menyimpan snapshot skor pertandingan terbaru ke Hive
  static Future<void> saveMatchScoreSnapshot({
    required int matchId,
    required Map<String, dynamic> scoreData,
  }) async {
    if (!_isInitialized) return;
    try {
      final key = 'score_$matchId';
      await _boxScores.put(key, jsonEncode(scoreData));
    } catch (e) {
      debugPrint('[MatchaHiveService] saveMatchScoreSnapshot error: $e');
    }
  }

  /// Mengambil snapshot skor pertandingan dari Hive
  static Map<String, dynamic>? getMatchScoreSnapshot(int matchId) {
    if (!_isInitialized) return null;
    try {
      final key = 'score_$matchId';
      final raw = _boxScores.get(key);
      if (raw == null) return null;
      return Map<String, dynamic>.from(jsonDecode(raw.toString()) as Map);
    } catch (e) {
      debugPrint('[MatchaHiveService] getMatchScoreSnapshot error: $e');
      return null;
    }
  }

  // ==========================================
  // SYNC QUEUE (OUTBOX PATTERN)
  // ==========================================

  /// Menambahkan atau memperbarui aksi skor dalam antrean sinkronisasi.
  /// Jika sedang offline dan skor berubah (10 -> 20 -> 40), antrean untuk matchId
  /// yang sama akan langsung di-coalesce/di-update dengan state TERAKHIR (40).
  static Future<void> enqueueOrUpdateScoreAction({
    required int matchId,
    required Map<String, dynamic> scorePayload,
  }) async {
    if (!_isInitialized) return;
    try {
      // Simpan snapshot skor lokal terlebih dahulu
      await saveMatchScoreSnapshot(matchId: matchId, scoreData: scorePayload);

      final queueKey = 'score_action_$matchId';
      final actionData = {
        'actionId': queueKey,
        'actionType': 'SAVE_SCORE',
        'matchId': matchId,
        'payload': scorePayload,
        'updatedAt': DateTime.now().toIso8601String(),
      };

      await _boxSyncQueue.put(queueKey, jsonEncode(actionData));
      debugPrint('[MatchaHiveService] Enqueued/Updated latest score action for matchId: $matchId');
    } catch (e) {
      debugPrint('[MatchaHiveService] enqueueOrUpdateScoreAction error: $e');
    }
  }

  /// Menambahkan aksi umum (selain skor) ke antrean sinkronisasi
  static Future<void> enqueueSyncAction({
    required String actionType,
    required String actionId,
    required Map<String, dynamic> payload,
  }) async {
    if (!_isInitialized) return;
    try {
      final actionData = {
        'actionId': actionId,
        'actionType': actionType,
        'payload': payload,
        'createdAt': DateTime.now().toIso8601String(),
      };
      await _boxSyncQueue.put(actionId, jsonEncode(actionData));
    } catch (e) {
      debugPrint('[MatchaHiveService] enqueueSyncAction error: $e');
    }
  }

  /// Mengambil semua antrean yang menunggu disinkronkan ke Supabase
  static List<Map<String, dynamic>> getAllPendingSyncActions() {
    if (!_isInitialized) return [];
    try {
      final List<Map<String, dynamic>> actions = [];
      for (final key in _boxSyncQueue.keys) {
        final raw = _boxSyncQueue.get(key);
        if (raw != null) {
          final map = Map<String, dynamic>.from(jsonDecode(raw.toString()) as Map);
          actions.add(map);
        }
      }
      return actions;
    } catch (e) {
      debugPrint('[MatchaHiveService] getAllPendingSyncActions error: $e');
      return [];
    }
  }

  /// Menghapus aksi dari antrean setelah berhasil disinkronkan ke Supabase
  static Future<void> removeSyncAction(String actionId) async {
    if (!_isInitialized) return;
    try {
      await _boxSyncQueue.delete(actionId);
      debugPrint('[MatchaHiveService] Removed sync action: $actionId');
    } catch (e) {
      debugPrint('[MatchaHiveService] removeSyncAction error: $e');
    }
  }

  /// Jumlah antrean yang belum tersinkron
  static int getPendingSyncCount() {
    if (!_isInitialized) return 0;
    return _boxSyncQueue.length;
  }

  // ==========================================
  // RECAP OFFLINE CACHE
  // ==========================================

  /// Menyimpan snapshot rekap pertandingan sesi ke Hive
  static Future<void> saveRecapSnapshot(dynamic sessionId, Map<String, dynamic> recapJson) async {
    if (!_isInitialized || sessionId == null) return;
    try {
      final key = 'recap_${sessionId.toString()}';
      await _boxRecaps.put(key, jsonEncode(recapJson));
    } catch (e) {
      debugPrint('[MatchaHiveService] saveRecapSnapshot error: $e');
    }
  }

  /// Membaca snapshot rekap pertandingan sesi dari Hive
  static Map<String, dynamic>? getRecapSnapshot(dynamic sessionId) {
    if (!_isInitialized || sessionId == null) return null;
    try {
      final key = 'recap_${sessionId.toString()}';
      final raw = _boxRecaps.get(key);
      if (raw == null) return null;
      return Map<String, dynamic>.from(jsonDecode(raw.toString()) as Map);
    } catch (e) {
      debugPrint('[MatchaHiveService] getRecapSnapshot error: $e');
      return null;
    }
  }

  // ==========================================
  // SESSION DATA CACHE
  // ==========================================

  /// Menyimpan metadata sesi ke Hive
  static Future<void> saveSessionData(dynamic sessionId, Map<String, dynamic> sessionData) async {
    if (!_isInitialized || sessionId == null) return;
    try {
      final key = 'session_${sessionId.toString()}';
      await _boxSessions.put(key, jsonEncode(sessionData));
    } catch (e) {
      debugPrint('[MatchaHiveService] saveSessionData error: $e');
    }
  }

  /// Membaca metadata sesi dari Hive
  static Map<String, dynamic>? getSessionData(dynamic sessionId) {
    if (!_isInitialized || sessionId == null) return null;
    try {
      final key = 'session_${sessionId.toString()}';
      final raw = _boxSessions.get(key);
      if (raw == null) return null;
      return Map<String, dynamic>.from(jsonDecode(raw.toString()) as Map);
    } catch (e) {
      debugPrint('[MatchaHiveService] getSessionData error: $e');
      return null;
    }
  }
}
