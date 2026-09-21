import 'package:flutter/material.dart';

class AppColors {
  AppColors._();

  // Matcha Brand Color Palette (matching matcha-pt-web)
  static const Color matchaDark = Color(0xFF063B00); // Deep Matcha Forest Green
  static const Color matchaDarkHover = Color(0xFF042A00);
  static const Color matchaLime = Color(0xFFA8E63A); // Lime Accent
  static const Color matchaSoftLime = Color(0xFFEBF8D8); // Pastel Lime Background
  static const Color matchaSoftLimeBorder = Color(0xFFC4E992);

  // Background & Surface (Light Mode Primary)
  static const Color lightBackground = Color(0xFFF8FAFC); // Slate-50 off-white
  static const Color lightSurface = Color(0xFFFFFFFF); // Pure white card
  static const Color lightSurfaceSecondary = Color(0xFFF1F5F9); // Slate-100 pill/card
  static const Color lightSurfaceBorder = Color(0xFFE2E8F0); // Slate-200 border

  // Text Hierarchy
  static const Color textPrimary = Color(0xFF050608); // Near Black / Deep Slate
  static const Color textSecondary = Color(0xFF64748B); // Slate-500
  static const Color textDisabled = Color(0xFF94A3B8); // Slate-400
  static const Color textOnPrimary = Color(0xFFFFFFFF); // White text on dark green

  // Semantic Colors
  static const Color success = Color(0xFF22C55E);
  static const Color warning = Color(0xFFF59E0B);
  static const Color error = Color(0xFFEF4444);
  static const Color info = Color(0xFF3B82F6);

  // Status Badges
  static const Color liveBadge = Color(0xFFEF4444);
  static const Color openBadge = Color(0xFF063B00);
}

/// Extension on BuildContext for quick access to theme colors
extension AppColorsExtension on BuildContext {
  Color get bg => AppColors.lightBackground;
  Color get surf => AppColors.lightSurface;
  Color get surfSec => AppColors.lightSurfaceSecondary;
  Color get surfBorder => AppColors.lightSurfaceBorder;
  Color get txtPrimary => AppColors.textPrimary;
  Color get txtSecondary => AppColors.textSecondary;
  Color get txtDisabled => AppColors.textDisabled;
  Color get brandColor => AppColors.matchaDark;
  Color get limeAccent => AppColors.matchaLime;
  Color get softLime => AppColors.matchaSoftLime;
}
