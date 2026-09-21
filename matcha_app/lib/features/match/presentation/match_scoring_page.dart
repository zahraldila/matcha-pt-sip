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
                  ? AppColors.matchaSoftLime
                  : const Color(0xFFEFF6FF),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: isHost
                    ? const Color(0xFF063B00).withValues(alpha: 0.3)
                    : const Color(0xFF93C5FD),
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  isHost ? Icons.sports_tennis : Icons.visibility_rounded,
                  size: 13,
                  color: isHost ? AppColors.matchaDark : const Color(0xFF1D4ED8),
                ),
                const SizedBox(width: 4),
                Text(
                  isHost ? 'HOST SCORER' : 'PENONTON',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: isHost ? AppColors.matchaDark : const Color(0xFF1D4ED8),
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
                      color: AppColors.matchaSoftLime,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.3)),
                    ),
                    child: const Text(
                      'SET 1',
                      style: TextStyle(fontWeight: FontWeight.w900, color: AppColors.matchaDark, fontSize: 12),
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
                  color: context.surf,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: context.surfBorder),
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

            const SizedBox(height: 8),

            // Main Scoreboard: Clean High-Contrast Cards for Team A & Team B
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
                        accentColor: AppColors.matchaDark,
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
                        accentColor: Colors.deepOrange,
                        isHost: isHost,
                        onTapScore: isHost ? () => _dataService.addPointTeamB() : null,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 14),

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
                        icon: const Icon(Icons.undo_rounded, size: 16),
                        label: const Text('Undo', style: TextStyle(fontSize: 12)),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          foregroundColor: context.txtPrimary,
                          side: BorderSide(color: context.surfBorder),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    // Switch Server Button
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _dataService.switchServer(),
                        icon: const Icon(Icons.swap_horiz_rounded, size: 16),
                        label: const Text('Ganti Server', style: TextStyle(fontSize: 12)),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          foregroundColor: context.txtPrimary,
                          side: BorderSide(color: context.surfBorder),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
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
                        icon: const Icon(Icons.skip_next_rounded, size: 16),
                        label: const Text('Set Baru', style: TextStyle(fontSize: 12)),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          foregroundColor: context.txtPrimary,
                          side: BorderSide(color: context.surfBorder),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 10),

              // Selesaikan Pertandingan Button
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 14),
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
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
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
                padding: const EdgeInsets.all(16),
                child: Text(
                  '💡 Skor dicatat langsung oleh Host. Layar penonton otomatis terupdate.',
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
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isServing ? accentColor : context.surfBorder,
          width: isServing ? 2 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: isServing ? accentColor.withValues(alpha: 0.08) : Colors.black.withValues(alpha: 0.02),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: onTapScore,
          child: Padding(
            padding: const EdgeInsets.all(16),
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
                            fontSize: 12,
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
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: AppColors.matchaSoftLime,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.3)),
                        ),
                        child: const Row(
                          children: [
                            Text('🎾', style: TextStyle(fontSize: 11)),
                            SizedBox(width: 4),
                            Text(
                              'SERVE',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
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
                    fontSize: 68,
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
