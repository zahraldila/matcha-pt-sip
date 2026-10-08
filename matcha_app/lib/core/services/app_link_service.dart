import 'dart:async';
import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import '../../features/auth/presentation/controllers/auth_controller.dart';
import '../../features/session/data/session_service.dart';
import '../../features/session/domain/session_model.dart';
import '../../features/session/presentation/session_detail_page.dart';
import '../../features/recap/presentation/session_match_recap_page.dart';

class AppLinkService {
  static final AppLinkService _instance = AppLinkService._internal();
  factory AppLinkService() => _instance;
  AppLinkService._internal();

  final AppLinks _appLinks = AppLinks();
  final SessionService _sessionService = SessionService();

  StreamSubscription<Uri>? _linkSubscription;
  GlobalKey<NavigatorState>? _navigatorKey;
  GlobalKey<ScaffoldMessengerState>? _messengerKey;
  AuthController? _authController;

  bool _isAppReady = false;
  Uri? _pendingUri;
  bool _isResolving = false;
  String? _lastResolvedToken;
  DateTime? _lastResolvedTime;

  /// Pure parser: Validates scheme, domain, path, and extracts shareToken
  static String? extractShareToken(Uri uri) {
    // 1. Validate scheme: only HTTPS
    if (uri.scheme.toLowerCase() != 'https') {
      return null;
    }

    // 2. Validate canonical host: matcha.siproduktif.com
    final host = uri.host.toLowerCase();
    if (host != 'matcha.siproduktif.com') {
      return null;
    }

    // 3. Validate path segments: /games/share/{token}
    // Note: uri.pathSegments handles leading/trailing slashes
    final segments = uri.pathSegments;
    if (segments.length >= 3 &&
        segments[0] == 'games' &&
        segments[1] == 'share') {
      final token = segments[2].trim();
      if (token.isNotEmpty) {
        return token;
      }
    }

    return null;
  }

  /// Initialize deep link listening
  void configure({
    required GlobalKey<NavigatorState> navigatorKey,
    required GlobalKey<ScaffoldMessengerState> messengerKey,
    required AuthController authController,
  }) {
    _navigatorKey = navigatorKey;
    _messengerKey = messengerKey;
    _authController = authController;
  }

  /// Call this when the app has finished initial bootstrap (e.g. Splash finishes)
  void setAppReady() {
    _isAppReady = true;
    if (_pendingUri != null) {
      final uri = _pendingUri!;
      _pendingUri = null;
      handleUri(uri);
    }
  }

  /// Start listening to cold start and warm start links
  Future<void> init() async {
    // 1. Listen to incoming links (warm start / background)
    _linkSubscription?.cancel();
    _linkSubscription = _appLinks.uriLinkStream.listen(
      (uri) {
        handleUri(uri);
      },
      onError: (err) {
        debugPrint('[AppLinkService] Error in uriLinkStream: $err');
      },
    );

    // 2. Check initial link (cold start)
    try {
      final initialUri = await _appLinks.getInitialLink();
      if (initialUri != null) {
        handleUri(initialUri);
      }
    } catch (e) {
      debugPrint('[AppLinkService] Error reading initial link: $e');
    }
  }

  /// Handle incoming URI safely
  Future<void> handleUri(Uri uri) async {
    final token = extractShareToken(uri);
    if (token == null) {
      // Invalid URL / foreign host / malformed path — ignore safely
      debugPrint('[AppLinkService] Ignored non-matching URL: $uri');
      return;
    }

    // If app is not ready (still bootstrapping / in splash screen), save pending
    if (!_isAppReady) {
      debugPrint('[AppLinkService] App not ready, queueing pending URI: $uri');
      _pendingUri = uri;
      return;
    }

    if (_navigatorKey?.currentState == null) {
      debugPrint('[AppLinkService] Navigator state not ready yet, retrying in 300ms...');
      _pendingUri = uri;
      Future.delayed(const Duration(milliseconds: 300), () {
        if (_pendingUri == uri) {
          _pendingUri = null;
          handleUri(uri);
        }
      });
      return;
    }

    // Debounce duplicate events within 2 seconds
    final now = DateTime.now();
    if (_lastResolvedToken == token &&
        _lastResolvedTime != null &&
        now.difference(_lastResolvedTime!).inSeconds < 2) {
      debugPrint('[AppLinkService] Debounced duplicate token resolution: $token');
      return;
    }

    if (_isResolving) {
      debugPrint('[AppLinkService] Already resolving a token, skipping concurrent trigger');
      return;
    }

    _isResolving = true;
    _lastResolvedToken = token;
    _lastResolvedTime = now;

    try {
      final SessionModel? session = await _sessionService.getSessionByShareToken(token);

      if (session == null) {
        _showFriendlyNotFoundDialog();
        return;
      }

      final statusLower = session.statusSession.trim().toLowerCase();
      final isFinished = statusLower == 'finished' ||
          statusLower == 'completed' ||
          statusLower == 'selesai';

      // Navigate to SessionDetailPage or SessionMatchRecapPage
      final nav = _navigatorKey?.currentState;
      if (nav != null) {
        if (isFinished) {
          // Buka SessionDetailPage di base stack lalu tampilkan SessionMatchRecapPage
          await nav.push(
            MaterialPageRoute(
              builder: (_) => SessionDetailPage(
                sessionId: session.sessionId,
                initialSession: session,
                authController: _authController,
              ),
            ),
          );
          await nav.push(
            MaterialPageRoute(
              builder: (_) => SessionMatchRecapPage(
                sessionId: session.sessionId,
                session: session,
                authController: _authController,
              ),
            ),
          );
        } else {
          await nav.push(
            MaterialPageRoute(
              builder: (_) => SessionDetailPage(
                sessionId: session.sessionId,
                initialSession: session,
                authController: _authController,
              ),
            ),
          );
        }
      }
    } catch (e) {
      debugPrint('[AppLinkService] Error resolving session by share token: $e');
      _showNotification('Gagal memuat sesi mabar dari link.');
    } finally {
      _isResolving = false;
    }
  }

  void _showFriendlyNotFoundDialog() {
    final ctx = _navigatorKey?.currentContext;
    if (ctx != null) {
      showDialog(
        context: ctx,
        builder: (dialogCtx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          backgroundColor: Colors.white,
          title: const Row(
            children: [
              Icon(Icons.info_outline_rounded, color: Color(0xFFD97706), size: 24),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Sesi Tidak Ditemukan',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
              ),
            ],
          ),
          content: const Text(
            'Sesi tidak ditemukan atau tautan sudah kedaluwarsa. Silakan periksa kembali tautan yang dibagikan atau temukan jadwal sesi mabar lainnya di Beranda.',
            style: TextStyle(fontSize: 13, color: Color(0xFF475569), height: 1.4),
          ),
          actions: [
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF063B00),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              ),
              onPressed: () => Navigator.pop(dialogCtx),
              child: const Text('Mengerti', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      );
    } else {
      _showNotification('Sesi tidak ditemukan atau tautan sudah kedaluwarsa.');
    }
  }

  void _showNotification(String message) {
    final messenger = _messengerKey?.currentState;
    if (messenger != null) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(message),
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 4),
        ),
      );
    }
  }

  void dispose() {
    _linkSubscription?.cancel();
    _linkSubscription = null;
  }
}
