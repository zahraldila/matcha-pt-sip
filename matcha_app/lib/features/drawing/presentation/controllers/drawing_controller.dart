import 'package:flutter/material.dart';
import '../../domain/models/drawing_round_model.dart';
import '../../domain/services/drawing_engine_service.dart';
import '../../data/datasource/drawing_remote_datasource.dart';

class DrawingController extends ChangeNotifier {
  final DrawingEngineService _engineService;
  final DrawingRemoteDataSource _remoteDataSource;

  DrawingController({
    DrawingEngineService? engineService,
    DrawingRemoteDataSource? remoteDataSource,
  })  : _engineService = engineService ?? DrawingEngineService(),
        _remoteDataSource = remoteDataSource ?? DrawingRemoteDataSource();

  DrawingRoundModel? _currentRound;
  bool _isLoading = false;
  String? _errorMessage;

  DrawingRoundModel? get currentRound => _currentRound;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  /// Generate susunan drawing ronde pertama atau ronde tertentu
  void generateDrawing({
    required String sessionName,
    required String sportName,
    required String drawingMethod,
    String format = 'Doubles',
    required List<String> playerNames,
    List<int>? playerIds,
    required List<String> courtNames,
    List<int>? courtIds,
    int roundNumber = 1,
    List<Map<String, dynamic>>? playerScores,
  }) {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      if (drawingMethod.toLowerCase() == 'mexicano' && playerScores != null && playerScores.isNotEmpty) {
        _currentRound = _engineService.generateMexicanoRound(
          sessionName: sessionName,
          sportName: sportName,
          playerScores: playerScores,
          courtNames: courtNames,
          courtIds: courtIds,
          roundNumber: roundNumber,
          format: format,
        );
      } else {
        _currentRound = _engineService.generateAmericanoRound(
          sessionName: sessionName,
          sportName: sportName,
          playerNames: playerNames,
          playerIds: playerIds,
          courtNames: courtNames,
          courtIds: courtIds,
          roundNumber: roundNumber,
          format: format,
        );
      }
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Acak ulang susunan pertandingan (Re-Draw)
  void reShuffleDrawing({
    required List<String> playerNames,
    List<int>? playerIds,
    required List<String> courtNames,
    List<int>? courtIds,
  }) {
    if (_currentRound == null) return;

    _isLoading = true;
    notifyListeners();

    try {
      _currentRound = _engineService.generateAmericanoRound(
        sessionName: _currentRound!.sessionName,
        sportName: _currentRound!.sportName,
        playerNames: playerNames,
        playerIds: playerIds,
        courtNames: courtNames,
        courtIds: courtIds,
        roundNumber: _currentRound!.roundNumber,
        format: _currentRound!.format,
        isReshuffle: true,
      );
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Menyimpan hasil ronde yang sudah disepakati ke Supabase
  Future<bool> saveDrawingToSupabase() async {
    if (_currentRound == null) return false;

    _isLoading = true;
    notifyListeners();

    try {
      final saved = await _remoteDataSource.saveDrawingRound(_currentRound!);
      _currentRound = saved;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _errorMessage = e.toString();
      _isLoading = false;
      notifyListeners();
      return true;
    }
  }
}
