import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../match/presentation/match_scoring_page.dart';
import '../../session/presentation/controllers/session_controller.dart';

class DrawingResultPage extends StatefulWidget {
  final int sessionId;
  final String sessionName;
  final String sportName;
  final String drawingMethod;
  final String jenisPermainan;
  final DateTime waktuSession;

  const DrawingResultPage({
    super.key,
    required this.sessionId,
    required this.sessionName,
    required this.sportName,
    required this.drawingMethod,
    required this.jenisPermainan,
    required this.waktuSession,
  });

  @override
  State<DrawingResultPage> createState() => _DrawingResultPageState();
}

class _DrawingResultPageState extends State<DrawingResultPage> {
  late final SessionController _sessionController;

  @override
  void initState() {
    super.initState();

    _sessionController = SessionController();

    _loadSessionDetails();
  }

  Future<void> _loadSessionDetails() async {
    await _sessionController.loadSessionDetails(
      sessionId: widget.sessionId,
    );

    if (mounted) {
      setState(() {});
    }
  }

  @override
  void dispose() {
    _sessionController.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> get _sessionCourts {
    return _sessionController.sessionCourts;
  }

  List<Map<String, dynamic>> get _sessionPlayers {
    return _sessionController.sessionPlayers;
  }

  String _formatSessionDateTime() {
    final date = widget.waktuSession;

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

    return '${date.day} ${monthNames[date.month - 1]} '
        '${date.year} · $hour:$minute';
  }

  void _reRollDrawing() {
    setState(() {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Drawing berhasil diacak ulang secara acak & adil! 🎲',
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
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text('Drawing Result', style: TextStyle(color: context.txtPrimary)),
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, size: 18, color: context.txtPrimary),
          onPressed: () => Navigator.maybePop(context),
        ),
        actions: [
          IconButton(
            icon: Icon(Icons.refresh_rounded, color: context.brandColor),
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
              _buildSessionInfoBanner(context),
              const SizedBox(height: 20),

              // 2. Section Title
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'SUSUNAN PERTANDINGAN',
                    style: AppTextStyles.badge.copyWith(color: context.txtSecondary, letterSpacing: 1.5),
                  ),
                  Text('${_sessionCourts.length} Court Aktif', style: AppTextStyles.caption.copyWith(color: context.brandColor)),
                ],
              ),
              const SizedBox(height: 12),

              // 3. Match Cards per Court
              ..._sessionCourts.map(
                (court) => Padding(
                  padding: const EdgeInsets.only(bottom: 14.0),
                  child: _buildCourtCard(
                    context,
                    court,
                  ),
                ),
              ),

              const SizedBox(height: 10),

              // 4. Waiting List Card
              _buildWaitingListCard(context),
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
                icon: Icon(Icons.shuffle_rounded, size: 18, color: context.brandColor),
                label: Text('Acak Ulang Susunan (Re-Draw)', style: TextStyle(color: context.txtPrimary)),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSessionInfoBanner(BuildContext context) {
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
                'RONDE 1',
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
          Text(widget.sessionName, style: AppTextStyles.sectionTitle.copyWith(fontSize: 18, color: context.txtPrimary)),
          const SizedBox(height: 4),
          Row(
            children: [
              Icon(Icons.sports_tennis_rounded, size: 14, color: context.brandColor),
              const SizedBox(width: 6),
              Text(
                '${widget.sportName} · Metode: ${widget.drawingMethod} · '
                '${widget.jenisPermainan == 'Single' ? 'Singles' : 'Doubles'}',
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

  Widget _buildCourtCard(
    BuildContext context,
    Map<String, dynamic> court,
  ) {
    final courtData = court['tb_court'];

    final courtName = courtData is Map
        ? courtData['nama_court']?.toString() ?? '-'
        : '-';

    final location = courtData is Map
        ? courtData['lokasi']?.toString() ?? ''
        : '';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: context.surfBorder,
        ),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: context.surfSec,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              Icons.sports_tennis_rounded,
              color: context.brandColor,
              size: 22,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  courtName,
                  style: AppTextStyles.cardTitle.copyWith(
                    fontSize: 14,
                    color: context.txtPrimary,
                  ),
                ),
                if (location.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    location,
                    style: AppTextStyles.caption.copyWith(
                      color: context.txtSecondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(
              horizontal: 8,
              vertical: 4,
            ),
            decoration: BoxDecoration(
              color: context.brandColor.withValues(
                alpha: 0.15,
              ),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              'DIPILIH',
              style: AppTextStyles.badge.copyWith(
                color: context.brandColor,
                fontSize: 9,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMatchCard(BuildContext context, Map<String, dynamic> match) {
    final List<String> sideA = List<String>.from(match['sideA']);
    final List<String> sideB = List<String>.from(match['sideB']);

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
                match['court'],
                style: AppTextStyles.cardTitle.copyWith(fontSize: 14, color: context.brandColor),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
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
                      'PLAYING',
                      style: AppTextStyles.badge.copyWith(color: context.brandColor, fontSize: 9),
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
                      Text('SIDE A', style: AppTextStyles.badge.copyWith(fontSize: 10, color: context.txtSecondary)),
                      const SizedBox(height: 4),
                      Text(
                        sideA.join(' · '),
                        textAlign: TextAlign.center,
                        style: AppTextStyles.body.copyWith(fontWeight: FontWeight.bold, fontSize: 14, color: context.txtPrimary),
                      ),
                    ],
                  ),
                ),

                // VS Badge
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: context.surf,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: context.surfBorder),
                  ),
                  child: Text(
                    'VS',
                    style: AppTextStyles.badge.copyWith(color: context.brandColor, fontSize: 11),
                  ),
                ),

                // Side B
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Text('SIDE B', style: AppTextStyles.badge.copyWith(fontSize: 10, color: context.txtSecondary)),
                      const SizedBox(height: 4),
                      Text(
                        sideB.join(' · '),
                        textAlign: TextAlign.center,
                        style: AppTextStyles.body.copyWith(fontWeight: FontWeight.bold, fontSize: 14, color: context.txtPrimary),
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

  Widget _buildWaitingListCard(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: context.surfBorder,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'PLAYERS SESSION (${_sessionPlayers.length})',
            style: AppTextStyles.caption.copyWith(
              color: context.txtSecondary,
              fontWeight: FontWeight.bold,
              letterSpacing: 0.5,
            ),
          ),
          const SizedBox(height: 12),

          if (_sessionPlayers.isEmpty)
            Text(
              'Belum ada pemain dalam session.',
              style: AppTextStyles.body.copyWith(
                color: context.txtSecondary,
              ),
            )
          else
            ..._sessionPlayers.map((player) {
              final playerData = player['tb_player'];

              final playerName = playerData is Map
                  ? playerData['nama_player']?.toString() ?? '-'
                  : '-';

              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Row(
                  children: [
                    Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        color: context.brandColor.withValues(alpha: 0.12),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.person_outline_rounded,
                        color: context.brandColor,
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        playerName,
                        style: AppTextStyles.body.copyWith(
                          color: context.txtPrimary,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              );
            }),
        ],
      ),
    );
  }
}
