import 'package:flutter/material.dart';

class AppColors {
  AppColors._();

  // Brand Colors
  static const Color primary = Color(0xFFA8E63A); // Lime Green
  static const Color primaryDark = Color(0xFF7FAF25); // Dark Green
  static const Color primaryLight = Color(0xFFC7F37A);

  // Background & Surface
  static const Color background = Color(0xFF050608); // Near Black
  static const Color surface = Color(0xFF111318); // Dark Gray
  static const Color surfaceSecondary = Color(0xFF1A1D23); // Gray Card
  static const Color surfaceBorder = Color(0xFF262A33);

  // Text Colors
  static const Color textPrimary = Color(0xFFFFFFFF);
  static const Color textSecondary = Color(0xFFA9ADB5);
  static const Color textDisabled = Color(0xFF666A73);
  static const Color textOnPrimary = Color(0xFF050608); // Dark text on Lime Green

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
