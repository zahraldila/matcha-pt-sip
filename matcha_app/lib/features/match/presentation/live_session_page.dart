import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import 'match_scoring_page.dart';

class LiveSessionPage extends StatefulWidget {
  final String sessionName;
  final String sportName;
  final bool isHost;

  const LiveSessionPage({
    super.key,
    this.sessionName = 'Saturday Morning',
    this.sportName = 'Tennis',
    this.isHost = true,
  });

  @override
  State<LiveSessionPage> createState() => _LiveSessionPageState();
}

class _LiveSessionPageState extends State<LiveSessionPage> {
  final List<Map<String, dynamic>> _liveCourts = [
    {
      'courtName': 'Court 1 — SiJi Tennis Court',
      'sideA': 'Aldi · Budi',
      'sideB': 'Caca · Dina',
      'scoreA': 6,
      'scoreB': 4,
      'status': 'IN PROGRESS',
    },
    {
      'courtName': 'Court 2 — SiJi Tennis Court',
      'sideA': 'Eka · Fajar',
      'sideB': 'Gilang · Hadi',
      'scoreA': 3,
      'scoreB': 2,
      'status': 'IN PROGRESS',
    },
  ];

  final List<String> _waitingPlayers = ['Indra', 'Joko'];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Live Session'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () => Navigator.maybePop(context),
        ),
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 16),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 8,
                  height: 8,
                  decoration: const BoxDecoration(
                    color: AppColors.primary,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  'LIVE',
                  style: AppTextStyles.badge.copyWith(color: AppColors.primary, fontSize: 11),
                ),
              ],
            ),
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Session Header Info
              _buildSessionHeader(),
              const SizedBox(height: 20),

              // 2. Section: Sedang Bertanding di Lapangan
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('PERTANDINGAN AKTIF', style: AppTextStyles.badge.copyWith(color: AppColors.textSecondary, letterSpacing: 1.5)),
                  Text('${_liveCourts.length} Lapangan Berjalan', style: AppTextStyles.caption.copyWith(color: AppColors.primary)),
                ],
              ),
              const SizedBox(height: 12),

              // 3. Live Court Score Cards
              ..._liveCourts.map((court) => Padding(
                    padding: const EdgeInsets.only(bottom: 14.0),
                    child: _buildCourtScoreCard(court),
                  )),

              const SizedBox(height: 10),

              // 4. Waiting Players Section
              _buildWaitingSection(),
              const SizedBox(height: 20),

              // 5. Next Drawing / Next Turn Preview Box
              _buildNextDrawingCard(),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSessionHeader() {
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
          Text(widget.sessionName, style: AppTextStyles.sectionTitle.copyWith(fontSize: 18)),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.sports_tennis_rounded, size: 14, color: AppColors.primary),
              const SizedBox(width: 6),
              Text(
                '${widget.sportName} · 8 Players · 2 Courts · Americano',
                style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildCourtScoreCard(Map<String, dynamic> court) {
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
          // Court Name & Status
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                court['courtName'],
                style: AppTextStyles.cardTitle.copyWith(color: AppColors.primary, fontSize: 14),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: AppColors.inProgressBadge.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  court['status'],
                  style: AppTextStyles.badge.copyWith(
                    color: AppColors.inProgressBadge,
                    fontSize: 9,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Live Score Display
          Container(
            padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
            decoration: BoxDecoration(
              color: AppColors.surfaceSecondary,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.surfaceBorder),
            ),
            child: Row(
              children: [
                // Team A
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        court['sideA'],
                        style: AppTextStyles.body.copyWith(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 2),
                      Text('Side A', style: AppTextStyles.caption.copyWith(fontSize: 10)),
                    ],
                  ),
                ),

                // Live Score Board Digit
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppColors.primary.withValues(alpha: 0.3)),
                  ),
                  child: Text(
                    '${court['scoreA']} — ${court['scoreB']}',
                    style: AppTextStyles.scoreDisplay.copyWith(
                      color: AppColors.primary,
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),

                // Team B
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        court['sideB'],
                        textAlign: TextAlign.end,
                        style: AppTextStyles.body.copyWith(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 2),
                      Text('Side B', style: AppTextStyles.caption.copyWith(fontSize: 10)),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Host Quick Action to Input Score
          if (widget.isHost) ...[
            const SizedBox(height: 12),
            InkWell(
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => MatchScoringPage(
                      sessionName: widget.sessionName,
                      courtName: court['courtName'],
                      sideA: court['sideA'],
                      sideB: court['sideB'],
                    ),
                  ),
                );
              },
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 8),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.primary.withValues(alpha: 0.25)),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.edit_note_rounded, size: 16, color: AppColors.primary),
                    const SizedBox(width: 6),
                    Text(
                      'Input / Update Skor Pertandingan',
                      style: AppTextStyles.caption.copyWith(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildWaitingSection() {
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
            children: [
              const Icon(Icons.pause_circle_outline_rounded, size: 18, color: AppColors.warning),
              const SizedBox(width: 8),
              Text(
                'WAITING PLAYERS (${_waitingPlayers.length})',
                style: AppTextStyles.badge.copyWith(color: AppColors.warning, letterSpacing: 1),
              ),
            ],
          ),
          const SizedBox(height: 10),
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
                    Text(name, style: AppTextStyles.body.copyWith(fontSize: 13)),
                  ],
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildNextDrawingCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.surfaceBorder),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.shuffle_rounded, color: AppColors.primary, size: 24),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Next Turn / Re-Drawing', style: AppTextStyles.cardTitle.copyWith(fontSize: 14)),
                const SizedBox(height: 2),
                Text(
                  'Susunan ronde berikutnya siap dibuat setelah semua match selesai.',
                  style: AppTextStyles.caption.copyWith(fontSize: 11),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          OutlinedButton(
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              minimumSize: const Size(60, 34),
            ),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => DrawingResultPage(
                    sessionName: widget.sessionName,
                    sportName: widget.sportName,
                  ),
                ),
              );
            },
            child: const Text('Lihat', style: TextStyle(fontSize: 12)),
          ),
        ],
      ),
    );
  }
}
