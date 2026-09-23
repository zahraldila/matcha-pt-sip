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
        tb_session_court (
          court_id,
          tb_court (
            court_id,
            nama_court
          )
        ),
        tb_session_player (
          player_id,
          tb_player (
            player_id,
            user_id,
            nama,
            level,
            gender,
            usia,
            foto,
            no_hp,
            email
          )
        )
      ''');

      if (sportId != null) {
        query = query.eq('sport_id', sportId);
      }
      if (status != null) {
        query = query.eq('status_session', status);
      }

      final response = await query.order('session_id', ascending: false);

      final list = (response as List)
          .map((item) => SessionModel.fromMap(Map<String, dynamic>.from(item)))
          .toList();

      // Urutkan seperti di web: Open & Ready for Drawing teratas, disusul yang terbaru
      list.sort((a, b) {
        final aPriority = _getStatusPriority(a.statusSession);
        final bPriority = _getStatusPriority(b.statusSession);
        if (aPriority != bPriority) {
          return aPriority.compareTo(bPriority);
        }
        return b.sessionId.compareTo(a.sessionId);
      });

      return list;
    } on PostgrestException catch (e) {
      throw Exception('Gagal mengambil data sesi mabar: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat memuat sesi mabar: $e');
    }
  }

  static int _getStatusPriority(String status) {
    final s = status.toLowerCase().trim();
    if (s == 'open') return 0;
    if (s == 'ready for drawing') return 1;
    if (s == 'in progress' || s == 'live') return 2;
    return 3; // finished / completed
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
            tb_session_court (
              court_id,
              tb_court (
                court_id,
                nama_court
              )
            ),
            tb_session_player (
              player_id,
              tb_player (
                player_id,
                user_id,
                nama,
                level,
                gender,
                usia,
                foto,
                no_hp,
                email
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
    int? usia,
    String? noHp,
  }) async {
    try {
      final res = await _supabase
          .from('tb_player')
          .insert({
            'nama': nama,
            'gender': gender,
            'level': level,
            'usia': usia,
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

  /// Membatalkan keikutsertaan pemain dari sesi mabar
  Future<void> leaveSession({
    required int sessionId,
    required int playerId,
  }) async {
    try {
      await _supabase
          .from('tb_session_player')
          .delete()
          .eq('session_id', sessionId)
          .eq('player_id', playerId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal membatalkan keikutsertaan: ${e.message}');
    }
  }

  /// Membuat sesi mabar baru dan menyimpannya ke tb_session, tb_session_court, tb_session_player
  Future<int> createScheduleSession({
    required int sportId,
    required int venueId,
    required int courtId,
    required String namaSession,
    required DateTime tanggal,
    required String jam,
    required String durasi,
    required int jumlahPemain,
    required String jenisPermainan,
    String? scoringSystem,
    String? levelRekomendasi,
    String? deskripsi,
    int? hostUserId,
    int? hostPlayerId,
  }) async {
    try {
      final waktuSession = '$jam WIB ($durasi)';
      final dateStr =
          '${tanggal.year}-${tanggal.month.toString().padLeft(2, '0')}-${tanggal.day.toString().padLeft(2, '0')}';
      final dateTimeStr = '$dateStr $jam:00';

      // 1. Insert session
      final sessionRes = await _supabase
          .from('tb_session')
          .insert({
            'host_user_id': hostUserId,
            'sport_id': sportId,
            'venue_id': venueId,
            'nama_session': namaSession,
            'waktu_session': waktuSession,
            'datetime': dateTimeStr,
            'status_session': 'Open',
            'jumlah_pemain': jumlahPemain.toString(),
            'jenis_permainan': jenisPermainan,
            'scoring_system': scoringSystem ?? 'Total of 3',
          })
          .select('session_id')
          .single();

      final sessionId = sessionRes['session_id'] as int;

      // 2. Hubungkan court ke session di tb_session_court
      await _supabase.from('tb_session_court').insert({
        'session_id': sessionId,
        'court_id': courtId,
      });

      // 3. Daftarkan host sebagai pemain di tb_session_player jika ada
      int? effectivePlayerId = hostPlayerId;
      if (effectivePlayerId == null && hostUserId != null) {
        final pRes = await _supabase
            .from('tb_player')
            .select('player_id')
            .eq('user_id', hostUserId)
            .maybeSingle();
        if (pRes != null && pRes['player_id'] != null) {
          effectivePlayerId = pRes['player_id'] as int;
        }
      }

      if (effectivePlayerId != null && effectivePlayerId > 0) {
        await _supabase.from('tb_session_player').insert({
          'session_id': sessionId,
          'player_id': effectivePlayerId,
        });
      }

      return sessionId;
    } on PostgrestException catch (e) {
      throw Exception('Gagal membuat sesi mabar: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }
}