import 'dart:math' as math;
import 'package:supabase_flutter/supabase_flutter.dart';
import '../domain/recap_models.dart';

class RecapService {
  final SupabaseClient _supabase;

  RecapService({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  static int _toInt(dynamic value, [int defaultValue = 0]) {
    if (value == null) return defaultValue;
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) {
      return int.tryParse(value) ?? defaultValue;
    }
    return defaultValue;
  }

  static int? _toNullableInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) {
      return int.tryParse(value);
    }
    return null;
  }

  /// Helper untuk memparsing identitas side/tim ('Team A', 'Team B', 'A', 'B', dsb)
  /// secara akurat tanpa terpengaruh huruf 'a' pada kata 'team'.
  static String? parseTeamSide(String? raw) {
    if (raw == null) return null;
    final clean = raw.trim().toUpperCase();
    if (clean.isEmpty) return null;
    if (clean == 'B' || clean == 'TEAM B' || clean == 'TEAM_B' || clean == 'SIDE B' || clean == 'SIDE_B') {
      return 'B';
    }
    if (clean == 'A' || clean == 'TEAM A' || clean == 'TEAM_A' || clean == 'SIDE A' || clean == 'SIDE_A') {
      return 'A';
    }
    final matchB = RegExp(r'(^|[^A-Z])B($|[^A-Z])').hasMatch(clean);
    final matchA = RegExp(r'(^|[^A-Z])A($|[^A-Z])').hasMatch(clean);
    if (matchB && !matchA) return 'B';
    if (matchA && !matchB) return 'A';
    return null;
  }

  /// Mengambil data rekap sesi mabar yang diselenggarakan oleh Host
  Future<HostRecapData> getHostRecap(int hostUserId) async {
    try {
      final response = await _supabase
          .from('tb_session')
          .select('''
            session_id,
            nama_session,
            host_user_id,
            sport_id,
            venue_id,
            datetime,
            waktu_session,
            jumlah_pemain,
            status_session,
            scoring_system,
            created_at,
            tb_sport (nama_sport),
            tb_venue (nama_venue),
            tb_session_court (tb_court (nama_court)),
            tb_session_player (tb_player (player_id, nama, gender, level, foto))
          ''')
          .eq('host_user_id', hostUserId)
          .order('created_at', ascending: false);

      final List<dynamic> list = response as List<dynamic>;

      int totalPlayers = 0;
      int completedCount = 0;
      final Map<String, int> venueCounts = {};

      final List<HostedSessionItem> sessions = [];

      for (final raw in list) {
        final map = Map<String, dynamic>.from(raw as Map);
        final playersList = (map['tb_session_player'] as List<dynamic>? ?? []);
        final joinedCount = playersList.length;
        totalPlayers += joinedCount;

        final status = (map['status_session']?.toString()) ?? 'Open';
        final statusLower = status.toLowerCase();
        if (statusLower.contains('ready') ||
            statusLower.contains('progress') ||
            statusLower.contains('complete') ||
            statusLower.contains('finish')) {
          completedCount++;
        }

        final venueMap = map['tb_venue'] as Map<String, dynamic>?;
        final venueName = (venueMap?['nama_venue']?.toString()) ?? 'Arena Olahraga';
        venueCounts[venueName] = (venueCounts[venueName] ?? 0) + 1;

        final sportMap = map['tb_sport'] as Map<String, dynamic>?;
        final sportName = (sportMap?['nama_sport']?.toString()) ?? 'Padel';

        String courtName = 'Court 1';
        final courtsList = map['tb_session_court'] as List<dynamic>?;
        if (courtsList != null && courtsList.isNotEmpty) {
          final firstCourt = courtsList.first as Map<String, dynamic>?;
          final cObj = firstCourt?['tb_court'] as Map<String, dynamic>?;
          if (cObj != null && cObj['nama_court'] != null) {
            courtName = cObj['nama_court'].toString();
          }
        }

        DateTime? dt;
        if (map['datetime'] != null) {
          dt = DateTime.tryParse(map['datetime'].toString());
        }
        final dateStr = dt != null ? formatDate(dt) : 'Hari Ini';
        final timeStr = (map['waktu_session']?.toString()) ?? '18:30 WIB';

        final List<HostedSessionPlayer> sessionPlayers = [];
        for (final p in playersList) {
          final pMap = p as Map<String, dynamic>?;
          final playerObj = pMap?['tb_player'] as Map<String, dynamic>?;
          if (playerObj != null) {
            sessionPlayers.add(
              HostedSessionPlayer(
                playerId: _toNullableInt(playerObj['player_id']),
                name: (playerObj['nama']?.toString()) ?? 'Pemain',
                gender: (playerObj['gender']?.toString()) ?? 'Male',
                level: (playerObj['level']?.toString()) ?? 'Intermediate',
                foto: playerObj['foto']?.toString(),
              ),
            );
          }
        }

        sessions.add(
          HostedSessionItem(
            sessionId: _toInt(map['session_id']),
            title: (map['nama_session']?.toString()) ?? 'Sesi Mabar',
            sport: sportName,
            venue: venueName,
            court: courtName,
            date: dateStr,
            time: timeStr,
            quota: _toInt(map['jumlah_pemain'], 6),
            joinedCount: joinedCount,
            status: status,
            scoringSystem: (map['scoring_system']?.toString()) ?? 'Total of 3 Sets',
            players: sessionPlayers,
          ),
        );
      }

      String favoriteVenue = '-';
      if (venueCounts.isNotEmpty) {
        final sortedVenues = venueCounts.entries.toList()
          ..sort((a, b) => b.value.compareTo(a.value));
        favoriteVenue = sortedVenues.first.key;
      }

      return HostRecapData(
        totalSessions: sessions.length,
        totalPlayers: totalPlayers,
        completedSessions: completedCount,
        favoriteVenue: favoriteVenue,
        sessions: sessions,
      );
    } catch (e) {
      throw Exception('Gagal memuat rekap host: $e');
    }
  }

  /// Mengambil data performa karier pemain dari database (persis seperti PlayerController di matcha-pt-web)
  Future<PlayerCareerRecapData> getPlayerCareerRecap(
    int userId, {
    int? playerId,
    String? userEmail,
    String? userNama,
    String? userFoto,
    String? userRole,
    String? userLevel,
  }) async {
    try {
      String playerName = userNama ?? 'Pemain Matcha';
      String currentLevel = userLevel ?? 'Intermediate';
      String? playerAvatar = (userFoto != null && userFoto.isNotEmpty) ? userFoto : null;
      String? playerRole = userRole;

      // 1. Kumpulkan SEMUA player_id milik user ini (user_id, email, atau nama)
      // Hal ini krusial karena satu user sering memiliki beberapa player_id historis di database
      final Set<int> allPlayerIds = {};
      if (playerId != null) allPlayerIds.add(playerId);

      // Cek di tb_user untuk identitas dasar jika belum ada
      try {
        final uRes = await _supabase
            .from('tb_user')
            .select('user_id, nama, email, foto, role')
            .eq('user_id', userId)
            .maybeSingle();
        if (uRes != null) {
          if ((playerName == 'Pemain Matcha' || playerName.isEmpty) && uRes['nama'] != null && uRes['nama'].toString().isNotEmpty) {
            playerName = uRes['nama'].toString();
          }
          if ((userEmail == null || userEmail.isEmpty) && uRes['email'] != null) {
            userEmail = uRes['email'].toString();
          }
          if (playerAvatar == null && uRes['foto'] != null && uRes['foto'].toString().isNotEmpty) {
            playerAvatar = uRes['foto'].toString();
          }
          if (playerRole == null && uRes['role'] != null) {
            playerRole = uRes['role'].toString() == 'venue_owner' ? 'Venue Owner' : 'Member';
          }
        }
      } catch (_) {}

      // Cek jika userId adalah player_id langsung di tb_player
      try {
        final pDirect = await _supabase
            .from('tb_player')
            .select('player_id, nama, level, foto, email, user_id, tb_user (nama, email, foto, role)')
            .eq('player_id', userId)
            .maybeSingle();
        if (pDirect != null) {
          final pid = _toNullableInt(pDirect['player_id']);
          if (pid != null) allPlayerIds.add(pid);

          final uObj = pDirect['tb_user'] as Map<String, dynamic>?;
          if (playerName == 'Pemain Matcha') {
            final pNama = (pDirect['nama'] ?? uObj?['nama'])?.toString();
            if (pNama != null && pNama.isNotEmpty) playerName = pNama;
          }
          if (pDirect['level'] != null && pDirect['level'].toString().isNotEmpty) {
            currentLevel = pDirect['level'].toString();
          }
          if (playerAvatar == null) {
            final pFoto = (pDirect['foto'] ?? uObj?['foto'])?.toString();
            if (pFoto != null && pFoto.isNotEmpty) playerAvatar = pFoto;
          }
          if (userEmail == null || userEmail.isEmpty) {
            userEmail = (pDirect['email'] ?? uObj?['email'])?.toString();
          }

          final linkedUid = _toNullableInt(pDirect['user_id']);
          if (linkedUid != null) {
            // Tarik juga semua player_id lain yang terhubung ke user_id ini
            final siblingPlayers = await _supabase
                .from('tb_player')
                .select('player_id, nama, level, foto, email')
                .eq('user_id', linkedUid);
            for (final sp in siblingPlayers as List<dynamic>) {
              final spid = _toNullableInt(sp['player_id']);
              if (spid != null) allPlayerIds.add(spid);
            }
          }
        }
      } catch (_) {}

      // Cek via user_id di tb_player
      try {
        final pUserRes = await _supabase
            .from('tb_player')
            .select('player_id, nama, level, foto, email, user_id')
            .eq('user_id', userId);
        for (final p in pUserRes as List<dynamic>) {
          final pid = _toNullableInt(p['player_id']);
          if (pid != null) allPlayerIds.add(pid);
          if (p['nama'] != null && p['nama'].toString().isNotEmpty && playerName == 'Pemain Matcha') {
            playerName = p['nama'].toString();
          }
          if (p['level'] != null && p['level'].toString().isNotEmpty) {
            currentLevel = p['level'].toString();
          }
          if (playerAvatar == null && p['foto'] != null && p['foto'].toString().isNotEmpty) {
            playerAvatar = p['foto'].toString();
          }
        }
      } catch (_) {}

      // Cek via email (seperti di web PlayerController)
      if (userEmail != null && userEmail.trim().isNotEmpty) {
        try {
          final pEmailRes = await _supabase
              .from('tb_player')
              .select('player_id, nama, level, foto, email, user_id')
              .eq('email', userEmail.trim().toLowerCase());
          for (final p in pEmailRes as List<dynamic>) {
            final pid = _toNullableInt(p['player_id']);
            if (pid != null) allPlayerIds.add(pid);
            if (p['nama'] != null && p['nama'].toString().isNotEmpty && playerName == 'Pemain Matcha') {
              playerName = p['nama'].toString();
            }
            if (p['level'] != null && p['level'].toString().isNotEmpty) {
              currentLevel = p['level'].toString();
            }
            if (playerAvatar == null && p['foto'] != null && p['foto'].toString().isNotEmpty) {
              playerAvatar = p['foto'].toString();
            }
          }
        } catch (_) {}
      }

      // Cek via nama
      if (playerName.isNotEmpty && playerName != 'Pemain Matcha') {
        try {
          final pNamaRes = await _supabase
              .from('tb_player')
              .select('player_id, nama, level, foto, email, user_id')
              .ilike('nama', playerName.trim());
          for (final p in pNamaRes as List<dynamic>) {
            final pid = _toNullableInt(p['player_id']);
            if (pid != null) allPlayerIds.add(pid);
            if (p['level'] != null && p['level'].toString().isNotEmpty) {
              currentLevel = p['level'].toString();
            }
            if (playerAvatar == null && p['foto'] != null && p['foto'].toString().isNotEmpty) {
              playerAvatar = p['foto'].toString();
            }
          }
        } catch (_) {}
      }

      final username = '@${playerName.toLowerCase().replaceAll(RegExp(r'[^a-z0-9_]'), '_')}';
      final avatar = (playerAvatar != null && playerAvatar.isNotEmpty)
          ? playerAvatar
          : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80';

      if (allPlayerIds.isEmpty) {
        return PlayerCareerRecapData(
          playerName: playerName,
          username: username,
          role: playerRole ?? 'Member',
          level: currentLevel,
          avatar: avatar,
          totalMatches: 0,
          wins: 0,
          losses: 0,
          winRate: '0%',
          totalHours: '0 Jam',
          streak: '0 Match',
          hasMatches: false,
          recentMatches: [],
          headToHead: [],
        );
      }

      // 2. Ambil data partisipasi pertandingan dari SEMUA player_id milik user
      final simpleParts = await _supabase
          .from('tb_match_participant')
          .select('match_id, player_id, side')
          .inFilter('player_id', allPlayerIds.toList());

      final partsList = simpleParts as List<dynamic>;
      if (partsList.isEmpty) {
        return PlayerCareerRecapData(
          playerName: playerName,
          username: username,
          role: playerRole ?? 'Member',
          level: currentLevel,
          avatar: avatar,
          totalMatches: 0,
          wins: 0,
          losses: 0,
          winRate: '0%',
          totalHours: '0 Jam',
          streak: '0 Match',
          hasMatches: false,
          recentMatches: [],
          headToHead: [],
        );
      }

      final Map<int, String> mySidePerMatch = {};
      final Set<int> matchIdSet = {};
      for (final p in partsList) {
        final mId = _toNullableInt(p['match_id']);
        if (mId != null) {
          matchIdSet.add(mId);
          mySidePerMatch[mId] = (p['side']?.toString()) ?? 'Team A';
        }
      }

      // 3. Ambil data detail pertandingan dari tb_match
      final matchesList = await _supabase
          .from('tb_match')
          .select('''
            match_id,
            drawing_id,
            status_match,
            winner_team,
            hasil_pertandingan,
            updated_at,
            tb_score (*),
            tb_match_participant (
              player_id,
              side,
              tb_player (player_id, nama, level, foto)
            ),
            tb_drawing (
              session_id,
              tb_session (
                session_id,
                nama_session,
                status_session,
                datetime,
                tb_sport (nama_sport),
                tb_venue (nama_venue)
              )
            )
          ''')
          .inFilter('match_id', matchIdSet.toList())
          .order('match_id', ascending: true);

      int totalWins = 0;
      int totalLosses = 0;
      final Map<String, Map<String, dynamic>> headToHeadMap = {};
      final List<Map<String, dynamic>> rawCompletedList = [];

      for (final rawMatch in matchesList as List<dynamic>) {
        final match = Map<String, dynamic>.from(rawMatch as Map);
        final matchId = _toInt(match['match_id']);

        // Filter: Hanya match yang selesai dan berada pada sesi mabar yang berstatus selesai (finished/completed/selesai)
        final drawing = match['tb_drawing'] as Map<String, dynamic>?;
        final session = drawing?['tb_session'] as Map<String, dynamic>?;

        final sessionStatus = (session?['status_session']?.toString())?.toLowerCase() ?? '';
        final isSessionFinished = sessionStatus.contains('finish') ||
            sessionStatus.contains('complete') ||
            sessionStatus.contains('selesai');
        if (!isSessionFinished) continue;

        final statusMatch = (match['status_match']?.toString())?.toLowerCase() ?? '';
        final isMatchFinished = statusMatch.contains('complete') ||
            statusMatch.contains('finish') ||
            statusMatch.contains('final') ||
            statusMatch.contains('selesai');
        if (!isMatchFinished) continue;

        final mySideStr = mySidePerMatch[matchId];
        final mySide = parseTeamSide(mySideStr) ?? 'A';
        final isSideA = mySide == 'A';

        // Evaluasi pemenang persis seperti PlayerController di web
        final winnerSide = parseTeamSide(match['winner_team']?.toString());
        bool isWinner = false;
        bool isDraw = false;

        if (winnerSide != null) {
          isWinner = (isSideA && winnerSide == 'A') || (!isSideA && winnerSide == 'B');
        } else {
          final scoresList = match['tb_score'] as List<dynamic>? ?? [];
          int scoreA = 0;
          int scoreB = 0;
          for (final s in scoresList) {
            final sMap = s as Map<String, dynamic>;
            scoreA += _toInt(sMap['game_score_a']) + _toInt(sMap['set_score_a']);
            scoreB += _toInt(sMap['game_score_b']) + _toInt(sMap['set_score_b']);
          }
          if (scoreA > scoreB) {
            isWinner = isSideA;
          } else if (scoreB > scoreA) {
            isWinner = !isSideA;
          } else {
            isDraw = true;
          }
        }

        if (isWinner) {
          totalWins++;
        } else if (!isDraw) {
          totalLosses++;
        }

        // Cari partner dan lawan bermain
        String partnerName = 'Solo';
        final List<String> opponents = [];

        final allParts = match['tb_match_participant'] as List<dynamic>? ?? [];
        for (final other in allParts) {
          final oMap = other as Map<String, dynamic>;
          final oPlayerId = _toNullableInt(oMap['player_id']);
          if (oPlayerId != null && allPlayerIds.contains(oPlayerId)) continue; // Diri sendiri

          final oSide = parseTeamSide(oMap['side']?.toString()) ?? 'B';
          final oSideA = oSide == 'A';

          final oPlayerObj = oMap['tb_player'] as Map<String, dynamic>?;
          final oName = (oPlayerObj?['nama']?.toString()) ?? 'Pemain';

          if (oSideA == isSideA) {
            partnerName = oName;
          } else {
            opponents.add(oName);
            if (!headToHeadMap.containsKey(oName)) {
              headToHeadMap[oName] = {'opponent': oName, 'win': 0, 'lose': 0, 'played': 0};
            }
            headToHeadMap[oName]!['played'] = _toInt(headToHeadMap[oName]!['played']) + 1;
            if (isWinner) {
              headToHeadMap[oName]!['win'] = _toInt(headToHeadMap[oName]!['win']) + 1;
            } else if (!isDraw) {
              headToHeadMap[oName]!['lose'] = _toInt(headToHeadMap[oName]!['lose']) + 1;
            }
          }
        }

        // Info sesi & cabang olahraga
        final sportMap = session?['tb_sport'] as Map<String, dynamic>?;
        final venueMap = session?['tb_venue'] as Map<String, dynamic>?;

        final sportName = (sportMap?['nama_sport']?.toString()) ?? 'Padel';
        final venueName = (venueMap?['nama_venue']?.toString()) ?? 'Arena Olahraga';

        DateTime? dt;
        if (match['updated_at'] != null) {
          dt = DateTime.tryParse(match['updated_at'].toString());
        } else if (session?['datetime'] != null) {
          dt = DateTime.tryParse(session!['datetime'].toString());
        }
        final matchDate = dt != null ? formatDate(dt) : 'Hari Ini';
        final timestamp = dt != null ? dt.millisecondsSinceEpoch : 0;

        // Hitung format skor (persis web: sum game_score atau hasil_pertandingan)
        String scoreDisplay = (match['hasil_pertandingan']?.toString()) ?? '';
        final scoresList = match['tb_score'] as List<dynamic>? ?? [];
        if (scoresList.isNotEmpty) {
          int sumA = 0;
          int sumB = 0;
          for (final s in scoresList) {
            final sMap = s as Map<String, dynamic>;
            sumA += _toInt(sMap['game_score_a']);
            sumB += _toInt(sMap['game_score_b']);
          }
          scoreDisplay = isSideA ? '$sumA - $sumB' : '$sumB - $sumA';
        }
        if (scoreDisplay.isEmpty) scoreDisplay = 'Set Selesai';

        rawCompletedList.add({
          'sport': sportName,
          'venue': venueName,
          'result': isWinner ? 'WIN' : (isDraw ? 'DRAW' : 'LOSE'),
          'score': scoreDisplay,
          'partner': partnerName,
          'opponents': opponents.isNotEmpty ? opponents : ['Lawan'],
          'matchDate': matchDate,
          'timestamp': timestamp,
          'isWinner': isWinner,
          'isDraw': isDraw,
        });
      }

      // Hitung streak secara kronologis (dari pertandingan terlama ke terbaru)
      rawCompletedList.sort((a, b) => (a['timestamp'] as int).compareTo(b['timestamp'] as int));
      int currentStreak = 0;
      for (final item in rawCompletedList) {
        if (item['isWinner'] == true) {
          currentStreak++;
        } else {
          currentStreak = 0;
        }
      }

      // Urutkan riwayat pertandingan dari yang paling baru (descending)
      rawCompletedList.sort((a, b) => (b['timestamp'] as int).compareTo(a['timestamp'] as int));
      final List<PlayerMatchHistoryItem> completedMatches = rawCompletedList.map((m) {
        return PlayerMatchHistoryItem(
          sport: m['sport'] as String,
          venue: m['venue'] as String,
          result: m['result'] as String,
          score: m['score'] as String,
          partner: m['partner'] as String,
          opponents: List<String>.from(m['opponents'] as List),
          matchDate: m['matchDate'] as String,
          timestamp: m['timestamp'] as int,
        );
      }).toList();

      final totalMatches = completedMatches.length;
      final winRatePercent = totalMatches > 0 ? ((totalWins / totalMatches) * 100).round() : 0;
      final totalHours = totalMatches > 0
          ? '${(totalMatches * 0.5).toStringAsFixed(totalMatches % 2 == 0 ? 0 : 1)} Jam'
          : '0 Jam';
      final streakDisplay = currentStreak > 0
          ? '🔥 $currentStreak Win Streak'
          : (totalMatches > 0 ? '0 Win Streak' : '0 Match');

      // Head to Head (diurutkan berdasarkan jumlah main terbanyak, top 5)
      final List<HeadToHeadItem> headToHeadList = [];
      for (final entry in headToHeadMap.values) {
        final played = _toInt(entry['played']);
        final win = _toInt(entry['win']);
        final lose = _toInt(entry['lose']);
        final winRate = played > 0 ? (win / played) * 100 : 0.0;

        headToHeadList.add(
          HeadToHeadItem(
            opponent: (entry['opponent']?.toString()) ?? 'Pemain',
            win: win,
            lose: lose,
            played: played,
            winRatePercent: winRate,
          ),
        );
      }
      headToHeadList.sort((a, b) => b.played.compareTo(a.played));

      return PlayerCareerRecapData(
        playerName: playerName,
        username: username,
        role: userRole ?? 'Member',
        level: currentLevel,
        avatar: avatar,
        totalMatches: totalMatches,
        wins: totalWins,
        losses: totalLosses,
        winRate: '$winRatePercent%',
        totalHours: totalHours,
        streak: streakDisplay,
        hasMatches: totalMatches > 0,
        recentMatches: completedMatches.take(10).toList(),
        headToHead: headToHeadList.take(5).toList(),
      );
    } catch (e) {
      throw Exception('Gagal memuat rekap karier pemain: $e');
    }
  }

  /// Mengambil data rekap pertandingan lengkap untuk sesi tertentu (seperti web /scoring/recap/{sessionId})
  Future<SessionMatchRecapData> getSessionMatchRecap(
    int sessionId, {
    int? currentUserId,
  }) async {
    try {
      // 1. Ambil Data Session
      final sessionRes = await _supabase
          .from('tb_session')
          .select('''
            session_id,
            host_user_id,
            nama_session,
            sport_id,
            venue_id,
            datetime,
            waktu_session,
            jumlah_pemain,
            status_session,
            scoring_system,
            tb_sport (nama_sport),
            tb_venue (nama_venue),
            tb_session_court (tb_court (nama_court)),
            tb_session_player (tb_player (player_id, user_id, nama, gender, level, foto, tb_user (foto)))
          ''')
          .eq('session_id', sessionId)
          .maybeSingle();

      if (sessionRes == null) {
        throw Exception('Sesi mabar tidak ditemukan di database.');
      }

      final sMap = Map<String, dynamic>.from(sessionRes);
      final sessionName = (sMap['nama_session']?.toString()) ?? 'Sesi Mabar';
      final sportMap = sMap['tb_sport'] as Map<String, dynamic>?;
      final sportName = (sportMap?['nama_sport']?.toString()) ?? 'Padel';
      final venueMap = sMap['tb_venue'] as Map<String, dynamic>?;
      final venueName = (venueMap?['nama_venue']?.toString()) ?? 'Arena Lapangan';
      final scoringSystem = (sMap['scoring_system']?.toString()) ?? 'Total of 3';

      String? courtName;
      final courtsList = sMap['tb_session_court'] as List<dynamic>?;
      if (courtsList != null && courtsList.isNotEmpty) {
        final firstCourt = courtsList.first as Map<String, dynamic>?;
        final cObj = firstCourt?['tb_court'] as Map<String, dynamic>?;
        if (cObj != null && cObj['nama_court'] != null) {
          courtName = cObj['nama_court'].toString();
        }
      }

      // 2. Ambil Pemain Terdaftar
      final registeredPlayers = <Map<String, dynamic>>[];
      final rawPlayersList = sMap['tb_session_player'] as List<dynamic>? ?? [];
      for (final rp in rawPlayersList) {
        final rpMap = rp as Map<String, dynamic>;
        final pObj = rpMap['tb_player'] as Map<String, dynamic>?;
        if (pObj != null) {
          final pCopy = Map<String, dynamic>.from(pObj);
          String? f = pCopy['foto']?.toString().trim();
          if ((f == null || f.isEmpty) && pCopy['tb_user'] is Map) {
            f = pCopy['tb_user']?['foto']?.toString().trim();
          }
          pCopy['foto'] = (f != null && f.isNotEmpty) ? f : null;
          registeredPlayers.add(pCopy);
        }
      }

      // 3. Ambil court count sesi untuk kalkulasi nomor ronde
      int sessionCourtCount = 1;
      try {
        final scRes = await _supabase
            .from('tb_session_court')
            .select('court_id')
            .eq('session_id', sessionId);
        if ((scRes as List<dynamic>).isNotEmpty) {
          sessionCourtCount = math.max(1, scRes.length);
        }
      } catch (_) {}

      // Ambil data Drawing aktif dari tb_drawing yang memiliki match
      final drawingsRes = await _supabase
          .from('tb_drawing')
          .select('drawing_id')
          .eq('session_id', sessionId)
          .order('drawing_id', ascending: false);

      int? activeDrawingId;
      if (drawingsRes.isNotEmpty) {
        for (final d in drawingsRes) {
          final dId = _toNullableInt(d['drawing_id']);
          if (dId == null) continue;
          final mCheck = await _supabase
              .from('tb_match')
              .select('match_id')
              .eq('drawing_id', dId)
              .limit(1);
          if ((mCheck as List).isNotEmpty) {
            activeDrawingId = dId;
            break;
          }
        }
        activeDrawingId ??= _toNullableInt(drawingsRes.first['drawing_id']);
      }

      List<dynamic> rawMatches = [];
      if (activeDrawingId != null) {
        final mRes = await _supabase
            .from('tb_match')
            .select('''
              *,
              tb_court (nama_court),
              tb_score (*),
              tb_match_participant (
                player_id,
                side,
                tb_player (player_id, user_id, nama, level, foto, gender, tb_user (foto))
              )
            ''')
            .eq('drawing_id', activeDrawingId)
            .order('nomor_match', ascending: true);
        rawMatches = mRes as List<dynamic>;
      } else {
        rawMatches = [];
      }

      // Deteksi format sistem scoring (Sets vs Games / Points)
      final lowerScoring = scoringSystem.toLowerCase();
      final isSets = lowerScoring.contains('set') || lowerScoring.contains('total of');

      // 4. Strukturisasi Ronde & Perhitungan Skor
      final Map<int, List<SessionMatchItem>> roundMatchesMap = {};
      final Map<int, Map<String, dynamic>> playerStatsMap = {};

      // Inisialisasi statistik pemain terdaftar
      for (final p in registeredPlayers) {
        final pId = _toInt(p['player_id']);
        final pName = (p['nama'] ?? p['nama_player'])?.toString() ?? 'Pemain';
        playerStatsMap[pId] = {
          'playerId': pId,
          'nama': pName,
          'level': (p['level']?.toString()) ?? 'Intermediate',
          'foto': p['foto']?.toString(),
          'gender': (p['gender']?.toString()) ?? 'Male',
          'userId': _toNullableInt(p['user_id']),
          'matchesPlayed': 0,
          'matchesWon': 0,
          'matchesLost': 0,
          'setsWon': 0,
          'setsLost': 0,
          'gamesWon': 0,
          'gamesLost': 0,
          'pointsFor': 0,
          'pointsAgainst': 0,
          'durationMinutes': 0,
        };
      }

      int matchCounter = 0;
      for (final rawM in rawMatches) {
        final mMap = Map<String, dynamic>.from(rawM as Map);
        matchCounter++;
        final matchId = _toInt(mMap['match_id'], matchCounter);
        final matchNo = _toInt(mMap['nomor_match'], matchCounter);
        final roundNum = _toInt(mMap['round_number'], ((matchNo - 1) ~/ sessionCourtCount) + 1);

        final courtObj = mMap['tb_court'] as Map<String, dynamic>?;
        final cName = (courtObj?['nama_court']?.toString()) ?? courtName ?? 'Court 1';

        final participants = mMap['tb_match_participant'] as List<dynamic>? ?? [];
        final sideANames = <String>[];
        final sideBNames = <String>[];
        final sideAPlayerIds = <int>[];
        final sideBPlayerIds = <int>[];

        for (final part in participants) {
          final partMap = part as Map<String, dynamic>;
          final pId = _toNullableInt(partMap['player_id']);
          final pObj = partMap['tb_player'] as Map<String, dynamic>?;
          final pName = (pObj?['nama'] ?? pObj?['nama_player'])?.toString() ?? 'Pemain';
          final sideParsed = parseTeamSide(partMap['side']?.toString());
          final isB = sideParsed == 'B';

          String? pFoto = pObj?['foto']?.toString().trim();
          if ((pFoto == null || pFoto.isEmpty) && pObj?['tb_user'] is Map) {
            pFoto = pObj?['tb_user']?['foto']?.toString().trim();
          }

          if (pId != null && !playerStatsMap.containsKey(pId)) {
            playerStatsMap[pId] = {
              'playerId': pId,
              'nama': pName,
              'level': (pObj?['level']?.toString()) ?? 'Intermediate',
              'foto': (pFoto != null && pFoto.isNotEmpty) ? pFoto : null,
              'gender': (pObj?['gender']?.toString()) ?? 'Male',
              'userId': _toNullableInt(pObj?['user_id']),
              'matchesPlayed': 0,
              'matchesWon': 0,
              'matchesLost': 0,
              'setsWon': 0,
              'setsLost': 0,
              'gamesWon': 0,
              'gamesLost': 0,
              'pointsFor': 0,
              'pointsAgainst': 0,
              'durationMinutes': 0,
            };
          }

          if (isB) {
            sideBNames.add(pName);
            if (pId != null) sideBPlayerIds.add(pId);
          } else {
            sideANames.add(pName);
            if (pId != null) sideAPlayerIds.add(pId);
          }
        }

        // Skor pertandingan
        final scores = mMap['tb_score'] as List<dynamic>? ?? [];
        final mStatus = (mMap['status_match']?.toString() ?? '').toLowerCase();
        final bool isMatchCompleted = mStatus == 'completed' ||
            mStatus == 'finished' ||
            mStatus == 'final' ||
            (scores.isNotEmpty &&
                scores.any((s) {
                  final st = (s['status_score'] ?? '').toString().toLowerCase();
                  return st == 'completed' || st == 'final';
                }));

        int mSetsA = 0;
        int mSetsB = 0;
        int mGamesA = 0;
        int mGamesB = 0;
        final List<String> setPills = [];

        if (scores.isNotEmpty) {
          int shSetsA = 0;
          int shSetsB = 0;
          int shGamesA = 0;
          int shGamesB = 0;

          for (int i = 0; i < scores.length; i++) {
            final sc = scores[i] as Map<String, dynamic>;
            final setNum = _toInt(sc['set_number'], i + 1);
            final scoreSideA = _toInt(sc['score_side_a']);
            final scoreSideB = _toInt(sc['score_side_b']);
            final gameA = _toInt(sc['game_score_a']);
            final gameB = _toInt(sc['game_score_b']);
            final setScoreA = _toInt(sc['set_score_a']);
            final setScoreB = _toInt(sc['set_score_b']);

            mSetsA = math.max(mSetsA, setScoreA);
            mSetsB = math.max(mSetsB, setScoreB);

            final ga = gameA > 0 ? gameA : scoreSideA;
            final gb = gameB > 0 ? gameB : scoreSideB;
            shGamesA += ga;
            shGamesB += gb;
            if (ga > gb) {
              shSetsA++;
            } else if (gb > ga) {
              shSetsB++;
            }

            setPills.add('Set $setNum: $ga-$gb');
          }

          if (mSetsA == 0 && mSetsB == 0) {
            mSetsA = shSetsA;
            mSetsB = shSetsB;
          }
          mGamesA = shGamesA;
          mGamesB = shGamesB;
        } else if (mMap['hasil_pertandingan'] != null && mMap['hasil_pertandingan'].toString().isNotEmpty) {
          setPills.add(mMap['hasil_pertandingan'].toString());
        }

        // Hitung durasi match
        int matchDuration = 0;
        if (mMap['waktu_mulai'] != null && mMap['waktu_selesai'] != null) {
          try {
            final sTime = DateTime.parse(mMap['waktu_mulai'].toString());
            final eTime = DateTime.parse(mMap['waktu_selesai'].toString());
            if (eTime.isAfter(sTime)) {
              matchDuration = eTime.difference(sTime).inMinutes;
            }
          } catch (_) {}
        }

        // Tentukan pemenang & poin akumulasi sesuai ScoringService web
        String? winnerSide = parseTeamSide(mMap['winner_team']?.toString());

        int ptsForA = 0;
        int ptsForB = 0;

        if (isSets) {
          if (mSetsA == 0 && mSetsB == 0 && (mGamesA > 0 || mGamesB > 0)) {
            mSetsA = mGamesA > mGamesB ? 1 : 0;
            mSetsB = mGamesB > mGamesA ? 1 : 0;
          }
          if (winnerSide == null) {
            if (mSetsA > mSetsB) {
              winnerSide = 'A';
            } else if (mSetsB > mSetsA) {
              winnerSide = 'B';
            } else if (mGamesA > mGamesB) {
              winnerSide = 'A';
            } else if (mGamesB > mGamesA) {
              winnerSide = 'B';
            }
          }
          ptsForA = mGamesA > 0 ? mGamesA : mSetsA;
          ptsForB = mGamesB > 0 ? mGamesB : mSetsB;
        } else {
          if (winnerSide == null) {
            if (mGamesA > mGamesB) {
              winnerSide = 'A';
            } else if (mGamesB > mGamesA) {
              winnerSide = 'B';
            }
          }
          ptsForA = mGamesA;
          ptsForB = mGamesB;
        }

        final isSideAWinner = winnerSide == 'A';
        final isSideBWinner = winnerSide == 'B';
        final isDraw = winnerSide == null;

        // Akumulasi statistik pemain Tim A & B (Hanya untuk match yang sudah selesai / memiliki skor final)
        if (isMatchCompleted) {
          for (final pId in sideAPlayerIds) {
            if (playerStatsMap.containsKey(pId)) {
              final st = playerStatsMap[pId]!;
              st['matchesPlayed'] = _toInt(st['matchesPlayed']) + 1;
              st['setsWon'] = _toInt(st['setsWon']) + mSetsA;
              st['setsLost'] = _toInt(st['setsLost']) + mSetsB;
              st['gamesWon'] = _toInt(st['gamesWon']) + mGamesA;
              st['gamesLost'] = _toInt(st['gamesLost']) + mGamesB;
              st['pointsFor'] = _toInt(st['pointsFor']) + ptsForA;
              st['pointsAgainst'] = _toInt(st['pointsAgainst']) + ptsForB;
              st['durationMinutes'] = _toInt(st['durationMinutes']) + matchDuration;
              if (isSideAWinner) {
                st['matchesWon'] = _toInt(st['matchesWon']) + 1;
              } else if (isSideBWinner) {
                st['matchesLost'] = _toInt(st['matchesLost']) + 1;
              }
            }
          }

          for (final pId in sideBPlayerIds) {
            if (playerStatsMap.containsKey(pId)) {
              final st = playerStatsMap[pId]!;
              st['matchesPlayed'] = _toInt(st['matchesPlayed']) + 1;
              st['setsWon'] = _toInt(st['setsWon']) + mSetsB;
              st['setsLost'] = _toInt(st['setsLost']) + mSetsA;
              st['gamesWon'] = _toInt(st['gamesWon']) + mGamesB;
              st['gamesLost'] = _toInt(st['gamesLost']) + mGamesA;
              st['pointsFor'] = _toInt(st['pointsFor']) + ptsForB;
              st['pointsAgainst'] = _toInt(st['pointsAgainst']) + ptsForA;
              st['durationMinutes'] = _toInt(st['durationMinutes']) + matchDuration;
              if (isSideBWinner) {
                st['matchesWon'] = _toInt(st['matchesWon']) + 1;
              } else if (isSideAWinner) {
                st['matchesLost'] = _toInt(st['matchesLost']) + 1;
              }
            }
          }
        }

        final scoreDisplayA = isSets ? (mGamesA > 0 ? mGamesA : mSetsA) : mGamesA;
        final scoreDisplayB = isSets ? (mGamesB > 0 ? mGamesB : mSetsB) : mGamesB;
        final setDetails = setPills.isNotEmpty ? 'Rincian: ${setPills.join(', ')}' : 'Set 1: $scoreDisplayA-$scoreDisplayB';

        final matchItem = SessionMatchItem(
          matchId: matchId,
          roundNumber: roundNum,
          courtName: cName,
          sideANames: sideANames.isNotEmpty ? sideANames : ['Side A'],
          sideBNames: sideBNames.isNotEmpty ? sideBNames : ['Side B'],
          scoreA: scoreDisplayA,
          scoreB: scoreDisplayB,
          setDetails: setDetails,
          isSideAWinner: isSideAWinner,
          isSideBWinner: isSideBWinner,
          isDraw: isDraw,
        );

        roundMatchesMap.putIfAbsent(roundNum, () => []).add(matchItem);
      }

      // 5. Susun Ronde
      final List<SessionRoundRecapItem> rounds = [];
      final sortedRoundKeys = roundMatchesMap.keys.toList()..sort();
      for (final k in sortedRoundKeys) {
        rounds.add(
          SessionRoundRecapItem(
            roundNumber: k,
            matches: roundMatchesMap[k]!,
          ),
        );
      }

      // 6. Susun Klasemen / Standings (Menggunakan Urutan Sort ScoringService Web)
      final rawStandings = playerStatsMap.values.toList();
      rawStandings.sort((a, b) {
        final ptsForA = _toInt(a['pointsFor']);
        final ptsForB = _toInt(b['pointsFor']);
        if (ptsForB != ptsForA) return ptsForB.compareTo(ptsForA);

        final ptDiffA = ptsForA - _toInt(a['pointsAgainst']);
        final ptDiffB = ptsForB - _toInt(b['pointsAgainst']);
        if (ptDiffB != ptDiffA) return ptDiffB.compareTo(ptDiffA);

        final winsA = _toInt(a['matchesWon']);
        final winsB = _toInt(b['matchesWon']);
        if (winsB != winsA) return winsB.compareTo(winsA);

        final gWonA = _toInt(a['gamesWon']);
        final gWonB = _toInt(b['gamesWon']);
        if (gWonB != gWonA) return gWonB.compareTo(gWonA);

        final gDiffA = gWonA - _toInt(a['gamesLost']);
        final gDiffB = gWonB - _toInt(b['gamesLost']);
        if (gDiffB != gDiffA) return gDiffB.compareTo(gDiffA);

        return (a['nama']?.toString() ?? '').toLowerCase().compareTo((b['nama']?.toString() ?? '').toLowerCase());
      });

      final List<SessionPlayerStanding> standings = [];
      for (int i = 0; i < rawStandings.length; i++) {
        final item = rawStandings[i];
        final gWon = _toInt(item['gamesWon']);
        final gLost = _toInt(item['gamesLost']);
        final sWon = _toInt(item['setsWon']);
        final sLost = _toInt(item['setsLost']);
        final pFor = _toInt(item['pointsFor']);
        final pAgainst = _toInt(item['pointsAgainst']);

        standings.add(
          SessionPlayerStanding(
            rank: i + 1,
            playerId: _toInt(item['playerId']),
            nama: item['nama'].toString(),
            level: item['level'].toString(),
            foto: item['foto']?.toString(),
            gender: item['gender']?.toString() ?? 'Male',
            matchesPlayed: _toInt(item['matchesPlayed']),
            matchesWon: _toInt(item['matchesWon']),
            matchesLost: _toInt(item['matchesLost']),
            setsWon: sWon,
            setsLost: sLost,
            gamesWon: gWon,
            gamesLost: gLost,
            pointsFor: pFor,
            pointsAgainst: pAgainst,
            pointDiff: pFor - pAgainst,
            gameDiff: gWon - gLost,
            setDiff: sWon - sLost,
            scoreWon: pFor,
            gamesDiff: gWon - gLost,
          ),
        );
      }

      // 7. Ambil Data Kudos dari tb_kudos
      final Map<int, Map<String, int>> kudosMap = {};
      final Map<int, Set<String>> userGivenKudos = {};

      try {
        final kudosRes = await _supabase
            .from('tb_kudos')
            .select('*')
            .eq('session_id', sessionId);
        final List<dynamic> rawKudos = kudosRes as List<dynamic>;

        for (final k in rawKudos) {
          final kMap = k as Map<String, dynamic>;
          final recId = _toNullableInt(kMap['recipient_player_id']);
          final badge = kMap['badge']?.toString() ?? '';
          final giverId = _toNullableInt(kMap['giver_user_id']);

          if (recId != null && badge.isNotEmpty) {
            kudosMap.putIfAbsent(recId, () => {});
            kudosMap[recId]![badge] = (kudosMap[recId]![badge] ?? 0) + 1;

            if (currentUserId != null && giverId == currentUserId) {
              userGivenKudos.putIfAbsent(recId, () => {});
              userGivenKudos[recId]!.add(badge);
            }
          }
        }
      } catch (_) {}

      // 8. Hitung Statistik Pribadi untuk User Saat Ini (Sesuai buildPlayerRecap web)
      SessionPersonalStat? myStats;
      Map<String, dynamic>? selectedPlayerEntry;

      if (currentUserId != null) {
        for (final item in rawStandings) {
          if (_toNullableInt(item['userId']) == currentUserId) {
            selectedPlayerEntry = item;
            break;
          }
        }
      }
      selectedPlayerEntry ??= rawStandings.isNotEmpty ? rawStandings.first : null;

      if (selectedPlayerEntry != null) {
        final mPlayed = _toInt(selectedPlayerEntry['matchesPlayed']);
        final mWon = _toInt(selectedPlayerEntry['matchesWon']);
        final mLost = _toInt(selectedPlayerEntry['matchesLost']);
        final winRate = mPlayed > 0 ? ((mWon / mPlayed) * 100).round() : 0;
        final dur = _toInt(selectedPlayerEntry['durationMinutes']);

        myStats = SessionPersonalStat(
          nama: selectedPlayerEntry['nama'].toString(),
          level: selectedPlayerEntry['level'].toString(),
          foto: selectedPlayerEntry['foto']?.toString(),
          sportName: sportName,
          totalPoints: _toInt(selectedPlayerEntry['pointsFor']),
          winRatePercent: winRate,
          durationPlayed: dur > 0 ? '${dur}m' : '0m',
          wins: mWon,
          losses: mLost,
        );
      }

      return SessionMatchRecapData(
        sessionId: sessionId,
        sessionName: sessionName,
        venueName: venueName,
        courtName: courtName,
        sportName: sportName,
        scoringSystem: scoringSystem,
        isSets: isSets,
        totalRounds: rounds.isNotEmpty ? rounds.length : 1,
        totalPlayers: standings.isNotEmpty ? standings.length : registeredPlayers.length,
        rounds: rounds,
        standings: standings,
        myStats: myStats,
        kudosMap: kudosMap,
        userGivenKudos: userGivenKudos,
      );
    } catch (e) {
      throw Exception('Gagal memuat rekap pertandingan sesi: $e');
    }
  }

  /// Toggle Kudos ke database tb_kudos
  Future<bool> toggleKudos({
    required int sessionId,
    required int? giverUserId,
    required int? recipientPlayerId,
    required String recipientName,
    required String badge,
  }) async {
    try {
      var query = _supabase
          .from('tb_kudos')
          .select('kudos_id')
          .eq('session_id', sessionId)
          .eq('recipient_name', recipientName)
          .eq('badge', badge);

      if (giverUserId != null) {
        query = query.eq('giver_user_id', giverUserId);
      }

      final existing = await query;
      if (existing.isNotEmpty) {
        final kId = existing.first['kudos_id'];
        await _supabase.from('tb_kudos').delete().eq('kudos_id', kId);
        return false;
      } else {
        await _supabase.from('tb_kudos').insert({
          'session_id': sessionId,
          'giver_user_id': giverUserId,
          'recipient_player_id': recipientPlayerId,
          'recipient_name': recipientName,
          'badge': badge,
        });
        return true;
      }
    } catch (_) {
      return true;
    }
  }

  static String formatDate(DateTime dt) {
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
      'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    return '${dt.day} ${months[dt.month - 1]} ${dt.year}';
  }
}

