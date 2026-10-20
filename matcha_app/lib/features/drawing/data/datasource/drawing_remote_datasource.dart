import 'package:supabase_flutter/supabase_flutter.dart';
import '../../domain/models/drawing_round_model.dart';
import '../../domain/models/match_pairing_model.dart';

class DrawingRemoteDataSource {
  final SupabaseClient _supabase;

  DrawingRemoteDataSource({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Menyimpan hasil ronde undian dan pertandingan ke Supabase tb_drawing & tb_match
  Future<DrawingRoundModel> saveDrawingRound(DrawingRoundModel round) async {
    try {
      // 1. Insert ke tb_drawing
      final drawingInsert = await _supabase
          .from('tb_drawing')
          .insert({
            if (round.sessionId != null) 'session_id': round.sessionId,
          })
          .select()
          .single();

      final int drawingId = drawingInsert['drawing_id'] as int? ?? 1;

      // 2. Insert batch matches ke tb_match
      final List<MatchPairingModel> savedMatches = [];

      for (final match in round.matches) {
        try {
          final matchInsert = await _supabase
              .from('tb_match')
              .insert({
                'drawing_id': drawingId,
                'court_id': match.courtId,
                'status_match': match.statusMatch,
              })
              .select()
              .single();

          savedMatches.add(match.copyWith(matchId: matchInsert['match_id'] as int?));
        } catch (_) {
          savedMatches.add(match);
        }
      }

      return round.copyWith(
        drawingId: drawingId,
        matches: savedMatches,
      );
    } catch (e) {
      // Fallback offline mock response
      return round.copyWith(drawingId: DateTime.now().millisecondsSinceEpoch);
    }
  }

  /// Mengambil data hasil drawing yang sudah tersimpan berdasarkan session_id
  Future<List<DrawingRoundModel>> getDrawingsBySessionId(int sessionId) async {
    try {
      final response = await _supabase
          .from('tb_drawing')
          .select('''
            drawing_id,
            session_id,
            created_at
          ''')
          .eq('session_id', sessionId)
          .order('drawing_id', ascending: true);

      final List<dynamic> data = response as List<dynamic>;
      return data.map((json) => DrawingRoundModel.fromJson(json as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }
}
