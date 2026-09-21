import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../../core/data/mock_data_service.dart';
import '../../data/datasource/auth_remote_data_source.dart';
import '../../domain/models/user_model.dart';

class AuthController extends ChangeNotifier {
  static const String _userSessionKey = 'saved_user_id';

  final AuthRemoteDataSource _authDataSource;

  AuthController({AuthRemoteDataSource? authDataSource})
      : _authDataSource = authDataSource ?? AuthRemoteDataSource();

  UserModel? _currentUser;
  bool _isLoading = false;
  String? _errorMessage;

  UserModel? get currentUser => _currentUser;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get isAuthenticated => _currentUser != null;
  bool get isHost => _currentUser?.isHost ?? false;

  /// Memeriksa apakah ada sesi login tersimpan di SharedPreferences (Auto-Login)
  Future<bool> checkSavedSession() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedUserId = prefs.getInt(_userSessionKey);

      if (savedUserId != null) {
        final user = await _authDataSource.getUserById(savedUserId);
        if (user != null && user.statusUser.toLowerCase() != 'inactive') {
          _currentUser = user;
          _syncToMockDataService(user);
          notifyListeners();
          return true;
        } else {
          await prefs.remove(_userSessionKey);
        }
      }
    } catch (_) {
      // Abaikan jika offline
    }
    return false;
  }

  /// Melakukan login ke Supabase
  Future<bool> login(String loginId, String password) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final user = await _authDataSource.login(
        loginId: loginId,
        password: password,
      );
      _currentUser = user;
      _syncToMockDataService(user);

      // Simpan session ID ke SharedPreferences
      try {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setInt(_userSessionKey, user.userId);
      } catch (_) {}

      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString().replaceFirst('Exception: ', '');
      notifyListeners();
      return false;
    }
  }

  /// Melakukan registrasi pengguna baru ke Supabase
  Future<bool> register({
    required String nama,
    required String email,
    required String noHp,
    required String password,
    required String gender,
    required int usia,
    required String level,
    int? communityId,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      await _authDataSource.register(
        nama: nama,
        email: email,
        noHp: noHp,
        password: password,
        gender: gender,
        usia: usia,
        level: level,
        communityId: communityId,
      );

      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _errorMessage = e.toString().replaceFirst('Exception: ', '');
      notifyListeners();
      return false;
    }
  }

  /// Toggle Host Mode dan simpan ke Supabase
  Future<void> toggleHost() async {
    if (_currentUser == null) return;
    final newHostStatus = !_currentUser!.isHost;
    _currentUser = _currentUser!.copyWith(isHost: newHostStatus);
    MockDataService().toggleHostMode();
    notifyListeners();

    await _authDataSource.updateHostStatus(_currentUser!.userId, newHostStatus);
  }

  /// Logout dan bersihkan session
  Future<void> logout() async {
    _currentUser = null;
    _errorMessage = null;

    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_userSessionKey);
    } catch (_) {}

    notifyListeners();
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  void _syncToMockDataService(UserModel user) {
    notifyListeners();
  }
}
