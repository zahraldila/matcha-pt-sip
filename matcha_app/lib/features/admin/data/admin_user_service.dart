import 'package:bcrypt/bcrypt.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../../auth/domain/models/user_model.dart';

class AdminUserService {
  final SupabaseClient _supabase;

  AdminUserService({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Mengambil semua akun pengguna terdaftar beserta data profil pemainnya
  Future<List<UserModel>> getAllUsers() async {
    try {
      final response = await _supabase
          .from('tb_user')
          .select('''
            user_id,
            nama,
            email,
            no_hp,
            role,
            is_host,
            foto,
            created_at,
            updated_at,
            tb_player (
              player_id,
              level,
              gender,
              usia,
              rating,
              community_id
            )
          ''')
          .order('nama', ascending: true);

      final list = (response as List).map((json) {
        final userData = Map<String, dynamic>.from(json);
        final players = json['tb_player'] as List?;
        final playerJson = (players != null && players.isNotEmpty)
            ? Map<String, dynamic>.from(players.first)
            : null;
        return UserModel.fromJson(userData, playerJson: playerJson);
      }).toList();

      list.sort((a, b) => a.nama.toLowerCase().compareTo(b.nama.toLowerCase()));

      return list;
    } on PostgrestException catch (e) {
      throw Exception('Gagal mengambil daftar pengguna: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Memeriksa apakah email sudah terdaftar pada pengguna lain
  Future<bool> isEmailTaken(String email, {required int excludeUserId}) async {
    try {
      final cleanEmail = email.trim().toLowerCase();
      final existing = await _supabase
          .from('tb_user')
          .select('user_id')
          .ilike('email', cleanEmail)
          .neq('user_id', excludeUserId)
          .maybeSingle();

      return existing != null;
    } catch (_) {
      return false;
    }
  }

  /// Memeriksa apakah nomor HP sudah terdaftar pada pengguna lain
  Future<bool> isPhoneTaken(String phone, {required int excludeUserId}) async {
    try {
      final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
      if (cleanPhone.isEmpty) return false;
      final existing = await _supabase
          .from('tb_user')
          .select('user_id')
          .eq('no_hp', cleanPhone)
          .neq('user_id', excludeUserId)
          .maybeSingle();

      return existing != null;
    } catch (_) {
      return false;
    }
  }

  /// Memperbarui profil, peran, dan detail pengguna oleh Admin
  Future<void> updateUser({
    required int userId,
    required String nama,
    required String email,
    String? noHp,
    required String role,
    required bool isHost,
    String? password,
    String? gender,
    int? usia,
    String? level,
  }) async {
    try {
      final cleanEmail = email.trim().toLowerCase();
      final cleanPhone = noHp?.replaceAll(RegExp(r'[^0-9]'), '');

      // 1. Cek duplikasi email pada akun pengguna lain (seperti saat registrasi)
      final existingEmail = await _supabase
          .from('tb_user')
          .select('user_id')
          .ilike('email', cleanEmail)
          .neq('user_id', userId)
          .maybeSingle();

      if (existingEmail != null) {
        throw Exception('Email sudah terdaftar.');
      }

      // 2. Update tb_user
      final userUpdate = <String, dynamic>{
        'nama': nama.trim(),
        'email': cleanEmail,
        'no_hp': cleanPhone != null && cleanPhone.isNotEmpty ? cleanPhone : null,
        'role': role.trim(),
        'is_host': isHost,
        'updated_at': DateTime.now().toIso8601String(),
      };

      if (password != null && password.trim().isNotEmpty) {
        userUpdate['password'] = BCrypt.hashpw(password.trim(), BCrypt.gensalt());
      }

      await _supabase.from('tb_user').update(userUpdate).eq('user_id', userId);

      // 2. Update tb_player secara aman
      try {
        final existingPlayers = await _supabase
            .from('tb_player')
            .select('player_id')
            .eq('user_id', userId)
            .limit(1);

        if ((existingPlayers as List).isNotEmpty) {
          final playerUpdate = <String, dynamic>{
            'nama': nama.trim(),
            'gender': ?gender,
            'usia': ?usia,
            'level': ?level,
          };
          await _supabase
              .from('tb_player')
              .update(playerUpdate)
              .eq('user_id', userId);
        } else if (role.trim().toLowerCase() != 'admin') {
          await _supabase.from('tb_player').insert({
            'user_id': userId,
            'nama': nama.trim(),
            'gender': gender ?? 'Male',
            'usia': usia ?? 22,
            'level': level ?? 'Intermediate',
            'rating': 1.00,
          });
        }
      } catch (_) {
        // Player profile sync fallback
      }
    } on PostgrestException catch (e) {
      throw Exception('Gagal memperbarui data pengguna: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Menghapus akun pengguna dari database secara permanen oleh Admin
  Future<void> deleteUser(int userId, {int? currentUserId}) async {
    if (currentUserId != null && userId == currentUserId) {
      throw Exception('Aksi ditolak: Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif login.');
    }
    try {
      // 1. Lepaskan atau hapus data relasi player
      try {
        await _supabase.from('tb_player').delete().eq('user_id', userId);
      } catch (_) {
        try {
          await _supabase
              .from('tb_player')
              .update({'user_id': null})
              .eq('user_id', userId);
        } catch (_) {}
      }

      // 2. Lepaskan keterkaitan venue owner jika ada
      try {
        await _supabase
            .from('tb_venue')
            .update({'created_by': null})
            .eq('created_by', userId);
      } catch (_) {}

      // 3. Lepaskan keterkaitan community creator jika ada
      try {
        await _supabase
            .from('tb_community')
            .update({'created_by': null})
            .eq('created_by', userId);
      } catch (_) {}

      // 4. Hapus record dari tb_user
      await _supabase.from('tb_user').delete().eq('user_id', userId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal menghapus pengguna: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }
}
