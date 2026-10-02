import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/app_error_handler.dart';
import '../../../core/widgets/offline_state_widget.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../session/domain/session_model.dart';
import '../data/recap_service.dart';
import '../domain/recap_models.dart';

class SessionMatchRecapPage extends StatefulWidget {
  final int sessionId;
  final SessionModel? session;
  final AuthController? authController;

  const SessionMatchRecapPage({
    super.key,
    required this.sessionId,
    this.session,
    this.authController,
  });

  @override
  State<SessionMatchRecapPage> createState() => _SessionMatchRecapPageState();
}

class _SessionMatchRecapPageState extends State<SessionMatchRecapPage> {
  final RecapService _recapService = RecapService();

  bool _isLoading = true;
  String? _errorMessage;
  SessionMatchRecapData? _data;

  // Kudos state: playerId -> Set of kudos tag names given by user
  final Map<int, Set<String>> _givenKudos = {};
  final Map<int, Map<String, int>> _kudosCounts = {};

  final List<String> _kudosOptions = const [
    '🎾 Super Forehand',
    '🛡️ Solid Defense',
    '🤝 Fun Partner',
    '💥 Killer Smash',
    '⭐ MVP Play',
    '✨ Fair Play',
  ];

  @override
  void initState() {
    super.initState();
    _loadRecap();
  }

  Future<void> _loadRecap() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final currentUserId = widget.authController?.currentUser?.userId;
      final recap = await _recapService.getSessionMatchRecap(
        widget.sessionId,
        currentUserId: currentUserId,
      );

      if (!mounted) return;
      setState(() {
        _data = recap;
        _givenKudos.clear();
        for (final entry in recap.userGivenKudos.entries) {
          _givenKudos[entry.key] = Set<String>.from(entry.value);
        }
        _kudosCounts.clear();
        for (final entry in recap.kudosMap.entries) {
          _kudosCounts[entry.key] = Map<String, int>.from(entry.value);
        }
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  void _toggleKudos(int playerId, String playerName, String kudosTag) {
    final currentUserId = widget.authController?.currentUser?.userId;
    setState(() {
      final userTags = _givenKudos.putIfAbsent(playerId, () => <String>{});
      final playerCounts = _kudosCounts.putIfAbsent(playerId, () => <String, int>{});

      if (userTags.contains(kudosTag)) {
        userTags.remove(kudosTag);
        final currentCount = playerCounts[kudosTag] ?? 1;
        playerCounts[kudosTag] = (currentCount - 1) > 0 ? (currentCount - 1) : 0;
      } else {
        userTags.add(kudosTag);
        playerCounts[kudosTag] = (playerCounts[kudosTag] ?? 0) + 1;
        AppErrorHandler.showSuccessSnackBar(
          context,
          'Kudos "$kudosTag" berhasil diberikan ke $playerName! 👏',
        );
      }
    });

    // Panggil service untuk persistensi ke database tb_kudos
    _recapService.toggleKudos(
      sessionId: widget.sessionId,
      giverUserId: currentUserId,
      recipientPlayerId: playerId,
      recipientName: playerName,
      badge: kudosTag,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A), size: 20),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Detail Mabar',
          style: TextStyle(
            color: Color(0xFF0F172A),
            fontWeight: FontWeight.bold,
            fontSize: 15,
          ),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : _errorMessage != null && _data == null
              ? OfflineStateWidget(
                  error: _errorMessage,
                  customTitle: 'Gagal Memuat Rekap Pertandingan',
                  onRetry: _loadRecap,
                )
              : _data == null
                  ? const SizedBox.shrink()
                  : RefreshIndicator(
                      onRefresh: _loadRecap,
                      color: AppColors.matchaDark,
                      child: SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // 0. Top Action Breadcrumbs Row (Kembali ke Daftar Mabar & Bagikan)
                            _buildTopActionBreadcrumbsRow(_data!),

                            const SizedBox(height: 12),

                            // 1. Hero Winner Card
                            _buildHeroWinnerCard(_data!),

                            const SizedBox(height: 18),

                            // 2. Section 1: Riwayat Hasil Pertandingan Seluruh Ronde
                            _buildRoundsHistorySection(_data!),

                            const SizedBox(height: 18),

                            // 3. Section 2: Podium & Klasemen Akhir
                            _buildPodiumAndStandingsSection(_data!),

                            const SizedBox(height: 18),

                            // 4. Section 3: Kudos System
                            _buildKudosSection(_data!),

                            const SizedBox(height: 18),

                            // 5. Section 4: Statistik Pribadi Pemain
                            if (_data!.myStats != null) ...[
                              _buildPersonalStatsCard(_data!.myStats!),
                              const SizedBox(height: 20),
                            ],
                          ],
                        ),
                      ),
                    ),
    );
  }

  /// 0. Top Action Breadcrumbs Row (Matching Web /scoring/recap layout)
  Widget _buildTopActionBreadcrumbsRow(SessionMatchRecapData data) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // Kembali ke Daftar Mabar
        GestureDetector(
          onTap: () => Navigator.pop(context),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.arrow_back_rounded, size: 13, color: Color(0xFF64748B)),
              SizedBox(width: 4),
              Text(
                'Kembali ke Daftar Mabar',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF64748B),
                ),
              ),
            ],
          ),
        ),

        // Badges & Actions (Match Finished + Bagikan Button)
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Badge Match Finished
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: const Color(0xFFEBF8D8),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.25)),
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.check_circle_rounded, size: 11, color: Color(0xFF063B00)),
                  SizedBox(width: 3),
                  Text(
                    'Match Finished',
                    style: TextStyle(
                      color: Color(0xFF063B00),
                      fontSize: 9.5,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),

            // Button Bagikan (Dark Green with Lime Share Icon)
            GestureDetector(
              onTap: () => _showShareModal(context, data),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: const Color(0xFF063B00),
                  borderRadius: BorderRadius.circular(10),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF063B00).withValues(alpha: 0.2),
                      blurRadius: 4,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.share_rounded, size: 12, color: Color(0xFFA8E63A)),
                    SizedBox(width: 4),
                    Text(
                      'Bagikan',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  void _showShareModal(BuildContext context, SessionMatchRecapData data) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          padding: const EdgeInsets.all(20),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEBF8D8),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.share_rounded, size: 18, color: Color(0xFF063B00)),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Bagikan Hasil Rekap Mabar',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                        ),
                        Text(
                          data.sessionName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.link_rounded, color: Color(0xFF0F172A), size: 20),
                ),
                title: const Text('Salin Tautan Rekap', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                subtitle: const Text('Bagikan tautan hasil pertandingan ke teman', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                onTap: () {
                  Navigator.pop(ctx);
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Tautan rekap pertandingan berhasil disalin! 🔗'),
                      backgroundColor: Color(0xFF063B00),
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                },
              ),
              const SizedBox(height: 10),
            ],
          ),
        );
      },
    );
  }

  /// 1. Hero Winner Card (matching web gold gradient banner)
  Widget _buildHeroWinnerCard(SessionMatchRecapData data) {
    final winner = data.standings.isNotEmpty ? data.standings.first : null;
    final winnerName = winner?.nama ?? 'Juara';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFFFFFBEB),
            Color(0xFFFEF3C7),
            Color(0xFFFFFDF5),
          ],
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFFDE68A), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFD97706).withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Juara 1 Pill
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
            decoration: BoxDecoration(
              color: const Color(0xFFFEF9C3),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFFACC15), width: 1.2),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text('🏆', style: TextStyle(fontSize: 14)),
                const SizedBox(width: 6),
                Text(
                  'Juara 1 - $winnerName',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 12.5,
                    color: Color(0xFF854D0E),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // Session Title
          Text(
            data.sessionName,
            textAlign: TextAlign.center,
            style: AppTextStyles.h1.copyWith(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color: const Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 6),

          // Venue & Format info
          Text(
            '${data.totalRounds} Ronde Selesai • ${data.venueName}${data.courtName != null ? ' (${data.courtName})' : ''} • ${data.sportName} • ${data.scoringSystem}',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 12,
              color: Color(0xFF64748B),
              height: 1.35,
            ),
          ),
          const SizedBox(height: 14),

          // Badges Row
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              _buildSummaryPill('👥 ${data.totalPlayers} Peserta'),
              _buildSummaryPill('🔄 ${data.totalRounds} Ronde'),
              _buildSummaryPill('✨ Rekap Final Selesai', isGreen: true),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryPill(String label, {bool isGreen = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: isGreen ? const Color(0xFFF0FDF4) : Colors.white.withValues(alpha: 0.9),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isGreen ? const Color(0xFF86EFAC) : const Color(0xFFE2E8F0),
        ),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: isGreen ? const Color(0xFF16A34A) : const Color(0xFF475569),
        ),
      ),
    );
  }

  /// 2. Section: Riwayat Hasil Pertandingan Seluruh Ronde
  Widget _buildRoundsHistorySection(SessionMatchRecapData data) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(7),
                decoration: BoxDecoration(
                  color: AppColors.matchaSoftLime,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.history_rounded, size: 18, color: AppColors.matchaDark),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Riwayat Hasil Pertandingan Seluruh Ronde',
                      style: AppTextStyles.h3.copyWith(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Rekap hasil skor tiap ronde yang telah di-scoring dalam sesi ini',
                      style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // List of Round Cards
          if (data.rounds.isEmpty)
            Container(
              padding: const EdgeInsets.all(16),
              alignment: Alignment.center,
              child: const Text('Belum ada data ronde pertandingan.', style: TextStyle(color: Color(0xFF64748B), fontSize: 12)),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: data.rounds.length,
              separatorBuilder: (context, index) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final round = data.rounds[index];
                return _buildSingleRoundCard(round);
              },
            ),
        ],
      ),
    );
  }

  Widget _buildSingleRoundCard(SessionRoundRecapItem round) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Round Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Round ${round.roundNumber}',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 13,
                  color: Color(0xFF0F172A),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFF0FDF4),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFBBF7D0)),
                ),
                child: const Text(
                  'Selesai',
                  style: TextStyle(
                    color: Color(0xFF16A34A),
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Matches in this round
          ...round.matches.map((m) => _buildMatchRow(m)),
        ],
      ),
    );
  }

  Widget _buildMatchRow(SessionMatchItem match) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFF1F5F9)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Side A
          Row(
            children: [
              Icon(
                match.isSideAWinner ? Icons.check_circle_rounded : Icons.circle_outlined,
                size: 14,
                color: match.isSideAWinner ? const Color(0xFF16A34A) : const Color(0xFF94A3B8),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  match.sideANames.join(' & '),
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: match.isSideAWinner ? FontWeight.bold : FontWeight.w600,
                    color: match.isSideAWinner ? const Color(0xFF0F172A) : const Color(0xFF475569),
                  ),
                ),
              ),
              if (match.isSideAWinner) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCFCE7),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text('WIN', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: Color(0xFF15803D))),
                ),
                const SizedBox(width: 8),
              ],
              Text(
                '${match.scoreA}',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                  color: match.isSideAWinner ? const Color(0xFF15803D) : const Color(0xFF64748B),
                ),
              ),
            ],
          ),
          const Divider(height: 10, color: Color(0xFFF1F5F9)),

          // Side B
          Row(
            children: [
              Icon(
                match.isSideBWinner ? Icons.check_circle_rounded : Icons.circle_outlined,
                size: 14,
                color: match.isSideBWinner ? const Color(0xFF16A34A) : const Color(0xFF94A3B8),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  match.sideBNames.join(' & '),
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: match.isSideBWinner ? FontWeight.bold : FontWeight.w600,
                    color: match.isSideBWinner ? const Color(0xFF0F172A) : const Color(0xFF475569),
                  ),
                ),
              ),
              if (match.isSideBWinner) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCFCE7),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text('WIN', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: Color(0xFF15803D))),
                ),
                const SizedBox(width: 8),
              ],
              Text(
                '${match.scoreB}',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                  color: match.isSideBWinner ? const Color(0xFF15803D) : const Color(0xFF64748B),
                ),
              ),
            ],
          ),

          if (match.setDetails.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              match.setDetails,
              style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
            ),
          ],
        ],
      ),
    );
  }

  /// 3. Section: Podium & Klasemen Akhir
  Widget _buildPodiumAndStandingsSection(SessionMatchRecapData data) {
    final standings = data.standings;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(7),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.emoji_events_rounded, size: 18, color: Color(0xFFD97706)),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Podium & Klasemen Akhir',
                      style: AppTextStyles.h3.copyWith(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Distribusi skor: Match Menang -> Total Game -> Selisih Game',
                      style: TextStyle(fontSize: 10.5, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Visual Podium for Top 3
          if (standings.isNotEmpty) _buildPodiumVisual(standings),

          const SizedBox(height: 20),

          // Table Ranking & Statistik
          const Text(
            'RANKING & STATISTIK',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: Color(0xFF64748B),
              letterSpacing: 0.5,
            ),
          ),
          const SizedBox(height: 10),

          // Ranking Rows
          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: standings.length,
            separatorBuilder: (context, index) => const Divider(height: 1, color: Color(0xFFF1F5F9)),
            itemBuilder: (context, index) {
              final player = standings[index];
              return _buildStandingRow(player);
            },
          ),
        ],
      ),
    );
  }

  Widget _buildPodiumVisual(List<SessionPlayerStanding> standings) {
    final first = standings.isNotEmpty ? standings[0] : null;
    final second = standings.length > 1 ? standings[1] : null;
    final third = standings.length > 2 ? standings[2] : null;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        // 2nd Place (Silver - Left)
        if (second != null)
          Expanded(
            child: Column(
              children: [
                _buildPodiumAvatar(second.foto, second.nama, '🥈'),
                const SizedBox(height: 6),
                Text(
                  second.nama,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF0F172A)),
                ),
                Text(
                  '${second.matchesWon} Win • ${second.gamesWon} Games',
                  style: const TextStyle(fontSize: 9.5, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 6),
                Container(
                  height: 75,
                  decoration: const BoxDecoration(
                    color: Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
                  ),
                  alignment: Alignment.center,
                  child: const Text('2nd', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: Color(0xFF475569))),
                ),
              ],
            ),
          )
        else
          const Spacer(),

        const SizedBox(width: 8),

        // 1st Place (Gold - Center)
        if (first != null)
          Expanded(
            child: Column(
              children: [
                _buildPodiumAvatar(first.foto, first.nama, '👑', isGold: true),
                const SizedBox(height: 6),
                Text(
                  first.nama,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 12, color: Color(0xFF0F172A)),
                ),
                Text(
                  '${first.matchesWon} Win • ${first.gamesWon} Games',
                  style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w600, color: Color(0xFFD97706)),
                ),
                const SizedBox(height: 6),
                Container(
                  height: 105,
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Color(0xFFFDE047), Color(0xFFEAB308)],
                    ),
                    borderRadius: BorderRadius.vertical(top: Radius.circular(14)),
                  ),
                  alignment: Alignment.center,
                  child: const Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text('🌱', style: TextStyle(fontSize: 18)),
                      SizedBox(height: 2),
                      Text('JUARA 1', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, color: Color(0xFF713F12))),
                    ],
                  ),
                ),
              ],
            ),
          ),

        const SizedBox(width: 8),

        // 3rd Place (Bronze - Right)
        if (third != null)
          Expanded(
            child: Column(
              children: [
                _buildPodiumAvatar(third.foto, third.nama, '🥉'),
                const SizedBox(height: 6),
                Text(
                  third.nama,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF0F172A)),
                ),
                Text(
                  '${third.matchesWon} Win • ${third.gamesWon} Games',
                  style: const TextStyle(fontSize: 9.5, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 6),
                Container(
                  height: 60,
                  decoration: const BoxDecoration(
                    color: Color(0xFFFED7AA),
                    borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
                  ),
                  alignment: Alignment.center,
                  child: const Text('3rd', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: Color(0xFF9A3412))),
                ),
              ],
            ),
          )
        else
          const Spacer(),
      ],
    );
  }

  Widget _buildPodiumAvatar(String? foto, String name, String badgeEmoji, {bool isGold = false}) {
    return Stack(
      alignment: Alignment.topRight,
      clipBehavior: Clip.none,
      children: [
        Container(
          width: isGold ? 52 : 44,
          height: isGold ? 52 : 44,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
              color: isGold ? const Color(0xFFEAB308) : const Color(0xFF94A3B8),
              width: isGold ? 2.5 : 1.5,
            ),
          ),
          child: ClipOval(
            child: (foto != null && foto.isNotEmpty)
                ? Image.network(
                    foto,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) => Container(
                      color: AppColors.matchaSoftLime,
                      alignment: Alignment.center,
                      child: Text(name.isNotEmpty ? name[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  )
                : Container(
                    color: AppColors.matchaSoftLime,
                    alignment: Alignment.center,
                    child: Text(name.isNotEmpty ? name[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold)),
                  ),
          ),
        ),
        Positioned(
          top: -4,
          right: -4,
          child: Text(badgeEmoji, style: TextStyle(fontSize: isGold ? 16 : 13)),
        ),
      ],
    );
  }

  Widget _buildStandingRow(SessionPlayerStanding player) {
    String rankMedal = '#${player.rank}';
    if (player.rank == 1) rankMedal = '🥇 1';
    if (player.rank == 2) rankMedal = '🥈 2';
    if (player.rank == 3) rankMedal = '🥉 3';

    final isSets = _data?.isSets ?? false;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          // Rank Badge
          SizedBox(
            width: 36,
            child: Text(
              rankMedal,
              style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13, color: Color(0xFF0F172A)),
            ),
          ),
          const SizedBox(width: 8),

          // Avatar
          Container(
            width: 32,
            height: 32,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.matchaSoftLime,
            ),
            child: ClipOval(
              child: (player.foto != null && player.foto!.isNotEmpty)
                  ? Image.network(
                      player.foto!,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) => Center(
                        child: Text(player.nama.isNotEmpty ? player.nama[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11)),
                      ),
                    )
                  : Center(
                      child: Text(player.nama.isNotEmpty ? player.nama[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11)),
                    ),
            ),
          ),
          const SizedBox(width: 10),

          // Name & Level
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  player.nama,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5, color: Color(0xFF0F172A)),
                ),
                Text(
                  '${player.level} • ${player.matchesPlayed} match',
                  style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                ),
              ],
            ),
          ),

          // Stats Columns (Win, Sets?, Game, Selisih)
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              _buildStatCol('${player.matchesWon}', 'Win'),
              if (isSets) ...[
                const SizedBox(width: 8),
                _buildStatCol('${player.setsWon}', 'Sets'),
              ],
              const SizedBox(width: 8),
              _buildStatCol('${player.gamesWon}', 'Games'),
              const SizedBox(width: 8),
              _buildStatCol(
                player.gameDiff >= 0 ? '+${player.gameDiff}' : '${player.gameDiff}',
                'Selisih',
                isHighlight: true,
                isPositive: player.gameDiff >= 0,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatCol(String val, String label, {bool isHighlight = false, bool isPositive = true}) {
    return Column(
      children: [
        Text(
          val,
          style: TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 12,
            color: isHighlight
                ? (isPositive ? const Color(0xFF15803D) : const Color(0xFFDC2626))
                : const Color(0xFF0F172A),
          ),
        ),
        Text(
          label,
          style: const TextStyle(fontSize: 8.5, color: Color(0xFF94A3B8)),
        ),
      ],
    );
  }

  /// 4. Section: Kudos System (Beri Kudos untuk Seluruh Pemain)
  Widget _buildKudosSection(SessionMatchRecapData data) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(7),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF9C3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text('🏅', style: TextStyle(fontSize: 16)),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Beri Kudos untuk Seluruh Pemain (Kudos System)',
                      style: AppTextStyles.h3.copyWith(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Apresiasi skill & sportivitas sesama pemain di lapangan',
                      style: TextStyle(fontSize: 10.5, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Player Kudos Cards
          ...data.standings.map((p) => _buildPlayerKudosCard(p)),
        ],
      ),
    );
  }

  Widget _buildPlayerKudosCard(SessionPlayerStanding player) {
    final userGiven = _givenKudos[player.playerId] ?? <String>{};

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header Player
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.matchaSoftLime,
                ),
                child: ClipOval(
                  child: (player.foto != null && player.foto!.isNotEmpty)
                      ? Image.network(player.foto!, fit: BoxFit.cover, errorBuilder: (context, error, stackTrace) => Center(child: Text(player.nama[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 10))))
                      : Center(child: Text(player.nama.isNotEmpty ? player.nama[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 10))),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: RichText(
                  text: TextSpan(
                    text: player.nama,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF0F172A)),
                    children: [
                      TextSpan(
                        text: ' • Juara ${player.rank} (${player.level})',
                        style: const TextStyle(fontWeight: FontWeight.normal, fontSize: 10, color: Color(0xFF64748B)),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Kudos Chips
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: _kudosOptions.map((tag) {
              final isSelected = userGiven.contains(tag);
              final kCount = _kudosCounts[player.playerId]?[tag] ?? 0;

              return GestureDetector(
                onTap: () => _toggleKudos(player.playerId, player.nama, tag),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: isSelected ? const Color(0xFFFEF3C7) : Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: isSelected ? const Color(0xFFF59E0B) : const Color(0xFFE2E8F0),
                      width: isSelected ? 1.2 : 1.0,
                    ),
                    boxShadow: isSelected
                        ? [
                            BoxShadow(
                              color: const Color(0xFFF59E0B).withValues(alpha: 0.15),
                              blurRadius: 4,
                              offset: const Offset(0, 1),
                            ),
                          ]
                        : null,
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        tag,
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: isSelected ? FontWeight.bold : FontWeight.w600,
                          color: isSelected ? const Color(0xFF92400E) : const Color(0xFF475569),
                        ),
                      ),
                      if (kCount > 0) ...[
                        const SizedBox(width: 5),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                          decoration: BoxDecoration(
                            color: isSelected ? const Color(0xFFF59E0B) : const Color(0xFFE2E8F0),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            '$kCount',
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w900,
                              color: isSelected ? Colors.white : const Color(0xFF475569),
                            ),
                          ),
                        ),
                      ],
                      if (isSelected) ...[
                        const SizedBox(width: 4),
                        const Icon(Icons.check_rounded, size: 12, color: Color(0xFFD97706)),
                      ],
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  /// 5. Section: Statistik Pribadi Pemain
  Widget _buildPersonalStatsCard(SessionPersonalStat stat) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.matchaSoftLime,
                ),
                child: ClipOval(
                  child: (stat.foto != null && stat.foto!.isNotEmpty)
                      ? Image.network(stat.foto!, fit: BoxFit.cover, errorBuilder: (context, error, stackTrace) => Center(child: Text(stat.nama[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold))))
                      : Center(child: Text(stat.nama.isNotEmpty ? stat.nama[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold))),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      stat.nama,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A)),
                    ),
                    Text(
                      'Statistik Pertandingan • ${stat.sportName}',
                      style: const TextStyle(fontSize: 10.5, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: AppColors.matchaSoftLime,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                ),
                child: Text(
                  stat.level,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: AppColors.matchaDark,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Stat boxes
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      const Text('Total Poin', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                      const SizedBox(height: 2),
                      Text(
                        '${stat.totalPoints}',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      const Text('Win Rate', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                      const SizedBox(height: 2),
                      Text(
                        '${stat.winRatePercent}%',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF16A34A)),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      const Text('Durasi', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                      const SizedBox(height: 2),
                      Text(
                        stat.durationPlayed,
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                      ),
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
