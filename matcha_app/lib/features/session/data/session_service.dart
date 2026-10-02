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
      // 1. Cek apakah session masih membuka slot atau sudah penuh
      final sessionData = await _supabase
          .from('tb_session')
          .select('jumlah_pemain, status_session')
          .eq('session_id', sessionId)
          .single();

      final maxPlayers = (sessionData['jumlah_pemain'] as num?)?.toInt() ?? 6;
      final status = (sessionData['status_session'] ?? 'Open').toString().toLowerCase();
      if (status == 'closed' || status == 'completed' || status == 'cancelled') {
        throw Exception('Sesi mabar sudah ditutup atau dibatalkan.');
      }

      // 2. Cek apakah player_id ini sudah terdaftar di sesi ini
      final existing = await _supabase
          .from('tb_session_player')
          .select('session_player_id')
          .eq('session_id', sessionId)
          .eq('player_id', playerId)
          .maybeSingle();

      if (existing != null) {
        throw Exception('Kamu sudah terdaftar di sesi mabar ini!');
      }

      // 3. Cek juga jika player tersebut terhubung ke user_id yang sama
      final playerInfo = await _supabase
          .from('tb_player')
          .select('user_id')
          .eq('player_id', playerId)
          .maybeSingle();

      if (playerInfo != null && playerInfo['user_id'] != null) {
        final userId = playerInfo['user_id'];
        final allSessionPlayers = await _supabase
            .from('tb_session_player')
            .select('player_id, tb_player(user_id)')
            .eq('session_id', sessionId);

        for (final sp in (allSessionPlayers as List)) {
          if (sp is Map && sp['tb_player'] is Map) {
            final uId = sp['tb_player']['user_id'];
            if (uId != null && uId == userId) {
              throw Exception('Akun kamu sudah terdaftar di sesi mabar ini!');
            }
          }
        }
      }

      // 4. Cek total pemain saat ini agar tidak melebihi kuota
      final currentPlayers = await _supabase
          .from('tb_session_player')
          .select('session_player_id')
          .eq('session_id', sessionId);

      if ((currentPlayers as List).length >= maxPlayers) {
        throw Exception('Maaf, kuota slot sesi mabar ini sudah penuh!');
      }

      // 5. Insert ke tb_session_player
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

  /// Mencari pemain dari tb_player berdasarkan nama
  Future<List<Map<String, dynamic>>> searchPlayers({
    String? query,
    int limit = 20,
  }) async {
    try {
      var req = _supabase
          .from('tb_player')
          .select('player_id, user_id, nama, gender, level, usia, foto');

      if (query != null && query.trim().isNotEmpty) {
        req = req.ilike('nama', '%${query.trim()}%');
      }

      final res = await req.order('player_id', ascending: false).limit(limit);
      return List<Map<String, dynamic>>.from(res as List);
    } on PostgrestException catch (e) {
      throw Exception('Gagal mencari pemain: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat mencari pemain: $e');
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

  /// Membatalkan sesi mabar (mengubah status menjadi Cancelled)
  Future<void> cancelSession(int sessionId) async {
    try {
      await _supabase
          .from('tb_session')
          .update({'status_session': 'Cancelled'})
          .eq('session_id', sessionId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal membatalkan sesi: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Menghapus sesi mabar secara permanen dari database
  Future<void> deleteSession(int sessionId) async {
    try {
      // 1. Ambil drawing_id yang terhubung
      final drawings = await _supabase
          .from('tb_drawing')
          .select('drawing_id')
          .eq('session_id', sessionId);

      final drawingIds = (drawings as List)
          .map((d) => d['drawing_id'])
          .where((id) => id != null)
          .toList();

      if (drawingIds.isNotEmpty) {
        // Ambil match_id yang terhubung
        final matches = await _supabase
            .from('tb_match')
            .select('match_id')
            .inFilter('drawing_id', drawingIds);

        final matchIds = (matches as List)
            .map((m) => m['match_id'])
            .where((id) => id != null)
            .toList();

        if (matchIds.isNotEmpty) {
          try {
            await _supabase
                .from('tb_match_player')
                .delete()
                .inFilter('match_id', matchIds);
          } catch (_) {}

          try {
            await _supabase
                .from('tb_score')
                .delete()
                .inFilter('match_id', matchIds);
          } catch (_) {}
        }

        try {
          await _supabase
              .from('tb_match')
              .delete()
              .inFilter('drawing_id', drawingIds);
        } catch (_) {}

        try {
          await _supabase
              .from('tb_drawing')
              .delete()
              .eq('session_id', sessionId);
        } catch (_) {}
      }

      // 2. Hapus tb_kudos jika ada
      try {
        await _supabase
            .from('tb_kudos')
            .delete()
            .eq('session_id', sessionId);
      } catch (_) {}

      // 3. Hapus relasi pemain di tb_session_player
      try {
        await _supabase
            .from('tb_session_player')
            .delete()
            .eq('session_id', sessionId);
      } catch (_) {}

      // 4. Hapus relasi court di tb_session_court
      try {
        await _supabase
            .from('tb_session_court')
            .delete()
            .eq('session_id', sessionId);
      } catch (_) {}

      // 5. Hapus row utama di tb_session
      await _supabase
          .from('tb_session')
          .delete()
          .eq('session_id', sessionId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal menghapus sesi dari database: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat menghapus sesi: $e');
    }
  }
}