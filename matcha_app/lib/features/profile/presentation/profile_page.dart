import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/theme/theme_controller.dart';
import '../../auth/domain/models/user_model.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';

class ProfilePage extends StatefulWidget {
  final UserModel? user;
  final AuthController authController;

  const ProfilePage({
    super.key,
    required this.user,
    required this.authController,
  });

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  bool _notificationEnabled = true;
  final ThemeController _themeController = ThemeController();

  void _handleLogout() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: context.surf,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: context.surfBorder),
        ),
        title: Text('Konfirmasi Keluar', style: AppTextStyles.cardTitle.copyWith(color: context.txtPrimary)),
        content: Text(
          'Apakah Anda yakin ingin keluar dari akun?',
          style: AppTextStyles.bodySecondary.copyWith(color: context.txtSecondary),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('Batal', style: AppTextStyles.caption.copyWith(color: context.txtSecondary)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
              foregroundColor: Colors.white,
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
    final name = widget.user?.nama ?? 'User';
    final email = widget.user?.email ?? 'user@matcha.com';
    final role = widget.user?.role ?? 'Personal User';
    final isHost = widget.user?.isHost ?? false;

    return Scaffold(
      backgroundColor: context.bg,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Profil Pengguna',
                style: AppTextStyles.pageTitle.copyWith(fontSize: 22, color: context.txtPrimary),
              ),
              const SizedBox(height: 16),

              // User Info Card
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: context.surf,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: context.surfBorder),
                ),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 30,
                      backgroundColor: isHost
                          ? context.brandColor.withValues(alpha: 0.2)
                          : AppColors.info.withValues(alpha: 0.2),
                      child: Text(
                        name.isNotEmpty ? name[0].toUpperCase() : 'U',
                        style: AppTextStyles.pageTitle.copyWith(
                          color: isHost ? context.brandColor : AppColors.info,
                          fontSize: 24,
                        ),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            name,
                            style: AppTextStyles.cardTitle.copyWith(fontSize: 17, color: context.txtPrimary),
                          ),
                          const SizedBox(height: 2),
                          Text(email, style: AppTextStyles.caption.copyWith(color: context.txtSecondary)),
                          const SizedBox(height: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: isHost
                                  ? context.brandColor.withValues(alpha: 0.15)
                                  : AppColors.info.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              role.toUpperCase(),
                              style: AppTextStyles.badge.copyWith(
                                color: isHost ? context.brandColor : AppColors.info,
                                fontSize: 10,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Section: Pengaturan Akun
              Text(
                'PENGATURAN TAMPILAN & AKUN',
                style: AppTextStyles.badge.copyWith(color: context.txtSecondary, letterSpacing: 1.5),
              ),
              const SizedBox(height: 12),

              Container(
                decoration: BoxDecoration(
                  color: context.surf,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: context.surfBorder),
                ),
                child: Column(
                  children: [
                    // Theme Switch Tile (Dark / Light Mode)
                    ListTile(
                      leading: Icon(
                        _themeController.isDarkMode ? Icons.dark_mode_rounded : Icons.light_mode_rounded,
                        color: context.brandColor,
                        size: 22,
                      ),
                      title: Text(
                        _themeController.isDarkMode ? 'Mode Gelap (Dark Mode)' : 'Mode Terang (Light Mode)',
                        style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                      ),
                      subtitle: Text(
                        _themeController.isDarkMode ? 'Tema sporty gelap aktif' : 'Tema terang bersih aktif',
                        style: AppTextStyles.caption.copyWith(color: context.txtSecondary),
                      ),
                      trailing: Switch(
                        value: _themeController.isDarkMode,
                        activeTrackColor: context.brandColor,
                        onChanged: (isDark) {
                          setState(() {
                            _themeController.toggleTheme(isDark);
                          });
                        },
                      ),
                    ),
                    Divider(height: 1, indent: 56, color: context.surfBorder),
                    _buildSettingsTile(
                      icon: Icons.lock_outline_rounded,
                      title: 'Ubah Kata Sandi',
                      onTap: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              'Fitur ubah kata sandi siap dikonfigurasi.',
                              style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                            ),
                            backgroundColor: context.surf,
                            behavior: SnackBarBehavior.floating,
                          ),
                        );
                      },
                    ),
                    Divider(height: 1, indent: 56, color: context.surfBorder),
                    ListTile(
                      leading: Icon(Icons.notifications_outlined, color: context.brandColor, size: 22),
                      title: Text('Notifikasi Pertandingan', style: AppTextStyles.body.copyWith(color: context.txtPrimary)),
                      trailing: Switch(
                        value: _notificationEnabled,
                        activeTrackColor: context.brandColor,
                        onChanged: (val) => setState(() => _notificationEnabled = val),
                      ),
                    ),
                    Divider(height: 1, indent: 56, color: context.surfBorder),
                    _buildSettingsTile(
                      icon: Icons.info_outline_rounded,
                      title: 'Tentang Aplikasi Matcha',
                      subtitle: 'Versi 1.0.0 (Match Arena)',
                      onTap: () {},
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 32),

              // Logout Action Button
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.error,
                  side: BorderSide(color: AppColors.error.withValues(alpha: 0.5)),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                onPressed: _handleLogout,
                icon: const Icon(Icons.logout_rounded, size: 20),
                label: const Text('Keluar dari Akun'),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSettingsTile({
    required IconData icon,
    required String title,
    String? subtitle,
    required VoidCallback onTap,
  }) {
    return ListTile(
      leading: Icon(icon, color: context.brandColor, size: 22),
      title: Text(title, style: AppTextStyles.body.copyWith(color: context.txtPrimary)),
      subtitle: subtitle != null ? Text(subtitle, style: AppTextStyles.caption.copyWith(color: context.txtSecondary)) : null,
      trailing: Icon(Icons.chevron_right_rounded, color: context.txtSecondary, size: 20),
      onTap: onTap,
    );
  }
}
