import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';

class ProfilePage extends StatefulWidget {
  final AuthController? authController;

  const ProfilePage({super.key, this.authController});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final MockDataService _dataService = MockDataService();

  @override
  void initState() {
    super.initState();
    _dataService.addListener(_onDataChanged);
    widget.authController?.addListener(_onDataChanged);
  }

  @override
  void dispose() {
    _dataService.removeListener(_onDataChanged);
    widget.authController?.removeListener(_onDataChanged);
    super.dispose();
  }

  void _onDataChanged() {
    if (mounted) setState(() {});
  }

  Future<void> _handleLogout() async {
    final shouldLogout = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Keluar dari Akun?'),
        content: const Text('Kamu perlu masuk kembali untuk mengakses fitur mabar dan kelola skor.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal', style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.redAccent,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );

    if (shouldLogout == true && mounted) {
      final authCtrl = widget.authController ?? AuthController();
      await authCtrl.logout();

      if (mounted) {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(
            builder: (ctx) => LoginPage(authController: authCtrl),
          ),
          (route) => false,
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final mockUser = _dataService.currentUser;
    final loggedInUser = widget.authController?.currentUser;

    final userName = loggedInUser?.nama ?? mockUser.name;
    final userEmail = loggedInUser?.email ?? 'marcel@matcha.id';
    final userLevel = loggedInUser?.level ?? 'Advanced';
    final isHost = loggedInUser?.isHost ?? _dataService.isHostMode;
    final avatarUrl = loggedInUser?.foto ?? mockUser.avatarUrl;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Profil Pemain',
          style: AppTextStyles.h2.copyWith(fontSize: 18, color: context.txtPrimary),
        ),
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // User Header Card
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: context.surf,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: context.surfBorder),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.02),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 38,
                    backgroundImage: NetworkImage(avatarUrl),
                    backgroundColor: context.surfBorder,
                    onBackgroundImageError: (e, stack) {},
                    child: avatarUrl.isEmpty
                        ? Text(
                            userName.isNotEmpty ? userName[0].toUpperCase() : 'U',
                            style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                          )
                        : null,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    userName,
                    style: AppTextStyles.h1.copyWith(fontSize: 18, color: context.txtPrimary),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    userEmail,
                    style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 12),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppColors.matchaSoftLime,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                        ),
                        child: Text(
                          'Tier $userLevel',
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: AppColors.matchaDark,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: context.surfSec,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: context.surfBorder),
                        ),
                        child: Text(
                          isHost ? '👑 Host Terverifikasi' : '👤 Personal Member',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: context.txtSecondary,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Toggle Host Mode Card
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
              decoration: BoxDecoration(
                color: isHost ? AppColors.matchaSoftLime : context.surf,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: isHost ? AppColors.matchaSoftLimeBorder : context.surfBorder,
                  width: 1,
                ),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: isHost ? Colors.white : context.surfSec,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      isHost ? Icons.sports_tennis_rounded : Icons.person_outline_rounded,
                      color: isHost ? AppColors.matchaDark : Colors.grey,
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Mode Host / Pembuat Mabar',
                          style: AppTextStyles.bodyMedium.copyWith(
                            fontWeight: FontWeight.bold,
                            color: context.txtPrimary,
                            fontSize: 13,
                          ),
                        ),
                        Text(
                          isHost
                              ? 'Kamu dapat mengelola sesi & input skor'
                              : 'Aktifkan untuk menjadi host mabar',
                          style: AppTextStyles.caption.copyWith(
                            color: context.txtSecondary,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Switch(
                    value: isHost,
                    activeThumbColor: Colors.white,
                    activeTrackColor: AppColors.matchaDark,
                    onChanged: (val) {
                      if (widget.authController != null) {
                        widget.authController!.toggleHost();
                      } else {
                        _dataService.toggleHostMode();
                      }
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text(
                            val
                                ? 'Mode Host diaktifkan! 🎾'
                                : 'Mode Host dinonaktifkan (Pemain Biasa) 👤',
                          ),
                          behavior: SnackBarBehavior.floating,
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // Performance & Career Stats
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Statistik Karir',
                style: AppTextStyles.h2.copyWith(fontSize: 15, color: context.txtPrimary),
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                _buildStatTile(context, 'Total Match', '${mockUser.totalMatches}', Icons.sports_tennis),
                const SizedBox(width: 10),
                _buildStatTile(context, 'Win Rate', '${mockUser.winRate}%', Icons.trending_up_rounded),
                const SizedBox(width: 10),
                _buildStatTile(context, 'Kudos 🔥', '${mockUser.kudosCount}', Icons.local_fire_department_rounded),
              ],
            ),

            const SizedBox(height: 22),

            // Riwayat Match Terakhir
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Riwayat Pertandingan Terakhir',
                style: AppTextStyles.h2.copyWith(fontSize: 15, color: context.txtPrimary),
              ),
            ),
            const SizedBox(height: 10),
            _buildMatchHistoryTile(
              context,
              date: '18 Sep 2026',
              venue: 'Sunset Padel Court Kemang',
              score: '21 - 18 • Win 🏆',
              isWin: true,
            ),
            const SizedBox(height: 8),
            _buildMatchHistoryTile(
              context,
              date: '14 Sep 2026',
              venue: 'Gelora Tennis Center',
              score: '19 - 21 • Loss',
              isWin: false,
            ),

            const SizedBox(height: 24),

            // Logout Button
            SizedBox(
              width: double.infinity,
              height: 48,
              child: OutlinedButton.icon(
                onPressed: _handleLogout,
                icon: const Icon(Icons.logout_rounded, color: Colors.redAccent, size: 18),
                label: const Text(
                  'Keluar dari Akun (Logout)',
                  style: TextStyle(
                    color: Colors.redAccent,
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Color(0xFFFECACA)),
                  backgroundColor: const Color(0xFFFEF2F2),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildStatTile(BuildContext context, String label, String value, IconData icon) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 10),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: context.surfBorder),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          children: [
            Icon(icon, size: 18, color: AppColors.matchaDark),
            const SizedBox(height: 6),
            Text(
              value,
              style: AppTextStyles.bodyMedium.copyWith(
                fontWeight: FontWeight.bold,
                fontSize: 14,
                color: context.txtPrimary,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 10),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMatchHistoryTile(
    BuildContext context, {
    required String date,
    required String venue,
    required String score,
    required bool isWin,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: isWin ? AppColors.matchaSoftLime : Colors.redAccent.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              isWin ? Icons.emoji_events_rounded : Icons.sports_score_rounded,
              color: isWin ? AppColors.matchaDark : Colors.redAccent,
              size: 18,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  venue,
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: context.txtPrimary,
                    fontSize: 13,
                  ),
                ),
                Text(
                  date,
                  style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                ),
              ],
            ),
          ),
          Text(
            score,
            style: TextStyle(
              fontWeight: FontWeight.bold,
              color: isWin ? AppColors.matchaDark : Colors.redAccent,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}
