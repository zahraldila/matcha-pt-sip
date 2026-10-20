import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';

class MatchRecapPage extends StatefulWidget {
  const MatchRecapPage({super.key});

  @override
  State<MatchRecapPage> createState() => _MatchRecapPageState();
}

class _MatchRecapPageState extends State<MatchRecapPage> {
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
    final kudos = _dataService.kudosCounts;
    final userKudos = _dataService.userGivenKudos;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Rekap Hasil Pertandingan',
          style: AppTextStyles.h2.copyWith(fontSize: 16, color: context.txtPrimary),
        ),
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.close_rounded),
          onPressed: () => Navigator.of(context).popUntil((route) => route.isFirst),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.share_outlined),
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Kartu rekap pertandingan siap dibagikan ke Instagram Story! 📸'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // Winner Banner (Light Mode Pastel Lime)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppColors.matchaSoftLime,
                borderRadius: BorderRadius.circular(22),
                border: Border.all(color: AppColors.matchaSoftLimeBorder, width: 1.5),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.matchaDark.withValues(alpha: 0.08),
                    blurRadius: 16,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                children: [
                  const Text('🏆', style: TextStyle(fontSize: 38)),
                  const SizedBox(height: 6),
                  const Text(
                    'TIM A MEMENANGKAN MATCH!',
                    style: TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 15,
                      color: AppColors.matchaDark,
                      letterSpacing: 0.5,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Marcel Santoso & Budi Pratama',
                    style: AppTextStyles.h3.copyWith(fontSize: 15, color: context.txtPrimary),
                  ),
                  const SizedBox(height: 14),
                  // Score Set Summary
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.matchaSoftLimeBorder),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        Column(
                          children: [
                            Text('Set 1', style: TextStyle(color: Colors.grey, fontSize: 11)),
                            SizedBox(height: 2),
                            Text('21 - 19', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.matchaDark, fontSize: 16)),
                          ],
                        ),
                        Text('•', style: TextStyle(color: Colors.grey)),
                        Column(
                          children: [
                            Text('Set 2', style: TextStyle(color: Colors.grey, fontSize: 11)),
                            SizedBox(height: 2),
                            Text('21 - 18', style: TextStyle(fontWeight: FontWeight.bold, color: AppColors.matchaDark, fontSize: 16)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Match Stat Pills (Strava-like)
            Row(
              children: [
                _buildMetricCard(context, label: 'Durasi Main', value: '42 Menit', icon: Icons.timer_outlined),
                const SizedBox(width: 10),
                _buildMetricCard(context, label: 'Total Rally', value: '79 Poin', icon: Icons.sports_tennis_rounded),
                const SizedBox(width: 10),
                _buildMetricCard(context, label: 'MVP Match', value: 'Marcel S.', icon: Icons.star_rounded),
              ],
            ),

            const SizedBox(height: 20),

            // Kudos Reaction Section
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
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Text('👏', style: TextStyle(fontSize: 20)),
                      const SizedBox(width: 8),
                      Text(
                        'Beri Kudos ke Pemain',
                        style: AppTextStyles.h3.copyWith(fontSize: 15, color: context.txtPrimary),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Apresiasi permainan seru teman dan lawan mainmu di lapangan!',
                    style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 12),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      _buildKudosButton(
                        context,
                        type: 'smash',
                        emoji: '🔥',
                        label: 'Smash King',
                        count: kudos['smash'] ?? 0,
                        isActive: userKudos.contains('smash'),
                      ),
                      const SizedBox(width: 10),
                      _buildKudosButton(
                        context,
                        type: 'speed',
                        emoji: '⚡',
                        label: 'Speedy',
                        count: kudos['speed'] ?? 0,
                        isActive: userKudos.contains('speed'),
                      ),
                      const SizedBox(width: 10),
                      _buildKudosButton(
                        context,
                        type: 'respect',
                        emoji: '👏',
                        label: 'Good Game',
                        count: kudos['respect'] ?? 0,
                        isActive: userKudos.contains('respect'),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Done Button
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.of(context).popUntil((route) => route.isFirst);
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.matchaDark,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('Selesai & Kembali ke Beranda', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMetricCard(
    BuildContext context, {
    required String label,
    required String value,
    required IconData icon,
  }) {
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
            Icon(icon, size: 18, color: AppColors.matchaDark),
            const SizedBox(height: 6),
            Text(
              value,
              style: AppTextStyles.bodyMedium.copyWith(fontWeight: FontWeight.bold, fontSize: 13, color: context.txtPrimary),
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

  Widget _buildKudosButton(
    BuildContext context, {
    required String type,
    required String emoji,
    required String label,
    required int count,
    required bool isActive,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: () => _dataService.toggleKudos(type),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          decoration: BoxDecoration(
            color: isActive
                ? AppColors.matchaSoftLime
                : context.surfSec,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: isActive ? AppColors.matchaDark : context.surfBorder,
              width: isActive ? 1.5 : 1,
            ),
          ),
          child: Column(
            children: [
              Text(emoji, style: const TextStyle(fontSize: 22)),
              const SizedBox(height: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: isActive ? AppColors.matchaDark : context.txtPrimary,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                '$count Kudos',
                style: TextStyle(
                  fontSize: 10,
                  color: isActive ? AppColors.matchaDark : context.txtSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
