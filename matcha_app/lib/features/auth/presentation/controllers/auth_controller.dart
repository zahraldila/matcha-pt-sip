import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
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

  /// Memeriksa apakah ada sesi login yang tersimpan di memori lokal HP (Auto-Login)
  Future<bool> checkSavedSession() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedUserId = prefs.getInt(_userSessionKey);

      if (savedUserId != null) {
        final user = await _authDataSource.getUserById(savedUserId);
        if (user != null && user.statusUser.toLowerCase() != 'inactive') {
          _currentUser = user;
          notifyListeners();
          return true;
        } else {
          await prefs.remove(_userSessionKey);
        }
      }
    } catch (_) {
      // Abaikan jika ada kendala pembacaan local storage / database awal
    }
    return false;
  }

  /// Melakukan login dan menyimpan sesi ke local storage
  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final user = await _authDataSource.login(
        email: email,
        password: password,
      );
      _currentUser = user;

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

  /// Logout dan menghapus session dari local storage
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
}
