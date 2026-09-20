import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final MockDataService _dataService = MockDataService();

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
    final user = _dataService.currentUser;
    final isHost = _dataService.isHostMode;

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
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: context.surfBorder),
              ),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 40,
                    backgroundImage: NetworkImage(user.avatarUrl),
                    backgroundColor: context.surfBorder,
                  ),
                  const SizedBox(height: 14),
                  Text(
                    user.name,
                    style: AppTextStyles.h1.copyWith(fontSize: 18, color: context.txtPrimary),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'marcel@matcha.id',
                    style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 12),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppColors.primary.withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          'Tier ${user.tier}',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            color: context.brandColor,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: context.surfSec,
                          borderRadius: BorderRadius.circular(10),
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
                color: context.surf,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: isHost ? AppColors.primary.withValues(alpha: 0.5) : context.surfBorder,
                  width: 1.2,
                ),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: (isHost ? AppColors.primary : Colors.grey).withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(
                      isHost ? Icons.sports_tennis_rounded : Icons.person_outline_rounded,
                      color: isHost ? context.brandColor : Colors.grey,
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 14),
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
                    activeThumbColor: context.brandColor,
                    onChanged: (val) {
                      _dataService.toggleHostMode();
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
                _buildStatTile(context, 'Total Match', '${user.totalMatches}', Icons.sports_tennis),
                const SizedBox(width: 10),
                _buildStatTile(context, 'Win Rate', '${user.winRate}%', Icons.trending_up_rounded),
                const SizedBox(width: 10),
                _buildStatTile(context, 'Kudos 🔥', '${user.kudosCount}', Icons.local_fire_department_rounded),
              ],
            ),

            const SizedBox(height: 24),

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
        ),
        child: Column(
          children: [
            Icon(icon, size: 18, color: context.brandColor),
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
              color: (isWin ? AppColors.primary : Colors.redAccent).withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              isWin ? Icons.emoji_events_rounded : Icons.sports_score_rounded,
              color: isWin ? context.brandColor : Colors.redAccent,
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
              color: isWin ? context.brandColor : Colors.redAccent,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}
