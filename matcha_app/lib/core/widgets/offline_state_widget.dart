import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../utils/app_error_handler.dart';

class OfflineStateWidget extends StatelessWidget {
  final dynamic error;
  final String? customTitle;
  final String? customMessage;
  final VoidCallback onRetry;
  final bool isCompact;

  const OfflineStateWidget({
    super.key,
    this.error,
    this.customTitle,
    this.customMessage,
    required this.onRetry,
    this.isCompact = false,
  });

  @override
  Widget build(BuildContext context) {
    final isNetwork = error == null || AppErrorHandler.isNetworkError(error);
    final title = customTitle ?? (isNetwork ? 'Koneksi Internet Terputus' : 'Gagal Memuat Data');
    final message = customMessage ??
        (isNetwork
            ? 'Tidak dapat terhubung ke server. Periksa jaringan Wi-Fi atau kuota internet Anda lalu coba lagi.'
            : AppErrorHandler.getReadableMessage(error));

    if (isCompact) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: const Color(0xFFFEF2F2),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFFECACA)),
        ),
        child: Row(
          children: [
            Icon(
              isNetwork ? Icons.wifi_off_rounded : Icons.error_outline_rounded,
              color: const Color(0xFFDC2626),
              size: 24,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                      color: Color(0xFF991B1B),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    message,
                    style: const TextStyle(
                      fontSize: 11,
                      color: Color(0xFF7F1D1D),
                      height: 1.3,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              icon: const Icon(Icons.refresh_rounded, color: Color(0xFFDC2626)),
              onPressed: onRetry,
              tooltip: 'Coba Lagi',
            ),
          ],
        ),
      );
    }

    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                color: isNetwork ? const Color(0xFFFEF3C7) : const Color(0xFFFEF2F2),
                shape: BoxShape.circle,
                border: Border.all(
                  color: isNetwork ? const Color(0xFFFDE68A) : const Color(0xFFFECACA),
                  width: 2,
                ),
              ),
              child: Icon(
                isNetwork ? Icons.wifi_off_rounded : Icons.cloud_off_rounded,
                size: 32,
                color: isNetwork ? const Color(0xFFD97706) : const Color(0xFFDC2626),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              title,
              style: const TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: Color(0xFF0F172A),
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 6),
            Text(
              message,
              style: const TextStyle(
                fontSize: 12.5,
                color: Color(0xFF64748B),
                height: 1.4,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text(
                'Coba Muat Ulang',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.matchaDark,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 12),
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
