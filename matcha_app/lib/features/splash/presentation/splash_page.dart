import 'dart:async';
import 'package:flutter/material.dart';
import '../../../core/services/app_link_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../main/presentation/main_shell_page.dart';

class SplashPage extends StatefulWidget {
  final AuthController authController;

  const SplashPage({super.key, required this.authController});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> with TickerProviderStateMixin {
  late AnimationController _entryController;
  late AnimationController _pulseController;
  late AnimationController _progressController;

  late Animation<double> _fadeAnimation;
  late Animation<double> _scaleAnimation;
  late Animation<double> _slideAnimation;
  late Animation<double> _glowAnimation;
  late Animation<double> _progressAnimation;

  @override
  void initState() {
    super.initState();

    // 1. Entry Animation (Logo & Card Reveal)
    _entryController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    );

    _fadeAnimation = CurvedAnimation(
      parent: _entryController,
      curve: const Interval(0.0, 0.7, curve: Curves.easeOut),
    );

    _scaleAnimation = Tween<double>(begin: 0.82, end: 1.0).animate(
      CurvedAnimation(
        parent: _entryController,
        curve: const Interval(0.0, 0.85, curve: Curves.easeOutBack),
      ),
    );

    _slideAnimation = Tween<double>(begin: 24.0, end: 0.0).animate(
      CurvedAnimation(
        parent: _entryController,
        curve: const Interval(0.2, 0.9, curve: Curves.easeOutCubic),
      ),
    );

    // 2. Ambient Pulse Glow Animation (Breathing background aura)
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2200),
    )..repeat(reverse: true);

    _glowAnimation = Tween<double>(begin: 0.85, end: 1.15).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );

    // 3. Smooth Progress Bar Animation
    _progressController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    );

    _progressAnimation = CurvedAnimation(
      parent: _progressController,
      curve: Curves.easeInOutCubic,
    );

    _entryController.forward();
    _progressController.forward();

    _checkSessionAndNavigate();
  }

  Future<void> _checkSessionAndNavigate() async {
    await Future.wait([
      widget.authController.checkSavedSession(),
      Future.delayed(const Duration(milliseconds: 2000)),
    ]);

    if (!mounted) return;

    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        pageBuilder: (ctx, animation, secondaryAnim) =>
            MainShellPage(authController: widget.authController),
        transitionsBuilder: (ctx, animation, secondaryAnim, child) =>
            FadeTransition(opacity: animation, child: child),
        transitionDuration: const Duration(milliseconds: 450),
      ),
    );

    WidgetsBinding.instance.addPostFrameCallback((_) {
      AppLinkService().setAppReady();
    });
  }

  @override
  void dispose() {
    _entryController.dispose();
    _pulseController.dispose();
    _progressController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF9FAF6),
      body: Stack(
        fit: StackFit.expand,
        children: [
          // 1. Fresh Sporty Gradient Background
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Color(0xFFFAFCF7),
                  Color(0xFFF2FBF0),
                  Color(0xFFE9F8D2),
                ],
              ),
            ),
          ),

          // 2. Subtle Tennis/Padel Court Lines Background Pattern
          Positioned.fill(
            child: Opacity(
              opacity: 0.45,
              child: CustomPaint(
                painter: _CourtLinesPainter(),
              ),
            ),
          ),

          // 3. Ambient Breathing Glow Orb in Center
          Center(
            child: AnimatedBuilder(
              animation: _glowAnimation,
              builder: (context, child) {
                return Transform.scale(
                  scale: _glowAnimation.value,
                  child: Container(
                    width: 300,
                    height: 300,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: RadialGradient(
                        colors: [
                          const Color(0xFFA8E63A).withValues(alpha: 0.35),
                          const Color(0xFF10B981).withValues(alpha: 0.12),
                          Colors.transparent,
                        ],
                        stops: const [0.0, 0.55, 1.0],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),

          // 4. Center Hero: Frosted Glass Logo Card & Branding
          Center(
            child: FadeTransition(
              opacity: _fadeAnimation,
              child: ScaleTransition(
                scale: _scaleAnimation,
                child: AnimatedBuilder(
                  animation: _slideAnimation,
                  builder: (context, child) {
                    return Transform.translate(
                      offset: Offset(0, _slideAnimation.value),
                      child: child,
                    );
                  },
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 28),
                    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.82),
                      borderRadius: BorderRadius.circular(32),
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.95),
                        width: 2.0,
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF063B00).withValues(alpha: 0.08),
                          blurRadius: 30,
                          offset: const Offset(0, 14),
                        ),
                        BoxShadow(
                          color: const Color(0xFFA8E63A).withValues(alpha: 0.15),
                          blurRadius: 20,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Matcha Official Logo Image
                        Hero(
                          tag: 'app_logo',
                          child: Image.asset(
                            'assets/images/logo.png',
                            width: 170,
                            fit: BoxFit.contain,
                            errorBuilder: (ctx, err, stack) => const Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text('🎾', style: TextStyle(fontSize: 56)),
                                SizedBox(height: 8),
                                Text(
                                  'MATCHA',
                                  style: TextStyle(
                                    fontSize: 28,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: 4.0,
                                    color: AppColors.matchaDark,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),

                        const SizedBox(height: 18),

                        // Match Arena Pill Badge
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEBF8D8),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(
                              color: const Color(0xFF063B00).withValues(alpha: 0.18),
                              width: 1.2,
                            ),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.sports_tennis_rounded, size: 13, color: Color(0xFF063B00)),
                              SizedBox(width: 6),
                              Text(
                                'MATCH ARENA',
                                style: TextStyle(
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF063B00),
                                  letterSpacing: 1.4,
                                ),
                              ),
                            ],
                          ),
                        ),

                        const SizedBox(height: 12),

                        // Tagline Subtitle
                        Text(
                          'Tennis & Padel Community Ecosystem',
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption.copyWith(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w700,
                            color: const Color(0xFF475569),
                            letterSpacing: 0.3,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),

          // 5. Bottom Loading Indicator & Footer
          Positioned(
            left: 0,
            right: 0,
            bottom: 42,
            child: FadeTransition(
              opacity: _fadeAnimation,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Slim Modern Capsule Progress Bar
                  Container(
                    width: 130,
                    height: 4.5,
                    decoration: BoxDecoration(
                      color: const Color(0xFFE2E8F0).withValues(alpha: 0.8),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: AnimatedBuilder(
                      animation: _progressAnimation,
                      builder: (context, child) {
                        return Align(
                          alignment: Alignment.centerLeft,
                          child: Container(
                            width: 130 * _progressAnimation.value,
                            height: 4.5,
                            decoration: BoxDecoration(
                              gradient: const LinearGradient(
                                colors: [
                                  Color(0xFF063B00),
                                  Color(0xFFA8E63A),
                                ],
                              ),
                              borderRadius: BorderRadius.circular(10),
                              boxShadow: [
                                BoxShadow(
                                  color: const Color(0xFFA8E63A).withValues(alpha: 0.5),
                                  blurRadius: 4,
                                  offset: const Offset(0, 1),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),

                  const SizedBox(height: 14),

                  // Footer Versioning / Subtitle
                  Text(
                    'SELADA INDONESIA PRODUKTIF',
                    style: AppTextStyles.caption.copyWith(
                      letterSpacing: 1.5,
                      color: const Color(0xFF64748B),
                      fontSize: 9.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Custom Painter for Subtle Tennis / Padel Court Lines in Background
class _CourtLinesPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF063B00).withValues(alpha: 0.04)
      ..strokeWidth = 1.5
      ..style = PaintingStyle.stroke;

    final centerLinePaint = Paint()
      ..color = const Color(0xFFA8E63A).withValues(alpha: 0.08)
      ..strokeWidth = 2.0
      ..style = PaintingStyle.stroke;

    final double w = size.width;
    final double h = size.height;

    // Outer Court Boundary
    final outerRect = RRect.fromRectAndRadius(
      Rect.fromLTWH(w * 0.08, h * 0.12, w * 0.84, h * 0.76),
      const Radius.circular(20),
    );
    canvas.drawRRect(outerRect, paint);

    // Inner Singles Lines
    final innerRect = Rect.fromLTWH(w * 0.15, h * 0.12, w * 0.70, h * 0.76);
    canvas.drawRect(innerRect, paint);

    // Horizontal Net Line (Middle of Court)
    canvas.drawLine(
      Offset(w * 0.04, h * 0.50),
      Offset(w * 0.96, h * 0.50),
      centerLinePaint,
    );

    // Service Lines (Top & Bottom halves)
    canvas.drawLine(
      Offset(w * 0.15, h * 0.32),
      Offset(w * 0.85, h * 0.32),
      paint,
    );
    canvas.drawLine(
      Offset(w * 0.15, h * 0.68),
      Offset(w * 0.85, h * 0.68),
      paint,
    );

    // Center Service Line
    canvas.drawLine(
      Offset(w * 0.50, h * 0.32),
      Offset(w * 0.50, h * 0.68),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
