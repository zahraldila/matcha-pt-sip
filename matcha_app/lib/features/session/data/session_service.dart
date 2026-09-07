import 'package:supabase_flutter/supabase_flutter.dart';

class SessionService {
  final SupabaseClient _supabase;

  SessionService({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Mengambil daftar olahraga yang aktif.
  Future<List<Map<String, dynamic>>> getSports() async {
    try {
      final response = await _supabase
          .from('tb_sport')
          .select('sport_id, nama_sport')
          .eq('status_sport', 'active')
          .order('nama_sport');

      return List<Map<String, dynamic>>.from(response);
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal mengambil data olahraga: ${e.message}',
      );
    }
  }

  /// Mengambil daftar pemain yang aktif.
  Future<List<Map<String, dynamic>>> getPlayers() async {
    try {
      final response = await _supabase
          .from('tb_player')
          .select('player_id, nama_player')
          .eq('status_member', 'active')
          .order('nama_player');

      return List<Map<String, dynamic>>.from(response);
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal mengambil data pemain: ${e.message}',
      );
    }
  }

  /// Mengambil daftar lapangan yang aktif dan tersedia
  /// untuk olahraga tertentu.
  Future<List<Map<String, dynamic>>> getCourts({
    required int sportId,
  }) async {
    try {
      final response = await _supabase
          .from('tb_court')
          .select('court_id, sport_id, nama_court, lokasi')
          .eq('sport_id', sportId)
          .eq('status_aktif', 'active')
          .eq('status_ketersediaan', 'tersedia')
          .order('nama_court');

      return List<Map<String, dynamic>>.from(response);
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal mengambil data lapangan: ${e.message}',
      );
    }
  }

  /// Mengambil daftar session.
  Future<List<Map<String, dynamic>>> getSessions() async {
    try {
      final response = await _supabase
          .from('tb_session')
          .select(
            'session_id, '
            'user_id, '
            'sport_id, '
            'nama_session, '
            'drawing_method, '
            'status_session, '
            'waktu_session, '
            'jenis_permainan',
          )
          .order('waktu_session', ascending: false);

      return List<Map<String, dynamic>>.from(response);
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal mengambil data session: ${e.message}',
      );
    }
  }
}