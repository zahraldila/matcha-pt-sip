import 'package:flutter/material.dart';
import '../services/network_status_service.dart';

class TopNetworkBanner extends StatefulWidget {
  const TopNetworkBanner({super.key});

  @override
  State<TopNetworkBanner> createState() => _TopNetworkBannerState();
}

class _TopNetworkBannerState extends State<TopNetworkBanner> {
  final NetworkStatusService _networkService = NetworkStatusService();
  bool _isChecking = false;

  Future<void> _handleManualRetry() async {
    if (_isChecking) return;
    setState(() => _isChecking = true);
    await _networkService.checkConnection();
    if (mounted) {
      setState(() => _isChecking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<NetworkStatus>(
      valueListenable: _networkService.status,
      builder: (context, status, _) {
        final isVisible = status != NetworkStatus.online;

        return AnimatedSize(
          duration: const Duration(milliseconds: 280),
          curve: Curves.easeInOutCubic,
          alignment: Alignment.topCenter,
          child: !isVisible
              ? const SizedBox.shrink()
              : Container(
                  width: double.infinity,
                  margin: const EdgeInsets.fromLTRB(14, 8, 14, 6),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: status == NetworkStatus.offline
                        ? const Color(0xFFFFF1F2) // Soft Rose
                        : const Color(0xFFECFDF5), // Soft Emerald
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: status == NetworkStatus.offline
                          ? const Color(0xFFFDA4AF)
                          : const Color(0xFFA7F3D0),
                      width: 1.2,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: (status == NetworkStatus.offline
                                ? const Color(0xFFE11D48)
                                : const Color(0xFF10B981))
                            .withValues(alpha: 0.08),
                        blurRadius: 10,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      // Status Icon with circular badge
                      Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: status == NetworkStatus.offline
                              ? const Color(0xFFFFE4E6)
                              : const Color(0xFFD1FAE5),
                        ),
                        child: Center(
                          child: Icon(
                            status == NetworkStatus.offline
                                ? Icons.wifi_off_rounded
                                : Icons.wifi_rounded,
                            size: 18,
                            color: status == NetworkStatus.offline
                                ? const Color(0xFFE11D48)
                                : const Color(0xFF059669),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),

                      // Message & Subtitle
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Row(
                              children: [
                                Text(
                                  status == NetworkStatus.offline
                                      ? 'Koneksi Terputus'
                                      : 'Koneksi Normal Kembali',
                                  style: TextStyle(
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.bold,
                                    color: status == NetworkStatus.offline
                                        ? const Color(0xFF881337)
                                        : const Color(0xFF064E3B),
                                  ),
                                ),
                                const SizedBox(width: 6),
                                if (status == NetworkStatus.offline)
                                  Container(
                                    width: 7,
                                    height: 7,
                                    decoration: const BoxDecoration(
                                      color: Color(0xFFE11D48),
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 2),
                            Text(
                              status == NetworkStatus.offline
                                  ? 'Tidak ada koneksi internet. Menunggu jaringan...'
                                  : 'Jaringan kembali aktif. Memperbarui data...',
                              style: TextStyle(
                                fontSize: 11,
                                height: 1.25,
                                color: status == NetworkStatus.offline
                                    ? const Color(0xFF9F1239)
                                    : const Color(0xFF047857),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),

                      // Status Badge / Retry Button
                      if (status == NetworkStatus.offline)
                        GestureDetector(
                          onTap: _handleManualRetry,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFE4E6),
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(
                                color: const Color(0xFFFDA4AF),
                                width: 1,
                              ),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                if (_isChecking)
                                  const SizedBox(
                                    width: 12,
                                    height: 12,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Color(0xFFE11D48),
                                    ),
                                  )
                                else
                                  const Icon(
                                    Icons.refresh_rounded,
                                    size: 13,
                                    color: Color(0xFFE11D48),
                                  ),
                                const SizedBox(width: 4),
                                const Text(
                                  'Cek',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFFE11D48),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        )
                      else
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 9,
                            vertical: 5,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFFD1FAE5),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Text(
                            'Terhubung',
                            style: TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF065F46),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
        );
      },
    );
  }
}
