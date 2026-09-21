import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../community/presentation/community_page.dart';
import '../../home/presentation/home_page.dart';
import '../../profile/presentation/profile_page.dart';
import '../../session/presentation/session_list_page.dart';

class MainShellPage extends StatefulWidget {
  final AuthController? authController;

  const MainShellPage({super.key, this.authController});

  @override
  State<MainShellPage> createState() => _MainShellPageState();
}

class _MainShellPageState extends State<MainShellPage> {
  final MockDataService _dataService = MockDataService();
  int _currentIndex = 0;

  @override
  void initState() {
    super.initState();
    _dataService.addListener(_onDataChanged);
  }

  @override
  void dispose() {
    _dataService.removeListener(_onDataChanged);
    super.dispose();
  }

  void _onDataChanged() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final isHost = _dataService.isHostMode;

    final tabs = [
      // Tab 0: Home Dashboard
      HomePage(
        authController: widget.authController,
        onExploreSessions: () => setState(() => _currentIndex = 1),
        onExploreCommunity: () => setState(() => _currentIndex = 2),
      ),

      // Tab 1: Sesi Mabar
      const SessionListPage(),

      // Tab 2: Komunitas Olahraga
      const CommunityPage(),

      // Tab 3: Profil & Pengaturan
      ProfilePage(authController: widget.authController),
    ];

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        backgroundColor: context.bg,
        title: Row(
          children: [
            Image.asset(
              'assets/images/logo.png',
              height: 26,
              fit: BoxFit.contain,
              errorBuilder: (context, error, stackTrace) => Row(
                children: [
                  const Text('🎾', style: TextStyle(fontSize: 18)),
                  const SizedBox(width: 6),
                  const Text(
                    'MATCHA',
                    style: TextStyle(
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
            // Host Badge Status
            GestureDetector(
              onTap: () => _dataService.toggleHostMode(),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: isHost
                      ? AppColors.matchaSoftLime
                      : const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: isHost
                        ? const Color(0xFF063B00).withValues(alpha: 0.3)
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
                    const SizedBox(width: 5),
                    Text(
                      isHost ? 'HOST MODE' : 'MEMBER',
                      style: AppTextStyles.badge.copyWith(
                        color: isHost ? AppColors.matchaDark : const Color(0xFF1D4ED8),
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      body: tabs[_currentIndex],
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: context.surfBorder, width: 1)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 10,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
          onTap: (index) => setState(() => _currentIndex = index),
          selectedItemColor: AppColors.matchaDark,
          unselectedItemColor: context.txtSecondary,
          backgroundColor: Colors.white,
          type: BottomNavigationBarType.fixed,
          selectedLabelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11),
          unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.normal, fontSize: 11),
          items: const [
            BottomNavigationBarItem(
              icon: Icon(Icons.home_outlined),
              activeIcon: Icon(Icons.home_rounded),
              label: 'Beranda',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.sports_esports_outlined),
              activeIcon: Icon(Icons.sports_esports_rounded),
              label: 'Sesi Mabar',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.groups_outlined),
              activeIcon: Icon(Icons.groups_rounded),
              label: 'Komunitas',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.person_outline_rounded),
              activeIcon: Icon(Icons.person_rounded),
              label: 'Profil',
            ),
          ],
        ),
      ),
    );
  }
}
