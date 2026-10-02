import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../theme/app_colors.dart';

class AppErrorHandler {
  /// Memeriksa apakah error disebabkan oleh gangguan jaringan/koneksi
  static bool isNetworkError(dynamic error) {
    if (error == null) return false;
    if (error is SocketException || error is TimeoutException) return true;

    final errStr = error.toString().toLowerCase();
    return errStr.contains('socket') ||
        errStr.contains('host lookup') ||
        errStr.contains('no address associated with hostname') ||
        errStr.contains('clientexception') ||
        errStr.contains('network is unreachable') ||
        errStr.contains('connection refused') ||
        errStr.contains('connection closed') ||
        errStr.contains('connection reset') ||
        errStr.contains('connection abort') ||
        errStr.contains('timed out') ||
        errStr.contains('timeout') ||
        errStr.contains('handshake') ||
        errStr.contains('xmlhttprequest error') ||
        errStr.contains('failed to fetch');
  }

  /// Mengonversi error teknis menjadi pesan yang ramah pengguna
  static String getReadableMessage(dynamic error, {String fallback = 'Terjadi kesalahan sistem. Silakan coba lagi.'}) {
    if (error == null) return fallback;

    // 1. Error Jaringan / Offline
    if (isNetworkError(error)) {
      return 'Koneksi internet terputus. Silakan periksa jaringan Wi-Fi atau data seluler Anda lalu coba lagi.';
    }

    // 2. Timeout
    if (error is TimeoutException || error.toString().toLowerCase().contains('timeout')) {
      return 'Waktu koneksi habis. Server memerlukan waktu lebih lama untuk merespons.';
    }

    // 3. Supabase PostgrestException
    if (error is PostgrestException) {
      if (error.code == '23505') {
        return 'Data ini sudah terdaftar sebelumnya (duplikat).';
      }
      if (error.code == 'PGRST116') {
        return 'Data yang diminta tidak ditemukan.';
      }
      if (error.message.isNotEmpty) {
        return _cleanMessage(error.message);
      }
    }

    // 4. Supabase AuthException
    if (error is AuthException) {
      final msg = error.message.toLowerCase();
      if (msg.contains('invalid login credentials') || msg.contains('invalid credentials')) {
        return 'Email atau kata sandi yang Anda masukkan salah.';
      }
      if (msg.contains('user already registered') || msg.contains('email already in use')) {
        return 'Email ini sudah terdaftar. Silakan gunakan email lain atau masuk ke akun Anda.';
      }
      if (msg.contains('weak password')) {
        return 'Kata sandi terlalu lemah. Gunakan minimal 6 karakter.';
      }
      if (error.message.isNotEmpty) {
        return _cleanMessage(error.message);
      }
    }

    // 5. Izin Kamera & Galeri
    final errStr = error.toString().toLowerCase();
    if (errStr.contains('camera_access_denied') ||
        errStr.contains('camera_access_restricted')) {
      return 'Akses kamera ditolak. Silakan berikan izin kamera di pengaturan HP Anda untuk mengambil foto.';
    }
    if (errStr.contains('photo_access_denied') ||
        errStr.contains('photo_access_restricted')) {
      return 'Akses galeri ditolak. Silakan berikan izin akses galeri di pengaturan HP Anda.';
    }

    // 6. Exception umum
    return _cleanMessage(error.toString());
  }

  static String _cleanMessage(String raw) {
    var cleaned = raw;
    if (cleaned.startsWith('Exception: ')) {
      cleaned = cleaned.replaceFirst('Exception: ', '');
    }
    if (cleaned.startsWith('Error: ')) {
      cleaned = cleaned.replaceFirst('Error: ', '');
    }
    // Jika masih ada sisa stacktrace atau url teknis, ambil kalimat pertama
    if (cleaned.contains('(OS Error:') || cleaned.contains('uri=')) {
      cleaned = 'Koneksi internet terputus. Silakan periksa jaringan Anda.';
    }
    return cleaned.trim();
  }

  /// Menampilkan SnackBar error modern yang user-friendly
  static void showErrorSnackBar(
    BuildContext context,
    dynamic error, {
    String? customMessage,
    String? actionLabel,
    VoidCallback? onAction,
    Duration duration = const Duration(seconds: 4),
  }) {
    if (!context.mounted) return;

    final isOffline = isNetworkError(error);
    final message = customMessage ?? getReadableMessage(error);

    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        elevation: 6,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        duration: duration,
        backgroundColor: const Color(0xFF1E293B),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(
            color: isOffline ? const Color(0xFFF59E0B) : const Color(0xFFEF4444),
            width: 1.2,
          ),
        ),
        content: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: isOffline
                    ? const Color(0xFFF59E0B).withValues(alpha: 0.2)
                    : const Color(0xFFEF4444).withValues(alpha: 0.2),
                shape: BoxShape.circle,
              ),
              child: Icon(
                isOffline ? Icons.wifi_off_rounded : Icons.error_outline_rounded,
                color: isOffline ? const Color(0xFFFBBF24) : const Color(0xFFF87171),
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    isOffline ? 'Koneksi Terputus' : 'Terjadi Kendala',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    message,
                    style: const TextStyle(
                      color: Color(0xFFCBD5E1),
                      fontSize: 11.5,
                      height: 1.3,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        action: actionLabel != null && onAction != null
            ? SnackBarAction(
                label: actionLabel,
                textColor: isOffline ? const Color(0xFFFBBF24) : AppColors.matchaLime,
                onPressed: onAction,
              )
            : null,
      ),
    );
  }

  /// Menampilkan SnackBar sukses modern
  static void showSuccessSnackBar(
    BuildContext context,
    String message, {
    Duration duration = const Duration(seconds: 3),
  }) {
    if (!context.mounted) return;

    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        elevation: 6,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        duration: duration,
        backgroundColor: const Color(0xFF063B00),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: Color(0xFF86EFAC), width: 1.2),
        ),
        content: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.2),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.check_circle_rounded,
                color: Color(0xFFA8E63A),
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                  fontSize: 12.5,
                  height: 1.3,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
