import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../community/presentation/community_page.dart';
import '../../court/presentation/venue_directory_page.dart';
import '../../home/presentation/home_page.dart';
import '../../profile/presentation/profile_page.dart';
import '../../session/presentation/create_session_page.dart';
import '../../session/presentation/session_detail_page.dart';
import '../../session/presentation/session_list_page.dart';

class MainShellPage extends StatefulWidget {
  final AuthController? authController;

  const MainShellPage({super.key, this.authController});

  @override
  State<MainShellPage> createState() => _MainShellPageState();
}

class _MainShellPageState extends State<MainShellPage> {
  int _currentIndex = 0;

  @override
  void initState() {
    super.initState();
    widget.authController?.addListener(_onAuthChanged);
  }

  @override
  void dispose() {
    widget.authController?.removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
    if (mounted) setState(() {});
  }

  void _onTabTapped(int index) {
    if (index == 2) {
      // Tab Host Center Button clicked
      _handleHostAction();
      return;
    }
    setState(() {
      _currentIndex = index;
    });
  }

  void _handleHostAction() {
    final user = widget.authController?.currentUser;
    final isHost = user?.isHost ?? false;

    if (user == null) {
      // Guest: tampilkan modal informasi Host
      _showHostInfoModal(isGuest: true);
    } else if (isHost) {
      // Host: langsung buka halaman Buat Sesi Mabar
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CreateSessionPage(
            authController: widget.authController,
          ),
        ),
      );
    } else {
      // Member tapi belum aktif mode host: tampilkan modal aktifkan host
      _showHostInfoModal(isGuest: false);
    }
  }

  void _showHostInfoModal({required bool isGuest}) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFCBD5E1),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              Container(
                width: 60,
                height: 60,
                decoration: BoxDecoration(
                  color: AppColors.matchaSoftLime,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: AppColors.matchaDark.withValues(alpha: 0.2),
                    width: 2,
                  ),
                ),
                child: const Icon(
                  Icons.sports_tennis_rounded,
                  color: AppColors.matchaDark,
                  size: 32,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                isGuest ? 'Masuk Sebagai Host Game' : 'Aktifkan Mode Host Game',
                style: AppTextStyles.h2.copyWith(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: const Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                isGuest
                    ? 'Sebagai Host Game, kamu dapat membuat jadwal mabar baru, mengacak drawing pemain, memimpin live match scoring, dan memberikan Kudos!'
                    : 'Kamu perlu mengaktifkan Status Akses Host Game di halaman profil untuk mulai membuat jadwal mabar dan mengelola drawing.',
                textAlign: TextAlign.center,
                style: AppTextStyles.caption.copyWith(
                  fontSize: 13,
                  color: const Color(0xFF64748B),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 46,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(ctx);
                    if (isGuest) {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => LoginPage(authController: widget.authController),
                        ),
                      );
                    } else {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ProfilePage(authController: widget.authController),
                        ),
                      );
                    }
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.matchaDark,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    elevation: 0,
                  ),
                  child: Text(
                    isGuest ? 'Masuk / Daftar Akun' : 'Buka Pengaturan Profil',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;
    final isHost = user?.isHost ?? false;

    final tabs = [
      // Tab 0: Home Dashboard
      HomePage(
        authController: widget.authController,
        onExploreSessions: () => setState(() => _currentIndex = 1),
        onExploreCommunity: () => setState(() => _currentIndex = 3),
      ),

      // Tab 1: Sesi Mabar
      SessionListPage(
        authController: widget.authController,
        onSessionTap: (sessionId) {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => SessionDetailPage(
                sessionId: sessionId,
                authController: widget.authController,
              ),
            ),
          );
        },
      ),

      // Tab 2: Placeholder for Host Center Tab Action
      const SizedBox.shrink(),

      // Tab 3: Komunitas
      CommunityPage(authController: widget.authController),

      // Tab 4: Direktori Venue & Court
      const VenueDirectoryPage(),
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: Row(
          children: [
            Image.asset(
              'assets/images/logo.png',
              height: 28,
              fit: BoxFit.contain,
              errorBuilder: (context, error, stackTrace) => Row(
                children: [
                  const Text('🎾', style: TextStyle(fontSize: 20)),
                  const SizedBox(width: 6),
                  Text(
                    'MATCHA',
                    style: AppTextStyles.h2.copyWith(
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1.5,
                      color: AppColors.matchaDark,
                      fontSize: 16,
                    ),
                  ),
                ],
              ),
            ),
            const Spacer(),
            // User / Host Status Badge & Profile Access
            if (user != null)
              GestureDetector(
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ProfilePage(authController: widget.authController),
                    ),
                  );
                },
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: isHost ? AppColors.matchaSoftLime : const Color(0xFFEFF6FF),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: isHost
                              ? AppColors.matchaDark.withValues(alpha: 0.3)
                              : const Color(0xFF93C5FD),
                          width: 1,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            isHost ? Icons.sports_tennis_rounded : Icons.person_outline_rounded,
                            size: 13,
                            color: isHost ? AppColors.matchaDark : const Color(0xFF1D4ED8),
                          ),
                          const SizedBox(width: 4),
                          Text(
                            isHost ? 'HOST ACTIVE' : 'MEMBER',
                            style: AppTextStyles.badge.copyWith(
                              color: isHost ? AppColors.matchaDark : const Color(0xFF1D4ED8),
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        shape: BoxShape.circle,
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: const Icon(Icons.person_rounded, size: 18, color: AppColors.matchaDark),
                    ),
                  ],
                ),
              )
            else
              GestureDetector(
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => LoginPage(authController: widget.authController),
                    ),
                  );
                },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.matchaSoftLime,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: AppColors.matchaDark.withValues(alpha: 0.3),
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.login_rounded, size: 13, color: AppColors.matchaDark),
                      const SizedBox(width: 4),
                      Text(
                        'MASUK',
                        style: AppTextStyles.badge.copyWith(
                          color: AppColors.matchaDark,
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
      body: IndexedStack(
        index: _currentIndex == 2 ? 0 : _currentIndex,
        children: tabs,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(top: BorderSide(color: Color(0xFFE2E8F0), width: 1)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 12,
              offset: const Offset(0, -3),
            ),
          ],
        ),
        child: SafeArea(
          child: SizedBox(
            height: 64,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildNavItem(
                  index: 0,
                  icon: Icons.home_outlined,
                  activeIcon: Icons.home_rounded,
                  label: 'Beranda',
                ),
                _buildNavItem(
                  index: 1,
                  icon: Icons.emoji_events_outlined,
                  activeIcon: Icons.emoji_events_rounded,
                  label: 'Mabar',
                ),
                // Center Host Button (+)
                _buildCenterHostButton(isHost: isHost),
                _buildNavItem(
                  index: 3,
                  icon: Icons.groups_outlined,
                  activeIcon: Icons.groups_rounded,
                  label: 'Komunitas',
                ),
                _buildNavItem(
                  index: 4,
                  icon: Icons.location_on_outlined,
                  activeIcon: Icons.location_on_rounded,
                  label: 'Venue',
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem({
    required int index,
    required IconData icon,
    required IconData activeIcon,
    required String label,
  }) {
    final isSelected = _currentIndex == index;
    return Expanded(
      child: InkWell(
        onTap: () => _onTabTapped(index),
        splashColor: Colors.transparent,
        highlightColor: Colors.transparent,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
              decoration: BoxDecoration(
                color: isSelected ? AppColors.matchaSoftLime : Colors.transparent,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Icon(
                isSelected ? activeIcon : icon,
                color: isSelected ? AppColors.matchaDark : const Color(0xFF64748B),
                size: 20,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                color: isSelected ? AppColors.matchaDark : const Color(0xFF64748B),
                fontSize: 10,
                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCenterHostButton({required bool isHost}) {
    return Expanded(
      child: GestureDetector(
        onTap: () => _onTabTapped(2),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: const Color(0xFF063B00),
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF063B00).withValues(alpha: 0.25),
                        blurRadius: 8,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: const Center(
                    child: Icon(
                      Icons.add_rounded,
                      color: Color(0xFFA8E63A),
                      size: 24,
                    ),
                  ),
                ),
                Positioned(
                  top: 0,
                  right: 0,
                  child: Container(
                    width: 9,
                    height: 9,
                    decoration: BoxDecoration(
                      color: const Color(0xFFA8E63A),
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 1.5),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 2),
            const Text(
              'Host',
              style: TextStyle(
                color: Color(0xFF063B00),
                fontSize: 10,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
