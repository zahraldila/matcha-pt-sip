import 'package:supabase_flutter/supabase_flutter.dart';

class MatchService {
  final SupabaseClient _supabase;

  MatchService({SupabaseClient? supabaseClient})
    : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Mengambil sesi live yang sedang aktif dari tb_session.
  /// Jika sessionId diberikan, ambil sesi tersebut.
  /// Jika tidak, cari sesi dengan status_session 'Live', atau fallback ke sesi terbaru.
  Future<Map<String, dynamic>?> getSession(dynamic sessionId) async {
    try {
      if (sessionId != null) {
        final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;
        final res = await _supabase
            .from('tb_session')
            .select()
            .eq('session_id', parsedId)
            .maybeSingle();
        if (res != null) return res;
      }

      // Cari sesi yang berstatus 'Live'
      final liveSessions = await _supabase
          .from('tb_session')
          .select()
          .ilike('status_session', 'live')
          .order('created_at', ascending: false)
          .limit(1);

      if (liveSessions.isNotEmpty) {
        return liveSessions.first;
      }

      // Fallback ke sesi paling terakhir jika tidak ada yang bertanda 'Live'
      final anySessions = await _supabase
          .from('tb_session')
          .select()
          .order('session_id', ascending: false)
          .limit(1);

      if (anySessions.isNotEmpty) {
        return anySessions.first;
      }

      return null;
    } catch (_) {
      return null;
    }
  }

  /// Mengambil daftar match untuk suatu session_id beserta detail court, pemain, dan skor
  Future<List<Map<String, dynamic>>> getMatchesForSession(
    dynamic sessionId,
  ) async {
    try {
      if (sessionId == null) return [];
      final parsedSessionId = int.tryParse(sessionId.toString()) ?? sessionId;

      final matches = await _supabase
          .from('tb_match')
          .select()
          .eq('session_id', parsedSessionId)
          .order('nomor_match', ascending: true);

      if (matches.isEmpty) {
        return [];
      }

      final List<Map<String, dynamic>> detailedMatches = [];

      for (final match in matches) {
        final matchId = match['match_id'] as int;
        final courtId = match['court_id'];
        final nomorMatch = match['nomor_match'] ?? 1;

        // 1. Ambil Nama Court
        String courtName = 'Court $nomorMatch';
        if (courtId != null) {
          try {
            final courtRes = await _supabase
                .from('tb_court')
                .select('nama_court')
                .eq('court_id', courtId)
                .maybeSingle();
            if (courtRes != null && courtRes['nama_court'] != null) {
              courtName = 'Court $nomorMatch — ${courtRes['nama_court']}';
            }
          } catch (_) {}
        }

        // 2. Ambil Pemain dari tb_match_participant -> tb_player
        List<String> sideAPlayers = [];
        List<String> sideBPlayers = [];

        try {
          final participants = await _supabase
              .from('tb_match_participant')
              .select('player_id, side, group_no')
              .eq('match_id', matchId);

          for (final p in participants) {
            final playerId = p['player_id'];
            if (playerId == null) continue;

            final playerRes = await _supabase
                .from('tb_player')
                .select('nama_player')
                .eq('player_id', playerId)
                .maybeSingle();

            final playerName = playerRes != null
                ? playerRes['nama_player'] as String?
                : null;
            if (playerName == null || playerName.isEmpty) continue;

            final sideVal = (p['side'] ?? '').toString().toLowerCase();
            final groupNo = p['group_no'];

            if (sideVal.contains('b') || groupNo == 2) {
              sideBPlayers.add(playerName);
            } else {
              sideAPlayers.add(playerName);
            }
          }
        } catch (_) {}

        final sideA = sideAPlayers.isNotEmpty
            ? sideAPlayers.join(' · ')
            : 'Side A';
        final sideB = sideBPlayers.isNotEmpty
            ? sideBPlayers.join(' · ')
            : 'Side B';

        // 3. Ambil Skor Terkini dari tb_score
        int scoreA = 0;
        int scoreB = 0;

        try {
          final scores = await _supabase
              .from('tb_score')
              .select('score_id, score_value, status_score, waktu_pencatatan')
              .eq('match_id', matchId)
              .order('score_id', ascending: true);

          for (final s in scores) {
            final statusScore = (s['status_score'] ?? '')
                .toString()
                .toLowerCase();
            final val = s['score_value'] as int? ?? 0;
            if (statusScore.contains('a')) {
              scoreA = val;
            } else if (statusScore.contains('b')) {
              scoreB = val;
            }
          }
        } catch (_) {}

        detailedMatches.add({
          'matchId': matchId,
          'courtId': courtId,
          'nomorMatch': nomorMatch,
          'courtName': courtName,
          'sideA': sideA,
          'sideB': sideB,
          'scoreA': scoreA,
          'scoreB': scoreB,
          'status': _normalizeMatchStatus(match['status_match']),
          'rawMatch': match,
        });
      }

      return detailedMatches;
    } catch (_) {
      return [];
    }
  }

  /// Normalisasi status match agar konsisten dengan nilai aktual database:
  /// - 'In Progress' untuk pertandingan berjalan
  /// - 'Finished' untuk pertandingan selesai
  String _normalizeMatchStatus(dynamic rawStatus) {
    final status = (rawStatus ?? '').toString().trim();
    if (status.isEmpty) return 'In Progress';
    final lower = status.toLowerCase();
    if (lower == 'in progress' || lower == 'playing') {
      return 'In Progress';
    }
    if (lower == 'finished') {
      return 'Finished';
    }
    if (lower == 'waiting') {
      return 'Waiting';
    }
    return status;
  }

  /// Mengambil daftar waiting players dari tb_drawing_participant -> tb_player
  Future<List<String>> getWaitingPlayers(dynamic sessionId) async {
    try {
      if (sessionId == null) return [];
      final parsedSessionId = int.tryParse(sessionId.toString()) ?? sessionId;

      // Ambil drawing terakhir untuk sesi ini
      final drawingList = await _supabase
          .from('tb_drawing')
          .select('drawing_id')
          .eq('session_id', parsedSessionId)
          .order('drawing_id', ascending: false)
          .limit(1);

      if (drawingList.isEmpty) return [];
      final drawingId = drawingList.first['drawing_id'] as int;

      // Ambil participant dengan status waiting atau tanpa court
      final participants = await _supabase
          .from('tb_drawing_participant')
          .select('player_id, status, court_id')
          .eq('drawing_id', drawingId);

      final List<String> waitingNames = [];

      for (final p in participants) {
        final status = (p['status'] ?? '').toString().toLowerCase();
        final courtId = p['court_id'];

        if (status.contains('wait') || courtId == null) {
          final playerId = p['player_id'];
          if (playerId != null) {
            final playerRes = await _supabase
                .from('tb_player')
                .select('nama_player')
                .eq('player_id', playerId)
                .maybeSingle();

            if (playerRes != null && playerRes['nama_player'] != null) {
              waitingNames.add(playerRes['nama_player'] as String);
            }
          }
        }
      }

      return waitingNames;
    } catch (_) {
      return [];
    }
  }

  /// Menyimpan atau mengupdate skor ke tb_score.
  /// Sesuai aturan:
  /// 1. Cek skor saat ini di tb_score
  /// 2. UPDATE jika sudah ada
  /// 3. INSERT jika belum ada (wajib sertakan created_at karena NOT NULL constraint)
  /// 4. HANYA jika database berhasil, broadcast event score_update ke channel
  Future<void> saveMatchScore({
    required int matchId,
    required int scoreA,
    required int scoreB,
    dynamic sessionId,
  }) async {
    final now = DateTime.now().toIso8601String();

    // 1. Ambil data skor yang sudah tercatat untuk match_id ini
    final existingRows = await _supabase
        .from('tb_score')
        .select('score_id, status_score')
        .eq('match_id', matchId);

    // Cari baris Side A
    Map<String, dynamic>? rowA;
    Map<String, dynamic>? rowB;

    for (final r in existingRows) {
      final status = (r['status_score'] ?? '').toString().toLowerCase();
      if (status.contains('a')) {
        rowA = r;
      } else if (status.contains('b')) {
        rowB = r;
      }
    }

    // Update / Insert Side A
    if (rowA != null) {
      await _supabase
          .from('tb_score')
          .update({'score_value': scoreA, 'waktu_pencatatan': now})
          .eq('score_id', rowA['score_id']);
    } else {
      await _supabase.from('tb_score').insert({
        'match_id': matchId,
        'score_value': scoreA,
        'status_score': 'Side A',
        'waktu_pencatatan': now,
        'created_at': now,
      });
    }

    // Update / Insert Side B
    if (rowB != null) {
      await _supabase
          .from('tb_score')
          .update({'score_value': scoreB, 'waktu_pencatatan': now})
          .eq('score_id', rowB['score_id']);
    } else {
      await _supabase.from('tb_score').insert({
        'match_id': matchId,
        'score_value': scoreB,
        'status_score': 'Side B',
        'waktu_pencatatan': now,
        'created_at': now,
      });
    }

    // 2. Database berhasil -> kirim broadcast ke Realtime Channel sebagai fallback
    try {
      final channelName = sessionId != null
          ? 'session_$sessionId'
          : 'match_arena_live';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'score_update',
        payload: {
          'match_id': matchId,
          'score_value_a': scoreA,
          'score_value_b': scoreB,
          'session_id': sessionId,
        },
      );
    } catch (_) {
      // Abaikan jika broadcast gagal, database tetap source of truth
    }
  }

  /// Memperbarui status match di tb_match menjadi Finished
  Future<void> finishMatch({required int matchId, dynamic sessionId}) async {
    final now = DateTime.now().toIso8601String();

    await _supabase
        .from('tb_match')
        .update({
          'status_match': 'Finished',
          'waktu_selesai': now,
          'updated_at': now,
        })
        .eq('match_id', matchId);

    // Broadcast status change setelah DB berhasil
    try {
      final channelName = sessionId != null
          ? 'session_$sessionId'
          : 'match_arena_live';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'match_status_update',
        payload: {
          'match_id': matchId,
          'status_match': 'Finished',
          'session_id': sessionId,
        },
      );
    } catch (_) {}
  }

  /// Berlangganan (subscribe) ke event realtime untuk tb_score DAN tb_match
  RealtimeChannel subscribeLiveSession({
    dynamic sessionId,
    required void Function() onDataChanged,
  }) {
    final channelName = sessionId != null
        ? 'session_$sessionId'
        : 'match_arena_live';

    final channel = _supabase.channel(channelName);

    channel
        // 1. Dengarkan perubahan tb_score dari Postgres publication
        .onPostgresChanges(
          event: PostgresChangeEvent.all,
          schema: 'public',
          table: 'tb_score',
          callback: (payload) {
            onDataChanged();
          },
        )
        // 2. Dengarkan perubahan tb_match dari Postgres publication
        .onPostgresChanges(
          event: PostgresChangeEvent.all,
          schema: 'public',
          table: 'tb_match',
          callback: (payload) {
            onDataChanged();
          },
        )
        // 3. Fallback broadcast score_update
        .onBroadcast(
          event: 'score_update',
          callback: (payload) {
            onDataChanged();
          },
        )
        // 4. Fallback broadcast match_status_update
        .onBroadcast(
          event: 'match_status_update',
          callback: (payload) {
            onDataChanged();
          },
        )
        .subscribe();

    return channel;
  }

  /// Menghapus channel subscription secara bersih
  Future<void> unsubscribe(RealtimeChannel? channel) async {
    if (channel != null) {
      await _supabase.removeChannel(channel);
    }
  }
}
