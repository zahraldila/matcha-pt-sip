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

      // Ambil data athlete profil dari tb_player (ambil 1 profil terbaru jika ada multiple)
      final userId = userData['user_id'];
      final List<dynamic> players = await _supabase
          .from('tb_player')
          .select()
          .eq('user_id', userId)
          .order('player_id', ascending: false)
          .limit(1);

      final playerResponse = players.isNotEmpty ? players.first as Map<String, dynamic> : null;

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

      final List<dynamic> players = await _supabase
          .from('tb_player')
          .select()
          .eq('user_id', userId)
          .order('player_id', ascending: false)
          .limit(1);

      final playerResponse = players.isNotEmpty ? players.first as Map<String, dynamic> : null;

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

  /// Upload avatar image bytes ke Supabase Storage
  Future<String?> uploadAvatar(dynamic bytes, String fileExt) async {
    try {
      final fileName = 'avatar_${DateTime.now().millisecondsSinceEpoch}.$fileExt';
      final storagePath = 'avatars/$fileName';

      // Coba upload ke bucket 'avatars', jika gagal fallback ke 'general'
      try {
        await _supabase.storage.from('avatars').uploadBinary(
          storagePath,
          bytes,
          fileOptions: FileOptions(
            contentType: 'image/$fileExt',
            upsert: true,
          ),
        );
        return _supabase.storage.from('avatars').getPublicUrl(storagePath);
      } catch (_) {
        await _supabase.storage.from('general').uploadBinary(
          storagePath,
          bytes,
          fileOptions: FileOptions(
            contentType: 'image/$fileExt',
            upsert: true,
          ),
        );
        return _supabase.storage.from('general').getPublicUrl(storagePath);
      }
    } catch (e) {
      return null;
    }
  }

  /// Update data profil di tb_user & tb_player
  Future<UserModel> updateProfile({
    required int userId,
    required String nama,
    required String noHp,
    required String gender,
    required int usia,
    required String level,
    int? communityId,
    String? fotoUrl,
    bool removeFoto = false,
  }) async {
    try {
      final cleanPhone = noHp.replaceAll(RegExp(r'[^0-9]'), '');

      // 1. Update tb_user
      final Map<String, dynamic> userUpdates = {
        'nama': nama.trim(),
        'no_hp': cleanPhone,
        'updated_at': DateTime.now().toIso8601String(),
      };
      if (removeFoto) {
        userUpdates['foto'] = null;
      } else if (fotoUrl != null) {
        userUpdates['foto'] = fotoUrl;
      }

      final updatedUserRaw = await _supabase
          .from('tb_user')
          .update(userUpdates)
          .eq('user_id', userId)
          .select()
          .single();

      // 2. Update or insert tb_player
      final existingPlayers = await _supabase
          .from('tb_player')
          .select()
          .eq('user_id', userId)
          .order('player_id', ascending: false);

      Map<String, dynamic>? playerRaw;
      final Map<String, dynamic> playerFields = {
        'nama': nama.trim(),
        'no_hp': cleanPhone,
        'gender': gender,
        'usia': usia,
        'level': level,
        'community_id': communityId,
        'updated_at': DateTime.now().toIso8601String(),
      };
      if (removeFoto) {
        playerFields['foto'] = null;
      } else if (fotoUrl != null) {
        playerFields['foto'] = fotoUrl;
      }

      if (existingPlayers.isNotEmpty) {
        final existingId = existingPlayers.first['player_id'];
        playerRaw = await _supabase
            .from('tb_player')
            .update(playerFields)
            .eq('player_id', existingId)
            .select()
            .single();
      } else {
        playerFields['user_id'] = userId;
        playerFields['email'] = updatedUserRaw['email'];
        playerFields['rating'] = 1.0;
        playerFields['created_at'] = DateTime.now().toIso8601String();
        playerRaw = await _supabase
            .from('tb_player')
            .insert(playerFields)
            .select()
            .single();
      }

      return UserModel.fromJson(updatedUserRaw, playerJson: playerRaw);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal memperbarui profil: ${e.message}');
      }
      rethrow;
    }
  }

  /// Mengambil daftar komunitas untuk dropdown
  Future<List<Map<String, dynamic>>> getCommunitiesList() async {
    try {
      final res = await _supabase
          .from('tb_community')
          .select('community_id, nama_community')
          .order('nama_community', ascending: true);
      return List<Map<String, dynamic>>.from(res as List);
    } catch (_) {
      return [];
    }
  }
}
