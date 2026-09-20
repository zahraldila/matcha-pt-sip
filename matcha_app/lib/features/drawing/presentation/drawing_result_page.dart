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
                color: isLocked ? Colors.amberAccent : context.txtSecondary,
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
                color: context.surfSec,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: context.surfBorder),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(Icons.info_outline_rounded, color: context.brandColor, size: 20),
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
                            color: context.txtPrimary,
                          ),
                        ),
                        Text(
                          'Sistem Matcha mengundi pasangan bermain berdasarkan kesetaraan tier pemain.',
                          style: AppTextStyles.caption.copyWith(
                            color: context.txtSecondary,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // Match Cards per Court
            for (var match in matches) ...[
              _buildCourtMatchCard(context, match),
              const SizedBox(height: 16),
            ],

            const SizedBox(height: 20),

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
                        foregroundColor: context.txtPrimary,
                        side: BorderSide(color: context.surfBorder),
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
                        backgroundColor: context.brandColor,
                        foregroundColor: Colors.black,
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
                    backgroundColor: context.brandColor,
                    foregroundColor: Colors.black,
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
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: context.surfBorder, width: 1),
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
                      color: match.status == 'live' ? Colors.redAccent : Colors.amberAccent,
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
                  borderRadius: BorderRadius.circular(8),
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
          const SizedBox(height: 14),
          // Team A vs Team B Lineup
          Row(
            children: [
              // Team A
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: context.surfSec,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: context.surfBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'TIM A',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppColors.primary),
                      ),
                      const SizedBox(height: 8),
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
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: context.surfBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'TIM B',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.orangeAccent),
                      ),
                      const SizedBox(height: 8),
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
