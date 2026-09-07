import 'package:flutter/material.dart';
import '../../data/datasource/auth_remote_data_source.dart';
import '../../domain/models/user_model.dart';

class AuthController extends ChangeNotifier {
  final AuthRemoteDataSource _authDataSource;

  AuthController({AuthRemoteDataSource? authDataSource})
      : _authDataSource = authDataSource ?? AuthRemoteDataSource();

  UserModel? _currentUser;
  bool _isLoading = false;
  String? _errorMessage;
  bool _isChangingPassword = false;
  String? _passwordError;

  UserModel? get currentUser => _currentUser;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get isAuthenticated => _currentUser != null;
  bool get isHost => _currentUser?.isHost ?? false;
  bool get isChangingPassword => _isChangingPassword;
  String? get passwordError => _passwordError;

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

  Future<bool> changePassword({
    required String oldPassword,
    required String newPassword,
  }) async {
    if (_currentUser == null) return false;
    _isChangingPassword = true;
    _passwordError = null;
    notifyListeners();

    try {
      await _authDataSource.changePassword(
        userId: _currentUser!.userId,
        oldPassword: oldPassword,
        newPassword: newPassword,
      );
      _isChangingPassword = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isChangingPassword = false;
      _passwordError = e.toString().replaceFirst('Exception: ', '');
      notifyListeners();
      return false;
    }
  }

  void logout() {
    _currentUser = null;
    _errorMessage = null;
    notifyListeners();
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  void clearPasswordError() {
    _passwordError = null;
    notifyListeners();
  }
}
