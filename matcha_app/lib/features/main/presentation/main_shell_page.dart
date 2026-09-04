import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../home/presentation/home_page.dart';

class MainShellPage extends StatefulWidget {
  final AuthController authController;

  const MainShellPage({super.key, required this.authController});

  @override
  State<MainShellPage> createState() => _MainShellPageState();
}

class _MainShellPageState extends State<MainShellPage> {
  int _currentIndex = 0;

  void _handleLogout() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppColors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: AppColors.surfaceBorder),
        ),
        title: Text('Konfirmasi Keluar', style: AppTextStyles.cardTitle),
        content: Text(
          'Apakah Anda yakin ingin keluar dari akun?',
          style: AppTextStyles.bodySecondary,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('Batal', style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
              minimumSize: const Size(80, 36),
            ),
            onPressed: () {
              Navigator.pop(context);
              widget.authController.logout();
              Navigator.of(context).pushReplacement(
                MaterialPageRoute(
                  builder: (context) => LoginPage(authController: widget.authController),
                ),
              );
            },
            child: const Text('Keluar'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.authController.currentUser;
    final isHost = widget.authController.isHost;

    final tabs = [
      HomePage(
        user: user,
        onCreateSessionTap: () => setState(() => _currentIndex = 1),
        onLiveSessionTap: () => setState(() => _currentIndex = 1),
        onManagePlayersTap: () => setState(() => _currentIndex = 2),
        onManageCourtsTap: () => setState(() => _currentIndex = 1),
        onCommunityTap: () => setState(() => _currentIndex = 2),
      ),
      _buildPlaceholderTab(
        title: 'Session Management',
        icon: Icons.calendar_today_rounded,
        description: isHost
            ? 'Buat sesi baru, pilih olahraga, lapangan, dan peserta.'
            : 'Pantau daftar sesi permainan yang sedang berlangsung.',
      ),
      _buildPlaceholderTab(
        title: 'Players & Members',
        icon: Icons.people_alt_rounded,
        description: isHost
            ? 'Kelola daftar pemain aktif, NIK, dan relasi komunitas.'
            : 'Lihat informasi pemain dan riwayat pertandingan.',
      ),
      _buildProfileTab(user?.nama ?? 'User', user?.email ?? '', user?.role ?? 'User', isHost),
    ];

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Row(
          children: [
            Image.asset(
              'assets/images/logo.png',
              height: 28,
              fit: BoxFit.contain,
            ),
            const Spacer(),
            // Role Badge
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: isHost
                    ? AppColors.primary.withValues(alpha: 0.15)
                    : AppColors.info.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: isHost ? AppColors.primary : AppColors.info,
                  width: 1,
                ),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    isHost ? Icons.sports_tennis_rounded : Icons.visibility_rounded,
                    size: 13,
                    color: isHost ? AppColors.primary : AppColors.info,
                  ),
                  const SizedBox(width: 5),
                  Text(
                    isHost ? 'HOST' : 'PERSONAL USER',
                    style: AppTextStyles.badge.copyWith(
                      color: isHost ? AppColors.primary : AppColors.info,
                      fontSize: 10,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: tabs[_currentIndex],
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          border: Border(
            top: BorderSide(color: AppColors.surfaceBorder, width: 1),
          ),
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
          onTap: (index) => setState(() => _currentIndex = index),
          items: const [
            BottomNavigationBarItem(
              icon: Icon(Icons.home_outlined),
              activeIcon: Icon(Icons.home_filled),
              label: 'Home',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.sports_esports_outlined),
              activeIcon: Icon(Icons.sports_esports_rounded),
              label: 'Session',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.group_outlined),
              activeIcon: Icon(Icons.group_rounded),
              label: 'Players',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.person_outline_rounded),
              activeIcon: Icon(Icons.person_rounded),
              label: 'Profile',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProfileTab(String name, String email, String role, bool isHost) {
    return Padding(
      padding: const EdgeInsets.all(20.0),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.surfaceBorder),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: AppColors.primary.withValues(alpha: 0.2),
                  child: Text(
                    name.isNotEmpty ? name[0].toUpperCase() : 'U',
                    style: AppTextStyles.pageTitle.copyWith(
                      color: AppColors.primary,
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(name, style: AppTextStyles.cardTitle),
                      const SizedBox(height: 2),
                      Text(email, style: AppTextStyles.caption),
                      const SizedBox(height: 6),
                      Text(
                        'Role: $role',
                        style: AppTextStyles.badge.copyWith(
                          color: isHost ? AppColors.primary : AppColors.info,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const Spacer(),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.error,
              side: BorderSide(color: AppColors.error.withValues(alpha: 0.5)),
            ),
            onPressed: _handleLogout,
            icon: const Icon(Icons.logout_rounded, size: 20),
            label: const Text('Keluar dari Akun'),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  Widget _buildPlaceholderTab({
    required String title,
    required IconData icon,
    required String description,
  }) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppColors.surface,
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.surfaceBorder),
              ),
              child: Icon(icon, size: 48, color: AppColors.primary),
            ),
            const SizedBox(height: 20),
            Text(title, style: AppTextStyles.sectionTitle),
            const SizedBox(height: 8),
            Text(
              description,
              textAlign: TextAlign.center,
              style: AppTextStyles.bodySecondary,
            ),
          ],
        ),
      ),
    );
  }
}
