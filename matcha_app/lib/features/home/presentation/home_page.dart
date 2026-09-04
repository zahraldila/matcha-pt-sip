import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/domain/models/user_model.dart';

class HomePage extends StatelessWidget {
  final UserModel? user;
  final VoidCallback? onCreateSessionTap;
  final VoidCallback? onLiveSessionTap;
  final VoidCallback? onManagePlayersTap;
  final VoidCallback? onManageCourtsTap;
  final VoidCallback? onCommunityTap;

  const HomePage({
    super.key,
    this.user,
    this.onCreateSessionTap,
    this.onLiveSessionTap,
    this.onManagePlayersTap,
    this.onManageCourtsTap,
    this.onCommunityTap,
  });

  @override
  Widget build(BuildContext context) {
    final isHost = user?.isHost ?? true;
    final userName = user?.nama.isNotEmpty == true ? user!.nama : 'Host';

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Header (Greeting & Notification)
              _buildHeader(userName),
              const SizedBox(height: 24),

              // 2. Live Session Card (Prominent Section)
              _buildLiveSessionCard(context),
              const SizedBox(height: 24),

              // 3. Upcoming Session Card
              _buildUpcomingSessionCard(),
              const SizedBox(height: 24),

              // 4. Quick Action Grid (Khusus Host / Sesuai Permission)
              if (isHost) ...[
                _buildQuickActionSection(),
                const SizedBox(height: 24),
              ],

              // 5. Ringkasan Statistik
              _buildSummaryStats(),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  // --- WIDGET SECTIONS ---

  Widget _buildHeader(String userName) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  'Halo, $userName!',
                  style: AppTextStyles.pageTitle.copyWith(fontSize: 22),
                ),
                const SizedBox(width: 6),
                const Text('👋', style: TextStyle(fontSize: 20)),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              'Sabtu, 6 September 2026',
              style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary),
            ),
          ],
        ),
        Container(
          decoration: BoxDecoration(
            color: AppColors.surface,
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.surfaceBorder),
          ),
          child: IconButton(
            icon: const Icon(Icons.notifications_outlined, color: AppColors.textPrimary, size: 22),
            onPressed: () {},
          ),
        ),
      ],
    );
  }

  Widget _buildLiveSessionCard(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.35)),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.05),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Badges
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'LIVE SESSION',
                style: AppTextStyles.badge.copyWith(
                  color: AppColors.primary,
                  letterSpacing: 1.5,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 6,
                      height: 6,
                      decoration: const BoxDecoration(
                        color: AppColors.primary,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 5),
                    Text(
                      'LIVE',
                      style: AppTextStyles.badge.copyWith(
                        color: AppColors.primary,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Session Title & Meta
          Text('Saturday Morning', style: AppTextStyles.sectionTitle.copyWith(fontSize: 17)),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.sports_tennis_rounded, size: 15, color: AppColors.primary),
              const SizedBox(width: 6),
              Text(
                'Tennis · 8 Players · 2 Courts',
                style: AppTextStyles.bodySecondary.copyWith(fontSize: 13),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Court 1 & Court 2 Live Score Cards
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceSecondary,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppColors.surfaceBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Court 1', style: AppTextStyles.caption.copyWith(color: AppColors.primary, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 4),
                      Text('Aldi · Budi', style: AppTextStyles.caption.copyWith(color: AppColors.textPrimary), overflow: TextOverflow.ellipsis),
                      const SizedBox(height: 2),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('6 — 4', style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.bold, color: AppColors.primary)),
                          Text('Caca · Dina', style: AppTextStyles.caption.copyWith(fontSize: 10, color: AppColors.textSecondary), overflow: TextOverflow.ellipsis),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceSecondary,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppColors.surfaceBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Court 2', style: AppTextStyles.caption.copyWith(color: AppColors.primary, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 4),
                      Text('Eka · Fajar', style: AppTextStyles.caption.copyWith(color: AppColors.textPrimary), overflow: TextOverflow.ellipsis),
                      const SizedBox(height: 2),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('3 — 2', style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.bold, color: AppColors.primary)),
                          Text('Gilang · Hadi', style: AppTextStyles.caption.copyWith(fontSize: 10, color: AppColors.textSecondary), overflow: TextOverflow.ellipsis),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Action Button
          ElevatedButton(
            onPressed: onLiveSessionTap,
            child: const Text('MASUK KE SESSION'),
          ),
        ],
      ),
    );
  }

  Widget _buildUpcomingSessionCard() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text('UPCOMING SESSION', style: AppTextStyles.badge.copyWith(color: AppColors.textSecondary, letterSpacing: 1.5)),
            Text('Lihat Semua >', style: AppTextStyles.caption.copyWith(color: AppColors.primary, fontWeight: FontWeight.w600)),
          ],
        ),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.surfaceBorder),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSecondary,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.sports_tennis_rounded, color: AppColors.primary, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Friday Night Play', style: AppTextStyles.cardTitle.copyWith(fontSize: 15)),
                    const SizedBox(height: 4),
                    Text('Padel · 6 Players · 1 Court', style: AppTextStyles.caption),
                    const SizedBox(height: 4),
                    Text('📅 7 Sep 2026 · 18:00', style: AppTextStyles.caption.copyWith(color: AppColors.primary)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: AppColors.textSecondary),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildQuickActionSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('QUICK ACTION', style: AppTextStyles.badge.copyWith(color: AppColors.textSecondary, letterSpacing: 1.5)),
        const SizedBox(height: 12),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            _buildActionItem(
              icon: Icons.add_circle_outline_rounded,
              label: 'Buat Session',
              onTap: onCreateSessionTap,
            ),
            _buildActionItem(
              icon: Icons.people_alt_outlined,
              label: 'Kelola Player',
              onTap: onManagePlayersTap,
            ),
            _buildActionItem(
              icon: Icons.stadium_outlined,
              label: 'Kelola Court',
              onTap: onManageCourtsTap,
            ),
            _buildActionItem(
              icon: Icons.diversity_3_outlined,
              label: 'Community',
              onTap: onCommunityTap,
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildActionItem({
    required IconData icon,
    required String label,
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: 76,
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.surfaceBorder),
        ),
        child: Column(
          children: [
            Icon(icon, color: AppColors.primary, size: 24),
            const SizedBox(height: 8),
            Text(
              label,
              textAlign: TextAlign.center,
              style: AppTextStyles.caption.copyWith(fontSize: 10, fontWeight: FontWeight.w500),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSummaryStats() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('RINGKASAN', style: AppTextStyles.badge.copyWith(color: AppColors.textSecondary, letterSpacing: 1.5)),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.surfaceBorder),
          ),
          child: const Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _StatItem(count: '8', label: 'Players'),
              _StatDivider(),
              _StatItem(count: '2', label: 'Courts'),
              _StatDivider(),
              _StatItem(count: '3', label: 'Session\nAktif'),
              _StatDivider(),
              _StatItem(count: '12', label: 'Total\nSesi'),
            ],
          ),
        ),
      ],
    );
  }
}

class _StatItem extends StatelessWidget {
  final String count;
  final String label;

  const _StatItem({required this.count, required this.label});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          count,
          style: AppTextStyles.pageTitle.copyWith(fontSize: 20, color: AppColors.primary),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          textAlign: TextAlign.center,
          style: AppTextStyles.caption.copyWith(fontSize: 10, color: AppColors.textSecondary),
        ),
      ],
    );
  }
}

class _StatDivider extends StatelessWidget {
  const _StatDivider();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      height: 30,
      color: AppColors.surfaceBorder,
    );
  }
}
