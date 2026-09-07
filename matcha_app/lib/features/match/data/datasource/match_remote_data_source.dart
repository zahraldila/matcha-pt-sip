import 'package:supabase_flutter/supabase_flutter.dart';
import '../../domain/models/match_model.dart';
import '../../domain/models/playing_history_model.dart';
import '../../domain/models/score_model.dart';

class MatchRemoteDataSource {
  final SupabaseClient _supabase;

  MatchRemoteDataSource({SupabaseClient? supabaseClient})
    : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Mengambil semua score dari tb_score berdasarkan match_id
  Future<List<ScoreModel>> getScoresByMatchId(int matchId) async {
    try {
      final response = await _supabase
          .from('tb_score')
          .select()
          .eq('match_id', matchId)
          .order('set_number', ascending: true);

      final List<dynamic> data = response as List<dynamic>;
      final List<ScoreModel> scores = [];
      for (final item in data) {
        final map = item as Map<String, dynamic>;
        // Abaikan/skip record legacy yang tidak memiliki set_number (null)
        if (map['set_number'] == null) continue;
        scores.add(ScoreModel.fromJson(map));
      }
      return scores;
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal mengambil data skor: ${e.message}');
      }
      rethrow;
    }
  }

  /// Menyimpan atau mengupdate skor pada tb_score berdasarkan match_id dan set_number
  Future<ScoreModel> saveOrUpdateScore({
    required int matchId,
    required int setNumber,
    required int scoreSideA,
    required int scoreSideB,
  }) async {
    if (scoreSideA < 0 || scoreSideB < 0) {
      throw Exception('Skor tidak boleh bernilai negatif.');
    }

    try {
      // Periksa apakah data skor untuk match dan set tersebut sudah ada
      final existing = await _supabase
          .from('tb_score')
          .select()
          .eq('match_id', matchId)
          .eq('set_number', setNumber)
          .maybeSingle();

      if (existing != null) {
        final updated = await _supabase
            .from('tb_score')
            .update({
              'score_side_a': scoreSideA,
              'score_side_b': scoreSideB,
              'score_value': scoreSideA + scoreSideB,
              'status_score': 'recorded',
              'updated_at': DateTime.now().toIso8601String(),
            })
            .eq('match_id', matchId)
            .eq('set_number', setNumber)
            .select()
            .single();

        return ScoreModel.fromJson(updated);
      } else {
        final now = DateTime.now().toIso8601String();
        final inserted = await _supabase
            .from('tb_score')
            .insert({
              'match_id': matchId,
              'set_number': setNumber,
              'score_side_a': scoreSideA,
              'score_side_b': scoreSideB,
              'score_value': scoreSideA + scoreSideB,
              'status_score': 'recorded',
              'waktu_pencatatan': now,
              'created_at': now,
              'updated_at': now,
            })
            .select()
            .single();

        return ScoreModel.fromJson(inserted);
      }
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal menyimpan skor ke database: ${e.message}');
      }
      rethrow;
    }
  }

  /// Mengambil data match dari tb_match berdasarkan match_id,
  /// dan melengkapi informasi player dari tb_match_participant jika tersedia.
  Future<MatchModel?> getMatchById(int matchId) async {
    try {
      final response = await _supabase
          .from('tb_match')
          .select()
          .eq('match_id', matchId)
          .maybeSingle();

      if (response == null) return null;

      final baseMatch = MatchModel.fromJson(response);

      // Ambil participant aktual dari tb_match_participant untuk melengkapi sideAPlayer & sideBPlayer
      try {
        final participants = await getMatchParticipantsByMatchId(matchId);
        int? sideA1;
        int? sideA2;
        int? sideB1;
        int? sideB2;

        for (final p in participants) {
          final rawPlayerId = p['player_id'];
          final pId = rawPlayerId is int
              ? rawPlayerId
              : int.tryParse(rawPlayerId.toString());
          if (pId == null) continue;

          final side = (p['side'] ?? '').toString().toLowerCase().trim();
          final groupNo = p['group_no'];
          final isSideB = side.contains('b') || groupNo == 2;

          if (isSideB) {
            if (sideB1 == null) {
              sideB1 = pId;
            } else {
              sideB2 ??= pId;
            }
          } else {
            if (sideA1 == null) {
              sideA1 = pId;
            } else {
              sideA2 ??= pId;
            }
          }
        }

        return MatchModel(
          matchId: baseMatch.matchId,
          drawingId: baseMatch.drawingId,
          courtId: baseMatch.courtId,
          roundNumber: baseMatch.roundNumber,
          sideAPlayer1: sideA1 ?? baseMatch.sideAPlayer1,
          sideAPlayer2: sideA2 ?? baseMatch.sideAPlayer2,
          sideBPlayer1: sideB1 ?? baseMatch.sideBPlayer1,
          sideBPlayer2: sideB2 ?? baseMatch.sideBPlayer2,
          statusMatch: baseMatch.statusMatch,
          hasilPertandingan: baseMatch.hasilPertandingan,
          waktuSelesai: baseMatch.waktuSelesai,
        );
      } catch (_) {
        return baseMatch;
      }
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal mengambil data pertandingan: ${e.message}');
      }
      rethrow;
    }
  }

  /// Mengambil seluruh participant pertandingan dari tb_match_participant
  /// berdasarkan match_id dengan kolom yang tersedia: match_id, player_id, side, group_no.
  Future<List<Map<String, dynamic>>> getMatchParticipantsByMatchId(
    int matchId,
  ) async {
    try {
      final response = await _supabase
          .from('tb_match_participant')
          .select('match_id, player_id, side, group_no')
          .eq('match_id', matchId);

      return List<Map<String, dynamic>>.from(response as List);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception(
          'Gagal mengambil participant pertandingan: ${e.message}',
        );
      }
      rethrow;
    }
  }

  /// Mengubah status match pada tb_match
  Future<void> updateMatchStatus({
    required int matchId,
    required String status,
  }) async {
    try {
      await _supabase
          .from('tb_match')
          .update({'status_match': status})
          .eq('match_id', matchId);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal memperbarui status pertandingan: ${e.message}');
      }
      rethrow;
    }
  }

  /// Menyelesaikan pertandingan (Finish Match) pada tb_match
  /// dan mencatat Playing History untuk setiap participant dari tb_match_participant.
  Future<void> finishMatch({
    required int matchId,
    required String hasilPertandingan,
  }) async {
    final now = DateTime.now().toIso8601String();
    try {
      await _supabase
          .from('tb_match')
          .update({
            'status_match': 'Finished',
            'waktu_selesai': now,
            'hasil_pertandingan': hasilPertandingan,
            'updated_at': now,
          })
          .eq('match_id', matchId);

      // Simpan riwayat bermain untuk seluruh participant aktual
      try {
        await recordPlayingHistory(matchId: matchId);
      } catch (_) {
        // Jangan gagalkan finishMatch jika history gagal dicatat
      }
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal menyelesaikan pertandingan: ${e.message}');
      }
      rethrow;
    }
  }

  /// Menyimpan statistik setiap player ke tb_playing_history
  Future<void> savePlayingHistories(List<PlayingHistoryModel> histories) async {
    try {
      for (final history in histories) {
        final existing = await _supabase
            .from('tb_playing_history')
            .select()
            .eq('player_id', history.playerId)
            .eq('match_id', history.matchId)
            .maybeSingle();

        final data = history.toJson();
        // Jangan sertakan history_id agar primary key dikelola otomatis oleh database
        data.remove('history_id');

        if (existing != null) {
          await _supabase
              .from('tb_playing_history')
              .update(data)
              .eq('player_id', history.playerId)
              .eq('match_id', history.matchId);
        } else {
          await _supabase.from('tb_playing_history').insert(data);
        }
      }
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception(
          'Gagal menyimpan riwayat bermain ke database: ${e.message}',
        );
      }
      rethrow;
    }
  }

  /// Helper khusus untuk membentuk dan mencatat Playing History ke tb_playing_history
  /// dengan mengambil data participant dari tb_match_participant (player_id, side, group_no),
  /// skor dari tb_score, serta informasi match (session_id, court_id) dari tb_match.
  Future<List<PlayingHistoryModel>> recordPlayingHistory({
    required int matchId,
  }) async {
    try {
      // 1. Ambil participant aktual dari tb_match_participant
      final participants = await getMatchParticipantsByMatchId(matchId);
      if (participants.isEmpty) {
        return [];
      }

      // 2. Ambil session_id dan court_id dari tb_match jika tersedia
      int? sessionId;
      int? courtId;
      try {
        final matchRes = await _supabase
            .from('tb_match')
            .select('session_id, court_id')
            .eq('match_id', matchId)
            .maybeSingle();
        if (matchRes != null) {
          sessionId = matchRes['session_id'] != null
              ? int.tryParse(matchRes['session_id'].toString())
              : null;
          courtId = matchRes['court_id'] != null
              ? int.tryParse(matchRes['court_id'].toString())
              : null;
        }
      } catch (_) {}

      // 3. Ambil seluruh skor dari tb_score
      final scores = await getScoresByMatchId(matchId);
      int totalScoreA = 0;
      int totalScoreB = 0;
      for (final s in scores) {
        totalScoreA += s.scoreSideA;
        totalScoreB += s.scoreSideB;
      }

      // 4. Pisahkan peserta ke dalam Side A dan Side B
      final List<int> sideAPlayers = [];
      final List<int> sideBPlayers = [];

      for (final participant in participants) {
        final rawPlayerId = participant['player_id'];
        if (rawPlayerId == null) continue;

        final playerIdValue = rawPlayerId is int
            ? rawPlayerId
            : int.tryParse(rawPlayerId.toString());
        if (playerIdValue == null) continue;

        final side = (participant['side'] ?? '').toString().toLowerCase().trim();
        final groupNo = participant['group_no'];
        final isSideB = side.contains('b') || groupNo == 2;

        if (isSideB) {
          sideBPlayers.add(playerIdValue);
        } else {
          sideAPlayers.add(playerIdValue);
        }
      }

      // 5. Bentuk PlayingHistoryModel untuk setiap participant
      final List<PlayingHistoryModel> histories = [];

      for (final pId in sideAPlayers) {
        int? partnerId;
        for (final other in sideAPlayers) {
          if (other != pId) {
            partnerId = other;
            break;
          }
        }
        final opponentId = sideBPlayers.isNotEmpty ? sideBPlayers.first : null;

        histories.add(
          PlayingHistoryModel(
            playerId: pId,
            matchId: matchId,
            sessionId: sessionId,
            courtId: courtId,
            score: totalScoreA,
            partnerPlayerId: partnerId,
            opponentPlayerId: opponentId,
            jumlahPermainan: 1,
          ),
        );
      }

      for (final pId in sideBPlayers) {
        int? partnerId;
        for (final other in sideBPlayers) {
          if (other != pId) {
            partnerId = other;
            break;
          }
        }
        final opponentId = sideAPlayers.isNotEmpty ? sideAPlayers.first : null;

        histories.add(
          PlayingHistoryModel(
            playerId: pId,
            matchId: matchId,
            sessionId: sessionId,
            courtId: courtId,
            score: totalScoreB,
            partnerPlayerId: partnerId,
            opponentPlayerId: opponentId,
            jumlahPermainan: 1,
          ),
        );
      }

      // 6. Simpan seluruh playing history ke tb_playing_history
      if (histories.isNotEmpty) {
        await savePlayingHistories(histories);
      }

      return histories;
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception(
          'Gagal mencatat riwayat bermain: ${e.message}',
        );
      }
      rethrow;
    }
  }

  /// Proses lengkap penyelesaian pertandingan:
  /// 1. Ambil data match dan score
  /// 2. Hitung agregat score
  /// 3. Update status tb_match menjadi 'finished'
  /// 4. Simpan riwayat bermain ke tb_playing_history berdasarkan tb_match_participant
  Future<void> finishMatchAndRecordHistory({
    required int matchId,
    MatchModel? fallbackMatch,
  }) async {
    try {
      // 1. Dapatkan data match dari tb_match
      MatchModel? match = await getMatchById(matchId);
      match ??= fallbackMatch;

      if (match == null) {
        throw Exception(
          'Data pertandingan dengan ID $matchId tidak ditemukan.',
        );
      }

      // 2. Ambil data score yang sudah tersimpan pada tb_score
      final scores = await getScoresByMatchId(matchId);
      if (scores.isEmpty) {
        throw Exception(
          'Belum ada skor yang tersimpan di database untuk pertandingan ini. Silakan simpan skor terlebih dahulu.',
        );
      }

      // 3. Hitung agregat total score untuk Side A dan Side B
      int totalScoreA = 0;
      int totalScoreB = 0;
      for (final s in scores) {
        totalScoreA += s.scoreSideA;
        totalScoreB += s.scoreSideB;
      }

      final String hasilPertandingan = totalScoreA > totalScoreB
          ? 'Side A Win'
          : (totalScoreB > totalScoreA ? 'Side B Win' : 'Draw');

      // 4. Selesaikan match pada tb_match
      await finishMatch(
        matchId: matchId,
        hasilPertandingan: hasilPertandingan,
      );

      // 5. Catat riwayat bermain dari tb_match_participant (jika belum tercatat oleh finishMatch)
      await recordPlayingHistory(matchId: matchId);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception(
          'Terjadi kesalahan saat menyelesaikan match: ${e.message}',
        );
      }
      rethrow;
    }
  }
}
