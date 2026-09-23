import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../games/domain/game_wizard_model.dart';
import '../../match/presentation/match_scoring_page.dart';
import '../domain/matcha_drawing_engine.dart';

class DrawingResultPage extends StatefulWidget {
  final GameWizardConfig? config;
  final List<DrawingRound>? initialRounds;
  final AuthController? authController;

  const DrawingResultPage({
    super.key,
    this.config,
    this.initialRounds,
    this.authController,
  });

  @override
  State<DrawingResultPage> createState() => _DrawingResultPageState();
}

class _DrawingResultPageState extends State<DrawingResultPage> {
  late GameWizardConfig _config;
  late List<DrawingRound> _rounds;
  int _selectedRoundIndex = 0;

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

    _rounds = widget.initialRounds ??
        MatchaDrawingEngine.generateDrawing(
          players: _config.players,
          courtCount: _config.courtCount,
          gameType: _config.gameType,
          playMode: _config.playMode,
          roundCount: _config.totalRounds,
        );
  }

  void _shuffleDrawing() {
    setState(() {
      _rounds = MatchaDrawingEngine.generateDrawing(
        players: _config.players,
        courtCount: _config.courtCount,
        gameType: _config.gameType,
        playMode: _config.playMode,
        roundCount: _config.totalRounds,
        shufflePlayers: true,
      );
    });

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Jadwal dan rotasi pemain berhasil diacak ulang! 🔀'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  void _startLiveScoring() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => MatchScoringPage(
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
        appBar: AppBar(title: const Text('Drawing & Jadwal Pertandingan')),
        body: const Center(child: Text('Data drawing tidak tersedia.')),
      );
    }

    final currentRound = _rounds[_selectedRoundIndex.clamp(0, _rounds.length - 1)];

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
              'Drawing & Jadwal Pertandingan',
              style: AppTextStyles.h2.copyWith(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              '${_config.gameType} • ${_config.playMode} (${_config.playMode == "Double" ? "2 vs 2" : "1 vs 1"})',
              style: AppTextStyles.caption.copyWith(
                fontSize: 11,
                color: const Color(0xFF64748B),
              ),
            ),
          ],
        ),
        centerTitle: true,
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                // Header Card with Shuffle Button
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: AppColors.matchaDark,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.sports_tennis_rounded, color: Colors.white, size: 20),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _config.activityName.isEmpty ? '${_config.sport} Tournament' : _config.activityName,
                              style: AppTextStyles.h2.copyWith(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: const Color(0xFF0F172A),
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              _config.venueName ?? 'Barong Padel Arena & Club',
                              style: AppTextStyles.caption.copyWith(
                                fontSize: 11,
                                color: const Color(0xFF64748B),
                              ),
                            ),
                          ],
                        ),
                      ),
                      OutlinedButton.icon(
                        onPressed: _shuffleDrawing,
                        icon: const Icon(Icons.shuffle_rounded, size: 14),
                        label: const Text('Acak Ulang', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.matchaDark,
                          side: const BorderSide(color: Color(0xFFCBD5E1)),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),

                // Round Selector Pills
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: List.generate(_rounds.length, (idx) {
                      final isSelected = _selectedRoundIndex == idx;
                      final r = _rounds[idx];
                      return Container(
                        margin: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text(
                            'Ronde ${r.roundNumber} (${r.matches.length} Match)',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: isSelected ? Colors.white : const Color(0xFF475569),
                            ),
                          ),
                          selected: isSelected,
                          selectedColor: AppColors.matchaDark,
                          backgroundColor: Colors.white,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                            side: BorderSide(
                              color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                            ),
                          ),
                          onSelected: (val) {
                            if (val) setState(() => _selectedRoundIndex = idx);
                          },
                        ),
                      );
                    }),
                  ),
                ),
                const SizedBox(height: 16),

                // Live Preview Lapangan Header
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: const BoxDecoration(
                            color: Color(0xFF22C55E),
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          'LIVE PREVIEW LAPANGAN',
                          style: AppTextStyles.caption.copyWith(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.8,
                            color: const Color(0xFF0F172A),
                          ),
                        ),
                      ],
                    ),
                    Text(
                      'RONDE ${currentRound.roundNumber} • ${currentRound.matches.length} Lapangan Berjalan',
                      style: AppTextStyles.caption.copyWith(
                        fontSize: 11,
                        color: const Color(0xFF64748B),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),

                // Visual Court Cards
                ...currentRound.matches.map((match) => _buildVisualCourtCard(match)),

                // Bangku Cadangan / Istirahat (Bench)
                if (currentRound.restingPlayers.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFFBEB),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFFDE68A)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.chair_outlined, size: 18, color: Color(0xFFD97706)),
                            const SizedBox(width: 8),
                            Text(
                              'BANGKU ISTIRAHAT (BENCH)',
                              style: AppTextStyles.caption.copyWith(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                color: const Color(0xFFB45309),
                              ),
                            ),
                            const Spacer(),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFEF3C7),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                '${currentRound.restingPlayers.length} Pemain',
                                style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFFB45309)),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          runSpacing: 6,
                          children: currentRound.restingPlayers.map((p) {
                            return Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: const Color(0xFFFDE68A)),
                              ),
                              child: Text(
                                p.name,
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                              ),
                            );
                          }).toList(),
                        ),
                      ],
                    ),
                  ),
                ],

                const SizedBox(height: 16),

                // Rincian Roster Pertandingan Card
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'ROSTER PERTANDINGAN',
                            style: AppTextStyles.caption.copyWith(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: const Color(0xFF0F172A),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEFF6FF),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              _config.gameType,
                              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      ...currentRound.matches.map((m) {
                        return Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Court ${m.courtNumber} (Sesi Game)',
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                              ),
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      'Team A: ${m.teamANames}',
                                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                    ),
                                  ),
                                  const Text('vs', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'Team B: ${m.teamBNames}',
                                      textAlign: TextAlign.right,
                                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        );
                      }),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Bottom Action Button
          Container(
            padding: const EdgeInsets.all(20),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
            ),
            child: SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton(
                onPressed: _startLiveScoring,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.matchaDark,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  elevation: 0,
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text('Kunci Tim & Mulai Scoring Live', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    SizedBox(width: 8),
                    Icon(Icons.arrow_forward_rounded, size: 18),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildVisualCourtCard(DrawingMatch match) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        children: [
          // Court Header
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Color(0xFF22C55E),
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'COURT ${match.courtNumber}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: Color(0xFF0F172A)),
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    match.status,
                    style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                  ),
                ),
              ],
            ),
          ),

          // Green Court Canvas Graphic
          Container(
            margin: const EdgeInsets.fromLTRB(14, 0, 14, 14),
            height: 180,
            decoration: BoxDecoration(
              color: const Color(0xFF1E4D36), // Deep green court
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.white.withValues(alpha: 0.2), width: 2),
            ),
            child: Stack(
              children: [
                // Court lines
                Center(
                  child: Container(
                    height: 2,
                    color: Colors.white.withValues(alpha: 0.6), // Net
                  ),
                ),
                Positioned.fill(
                  child: Align(
                    alignment: Alignment.center,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.3),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: const Text('NET', style: TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold)),
                    ),
                  ),
                ),

                // Team A (Top Half)
                Positioned(
                  top: 8,
                  left: 10,
                  right: 10,
                  child: Column(
                    children: [
                      const Text(
                        'TEAM A',
                        style: TextStyle(color: Color(0xFFA7F3D0), fontSize: 9, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: match.teamA.map((p) => _buildPlayerBadge(p)).toList(),
                      ),
                    ],
                  ),
                ),

                // Team B (Bottom Half)
                Positioned(
                  bottom: 8,
                  left: 10,
                  right: 10,
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: match.teamB.map((p) => _buildPlayerBadge(p)).toList(),
                      ),
                      const SizedBox(height: 6),
                      const Text(
                        'TEAM B',
                        style: TextStyle(color: Color(0xFFA7F3D0), fontSize: 9, fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPlayerBadge(GamePlayerItem player) {
    final initials = player.name.trim().isNotEmpty
        ? player.name.trim().split(' ').map((s) => s.isNotEmpty ? s[0] : '').take(2).join().toUpperCase()
        : 'P';

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: const Color(0xFF38BDF8),
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 2),
          ),
          child: Center(
            child: Text(
              initials,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 11),
            ),
          ),
        ),
        const SizedBox(height: 2),
        SizedBox(
          width: 60,
          child: Text(
            player.name,
            textAlign: TextAlign.center,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
          ),
        ),
      ],
    );
  }
}
