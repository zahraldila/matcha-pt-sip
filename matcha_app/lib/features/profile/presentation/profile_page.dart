import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/theme/theme_controller.dart';
import '../../auth/domain/models/user_model.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../player/presentation/controllers/player_controller.dart';

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
  late final PlayerController _playerController;

  @override
  void initState() {
    super.initState();
    _playerController = PlayerController();
    _loadPlayerStats();
  }

  void _loadPlayerStats() {
    if (widget.user != null) {
      _playerController.loadPlayerStats(widget.user!.userId);
    }
  }

  @override
  void dispose() {
    _playerController.dispose();
    super.dispose();
  }

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

  void _showChangePasswordSheet() {
    final formKey = GlobalKey<FormState>();
    final oldPasswordController = TextEditingController();
    final newPasswordController = TextEditingController();
    final confirmPasswordController = TextEditingController();

    widget.authController.clearPasswordError();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(sheetContext).viewInsets.bottom),
          child: Container(
            decoration: BoxDecoration(
              color: context.surf,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
              border: Border(top: BorderSide(color: context.surfBorder)),
            ),
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
            child: ListenableBuilder(
              listenable: widget.authController,
              builder: (context, _) {
                final passwordError = widget.authController.passwordError;

                return Form(
                  key: formKey,
                  child: SingleChildScrollView(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Center(
                          child: Container(
                            width: 48,
                            height: 4,
                            decoration: BoxDecoration(
                              color: context.txtSecondary.withValues(alpha: 0.3),
                              borderRadius: BorderRadius.circular(50),
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'Ubah Kata Sandi',
                          style: AppTextStyles.cardTitle.copyWith(color: context.txtPrimary),
                        ),
                        const SizedBox(height: 20),
                        TextFormField(
                          controller: oldPasswordController,
                          obscureText: true,
                          style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                          decoration: InputDecoration(
                            labelText: 'Kata Sandi Lama',
                            labelStyle: AppTextStyles.caption.copyWith(color: context.txtSecondary),
                            filled: true,
                            fillColor: context.bg,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide(color: context.surfBorder),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide(color: context.surfBorder),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide(color: context.brandColor),
                            ),
                          ),
                          validator: (value) {
                            final text = value?.trim() ?? '';
                            if (text.isEmpty) return 'Kata sandi lama wajib diisi.';
                            if (text.length < 6) return 'Minimal 6 karakter.';
                            return null;
                          },
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          controller: newPasswordController,
                          obscureText: true,
                          style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                          decoration: InputDecoration(
                            labelText: 'Kata Sandi Baru',
                            labelStyle: AppTextStyles.caption.copyWith(color: context.txtSecondary),
                            filled: true,
                            fillColor: context.bg,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide(color: context.surfBorder),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide(color: context.surfBorder),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide(color: context.brandColor),
                            ),
                          ),
                          validator: (value) {
                            final text = value?.trim() ?? '';
                            if (text.isEmpty) return 'Kata sandi baru wajib diisi.';
                            if (text.length < 6) return 'Minimal 6 karakter.';
                            return null;
                          },
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          controller: confirmPasswordController,
                          obscureText: true,
                          style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                          decoration: InputDecoration(
                            labelText: 'Konfirmasi Kata Sandi Baru',
                            labelStyle: AppTextStyles.caption.copyWith(color: context.txtSecondary),
                            filled: true,
                            fillColor: context.bg,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide(color: context.surfBorder),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide(color: context.surfBorder),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: BorderSide(color: context.brandColor),
                            ),
                          ),
                          validator: (value) {
                            final text = value?.trim() ?? '';
                            if (text.isEmpty) return 'Konfirmasi kata sandi wajib diisi.';
                            if (text.length < 6) return 'Minimal 6 karakter.';
                            if (newPasswordController.text.trim() != text) {
                              return 'Konfirmasi kata sandi tidak cocok.';
                            }
                            return null;
                          },
                        ),
                        if (passwordError != null) ... [
                          const SizedBox(height: 12),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: AppColors.error.withValues(alpha: 0.08),
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: AppColors.error.withValues(alpha: 0.25)),
                            ),
                            child: Text(
                              passwordError,
                              style: AppTextStyles.caption.copyWith(color: AppColors.error),
                            ),
                          ),
                        ],
                        const SizedBox(height: 20),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: widget.authController.isChangingPassword
                                ? null
                                : () async {
                                    if (!formKey.currentState!.validate()) return;

                                    final success = await widget.authController.changePassword(
                                      oldPassword: oldPasswordController.text,
                                      newPassword: newPasswordController.text,
                                    );

                                    if (!mounted) return;

                                    if (success) {
                                      Navigator.of(sheetContext).pop();
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(
                                          content: Text(
                                            'Kata sandi berhasil diperbarui.',
                                            style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                                          ),
                                          backgroundColor: context.surf,
                                          behavior: SnackBarBehavior.floating,
                                        ),
                                      );
                                    }
                                  },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: context.brandColor,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: widget.authController.isChangingPassword
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                  )
                                : const Text('Simpan Kata Sandi'),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        );
      },
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

              // Statistics Cards Section
              Text(
                'STATISTIK PERFORMA',
                style: AppTextStyles.badge.copyWith(color: context.txtSecondary, letterSpacing: 1.5),
              ),
              const SizedBox(height: 12),

              ListenableBuilder(
                listenable: _playerController,
                builder: (context, _) {
                  if (_playerController.isLoading) {
                    return Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: context.surf,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: context.surfBorder),
                      ),
                      child: const Center(child: CircularProgressIndicator()),
                    );
                  }

                  if (_playerController.errorMessage != null) {
                    return Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: context.surf,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: context.surfBorder),
                      ),
                      child: Column(
                        children: [
                          Icon(Icons.info_outline, color: context.txtSecondary, size: 32),
                          const SizedBox(height: 8),
                          Text(
                            'Data performa tidak dapat dimuat',
                            style: AppTextStyles.body.copyWith(color: context.txtSecondary),
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    );
                  }

                  return GridView.count(
                    crossAxisCount: 2,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    children: [
                      _buildStatCard(
                        label: 'Total Pertandingan',
                        value: _playerController.totalMatches.toString(),
                        icon: Icons.sports_tennis_rounded,
                      ),
                      _buildStatCard(
                        label: 'Win Rate',
                        value: '${_playerController.winRate.toStringAsFixed(1)}%',
                        icon: Icons.trending_up_rounded,
                      ),
                      _buildStatCard(
                        label: 'Menang',
                        value: _playerController.wins.toString(),
                        icon: Icons.check_circle_outline_rounded,
                      ),
                      _buildStatCard(
                        label: 'Kalah',
                        value: _playerController.losses.toString(),
                        icon: Icons.cancel_outlined,
                      ),
                    ],
                  );
                },
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
                      onTap: _showChangePasswordSheet,
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

  Widget _buildStatCard({
    required String label,
    required String value,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: context.brandColor, size: 26),
          const SizedBox(height: 12),
          Text(
            value,
            style: AppTextStyles.pageTitle.copyWith(
              fontSize: 20,
              color: context.txtPrimary,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: AppTextStyles.caption.copyWith(
              color: context.txtSecondary,
            ),
          ),
        ],
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
