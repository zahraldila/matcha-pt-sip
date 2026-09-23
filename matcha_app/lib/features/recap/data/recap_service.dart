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

  /// Mengambil data performa karier pemain dari database
  Future<PlayerCareerRecapData> getPlayerCareerRecap(
    int userId, {
    int? playerId,
    String? userNama,
    String? userFoto,
    String? userRole,
    String? userLevel,
  }) async {
    try {
      final playerName = userNama ?? 'Pemain Matcha';
      final username = '@${playerName.toLowerCase().replaceAll(RegExp(r'[^a-z0-9_]'), '_')}';
      final avatar = (userFoto != null && userFoto.isNotEmpty)
          ? userFoto
          : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80';

      int? targetPlayerId = playerId;
      String currentLevel = userLevel ?? 'Intermediate';

      if (targetPlayerId == null) {
        final pRes = await _supabase
            .from('tb_player')
            .select('player_id, nama, level, foto')
            .eq('user_id', userId);
        if (pRes.isNotEmpty) {
          final first = pRes.first;
          targetPlayerId = _toNullableInt(first['player_id']);
          if (first['level'] != null) currentLevel = first['level'].toString();
        }
      }

      if (targetPlayerId == null) {
        return PlayerCareerRecapData(
          playerName: playerName,
          username: username,
          role: userRole ?? 'Member',
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

      final partsResponse = await _supabase
          .from('tb_match_participant')
          .select('''
            *,
            tb_match (
              match_id,
              status_match,
              winner_team,
              hasil_pertandingan,
              updated_at,
              tb_drawing (
                tb_session (
                  nama_session,
                  datetime,
                  tb_sport (nama_sport),
                  tb_venue (nama_venue)
                )
              ),
              tb_score (
                *
              ),
              tb_match_participant (
                player_id,
                side,
                tb_player (
                  player_id,
                  nama,
                  level,
                  foto
                )
              )
            )
          ''')
          .eq('player_id', targetPlayerId);

      final List<dynamic> rawParts = partsResponse as List<dynamic>;

      int totalWins = 0;
      int totalLosses = 0;
      int currentStreak = 0;
      final Map<String, Map<String, dynamic>> headToHeadMap = {};
      final List<PlayerMatchHistoryItem> completedMatches = [];

      for (final part in rawParts) {
        final pMap = Map<String, dynamic>.from(part as Map);
        final match = pMap['tb_match'] as Map<String, dynamic>?;
        if (match == null) continue;

        final statusMatch = (match['status_match']?.toString())?.toLowerCase() ?? '';
        if (statusMatch != 'completed' && statusMatch != 'finished') continue;

        final mySide = (pMap['side']?.toString()) ?? 'Team A';
        final isSideA = mySide.toLowerCase().contains('a');

        final winnerTeam = (match['winner_team']?.toString()) ?? '';
        bool isWinner = false;
        bool isDraw = false;

        if (winnerTeam.isNotEmpty) {
          final winnerSideA = winnerTeam.toLowerCase().contains('a');
          isWinner = (isSideA && winnerSideA) || (!isSideA && !winnerSideA);
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
          currentStreak++;
        } else if (!isDraw) {
          totalLosses++;
          currentStreak = 0;
        }

        String partnerName = 'Solo';
        final List<String> opponents = [];

        final allParts = match['tb_match_participant'] as List<dynamic>? ?? [];
        for (final other in allParts) {
          final oMap = other as Map<String, dynamic>;
          final oPlayerId = _toNullableInt(oMap['player_id']);
          if (oPlayerId == targetPlayerId) continue;

          final oSide = (oMap['side']?.toString()) ?? '';
          final oSideA = oSide.toLowerCase().contains('a');

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

        final drawing = match['tb_drawing'] as Map<String, dynamic>?;
        final session = drawing?['tb_session'] as Map<String, dynamic>?;
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

        String scoreDisplay = (match['hasil_pertandingan']?.toString()) ?? 'Set Selesai';
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

        completedMatches.add(
          PlayerMatchHistoryItem(
            sport: sportName,
            venue: venueName,
            result: isWinner ? 'WIN' : (isDraw ? 'DRAW' : 'LOSE'),
            score: scoreDisplay,
            partner: partnerName,
            opponents: opponents.isNotEmpty ? opponents : ['Lawan'],
            matchDate: matchDate,
            timestamp: timestamp,
          ),
        );
      }

      completedMatches.sort((a, b) => b.timestamp.compareTo(a.timestamp));
      final totalMatches = completedMatches.length;
      final winRatePercent = totalMatches > 0 ? ((totalWins / totalMatches) * 100).round() : 0;
      final totalHours = totalMatches > 0 ? '${(totalMatches * 0.5).toStringAsFixed(totalMatches % 2 == 0 ? 0 : 1)} Jam' : '0 Jam';
      final streakDisplay = currentStreak > 0 ? '🔥 $currentStreak Win Streak' : (totalMatches > 0 ? '0 Win Streak' : '0 Match');

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

  static String formatDate(DateTime dt) {
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
      'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    return '${dt.day} ${months[dt.month - 1]} ${dt.year}';
  }
}
