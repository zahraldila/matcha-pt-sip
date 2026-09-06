import 'package:supabase_flutter/supabase_flutter.dart';

class SessionRemoteDataSource {
  final SupabaseClient _supabase;

  SessionRemoteDataSource({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  // ============================================================
  // MASTER DATA SPORT
  // ============================================================

  Future<List<Map<String, dynamic>>> getActiveSports() async {
    try {
      final response = await _supabase
          .from('tb_sport')
          .select('sport_id, nama_sport, status_sport')
          .eq('status_sport', 'active')
          .order('nama_sport');

      return List<Map<String, dynamic>>.from(response);
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal mengambil data olahraga: ${e.message}',
      );
    }
  }

  // ============================================================
  // MASTER DATA PLAYER
  // ============================================================

  Future<List<Map<String, dynamic>>> getActivePlayers() async {
    try {
      final response = await _supabase
          .from('tb_player')
          .select('player_id, nama_player, status_member')
          .eq('status_member', 'active')
          .order('nama_player');

      return List<Map<String, dynamic>>.from(response);
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal mengambil data pemain: ${e.message}',
      );
    }
  }

  // ============================================================
  // MASTER DATA COURT
  // ============================================================

  Future<List<Map<String, dynamic>>> getAvailableCourts({
    required int sportId,
  }) async {
    try {
      final response = await _supabase
          .from('tb_court')
          .select(
            'court_id, sport_id, nama_court, lokasi, '
            'status_ketersediaan, status_aktif',
          )
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

  // ============================================================
  // CREATE SESSION
  // ============================================================

  Future<int> createSession({
    required int userId,
    required int sportId,
    required String namaSession,
    required String drawingMethod,
    required String statusSession,
    required DateTime waktuSession,
    required String jenisPermainan,
  }) async {
    try {
      final response = await _supabase
          .from('tb_session')
          .insert({
            'user_id': userId,
            'sport_id': sportId,
            'nama_session': namaSession,
            'drawing_method': drawingMethod,
            'status_session': statusSession,
            'waktu_session': waktuSession.toIso8601String(),
            'created_at': DateTime.now().toIso8601String(),
            'jenis_permainan': jenisPermainan,
          })
          .select('session_id')
          .single();

      return response['session_id'] as int;
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal membuat sesi: ${e.message}',
      );
    }
  }

  // ============================================================
  // SESSION - PLAYER
  // ============================================================

  Future<void> addSessionPlayers({
    required int sessionId,
    required List<int> playerIds,
  }) async {
    if (playerIds.isEmpty) return;

    try {
      final rows = playerIds
          .map(
            (playerId) => {
              'session_id': sessionId,
              'player_id': playerId,
            },
          )
          .toList();

      await _supabase.from('tb_session_player').insert(rows);
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal menyimpan peserta sesi: ${e.message}',
      );
    }
  }

  // ============================================================
  // SESSION - COURT
  // ============================================================

  Future<void> addSessionCourts({
    required int sessionId,
    required List<int> courtIds,
  }) async {
    if (courtIds.isEmpty) return;

    try {
      final rows = courtIds
          .map(
            (courtId) => {
              'session_id': sessionId,
              'court_id': courtId,
            },
          )
          .toList();

      await _supabase.from('tb_session_court').insert(rows);
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal menyimpan lapangan sesi: ${e.message}',
      );
    }
  }

  // ============================================================
  // GET SESSION LIST
  // ============================================================

  Future<List<Map<String, dynamic>>> getSessions({
    int? userId,
  }) async {
    try {
      var query = _supabase.from('tb_session').select(
        '''
        session_id,
        user_id,
        sport_id,
        nama_session,
        drawing_method,
        status_session,
        waktu_session,
        created_at,
        updated_at,
        jenis_permainan,
        tb_sport (
          sport_id,
          nama_sport
        )
        ''',
      );

      if (userId != null) {
        query = query.eq('user_id', userId);
      }

      final response = await query.order(
        'waktu_session',
        ascending: false,
      );

      return List<Map<String, dynamic>>.from(response);
    } on PostgrestException catch (e) {
      throw Exception(
        'Gagal mengambil daftar sesi: ${e.message}',
      );
    }
  }
}