import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../../../../core/storage/matcha_hive_service.dart';
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

  /// Mengambil daftar pemain sesi dari tb_session_player bergabung dengan tb_player
  Future<List<GamePlayerItem>> getSessionPlayers(dynamic sessionId) async {
    try {
      if (sessionId == null) return [];
      final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;
      final res = await _supabase
          .from('tb_session_player')
          .select('''
            player_id,
            status,
            withdrawn_reason,
            withdrawn_at,
            tb_player (
              player_id,
              user_id,
              nama,
              level,
              gender,
              foto,
              tb_user (foto)
            )
          ''')
          .eq('session_id', parsedId);

      final List<GamePlayerItem> players = [];
      for (final row in (res as List)) {
        final rawPlayer = row['tb_player'] as Map<String, dynamic>?;
        final pId = row['player_id'] is int
            ? row['player_id'] as int
            : int.tryParse(row['player_id']?.toString() ?? '');
        if (pId == null) continue;

        final name = rawPlayer?['nama']?.toString() ?? 'Pemain $pId';
        final gender = rawPlayer?['gender']?.toString() ?? 'Laki-laki';
        final level = rawPlayer?['level']?.toString() ?? 'Beginner';
        final status = (row['status'] ?? 'active').toString().toLowerCase();
        final withdrawnReason = row['withdrawn_reason']?.toString();
        DateTime? withdrawnAt;
        if (row['withdrawn_at'] != null) {
          withdrawnAt = DateTime.tryParse(row['withdrawn_at'].toString());
        }
        final rawUser = rawPlayer?['tb_user'] as Map<String, dynamic>?;
        final avatar = rawPlayer?['foto']?.toString() ?? rawUser?['foto']?.toString();
        final rawUserId = rawPlayer?['user_id'];
        final uId = rawUserId is int
            ? rawUserId
            : int.tryParse(rawUserId?.toString() ?? '');

        players.add(GamePlayerItem(
          id: pId.toString(),
          playerId: pId,
          userId: uId,
          name: name,
          gender: gender,
          level: level,
          avatarUrl: avatar,
          status: status,
          withdrawnReason: withdrawnReason,
          withdrawnAt: withdrawnAt,
        ));
      }
      return players;
    } catch (e) {
      debugPrint('[MatchService] getSessionPlayers error: $e');
      return [];
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

  /// Resolusi integer ID pemain dari GamePlayerItem (mendukung playerId, numeric id, user_X, guest_db_X, atau pencocokan nama di allPlayers)
  static int? resolvePlayerId(GamePlayerItem? player, [List<GamePlayerItem>? allPlayers]) {
    if (player == null) return null;
    if (player.playerId != null && player.playerId! > 0) return player.playerId;
    final direct = int.tryParse(player.id);
    if (direct != null && direct > 0) return direct;
    if (allPlayers != null && allPlayers.isNotEmpty) {
      final cleanName = player.name.trim().toLowerCase();
      final found = allPlayers.where((p) => p.name.trim().toLowerCase() == cleanName).firstOrNull;
      if (found != null) {
        if (found.playerId != null && found.playerId! > 0) return found.playerId;
        final fDirect = int.tryParse(found.id);
        if (fDirect != null && fDirect > 0) return fDirect;
      }
    }
    if (player.id.startsWith('user_') || player.id.startsWith('guest_db_')) {
      final match = RegExp(r'\d+').firstMatch(player.id);
      if (match != null) return int.tryParse(match.group(0)!);
    }
    return null;
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
    dynamic sessionId, {
    int? courtCount,
  }) async {
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

      int sessionCourtCount = (courtCount != null && courtCount > 0) ? courtCount : 0;
      if (sessionCourtCount <= 0) {
        try {
          final sCourts = await _supabase
              .from('tb_session_court')
              .select('court_id')
              .eq('session_id', parsedSessionId);
          if ((sCourts as List).isNotEmpty) {
            final uniqueCourtIds = (sCourts as List)
                .map((c) => int.tryParse(c['court_id']?.toString() ?? ''))
                .whereType<int>()
                .where((id) => id > 0)
                .toSet();
            if (uniqueCourtIds.isNotEmpty) {
              sessionCourtCount = uniqueCourtIds.length;
            }
          }
        } catch (_) {}
      }
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
    if (lower == 'stopped') {
      return 'Stopped';
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
    final scorePayload = {
      'matchId': matchId,
      'sessionId': sessionId,
      'setNumber': setNumber,
      'gameNumber': gameNumber,
      'scoreA': scoreA,
      'scoreB': scoreB,
      'gameScoreA': gameScoreA ?? scoreA,
      'gameScoreB': gameScoreB ?? scoreB,
      'pointScoreA': pointScoreA,
      'pointScoreB': pointScoreB,
      'setScoreA': setScoreA,
      'setScoreB': setScoreB,
      'scoringSystem': scoringSystem,
      'statusScore': statusScore,
      'winnerTeam': winnerTeam,
      'version': version,
      'lastEventId': lastEventId,
      'matchKey': matchKey,
      'updatedAt': DateTime.now().toIso8601String(),
    };

    // 1. Simpan ke Hive dan perbarui antrean offline seketika (State Snapshot Coalescing)
    try {
      await MatchaHiveService.enqueueOrUpdateScoreAction(
        matchId: matchId,
        scorePayload: scorePayload,
      );
    } catch (_) {}

    try {
      // 0. Defensive guard: If match status is Stopped, reject score mutation immediately
      final existingMatch = await _supabase
          .from('tb_match')
          .select('status_match')
          .eq('match_id', matchId)
          .maybeSingle();
      if (existingMatch != null) {
        final sMatch = (existingMatch['status_match'] ?? '').toString().toLowerCase();
        if (sMatch == 'stopped') {
          debugPrint('[MatchService] Rejected saveMatchScore: Match $matchId is already Stopped.');
          return;
        }
      }

      final now = DateTime.now();
      final nowIso = now.toIso8601String();
      final nowTime =
          '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';

      // 2. Delegasikan ke MatchRemoteDataSource untuk operasi tulis ke Supabase
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

      // 3. Database berhasil -> kirim broadcast ke Realtime Channel sesuai Web
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
      } catch (_) {}

      // 4. Sukses disinkronkan ke Supabase, hapus aksi dari antrean pending Hive
      try {
        await MatchaHiveService.removeSyncAction('score_action_$matchId');
      } catch (_) {}
    } catch (e) {
      debugPrint('[MatchService] saveMatchScore offline/network error: Saved in Hive. Error: $e');
      // Tidak melempar exception fatal agar UI scoring tetap responsif
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
    try {
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
    } catch (e) {
      debugPrint('[MatchService] finishMatch offline, queuing in Hive: $e');
      await MatchaHiveService.enqueueSyncAction(
        actionType: 'FINISH_MATCH',
        actionId: 'finish_match_$matchId',
        payload: {
          'matchId': matchId,
          'winnerTeam': winnerTeam,
          'hasilPertandingan': hasilPertandingan,
        },
      );
    }
  }

  /// Memperbarui status match di tb_match menjadi Stopped dengan mempertahankan skor parsial
  Future<void> stopMatch({
    required int matchId,
    dynamic sessionId,
    required String reason,
    String? stopType,
    int? stoppedByUserId,
    String? hasilPertandingan,
    List<int>? pausedPlayerIds,
    List<int>? withdrawnPlayerIds,
    Map<int, String>? playerReasons,
    int? scoreA,
    int? scoreB,
    String? pointDisplayA,
    String? pointDisplayB,
    int? roundNumber,
    String? scoringSystem,
  }) async {
    final now = DateTime.now();
    final nowIso = now.toIso8601String();
    final nowTime =
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';

    final effectiveStopType = stopType ??
        ((pausedPlayerIds != null && pausedPlayerIds.isNotEmpty)
            ? 'temporary_pause'
            : ((withdrawnPlayerIds != null && withdrawnPlayerIds.isNotEmpty)
                ? 'withdraw_players'
                : 'keep_in_session'));

    final effectiveWithdrawn = (withdrawnPlayerIds != null && withdrawnPlayerIds.isNotEmpty)
        ? withdrawnPlayerIds
        : (pausedPlayerIds ?? []);

    final notesData = <String, dynamic>{};
    if (playerReasons != null) {
      playerReasons.forEach((k, v) => notesData[k.toString()] = v);
    }
    notesData['stop_type'] = effectiveStopType;
    notesData['stop_reason'] = reason;
    notesData['timestamp'] = nowIso;

    // 1. Update tb_match
    await _supabase
        .from('tb_match')
        .update({
          'status_match': 'Stopped',
          'waktu_selesai': nowTime,
          'winner_team': null,
          'stop_type': effectiveStopType,
          'stop_reason': reason,
          'stopped_by_user_id': stoppedByUserId,
          'stopped_at': nowIso,
          'hasil_pertandingan': hasilPertandingan ?? 'Dihentikan: ${scoreA ?? 0} - ${scoreB ?? 0} ($reason)',
          'withdrawn_player_ids': jsonEncode(effectiveWithdrawn),
          'player_notes': jsonEncode(notesData),
          'updated_at': nowIso,
        })
        .eq('match_id', matchId);

    // 2. Update atau insert baris di tb_score dengan status_score 'Stopped'
    try {
      final existingScore = await _supabase
          .from('tb_score')
          .select('score_id')
          .eq('match_id', matchId)
          .maybeSingle();

      if (existingScore != null) {
        final updateData = <String, dynamic>{
          'status_score': 'Stopped',
          'updated_at': nowIso,
        };
        if (scoreA != null) {
          updateData['game_score_a'] = scoreA;
          updateData['score_side_a'] = scoreA;
        }
        if (scoreB != null) {
          updateData['game_score_b'] = scoreB;
          updateData['score_side_b'] = scoreB;
        }
        if (pointDisplayA != null) updateData['point_score_a'] = pointDisplayA;
        if (pointDisplayB != null) updateData['point_score_b'] = pointDisplayB;

        await _supabase
            .from('tb_score')
            .update(updateData)
            .eq('match_id', matchId);
      } else {
        await _supabase.from('tb_score').insert({
          'match_id': matchId,
          'set_number': roundNumber ?? 1,
          'game_number': 1,
          'game_score_a': scoreA ?? 0,
          'game_score_b': scoreB ?? 0,
          'score_side_a': scoreA ?? 0,
          'score_side_b': scoreB ?? 0,
          'point_score_a': pointDisplayA ?? '0',
          'point_score_b': pointDisplayB ?? '0',
          'status_score': 'Stopped',
          'scoring_system': scoringSystem ?? 'total_of_sets',
          'created_at': nowIso,
          'updated_at': nowIso,
        });
      }
    } catch (e) {
      debugPrint('[MatchService] tb_score update/insert on stopMatch error: $e');
    }

    // 3. Perbarui status pemain di tb_session_player jika ada pemain yang diistirahatkan/keluar
    final parsedSessionId = int.tryParse(sessionId?.toString() ?? '') ?? sessionId;
    if (parsedSessionId != null) {
      if (pausedPlayerIds != null && pausedPlayerIds.isNotEmpty) {
        for (final pId in pausedPlayerIds) {
          try {
            await _supabase.from('tb_session_player').update({
              'status': 'resting',
              'withdrawn_reason': playerReasons?[pId] ?? reason,
              'withdrawn_match_id': matchId,
            }).eq('session_id', parsedSessionId).eq('player_id', pId);
          } catch (e) {
            debugPrint('[MatchService] tb_session_player resting error: $e');
          }
        }
      }

      if (withdrawnPlayerIds != null && withdrawnPlayerIds.isNotEmpty) {
        for (final pId in withdrawnPlayerIds) {
          try {
            await _supabase.from('tb_session_player').update({
              'status': 'withdrawn',
              'withdrawn_reason': playerReasons?[pId] ?? reason,
              'withdrawn_at': nowIso,
              'withdrawn_match_id': matchId,
            }).eq('session_id', parsedSessionId).eq('player_id', pId);
          } catch (e) {
            debugPrint('[MatchService] tb_session_player withdrawn error: $e');
          }
        }
      }
    }

    // 4. Broadcast status change setelah DB berhasil
    try {
      final channelName = sessionId != null
          ? 'session_$sessionId'
          : 'match_arena_live';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'match_status_update',
        payload: {
          'match_id': matchId,
          'status_match': 'Stopped',
          'session_id': sessionId,
          'stop_reason': reason,
          'stop_type': effectiveStopType,
          'paused_player_ids': pausedPlayerIds,
          'withdrawn_player_ids': withdrawnPlayerIds,
          'score_a': scoreA,
          'score_b': scoreB,
          'timestamp': now.millisecondsSinceEpoch,
        },
      );
      await channel.sendBroadcastMessage(
        event: 'player_status_update',
        payload: {
          'session_id': sessionId,
          'match_id': matchId,
          'paused_player_ids': pausedPlayerIds,
          'withdrawn_player_ids': withdrawnPlayerIds,
          'timestamp': now.millisecondsSinceEpoch,
        },
      );
    } catch (_) {}
  }

  bool _hasStartedScoring(
    Map<String, dynamic> match,
    List<dynamic> scoreRows,
  ) {
    final status = match['status_match']?.toString().trim().toLowerCase();
    if (status == null ||
        status.isEmpty ||
        status == 'scheduled' ||
        status == 'completed' ||
        status == 'stopped' ||
        status == 'finished' ||
        status == 'cancelled') {
      return false;
    }
    if (match['last_event_id']?.toString().trim().isNotEmpty == true) {
      return true;
    }

    bool hasPositiveValue(dynamic value) =>
        (num.tryParse(value?.toString() ?? '') ?? 0) > 0;
    bool hasActivePoint(dynamic value) {
      final point = value?.toString().trim().toLowerCase() ?? '';
      return point.isNotEmpty &&
          point != '0' &&
          point != '0-0' &&
          point != '0 - 0' &&
          point != 'love';
    }

    return scoreRows.any((row) {
      if (row['match_id']?.toString() != match['match_id']?.toString()) {
        return false;
      }
      return hasPositiveValue(row['game_score_a']) ||
          hasPositiveValue(row['game_score_b']) ||
          hasPositiveValue(row['score_side_a']) ||
          hasPositiveValue(row['score_side_b']) ||
          hasActivePoint(row['point_score_a']) ||
          hasActivePoint(row['point_score_b']) ||
          row['last_event_id']?.toString().trim().isNotEmpty == true ||
          row['status_score']?.toString().toLowerCase() == 'eventlog' ||
          (int.tryParse(row['version']?.toString() ?? '') ?? 0) > 1;
    });
  }

  /// Mengelola status partisipasi pemain (active, resting, withdrawn) di tb_session_player
  Future<Map<String, dynamic>> managePlayerStatus({
    required dynamic sessionId,
    required int playerId,
    required String action, // 'pause', 'withdraw', 'reactivate'
    String? reason,
  }) async {
    final parsedSessionId = int.tryParse(sessionId.toString()) ?? sessionId;
    final now = DateTime.now().toIso8601String();

    String targetStatus;
    if (action == 'pause') {
      targetStatus = 'resting';
    } else if (action == 'withdraw') {
      targetStatus = 'withdrawn';
    } else if (action == 'reactivate') {
      targetStatus = 'active';
    } else {
      throw Exception('Aksi status tidak valid: $action');
    }

    if (action == 'reactivate') {
      final cur = await _supabase
          .from('tb_session_player')
          .select('status')
          .eq('session_id', parsedSessionId)
          .eq('player_id', playerId)
          .maybeSingle();
      if (cur != null && (cur['status']?.toString().toLowerCase() == 'withdrawn')) {
        throw Exception('Pemain yang telah keluar secara permanen tidak dapat diaktifkan kembali.');
      }
    }

    if (action == 'pause' || action == 'withdraw') {
      final sessionData = await _supabase
          .from('tb_session')
          .select('active_round')
          .eq('session_id', parsedSessionId)
          .maybeSingle();
      final activeRoundText = sessionData?['active_round']?.toString() ?? '';
      final roundMatch = RegExp(r'(\d+)').firstMatch(activeRoundText);
      final activeRoundNumber =
          int.tryParse(roundMatch?.group(1) ?? '') ?? 1;
      final drawingId = await getLatestDrawingId(parsedSessionId);
      if (drawingId != null) {
        final courtRows = await _supabase
            .from('tb_session_court')
            .select('court_id')
            .eq('session_id', parsedSessionId);
        final courtIds = (courtRows as List)
            .map((court) => int.tryParse(court['court_id']?.toString() ?? ''))
            .whereType<int>()
            .where((id) => id > 0)
            .toSet();
        final courtCount = courtIds.isNotEmpty ? courtIds.length : 1;
        final startMatchNumber = (activeRoundNumber - 1) * courtCount + 1;
        final endMatchNumber = activeRoundNumber * courtCount;
        final currentMatches = await _supabase
            .from('tb_match')
            .select('match_id, nomor_match, status_match, last_event_id')
            .eq('drawing_id', drawingId)
            .gte('nomor_match', startMatchNumber)
            .lte('nomor_match', endMatchNumber);
        final activeCandidates = (currentMatches as List)
            .where((match) {
              final status =
                  match['status_match']?.toString().toLowerCase() ?? '';
              return status != 'scheduled' &&
                  status != 'completed' &&
                  status != 'stopped' &&
                  status != 'finished' &&
                  status != 'cancelled' &&
                  status.isNotEmpty;
            })
            .toList();
        final activeMatchIds = activeCandidates
            .map((match) => int.tryParse(match['match_id']?.toString() ?? ''))
            .whereType<int>()
            .toList();
        if (activeMatchIds.isNotEmpty) {
          final scoreRows = await _supabase
              .from('tb_score')
              .select(
                'match_id, game_score_a, game_score_b, score_side_a, score_side_b, point_score_a, point_score_b, status_score, version, last_event_id',
              )
              .inFilter('match_id', activeMatchIds);
          for (final match in activeCandidates) {
            if (!_hasStartedScoring(match, List<dynamic>.from(scoreRows))) {
              continue;
            }
            final hasPlayer = await _supabase
                .from('tb_match_participant')
                .select('match_id')
                .eq('match_id', match['match_id'])
                .eq('player_id', playerId)
                .maybeSingle();
            if (hasPlayer != null) {
              final nomorMatch =
                  int.tryParse(match['nomor_match']?.toString() ?? '') ?? 1;
              final courtNumber = ((nomorMatch - 1) % courtCount) + 1;
              return {
                'success': false,
                'is_playing': true,
                'court': courtNumber,
                'message':
                    'Pemain sedang bertanding di Court $courtNumber. Silakan hentikan match terkait sebelum mengubah statusnya.',
              };
            }
          }
        }
      }
    }

    final updatePayload = <String, dynamic>{
      'status': targetStatus,
      'withdrawn_reason': (action == 'reactivate') ? null : reason,
      'withdrawn_at': (action == 'withdraw') ? now : (action == 'reactivate' ? null : null),
    };

    await _supabase
        .from('tb_session_player')
        .update(updatePayload)
        .eq('session_id', parsedSessionId)
        .eq('player_id', playerId);

    // Broadcast player_status_update
    try {
      final channelName = 'session_$parsedSessionId';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'player_status_update',
        payload: {
          'session_id': parsedSessionId,
          'player_id': playerId,
          'action': action,
          'status': targetStatus,
          'reason': reason,
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        },
      );
    } catch (_) {}

    return {
      'success': true,
      'status': targetStatus,
      'needs_schedule_adjustment': (action == 'pause' || action == 'withdraw'),
    };
  }

  /// Menghentikan sesi pertandingan karena jumlah pemain aktif tidak mencukupi (Web parity)
  Future<void> stopSessionDueToInsufficientPlayers({
    required dynamic sessionId,
    required String reason,
    int? stopPlayerId,
  }) async {
    if (sessionId == null) return;
    final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;

    try {
      // 1. Ambil drawing_id
      final drawingId = await getLatestDrawingId(parsedId);
      if (drawingId != null) {
        // Hentikan semua match yang masih Scheduled atau In Progress
        await _supabase
            .from('tb_match')
            .update({
              'status_match': 'Stopped',
              'stop_type': 'withdraw_players',
              'stop_reason': reason,
            })
            .eq('drawing_id', drawingId)
            .inFilter('status_match', ['Scheduled', 'In Progress', 'Live']);
      }

      // 2. Tandai session sebagai Completed
      await _supabase
          .from('tb_session')
          .update({'status_session': 'Completed'})
          .eq('session_id', parsedId);

      // 3. Broadcast session_finished dan drawing_updated
      final channelName = 'session_$parsedId';
      final channel = _supabase.channel(channelName);
      await channel.sendBroadcastMessage(
        event: 'session_finished',
        payload: {
          'session_id': parsedId,
          'status_session': 'Completed',
          'reason': reason,
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        },
      );
      await channel.sendBroadcastMessage(
        event: 'drawing_updated',
        payload: {
          'session_id': parsedId,
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        },
      );
    } catch (e) {
      debugPrint('[MatchService] stopSessionDueToInsufficientPlayers error: $e');
    }
  }

  /// Memperbarui partisipan pada match Scheduled ketika terjadi pergantian pemain dari bench
  Future<void> substituteMatchParticipant({
    required int matchId,
    required int oldPlayerId,
    required int newPlayerId,
    required String side,
  }) async {
    try {
      await _supabase
          .from('tb_match_participant')
          .update({'player_id': newPlayerId})
          .eq('match_id', matchId)
          .eq('player_id', oldPlayerId);
    } catch (e) {
      debugPrint('[MatchService] substituteMatchParticipant error: $e');
    }
  }

  /// Menghapus partisipan dan menandai match Scheduled sebagai Stopped karena pemain keluar tanpa pengganti
  Future<void> cancelOrStopScheduledMatch({
    required int matchId,
    required String reason,
  }) async {
    try {
      await _supabase
          .from('tb_match_participant')
          .delete()
          .eq('match_id', matchId);

      await _supabase
          .from('tb_match')
          .update({
            'status_match': 'Stopped',
            'stop_type': 'withdraw_players',
            'stop_reason': reason,
          })
          .eq('match_id', matchId);
    } catch (e) {
      debugPrint('[MatchService] cancelOrStopScheduledMatch error: $e');
    }
  }

  /// Mengatur ulang partisipan pada match tertentu (misal setelah reaktivasi court kosong)
  Future<void> setMatchParticipants({
    required int matchId,
    required List<GamePlayerItem> teamA,
    required List<GamePlayerItem> teamB,
    List<GamePlayerItem>? allPlayers,
  }) async {
    try {
      await _supabase
          .from('tb_match_participant')
          .delete()
          .eq('match_id', matchId);

      for (final p in teamA) {
        final pId = resolvePlayerId(p, allPlayers) ?? p.playerId ?? int.tryParse(p.id);
        if (pId != null && pId > 0) {
          await _supabase.from('tb_match_participant').insert({
            'match_id': matchId,
            'player_id': pId,
            'side': 'A',
          });
        }
      }

      for (final p in teamB) {
        final pId = resolvePlayerId(p, allPlayers) ?? p.playerId ?? int.tryParse(p.id);
        if (pId != null && pId > 0) {
          await _supabase.from('tb_match_participant').insert({
            'match_id': matchId,
            'player_id': pId,
            'side': 'B',
          });
        }
      }

      await _supabase
          .from('tb_match')
          .update({'status_match': 'Scheduled'})
          .eq('match_id', matchId);
    } catch (e) {
      debugPrint('[MatchService] setMatchParticipants error: $e');
    }
  }

  /// Menyesuaikan match aktif yang belum mulai scoring dan ronde mendatang saat pemain istirahat/keluar.
  Future<void> adjustFutureScheduleOnPlayerStatusChange({
    required dynamic sessionId,
    required int currentRoundNumber,
    required int targetPlayerId,
    required String action,
    List<GamePlayerItem>? allPlayers,
  }) async {
    if (sessionId == null || targetPlayerId <= 0) return;
    final parsedSessionId = int.tryParse(sessionId.toString()) ?? sessionId;

    try {
      final drawingId = await getLatestDrawingId(parsedSessionId);
      if (drawingId == null) return;

      final sCourts = await _supabase
          .from('tb_session_court')
          .select('court_id')
          .eq('session_id', parsedSessionId);
      final uniqueCourtIds = (sCourts as List)
          .map((c) => int.tryParse(c['court_id']?.toString() ?? ''))
          .whereType<int>()
          .where((id) => id > 0)
          .toSet();
      final effectiveCourtCount = uniqueCourtIds.isNotEmpty ? uniqueCourtIds.length : 1;

      final sPlayers = await _supabase
          .from('tb_session_player')
          .select('player_id, status')
          .eq('session_id', parsedSessionId);

      final excludedIds = <int>{};
      final allPlayerIds = <int>[];
      for (final sp in (sPlayers as List)) {
        final pid = int.tryParse(sp['player_id']?.toString() ?? '');
        if (pid != null) {
          allPlayerIds.add(pid);
          final st = sp['status']?.toString().toLowerCase();
          if (st == 'resting' || st == 'withdrawn' || st == 'paused' || (action != 'reactivate' && pid == targetPlayerId)) {
            excludedIds.add(pid);
          }
        }
      }

      final minNomorMatch =
          ((currentRoundNumber - 1) * effectiveCourtCount) + 1;
      final scheduleMatches = await _supabase
          .from('tb_match')
          .select('match_id, nomor_match, status_match, last_event_id')
          .eq('drawing_id', drawingId)
          .gte('nomor_match', minNomorMatch)
          .order('nomor_match', ascending: true);

      if (scheduleMatches.isEmpty) return;

      final currentRoundEnd = currentRoundNumber * effectiveCourtCount;
      final currentInProgressIds = (scheduleMatches as List)
          .where((match) {
            final nomor =
                int.tryParse(match['nomor_match']?.toString() ?? '') ?? 0;
            final status =
                match['status_match']?.toString().toLowerCase() ?? '';
            return nomor <= currentRoundEnd &&
                status != 'scheduled' &&
                status != 'completed' &&
                status != 'stopped' &&
                status != 'finished' &&
                status != 'cancelled' &&
                status.isNotEmpty;
          })
          .map((match) => int.tryParse(match['match_id']?.toString() ?? ''))
          .whereType<int>()
          .toList();
      final currentScoreRows = currentInProgressIds.isEmpty
          ? <dynamic>[]
          : List<dynamic>.from(
              await _supabase
                  .from('tb_score')
                  .select(
                    'match_id, game_score_a, game_score_b, score_side_a, score_side_b, point_score_a, point_score_b, status_score, version, last_event_id',
                  )
                  .inFilter('match_id', currentInProgressIds),
            );
      final editableMatches = (scheduleMatches as List).where((match) {
        final nomor =
            int.tryParse(match['nomor_match']?.toString() ?? '') ?? 0;
        final status = match['status_match']?.toString().toLowerCase() ?? '';
        if (nomor > currentRoundEnd) return status == 'scheduled';
        if (status == 'scheduled') return true;
        if (status == 'completed' ||
            status == 'stopped' ||
            status == 'finished' ||
            status == 'cancelled' ||
            status.isEmpty) {
          return false;
        }
        return !_hasStartedScoring(match, currentScoreRows);
      }).toList();
      if (editableMatches.isEmpty) return;

      final editableMatchIds = editableMatches
          .map((match) => int.tryParse(match['match_id']?.toString() ?? ''))
          .whereType<int>()
          .toList();

      final sessionData = await _supabase
          .from('tb_session')
          .select('jenis_permainan')
          .eq('session_id', parsedSessionId)
          .maybeSingle();
      final isSingle =
          sessionData?['jenis_permainan']?.toString().toLowerCase() == 'single';
      final minimumPlayers = isSingle ? 2 : 4;
      final activePlayerIds =
          allPlayerIds.where((playerId) => !excludedIds.contains(playerId));
      if (activePlayerIds.length < minimumPlayers) {
        await _supabase
            .from('tb_match_participant')
            .delete()
            .inFilter('match_id', editableMatchIds);
        await _supabase.from('tb_match').update({
          'status_match': 'Stopped',
          'stop_type': action == 'pause' ? 'temporary_pause' : 'withdraw_players',
          'stop_reason':
              'Jumlah pemain aktif tidak mencukupi untuk memulai pertandingan.',
        }).inFilter('match_id', editableMatchIds);
        await broadcastDrawingUpdate(parsedSessionId);
        final channel = _supabase.channel('session_$parsedSessionId');
        await channel.sendBroadcastMessage(
          event: 'schedule_adjusted',
          payload: {
            'session_id': parsedSessionId,
            'timestamp': DateTime.now().millisecondsSinceEpoch,
          },
        );
        return;
      }

      final allScheduleMatchIds = (scheduleMatches as List)
          .map((match) => int.tryParse(match['match_id']?.toString() ?? ''))
          .whereType<int>()
          .toList();
      final allParticipants = await _supabase
          .from('tb_match_participant')
          .select('match_id, player_id, side')
          .inFilter('match_id', allScheduleMatchIds);
      final participants = (allParticipants as List)
          .where((participant) => editableMatchIds.contains(
                int.tryParse(participant['match_id']?.toString() ?? ''),
              ))
          .toList();

      final matchesByRound = <int, List<Map<String, dynamic>>>{};
      for (final m in editableMatches) {
        final nom = int.tryParse(m['nomor_match']?.toString() ?? '') ?? 1;
        final rNum = ((nom - 1) ~/ effectiveCourtCount) + 1;
        matchesByRound.putIfAbsent(rNum, () => []).add(m);
      }

      for (final entry in matchesByRound.entries) {
        final rMatches = entry.value;
        final rMatchIds = rMatches.map((m) => int.tryParse(m['match_id']?.toString() ?? '')).whereType<int>().toSet();
        final allRoundMatchIds = (scheduleMatches as List)
            .where((match) {
              final nom =
                  int.tryParse(match['nomor_match']?.toString() ?? '') ?? 1;
              return ((nom - 1) ~/ effectiveCourtCount) + 1 == entry.key;
            })
            .map((match) => int.tryParse(match['match_id']?.toString() ?? ''))
            .whereType<int>()
            .toSet();

        final roundParticipants = participants
            .where((p) => rMatchIds.contains(int.tryParse(p['match_id']?.toString() ?? '')))
            .toList();

        final participatingPlayerIds = (allParticipants as List)
            .where((p) => allRoundMatchIds.contains(
                  int.tryParse(p['match_id']?.toString() ?? ''),
                ))
            .map((p) => int.tryParse(p['player_id']?.toString() ?? ''))
            .whereType<int>()
            .toSet();

        final availableBenchIds = allPlayerIds
            .where((pid) => !excludedIds.contains(pid) && !participatingPlayerIds.contains(pid))
            .toList();

        for (final p in roundParticipants) {
          final pId = int.tryParse(p['player_id']?.toString() ?? '');
          if (pId == targetPlayerId) {
            final mId = int.tryParse(p['match_id']?.toString() ?? '');
            if (mId != null) {
              if (availableBenchIds.isNotEmpty) {
                final substituteId = availableBenchIds.removeAt(0);
                participatingPlayerIds.add(substituteId);
                await _supabase
                    .from('tb_match_participant')
                    .update({'player_id': substituteId})
                    .eq('match_id', mId)
                    .eq('player_id', targetPlayerId);
              } else {
                await _supabase
                    .from('tb_match_participant')
                    .delete()
                    .eq('match_id', mId);
                await _supabase.from('tb_match').update({
                  'status_match': 'Stopped',
                  'stop_type':
                      action == 'pause' ? 'temporary_pause' : 'withdraw_players',
                  'stop_reason':
                      'Pemain istirahat atau keluar dan tidak tersedia pemain pengganti.',
                }).eq('match_id', mId);
              }
            }
          }
        }
      }

      await broadcastDrawingUpdate(parsedSessionId);
      final channel = _supabase.channel('session_$parsedSessionId');
      await channel.sendBroadcastMessage(
        event: 'schedule_adjusted',
        payload: {
          'session_id': parsedSessionId,
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        },
      );
    } catch (e) {
      debugPrint('[MatchService] adjustFutureScheduleOnPlayerStatusChange error: $e');
    }
  }

  /// Konfirmasi penyesuaian jadwal
  Future<void> confirmAdjustedSchedule({
    required dynamic sessionId,
    int? expectedVersion,
  }) async {
    final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;
    try {
      final channel = _supabase.channel('session_$parsedId');
      await channel.sendBroadcastMessage(
        event: 'schedule_adjusted',
        payload: {
          'session_id': parsedId,
          'confirmed': true,
          'timestamp': DateTime.now().millisecondsSinceEpoch,
        },
      );
    } catch (_) {}
  }

  /// Broadcast round advanced event ke channel realtime (sesuai Web broadcastRoundAdvancedRealtime)
  Future<void> broadcastRoundAdvanced({
    required dynamic sessionId,
    required int currentRoundNum,
    required int nextRoundNum,
    RealtimeChannel? activeChannel,
  }) async {
    final channelName = sessionId != null ? 'session_$sessionId' : 'match_arena_live';
    final payload = {
      'session_id': sessionId,
      'current_round': 'round_$currentRoundNum',
      'current_round_num': currentRoundNum,
      'next_round': 'round_$nextRoundNum',
      'next_round_num': nextRoundNum,
      'active_round': nextRoundNum,
      'session_active_round': nextRoundNum,
      'timestamp': DateTime.now().millisecondsSinceEpoch,
    };
    try {
      final channel = activeChannel ?? _supabase.channel(channelName);
      final res = await channel.sendBroadcastMessage(
        event: 'round_advanced',
        payload: payload,
      );
      debugPrint('[MATCHA_DEBUG] [Broadcast] round_advanced sent to $channelName (status: $res, payload: $payload)');
    } catch (e) {
      debugPrint('[MATCHA_ERROR] [Broadcast] Gagal broadcast round_advanced ke $channelName: $e');
    }
  }

  /// Memperbarui status pertandingan ronde berikutnya menjadi In Progress pada tb_match
  Future<void> advanceRoundMatches({
    required dynamic sessionId,
    required int roundNumber,
    List<int>? matchIds,
  }) async {
    try {
      if (sessionId == null) return;
      final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;
      final drawingId = await getLatestDrawingId(parsedId);
      debugPrint('[MATCHA_DEBUG] [AdvanceMatches] sessionId=$parsedId drawingId=$drawingId roundNumber=$roundNumber matchIds=$matchIds');

      // 1. Update status sesi di tb_session ke active_round baru
      try {
        await _supabase
            .from('tb_session')
            .update({
              'active_round': 'round_$roundNumber',
              'session_active_round': roundNumber,
            })
            .eq('session_id', parsedId);
      } catch (_) {}

      // 2. Update matches
      if (matchIds != null && matchIds.isNotEmpty) {
        await _supabase
            .from('tb_match')
            .update({'status_match': 'In Progress'})
            .inFilter('match_id', matchIds);
      } else if (drawingId != null) {
        final sCourts = await _supabase
            .from('tb_session_court')
            .select('court_id')
            .eq('session_id', parsedId);
        final uniqueCourtIds = (sCourts as List)
            .map((c) => int.tryParse(c['court_id']?.toString() ?? ''))
            .whereType<int>()
            .where((id) => id > 0)
            .toSet();
        final courtCount = uniqueCourtIds.isNotEmpty ? uniqueCourtIds.length : 1;

        final startMatchNo = (roundNumber - 1) * courtCount + 1;
        final endMatchNo = roundNumber * courtCount;

        await _supabase
            .from('tb_match')
            .update({'status_match': 'In Progress'})
            .eq('drawing_id', drawingId)
            .gte('nomor_match', startMatchNo)
            .lte('nomor_match', endMatchNo);

        // Pastikan match pada ronde-ronde sebelumnya berstatus Completed
        await _supabase
            .from('tb_match')
            .update({'status_match': 'Completed'})
            .eq('drawing_id', drawingId)
            .lt('nomor_match', startMatchNo)
            .neq('status_match', 'Stopped');
      }
    } catch (e) {
      debugPrint('[MATCHA_ERROR] [AdvanceMatches] Gagal update status match ronde $roundNumber: $e');
    }
  }

  /// Memperbarui status sesi menjadi Finished dan broadcast event session_finished
  Future<void> finishSession(dynamic sessionId) async {
    if (sessionId == null) return;
    final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;

    try {
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
    } catch (e) {
      debugPrint('[MatchService] finishSession offline, queuing in Hive: $e');
      await MatchaHiveService.enqueueSyncAction(
        actionType: 'FINISH_SESSION',
        actionId: 'finish_session_$parsedId',
        payload: {'sessionId': parsedId},
      );
    }
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

    // 1. Simpan ke Hive lokal terlebih dahulu agar selalu aman dibuka offline
    try {
      await MatchaHiveService.saveDrawingRounds(
        parsedSessionId,
        rounds.map((r) => r.toMap()).toList(),
      );
    } catch (_) {}

    try {
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

    for (final round in rounds) {
      final List<DrawingMatch> savedMatches = [];
      for (final match in round.matches) {
        if (match.courtNumber < 1 ||
            match.courtNumber > effectiveCourtCount) {
          throw Exception('Nomor court pertandingan tidak valid.');
        }

        final courtId = courtIds[match.courtNumber - 1];

        final nomorMatch =
            (round.roundNumber - 1) * effectiveCourtCount +
            match.courtNumber;

        final initialStatus = round.roundNumber == 1
            ? (matchStatus.isNotEmpty ? matchStatus : 'In Progress')
            : 'Scheduled';

        final matchInsert = await _supabase
            .from('tb_match')
            .insert({
              'drawing_id': drawingId,
              'court_id': courtId,
              'nomor_match': nomorMatch,
              'status_match': initialStatus,
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

    // Simpan hasil drawing tersinkron ke Hive
    try {
      await MatchaHiveService.saveDrawingRounds(
        parsedSessionId,
        savedRounds.map((r) => r.toMap()).toList(),
      );
    } catch (_) {}

      return savedRounds;
    } catch (e) {
      debugPrint('[MatchService] saveDrawingMatches offline/error, falling back to local rounds: $e');
      int fallbackId = 1000;
      for (final r in rounds) {
        for (final m in r.matches) {
          m.matchId ??= ++fallbackId;
        }
      }
      try {
        await MatchaHiveService.saveDrawingRounds(
          parsedSessionId,
          rounds.map((r) => r.toMap()).toList(),
        );
      } catch (_) {}
      return rounds;
    }
  }

  /// Mengambil data hasil drawing yang tersimpan di tb_match & tb_match_participant
  Future<List<DrawingRound>?> loadSavedDrawing({
    required dynamic sessionId,
    List<GamePlayerItem>? registeredPlayers,
    int? courtCount,
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

      // 1. Ambil seluruh session player terdaftar dan sinkronkan statusnya dari tb_session_player
      final spMap = <int, Map<String, dynamic>>{};
      try {
        final sPlayers = await _supabase
            .from('tb_session_player')
            .select('player_id, status, withdrawn_reason, withdrawn_at')
            .eq('session_id', parsedSessionId);
        for (final sp in (sPlayers as List)) {
          final pid = int.tryParse(sp['player_id']?.toString() ?? '');
          if (pid != null) spMap[pid] = sp;
        }
      } catch (_) {}

      List<GamePlayerItem> allPlayers = registeredPlayers != null ? List.from(registeredPlayers) : [];
      if (allPlayers.isEmpty) {
        try {
          final spIds = spMap.keys.toList();
          if (spIds.isNotEmpty) {
            final pList = await _supabase
                .from('tb_player')
                .select('player_id, user_id, nama, foto, level')
                .inFilter('player_id', spIds);

            for (final pData in pList) {
              final pId = pData['player_id'];
              if (pId != null) {
                final parsedPId = pId is int ? pId : int.tryParse(pId.toString());
                final spInfo = parsedPId != null ? spMap[parsedPId] : null;
                allPlayers.add(GamePlayerItem(
                  id: pId.toString(),
                  playerId: parsedPId,
                  userId: pData['user_id'] as int?,
                  name: (pData['nama'] ?? pData['nama_player'])?.toString() ?? 'Pemain',
                  avatarUrl: pData['foto']?.toString(),
                  level: pData['level']?.toString() ?? 'Beginner',
                  status: spInfo?['status']?.toString() ?? 'active',
                  withdrawnReason: spInfo?['withdrawn_reason']?.toString(),
                  withdrawnAt: spInfo?['withdrawn_at'] != null ? DateTime.tryParse(spInfo!['withdrawn_at'].toString()) : null,
                ));
              }
            }
          }
        } catch (_) {}
      } else if (allPlayers.isNotEmpty && spMap.isNotEmpty) {
        allPlayers = allPlayers.map((pl) {
          final pid = pl.playerId ?? int.tryParse(pl.id);
          final spInfo = pid != null ? spMap[pid] : null;
          if (spInfo != null) {
            final dbStatus = spInfo['status']?.toString();
            final dbReason = spInfo['withdrawn_reason']?.toString();
            final dbAt = spInfo['withdrawn_at'] != null ? DateTime.tryParse(spInfo['withdrawn_at'].toString()) : null;
            return pl.copyWith(
              status: dbStatus ?? pl.status,
              withdrawnReason: dbReason ?? pl.withdrawnReason,
              withdrawnAt: dbAt ?? pl.withdrawnAt,
            );
          }
          return pl;
        }).toList();
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
              final spInfo = spMap[pid];
              allPlayers.add(GamePlayerItem(
                id: pid.toString(),
                playerId: pid,
                userId: pData['user_id'] as int?,
                name: (pData['nama'] ?? pData['nama_player'])?.toString() ?? 'Pemain',
                avatarUrl: pData['foto']?.toString(),
                level: pData['level']?.toString() ?? 'Beginner',
                status: spInfo?['status']?.toString() ?? 'active',
                withdrawnReason: spInfo?['withdrawn_reason']?.toString(),
                withdrawnAt: spInfo?['withdrawn_at'] != null ? DateTime.tryParse(spInfo!['withdrawn_at'].toString()) : null,
              ));
            }
          }
        } catch (_) {}
      }

      // 3. Map match per ronde dengan rekonstruksi dari nomor_match (Web parity)
      int sessionCourtCount = (courtCount != null && courtCount > 0) ? courtCount : 0;
      if (sessionCourtCount <= 0) {
        try {
          final sCourts = await _supabase
              .from('tb_session_court')
              .select('court_id')
              .eq('session_id', parsedSessionId);
          if ((sCourts as List).isNotEmpty) {
            final uniqueCourtIds = (sCourts as List)
                .map((c) => int.tryParse(c['court_id']?.toString() ?? ''))
                .whereType<int>()
                .where((id) => id > 0)
                .toSet();
            if (uniqueCourtIds.isNotEmpty) {
              sessionCourtCount = uniqueCourtIds.length;
            }
          }
        } catch (_) {}
      }
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
          final spInfo = parsedPId != null ? spMap[parsedPId] : null;
          final pStatus = existingPlayer?.status ?? spInfo?['status']?.toString() ?? 'active';
          final pReason = existingPlayer?.withdrawnReason ?? spInfo?['withdrawn_reason']?.toString();
          final playerItem = GamePlayerItem(
            id: pId.toString(),
            playerId: parsedPId,
            userId: existingPlayer?.userId ?? pData?['user_id'] as int?,
            name: pName,
            avatarUrl: existingPlayer?.avatarUrl ?? pData?['foto']?.toString(),
            level: existingPlayer?.level ?? pData?['level']?.toString() ?? 'Beginner',
            status: pStatus,
            withdrawnReason: pReason,
            withdrawnAt: existingPlayer?.withdrawnAt,
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
          stopType: match['stop_type']?.toString(),
          stopReason: match['stop_reason']?.toString(),
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

      if (reconstructedRounds.isNotEmpty) {
        try {
          await MatchaHiveService.saveDrawingRounds(
            parsedSessionId,
            reconstructedRounds.map((r) => r.toMap()).toList(),
          );
        } catch (_) {}
      }

      return reconstructedRounds;
    } catch (e) {
      debugPrint('[MatchService] loadSavedDrawing failed, checking Hive cache: $e');
      final cached = MatchaHiveService.getDrawingRounds(sessionId);
      if (cached != null && cached.isNotEmpty) {
        return cached.map((map) => DrawingRound.fromMap(map)).toList();
      }
      return null;
    }
  }

  /// Mengunci drawing sesi pertandingan dan menandai status menjadi 'In Progress'
  Future<List<DrawingRound>> lockDrawingSession(
    dynamic sessionId, {
    required List<DrawingRound> rounds,
    List<GamePlayerItem>? allPlayers,
    required String playMode,
    int? matchFormatId,
    int? courtCount,
  }) async {
    if (sessionId == null) return rounds;
    final parsedId = int.tryParse(sessionId.toString()) ?? sessionId;

    final sessionPlayers = await _supabase
        .from('tb_session_player')
        .select('player_id, status')
        .eq('session_id', parsedId);
    final inactivePlayerIds = <int>{};
    int activePlayerCount = 0;
    for (final player in (sessionPlayers as List)) {
      final playerId = int.tryParse(player['player_id']?.toString() ?? '');
      if (playerId == null) continue;
      final status = player['status']?.toString().toLowerCase();
      if (status == 'resting' || status == 'withdrawn' || status == 'paused') {
        inactivePlayerIds.add(playerId);
      } else {
        activePlayerCount++;
      }
    }
    if (sessionPlayers.isEmpty && allPlayers != null) {
      activePlayerCount = allPlayers
          .where((player) => !player.isResting && !player.isWithdrawn)
          .length;
    }

    final isSingle = playMode.toLowerCase() == 'single';
    final minimumPlayers = isSingle ? 2 : 4;
    if (activePlayerCount < minimumPlayers) {
      throw Exception(
        'Pemain aktif tidak mencukupi (tersisa $activePlayerCount, minimal $minimumPlayers). Drawing belum dapat dikunci atau dimulai.',
      );
    }
    if (rounds.isEmpty) {
      throw Exception(
        'Drawing belum memiliki susunan pertandingan yang dapat dimulai.',
      );
    }
    final teamSize = isSingle ? 1 : 2;
    final firstRoundMatches = rounds.first.matches;
    final hasPlayableMatch = firstRoundMatches.any((match) =>
        match.teamA.length == teamSize &&
        match.teamB.length == teamSize &&
        [...match.teamA, ...match.teamB].every((player) {
          final playerId = resolvePlayerId(player, allPlayers) ??
              player.playerId ??
              int.tryParse(player.id);
          return !player.isResting &&
              !player.isWithdrawn &&
              (playerId == null || !inactivePlayerIds.contains(playerId));
        }));
    final hasInvalidMatch = firstRoundMatches.any((match) {
      if (match.teamA.length != teamSize || match.teamB.length != teamSize) {
        return true;
      }
      return [...match.teamA, ...match.teamB].any((player) {
        final playerId = resolvePlayerId(player, allPlayers) ??
            player.playerId ??
            int.tryParse(player.id);
        return player.isResting ||
            player.isWithdrawn ||
            (playerId != null && inactivePlayerIds.contains(playerId));
      });
    });
    if (!hasPlayableMatch || hasInvalidMatch) {
      throw Exception(
        'Susunan court belum lengkap atau masih berisi pemain yang istirahat/keluar. Perbaiki drawing sebelum memulai scoring.',
      );
    }

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
        courtCount: courtCount,
        matchFormatId: matchFormatId,
      );
    } else if (drawingId > 0) {
      final sCourts = await _supabase
          .from('tb_session_court')
          .select('court_id')
          .eq('session_id', parsedId);
      final uniqueCourtIds = (sCourts as List)
          .map((c) => int.tryParse(c['court_id']?.toString() ?? ''))
          .whereType<int>()
          .where((id) => id > 0)
          .toSet();
      final effectiveCourtCount = courtCount ?? (uniqueCourtIds.isNotEmpty ? uniqueCourtIds.length : 1);

      await _supabase
          .from('tb_match')
          .update({'status_match': 'In Progress'})
          .eq('drawing_id', drawingId)
          .lte('nomor_match', effectiveCourtCount);

      await _supabase
          .from('tb_match')
          .update({'status_match': 'Scheduled'})
          .eq('drawing_id', drawingId)
          .gt('nomor_match', effectiveCourtCount);

      final hasNullMatchId = rounds.any((r) => r.matches.any((m) => m.matchId == null || m.matchId! <= 0));
      if (hasNullMatchId) {
        final reloaded = await loadSavedDrawing(
          sessionId: parsedId,
          registeredPlayers: allPlayers,
          courtCount: courtCount,
        );
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
