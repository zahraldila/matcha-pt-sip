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
    final title = customTitle ?? (isNetwork ? 'Koneksi Terputus' : 'Gagal Memuat Data');
    final message = customMessage ??
        (isNetwork
            ? 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda lalu coba lagi.'
            : AppErrorHandler.getReadableMessage(error));

    if (isCompact) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF1F2), // Soft Rose
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFFDA4AF), width: 1.2),
        ),
        child: Row(
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: const BoxDecoration(
                color: Color(0xFFFFE4E6),
                shape: BoxShape.circle,
              ),
              child: Icon(
                isNetwork ? Icons.wifi_off_rounded : Icons.error_outline_rounded,
                color: const Color(0xFFE11D48),
                size: 18,
              ),
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
                      fontSize: 12.5,
                      color: Color(0xFF881337),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    message,
                    style: const TextStyle(
                      fontSize: 11,
                      color: Color(0xFF9F1239),
                      height: 1.25,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            IconButton(
              icon: const Icon(Icons.refresh_rounded, color: Color(0xFFE11D48), size: 20),
              onPressed: onRetry,
              tooltip: 'Coba Lagi',
              visualDensity: VisualDensity.compact,
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
                color: const Color(0xFFFFF1F2),
                shape: BoxShape.circle,
                border: Border.all(
                  color: const Color(0xFFFDA4AF),
                  width: 2,
                ),
              ),
              child: Icon(
                isNetwork ? Icons.wifi_off_rounded : Icons.cloud_off_rounded,
                size: 32,
                color: const Color(0xFFE11D48),
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
