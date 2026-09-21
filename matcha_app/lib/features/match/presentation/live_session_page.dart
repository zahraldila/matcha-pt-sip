import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import 'match_scoring_page.dart';

class LiveSessionPage extends StatefulWidget {
  final dynamic sessionId;
  final String sessionName;
  final String sportName;
  final bool isHost;

  const LiveSessionPage({
    super.key,
    this.sessionId,
    this.sessionName = 'Mabar Padel Live',
    this.sportName = 'Padel',
    this.isHost = true,
  });

  @override
  State<LiveSessionPage> createState() => _LiveSessionPageState();
}

class _LiveSessionPageState extends State<LiveSessionPage> {
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
    final isHost = _dataService.isHostMode;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          widget.sessionName,
          style: AppTextStyles.h2.copyWith(fontSize: 16, color: context.txtPrimary),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.shuffle_rounded),
            tooltip: 'Lihat Bagan & Drawing',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const DrawingResultPage()),
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
            // Live Status Banner
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
                    width: 10,
                    height: 10,
                    decoration: const BoxDecoration(
                      color: Colors.redAccent,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'SESI MABAR LIVE AKTIF',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                            color: Colors.redAccent,
                            letterSpacing: 0.5,
                          ),
                        ),
                        Text(
                          '${matches.length} Lapangan Sedang Bertanding',
                          style: AppTextStyles.caption.copyWith(color: AppColors.matchaDark, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                  ElevatedButton(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const MatchScoringPage()),
                      );
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: Text(
                      isHost ? 'Input Skor' : 'Papan Skor',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            Text(
              'Pertandingan Lapangan',
              style: AppTextStyles.h2.copyWith(fontSize: 16, color: context.txtPrimary),
            ),
            const SizedBox(height: 12),

            for (var match in matches) ...[
              Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: context.surf,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: context.surfBorder),
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
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          match.courtName,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppColors.matchaSoftLime,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppColors.matchaSoftLimeBorder),
                          ),
                          child: Text(
                            'Set ${_dataService.currentSet} • ${match.teamAScore} - ${match.teamBScore}',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w900,
                              color: AppColors.matchaDark,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            match.teamA.map((p) => p.name).join(' & '),
                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                          ),
                        ),
                        const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 8),
                          child: Text('VS', style: TextStyle(color: Colors.grey, fontSize: 11, fontWeight: FontWeight.w900)),
                        ),
                        Expanded(
                          child: Text(
                            match.teamB.map((p) => p.name).join(' & '),
                            textAlign: TextAlign.right,
                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
