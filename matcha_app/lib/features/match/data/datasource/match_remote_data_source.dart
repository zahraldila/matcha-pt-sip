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

  /// Mengambil data match dari tb_match berdasarkan match_id
  Future<MatchModel?> getMatchById(int matchId) async {
    try {
      final response = await _supabase
          .from('tb_match')
          .select()
          .eq('match_id', matchId)
          .maybeSingle();

      if (response == null) return null;
      return MatchModel.fromJson(response);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal mengambil data pertandingan: ${e.message}');
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
  Future<void> finishMatch({
    required int matchId,
    required String hasilPertandingan,
  }) async {
    final now = DateTime.now().toIso8601String();
    try {
      await _supabase.from('tb_match').update({
        'status_match': 'Finished',
        'waktu_selesai': now,
        'hasil_pertandingan': hasilPertandingan,
        'updated_at': now,
      }).eq('match_id', matchId);
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

        if (existing != null) {
          await _supabase
              .from('tb_playing_history')
              .update({
                'total_score': history.totalScore,
                'is_win': history.isWin,
              })
              .eq('player_id', history.playerId)
              .eq('match_id', history.matchId);
        } else {
          await _supabase
              .from('tb_playing_history')
              .insert({
                'player_id': history.playerId,
                'match_id': history.matchId,
                'total_score': history.totalScore,
                'is_win': history.isWin,
              });
        }
      }
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal menyimpan riwayat bermain ke database: ${e.message}');
      }
      rethrow;
    }
  }

  /// Proses lengkap penyelesaian pertandingan:
  /// 1. Ambil data match dan score
  /// 2. Hitung agregat score
  /// 3. Update status tb_match menjadi 'finished'
  /// 4. Simpan riwayat bermain ke tb_playing_history
  Future<void> finishMatchAndRecordHistory({
    required int matchId,
    MatchModel? fallbackMatch,
  }) async {
    try {
      // 1. Dapatkan data match dari tb_match
      MatchModel? match = await getMatchById(matchId);
      match ??= fallbackMatch;

      if (match == null) {
        throw Exception('Data pertandingan dengan ID $matchId tidak ditemukan.');
      }

      // 2. Ambil data score yang sudah tersimpan pada tb_score
      final scores = await getScoresByMatchId(matchId);
      if (scores.isEmpty) {
        throw Exception('Belum ada skor yang tersimpan di database untuk pertandingan ini. Silakan simpan skor terlebih dahulu.');
      }

      // 3. Hitung agregat total score untuk Side A dan Side B
      int totalScoreA = 0;
      int totalScoreB = 0;
      for (final s in scores) {
        totalScoreA += s.scoreSideA;
        totalScoreB += s.scoreSideB;
      }

      final bool isSideAWin = totalScoreA > totalScoreB;
      final bool isSideBWin = totalScoreB > totalScoreA;

      // 4. Ubah status match menjadi 'finished' pada tb_match
      await updateMatchStatus(matchId: matchId, status: 'finished');

      // 5. Susun playing history untuk setiap player yang terlibat pada match
      final List<PlayingHistoryModel> histories = [];

      // Side A Players
      if (match.sideAPlayer1 != null) {
        histories.add(PlayingHistoryModel(
          playerId: match.sideAPlayer1!,
          matchId: matchId,
          totalScore: totalScoreA,
          isWin: isSideAWin,
        ));
      }
      if (match.sideAPlayer2 != null) {
        histories.add(PlayingHistoryModel(
          playerId: match.sideAPlayer2!,
          matchId: matchId,
          totalScore: totalScoreA,
          isWin: isSideAWin,
        ));
      }

      // Side B Players
      if (match.sideBPlayer1 != null) {
        histories.add(PlayingHistoryModel(
          playerId: match.sideBPlayer1!,
          matchId: matchId,
          totalScore: totalScoreB,
          isWin: isSideBWin,
        ));
      }
      if (match.sideBPlayer2 != null) {
        histories.add(PlayingHistoryModel(
          playerId: match.sideBPlayer2!,
          matchId: matchId,
          totalScore: totalScoreB,
          isWin: isSideBWin,
        ));
      }

      // 6. Simpan seluruh playing history ke tb_playing_history
      if (histories.isNotEmpty) {
        await savePlayingHistories(histories);
      }
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Terjadi kesalahan saat menyelesaikan match: ${e.message}');
      }
      rethrow;
    }
  }
}
