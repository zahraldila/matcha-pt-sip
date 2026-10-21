import 'dart:async';
import 'dart:io';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import '../../features/court/data/venue_service.dart';
import '../../features/session/data/session_service.dart';

enum NetworkStatus {
  online,
  offline,
  recovered,
}

class NetworkStatusService {
  static final NetworkStatusService _instance = NetworkStatusService._internal();
  factory NetworkStatusService() => _instance;
  NetworkStatusService._internal();

  final Connectivity _connectivity = Connectivity();
  StreamSubscription<List<ConnectivityResult>>? _subscription;
  Timer? _heartbeatTimer;
  Timer? _recoveredTimer;

  final ValueNotifier<NetworkStatus> status =
      ValueNotifier<NetworkStatus>(NetworkStatus.online);

  /// Event notifier bertambah setiap kali koneksi pulih kembali
  final ValueNotifier<int> connectionRestoredNotifier = ValueNotifier<int>(0);

  bool get isOffline => status.value == NetworkStatus.offline;
  bool get isOnline =>
      status.value == NetworkStatus.online || status.value == NetworkStatus.recovered;

  void init() {
    _subscription?.cancel();
    _subscription = _connectivity.onConnectivityChanged.listen(_handleConnectivityChange);

    // Initial check segera saat aplikasi dibuka
    checkConnection();

    // Heartbeat check setiap 20 detik untuk mengantisipasi jaringan mati diam-diam
    _heartbeatTimer?.cancel();
    _heartbeatTimer = Timer.periodic(const Duration(seconds: 20), (_) {
      checkConnection();
    });
  }

  void dispose() {
    _subscription?.cancel();
    _heartbeatTimer?.cancel();
    _recoveredTimer?.cancel();
  }

  Future<void> _handleConnectivityChange(List<ConnectivityResult> results) async {
    final hasInterface = results.any((r) => r != ConnectivityResult.none);
    if (!hasInterface) {
      _setOffline();
    } else {
      await checkConnection();
    }
  }

  Future<bool> checkConnection() async {
    bool hasInternet = false;
    try {
      final results = await _connectivity.checkConnectivity();
      final hasInterface = results.any((r) => r != ConnectivityResult.none);
      if (!hasInterface) {
        _setOffline();
        return false;
      }

      final lookup = await InternetAddress.lookup('google.com')
          .timeout(const Duration(seconds: 3));
      hasInternet = lookup.isNotEmpty && lookup[0].rawAddress.isNotEmpty;
    } catch (_) {
      hasInternet = false;
    }

    if (hasInternet) {
      _setOnline();
    } else {
      _setOffline();
    }
    return hasInternet;
  }

  void _setOffline() {
    _recoveredTimer?.cancel();
    if (status.value != NetworkStatus.offline) {
      status.value = NetworkStatus.offline;
    }
  }

  void _setOnline() {
    if (status.value == NetworkStatus.offline) {
      // Transisi dari offline -> pulih (recovered)
      status.value = NetworkStatus.recovered;
      connectionRestoredNotifier.value++;

      // Otomatis refresh data sesi & venue global
      SessionService.notifySessionsChanged();
      VenueService.notifyVenuesChanged();

      _recoveredTimer?.cancel();
      _recoveredTimer = Timer(const Duration(milliseconds: 3200), () {
        if (status.value == NetworkStatus.recovered) {
          status.value = NetworkStatus.online;
        }
      });
    }
  }
}
