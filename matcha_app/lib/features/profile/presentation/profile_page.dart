import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../auth/presentation/register_page.dart';

class ProfilePage extends StatefulWidget {
  final AuthController? authController;

  const ProfilePage({super.key, this.authController});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> with SingleTickerProviderStateMixin {
  late AnimationController _animController;
  late Animation<double> _pulseAnimation;

  @override
  void initState() {
    super.initState();
    _animController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 2),
    )..repeat(reverse: true);

    _pulseAnimation = Tween<double>(begin: 0.95, end: 1.05).animate(
      CurvedAnimation(parent: _animController, curve: Curves.easeInOut),
    );

    widget.authController?.addListener(_onAuthChanged);
  }

  @override
  void dispose() {
    _animController.dispose();
    widget.authController?.removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
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
    final user = widget.authController?.currentUser;

    // Jika user adalah Guest (belum login), tampilkan Guest Locked State
    if (user == null) {
      return _buildGuestProfileView();
    }

    // Jika user sudah login, tampilkan Profil Lengkap
    return _buildLoggedInProfileView(user);
  }

  /// Tampilan Profil untuk Pemain Tamu (Guest)
  Widget _buildGuestProfileView() {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: Text(
          'Profil Pemain',
          style: AppTextStyles.h2.copyWith(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF0F172A),
          ),
        ),
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
        child: Column(
          children: [
            const SizedBox(height: 10),
            // Animated Glowing Tennis Badge
            ScaleTransition(
              scale: _pulseAnimation,
              child: Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    colors: [Color(0xFF063B00), Color(0xFF1E5B10)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.matchaDark.withValues(alpha: 0.3),
                      blurRadius: 20,
                      spreadRadius: 2,
                    ),
                  ],
                ),
                child: const Center(
                  child: Icon(
                    Icons.sports_tennis_rounded,
                    size: 48,
                    color: Color(0xFFA8E63A),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 20),

            // Badge Status Tamu
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF3C7),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFF59E0B).withValues(alpha: 0.4)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.lock_outline_rounded, size: 13, color: Color(0xFFD97706)),
                  const SizedBox(width: 5),
                  Text(
                    'MODE TAMU (GUEST)',
                    style: AppTextStyles.badge.copyWith(
                      color: const Color(0xFFD97706),
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.5,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),

            Text(
              'Akses Profil & Statistik Mabar',
              textAlign: TextAlign.center,
              style: AppTextStyles.h1.copyWith(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Masuk atau buat akun MATCHA untuk menyimpan riwayat bermain, tracking win rate, mengumpulkan Kudos 🔥, dan mengaktifkan hak akses Host Game.',
              textAlign: TextAlign.center,
              style: AppTextStyles.caption.copyWith(
                fontSize: 13,
                color: const Color(0xFF64748B),
                height: 1.5,
              ),
            ),
            const SizedBox(height: 24),

            // Benefit List Card
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFE2E8F0)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.02),
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: Column(
                children: [
                  _buildBenefitRow(
                    icon: Icons.flash_on_rounded,
                    color: const Color(0xFFEAB308),
                    title: 'Gabung Mabar 1-Klik Instan',
                    desc: 'Tidak perlu input ulang nama dan skill level tiap gabung.',
                  ),
                  const Divider(height: 20, color: Color(0xFFF1F5F9)),
                  _buildBenefitRow(
                    icon: Icons.sports_tennis_rounded,
                    color: AppColors.matchaDark,
                    title: 'Akses Mode Host Game',
                    desc: 'Bikin jadwal mabar baru, acak drawing, dan input skor live.',
                  ),
                  const Divider(height: 20, color: Color(0xFFF1F5F9)),
                  _buildBenefitRow(
                    icon: Icons.groups_rounded,
                    color: const Color(0xFF3B82F6),
                    title: 'Bikin Komunitas Olahraga',
                    desc: 'Bebas bangun komunitas Tennis & Padel kamu sendiri.',
                  ),
                  const Divider(height: 20, color: Color(0xFFF1F5F9)),
                  _buildBenefitRow(
                    icon: Icons.local_fire_department_rounded,
                    color: Colors.deepOrange,
                    title: 'Koleksi Kudos & Win Rate',
                    desc: 'Simpan penghargaan MVP dan rasio kemenangan turnamen.',
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Action Buttons
            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => LoginPage(authController: widget.authController),
                    ),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.matchaDark,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.login_rounded, size: 18),
                    SizedBox(width: 8),
                    Text(
                      'Masuk ke Akun',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: OutlinedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => RegisterPage(authController: widget.authController),
                    ),
                  );
                },
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.matchaDark,
                  side: const BorderSide(color: AppColors.matchaDark, width: 1.5),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                child: const Text(
                  'Daftar Member Baru',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                ),
              ),
            ),
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }

  Widget _buildBenefitRow({
    required IconData icon,
    required Color color,
    required String title,
    required String desc,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: color, size: 18),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: AppTextStyles.caption.copyWith(
                  fontWeight: FontWeight.bold,
                  color: const Color(0xFF0F172A),
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                desc,
                style: AppTextStyles.caption.copyWith(
                  color: const Color(0xFF64748B),
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  /// Tampilan Profil untuk Pengguna yang Sudah Login
  Widget _buildLoggedInProfileView(dynamic user) {
    final userName = user.nama ?? 'Pemain';
    final userEmail = user.email ?? 'email@matcha.id';
    final userLevel = user.level ?? 'Intermediate';
    final isHost = user.isHost ?? false;
    final avatarUrl = user.foto;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: Text(
          'Profil Pemain',
          style: AppTextStyles.h2.copyWith(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF0F172A),
          ),
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
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFE2E8F0)),
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
                    backgroundImage: (avatarUrl != null && avatarUrl.isNotEmpty)
                        ? NetworkImage(avatarUrl)
                        : null,
                    backgroundColor: AppColors.matchaSoftLime,
                    child: (avatarUrl == null || avatarUrl.isEmpty)
                        ? Text(
                            userName.isNotEmpty ? userName[0].toUpperCase() : 'U',
                            style: const TextStyle(
                              fontSize: 24,
                              fontWeight: FontWeight.bold,
                              color: AppColors.matchaDark,
                            ),
                          )
                        : null,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    userName,
                    style: AppTextStyles.h1.copyWith(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    userEmail,
                    style: AppTextStyles.caption.copyWith(color: const Color(0xFF64748B), fontSize: 12),
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
                          'Level $userLevel',
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
                          color: isHost ? const Color(0xFFEFF6FF) : const Color(0xFFF1F5F9),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: isHost ? const Color(0xFF93C5FD) : const Color(0xFFCBD5E1),
                          ),
                        ),
                        child: Text(
                          isHost ? '👑 Host Game Active' : '👤 Personal Member',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: isHost ? const Color(0xFF1D4ED8) : const Color(0xFF64748B),
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
                color: isHost ? AppColors.matchaSoftLime : Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: isHost ? AppColors.matchaSoftLimeBorder : const Color(0xFFE2E8F0),
                  width: 1,
                ),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: isHost ? Colors.white : const Color(0xFFF1F5F9),
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
                            color: const Color(0xFF0F172A),
                            fontSize: 13,
                          ),
                        ),
                        Text(
                          isHost
                              ? 'Kamu dapat mengelola sesi & input skor'
                              : 'Aktifkan untuk menjadi host mabar',
                          style: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF64748B),
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
                      widget.authController?.toggleHost();
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
                style: AppTextStyles.h2.copyWith(fontSize: 15, color: const Color(0xFF0F172A)),
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                _buildStatTile('Total Match', '0 Match', Icons.sports_tennis),
                const SizedBox(width: 10),
                _buildStatTile('Win Rate', '0%', Icons.trending_up_rounded),
                const SizedBox(width: 10),
                _buildStatTile('Kudos 🔥', '0 Kudos', Icons.local_fire_department_rounded),
              ],
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

  Widget _buildStatTile(String label, String value, IconData icon) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE2E8F0)),
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
                fontSize: 13,
                color: const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: AppTextStyles.caption.copyWith(color: const Color(0xFF64748B), fontSize: 10),
            ),
          ],
        ),
      ),
    );
  }
}
