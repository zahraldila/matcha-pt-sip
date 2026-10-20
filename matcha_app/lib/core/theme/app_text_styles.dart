import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTextStyles {
  AppTextStyles._();

  // Headings & Titles
  static TextStyle h1 = GoogleFonts.plusJakartaSans(
    fontSize: 24,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.5,
  );

  static TextStyle h2 = GoogleFonts.plusJakartaSans(
    fontSize: 18,
    fontWeight: FontWeight.w700,
  );

  static TextStyle h3 = GoogleFonts.plusJakartaSans(
    fontSize: 15,
    fontWeight: FontWeight.w700,
  );

  static TextStyle pageTitle = GoogleFonts.plusJakartaSans(
    fontSize: 22,
    fontWeight: FontWeight.w800,
    letterSpacing: -0.5,
  );

  static TextStyle sectionTitle = GoogleFonts.plusJakartaSans(
    fontSize: 17,
    fontWeight: FontWeight.w700,
  );

  static TextStyle cardTitle = GoogleFonts.plusJakartaSans(
    fontSize: 15,
    fontWeight: FontWeight.w700,
  );

  // Body Texts
  static TextStyle bodyLarge = GoogleFonts.plusJakartaSans(
    fontSize: 15,
    fontWeight: FontWeight.w400,
  );

  static TextStyle bodyMedium = GoogleFonts.plusJakartaSans(
    fontSize: 14,
    fontWeight: FontWeight.w500,
  );

  static TextStyle body = GoogleFonts.plusJakartaSans(
    fontSize: 14,
    fontWeight: FontWeight.w400,
  );

  static TextStyle bodySmall = GoogleFonts.plusJakartaSans(
    fontSize: 12,
    fontWeight: FontWeight.w400,
  );

  static TextStyle bodySecondary = GoogleFonts.plusJakartaSans(
    fontSize: 13,
    fontWeight: FontWeight.w400,
  );

  // Interactive & Small Elements
  static TextStyle button = GoogleFonts.plusJakartaSans(
    fontSize: 14,
    fontWeight: FontWeight.w700,
    letterSpacing: 0.1,
  );

  static TextStyle buttonSecondary = GoogleFonts.plusJakartaSans(
    fontSize: 14,
    fontWeight: FontWeight.w700,
  );

  static TextStyle caption = GoogleFonts.plusJakartaSans(
    fontSize: 12,
    fontWeight: FontWeight.w500,
  );

  static TextStyle badge = GoogleFonts.plusJakartaSans(
    fontSize: 11,
    fontWeight: FontWeight.w700,
    letterSpacing: 0.3,
  );

  // Score & Stat Big Text
  static TextStyle scoreDisplay = GoogleFonts.plusJakartaSans(
    fontSize: 32,
    fontWeight: FontWeight.w900,
  );

  static TextStyle brandLogo = GoogleFonts.plusJakartaSans(
    fontSize: 26,
    fontWeight: FontWeight.w900,
    letterSpacing: 2,
  );
}
