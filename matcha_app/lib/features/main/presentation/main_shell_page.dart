import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../home/presentation/home_page.dart';
import '../../session/presentation/session_list_page.dart';
import '../../session/presentation/create_session_page.dart';
import '../../match/presentation/live_session_page.dart';
import '../../player/presentation/player_list_page.dart';
import '../../profile/presentation/profile_page.dart';

class MainShellPage extends StatefulWidget {
  final AuthController authController;

  const MainShellPage({super.key, required this.authController});

  @override
  State<MainShellPage> createState() => _MainShellPageState();
}

class _MainShellPageState extends State<MainShellPage> {
  int _currentIndex = 0;

  @override
  Widget build(BuildContext context) {
    final user = widget.authController.currentUser;
    final isHost = widget.authController.isHost;

    final tabs = [
      // Tab 0: Home Dashboard
      HomePage(
        user: user,
        onCreateSessionTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (context) => const CreateSessionPage()),
          );
        },
        onLiveSessionTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => LiveSessionPage(
                sessionName: 'Saturday Morning',
                sportName: 'Tennis',
                isHost: isHost,
              ),
            ),
          );
        },
        onManagePlayersTap: () => setState(() => _currentIndex = 2),
        onManageCourtsTap: () => setState(() => _currentIndex = 1),
        onCommunityTap: () => setState(() => _currentIndex = 2),
      ),

      // Tab 1: Session Management
      SessionListPage(
        isHost: isHost,
        onSessionTap: (id) {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => LiveSessionPage(
                sessionName: 'Saturday Morning',
                sportName: 'Tennis',
                isHost: isHost,
              ),
            ),
          );
        },
      ),

      // Tab 2: Players & Members
      PlayerListPage(isHost: isHost),

      // Tab 3: Profile & Settings
      ProfilePage(user: user, authController: widget.authController),
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
}
