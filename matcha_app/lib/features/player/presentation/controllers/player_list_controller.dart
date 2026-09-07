import 'package:flutter/foundation.dart';
import '../../data/datasource/player_remote_data_source.dart';
import '../../domain/models/player_model.dart';

class PlayerListController extends ChangeNotifier {
  final PlayerRemoteDataSource _dataSource;
  final bool isHost;

  List<PlayerModel> _players = [];
  bool _isLoading = false;
  String? _errorMessage;
  String _filterStatus = 'Semua';

  PlayerListController({
    required this.isHost,
    PlayerRemoteDataSource? dataSource,
  }) : _dataSource = dataSource ?? PlayerRemoteDataSource();

  // Getters
  List<PlayerModel> get players => _players;
  List<PlayerModel> get filteredPlayers {
    if (_filterStatus == 'Semua' || _filterStatus == 'Active') {
      return _players;
    }
    // Filter dapat di-extend nanti sesuai kebutuhan
    return _players;
  }

  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  String get filterStatus => _filterStatus;
  bool get canModifyPlayers => isHost;

  /// Load active players dari database
  Future<void> loadActivePlayers() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _players = await _dataSource.getActivePlayerList();
      _errorMessage = null;
    } catch (e) {
      _errorMessage = e.toString();
      _players = [];
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Update filter status
  void setFilterStatus(String status) {
    _filterStatus = status;
    notifyListeners();
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
