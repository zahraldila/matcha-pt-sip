import 'package:supabase_flutter/supabase_flutter.dart';
import '../../domain/models/user_model.dart';

class AuthRemoteDataSource {
  final SupabaseClient _supabase;

  AuthRemoteDataSource({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Melakukan validasi login pengguna berdasarkan email dan password ke tb_user
  Future<UserModel> login({
    required String email,
    required String password,
  }) async {
    try {
      final response = await _supabase
          .from('tb_user')
          .select()
          .eq('email', email.trim())
          .eq('password', password.trim())
          .maybeSingle();

      if (response == null) {
        throw Exception('Email atau password yang Anda masukkan salah.');
      }

      final user = UserModel.fromJson(response);

      if (user.statusUser.toLowerCase() == 'inactive') {
        throw Exception('Akun Anda saat ini dinonaktifkan.');
      }

      return user;
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Terjadi gangguan koneksi database: ${e.message}');
      }
      rethrow;
    }
  }

  /// Mengambil data user berdasarkan user_id
  Future<UserModel?> getUserById(int userId) async {
    try {
      final response = await _supabase
          .from('tb_user')
          .select()
          .eq('user_id', userId)
          .maybeSingle();

      if (response == null) return null;
      return UserModel.fromJson(response);
    } catch (_) {
      return null;
    }
  }
}
