import 'dart:async';
import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import '../../features/auth/presentation/controllers/auth_controller.dart';
import '../../features/session/data/session_service.dart';
import '../../features/session/domain/session_model.dart';
import '../../features/session/presentation/session_detail_page.dart';
import '../../features/recap/presentation/session_match_recap_page.dart';
import '../../features/recap/presentation/match_recap_page.dart';

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
    final scheme = uri.scheme.toLowerCase();
    final host = uri.host.toLowerCase();

    if (scheme == 'https' || scheme == 'http') {
      if (host != 'matcha.siproduktif.com') return null;
      final segments = uri.pathSegments;
      if (segments.length >= 3 && segments[0] == 'games' && segments[1] == 'share') {
        final token = segments[2].trim();
        if (token.isNotEmpty) return token;
      }
    } else if (scheme == 'matcha') {
      final segments = uri.pathSegments;
      if (segments.length >= 2 && segments[0] == 'share') {
        return segments[1].trim();
      }
      if (host == 'games' && segments.length >= 2 && segments[0] == 'share') {
        return segments[1].trim();
      }
    }

    return null;
  }

  /// Pure parser: Validates scheme, domain, path, and extracts sessionId from /scoring/recap/{id}
  static int? extractRecapSessionId(Uri uri) {
    final scheme = uri.scheme.toLowerCase();
    final host = uri.host.toLowerCase();

    if (scheme == 'https' || scheme == 'http') {
      if (host != 'matcha.siproduktif.com') return null;
      final segments = uri.pathSegments;
      if (segments.length >= 3 && segments[0] == 'scoring' && segments[1] == 'recap') {
        return int.tryParse(segments[2].trim());
      }
    } else if (scheme == 'matcha') {
      final segments = uri.pathSegments;
      if (host == 'scoring' && segments.isNotEmpty && segments[0] == 'recap' && segments.length >= 2) {
        return int.tryParse(segments[1].trim());
      }
    }

    return null;
  }

  /// Pure parser: Validates scheme, domain, path, and extracts userId / playerId from /player/recap/{id} or /recap/user/{id}
  static int? extractPlayerRecapUserId(Uri uri) {
    final scheme = uri.scheme.toLowerCase();
    final host = uri.host.toLowerCase();

    if (scheme == 'https' || scheme == 'http') {
      if (host != 'matcha.siproduktif.com') {
        return null;
      }

      final segments = uri.pathSegments;
      const validPrefixes = {'player', 'players', 'profile', 'profiles', 'user', 'users'};

      // Format 1: /{player|players|profile|profiles|user|users}/recap/{id}
      if (segments.length >= 3 &&
          validPrefixes.contains(segments[0]) &&
          segments[1] == 'recap') {
        return int.tryParse(segments[2].trim());
      }
      // Format 2: /{player|players|profile|profiles|user|users}/recap (without id)
      if (segments.length == 2 &&
          validPrefixes.contains(segments[0]) &&
          segments[1] == 'recap') {
        return -1;
      }
      // Format 3: /recap/{user|player|profile}/{id}
      if (segments.length >= 3 &&
          segments[0] == 'recap' &&
          (segments[1] == 'user' || segments[1] == 'player' || segments[1] == 'profile')) {
        return int.tryParse(segments[2].trim());
      }
    } else if (scheme == 'matcha') {
      final segments = uri.pathSegments;
      const validPrefixes = {'player', 'players', 'profile', 'profiles', 'user', 'users'};

      // matcha://{player|profile}/recap/{id}
      if (validPrefixes.contains(host) && segments.isNotEmpty && segments[0] == 'recap') {
        if (segments.length >= 2) {
          return int.tryParse(segments[1].trim());
        }
        return -1;
      }
      // matcha://recap/{user|player}/{id}
      if (host == 'recap' && segments.isNotEmpty && (segments[0] == 'user' || segments[0] == 'player' || segments[0] == 'profile') && segments.length >= 2) {
        return int.tryParse(segments[1].trim());
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
    final recapSessionId = extractRecapSessionId(uri);
    final playerRecapUserId = extractPlayerRecapUserId(uri);

    if (token == null && recapSessionId == null && playerRecapUserId == null) {
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

    // Handle Player Career Recap Deep Link
    if (playerRecapUserId != null) {
      final nav = _navigatorKey?.currentState;
      if (nav != null) {
        final targetId = playerRecapUserId == -1 ? null : playerRecapUserId;
        nav.push(
          MaterialPageRoute(
            builder: (_) => MatchRecapPage(
              authController: _authController,
              targetUserId: targetId,
              initialTab: 'career',
            ),
          ),
        );
      }
      return;
    }

    final resolveKey = token ?? 'recap_$recapSessionId';

    // Debounce duplicate events within 2 seconds
    final now = DateTime.now();
    if (_lastResolvedToken == resolveKey &&
        _lastResolvedTime != null &&
        now.difference(_lastResolvedTime!).inSeconds < 2) {
      debugPrint('[AppLinkService] Debounced duplicate token resolution: $resolveKey');
      return;
    }

    if (_isResolving) {
      debugPrint('[AppLinkService] Already resolving a token, skipping concurrent trigger');
      return;
    }

    _isResolving = true;
    _lastResolvedToken = resolveKey;
    _lastResolvedTime = now;

    try {
      final SessionModel? session = token != null
          ? await _sessionService.getSessionByShareToken(token)
          : await _sessionService.getSessionDetail(recapSessionId!);

      if (session == null) {
        _showFriendlyNotFoundDialog();
        return;
      }

      final statusLower = session.statusSession.trim().toLowerCase();
      final isFinished = statusLower == 'finished' ||
          statusLower == 'completed' ||
          statusLower == 'selesai' ||
          recapSessionId != null;

      // Navigate to SessionDetailPage or SessionMatchRecapPage
      final nav = _navigatorKey?.currentState;
      if (nav != null) {
        if (isFinished) {
          // Buka SessionDetailPage di base stack lalu tampilkan SessionMatchRecapPage di depan layar
          nav.push(
            MaterialPageRoute(
              builder: (_) => SessionDetailPage(
                sessionId: session.sessionId,
                initialSession: session,
                authController: _authController,
              ),
            ),
          );
          nav.push(
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
