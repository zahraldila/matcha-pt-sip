import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../drawing/domain/matcha_drawing_engine.dart';
import '../../games/domain/game_wizard_model.dart';
import '../../games/presentation/game_final_recap_page.dart';

class MatchScoringPage extends StatefulWidget {
  final GameWizardConfig? config;
  final List<DrawingRound>? rounds;
  final AuthController? authController;

  const MatchScoringPage({
    super.key,
    this.config,
    this.rounds,
    this.authController,
  });

  @override
  State<MatchScoringPage> createState() => _MatchScoringPageState();
}

class _MatchScoringPageState extends State<MatchScoringPage> {
  late GameWizardConfig _config;
  late List<DrawingRound> _rounds;
  int _activeRoundIndex = 0;

  @override
  void initState() {
    super.initState();
    _config = widget.config ??
        GameWizardConfig(
          activityName: 'Match Padel Tournament',
          venueName: 'Barong Padel Arena & Club',
          players: [
            const GamePlayerItem(id: '1', name: 'Aku', level: 'Beginner', isGuest: true),
            const GamePlayerItem(id: '2', name: 'Kamu', level: 'Beginner', isGuest: true),
            const GamePlayerItem(id: '3', name: 'Dia', level: 'Beginner', isGuest: true),
            const GamePlayerItem(id: '4', name: 'Kita', level: 'Beginner', isGuest: true),
          ],
        );

    _rounds = widget.rounds ??
        MatchaDrawingEngine.generateDrawing(
          players: _config.players,
          courtCount: _config.courtCount,
          gameType: _config.gameType,
          playMode: _config.playMode,
          roundCount: _config.totalRounds,
        );
  }

  void _addPointTeamA(DrawingMatch match) {
    if (match.status == 'Completed') return;
    setState(() {
      match.scoreA++;
      if (match.scoreA >= _config.maxTargetPoints) {
        match.gamesWonA++;
        match.status = 'Completed';
      } else {
        match.status = 'In Progress';
      }
    });
  }

  void _addPointTeamB(DrawingMatch match) {
    if (match.status == 'Completed') return;
    setState(() {
      match.scoreB++;
      if (match.scoreB >= _config.maxTargetPoints) {
        match.gamesWonB++;
        match.status = 'Completed';
      } else {
        match.status = 'In Progress';
      }
    });
  }

  void _walkoverMatch(DrawingMatch match, bool winForTeamA) {
    setState(() {
      if (winForTeamA) {
        match.scoreA = _config.maxTargetPoints;
        match.gamesWonA++;
      } else {
        match.scoreB = _config.maxTargetPoints;
        match.gamesWonB++;
      }
      match.status = 'Completed';
    });
  }

  bool get _isCurrentRoundFinished {
    if (_rounds.isEmpty) return false;
    final currentRound = _rounds[_activeRoundIndex.clamp(0, _rounds.length - 1)];
    return currentRound.matches.every((m) => m.status == 'Completed');
  }

  bool get _isAllRoundsFinished {
    if (_rounds.isEmpty) return false;
    return _rounds.every((r) => r.matches.every((m) => m.status == 'Completed'));
  }

  void _finishSessionAndShowRecap() {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => GameFinalRecapPage(
          config: _config,
          rounds: _rounds,
          authController: widget.authController,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_rounds.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: const Text('Live Match Scoring Console')),
        body: const Center(child: Text('Data ronde tidak tersedia.')),
      );
    }

    final currentRound = _rounds[_activeRoundIndex.clamp(0, _rounds.length - 1)];

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () => Navigator.pop(context),
        ),
        title: Column(
          children: [
            Text(
              'Live Match Scoring Console',
              style: AppTextStyles.h2.copyWith(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              'Host menekan tombol untuk mencatat poin game & match secara langsung',
              style: AppTextStyles.caption.copyWith(
                fontSize: 10,
                color: const Color(0xFF64748B),
              ),
            ),
          ],
        ),
        centerTitle: true,
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 14),
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: const Color(0xFFECFDF5),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.circle, color: Color(0xFF10B981), size: 8),
                SizedBox(width: 4),
                Text(
                  'MATCH LIVE',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 10, color: Color(0xFF065F46)),
                ),
              ],
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          // Banner Status
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: const Color(0xFFECFDF5),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: const Row(
              children: [
                Icon(Icons.check_circle_rounded, color: Color(0xFF059669), size: 16),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Jadwal pertandingan berhasil dikunci!',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF065F46)),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),

          // Round Selector Tabs
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Pilih Ronde: (${_rounds.length} Ronde)',
                style: AppTextStyles.caption.copyWith(fontWeight: FontWeight.bold, color: const Color(0xFF334155)),
              ),
              Text(
                'Format: ${_config.gameType} (${_config.playMode == "Double" ? "2v2" : "1v1"})',
                style: AppTextStyles.caption.copyWith(fontSize: 10, color: const Color(0xFF94A3B8)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: List.generate(_rounds.length, (idx) {
                final isSelected = _activeRoundIndex == idx;
                final r = _rounds[idx];
                final isFinished = r.matches.every((m) => m.status == 'Completed');

                return Container(
                  margin: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text('Ronde ${r.roundNumber}'),
                        if (isFinished) ...[
                          const SizedBox(width: 4),
                          const Icon(Icons.check, size: 12, color: Colors.white),
                        ],
                      ],
                    ),
                    selected: isSelected,
                    selectedColor: AppColors.matchaDark,
                    backgroundColor: Colors.white,
                    labelStyle: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: isSelected ? Colors.white : const Color(0xFF475569),
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                      side: BorderSide(color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0)),
                    ),
                    onSelected: (val) {
                      if (val) setState(() => _activeRoundIndex = idx);
                    },
                  ),
                );
              }),
            ),
          ),
          const SizedBox(height: 16),

          // Matches in this round
          ...currentRound.matches.map((m) => _buildScoreboardCard(m)),

          // Round Finished Notification Banner
          if (_isCurrentRoundFinished && !_isAllRoundsFinished)
            Container(
              margin: const EdgeInsets.only(top: 8),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF3C7),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFFDE68A)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.emoji_events_rounded, color: Color(0xFFD97706), size: 20),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Court 1 telah selesai pada Ronde ${currentRound.roundNumber}!',
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: Color(0xFF92400E)),
                        ),
                        const SizedBox(height: 2),
                        const Text(
                          'Poin telah dicatat ke klasemen. Silakan lanjut ke ronde berikutnya.',
                          style: TextStyle(fontSize: 11, color: Color(0xFFB45309)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

          // All Rounds Finished Banner & Final Recap Trigger
          if (_isAllRoundsFinished) ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFF22C55E)),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF22C55E).withValues(alpha: 0.1),
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: Column(
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFDCFCE7),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.sports_score_rounded, color: Color(0xFF15803D), size: 24),
                      ),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Seluruh Pertandingan Ronde Selesai! 🏁',
                              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Color(0xFF0F172A)),
                            ),
                            SizedBox(height: 2),
                            Text(
                              'Semua court telah mencatat skor akhir. Silakan lanjut ke hasil akhir & podium.',
                              style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    height: 44,
                    child: ElevatedButton.icon(
                      onPressed: _finishSessionAndShowRecap,
                      icon: const Icon(Icons.emoji_events_rounded, size: 18),
                      label: const Text('Selesaikan Sesi & Lihat Juara', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.matchaDark,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        elevation: 0,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildScoreboardCard(DrawingMatch match) {
    final isCompleted = match.status == 'Completed';

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
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
          // Header Court & Target
          Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${_config.venueName ?? "Barong Arena"} • Court ${match.courtNumber}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: Color(0xFF0F172A)),
                    ),
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                          decoration: BoxDecoration(
                            color: AppColors.matchaSoftLime,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            _config.sport,
                            style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          _config.scoringSystem,
                          style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: isCompleted ? const Color(0xFFF1F5F9) : const Color(0xFFDCFCE7),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    isCompleted ? 'Skor Terkunci' : 'Sedang Berlangsung',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: isCompleted ? const Color(0xFF64748B) : const Color(0xFF15803D),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: Color(0xFFE2E8F0)),

          // Team A Scoring Box
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text('TEAM A', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 10, color: Color(0xFF2563EB))),
                ),
                const SizedBox(height: 4),
                Text(
                  match.teamANames,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 10),
                const Text('CURRENT POINT', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                Text(
                  '${match.scoreA}',
                  style: const TextStyle(fontSize: 48, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), height: 1.1),
                ),
                Text(
                  'Games Won: ${match.gamesWonA} Game',
                  style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  height: 42,
                  child: ElevatedButton(
                    onPressed: isCompleted ? null : () => _addPointTeamA(match),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      disabledBackgroundColor: const Color(0xFFF1F5F9),
                      foregroundColor: Colors.white,
                      disabledForegroundColor: const Color(0xFF94A3B8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      elevation: 0,
                    ),
                    child: Text(
                      isCompleted ? '🔒 Skor Terkunci (Ronde Selesai)' : '+ Tambah Poin Team A',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ),
                ),
              ],
            ),
          ),

          const Divider(height: 1, color: Color(0xFFE2E8F0)),

          // Team B Scoring Box
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF3C7),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text('TEAM B', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 10, color: Color(0xFFB45309))),
                ),
                const SizedBox(height: 4),
                Text(
                  match.teamBNames,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 10),
                const Text('CURRENT POINT', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                Text(
                  '${match.scoreB}',
                  style: const TextStyle(fontSize: 48, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), height: 1.1),
                ),
                Text(
                  'Games Won: ${match.gamesWonB} Game',
                  style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  height: 42,
                  child: ElevatedButton(
                    onPressed: isCompleted ? null : () => _addPointTeamB(match),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      disabledBackgroundColor: const Color(0xFFF1F5F9),
                      foregroundColor: Colors.white,
                      disabledForegroundColor: const Color(0xFF94A3B8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      elevation: 0,
                    ),
                    child: Text(
                      isCompleted ? '🔒 Skor Terkunci (Ronde Selesai)' : '+ Tambah Poin Team B',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Walkover Action Button (if in progress)
          if (!isCompleted) ...[
            const Divider(height: 1, color: Color(0xFFE2E8F0)),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  TextButton.icon(
                    onPressed: () => _walkoverMatch(match, true),
                    icon: const Icon(Icons.warning_amber_rounded, size: 14, color: Color(0xFFEF4444)),
                    label: const Text('Akhir Paksa (Walkover Team A Win)', style: TextStyle(fontSize: 10, color: Color(0xFFEF4444))),
                  ),
                  TextButton(
                    onPressed: () => _walkoverMatch(match, false),
                    child: const Text('Team B Win', style: TextStyle(fontSize: 10, color: Color(0xFFEF4444))),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
