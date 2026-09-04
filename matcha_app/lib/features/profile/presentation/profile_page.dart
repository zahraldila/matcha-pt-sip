import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
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
    final name = widget.user?.nama ?? 'User';
    final email = widget.user?.email ?? 'user@matcha.com';
    final role = widget.user?.role ?? 'Personal User';
    final isHost = widget.user?.isHost ?? false;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Profil Pengguna', style: AppTextStyles.pageTitle.copyWith(fontSize: 22)),
              const SizedBox(height: 16),

              // User Info Card
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
                      radius: 30,
                      backgroundColor: isHost
                          ? AppColors.primary.withValues(alpha: 0.2)
                          : AppColors.info.withValues(alpha: 0.2),
                      child: Text(
                        name.isNotEmpty ? name[0].toUpperCase() : 'U',
                        style: AppTextStyles.pageTitle.copyWith(
                          color: isHost ? AppColors.primary : AppColors.info,
                          fontSize: 24,
                        ),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(name, style: AppTextStyles.cardTitle.copyWith(fontSize: 17)),
                          const SizedBox(height: 2),
                          Text(email, style: AppTextStyles.caption),
                          const SizedBox(height: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: isHost
                                  ? AppColors.primary.withValues(alpha: 0.15)
                                  : AppColors.info.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              role.toUpperCase(),
                              style: AppTextStyles.badge.copyWith(
                                color: isHost ? AppColors.primary : AppColors.info,
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
              Text('PENGATURAN', style: AppTextStyles.badge.copyWith(color: AppColors.textSecondary, letterSpacing: 1.5)),
              const SizedBox(height: 12),

              Container(
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.surfaceBorder),
                ),
                child: Column(
                  children: [
                    _buildSettingsTile(
                      icon: Icons.lock_outline_rounded,
                      title: 'Ubah Kata Sandi',
                      onTap: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              'Fitur ubah kata sandi siap dikonfigurasi.',
                              style: AppTextStyles.body.copyWith(color: AppColors.textPrimary),
                            ),
                            backgroundColor: AppColors.surfaceSecondary,
                            behavior: SnackBarBehavior.floating,
                          ),
                        );
                      },
                    ),
                    const Divider(height: 1, indent: 56),
                    ListTile(
                      leading: const Icon(Icons.notifications_outlined, color: AppColors.primary, size: 22),
                      title: Text('Notifikasi Pertandingan', style: AppTextStyles.body),
                      trailing: Switch(
                        value: _notificationEnabled,
                        activeTrackColor: AppColors.primary,
                        onChanged: (val) => setState(() => _notificationEnabled = val),
                      ),
                    ),
                    const Divider(height: 1, indent: 56),
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
      leading: Icon(icon, color: AppColors.primary, size: 22),
      title: Text(title, style: AppTextStyles.body),
      subtitle: subtitle != null ? Text(subtitle, style: AppTextStyles.caption) : null,
      trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.textSecondary, size: 20),
      onTap: onTap,
    );
  }
}
