import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../match/presentation/match_scoring_page.dart';

class DrawingResultPage extends StatefulWidget {
  final String sessionName;
  final String sportName;
  final String drawingMethod;

  const DrawingResultPage({
    super.key,
    this.sessionName = 'Saturday Morning',
    this.sportName = 'Tennis',
    this.drawingMethod = 'Americano',
  });

  @override
  State<DrawingResultPage> createState() => _DrawingResultPageState();
}

class _DrawingResultPageState extends State<DrawingResultPage> {
  // Mock drawing results
  final List<Map<String, dynamic>> _matches = [
    {
      'court': 'Court 1 — SiJi Tennis Court',
      'sideA': ['Aldi', 'Budi'],
      'sideB': ['Caca', 'Dina'],
      'status': 'PLAYING',
    },
    {
      'court': 'Court 2 — SiJi Tennis Court',
      'sideA': ['Eka', 'Fajar'],
      'sideB': ['Gilang', 'Hadi'],
      'status': 'PLAYING',
    },
  ];

  final List<String> _waitingPlayers = ['Indra', 'Joko'];

  void _reRollDrawing() {
    setState(() {
      // Simulate shuffle animation/feedback
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Drawing berhasil diacak ulang secara acak & adil! 🎲'),
          backgroundColor: AppColors.surfaceSecondary,
          duration: Duration(seconds: 2),
        ),
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Drawing Result'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () => Navigator.maybePop(context),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: AppColors.primary),
            tooltip: 'Acak Ulang Drawing',
            onPressed: _reRollDrawing,
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Session Info Banner
              _buildSessionInfoBanner(),
              const SizedBox(height: 20),

              // 2. Section Title
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('SUSUNAN PERTANDINGAN', style: AppTextStyles.badge.copyWith(color: AppColors.textSecondary, letterSpacing: 1.5)),
                  Text('${_matches.length} Court Aktif', style: AppTextStyles.caption.copyWith(color: AppColors.primary)),
                ],
              ),
              const SizedBox(height: 12),

              // 3. Match Cards per Court
              ..._matches.map((match) => Padding(
                    padding: const EdgeInsets.only(bottom: 14.0),
                    child: _buildMatchCard(match),
                  )),

              const SizedBox(height: 10),

              // 4. Waiting List Card
              _buildWaitingListCard(),
              const SizedBox(height: 32),

              // 5. Action Buttons (Mulai Match & Re-Draw)
              ElevatedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => MatchScoringPage(
                        sessionName: widget.sessionName,
                        courtName: 'Court 1 — SiJi Tennis Court',
                        sideA: 'Aldi · Budi',
                        sideB: 'Caca · Dina',
                      ),
                    ),
                  );
                },
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text('MULAI MATCH PERTANDINGAN'),
                    SizedBox(width: 8),
                    Icon(Icons.play_arrow_rounded, size: 22),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _reRollDrawing,
                icon: const Icon(Icons.shuffle_rounded, size: 18, color: AppColors.primary),
                label: const Text('Acak Ulang Susunan (Re-Draw)'),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSessionInfoBanner() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.surfaceBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'RONDE 1',
                style: AppTextStyles.badge.copyWith(
                  color: AppColors.primary,
                  letterSpacing: 2,
                  fontSize: 12,
                ),
              ),
              Text(
                '6 Sep 2026 · 08:00',
                style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(widget.sessionName, style: AppTextStyles.sectionTitle.copyWith(fontSize: 18)),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.sports_tennis_rounded, size: 14, color: AppColors.primary),
              const SizedBox(width: 6),
              Text(
                '${widget.sportName} · Metode: ${widget.drawingMethod} · Doubles',
                style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMatchCard(Map<String, dynamic> match) {
    final List<String> sideA = List<String>.from(match['sideA']);
    final List<String> sideB = List<String>.from(match['sideB']);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.surfaceBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Court Header & Status
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                match['court'],
                style: AppTextStyles.cardTitle.copyWith(fontSize: 14, color: AppColors.primary),
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
                    const SizedBox(width: 4),
                    Text(
                      'PLAYING',
                      style: AppTextStyles.badge.copyWith(color: AppColors.primary, fontSize: 9),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Team A vs Team B Display
          Container(
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
            decoration: BoxDecoration(
              color: AppColors.surfaceSecondary,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.surfaceBorder),
            ),
            child: Row(
              children: [
                // Side A
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Text('SIDE A', style: AppTextStyles.badge.copyWith(fontSize: 10, color: AppColors.textSecondary)),
                      const SizedBox(height: 4),
                      Text(
                        sideA.join(' · '),
                        textAlign: TextAlign.center,
                        style: AppTextStyles.body.copyWith(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                    ],
                  ),
                ),

                // VS Badge
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppColors.surfaceBorder),
                  ),
                  child: Text(
                    'VS',
                    style: AppTextStyles.badge.copyWith(color: AppColors.primary, fontSize: 11),
                  ),
                ),

                // Side B
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Text('SIDE B', style: AppTextStyles.badge.copyWith(fontSize: 10, color: AppColors.textSecondary)),
                      const SizedBox(height: 4),
                      Text(
                        sideB.join(' · '),
                        textAlign: TextAlign.center,
                        style: AppTextStyles.body.copyWith(fontWeight: FontWeight.bold, fontSize: 14),
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

  Widget _buildWaitingListCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.surfaceBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  const Icon(Icons.pause_circle_outline_rounded, size: 18, color: AppColors.warning),
                  const SizedBox(width: 8),
                  Text(
                    'WAITING PLAYERS (${_waitingPlayers.length})',
                    style: AppTextStyles.badge.copyWith(color: AppColors.warning, letterSpacing: 1),
                  ),
                ],
              ),
              Text(
                'Prioritas Ronde 2',
                style: AppTextStyles.caption.copyWith(fontSize: 11, color: AppColors.textSecondary),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            children: _waitingPlayers.map((name) {
              return Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSecondary,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.surfaceBorder),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.person_outline_rounded, size: 14, color: AppColors.textSecondary),
                    const SizedBox(width: 6),
                    Text(name, style: AppTextStyles.body.copyWith(fontSize: 13, fontWeight: FontWeight.w500)),
                  ],
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }
}
