import 'package:supabase_flutter/supabase_flutter.dart';
import '../../domain/models/player_model.dart';

class PlayerRemoteDataSource {
  final SupabaseClient _supabase;

  PlayerRemoteDataSource({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Mengambil semua daftar pemain aktif & nonaktif dari Supabase
  Future<List<PlayerModel>> getPlayers() async {
    try {
      final response = await _supabase
          .from('tb_player')
          .select('''
            player_id,
            nama_player,
            nik,
            status_member,
            created_at
          ''')
          .order('player_id', ascending: false);

      final List<dynamic> data = response as List<dynamic>;
      return data.map((json) => PlayerModel.fromJson(json)).toList();
    } catch (e) {
      // Fallback data jika tabel belum ada isi
      return [];
    }
  }

  /// Menambahkan member/pemain baru ke tb_player
  Future<PlayerModel> addPlayer({
    required String namaPlayer,
    required String nik,
    String statusMember = 'active',
  }) async {
    try {
      final response = await _supabase
          .from('tb_player')
          .insert({
            'nama_player': namaPlayer.trim(),
            'nik': nik.trim(),
            'status_member': statusMember,
          })
          .select()
          .single();

      return PlayerModel.fromJson(response);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal menambah pemain: ${e.message}');
      }
      rethrow;
    }
  }

  /// Mengubah data profil pemain
  Future<PlayerModel> updatePlayer({
    required int playerId,
    required String namaPlayer,
    required String nik,
    required String statusMember,
  }) async {
    try {
      final response = await _supabase
          .from('tb_player')
          .update({
            'nama_player': namaPlayer.trim(),
            'nik': nik.trim(),
            'status_member': statusMember,
          })
          .eq('player_id', playerId)
          .select()
          .single();

      return PlayerModel.fromJson(response);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal mengubah data pemain: ${e.message}');
      }
      rethrow;
    }
  }

  /// Mengubah status aktif / nonaktif member
  Future<void> updatePlayerStatus(int playerId, String newStatus) async {
    try {
      await _supabase
          .from('tb_player')
          .update({'status_member': newStatus})
          .eq('player_id', playerId);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Gagal mengubah status: ${e.message}');
      }
      rethrow;
    }
  }
}
