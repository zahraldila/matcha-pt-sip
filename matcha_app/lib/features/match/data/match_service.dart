import 'package:flutter/foundation.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../../drawing/domain/matcha_drawing_engine.dart';
import '../../games/domain/game_wizard_model.dart';
import 'datasource/match_remote_data_source.dart';

class MatchService {
  final SupabaseClient? _client;
  SupabaseClient get _supabase => _client ?? Supabase.instance.client;

  final MatchRemoteDataSource? _remoteDataSource;
  MatchRemoteDataSource get _dataSource =>
      _remoteDataSource ?? MatchRemoteDataSource(supabaseClient: _supabase);

  MatchService({
    SupabaseClient? supabaseClient,
    MatchRemoteDataSource? remoteDataSource,
  })  : _client = supabaseClient,
        _remoteDataSource = remoteDataSource;

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
        return res;
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

  /// Mapping ID ke nama format pertandingan resmi:
  /// 1 Americano, 2 Mexicano, 3 Mix Americano, 4 Team Americano,
  /// 5 Tennis Single / Double, 6 King of the Court
  static String? formatIdToName(int? id) {
    switch (id) {
      case 1:
        return 'Americano';
      case 2:
        return 'Mexicano';
      case 3:
        return 'Mix Americano';
      case 4:
        return 'Team Americano';
      case 5:
        return 'Tennis Single / Double';
      case 6:
        return 'King of the Court';
      default:
        return null;
    }
  }

  /// Mapping nama format pertandingan ke ID format resmi (1-6)
  static int? formatNameToId(String? name) {
    if (name == null || name.trim().isEmpty) return null;
    final normalized = name.trim().toLowerCase();
    switch (normalized) {
      case 'americano':
        return 1;
      case 'mexicano':
        return 2;
      case 'mix americano':
      case 'mixamericano':
        return 3;
      case 'team americano':
      case 'teamamericano':
        return 4;
      case 'tennis single / double':
      case 'tennis single/double':
      case 'tennis':
        return 5;
      case 'king of the court':
      case 'king of court':
      case 'kingofthecourt':
        return 6;
      default:
        if (normalized.contains('team americano')) return 4;
        if (normalized.contains('mix americano')) return 3;
        if (normalized.contains('americano')) return 1;
        if (normalized.contains('mexicano')) return 2;
        if (normalized.contains('tennis')) return 5;
        if (normalized.contains('king of the court')) return 6;
        return null;
    }
  }

  /// Mengambil drawing_id aktif untuk suatu session_id, memprioritaskan
  /// drawing yang memiliki data match tersimpan sebagai sumber aktif pertandingan.
  Future<int?> getLatestDrawingId(dynamic sessionId) async {
    try {
      if (sessionId == null) return null;
      final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;
      final res = await _supabase
          .from('tb_drawing')
          .select('drawing_id')
          .eq('session_id', parsedId)
          .order('drawing_id', ascending: false);
      if ((res as List).isEmpty) return null;

      for (final d in res) {
        final dId = d['drawing_id'] is int
            ? d['drawing_id'] as int
            : int.tryParse(d['drawing_id']?.toString() ?? '');
        if (dId == null) continue;
        final mCheck = await _supabase
            .from('tb_match')
            .select('match_id')
            .eq('drawing_id', dId)
            .limit(1);
        if ((mCheck as List).isNotEmpty) {
          return dId;
        }
      }

      final firstId = res.first['drawing_id'];
      return firstId is int
          ? firstId
          : int.tryParse(firstId?.toString() ?? '');
    } catch (_) {
      return null;
    }
  }

  /// Mengambil info drawing aktif dan format pertandingan untuk suatu session_id
  /// dari drawing aktif yang sama dengan sumber match.
  Future<Map<String, dynamic>?> getActiveDrawingFormat(dynamic sessionId) async {
    try {
      if (sessionId == null) return null;
      final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;

      final drawingsRes = await _supabase
          .from('tb_drawing')
          .select('drawing_id, match_format_id')
          .eq('session_id', parsedId)
          .order('drawing_id', ascending: false);

      if ((drawingsRes as List).isEmpty) return null;

      int? activeDrawingId;
      int? activeMatchFormatId;

      for (final d in drawingsRes) {
        final dId = d['drawing_id'] is int
            ? d['drawing_id'] as int
            : int.tryParse(d['drawing_id']?.toString() ?? '');
        if (dId == null) continue;
        final mCheck = await _supabase
            .from('tb_match')
            .select('match_id')
            .eq('drawing_id', dId)
            .limit(1);
        if ((mCheck as List).isNotEmpty) {
          activeDrawingId = dId;
          final mfId = d['match_format_id'];
          activeMatchFormatId = mfId is int
              ? mfId
              : int.tryParse(mfId?.toString() ?? '');
          break;
        }
      }

      if (activeDrawingId == null) {
        final first = drawingsRes.first;
        final dId = first['drawing_id'];
        activeDrawingId = dId is int
            ? dId
            : int.tryParse(dId?.toString() ?? '');
        final mfId = first['match_format_id'];
        activeMatchFormatId = mfId is int
            ? mfId
            : int.tryParse(mfId?.toString() ?? '');
      }

      String? formatName;
      if (activeMatchFormatId != null) {
        try {
          final fRes = await _supabase
              .from('tb_match_format')
              .select('nama_format')
              .eq('match_format_id', activeMatchFormatId)
              .maybeSingle();
          if (fRes != null && fRes['nama_format'] != null) {
            formatName = fRes['nama_format'].toString().trim();
          }
        } catch (_) {}
      }

      if ((formatName == null || formatName.isEmpty) && activeMatchFormatId != null) {
        formatName = formatIdToName(activeMatchFormatId);
      }

      return {
        'drawing_id': activeDrawingId,
        'match_format_id': activeMatchFormatId,
        'nama_format': formatName,
      };
    } catch (_) {
      return null;
    }
  }

  /// Mengambil atau membuat record tb_drawing untuk suatu session_id
  Future<int> getOrCreateDrawingId(
    dynamic sessionId, {
    int? matchFormatId,
  }) async {
    final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;

    // Verifikasi format sebelum mengubah drawing.
    if (matchFormatId != null) {
      final format = await _supabase
          .from('tb_match_format')
          .select('match_format_id')
          .eq('match_format_id', matchFormatId)
          .maybeSingle();

      if (format == null) {
        throw Exception('Format pertandingan tidak ditemukan.');
      }
    }

    final existingId = await getLatestDrawingId(parsedId);

    if (existingId != null && existingId > 0) {
      if (matchFormatId != null) {
        await _supabase
            .from('tb_drawing')
            .update({'match_format_id': matchFormatId})
            .eq('drawing_id', existingId);
      }

      return existingId;
    }

    // Drawing baru harus memiliki format yang jelas.
    if (matchFormatId == null) {
      throw Exception('Format pertandingan belum dipilih.');
    }

    final now = DateTime.now();
    final nowTime =
        '${now.hour.toString().padLeft(2, '0')}:'
        '${now.minute.toString().padLeft(2, '0')}:'
        '${now.second.toString().padLeft(2, '0')}';

    final drawing = await _supabase
        .from('tb_drawing')
        .insert({
          'session_id': parsedId,
          'match_format_id': matchFormatId,
          'tanggal_drawing': now.toIso8601String().split('T')[0],
          'jam_drawing': nowTime,
        })
        .select('drawing_id')
        .single();

    return int.parse(drawing['drawing_id'].toString());
  }

  /// Mengambil daftar match untuk suatu session_id beserta detail court, pemain, dan skor
  Future<List<Map<String, dynamic>>> getMatchesForSession(
    dynamic sessionId,
  ) async {
    try {
      if (sessionId == null) return [];
      final parsedSessionId = int.tryParse(sessionId.toString()) ?? sessionId;

      final drawingId = await getLatestDrawingId(parsedSessionId);
      if (drawingId == null) {
        return [];
      }

      final matches = await _supabase
          .from('tb_match')
          .select()
          .eq('drawing_id', drawingId)
          .order('nomor_match', ascending: true);

      if (matches.isEmpty) {
        return [];
      }

      int sessionCourtCount = 1;
      try {
        final sCourts = await _supabase
            .from('tb_session_court')
            .select('court_id')
            .eq('session_id', parsedSessionId);
        if ((sCourts as List).isNotEmpty) {
          sessionCourtCount = sCourts.length;
        }
      } catch (_) {}
      final effectiveCourtCount = sessionCourtCount > 0 ? sessionCourtCount : 1;

      // Batch ambil partisipan untuk seluruh match dalam drawing
      final matchIds = matches
          .map((m) => m['match_id'])
          .where((id) => id != null)
          .map((id) => id is int ? id : int.tryParse(id.toString()))
          .whereType<int>()
          .toList();

      List<Map<String, dynamic>> allParticipants = [];
      if (matchIds.isNotEmpty) {
        try {
          final pRes = await _supabase
              .from('tb_match_participant')
              .select('match_id, player_id, side')
              .inFilter('match_id', matchIds);
          allParticipants = List<Map<String, dynamic>>.from(pRes as List);
        } catch (e) {
          debugPrint('[MATCHA_ERROR] Gagal memuat tb_match_participant: $e');
          rethrow;
        }
      }

      // Batch ambil profil pemain dari tb_player menggunakan kolom 'nama'
      final participantPlayerIds = allParticipants
          .map((p) => p['player_id'])
          .where((id) => id != null)
          .map((id) => id is int ? id : int.tryParse(id.toString()))
          .whereType<int>()
          .toSet()
          .toList();

      final Map<int, Map<String, dynamic>> playerMap = {};
      if (participantPlayerIds.isNotEmpty) {
        try {
          final pList = await _supabase
              .from('tb_player')
              .select('player_id, user_id, nama, foto, level')
              .inFilter('player_id', participantPlayerIds);
          for (final pData in pList) {
            final pid = pData['player_id'];
            final parsedPid = pid is int ? pid : int.tryParse(pid?.toString() ?? '');
            if (parsedPid != null) {
              playerMap[parsedPid] = Map<String, dynamic>.from(pData);
            }
          }
        } catch (e) {
          debugPrint('[MATCHA_ERROR] Gagal memuat tb_player: $e');
          rethrow;
        }
      }

      final List<Map<String, dynamic>> detailedMatches = [];

      for (final match in matches) {
        final matchId = match['match_id'] as int;
        final courtId = match['court_id'];
        final int nomorMatch = match['nomor_match'] is int
            ? match['nomor_match'] as int
            : (int.tryParse(match['nomor_match']?.toString() ?? '') ?? 1);
        final int roundNum = ((nomorMatch - 1) ~/ effectiveCourtCount) + 1;
        final int courtNum = ((nomorMatch - 1) % effectiveCourtCount) + 1;

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
        List<Map<String, dynamic>> sideAPlayerObjects = [];
        List<Map<String, dynamic>> sideBPlayerObjects = [];

        final matchParticipants = allParticipants.where((p) {
          final mId = p['match_id'];
          final parsedMId = mId is int ? mId : int.tryParse(mId?.toString() ?? '');
          return parsedMId == matchId;
        });

        for (final p in matchParticipants) {
          final playerId = p['player_id'];
          if (playerId == null) continue;
          final int? parsedPId = playerId is int ? playerId : int.tryParse(playerId.toString());
          if (parsedPId == null) continue;

          final pData = playerMap[parsedPId];
          final pName = (pData?['nama'] ?? pData?['nama_player'])?.toString().trim();
          if (pName == null || pName.isEmpty) continue;

          final playerItem = {
            'id': parsedPId.toString(),
            'playerId': parsedPId,
            'userId': pData?['user_id'] is int
                ? pData!['user_id'] as int
                : int.tryParse(pData?['user_id']?.toString() ?? ''),
            'name': pName,
            'avatarUrl': pData?['foto']?.toString(),
            'level': pData?['level']?.toString() ?? 'Beginner',
          };

          final sideVal = (p['side'] ?? '').toString().trim().toUpperCase();
          final isB = sideVal == 'B' || sideVal == 'TEAM_B' || sideVal == 'SIDE_B' || sideVal.contains('B');

          if (isB) {
            sideBPlayers.add(pName);
            sideBPlayerObjects.add(playerItem);
          } else {
            sideAPlayers.add(pName);
            sideAPlayerObjects.add(playerItem);
          }
        }

        final sideA = sideAPlayers.join(' · ');
        final sideB = sideBPlayers.join(' · ');

        // 3. Ambil Skor Terkini dari tb_score
        int scoreA = 0;
        int scoreB = 0;
        String pointScoreA = '0';
        String pointScoreB = '0';
        int? setScoreA;
        int? setScoreB;
        int version = 0;
        String? lastEventId;
        String? statusScore;

        try {
          final scores = await _supabase
              .from('tb_score')
              .select(
                'score_id, score_side_a, score_side_b, game_score_a, game_score_b, point_score_a, point_score_b, set_score_a, set_score_b, status_score, version, last_event_id, updated_at, created_at',
              )
              .eq('match_id', matchId)
              .order('score_id', ascending: true);

          for (final s in scores) {
            if (s['game_score_a'] != null) {
              scoreA = s['game_score_a'] as int? ?? scoreA;
            } else if (s['score_side_a'] != null) {
              scoreA = s['score_side_a'] as int? ?? scoreA;
            }
            if (s['game_score_b'] != null) {
              scoreB = s['game_score_b'] as int? ?? scoreB;
            } else if (s['score_side_b'] != null) {
              scoreB = s['score_side_b'] as int? ?? scoreB;
            }
            if (s['point_score_a'] != null) {
              pointScoreA = s['point_score_a'].toString();
            }
            if (s['point_score_b'] != null) {
              pointScoreB = s['point_score_b'].toString();
            }
            if (s['set_score_a'] != null) {
              setScoreA = s['set_score_a'] as int?;
            }
            if (s['set_score_b'] != null) {
              setScoreB = s['set_score_b'] as int?;
            }
            if (s['version'] != null) {
              final v = s['version'];
              version = v is int ? v : (int.tryParse(v.toString()) ?? 0);
            }
            if (s['last_event_id'] != null) {
              lastEventId = s['last_event_id'].toString();
            }
            if (s['status_score'] != null) {
              statusScore = s['status_score'].toString();
            }
          }
        } catch (_) {}

        detailedMatches.add({
          'matchId': matchId,
          'courtId': courtId,
          'nomorMatch': nomorMatch,
          'roundNumber': roundNum,
          'courtNumber': courtNum,
          'courtName': courtName,
          'sideA': sideA,
          'sideB': sideB,
          'teamAPlayers': sideAPlayerObjects,
          'teamBPlayers': sideBPlayerObjects,
          'scoreA': scoreA,
          'scoreB': scoreB,
          'pointScoreA': pointScoreA,
          'pointScoreB': pointScoreB,
          'setScoreA': setScoreA,
          'setScoreB': setScoreB,
          'winnerTeam': match['winner_team'],
          'version': version,
          'lastEventId': lastEventId,
          'status': _normalizeMatchStatus(match['status_match']),
          'statusScore': statusScore,
          'rawMatch': {
            ...match,
            'round_number': roundNum,
            if (match['court_id'] == null) 'court_id': courtNum,
          },
        });
      }

      return detailedMatches;
    } catch (e) {
      debugPrint('[MATCHA_ERROR] getMatchesForSession failed: $e');
      rethrow;
    }
  }

  /// Normalisasi status match agar konsisten dengan nilai aktual database:
  /// - 'In Progress' untuk pertandingan berjalan
  /// - 'Finished' untuk pertandingan selesai
  String _normalizeMatchStatus(dynamic rawStatus) {
    final status = (rawStatus ?? '').toString().trim();
    if (status.isEmpty) return 'Scheduled';
    final lower = status.toLowerCase();
    if (lower == 'in progress' || lower == 'playing') {
      return 'In Progress';
    }
    if (lower == 'finished' || lower == 'completed') {
      return 'Finished';
    }
    if (lower == 'waiting' || lower == 'scheduled' || lower == 'pending') {
      return 'Scheduled';
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
                .select('nama')
                .eq('player_id', playerId)
                .maybeSingle();

            if (playerRes != null) {
              final name = (playerRes['nama'] ?? playerRes['nama_player'])?.toString();
              if (name != null && name.isNotEmpty) {
                waitingNames.add(name);
              }
            }
          }
        }
      }

      return waitingNames;
    } catch (_) {
      return [];
    }
  }

  /// Menyimpan atau mengupdate skor ke tb_score dengan dukungan format terstruktur Web.
  Future<void> saveMatchScore({
    required int matchId,
    required int scoreA,
    required int scoreB,
    dynamic sessionId,
    int setNumber = 1,
    int? gameNumber = 1,
    String? pointScoreA,
    String? pointScoreB,
    int? gameScoreA,
    int? gameScoreB,
    int? setScoreA,
    int? setScoreB,
    String? scoringSystem,
    String? statusScore,
    String? winnerTeam,
    int? version,
    String? lastEventId,
    String? matchKey,
  }) async {
    final now = DateTime.now();
    final nowIso = now.toIso8601String();
    final nowTime =
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';

    // 1. Delegasikan ke MatchRemoteDataSource untuk operasi tulis, deduplikasi, dan anti-downgrade
    final savedScore = await _dataSource.saveOrUpdateScore(
      matchId: matchId,
      setNumber: setNumber,
      gameNumber: gameNumber,
      pointScoreA: pointScoreA,
      pointScoreB: pointScoreB,
      gameScoreA: gameScoreA ?? scoreA,
      gameScoreB: gameScoreB ?? scoreB,
      scoreSideA: scoreA,
      scoreSideB: scoreB,
      setScoreA: setScoreA,
      setScoreB: setScoreB,
      scoringSystem: scoringSystem,
      statusScore: statusScore,
      version: version,
      lastEventId: lastEventId,
    );

    final finalVersion = savedScore.version ?? version ?? 1;

    // Update tb_match jika match selesai atau ada pembaruan status
    if (statusScore != null && (statusScore.toLowerCase().contains('final') || statusScore.toLowerCase().contains('completed'))) {
      try {
        await _supabase.from('tb_match').update({
          'status_match': 'Completed',
          'hasil_pertandingan': 'Game Score $scoreA - $scoreB',
          'winner_team': ?winnerTeam,
          'version': finalVersion,
          'last_event_id': ?lastEventId,
          'waktu_selesai': nowTime,
          'updated_at': nowIso,
        }).eq('match_id', matchId);
      } catch (_) {}
    }

    // 2. Database berhasil -> kirim broadcast ke Realtime Channel sesuai Web
    try {
      final channelName = sessionId != null
          ? 'session_$sessionId'
          : 'match_arena_live';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'score_update',
        payload: {
          'session_id': sessionId,
          'match_id': matchId,
          'match_key': matchKey ?? 'round_${setNumber}_court_1',
          'score_a': gameScoreA ?? scoreA,
          'score_b': gameScoreB ?? scoreB,
          'games_a': gameScoreA ?? scoreA,
          'games_b': gameScoreB ?? scoreB,
          'score_value_a': scoreA,
          'score_value_b': scoreB,
          'point_display_a': pointScoreA ?? '0',
          'point_display_b': pointScoreB ?? '0',
          'sets_a': setScoreA ?? 0,
          'sets_b': setScoreB ?? 0,
          'version': finalVersion,
          'server_version': finalVersion,
          'last_event_id': lastEventId ?? '',
          'status': (statusScore ?? 'in_progress').toLowerCase(),
          'winner_team': winnerTeam,
        },
      );
    } catch (_) {
      // Abaikan jika broadcast gagal, database tetap source of truth
    }
  }

  /// Memperbarui status match di tb_match menjadi Finished
  Future<void> finishMatch({
    required int matchId,
    dynamic sessionId,
    String? winnerTeam,
    int? version,
    String? lastEventId,
    String? hasilPertandingan,
  }) async {
    final now = DateTime.now();
    final nowIso = now.toIso8601String();
    final nowTime =
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';

    await _supabase
        .from('tb_match')
        .update({
          'status_match': 'Finished',
          'waktu_selesai': nowTime,
          'winner_team': ?winnerTeam,
          'version': ?version,
          'last_event_id': ?lastEventId,
          'hasil_pertandingan': ?hasilPertandingan,
          'updated_at': nowIso,
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
          'winner_team': ?winnerTeam,
        },
      );
    } catch (_) {}
  }

  /// Broadcast round advanced event ke channel realtime (sesuai Web broadcastRoundAdvancedRealtime)
  Future<void> broadcastRoundAdvanced({
    required dynamic sessionId,
    required int currentRoundNum,
    required int nextRoundNum,
  }) async {
    try {
      final channelName = sessionId != null ? 'session_$sessionId' : 'match_arena_live';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'round_advanced',
        payload: {
          'session_id': sessionId,
          'current_round': 'round_$currentRoundNum',
          'current_round_num': currentRoundNum,
          'next_round': 'round_$nextRoundNum',
          'next_round_num': nextRoundNum,
          'active_round': nextRoundNum,
          'session_active_round': nextRoundNum,
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        },
      );
    } catch (_) {}
  }

  /// Memperbarui status pertandingan ronde berikutnya menjadi In Progress pada tb_match
  Future<void> advanceRoundMatches({
    required dynamic sessionId,
    required int roundNumber,
    List<int>? matchIds,
  }) async {
    try {
      if (sessionId == null) return;
      if (matchIds != null && matchIds.isNotEmpty) {
        await _supabase
            .from('tb_match')
            .update({'status_match': 'In Progress'})
            .inFilter('match_id', matchIds);
        return;
      }
      final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;
      final drawingId = await getLatestDrawingId(parsedId);
      if (drawingId == null) return;

      final sCourts = await _supabase
          .from('tb_session_court')
          .select('court_id')
          .eq('session_id', parsedId);
      final courtCount = (sCourts as List).isNotEmpty ? sCourts.length : 1;

      final startMatchNo = (roundNumber - 1) * courtCount + 1;
      final endMatchNo = roundNumber * courtCount;

      await _supabase
          .from('tb_match')
          .update({'status_match': 'In Progress'})
          .eq('drawing_id', drawingId)
          .gte('nomor_match', startMatchNo)
          .lte('nomor_match', endMatchNo);
    } catch (_) {}
  }

  /// Memperbarui status sesi menjadi Finished dan broadcast event session_finished
  Future<void> finishSession(dynamic sessionId) async {
    if (sessionId == null) return;
    final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;

    await _supabase
        .from('tb_session')
        .update({'status_session': 'Finished'})
        .eq('session_id', parsedId);

    try {
      final channelName = 'session_$parsedId';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'session_finished',
        payload: {
          'session_id': parsedId,
          'status_session': 'Finished',
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        },
      );
    } catch (_) {}
  }

  /// Berlangganan (subscribe) ke event realtime untuk tb_score DAN tb_match
  RealtimeChannel subscribeLiveSession({
    dynamic sessionId,
    required void Function() onDataChanged,
    void Function(Map<String, dynamic> payload)? onRoundAdvanced,
    void Function(Map<String, dynamic> payload)? onSessionFinished,
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
        // Dengarkan perubahan tb_session (misal status berubah jadi Finished)
        .onPostgresChanges(
          event: PostgresChangeEvent.all,
          schema: 'public',
          table: 'tb_session',
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
        // 5. Broadcast round_advanced
        .onBroadcast(
          event: 'round_advanced',
          callback: (payload) {
            if (onRoundAdvanced != null) {
              onRoundAdvanced(payload);
            }
            onDataChanged();
          },
        )
        // 6. Broadcast session_finished
        .onBroadcast(
          event: 'session_finished',
          callback: (payload) {
            if (onSessionFinished != null) {
              onSessionFinished(payload);
            }
            onDataChanged();
          },
        )
        // 7. Broadcast drawing_updated
        .onBroadcast(
          event: 'drawing_updated',
          callback: (payload) {
            onDataChanged();
          },
        )
        // 8. Broadcast drawing_locked
        .onBroadcast(
          event: 'drawing_locked',
          callback: (payload) {
            onDataChanged();
          },
        )
        .subscribe();

    return channel;
  }

  /// Berlangganan ke perubahan susunan drawing untuk suatu sesi
  RealtimeChannel? subscribeDrawingSession({
    required dynamic sessionId,
    required void Function() onDrawingChanged,
    void Function()? onDrawingLocked,
  }) {
    try {
      final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;
      final channelName = 'session_$parsedId';
      final channel = _supabase.channel(channelName);

      channel
          .onPostgresChanges(
            event: PostgresChangeEvent.all,
            schema: 'public',
            table: 'tb_match',
            callback: (_) => onDrawingChanged(),
          )
          .onPostgresChanges(
            event: PostgresChangeEvent.all,
            schema: 'public',
            table: 'tb_drawing',
            callback: (_) => onDrawingChanged(),
          )
          .onPostgresChanges(
            event: PostgresChangeEvent.all,
            schema: 'public',
            table: 'tb_session',
            callback: (payload) {
              final newStatus = payload.newRecord['status_session']?.toString().toLowerCase();
              if (newStatus == 'completed' || newStatus == 'finished') {
                if (onDrawingLocked != null) {
                  onDrawingLocked();
                } else {
                  onDrawingChanged();
                }
              } else {
                onDrawingChanged();
              }
            },
          )
          .onBroadcast(
            event: 'drawing_updated',
            callback: (_) => onDrawingChanged(),
          )
          .onBroadcast(
            event: 'drawing_locked',
            callback: (_) {
              if (onDrawingLocked != null) {
                onDrawingLocked();
              } else {
                onDrawingChanged();
              }
            },
          )
          .subscribe();

      return channel;
    } catch (_) {
      return null;
    }
  }

  /// Memeriksa apakah drawing sesi sudah berjalan atau terkunci
  Future<bool> isSessionDrawingLocked(dynamic sessionId) async {
    try {
      if (sessionId == null) return false;
      final parsedSessionId = int.tryParse(sessionId.toString()) ?? sessionId;

      // 1. Cek status session di tb_session (hanya jika sesi sudah selesai)
      final sessionRes = await _supabase
          .from('tb_session')
          .select('status_session')
          .eq('session_id', parsedSessionId)
          .maybeSingle();

      final statusSession = sessionRes?['status_session']?.toString().toLowerCase();
      if (statusSession == 'completed' || statusSession == 'finished') {
        return true;
      }

      // 2. Cek apakah ada match dengan status 'Completed' / 'In Progress' atau memiliki score di tb_score
      final drawingId = await getLatestDrawingId(parsedSessionId);
      if (drawingId == null) return false;

      final matches = await _supabase
          .from('tb_match')
          .select('match_id, status_match')
          .eq('drawing_id', drawingId);

      if (matches.isEmpty) return false;

      final matchIds = <int>[];
      for (final m in matches) {
        final mStatus = m['status_match']?.toString().toLowerCase();
        if (mStatus == 'completed' ||
            mStatus == 'finished' ||
            mStatus == 'in progress' ||
            mStatus == 'live') {
          return true;
        }
        final mId = m['match_id'] is int ? m['match_id'] as int : int.tryParse(m['match_id'].toString());
        if (mId != null) matchIds.add(mId);
      }

      if (matchIds.isNotEmpty) {
        final scoreCount = await _supabase
            .from('tb_score')
            .select('score_id')
            .inFilter('match_id', matchIds)
            .limit(1);

        if ((scoreCount as List).isNotEmpty) {
          return true;
        }
      }

      return false;
    } catch (_) {
      return false;
    }
  }

  /// Menyimpan susunan drawing & pertandingan ke tb_drawing, tb_match, dan tb_match_participant
  Future<List<DrawingRound>> saveDrawingMatches({
    required dynamic sessionId,
    required List<DrawingRound> rounds,
    String matchStatus = 'Scheduled',
    List<GamePlayerItem>? allPlayers,
    int? courtCount,
    int? matchFormatId,
  }) async {
    if (sessionId == null || rounds.isEmpty) return rounds;

    final parsedSessionId =
        int.tryParse(sessionId.toString()) ?? sessionId;

    final maxCourtNumber = rounds
        .expand((round) => round.matches)
        .fold<int>(
          0,
          (highest, match) =>
              match.courtNumber > highest ? match.courtNumber : highest,
        );

    final requiredCourtCount = courtCount ?? maxCourtNumber;

    if (requiredCourtCount < 1 || maxCourtNumber > requiredCourtCount) {
      throw Exception('Nomor court drawing tidak sesuai konfigurasi.');
    }

    final drawingId = await getOrCreateDrawingId(
      parsedSessionId,
      matchFormatId: matchFormatId,
    );

    final existingMatches = await _supabase
        .from('tb_match')
        .select('match_id')
        .eq('drawing_id', drawingId);

    final oldMatchIds = existingMatches
        .map((row) => int.tryParse(row['match_id'].toString()))
        .whereType<int>()
        .where((id) => id > 0)
        .toList();

    // Periksa skor sebelum mengubah relasi court atau drawing.
    if (oldMatchIds.isNotEmpty) {
      final existingScores = await _supabase
          .from('tb_score')
          .select('score_id')
          .inFilter('match_id', oldMatchIds)
          .limit(1);

      if (existingScores.isNotEmpty) {
        throw Exception(
          'Tidak dapat mengacak ulang: pertandingan sudah memiliki skor aktif.',
        );
      }
    }

    final courtIds = await _ensureDrawingCourts(
      sessionId: parsedSessionId,
      requiredCount: requiredCourtCount,
    );

    // Jangan hapus drawing lama jika validasi court gagal.
    if (oldMatchIds.isNotEmpty) {
      await _supabase
          .from('tb_match_participant')
          .delete()
          .inFilter('match_id', oldMatchIds);

      await _supabase
          .from('tb_match')
          .delete()
          .inFilter('match_id', oldMatchIds);
    }

    final List<DrawingRound> savedRounds = [];
    final int effectiveCourtCount = courtIds.length;
    int matchCounter = 0;

    for (final round in rounds) {
      final List<DrawingMatch> savedMatches = [];
      for (final match in round.matches) {
        matchCounter++;
        if (match.courtNumber < 1 ||
            match.courtNumber > effectiveCourtCount) {
          throw Exception('Nomor court pertandingan tidak valid.');
        }

        final courtId = courtIds[match.courtNumber - 1];

        final nomorMatch =
            (round.roundNumber - 1) * effectiveCourtCount +
            match.courtNumber;

        final matchInsert = await _supabase
            .from('tb_match')
            .insert({
              'drawing_id': drawingId,
              if (courtId != null) 'court_id': courtId,
              'nomor_match': nomorMatch,
              'status_match': matchStatus,
              'created_at': DateTime.now().toIso8601String(),
            })
            .select('match_id')
            .single();

        final matchId = matchInsert['match_id'] is int
            ? matchInsert['match_id'] as int
            : int.parse(matchInsert['match_id'].toString());

        match.matchId = matchId;

        int? resolvePlayerId(GamePlayerItem p) {
          if (p.playerId != null && p.playerId! > 0) return p.playerId;
          final parsed = int.tryParse(p.id);
          if (parsed != null && parsed > 0) return parsed;
          if (p.id.contains('_')) {
            final lastPart = int.tryParse(p.id.split('_').last);
            if (lastPart != null && lastPart > 0) return lastPart;
          }
          if (allPlayers != null) {
            final found = allPlayers.where((ap) =>
              ap.id == p.id ||
              (ap.name.trim().isNotEmpty && ap.name.trim().toLowerCase() == p.name.trim().toLowerCase())
            ).firstOrNull;
            if (found?.playerId != null && found!.playerId! > 0) return found.playerId;
            final foundParsed = int.tryParse(found?.id ?? '');
            if (foundParsed != null && foundParsed > 0) return foundParsed;
          }
          return null;
        }

        for (final p in match.teamA) {
          final pId = resolvePlayerId(p);
          if (pId != null && pId > 0) {
            await _supabase.from('tb_match_participant').insert({
              'match_id': matchId,
              'player_id': pId,
              'side': 'A',
            });
          }
        }

        for (final p in match.teamB) {
          final pId = resolvePlayerId(p);
          if (pId != null && pId > 0) {
            await _supabase.from('tb_match_participant').insert({
              'match_id': matchId,
              'player_id': pId,
              'side': 'B',
            });
          }
        }

        savedMatches.add(match);
      }
      savedRounds.add(DrawingRound(
        roundNumber: round.roundNumber,
        matches: savedMatches,
        restingPlayers: round.restingPlayers,
      ));
    }

    return savedRounds;
  }

  /// Mengambil data hasil drawing yang tersimpan di tb_match & tb_match_participant
  Future<List<DrawingRound>?> loadSavedDrawing({
    required dynamic sessionId,
    List<GamePlayerItem>? registeredPlayers,
  }) async {
    try {
      if (sessionId == null) return null;
      final parsedSessionId = int.tryParse(sessionId.toString()) ?? sessionId;

      final drawingId = await getLatestDrawingId(parsedSessionId);
      if (drawingId == null) return null;

      final matches = await _supabase
          .from('tb_match')
          .select()
          .eq('drawing_id', drawingId)
          .order('nomor_match', ascending: true);

      if (matches.isEmpty) return null;

      // 1. Ambil seluruh session player terdaftar jika belum diberikan
      List<GamePlayerItem> allPlayers = registeredPlayers != null ? List.from(registeredPlayers) : [];
      if (allPlayers.isEmpty) {
        try {
          final sPlayers = await _supabase
              .from('tb_session_player')
              .select('player_id')
              .eq('session_id', parsedSessionId);

          final spIds = (sPlayers as List)
              .map((sp) => sp['player_id'])
              .where((id) => id != null)
              .map((id) => id is int ? id : int.tryParse(id.toString()))
              .whereType<int>()
              .toList();

          if (spIds.isNotEmpty) {
            final pList = await _supabase
                .from('tb_player')
                .select('player_id, user_id, nama, foto, level')
                .inFilter('player_id', spIds);

            for (final pData in pList) {
              final pId = pData['player_id'];
              if (pId != null) {
                allPlayers.add(GamePlayerItem(
                  id: pId.toString(),
                  playerId: pId is int ? pId : int.tryParse(pId.toString()),
                  userId: pData['user_id'] as int?,
                  name: (pData['nama'] ?? pData['nama_player'])?.toString() ?? 'Pemain',
                  avatarUrl: pData['foto']?.toString(),
                  level: pData['level']?.toString() ?? 'Beginner',
                ));
              }
            }
          }
        } catch (_) {}
      }

      // 2. Batch ambil data partisipan untuk seluruh match
      final matchIds = matches
          .map((m) => m['match_id'] is int ? m['match_id'] as int : int.parse(m['match_id'].toString()))
          .toList();

      List<Map<String, dynamic>> allParticipants = [];
      try {
        final pRes = await _supabase
            .from('tb_match_participant')
            .select('match_id, player_id, side')
            .inFilter('match_id', matchIds);
        allParticipants = List<Map<String, dynamic>>.from(pRes as List);
      } catch (_) {}

      // Batch ambil profil pemain di partisipan jika belum ada di allPlayers
      final participantPlayerIds = allParticipants
          .map((p) => p['player_id'])
          .where((id) => id != null)
          .map((id) => id is int ? id : int.tryParse(id.toString()))
          .whereType<int>()
          .toSet()
          .toList();

      final Map<int, Map<String, dynamic>> playerDetailsMap = {};
      final missingPlayerIds = participantPlayerIds.where((id) => !allPlayers.any((ap) => ap.playerId == id)).toList();
      if (missingPlayerIds.isNotEmpty) {
        try {
          final pList = await _supabase
              .from('tb_player')
              .select('player_id, user_id, nama, foto, level')
              .inFilter('player_id', missingPlayerIds);
          for (final pData in pList) {
            final pid = pData['player_id'] as int?;
            if (pid != null) {
              playerDetailsMap[pid] = pData;
              allPlayers.add(GamePlayerItem(
                id: pid.toString(),
                playerId: pid,
                userId: pData['user_id'] as int?,
                name: (pData['nama'] ?? pData['nama_player'])?.toString() ?? 'Pemain',
                avatarUrl: pData['foto']?.toString(),
                level: pData['level']?.toString() ?? 'Beginner',
              ));
            }
          }
        } catch (_) {}
      }

      // 3. Map match per ronde dengan rekonstruksi dari nomor_match (Web parity)
      int sessionCourtCount = 1;
      try {
        final sCourts = await _supabase
            .from('tb_session_court')
            .select('court_id')
            .eq('session_id', parsedSessionId);
        if ((sCourts as List).isNotEmpty) {
          sessionCourtCount = sCourts.length;
        }
      } catch (_) {}
      final effectiveCourtCount = sessionCourtCount > 0 ? sessionCourtCount : 1;

      final Map<int, List<DrawingMatch>> roundMap = {};

      for (final match in matches) {
        final matchId = match['match_id'] is int
            ? match['match_id'] as int
            : int.parse(match['match_id'].toString());
        final nomorMatch = match['nomor_match'] is int
            ? match['nomor_match'] as int
            : (int.tryParse(match['nomor_match']?.toString() ?? '') ?? 1);
        final roundNum = ((nomorMatch - 1) ~/ effectiveCourtCount) + 1;
        final courtNum = ((nomorMatch - 1) % effectiveCourtCount) + 1;

        final List<GamePlayerItem> teamA = [];
        final List<GamePlayerItem> teamB = [];

        final matchParticipants = allParticipants.where((p) => p['match_id'] == matchId);
        for (final p in matchParticipants) {
          final pId = p['player_id'];
          if (pId == null) continue;
          final int? parsedPId = pId is int ? pId : int.tryParse(pId.toString());

          final existingPlayer = allPlayers.where((ap) => ap.playerId == parsedPId || ap.id == pId.toString()).firstOrNull;
          final pData = parsedPId != null ? playerDetailsMap[parsedPId] : null;

          final pName = existingPlayer?.name ?? (pData?['nama'] ?? pData?['nama_player'])?.toString() ?? 'Pemain';
          final playerItem = GamePlayerItem(
            id: pId.toString(),
            playerId: parsedPId,
            userId: existingPlayer?.userId ?? pData?['user_id'] as int?,
            name: pName,
            avatarUrl: existingPlayer?.avatarUrl ?? pData?['foto']?.toString(),
            level: existingPlayer?.level ?? pData?['level']?.toString() ?? 'Beginner',
          );

          final sideVal = (p['side'] ?? '').toString().trim().toUpperCase();
          final isB = sideVal == 'B' || sideVal == 'TEAM_B' || sideVal == 'SIDE_B' || sideVal.contains('B');
          if (isB) {
            teamB.add(playerItem);
          } else {
            teamA.add(playerItem);
          }
        }

        final drawingMatch = DrawingMatch(
          courtNumber: courtNum,
          teamA: teamA,
          teamB: teamB,
          status: match['status_match']?.toString() ?? 'Scheduled',
          winnerTeam: match['winner_team']?.toString(),
          matchId: matchId,
        );

        roundMap.putIfAbsent(roundNum, () => []).add(drawingMatch);
      }

      if (roundMap.isEmpty) return null;

      final List<DrawingRound> reconstructedRounds = [];
      final sortedKeys = roundMap.keys.toList()..sort();

      for (final rNum in sortedKeys) {
        final rMatches = roundMap[rNum]!;
        final activePlayerIds = <String>{};
        for (final m in rMatches) {
          for (final p in m.teamA) {
            activePlayerIds.add(p.id);
            if (p.playerId != null) activePlayerIds.add(p.playerId.toString());
          }
          for (final p in m.teamB) {
            activePlayerIds.add(p.id);
            if (p.playerId != null) activePlayerIds.add(p.playerId.toString());
          }
        }

        final restingPlayers = allPlayers.where((p) {
          return !activePlayerIds.contains(p.id) &&
              (p.playerId == null || !activePlayerIds.contains(p.playerId.toString()));
        }).toList();

        reconstructedRounds.add(DrawingRound(
          roundNumber: rNum,
          matches: rMatches,
          restingPlayers: restingPlayers,
        ));
      }

      return reconstructedRounds;
    } catch (_) {
      return null;
    }
  }

  /// Mengunci drawing sesi pertandingan dan menandai status menjadi 'In Progress'
  Future<List<DrawingRound>> lockDrawingSession(
    dynamic sessionId, {
    required List<DrawingRound> rounds,
    List<GamePlayerItem>? allPlayers,
    int? matchFormatId,
  }) async {
    if (sessionId == null) return rounds;
    final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;

    List<DrawingRound> finalRounds = rounds;

    // 1. Cek apakah match sudah tersimpan di database
    final drawingId = await getOrCreateDrawingId(
      parsedId,
      matchFormatId: matchFormatId,
    );
    bool matchesExist = false;
    if (drawingId > 0) {
      final existingMatches = await _supabase
          .from('tb_match')
          .select('match_id')
          .eq('drawing_id', drawingId);

      matchesExist = (existingMatches as List).isNotEmpty;
    }

    if (!matchesExist && rounds.isNotEmpty) {
      finalRounds = await saveDrawingMatches(
        sessionId: parsedId,
        rounds: rounds,
        matchStatus: 'In Progress',
        allPlayers: allPlayers,
        matchFormatId: matchFormatId,
      );
    } else if (drawingId > 0) {
      await _supabase
          .from('tb_match')
          .update({'status_match': 'In Progress'})
          .eq('drawing_id', drawingId);

      final hasNullMatchId = rounds.any((r) => r.matches.any((m) => m.matchId == null || m.matchId! <= 0));
      if (hasNullMatchId) {
        final reloaded = await loadSavedDrawing(sessionId: parsedId, registeredPlayers: allPlayers);
        if (reloaded != null && reloaded.isNotEmpty) {
          finalRounds = reloaded;
        }
      }
    }

    // 2. Update status sesi di tb_session
    await _supabase
        .from('tb_session')
        .update({'status_session': 'In Progress'})
        .eq('session_id', parsedId);

    // 3. Broadcast event drawing_locked
    await broadcastDrawingLocked(parsedId);

    return finalRounds;
  }

  /// Broadcast event drawing diacak ulang atau diperbarui ke channel session
  Future<void> broadcastDrawingUpdate(dynamic sessionId) async {
    try {
      if (sessionId == null) return;
      final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;
      final channelName = 'session_$parsedId';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'drawing_updated',
        payload: {
          'session_id': parsedId,
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        },
      );
    } catch (_) {}
  }

  /// Broadcast event drawing dikunci ke channel session
  Future<void> broadcastDrawingLocked(dynamic sessionId) async {
    try {
      if (sessionId == null) return;
      final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;
      final channelName = 'session_$parsedId';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'drawing_locked',
        payload: {
          'session_id': parsedId,
          'is_locked': true,
          'status_session': 'In Progress',
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        },
      );
    } catch (_) {}
  }

  /// Mengambil sesi yang akan datang (Upcoming Session)
  Future<Map<String, dynamic>?> getUpcomingSession() async {
    try {
      final upcomingSessions = await _supabase
          .from('tb_session')
          .select('''
            session_id,
            nama_session,
            status_session,
            waktu_session,
            jenis_permainan,
            sport_id,
            tb_sport (nama_sport)
          ''')
          .neq('status_session', 'live')
          .neq('status_session', 'finished')
          .order('waktu_session', ascending: true)
          .limit(1);

      if (upcomingSessions.isNotEmpty) {
        final session = upcomingSessions.first;
        final sessionId = session['session_id'];

        int playerCount = 0;
        int courtCount = 0;
        try {
          final players = await _supabase
              .from('tb_session_player')
              .select('player_id')
              .eq('session_id', sessionId);
          playerCount = (players as List).length;
        } catch (_) {}

        try {
          final courts = await _supabase
              .from('tb_session_court')
              .select('court_id')
              .eq('session_id', sessionId);
          courtCount = (courts as List).length;
        } catch (_) {}

        final sportMap = session['tb_sport'] as Map<String, dynamic>?;
        final sportName = sportMap?['nama_sport']?.toString() ?? 'Tennis';

        return {
          'session_id': sessionId,
          'nama_session': session['nama_session'] ?? 'Upcoming Match Session',
          'sport_name': sportName,
          'player_count': playerCount > 0 ? playerCount : 6,
          'court_count': courtCount > 0 ? courtCount : 1,
          'waktu_session': session['waktu_session'],
        };
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  /// Mengambil ringkasan data statistik aplikasi secara dinamis dari Supabase
  Future<Map<String, int>> getSummaryStats() async {
    int playersCount = 8;
    int courtsCount = 2;
    int activeSessionsCount = 1;
    int totalSessionsCount = 1;

    try {
      final pRes = await _supabase.from('tb_player').select('player_id');
      if (pRes.isNotEmpty) {
        playersCount = pRes.length;
      }
    } catch (_) {}

    try {
      final cRes = await _supabase.from('tb_court').select('court_id');
      if (cRes.isNotEmpty) {
        courtsCount = cRes.length;
      }
    } catch (_) {}

    try {
      final sAll = await _supabase
          .from('tb_session')
          .select('session_id, status_session');
      if (sAll.isNotEmpty) {
        totalSessionsCount = sAll.length;
        activeSessionsCount = sAll.where((s) {
          final st = (s['status_session'] ?? '').toString().toLowerCase();
          return st == 'live' || st == 'in_progress' || st == 'active';
        }).length;
      }
    } catch (_) {}

    return {
      'players': playersCount,
      'courts': courtsCount,
      'activeSessions': activeSessionsCount,
      'totalSessions': totalSessionsCount,
    };
  }

  /// Menghapus channel subscription secara bersih
  Future<void> unsubscribe(RealtimeChannel? channel) async {
    if (channel != null) {
      await _supabase.removeChannel(channel);
    }
  }

  Future<List<int>> _ensureDrawingCourts({
    required dynamic sessionId,
    required int requiredCount,
  }) async {
    if (requiredCount < 1) {
      throw Exception('Jumlah court drawing tidak valid.');
    }

    final session = await _supabase
        .from('tb_session')
        .select('venue_id, sport_id')
        .eq('session_id', sessionId)
        .single();

    final venueId = session['venue_id'];
    final sportId = session['sport_id'];

    if (venueId == null || sportId == null) {
      throw Exception('Venue atau olahraga sesi belum lengkap.');
    }

    final courtRows = await _supabase
        .from('tb_court')
        .select('court_id, status_ketersediaan')
        .eq('venue_id', venueId)
        .eq('sport_id', sportId)
        .order('court_id', ascending: true);

    final venueCourtIds = <int>{};
    final availableCourtIds = <int>[];

    for (final row in courtRows) {
      final id = int.tryParse(row['court_id'].toString());
      if (id == null || id <= 0) continue;

      venueCourtIds.add(id);

      final status = row['status_ketersediaan']
          ?.toString()
          .trim()
          .toLowerCase();

      if (status == null || status.isEmpty || status == 'available') {
        availableCourtIds.add(id);
      }
    }

    final relations = await _supabase
        .from('tb_session_court')
        .select('court_id')
        .eq('session_id', sessionId)
        .order('court_id', ascending: true);

    final ids = <int>{};

    for (final row in relations) {
      final id = int.tryParse(row['court_id'].toString());
      if (id == null || !venueCourtIds.contains(id)) {
        throw Exception('Court sesi tidak sesuai venue atau olahraga.');
      }
      ids.add(id);
    }

    if (ids.length > requiredCount) {
      throw Exception(
        'Jumlah court sesi berbeda dari konfigurasi drawing. '
        'Periksa konfigurasi sebelum melanjutkan.',
      );
    }

    final missing = <int>[];

    for (final id in availableCourtIds) {
      if (ids.length + missing.length >= requiredCount) break;
      if (!ids.contains(id)) missing.add(id);
    }

    if (ids.length + missing.length < requiredCount) {
      throw Exception(
        'Venue tidak memiliki $requiredCount court yang tersedia '
        'untuk olahraga ini.',
      );
    }

    if (missing.isNotEmpty) {
      await _supabase.from('tb_session_court').insert(
        missing.map((id) => {
          'session_id': sessionId,
          'court_id': id,
        }).toList(),
      );
    }

    final result = <int>[...ids, ...missing]..sort();
    return result;
  }
}
