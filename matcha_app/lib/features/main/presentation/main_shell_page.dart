import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../community/presentation/community_page.dart';
import '../../court/presentation/venue_directory_page.dart';
import '../../games/presentation/create_game_wizard_page.dart';
import '../../home/presentation/home_page.dart';
import '../../profile/presentation/profile_page.dart';
import '../../recap/presentation/match_recap_page.dart';
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
  final GlobalKey _avatarKey = GlobalKey();

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
      // Host: langsung buka halaman Host Game Creation Wizard
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CreateGameWizardPage(
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
      VenueDirectoryPage(authController: widget.authController),
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: Row(
          children: [
            // Brand Logo & Title (matching web navbar)
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.asset(
                    'assets/images/logo.png',
                    width: 34,
                    height: 34,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => Container(
                      width: 34,
                      height: 34,
                      decoration: BoxDecoration(
                        color: const Color(0xFF0F172A),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Center(
                        child: Text('🎾', style: TextStyle(fontSize: 18)),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'MATCHA',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.3,
                        color: Color(0xFF0F172A),
                        height: 1.05,
                      ),
                    ),
                    Text(
                      'MATCH ARENA',
                      style: TextStyle(
                        fontSize: 8.5,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.2,
                        color: Color(0xFF063B00),
                        height: 1.1,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const Spacer(),
            // User Profile Avatar & Dropdown Chevron (matching web navbar)
            if (user != null)
              GestureDetector(
                key: _avatarKey,
                onTap: () => _showUserDropdownMenu(context, _avatarKey),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: const Color(0xFF063B00),
                          border: Border.all(
                            color: const Color(0xFFBEF264),
                            width: 1.8,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.05),
                              blurRadius: 4,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: (user.foto != null && user.foto!.isNotEmpty)
                            ? Image.network(
                                user.foto!,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) => Center(
                                  child: Text(
                                    user.nama.isNotEmpty ? user.nama[0].toUpperCase() : 'U',
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w900,
                                      fontSize: 13,
                                    ),
                                  ),
                                ),
                              )
                            : Center(
                                child: Text(
                                  user.nama.isNotEmpty ? user.nama[0].toUpperCase() : 'U',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                    fontSize: 13,
                                  ),
                                ),
                              ),
                      ),
                      const SizedBox(width: 4),
                      const Icon(
                        Icons.keyboard_arrow_down_rounded,
                        size: 16,
                        color: Color(0xFF94A3B8),
                      ),
                    ],
                  ),
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
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: AppColors.matchaSoftLime,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: AppColors.matchaDark.withValues(alpha: 0.25),
                    ),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.login_rounded, size: 14, color: AppColors.matchaDark),
                      SizedBox(width: 5),
                      Text(
                        'Masuk',
                        style: TextStyle(
                          color: AppColors.matchaDark,
                          fontSize: 12,
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

  void _showUserDropdownMenu(BuildContext context, GlobalKey key) {
    final user = widget.authController?.currentUser;
    if (user == null) return;

    final renderBox = key.currentContext?.findRenderObject() as RenderBox?;
    final offset = renderBox?.localToGlobal(Offset.zero) ?? const Offset(200, 60);
    final size = renderBox?.size ?? const Size(40, 40);

    final isHost = user.isHost;
    final isVenueOwner = user.role == 'venue_owner';

    showGeneralDialog(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'UserDropdown',
      barrierColor: Colors.black.withValues(alpha: 0.15),
      transitionDuration: const Duration(milliseconds: 180),
      transitionBuilder: (context, anim1, anim2, child) {
        return Transform.scale(
          scale: 0.85 + (0.15 * Curves.easeOutBack.transform(anim1.value)),
          alignment: Alignment.topRight,
          child: Opacity(
            opacity: anim1.value,
            child: child,
          ),
        );
      },
      pageBuilder: (ctx, anim1, anim2) {
        return Stack(
          children: [
            Positioned(
              top: offset.dy + size.height + 6,
              right: 14,
              child: Material(
                color: Colors.transparent,
                child: Container(
                  width: 250,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.12),
                        blurRadius: 24,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // User Info Card Header (matching web)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        child: Row(
                          children: [
                            Container(
                              width: 38,
                              height: 38,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: const Color(0xFF063B00),
                                border: Border.all(
                                  color: const Color(0xFFBEF264),
                                  width: 1.5,
                                ),
                              ),
                              clipBehavior: Clip.antiAlias,
                              child: (user.foto != null && user.foto!.isNotEmpty)
                                  ? Image.network(
                                      user.foto!,
                                      fit: BoxFit.cover,
                                      errorBuilder: (_, _, _) => Center(
                                        child: Text(
                                          user.nama.isNotEmpty ? user.nama[0].toUpperCase() : 'U',
                                          style: const TextStyle(
                                            color: Colors.white,
                                            fontWeight: FontWeight.w900,
                                            fontSize: 14,
                                          ),
                                        ),
                                      ),
                                    )
                                  : Center(
                                      child: Text(
                                        user.nama.isNotEmpty ? user.nama[0].toUpperCase() : 'U',
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontWeight: FontWeight.w900,
                                          fontSize: 14,
                                        ),
                                      ),
                                    ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    user.nama,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w900,
                                      fontSize: 13,
                                      color: Color(0xFF0F172A),
                                      height: 1.1,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    user.email,
                                    style: const TextStyle(
                                      fontSize: 10,
                                      color: Color(0xFF94A3B8),
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  const SizedBox(height: 4),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: isHost
                                          ? const Color(0xFFECFDF5)
                                          : (isVenueOwner ? const Color(0xFFF0F9FF) : const Color(0xFFF1F5F9)),
                                      borderRadius: BorderRadius.circular(20),
                                    ),
                                    child: Text(
                                      isVenueOwner
                                          ? 'Venue Owner'
                                          : (isHost ? 'Host Game & Player' : 'Member Pemain'),
                                      style: TextStyle(
                                        fontSize: 9,
                                        fontWeight: FontWeight.w800,
                                        color: isHost
                                            ? const Color(0xFF065F46)
                                            : (isVenueOwner ? const Color(0xFF0369A1) : const Color(0xFF475569)),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Divider(height: 1, color: Color(0xFFF1F5F9)),

                      // Menu Items
                      _buildDropdownMenuItem(
                        icon: Icons.show_chart_rounded,
                        iconColor: const Color(0xFF64748B),
                        label: 'Match Recap & Statistik',
                        onTap: () {
                          Navigator.pop(ctx);
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => MatchRecapPage(
                                authController: widget.authController,
                              ),
                            ),
                          );
                        },
                      ),
                      _buildDropdownMenuItem(
                        icon: Icons.badge_outlined,
                        iconColor: const Color(0xFF64748B),
                        label: 'Profil & Status Host',
                        onTap: () {
                          Navigator.pop(ctx);
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => ProfilePage(authController: widget.authController),
                            ),
                          );
                        },
                      ),
                      const Divider(height: 1, color: Color(0xFFF1F5F9)),
                      _buildDropdownMenuItem(
                        icon: Icons.logout_rounded,
                        iconColor: const Color(0xFFE11D48),
                        label: 'Keluar (Logout)',
                        isDestructive: true,
                        onTap: () {
                          Navigator.pop(ctx);
                          _handleLogoutFromDropdown();
                        },
                      ),
                      const SizedBox(height: 4),
                    ],
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _buildDropdownMenuItem({
    required IconData icon,
    required Color iconColor,
    required String label,
    required VoidCallback onTap,
    bool isDestructive = false,
  }) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        child: Row(
          children: [
            Icon(icon, size: 16, color: iconColor),
            const SizedBox(width: 10),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: isDestructive ? FontWeight.w800 : FontWeight.w600,
                color: isDestructive ? const Color(0xFFE11D48) : const Color(0xFF334155),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _handleLogoutFromDropdown() async {
    final shouldLogout = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.logout_rounded, color: Color(0xFFE11D48), size: 22),
            SizedBox(width: 8),
            Text(
              'Konfirmasi Keluar',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
            ),
          ],
        ),
        content: const Text(
          'Apakah Anda yakin ingin keluar dari akun? Anda perlu masuk kembali untuk mengakses sesi mabar dan profil Anda.',
          style: TextStyle(fontSize: 12.5, color: Color(0xFF64748B), height: 1.4),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal', style: TextStyle(color: Color(0xFF64748B), fontWeight: FontWeight.bold)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFE11D48),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              elevation: 0,
            ),
            child: const Text('Ya, Keluar Akun', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    if (shouldLogout == true) {
      await widget.authController?.logout();
      if (!mounted) return;
      setState(() => _currentIndex = 0);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Anda telah berhasil keluar dari akun.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }
}
