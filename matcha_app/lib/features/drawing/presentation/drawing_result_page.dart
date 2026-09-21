import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../match/presentation/match_scoring_page.dart';

class DrawingResultPage extends StatefulWidget {
  const DrawingResultPage({super.key});

  @override
  State<DrawingResultPage> createState() => _DrawingResultPageState();
}

class _DrawingResultPageState extends State<DrawingResultPage> {
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
    final matches = _dataService.currentDrawingMatches;
    final isLocked = _dataService.isDrawingLocked;
    final isHost = _dataService.isHostMode;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Bagan & Drawing Tim',
          style: AppTextStyles.h2.copyWith(fontSize: 16, color: context.txtPrimary),
        ),
        centerTitle: true,
        actions: [
          if (isHost)
            IconButton(
              icon: Icon(
                isLocked ? Icons.lock_rounded : Icons.lock_open_rounded,
                color: isLocked ? AppColors.matchaDark : context.txtSecondary,
              ),
              tooltip: isLocked ? 'Buka Kunci Drawing' : 'Kunci Drawing',
              onPressed: () {
                _dataService.toggleLockDrawing();
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      _dataService.isDrawingLocked
                          ? 'Drawing tim berhasil dikunci 🔒'
                          : 'Kunci drawing dibuka 🔓',
                    ),
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
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Banner Info
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.matchaSoftLime,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.matchaSoftLimeBorder),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppColors.matchaSoftLimeBorder),
                    ),
                    child: const Icon(Icons.info_outline_rounded, color: AppColors.matchaDark, size: 20),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          isLocked ? 'Drawing Dikunci (Siap Main)' : 'Drawing Otomatis Seimbang',
                          style: AppTextStyles.bodyMedium.copyWith(
                            fontWeight: FontWeight.bold,
                            color: AppColors.matchaDark,
                          ),
                        ),
                        Text(
                          'Sistem Matcha mengundi pasangan bermain berdasarkan kesetaraan tier pemain.',
                          style: AppTextStyles.caption.copyWith(
                            color: AppColors.matchaDark.withValues(alpha: 0.8),
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 18),

            // Match Cards per Court
            for (var match in matches) ...[
              _buildCourtMatchCard(context, match),
              const SizedBox(height: 14),
            ],

            const SizedBox(height: 18),

            // Host Actions: Shuffle & Start Match
            if (isHost) ...[
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: isLocked
                          ? null
                          : () {
                              _dataService.shuffleDrawing();
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text('Tim berhasil diacak ulang! 🎲'),
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            },
                      icon: const Icon(Icons.shuffle_rounded),
                      label: const Text('Acak Tim'),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        foregroundColor: AppColors.matchaDark,
                        side: const BorderSide(color: AppColors.matchaDark, width: 1.5),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const MatchScoringPage()),
                        );
                      },
                      icon: const Icon(Icons.play_arrow_rounded),
                      label: const Text('Mulai Match'),
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        backgroundColor: AppColors.matchaDark,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                    ),
                  ),
                ],
              ),
            ] else ...[
              // Member View: Tombol Buka Live Score
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const MatchScoringPage()),
                    );
                  },
                  icon: const Icon(Icons.live_tv_rounded),
                  label: const Text('Pantau Live Score (Penonton)'),
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    backgroundColor: AppColors.matchaDark,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildCourtMatchCard(BuildContext context, CourtMatch match) {
    return Container(
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: context.surfBorder, width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Court Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: match.status == 'live' ? Colors.redAccent : Colors.orangeAccent,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    match.courtName,
                    style: AppTextStyles.h3.copyWith(fontSize: 14, color: context.txtPrimary),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: context.surfSec,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  match.status == 'live' ? 'Live • Set 1' : 'Menunggu',
                  style: TextStyle(
                    color: match.status == 'live' ? Colors.redAccent : context.txtSecondary,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          // Team A vs Team B Lineup
          Row(
            children: [
              // Team A
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: context.surfSec,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: context.surfBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'TIM A',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                      ),
                      const SizedBox(height: 6),
                      for (var p in match.teamA) ...[
                        Row(
                          children: [
                            CircleAvatar(radius: 10, backgroundImage: NetworkImage(p.avatarUrl)),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(
                                p.name,
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                      ],
                    ],
                  ),
                ),
              ),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 10),
                child: Text(
                  'VS',
                  style: TextStyle(fontWeight: FontWeight.w900, color: Colors.grey, fontSize: 13),
                ),
              ),
              // Team B
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: context.surfSec,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: context.surfBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'TIM B',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.deepOrange),
                      ),
                      const SizedBox(height: 6),
                      for (var p in match.teamB) ...[
                        Row(
                          children: [
                            CircleAvatar(radius: 10, backgroundImage: NetworkImage(p.avatarUrl)),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(
                                p.name,
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
