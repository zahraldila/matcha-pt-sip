import 'package:flutter/material.dart';

class AppColors {
  AppColors._();

  // Brand Colors (Lime Green)
  static const Color primary = Color(0xFFA8E63A); // Lime Green for Dark Mode
  static const Color primaryDark = Color(0xFF7FAF25); // Darker Green
  static const Color primaryLight = Color(0xFFC7F37A);
  static const Color primaryAccentLight = Color(0xFF6DA318); // High contrast green for light mode

  // Dark Theme Background & Surface
  static const Color background = Color(0xFF050608); // Near Black
  static const Color surface = Color(0xFF111318); // Dark Gray
  static const Color surfaceSecondary = Color(0xFF1A1D23); // Gray Card
  static const Color surfaceBorder = Color(0xFF262A33);

  // Light Theme Background & Surface
  static const Color lightBackground = Color(0xFFF6F8FA); // Soft off-white
  static const Color lightSurface = Color(0xFFFFFFFF); // Pure white card
  static const Color lightSurfaceSecondary = Color(0xFFEFF2F6); // Soft gray card/pill
  static const Color lightSurfaceBorder = Color(0xFFE2E8F0); // Light border

  // Text Colors - Dark Theme
  static const Color textPrimary = Color(0xFFFFFFFF);
  static const Color textSecondary = Color(0xFFA9ADB5);
  static const Color textDisabled = Color(0xFF666A73);
  static const Color textOnPrimary = Color(0xFF050608); // Dark text on Lime Green

  // Text Colors - Light Theme
  static const Color lightTextPrimary = Color(0xFF0F172A); // Deep slate
  static const Color lightTextSecondary = Color(0xFF64748B); // Cool gray
  static const Color lightTextDisabled = Color(0xFF94A3B8);
  static const Color lightTextOnPrimary = Color(0xFFFFFFFF);

  // Semantic Status Colors
  static const Color success = Color(0xFF4CAF50);
  static const Color warning = Color(0xFFFF9800);
  static const Color error = Color(0xFFF44336);
  static const Color info = Color(0xFF2196F3);

  // Game / Session Status Badges
  static const Color liveBadge = Color(0xFFA8E63A);
  static const Color inProgressBadge = Color(0xFFFF9800);
  static const Color finishedBadge = Color(0xFF666A73);
  static const Color waitingBadge = Color(0xFF3F82F6);
  static const Color playingBadge = Color(0xFFA8E63A);
}

/// Extension on BuildContext for quick access to theme-aware colors
extension AppColorsExtension on BuildContext {
  bool get isDarkMode => Theme.of(this).brightness == Brightness.dark;

  Color get bg => isDarkMode ? AppColors.background : AppColors.lightBackground;
  Color get surf => isDarkMode ? AppColors.surface : AppColors.lightSurface;
  Color get surfSec => isDarkMode ? AppColors.surfaceSecondary : AppColors.lightSurfaceSecondary;
  Color get surfBorder => isDarkMode ? AppColors.surfaceBorder : AppColors.lightSurfaceBorder;
  Color get txtPrimary => isDarkMode ? AppColors.textPrimary : AppColors.lightTextPrimary;
  Color get txtSecondary => isDarkMode ? AppColors.textSecondary : AppColors.lightTextSecondary;
  Color get txtDisabled => isDarkMode ? AppColors.textDisabled : AppColors.lightTextDisabled;
  Color get brandColor => isDarkMode ? AppColors.primary : AppColors.primaryAccentLight;
}
