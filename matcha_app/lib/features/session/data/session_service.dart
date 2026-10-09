import 'dart:math';
import 'package:flutter/foundation.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../domain/session_model.dart';
import '../../drawing/domain/matcha_drawing_engine.dart';
import '../../games/domain/game_wizard_model.dart';

class SessionService {
  final SupabaseClient _supabase;

  /// Global reactive notifier untuk auto-refresh sesi di seluruh view yang sedang aktif
  static final ValueNotifier<int> sessionsVersion = ValueNotifier<int>(0);

  /// Memicu sinkronisasi data sesi reaktif
  static void notifySessionsChanged() {
    sessionsVersion.value++;
  }

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
        share_token,
        tb_user!tb_session_host_user_id_fkey (
          user_id,
          nama,
          foto
        ),
        tb_sport (
          sport_id,
          nama_sport
        ),
        tb_venue (
          venue_id,
          owner_user_id,
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
            share_token,
            tb_user!tb_session_host_user_id_fkey (
              user_id,
              nama,
              foto
            ),
            tb_sport (
              sport_id,
              nama_sport
            ),
            tb_venue (
              venue_id,
              owner_user_id,
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

  /// Mengambil detail sesi mabar berdasarkan share_token (Android App Links / Deep Link)
  Future<SessionModel?> getSessionByShareToken(String shareToken) async {
    final cleanToken = shareToken.trim();
    if (cleanToken.isEmpty) {
      return null;
    }

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
            share_token,
            tb_sport (
              sport_id,
              nama_sport
            ),
            tb_venue (
              venue_id,
              owner_user_id,
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
          .eq('share_token', cleanToken)
          .maybeSingle();

      if (response == null) return null;
      return SessionModel.fromMap(Map<String, dynamic>.from(response));
    } on PostgrestException catch (e) {
      throw Exception('Gagal mencari sesi berdasarkan share token: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat memuat sesi: $e');
    }
  }

  /// Memastikan sesi memiliki share_token valid. Jika belum ada (null), generate dan simpan ke database.
  Future<String> ensureShareToken(int sessionId) async {
    try {
      final res = await _supabase
          .from('tb_session')
          .select('share_token')
          .eq('session_id', sessionId)
          .maybeSingle();

      final existing = res?['share_token']?.toString().trim();
      if (existing != null && existing.isNotEmpty) {
        return existing;
      }

      // Generate random 32-character hexadecimal token
      final random = Random.secure();
      final values = List<int>.generate(16, (i) => random.nextInt(256));
      final token = values.map((b) => b.toRadixString(16).padLeft(2, '0')).join();

      await _supabase
          .from('tb_session')
          .update({'share_token': token})
          .eq('session_id', sessionId);

      return token;
    } on PostgrestException catch (e) {
      throw Exception('Gagal membuat share token sesi: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat memproses share token: $e');
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

      final rawJumlah = sessionData['jumlah_pemain'];
      final maxPlayers = (rawJumlah is num)
          ? rawJumlah.toInt()
          : (rawJumlah is String ? (int.tryParse(rawJumlah) ?? 6) : 6);
      final status = (sessionData['status_session'] ?? 'Open').toString().toLowerCase();
      if (status == 'closed' || status == 'completed' || status == 'cancelled') {
        throw Exception('Sesi mabar sudah ditutup atau dibatalkan.');
      }

      // 2. Cek apakah player_id ini sudah terdaftar di sesi ini
      final existing = await _supabase
          .from('tb_session_player')
          .select('player_id')
          .eq('session_id', sessionId)
          .eq('player_id', playerId)
          .maybeSingle();

      if (existing != null) {
        throw Exception('Kamu sudah terdaftar di sesi mabar ini!');
      }

      // 3. Cek role dan apakah player tersebut terhubung ke user_id yang sama
      final playerInfo = await _supabase
          .from('tb_player')
          .select('user_id')
          .eq('player_id', playerId)
          .maybeSingle();

      if (playerInfo != null && playerInfo['user_id'] != null) {
        final userId = playerInfo['user_id'];

        // Proteksi: Venue Owner tidak boleh bergabung sebagai peserta
        try {
          final userRow = await _supabase
              .from('tb_user')
              .select('role')
              .eq('user_id', userId)
              .maybeSingle();

          final role = (userRow?['role'] ?? '').toString().toLowerCase().trim();
          if (role == 'venue_owner' || role == 'owner' || role.contains('venue')) {
            throw Exception('Venue Owner tidak diperbolehkan bergabung ke sesi mabar sebagai peserta.');
          }
        } on PostgrestException catch (_) {
          // Abaikan jika query role gagal, lanjutkan ke pemeriksaan duplikasi
        }

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
          .select('player_id')
          .eq('session_id', sessionId);

      if ((currentPlayers as List).length >= maxPlayers) {
        throw Exception('Maaf, kuota slot sesi mabar ini sudah penuh!');
      }

      // 5. Insert ke tb_session_player
      await _supabase.from('tb_session_player').insert({
        'session_id': sessionId,
        'player_id': playerId,
      });
      notifySessionsChanged();
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
      final payload = <String, dynamic>{
        'nama': nama.trim(),
        'gender': gender,
        'level': level,
        'rating': 1200,
        if (usia != null) 'usia': usia,
        if (noHp != null && noHp.trim().isNotEmpty) 'no_hp': noHp.trim(),
        'user_id': null, // Guest player tidak punya user_id
        'created_at': DateTime.now().toIso8601String(),
        'updated_at': DateTime.now().toIso8601String(),
      };

      try {
        final res = await _supabase
            .from('tb_player')
            .insert(payload)
            .select('player_id')
            .single();
        return res['player_id'] is int
            ? res['player_id'] as int
            : int.parse(res['player_id'].toString());
      } catch (_) {
        // Fallback with minimal essential fields if any extra columns differ
        final minimalPayload = <String, dynamic>{
          'nama': nama.trim(),
          'gender': gender,
          'level': level,
          'user_id': null,
        };
        final res = await _supabase
            .from('tb_player')
            .insert(minimalPayload)
            .select('player_id')
            .single();
        return res['player_id'] is int
            ? res['player_id'] as int
            : int.parse(res['player_id'].toString());
      }
    } on PostgrestException catch (e) {
      throw Exception('Gagal mendaftarkan pemain tamu: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan mendaftarkan tamu: $e');
    }
  }

  /// Mencari pemain dari tb_player berdasarkan nama (termasuk foto dari tb_player / tb_user)
  Future<List<Map<String, dynamic>>> searchPlayers({
    String? query,
    int limit = 30,
  }) async {
    try {
      var req = _supabase
          .from('tb_player')
          .select('''
            player_id,
            user_id,
            nama,
            gender,
            level,
            usia,
            foto,
            tb_user (
              foto
            )
          ''');

      if (query != null && query.trim().isNotEmpty) {
        req = req.ilike('nama', '%${query.trim()}%');
      }

      final res = await req.order('player_id', ascending: false).limit(limit);
      final rawList = List<Map<String, dynamic>>.from(res as List);
      return rawList.map((p) {
        String? fotoUrl = p['foto'] as String?;
        if ((fotoUrl == null || fotoUrl.trim().isEmpty) && p['tb_user'] is Map) {
          fotoUrl = p['tb_user']['foto'] as String?;
        }
        final copy = Map<String, dynamic>.from(p);
        copy['foto'] = (fotoUrl != null && fotoUrl.trim().isNotEmpty) ? fotoUrl.trim() : null;
        return copy;
      }).toList();
    } catch (_) {
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
      notifySessionsChanged();
    } on PostgrestException catch (e) {
      throw Exception('Gagal membatalkan keikutsertaan: ${e.message}');
    }
  }

  /// Membuat sesi mabar baru dan menyimpannya ke tb_session, tb_session_court, tb_session_player
  Future<int> createScheduleSession({
    required int sportId,
    required int venueId,
    int? courtId,
    List<int>? courtIds,
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
    bool addYourselfAsPlayer = false,
  }) async {
    try {
      final waktuSession = '$jam WIB ($durasi)';
      final dateStr =
          '${tanggal.year}-${tanggal.month.toString().padLeft(2, '0')}-${tanggal.day.toString().padLeft(2, '0')}';
      final dateTimeStr = '$dateStr $jam:00';

      final random = Random.secure();
      final values = List<int>.generate(16, (i) => random.nextInt(256));
      final generatedToken = values.map((b) => b.toRadixString(16).padLeft(2, '0')).join();

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
            'share_token': generatedToken,
          })
          .select('session_id')
          .single();

      final sessionId = sessionRes['session_id'] as int;

      // 2. Hubungkan court ke session di tb_session_court (bisa multiple court)
      final finalCourtIds = <int>{};
      if (courtIds != null) {
        finalCourtIds.addAll(courtIds);
      }
      if (courtId != null) {
        finalCourtIds.add(courtId);
      }

      if (finalCourtIds.isNotEmpty) {
        final courtInserts = finalCourtIds.map((cId) => {
          'session_id': sessionId,
          'court_id': cId,
        }).toList();
        await _supabase.from('tb_session_court').insert(courtInserts);
      }

      // 3. Jika host memilih "Add Yourself", daftarkan host sebagai peserta
      if (addYourselfAsPlayer && hostPlayerId != null) {
        await _supabase.from('tb_session_player').insert({
          'session_id': sessionId,
          'player_id': hostPlayerId,
        });
      }

      notifySessionsChanged();
      return sessionId;
    } on PostgrestException catch (e) {
      throw Exception('Gagal membuat sesi mabar: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Membuat sesi pertandingan Host Game dari Wizard dan menyimpannya ke Supabase
  Future<int> createHostGameSession({
    required int sportId,
    required int venueId,
    int? courtId,
    required String namaSession,
    required String scoringSystem,
    required String jenisPermainan,
    required String playMode,
    required int jumlahPemain,
    String statusSession = 'In Progress',
    int? hostUserId,
    int? hostPlayerId,
    List<int>? playerIds,
  }) async {
    try {
      final now = DateTime.now();
      final dateStr =
          '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
      final jamStr =
          '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';
      final waktuSession = '$jamStr WIB (2 Jam)';
      final dateTimeStr = '$dateStr $jamStr:00';

      final random = Random.secure();
      final values = List<int>.generate(16, (i) => random.nextInt(256));
      final generatedToken = values.map((b) => b.toRadixString(16).padLeft(2, '0')).join();

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
            'status_session': statusSession,
            'jumlah_pemain': jumlahPemain.toString(),
            'jenis_permainan': playMode.isNotEmpty ? playMode : jenisPermainan,
            'scoring_system': scoringSystem,
            'share_token': generatedToken,
          })
          .select('session_id')
          .single();

      final sessionId = sessionRes['session_id'] is int
          ? sessionRes['session_id'] as int
          : int.parse(sessionRes['session_id'].toString());

      // 2. Hubungkan court ke session di tb_session_court
      int? effectiveCourtId = courtId;
      if (effectiveCourtId == null || effectiveCourtId <= 0) {
        try {
          final courtRes = await _supabase
              .from('tb_court')
              .select('court_id')
              .eq('venue_id', venueId)
              .limit(1)
              .maybeSingle();
          if (courtRes != null && courtRes['court_id'] != null) {
            effectiveCourtId = courtRes['court_id'] as int;
          }
        } catch (_) {}
      }

      if (effectiveCourtId != null && effectiveCourtId > 0) {
        try {
          await _supabase.from('tb_session_court').insert({
            'session_id': sessionId,
            'court_id': effectiveCourtId,
          });
        } catch (_) {}
      }

      // 3. Daftarkan semua player yang terdaftar di game ke tb_session_player
      final Set<int> allPlayerIds = {};
      if (playerIds != null) {
        for (final pId in playerIds) {
          if (pId > 0) allPlayerIds.add(pId);
        }
      }

      for (final pId in allPlayerIds) {
        try {
          await _supabase.from('tb_session_player').insert({
            'session_id': sessionId,
            'player_id': pId,
          });
        } catch (_) {}
      }

      notifySessionsChanged();
      return sessionId;
    } on PostgrestException catch (e) {
      throw Exception('Gagal membuat sesi host game: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Mengubah status sesi mabar (misal: 'In Progress', 'Finished', 'Cancelled')
  Future<void> updateSessionStatus(int sessionId, String status) async {
    try {
      await _supabase
          .from('tb_session')
          .update({
            'status_session': status,
            'updated_at': DateTime.now().toIso8601String(),
          })
          .eq('session_id', sessionId);
      notifySessionsChanged();
    } catch (_) {
      try {
        await _supabase
            .from('tb_session')
            .update({'status_session': status})
            .eq('session_id', sessionId);
        notifySessionsChanged();
      } catch (_) {}
    }
  }

  /// Membatalkan sesi mabar (mengubah status menjadi Cancelled)
  Future<void> cancelSession(int sessionId) async {
    try {
      await _supabase
          .from('tb_session')
          .update({'status_session': 'Cancelled'})
          .eq('session_id', sessionId);
      notifySessionsChanged();
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
      notifySessionsChanged();
    } on PostgrestException catch (e) {
      throw Exception('Gagal menghapus sesi dari database: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan saat menghapus sesi: $e');
    }
  }

  /// Menyimpan hasil drawing, match, participant, dan skor ke Supabase saat game selesai
  Future<void> saveFinishedGameResults({
    required int sessionId,
    required List<DrawingRound> rounds,
    required List<GamePlayerItem> allPlayers,
  }) async {
    try {
      // 1. Update session status menjadi Finished
      await _supabase
          .from('tb_session')
          .update({'status_session': 'Finished'})
          .eq('session_id', sessionId);

      // 2. Ambil court_id dari tb_session_court
      final sessionCourts = await _supabase
          .from('tb_session_court')
          .select('court_id')
          .eq('session_id', sessionId)
          .order('court_id', ascending: true);

      final List<int> courtIds = (sessionCourts as List)
          .map((c) => c['court_id'])
          .where((id) => id != null)
          .map((id) => id is int ? id : (int.tryParse(id.toString()) ?? 0))
          .where((id) => id > 0)
          .toList();

      // 3. Pastikan ada record tb_drawing
      final existingDrawings = await _supabase
          .from('tb_drawing')
          .select('drawing_id')
          .eq('session_id', sessionId)
          .limit(1);

      int drawingId;
      if ((existingDrawings as List).isNotEmpty) {
        final d = existingDrawings.first;
        drawingId = d['drawing_id'] is int ? d['drawing_id'] : int.parse(d['drawing_id'].toString());
      } else {
        final now = DateTime.now();
        final nowTime = '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}';
        final drawingInsert = await _supabase
            .from('tb_drawing')
            .insert({
              'session_id': sessionId,
              'match_format_id': 1,
              'tanggal_drawing': now.toIso8601String().split('T')[0],
              'jam_drawing': nowTime,
            })
            .select('drawing_id')
            .single();
        drawingId = drawingInsert['drawing_id'] is int ? drawingInsert['drawing_id'] : int.parse(drawingInsert['drawing_id'].toString());
      }

      // 4. Hapus match lama jika pernah tersimpan sebelumnya agar tidak duplikat
      final existingMatches = await _supabase
          .from('tb_match')
          .select('match_id')
          .eq('drawing_id', drawingId);

      final oldMatchIds = (existingMatches as List)
          .map((m) => m['match_id'])
          .where((id) => id != null)
          .toList();

      if (oldMatchIds.isNotEmpty) {
        try {
          await _supabase.from('tb_match_participant').delete().inFilter('match_id', oldMatchIds);
        } catch (_) {}
        try {
          await _supabase.from('tb_score').delete().inFilter('match_id', oldMatchIds);
        } catch (_) {}
        try {
          await _supabase.from('tb_match').delete().inFilter('match_id', oldMatchIds);
        } catch (_) {}
      }

      // 5. Loop seluruh ronde dan match untuk insert ke tb_match, tb_match_participant, dan tb_score
      int matchCounter = 0;
      final effectiveCourtCount = courtIds.isNotEmpty ? courtIds.length : 1;
      for (final round in rounds) {
        for (final match in round.matches) {
          matchCounter++;
          int? courtId;
          if (courtIds.isNotEmpty) {
            final cIdx = (match.courtNumber - 1).clamp(0, courtIds.length - 1);
            courtId = courtIds[cIdx];
          }

          final winnerTeam = match.scoreA > match.scoreB
              ? 'team_a'
              : (match.scoreB > match.scoreA ? 'team_b' : 'draw');

          final matchSummary = 'Game Score ${match.scoreA} - ${match.scoreB}';

          final cIdx = (match.courtNumber - 1).clamp(0, effectiveCourtCount - 1);
          final int nomorMatch = (round.roundNumber - 1) * effectiveCourtCount + (match.courtNumber > 0 ? match.courtNumber : (cIdx + 1));

          final matchInsert = await _supabase
              .from('tb_match')
              .insert({
                'drawing_id': drawingId,
                if (courtId != null) 'court_id': courtId,
                'nomor_match': nomorMatch,
                'status_match': 'Completed',
                'winner_team': winnerTeam,
                'hasil_pertandingan': matchSummary,
                'waktu_selesai': DateTime.now().toIso8601String(),
              })
              .select('match_id')
              .single();

          final matchId = matchInsert['match_id'] is int ? matchInsert['match_id'] : int.parse(matchInsert['match_id'].toString());

          int? resolvePlayerId(GamePlayerItem p) {
            if (p.playerId != null && p.playerId! > 0) return p.playerId;
            final matchItem = allPlayers.where((ap) => ap.id == p.id || ap.name.trim().toLowerCase() == p.name.trim().toLowerCase()).firstOrNull;
            if (matchItem != null && matchItem.playerId != null && matchItem.playerId! > 0) {
              return matchItem.playerId;
            }
            return null;
          }

          // Insert team_a participants
          for (final p in match.teamA) {
            final pId = resolvePlayerId(p);
            if (pId != null && pId > 0) {
              await _supabase.from('tb_match_participant').insert({
                'match_id': matchId,
                'player_id': pId,
                'side': 'team_a',
              });
            }
          }

          // Insert team_b participants
          for (final p in match.teamB) {
            final pId = resolvePlayerId(p);
            if (pId != null && pId > 0) {
              await _supabase.from('tb_match_participant').insert({
                'match_id': matchId,
                'player_id': pId,
                'side': 'team_b',
              });
            }
          }

          // Insert score ke tb_score
          await _supabase.from('tb_score').insert({
            'match_id': matchId,
            'set_number': 1,
            'game_number': 1,
            'point_score_a': match.scoreA.toString(),
            'point_score_b': match.scoreB.toString(),
            'game_score_a': match.scoreA,
            'game_score_b': match.scoreB,
            'score_side_a': match.scoreA,
            'score_side_b': match.scoreB,
            'status_score': 'Final',
          });
        }
      }
      notifySessionsChanged();
    } catch (_) {
      // Fallback
    }
  }
}