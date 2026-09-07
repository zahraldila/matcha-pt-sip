import 'package:supabase_flutter/supabase_flutter.dart';
import '../../domain/models/player_model.dart';

class PlayerRemoteDataSource {
  final SupabaseClient _supabase;

  PlayerRemoteDataSource({SupabaseClient? supabaseClient})
      : _supabase = supabaseClient ?? Supabase.instance.client;

  /// Mengambil data player berdasarkan player_id
  /// Termasuk agregasi stats dari tb_playing_history
  Future<PlayerModel?> getPlayerById(int playerId) async {
    try {
      // Get player data from tb_player
      final playerResponse = await _supabase
          .from('tb_player')
          .select()
          .eq('player_id', playerId)
          .maybeSingle();

      if (playerResponse == null) return null;

      // Get stats from tb_playing_history
      final stats = await _getPlayerStats(playerId);

      // Combine player data with stats
      final playerData = {...playerResponse, ...stats};

      return PlayerModel.fromJson(playerData);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Error fetching player data: ${e.message}');
      }
      rethrow;
    }
  }

  /// Mengambil player berdasarkan nama_player
  /// Digunakan untuk link user dengan player mereka
  /// NOTE: Asumsi bahwa user profile dapat di-link dengan player berdasarkan nama.
  /// Jika ada relasi user_id di tb_player, method ini dapat disesuaikan.
  Future<PlayerModel?> getPlayerByName(String playerName) async {
    try {
      final playerResponse = await _supabase
          .from('tb_player')
          .select()
          .eq('nama_player', playerName)
          .maybeSingle();

      if (playerResponse == null) return null;

      final playerId = playerResponse['player_id'] as int;
      final stats = await _getPlayerStats(playerId);
      final combinedData = {...playerResponse, ...stats};

      return PlayerModel.fromJson(combinedData);
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Error fetching player by name: ${e.message}');
      }
      return null;
    }
  }

  /// Mengambil daftar player dengan status_member = 'active'
  /// Termasuk stats untuk setiap player
  Future<List<PlayerModel>> getActivePlayerList() async {
    try {
      // Get all active players from tb_player
      final playersResponse = await _supabase
          .from('tb_player')
          .select()
          .eq('status_member', 'active')
          .order('nama_player', ascending: true);

      if (playersResponse.isEmpty) return [];

      // Get stats for each player
      final playersList = <PlayerModel>[];
      for (final playerData in playersResponse as List) {
        final playerId = playerData['player_id'] as int;
        final stats = await _getPlayerStats(playerId);
        final combinedData = {...playerData as Map<String, dynamic>, ...stats};
        playersList.add(PlayerModel.fromJson(combinedData));
      }

      return playersList;
    } catch (e) {
      if (e is PostgrestException) {
        throw Exception('Error fetching player list: ${e.message}');
      }
      rethrow;
    }
  }

  /// Get stats untuk player dari tb_playing_history
  /// Returns map dengan: total_matches, wins, losses
  Future<Map<String, dynamic>> _getPlayerStats(int playerId) async {
    try {
      final historyResponse = await _supabase
          .from('tb_playing_history')
          .select()
          .eq('player_id', playerId);

      if (historyResponse.isEmpty) {
        return {
          'total_matches': 0,
          'wins': 0,
          'losses': 0,
        };
      }

      int wins = 0;
      int losses = 0;

      for (final history in historyResponse as List) {
        final isWin = history['is_win'] as bool? ?? false;
        if (isWin) {
          wins++;
        } else {
          losses++;
        }
      }

      final totalMatches = wins + losses;

      return {
        'total_matches': totalMatches,
        'wins': wins,
        'losses': losses,
      };
    } catch (e) {
      // Return default stats if query fails
      return {
        'total_matches': 0,
        'wins': 0,
        'losses': 0,
      };
    }
  }
}
