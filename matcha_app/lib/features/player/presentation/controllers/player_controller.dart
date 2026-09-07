import 'package:flutter/foundation.dart';
import '../../data/datasource/player_remote_data_source.dart';
import '../../domain/models/player_model.dart';

class PlayerController extends ChangeNotifier {
  final PlayerRemoteDataSource _dataSource;

  PlayerModel? _player;
  bool _isLoading = false;
  String? _errorMessage;

  PlayerController({PlayerRemoteDataSource? dataSource})
      : _dataSource = dataSource ?? PlayerRemoteDataSource();

  // Getters
  PlayerModel? get player => _player;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  int get totalMatches => _player?.totalMatches ?? 0;
  int get wins => _player?.wins ?? 0;
  int get losses => _player?.losses ?? 0;
  double get winRate => _player?.winRate ?? 0.0;

  /// Load player stats berdasarkan playerId
  Future<void> loadPlayerStats(int playerId) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _player = await _dataSource.getPlayerById(playerId);
      _errorMessage = null;
    } catch (e) {
      _errorMessage = e.toString();
      _player = null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Clear error message
  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  @override
  void dispose() {
    super.dispose();
  }
}
