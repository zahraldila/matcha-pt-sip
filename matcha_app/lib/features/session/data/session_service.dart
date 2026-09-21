import 'package:supabase_flutter/supabase_flutter.dart';
import '../domain/session_model.dart';

class SessionService {
  final SupabaseClient _supabase;

  SessionService({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Mengambil daftar olahraga aktif dari tb_sport
  Future<List<Map<String, dynamic>>> getSports() async {
    try {
      final response = await _supabase
          .from('tb_sport')
          .select('sport_id, nama_sport, status_sport')
          .order('nama_sport');

      return List<Map<String, dynamic>>.from(response);
    } on PostgrestException catch (e) {
      throw Exception('Gagal mengambil data olahraga: ${e.message}');
    }
  }

  /// Mengambil daftar sesi mabar dengan data terhubung (sport, venue, players)
  Future<List<SessionModel>> getSessions({
    int? sportId,
    String? status,
  }) async {
    try {
      var query = _supabase.from('tb_session').select('''
        session_id,
        host_user_id,
        sport_id,
        venue_id,
        nama_session,
        scoring_system,
        waktu_session,
        datetime,
        status_session,
        jumlah_pemain,
        jenis_permainan,
        tb_sport (
          sport_id,
          nama_sport
        ),
        tb_venue (
          venue_id,
          nama_venue,
          kota,
          alamat,
          foto
        ),
        tb_session_player (
          player_id,
          tb_player (
            player_id,
            nama,
            level,
            gender,
            foto
          )
        )
      ''');

      if (sportId != null) {
        query = query.eq('sport_id', sportId);
      }
      if (status != null) {
        query = query.eq('status_session', status);
      }

      final response = await query.order('datetime', ascending: false);

      return (response as List)
          .map((item) => SessionModel.fromMap(Map<String, dynamic>.from(item)))
          .toList();
    } on PostgrestException catch (e) {
      throw Exception('Gagal mengambil data sesi mabar: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat memuat sesi mabar: $e');
    }
  }

  /// Mengambil detail satu sesi mabar berdasarkan ID
  Future<SessionModel> getSessionDetail(int sessionId) async {
    try {
      final response = await _supabase
          .from('tb_session')
          .select('''
            session_id,
            host_user_id,
            sport_id,
            venue_id,
            nama_session,
            scoring_system,
            waktu_session,
            datetime,
            status_session,
            jumlah_pemain,
            jenis_permainan,
            tb_sport (
              sport_id,
              nama_sport
            ),
            tb_venue (
              venue_id,
              nama_venue,
              kota,
              alamat,
              foto,
              fasilitas,
              jam_operasional
            ),
            tb_session_player (
              player_id,
              tb_player (
                player_id,
                nama,
                level,
                gender,
                foto
              )
            )
          ''')
          .eq('session_id', sessionId)
          .single();

      return SessionModel.fromMap(Map<String, dynamic>.from(response));
    } on PostgrestException catch (e) {
      throw Exception('Gagal mengambil detail sesi: ${e.message}');
    }
  }

  /// Quick Join Sesi untuk Tamu (Guest) atau Member
  Future<void> joinSession({
    required int sessionId,
    required int playerId,
  }) async {
    try {
      await _supabase.from('tb_session_player').insert({
        'session_id': sessionId,
        'player_id': playerId,
      });
    } on PostgrestException catch (e) {
      throw Exception('Gagal bergabung ke sesi: ${e.message}');
    }
  }

  /// Membuat Player Tamu baru (Guest) secara instan lalu gabung
  Future<int> registerGuestPlayer({
    required String nama,
    required String gender,
    required String level,
    String? noHp,
  }) async {
    try {
      final res = await _supabase
          .from('tb_player')
          .insert({
            'nama': nama,
            'gender': gender,
            'level': level,
            'no_hp': noHp,
            'user_id': null, // Guest player tidak punya user_id
          })
          .select('player_id')
          .single();

      return res['player_id'] as int;
    } on PostgrestException catch (e) {
      throw Exception('Gagal mendaftarkan pemain tamu: ${e.message}');
    }
  }
}