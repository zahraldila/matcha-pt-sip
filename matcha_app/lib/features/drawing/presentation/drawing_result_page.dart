import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../match/presentation/match_scoring_page.dart';
import '../domain/models/drawing_round_model.dart';
import '../domain/models/match_pairing_model.dart';
import 'controllers/drawing_controller.dart';

class DrawingResultPage extends StatefulWidget {
  final int? sessionId;
  final String sessionName;
  final String sportName;
  final String drawingMethod;
  final String format;
  final String? jenisPermainan;
  final DateTime? waktuSession;
  final List<String>? playerNames;
  final List<int>? playerIds;
  final List<String>? courtNames;
  final List<int>? courtIds;
  final DrawingController? controller;

  const DrawingResultPage({
    super.key,
    this.sessionId,
    this.sessionName = 'Saturday Morning',
    this.sportName = 'Tennis',
    this.drawingMethod = 'Americano',
    this.format = 'Doubles',
    this.jenisPermainan,
    this.waktuSession,
    this.playerNames,
    this.playerIds,
    this.courtNames,
    this.courtIds,
    this.controller,
  });

  @override
  State<DrawingResultPage> createState() => _DrawingResultPageState();
}

class _DrawingResultPageState extends State<DrawingResultPage> {
  late final DrawingController _controller;

  late final List<String> _effectivePlayerNames;
  late final List<int> _effectivePlayerIds;
  late final List<String> _effectiveCourtNames;
  late final List<int> _effectiveCourtIds;

  @override
  void initState() {
    super.initState();
    _controller = widget.controller ?? DrawingController();

    _effectivePlayerNames = widget.playerNames ??
        [
          'Aldi',
          'Budi',
          'Caca',
          'Dina',
          'Eka',
          'Fajar',
          'Gilang',
          'Hadi',
          'Indra',
          'Joko'
        ];
    _effectivePlayerIds = widget.playerIds ??
        List<int>.generate(_effectivePlayerNames.length, (i) => i + 1);

    _effectiveCourtNames = widget.courtNames ??
        ['Court 1 — SiJi Tennis Court', 'Court 2 — SiJi Tennis Court'];
    _effectiveCourtIds = widget.courtIds ?? [1, 2];

    // Generate initial drawing round
    _controller.generateDrawing(
      sessionName: widget.sessionName,
      sportName: widget.sportName,
      drawingMethod: widget.drawingMethod,
      format: widget.format,
      playerNames: _effectivePlayerNames,
      playerIds: _effectivePlayerIds,
      courtNames: _effectiveCourtNames,
      courtIds: _effectiveCourtIds,
      roundNumber: 1,
    );
  }

  void _handleReRoll() {
    _controller.reShuffleDrawing(
      playerNames: _effectivePlayerNames,
      playerIds: _effectivePlayerIds,
      courtNames: _effectiveCourtNames,
      courtIds: _effectiveCourtIds,
    );

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Drawing berhasil diacak ulang secara adil & acak! 🎲',
          style: AppTextStyles.body.copyWith(
            color: context.txtPrimary,
            fontWeight: FontWeight.w600,
          ),
        ),
        backgroundColor: context.surf,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: context.surfBorder, width: 1),
        ),
        duration: const Duration(seconds: 2),
      ),
    );
  }

  void _handleStartMatch() async {
    final nav = Navigator.of(context);
    await _controller.saveDrawingToSupabase();

    final currentRound = _controller.currentRound;
    final firstMatch = currentRound?.matches.isNotEmpty == true
        ? currentRound!.matches.first
        : null;

    nav.push(
      MaterialPageRoute(
        builder: (context) => MatchScoringPage(
          matchId: firstMatch?.matchId,
          sessionName: widget.sessionName,
          courtName: firstMatch?.courtName ?? 'Court 1 — SiJi Tennis Court',
          sideA: firstMatch?.sideADisplay ?? 'Aldi · Budi',
          sideB: firstMatch?.sideBDisplay ?? 'Caca · Dina',
        ),
      ),
    );
  }

  String _formatSessionDateTime() {
    if (widget.waktuSession != null) {
      final date = widget.waktuSession!;
      const monthNames = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des',
      ];
      final hour = date.hour.toString().padLeft(2, '0');
      final minute = date.minute.toString().padLeft(2, '0');
      return '${date.day} ${monthNames[date.month - 1]} ${date.year} · $hour:$minute';
    }
    return 'Hari ini · 08:00';
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: _controller,
      builder: (context, _) {
        final currentRound = _controller.currentRound;
        final matches = currentRound?.matches ?? [];
        final waitingPlayers = currentRound?.waitingPlayers ?? [];

        return Scaffold(
          backgroundColor: context.bg,
          appBar: AppBar(
            title: Text(
              'Drawing Result',
              style: TextStyle(color: context.txtPrimary),
            ),
            leading: IconButton(
              icon: Icon(
                Icons.arrow_back_ios_new_rounded,
                size: 18,
                color: context.txtPrimary,
              ),
              onPressed: () => Navigator.maybePop(context),
            ),
            actions: [
              IconButton(
                icon: Icon(Icons.refresh_rounded, color: context.brandColor),
                tooltip: 'Acak Ulang Drawing',
                onPressed: _handleReRoll,
              ),
            ],
          ),
          body: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(
                horizontal: 20.0,
                vertical: 16.0,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // 1. Session Info Banner
                  _buildSessionInfoBanner(context, currentRound),
                  const SizedBox(height: 20),

                  // 2. Section Title
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'SUSUNAN PERTANDINGAN',
                        style: AppTextStyles.badge.copyWith(
                          color: context.txtSecondary,
                          letterSpacing: 1.5,
                        ),
                      ),
                      Text(
                        '${matches.length} Court Aktif',
                        style: AppTextStyles.caption.copyWith(
                          color: context.brandColor,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  // 3. Match Cards per Court
                  if (_controller.isLoading)
                    Center(
                      child: Padding(
                        padding: const EdgeInsets.all(32.0),
                        child: CircularProgressIndicator(
                          color: context.brandColor,
                        ),
                      ),
                    )
                  else ...[
                    ...matches.map(
                      (match) => Padding(
                        padding: const EdgeInsets.only(bottom: 14.0),
                        child: _buildMatchCard(context, match),
                      ),
                    ),
                  ],

                  const SizedBox(height: 10),

                  // 4. Waiting List Card
                  if (waitingPlayers.isNotEmpty) ...[
                    _buildWaitingListCard(context, waitingPlayers),
                    const SizedBox(height: 24),
                  ],

                  const SizedBox(height: 12),

                  // 5. Action Buttons (Mulai Match & Re-Draw)
                  ElevatedButton(
                    onPressed: _handleStartMatch,
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
                    onPressed: _handleReRoll,
                    icon: Icon(
                      Icons.shuffle_rounded,
                      size: 18,
                      color: context.brandColor,
                    ),
                    label: Text(
                      'Acak Ulang Susunan (Re-Draw)',
                      style: TextStyle(color: context.txtPrimary),
                    ),
                  ),
                  const SizedBox(height: 20),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildSessionInfoBanner(
    BuildContext context,
    DrawingRoundModel? round,
  ) {
    final roundNumber = round?.roundNumber ?? 1;
    final method = round?.drawingMethod ?? widget.drawingMethod;
    final formatDisplay = widget.format.isNotEmpty
        ? widget.format
        : (widget.jenisPermainan == 'Single' ? 'Singles' : 'Doubles');

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'RONDE $roundNumber',
                style: AppTextStyles.badge.copyWith(
                  color: context.brandColor,
                  letterSpacing: 2,
                  fontSize: 12,
                ),
              ),
              Text(
                _formatSessionDateTime(),
                style: AppTextStyles.caption.copyWith(
                  color: context.txtSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            widget.sessionName,
            style: AppTextStyles.sectionTitle.copyWith(
              fontSize: 18,
              color: context.txtPrimary,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              Icon(
                Icons.sports_tennis_rounded,
                size: 14,
                color: context.brandColor,
              ),
              const SizedBox(width: 6),
              Text(
                '${widget.sportName} · Metode: $method · $formatDisplay',
                style: AppTextStyles.caption.copyWith(
                  color: context.txtSecondary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMatchCard(BuildContext context, MatchPairingModel match) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Court Header & Status
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                match.courtName,
                style: AppTextStyles.cardTitle.copyWith(
                  fontSize: 14,
                  color: context.brandColor,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 8,
                  vertical: 3,
                ),
                decoration: BoxDecoration(
                  color: context.brandColor.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: context.brandColor,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      match.statusMatch,
                      style: AppTextStyles.badge.copyWith(
                        color: context.brandColor,
                        fontSize: 9,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Team A vs Team B Display
          Container(
            padding: const EdgeInsets.symmetric(
              vertical: 12,
              horizontal: 14,
            ),
            decoration: BoxDecoration(
              color: context.surfSec,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: context.surfBorder),
            ),
            child: Row(
              children: [
                // Side A
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Text(
                        'SIDE A',
                        style: AppTextStyles.badge.copyWith(
                          fontSize: 10,
                          color: context.txtSecondary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        match.sideADisplay,
                        textAlign: TextAlign.center,
                        style: AppTextStyles.body.copyWith(
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          color: context.txtPrimary,
                        ),
                      ),
                    ],
                  ),
                ),

                // VS Badge
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: context.surf,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: context.surfBorder),
                  ),
                  child: Text(
                    'VS',
                    style: AppTextStyles.badge.copyWith(
                      color: context.brandColor,
                      fontSize: 11,
                    ),
                  ),
                ),

                // Side B
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Text(
                        'SIDE B',
                        style: AppTextStyles.badge.copyWith(
                          fontSize: 10,
                          color: context.txtSecondary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        match.sideBDisplay,
                        textAlign: TextAlign.center,
                        style: AppTextStyles.body.copyWith(
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          color: context.txtPrimary,
                        ),
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

  Widget _buildWaitingListCard(
    BuildContext context,
    List<String> waitingPlayers,
  ) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  const Icon(
                    Icons.pause_circle_outline_rounded,
                    size: 18,
                    color: AppColors.warning,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'WAITING PLAYERS (${waitingPlayers.length})',
                    style: AppTextStyles.badge.copyWith(
                      color: AppColors.warning,
                      letterSpacing: 1,
                    ),
                  ),
                ],
              ),
              Text(
                'Prioritas Ronde 2',
                style: AppTextStyles.caption.copyWith(
                  fontSize: 11,
                  color: context.txtSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: waitingPlayers.map((name) {
              return Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: context.surfSec,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: context.surfBorder),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: AppColors.warning,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      name,
                      style: AppTextStyles.body.copyWith(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: context.txtPrimary,
                      ),
                    ),
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
