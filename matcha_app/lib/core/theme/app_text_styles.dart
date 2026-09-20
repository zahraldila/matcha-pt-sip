import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTextStyles {
  AppTextStyles._();

  // Headings & Titles
  static TextStyle h1 = GoogleFonts.inter(
    fontSize: 24,
    fontWeight: FontWeight.bold,
    letterSpacing: -0.5,
  );

  static TextStyle h2 = GoogleFonts.inter(
    fontSize: 18,
    fontWeight: FontWeight.w700,
  );

  static TextStyle h3 = GoogleFonts.inter(
    fontSize: 16,
    fontWeight: FontWeight.w600,
  );

  static TextStyle pageTitle = GoogleFonts.inter(
    fontSize: 24,
    fontWeight: FontWeight.bold,
    letterSpacing: -0.5,
  );

  static TextStyle sectionTitle = GoogleFonts.inter(
    fontSize: 18,
    fontWeight: FontWeight.w600,
  );

  static TextStyle cardTitle = GoogleFonts.inter(
    fontSize: 16,
    fontWeight: FontWeight.w600,
  );

  // Body Texts
  static TextStyle bodyLarge = GoogleFonts.inter(
    fontSize: 15,
    fontWeight: FontWeight.w400,
  );

  static TextStyle bodyMedium = GoogleFonts.inter(
    fontSize: 14,
    fontWeight: FontWeight.w500,
  );

  static TextStyle body = GoogleFonts.inter(
    fontSize: 14,
    fontWeight: FontWeight.w400,
  );

  static TextStyle bodySmall = GoogleFonts.inter(
    fontSize: 12,
    fontWeight: FontWeight.w400,
  );

  static TextStyle bodySecondary = GoogleFonts.inter(
    fontSize: 13,
    fontWeight: FontWeight.w400,
  );

  // Interactive & Small Elements
  static TextStyle button = GoogleFonts.inter(
    fontSize: 15,
    fontWeight: FontWeight.w600,
    letterSpacing: 0.2,
  );

  static TextStyle buttonSecondary = GoogleFonts.inter(
    fontSize: 14,
    fontWeight: FontWeight.w600,
  );

  static TextStyle caption = GoogleFonts.inter(
    fontSize: 12,
    fontWeight: FontWeight.w400,
  );

  static TextStyle badge = GoogleFonts.inter(
    fontSize: 11,
    fontWeight: FontWeight.w700,
    letterSpacing: 0.5,
  );

  // Score & Stat Big Text
  static TextStyle scoreDisplay = GoogleFonts.inter(
    fontSize: 32,
    fontWeight: FontWeight.w800,
  );

  static TextStyle brandLogo = GoogleFonts.inter(
    fontSize: 28,
    fontWeight: FontWeight.w900,
    letterSpacing: 3,
  );
}
