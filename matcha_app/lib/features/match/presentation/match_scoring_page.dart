import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import 'match_recap_page.dart';

class MatchScoringPage extends StatefulWidget {
  const MatchScoringPage({super.key});

  @override
  State<MatchScoringPage> createState() => _MatchScoringPageState();
}

class _MatchScoringPageState extends State<MatchScoringPage> {
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
    final isHost = _dataService.isHostMode;
    final currentSet = _dataService.currentSet;
    final teamAPoints = _dataService.teamAPoints;
    final teamBPoints = _dataService.teamBPoints;
    final server = _dataService.currentServer;
    final setHistory = _dataService.setHistory;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Live Match Scoring',
          style: AppTextStyles.h2.copyWith(fontSize: 16, color: context.txtPrimary),
        ),
        centerTitle: true,
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 16),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: isHost
                  ? AppColors.primary.withValues(alpha: 0.2)
                  : Colors.blueAccent.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: isHost ? AppColors.primary : Colors.blueAccent),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  isHost ? Icons.sports_tennis : Icons.visibility_rounded,
                  size: 13,
                  color: isHost ? context.brandColor : Colors.blueAccent,
                ),
                const SizedBox(width: 4),
                Text(
                  isHost ? 'HOST SCORER' : 'PENONTON',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: isHost ? context.brandColor : Colors.blueAccent,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            // Top Set & Court Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'SCBD Padel Court 1',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                      Text(
                        'Americano Double • Target 21 Poin',
                        style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                      ),
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.primary),
                    ),
                    child: Text(
                      'SET $currentSet',
                      style: const TextStyle(fontWeight: FontWeight.w900, color: AppColors.primary, fontSize: 13),
                    ),
                  ),
                ],
              ),
            ),

            // Finished Sets History Banner (if any)
            if (setHistory.isNotEmpty)
              Container(
                margin: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color: context.surfSec,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    const Text('Hasil Set Sebelumnya: ', style: TextStyle(fontSize: 11, color: Colors.grey)),
                    for (int i = 0; i < setHistory.length; i++) ...[
                      Text(
                        'Set ${i + 1}: ${setHistory[i][0]}-${setHistory[i][1]}',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                      if (i < setHistory.length - 1) const Text(' • ', style: TextStyle(color: Colors.grey)),
                    ],
                  ],
                ),
              ),

            const SizedBox(height: 10),

            // Main Scoreboard: High-Contrast Tap Cards for Team A & Team B
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(
                  children: [
                    // Team A Card
                    Expanded(
                      child: _buildTeamScoreCard(
                        context,
                        teamName: 'TIM A',
                        playerNames: 'Marcel Santoso & Budi Pratama',
                        points: teamAPoints,
                        isServing: server == 'teamA',
                        accentColor: AppColors.primary,
                        isHost: isHost,
                        onTapScore: isHost ? () => _dataService.addPointTeamA() : null,
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Team B Card
                    Expanded(
                      child: _buildTeamScoreCard(
                        context,
                        teamName: 'TIM B',
                        playerNames: 'Dimas Anggara & Kevin Sanjaya',
                        points: teamBPoints,
                        isServing: server == 'teamB',
                        accentColor: Colors.orangeAccent,
                        isHost: isHost,
                        onTapScore: isHost ? () => _dataService.addPointTeamB() : null,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Host Control Toolbar: Undo, Switch Server, Next Set, Finish Match
            if (isHost) ...[
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Row(
                  children: [
                    // Undo Button
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _dataService.undoLastPoint(),
                        icon: const Icon(Icons.undo_rounded, size: 18),
                        label: const Text('Undo', style: TextStyle(fontSize: 12)),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          foregroundColor: context.txtPrimary,
                          side: BorderSide(color: context.surfBorder),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    // Switch Server Button
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _dataService.switchServer(),
                        icon: const Icon(Icons.swap_horiz_rounded, size: 18),
                        label: const Text('Ganti Server', style: TextStyle(fontSize: 12)),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          foregroundColor: context.txtPrimary,
                          side: BorderSide(color: context.surfBorder),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    // Next Set Button
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () {
                          _dataService.finishCurrentSet();
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text('Set $currentSet selesai! Masuk ke Set ${currentSet + 1} 🏸'),
                              behavior: SnackBarBehavior.floating,
                            ),
                          );
                        },
                        icon: const Icon(Icons.skip_next_rounded, size: 18),
                        label: const Text('Set Baru', style: TextStyle(fontSize: 12)),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          foregroundColor: context.txtPrimary,
                          side: BorderSide(color: context.surfBorder),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 12),

              // Selesaikan Pertandingan Button
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
                child: SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.pushReplacement(
                        context,
                        MaterialPageRoute(builder: (_) => const MatchRecapPage()),
                      );
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: context.brandColor,
                      foregroundColor: Colors.black,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: const Text(
                      'Selesaikan Match & Lihat Rekap 🏁',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ),
                ),
              ),
            ] else ...[
              // Member notice
              Padding(
                padding: const EdgeInsets.all(20),
                child: Text(
                  '💡 Skor sedang dicatat oleh Host secara realtime. Tampilan akan otomatis terupdate.',
                  textAlign: TextAlign.center,
                  style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildTeamScoreCard(
    BuildContext context, {
    required String teamName,
    required String playerNames,
    required int points,
    required bool isServing,
    required Color accentColor,
    required bool isHost,
    required VoidCallback? onTapScore,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isServing ? accentColor.withValues(alpha: 0.8) : context.surfBorder,
          width: isServing ? 2 : 1,
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(24),
          onTap: onTapScore,
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Team Header & Serve Indicator
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          teamName,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w900,
                            color: accentColor,
                            letterSpacing: 1,
                          ),
                        ),
                        Text(
                          playerNames,
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: context.txtPrimary,
                          ),
                        ),
                      ],
                    ),
                    if (isServing)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: accentColor.withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: accentColor),
                        ),
                        child: Row(
                          children: [
                            const Text('🎾', style: TextStyle(fontSize: 12)),
                            const SizedBox(width: 4),
                            Text(
                              'SERVE',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: accentColor),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),

                // Giant Score Display
                Text(
                  '$points',
                  style: TextStyle(
                    fontSize: 72,
                    fontWeight: FontWeight.w900,
                    color: accentColor,
                    letterSpacing: -2,
                  ),
                ),

                // Tap Hint
                if (isHost)
                  Text(
                    '+1 Poin (Tap Kartu)',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: context.txtSecondary,
                    ),
                  )
                else
                  const SizedBox.shrink(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
