import 'package:flutter/material.dart';

import '../../data/datasource/session_remote_data_source.dart';
import '../../../auth/domain/models/user_model.dart';

class SessionController extends ChangeNotifier {
  final SessionRemoteDataSource _dataSource;

  SessionController({
    SessionRemoteDataSource? dataSource,
  }) : _dataSource = dataSource ?? SessionRemoteDataSource();

  // ============================================================
  // STATE
  // ============================================================

  bool _isLoading = false;
  bool _isCreatingSession = false;

  String? _errorMessage;

  List<Map<String, dynamic>> _sports = [];
  List<Map<String, dynamic>> _players = [];
  List<Map<String, dynamic>> _courts = [];
  List<Map<String, dynamic>> _sessions = [];

  // ============================================================
  // GETTERS
  // ============================================================

  bool get isLoading => _isLoading;
  bool get isCreatingSession => _isCreatingSession;

  String? get errorMessage => _errorMessage;

  List<Map<String, dynamic>> get sports => _sports;
  List<Map<String, dynamic>> get players => _players;
  List<Map<String, dynamic>> get courts => _courts;
  List<Map<String, dynamic>> get sessions => _sessions;

  // ============================================================
  // ERROR
  // ============================================================

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  // ============================================================
  // FETCH MASTER DATA
  // ============================================================

  Future<void> loadMasterData({
    required int sportId,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _sports = await _dataSource.getActiveSports();

      _players = await _dataSource.getActivePlayers();

      _courts = await _dataSource.getAvailableCourts(
        sportId: sportId,
      );

      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString().replaceFirst(
            'Exception: ',
            '',
          );
      notifyListeners();
    }
  }

  // ============================================================
  // FETCH SPORTS
  // ============================================================

  Future<void> loadSports() async {
    _errorMessage = null;

    try {
      _sports = await _dataSource.getActiveSports();
      notifyListeners();
    } catch (e) {
      _errorMessage = e.toString().replaceFirst(
            'Exception: ',
            '',
          );
      notifyListeners();
    }
  }

  // ============================================================
  // FETCH PLAYERS
  // ============================================================

  Future<void> loadPlayers() async {
    _errorMessage = null;

    try {
      _players = await _dataSource.getActivePlayers();
      notifyListeners();
    } catch (e) {
      _errorMessage = e.toString().replaceFirst(
            'Exception: ',
            '',
          );
      notifyListeners();
    }
  }

  // ============================================================
  // FETCH COURTS
  // ============================================================

  Future<void> loadCourts({
    required int sportId,
  }) async {
    _errorMessage = null;

    try {
      _courts = await _dataSource.getAvailableCourts(
        sportId: sportId,
      );
      notifyListeners();
    } catch (e) {
      _errorMessage = e.toString().replaceFirst(
            'Exception: ',
            '',
          );
      notifyListeners();
    }
  }

  // ============================================================
  // CREATE SESSION
  // ============================================================

  Future<int?> createSession({
    required UserModel currentUser,
    required int sportId,
    required String namaSession,
    required String drawingMethod,
    required String statusSession,
    required DateTime waktuSession,
    required String jenisPermainan,
    required List<int> playerIds,
    required List<int> courtIds,
  }) async {
    _isCreatingSession = true;
    _errorMessage = null;
    notifyListeners();

    try {
      // ----------------------------------------------------------
      // 1. Validasi user
      // ----------------------------------------------------------

      if (!currentUser.isHost) {
        throw Exception(
          'Hanya Host yang dapat membuat sesi permainan.',
        );
      }

      // ----------------------------------------------------------
      // 2. Validasi input
      // ----------------------------------------------------------

      if (namaSession.trim().isEmpty) {
        throw Exception(
          'Nama sesi harus diisi.',
        );
      }

      if (playerIds.isEmpty) {
        throw Exception(
          'Pilih minimal satu pemain.',
        );
      }

      if (courtIds.isEmpty) {
        throw Exception(
          'Pilih minimal satu lapangan.',
        );
      }

      if (jenisPermainan != 'Single' &&
          jenisPermainan != 'Double') {
        throw Exception(
          'Jenis permainan tidak valid.',
        );
      }

      // ----------------------------------------------------------
      // 3. Buat session
      // ----------------------------------------------------------

      final sessionId = await _dataSource.createSession(
        userId: currentUser.userId,
        sportId: sportId,
        namaSession: namaSession.trim(),
        drawingMethod: drawingMethod,
        statusSession: statusSession,
        waktuSession: waktuSession,
        jenisPermainan: jenisPermainan,
      );

      // ----------------------------------------------------------
      // 4. Simpan pemain session
      // ----------------------------------------------------------

      await _dataSource.addSessionPlayers(
        sessionId: sessionId,
        playerIds: playerIds,
      );

      // ----------------------------------------------------------
      // 5. Simpan court session
      // ----------------------------------------------------------

      await _dataSource.addSessionCourts(
        sessionId: sessionId,
        courtIds: courtIds,
      );

      _isCreatingSession = false;
      notifyListeners();

      return sessionId;
    } catch (e) {
      _isCreatingSession = false;

      _errorMessage = e.toString().replaceFirst(
            'Exception: ',
            '',
          );

      notifyListeners();

      return null;
    }
  }

  // ============================================================
  // FETCH SESSION LIST
  // ============================================================

  Future<void> loadSessions({
    int? userId,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _sessions = await _dataSource.getSessions(
        userId: userId,
      );

      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;

      _errorMessage = e.toString().replaceFirst(
            'Exception: ',
            '',
          );

      notifyListeners();
    }
  }
}