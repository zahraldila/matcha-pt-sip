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
            status_user,
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
          .order('user_id', ascending: true);

      final list = (response as List).map((json) {
        final userData = Map<String, dynamic>.from(json);
        final players = json['tb_player'] as List?;
        final playerJson = (players != null && players.isNotEmpty)
            ? Map<String, dynamic>.from(players.first)
            : null;
        return UserModel.fromJson(userData, playerJson: playerJson);
      }).toList();

      return list;
    } on PostgrestException catch (e) {
      throw Exception('Gagal mengambil daftar pengguna: ${e.message}');
    } catch (e) {
      throw Exception('Terjadi kesalahan: $e');
    }
  }

  /// Memperbarui role dan status akun pengguna oleh Admin
  Future<void> updateUserRoleAndStatus({
    required int userId,
    required String role,
    required bool isHost,
    required String statusUser,
  }) async {
    try {
      await _supabase.from('tb_user').update({
        'role': role,
        'is_host': isHost,
        'status_user': statusUser,
        'updated_at': DateTime.now().toIso8601String(),
      }).eq('user_id', userId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal memperbarui pengguna: ${e.message}');
    }
  }

  /// Menonaktifkan akun pengguna (Soft Delete / Inactive) demi menjaga relasi database
  Future<void> deactivateUser(int userId) async {
    try {
      await _supabase.from('tb_user').update({
        'status_user': 'Inactive',
        'updated_at': DateTime.now().toIso8601String(),
      }).eq('user_id', userId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal menonaktifkan pengguna: ${e.message}');
    }
  }

  /// Mengaktifkan kembali akun pengguna
  Future<void> activateUser(int userId) async {
    try {
      await _supabase.from('tb_user').update({
        'status_user': 'Active',
        'updated_at': DateTime.now().toIso8601String(),
      }).eq('user_id', userId);
    } on PostgrestException catch (e) {
      throw Exception('Gagal mengaktifkan pengguna: ${e.message}');
    }
  }
}
