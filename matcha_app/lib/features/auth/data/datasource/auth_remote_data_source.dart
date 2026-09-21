import 'package:bcrypt/bcrypt.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../../domain/models/user_model.dart';

class AuthRemoteDataSource {
  final SupabaseClient _supabase;

  AuthRemoteDataSource({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Login dengan email atau nomor WhatsApp (no_hp)
  Future<UserModel> login({
    required String loginId,
    required String password,
  }) async {
    try {
      final trimmedLoginId = loginId.trim();
      final loginIdLower = trimmedLoginId.toLowerCase();
      final cleanPhone = trimmedLoginId.replaceAll(RegExp(r'[^0-9]'), '');

      // Query tb_user by email atau no_hp
      var query = _supabase.from('tb_user').select();

      final List<dynamic> users = await query
          .or('email.ilike.$loginIdLower,no_hp.eq.$trimmedLoginId${cleanPhone.isNotEmpty ? ",no_hp.eq.$cleanPhone" : ""}');

      if (users.isEmpty) {
        throw Exception('Email/Nomor WhatsApp tidak terdaftar.');
      }

      final userData = users.first as Map<String, dynamic>;
      final dbPassword = userData['password']?.toString() ?? '';

      // Verifikasi password (Bcrypt Hash atau Plaintext)
      bool isPasswordValid = false;
      if (dbPassword.startsWith(r'$2y$') || dbPassword.startsWith(r'$2a$') || dbPassword.startsWith(r'$2b$')) {
        // Standarisasi prefix $2y$ ke $2a$ untuk compatibility bcrypt library di Dart
        final normalizedHash = dbPassword.startsWith(r'$2y$')
            ? dbPassword.replaceFirst(r'$2y$', r'$2a$')
            : dbPassword;
        try {
          isPasswordValid = BCrypt.checkpw(password, normalizedHash);
        } catch (_) {
          isPasswordValid = false;
        }
      } else {
        // Fallback jika di database masih password plaintext
        isPasswordValid = (dbPassword == password.trim());
      }

      if (!isPasswordValid) {
        throw Exception('Password yang Anda masukkan salah.');
      }

      if (userData['status_user']?.toString().toLowerCase() == 'inactive') {
        throw Exception('Akun Anda saat ini dinonaktifkan.');
      }

      // Ambil data athlete profil dari tb_player
      final userId = userData['user_id'];
      final playerResponse = await _supabase
          .from('tb_player')
          .select()
          .eq('user_id', userId)
          .maybeSingle();

      return UserModel.fromJson(userData, playerJson: playerResponse);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Kendala database: ${e.message}');
      }
      rethrow;
    }
  }

  /// Registrasi pengguna baru ke tb_user dan otomatis buat data tb_player
  Future<UserModel> register({
    required String nama,
    required String email,
    required String noHp,
    required String password,
    required String gender,
    required int usia,
    required String level,
    String role = 'member',
    int? communityId,
  }) async {
    try {
      final cleanEmail = email.trim().toLowerCase();
      final cleanPhone = noHp.replaceAll(RegExp(r'[^0-9]'), '');

      // 1. Cek duplikasi email
      final existingEmail = await _supabase
          .from('tb_user')
          .select('user_id')
          .ilike('email', cleanEmail)
          .maybeSingle();

      if (existingEmail != null) {
        throw Exception('Email $cleanEmail sudah terdaftar. Silakan login.');
      }

      // 2. Cek duplikasi no_hp
      final existingPhone = await _supabase
          .from('tb_user')
          .select('user_id')
          .eq('no_hp', cleanPhone)
          .maybeSingle();

      if (existingPhone != null) {
        throw Exception('Nomor WhatsApp $cleanPhone sudah terdaftar.');
      }

      // 3. Hash password dengan Bcrypt
      final hashedPassword = BCrypt.hashpw(password, BCrypt.gensalt());

      // 4. Insert ke tb_user
      final newUserResponse = await _supabase
          .from('tb_user')
          .insert({
            'nama': nama.trim(),
            'email': cleanEmail,
            'no_hp': cleanPhone,
            'password': hashedPassword,
            'role': role,
            'status_user': 'Active',
            'is_host': false,
            'created_at': DateTime.now().toIso8601String(),
            'updated_at': DateTime.now().toIso8601String(),
          })
          .select()
          .single();

      final int createdUserId = newUserResponse['user_id'] is int
          ? newUserResponse['user_id'] as int
          : int.parse(newUserResponse['user_id'].toString());

      // 5. Insert ke tb_player
      Map<String, dynamic>? newPlayerResponse;
      try {
        newPlayerResponse = await _supabase
            .from('tb_player')
            .insert({
              'user_id': createdUserId,
              'nama': nama.trim(),
              'email': cleanEmail,
              'no_hp': cleanPhone,
              'gender': gender,
              'usia': usia,
              'level': level,
              'rating': 1200,
              ...?communityId == null ? null : {'community_id': communityId},
              'created_at': DateTime.now().toIso8601String(),
              'updated_at': DateTime.now().toIso8601String(),
            })
            .select()
            .single();
      } catch (_) {
        // abaikan jika tabel player opsional
      }

      return UserModel.fromJson(newUserResponse, playerJson: newPlayerResponse);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal mendaftar: ${e.message}');
      }
      rethrow;
    }
  }

  /// Mengambil data user berdasarkan user_id
  Future<UserModel?> getUserById(int userId) async {
    try {
      final userResponse = await _supabase
          .from('tb_user')
          .select()
          .eq('user_id', userId)
          .maybeSingle();

      if (userResponse == null) return null;

      final playerResponse = await _supabase
          .from('tb_player')
          .select()
          .eq('user_id', userId)
          .maybeSingle();

      return UserModel.fromJson(userResponse, playerJson: playerResponse);
    } catch (_) {
      return null;
    }
  }

  /// Update status is_host ke tb_user
  Future<bool> updateHostStatus(int userId, bool isHost) async {
    try {
      await _supabase
          .from('tb_user')
          .update({'is_host': isHost, 'updated_at': DateTime.now().toIso8601String()})
          .eq('user_id', userId);
      return true;
    } catch (_) {
      return false;
    }
  }
}
