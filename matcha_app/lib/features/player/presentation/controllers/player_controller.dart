import 'package:flutter/material.dart';
import '../../domain/models/player_model.dart';
import '../../data/datasource/player_remote_datasource.dart';

class PlayerController extends ChangeNotifier {
  final PlayerRemoteDataSource _dataSource;

  PlayerController({PlayerRemoteDataSource? dataSource})
      : _dataSource = dataSource ?? PlayerRemoteDataSource();

  List<PlayerModel> _players = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<PlayerModel> get players => _players;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchPlayers() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final result = await _dataSource.getPlayers();
      if (result.isNotEmpty) {
        _players = result;
      } else {
        // Mock fallback default jika database awal masih kosong
        _players = _defaultMockPlayers;
      }
    } catch (e) {
      _errorMessage = e.toString();
      _players = _defaultMockPlayers;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> addPlayer({
    required String namaPlayer,
    required String nik,
    String statusMember = 'active',
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final newPlayer = await _dataSource.addPlayer(
        namaPlayer: namaPlayer,
        nik: nik,
        statusMember: statusMember,
      );
      _players.insert(0, newPlayer);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      // Offline fallback: tambahkan ke memori lokal
      final offlinePlayer = PlayerModel(
        playerId: DateTime.now().millisecondsSinceEpoch,
        namaPlayer: namaPlayer,
        nik: nik,
        statusMember: statusMember,
      );
      _players.insert(0, offlinePlayer);
      _isLoading = false;
      notifyListeners();
      return true;
    }
  }

  Future<bool> updatePlayerStatus(int playerId, String newStatus) async {
    try {
      await _dataSource.updatePlayerStatus(playerId, newStatus);
      final index = _players.indexWhere((p) => p.playerId == playerId);
      if (index != -1) {
        _players[index] = _players[index].copyWith(statusMember: newStatus);
        notifyListeners();
      }
      return true;
    } catch (e) {
      final index = _players.indexWhere((p) => p.playerId == playerId);
      if (index != -1) {
        _players[index] = _players[index].copyWith(statusMember: newStatus);
        notifyListeners();
      }
      return true;
    }
  }

  static final List<PlayerModel> _defaultMockPlayers = [
    PlayerModel(playerId: 1, namaPlayer: 'Aldi', nik: 'NIK-001', communityName: 'Sportif Tennis Club', totalMatches: 14, totalWins: 9, totalLosses: 5),
    PlayerModel(playerId: 2, namaPlayer: 'Budi', nik: 'NIK-002', communityName: 'Smansa Tennis', totalMatches: 12, totalWins: 8, totalLosses: 4),
    PlayerModel(playerId: 3, namaPlayer: 'Caca', nik: 'NIK-003', communityName: 'Individual', totalMatches: 10, totalWins: 6, totalLosses: 4),
    PlayerModel(playerId: 4, namaPlayer: 'Dina', nik: 'NIK-004', communityName: 'Individual', totalMatches: 10, totalWins: 5, totalLosses: 5),
    PlayerModel(playerId: 5, namaPlayer: 'Eka', nik: 'NIK-005', communityName: 'Viborazer Padel', totalMatches: 8, totalWins: 5, totalLosses: 3),
    PlayerModel(playerId: 6, namaPlayer: 'Fajar', nik: 'NIK-006', communityName: 'Viborazer Padel', totalMatches: 8, totalWins: 4, totalLosses: 4),
    PlayerModel(playerId: 7, namaPlayer: 'Gilang', nik: 'NIK-007', communityName: 'Sportif Tennis Club', totalMatches: 6, totalWins: 3, totalLosses: 3),
    PlayerModel(playerId: 8, namaPlayer: 'Hadi', nik: 'NIK-008', communityName: 'Smansa Tennis', totalMatches: 6, totalWins: 2, totalLosses: 4),
  ];
}
