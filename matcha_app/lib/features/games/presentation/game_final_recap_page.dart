import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../drawing/domain/matcha_drawing_engine.dart';
import '../../main/presentation/main_shell_page.dart';
import '../domain/game_wizard_model.dart';

class GameFinalRecapPage extends StatefulWidget {
  final GameWizardConfig config;
  final List<DrawingRound> rounds;
  final AuthController? authController;

  const GameFinalRecapPage({
    super.key,
    required this.config,
    required this.rounds,
    this.authController,
  });

  @override
  State<GameFinalRecapPage> createState() => _GameFinalRecapPageState();
}

class _GameFinalRecapPageState extends State<GameFinalRecapPage> {
  // Kudos Badges tracking: Map<playerId, Set<String>>
  final Map<String, Set<String>> _playerKudos = {};

  final List<Map<String, dynamic>> _kudosOptions = [
    {'name': 'Super Forehand', 'icon': Icons.flash_on_rounded, 'color': const Color(0xFFEF4444)},
    {'name': 'Solid Defense', 'icon': Icons.shield_rounded, 'color': const Color(0xFF3B82F6)},
    {'name': 'Fun Partner', 'icon': Icons.sentiment_very_satisfied_rounded, 'color': const Color(0xFFF59E0B)},
    {'name': 'Killer Smash', 'icon': Icons.local_fire_department_rounded, 'color': const Color(0xFFDC2626)},
    {'name': 'MVP Play', 'icon': Icons.star_rounded, 'color': const Color(0xFFEAB308)},
    {'name': 'Fair Play', 'icon': Icons.handshake_rounded, 'color': const Color(0xFF10B981)},
  ];

  late List<_PlayerScoreSummary> _ranking;
  late List<_TeamScoreSummary> _teamRanking;

  bool get _isTeamAmericano =>
      widget.config.gameType.toLowerCase().contains('team americano');

  @override
  void initState() {
    super.initState();
    _calculateRanking();
  }

  void _calculateRanking() {
    final Map<String, _PlayerScoreSummary> map = {};
    final Map<String, _TeamScoreSummary> teamMap = {};

    for (var p in widget.config.players) {
      map[p.id] = _PlayerScoreSummary(player: p);
    }

    for (var round in widget.rounds) {
      for (var match in round.matches) {
        final isCompleted = match.status.toLowerCase() == 'completed' ||
            (match.winnerTeam != null && match.winnerTeam!.isNotEmpty) ||
            (match.status != 'Scheduled' && match.status != 'In Progress' && (match.scoreA > 0 || match.scoreB > 0));

        final winA = match.winnerTeam != null && match.winnerTeam!.isNotEmpty
            ? (match.winnerTeam!.trim().toUpperCase() == 'A' || match.winnerTeam!.trim().toUpperCase() == 'TEAM A')
            : match.scoreA > match.scoreB;
        final winB = match.winnerTeam != null && match.winnerTeam!.isNotEmpty
            ? (match.winnerTeam!.trim().toUpperCase() == 'B' || match.winnerTeam!.trim().toUpperCase() == 'TEAM B')
            : match.scoreB > match.scoreA;

        // Individual player stats (selalu dihitung per pemain untuk Kudos & personal stats)
        if (isCompleted) {
          for (var p in match.teamA) {
            final s = map[p.id];
            if (s != null) {
              s.matchesPlayed++;
              s.totalPoints += match.scoreA;
              s.totalGames += match.gamesWonA > 0 ? match.gamesWonA : match.scoreA;
              s.diff += (match.scoreA - match.scoreB);
              if (winA) {
                s.wins++;
                s.setsWon++;
              }
            }
          }

          for (var p in match.teamB) {
            final s = map[p.id];
            if (s != null) {
              s.matchesPlayed++;
              s.totalPoints += match.scoreB;
              s.totalGames += match.gamesWonB > 0 ? match.gamesWonB : match.scoreB;
              s.diff += (match.scoreB - match.scoreA);
              if (winB) {
                s.wins++;
                s.setsWon++;
              }
            }
          }
        }

        // Team stats (KHUSUS TEAM AMERICANO)
        if (_isTeamAmericano && match.teamA.isNotEmpty && match.teamB.isNotEmpty) {
          final sortedA = List<GamePlayerItem>.from(match.teamA)..sort((a, b) => a.id.compareTo(b.id));
          final teamAKey = sortedA.map((p) => p.id).join('-');
          final teamAName = sortedA.map((p) => p.name).join(' & ');

          final sortedB = List<GamePlayerItem>.from(match.teamB)..sort((a, b) => a.id.compareTo(b.id));
          final teamBKey = sortedB.map((p) => p.id).join('-');
          final teamBName = sortedB.map((p) => p.name).join(' & ');

          teamMap.putIfAbsent(
            teamAKey,
            () => _TeamScoreSummary(
              teamId: teamAKey,
              teamName: teamAName,
              members: sortedA,
            ),
          );

          teamMap.putIfAbsent(
            teamBKey,
            () => _TeamScoreSummary(
              teamId: teamBKey,
              teamName: teamBName,
              members: sortedB,
            ),
          );

          if (isCompleted) {
            final tA = teamMap[teamAKey]!;
            tA.matchesPlayed++;
            tA.totalPoints += match.scoreA;
            tA.totalGames += match.gamesWonA > 0 ? match.gamesWonA : match.scoreA;
            tA.diff += (match.scoreA - match.scoreB);
            if (winA) {
              tA.wins++;
              tA.setsWon++;
            }

            final tB = teamMap[teamBKey]!;
            tB.matchesPlayed++;
            tB.totalPoints += match.scoreB;
            tB.totalGames += match.gamesWonB > 0 ? match.gamesWonB : match.scoreB;
            tB.diff += (match.scoreB - match.scoreA);
            if (winB) {
              tB.wins++;
              tB.setsWon++;
            }
          }
        }
      }
    }

    _ranking = map.values.toList()
      ..sort((a, b) {
        if (widget.config.leaderboardRankedBy == 'Win') {
          final wComp = b.wins.compareTo(a.wins);
          if (wComp != 0) return wComp;
          final dComp = b.diff.compareTo(a.diff);
          if (dComp != 0) return dComp;
          return b.totalPoints.compareTo(a.totalPoints);
        }
        final pComp = b.totalPoints.compareTo(a.totalPoints);
        if (pComp != 0) return pComp;
        final dComp = b.diff.compareTo(a.diff);
        if (dComp != 0) return dComp;
        return b.wins.compareTo(a.wins);
      });

    _teamRanking = teamMap.values.toList()
      ..sort((a, b) {
        if (widget.config.leaderboardRankedBy == 'Win') {
          final wComp = b.wins.compareTo(a.wins);
          if (wComp != 0) return wComp;
          final dComp = b.diff.compareTo(a.diff);
          if (dComp != 0) return dComp;
          return b.totalPoints.compareTo(a.totalPoints);
        }
        final pComp = b.totalPoints.compareTo(a.totalPoints);
        if (pComp != 0) return pComp;
        final dComp = b.diff.compareTo(a.diff);
        if (dComp != 0) return dComp;
        final wComp = b.wins.compareTo(a.wins);
        if (wComp != 0) return wComp;
        return a.teamName.toLowerCase().compareTo(b.teamName.toLowerCase());
      });
  }

  void _toggleKudos(String playerId, String badgeName) {
    setState(() {
      final current = _playerKudos[playerId] ?? {};
      if (current.contains(badgeName)) {
        current.remove(badgeName);
      } else {
        current.add(badgeName);
      }
      _playerKudos[playerId] = current;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Kudos "$badgeName" berhasil diperbarui! 🎉'),
        duration: const Duration(milliseconds: 1500),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final winner = _isTeamAmericano && _teamRanking.isNotEmpty
        ? _teamRanking.first.teamName
        : (_ranking.isNotEmpty ? _ranking.first.player.name : 'Pemain');

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () {
            Navigator.pushAndRemoveUntil(
              context,
              MaterialPageRoute(
                builder: (_) => MainShellPage(
                  authController: widget.authController,
                  initialIndex: 0,
                ),
              ),
              (route) => false,
            );
          },
        ),
        title: Text(
          'Rekap Pertandingan & Podium',
          style: AppTextStyles.h2.copyWith(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF0F172A),
          ),
        ),
        centerTitle: true,
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 14),
            child: TextButton.icon(
              onPressed: () => _showShareModal(context),
              icon: const Icon(Icons.share_rounded, size: 14, color: AppColors.matchaDark),
              label: const Text('Bagikan', style: TextStyle(color: AppColors.matchaDark, fontWeight: FontWeight.bold, fontSize: 11)),
              style: TextButton.styleFrom(
                backgroundColor: AppColors.matchaSoftLime,
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          // Winner Hero Header Banner
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFECFDF5), Color(0xFFD1FAE5)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF3C7),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0xFFFDE68A)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.emoji_events_rounded, color: Color(0xFFD97706), size: 16),
                      const SizedBox(width: 6),
                      Text(
                        'Juara 1 • $winner',
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: Color(0xFFB45309)),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  widget.config.activityName.isEmpty ? '${widget.config.sport} Tournament' : widget.config.activityName,
                  textAlign: TextAlign.center,
                  style: AppTextStyles.h2.copyWith(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    color: const Color(0xFF065F46),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${widget.rounds.length} Ronde Selesai • ${widget.config.venueName ?? "Barong Padel"} • ${widget.config.sport} • ${widget.config.scoringSystem}',
                  textAlign: TextAlign.center,
                  style: AppTextStyles.caption.copyWith(
                    fontSize: 11,
                    color: const Color(0xFF047857),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _buildMetaChip('${widget.config.players.length} Peserta', Icons.group_rounded),
                    const SizedBox(width: 8),
                    _buildMetaChip('${widget.rounds.length} Ronde', Icons.repeat_rounded),
                    const SizedBox(width: 8),
                    _buildMetaChip('Rekap Final Selesai', Icons.check_circle_rounded),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Riwayat Hasil Pertandingan Seluruh Ronde
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Riwayat Hasil Pertandingan',
                      style: AppTextStyles.h2.copyWith(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    Text(
                      '${widget.rounds.length} Ronde',
                      style: AppTextStyles.caption.copyWith(fontSize: 11, color: const Color(0xFF94A3B8)),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                ...List.generate(widget.rounds.length, (idx) {
                  final round = widget.rounds[idx];
                  return Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Round ${round.roundNumber}',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF475569)),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: AppColors.matchaSoftLime,
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: const Text('Selesai', style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: AppColors.matchaDark)),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        ...round.matches.map((m) {
                          final winA = m.scoreA >= m.scoreB;
                          final winB = m.scoreB > m.scoreA;
                          return Column(
                            children: [
                              _buildMatchRow(m.teamANames, m.scoreA, winA),
                              const SizedBox(height: 4),
                              _buildMatchRow(m.teamBNames, m.scoreB, winB),
                            ],
                          );
                        }),
                      ],
                    ),
                  );
                }),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Podium & Klasemen Akhir
          _buildPodiumSection(),
          const SizedBox(height: 20),

          // Kudos System
          _buildKudosSection(),
          const SizedBox(height: 20),

          // Action Buttons: Lihat di Jadwal Mabar & Kembali ke Beranda
          Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 46,
                  child: OutlinedButton.icon(
                    onPressed: () {
                      Navigator.pushAndRemoveUntil(
                        context,
                        MaterialPageRoute(
                          builder: (_) => MainShellPage(
                            authController: widget.authController,
                            initialIndex: 0,
                          ),
                        ),
                        (route) => false,
                      );
                    },
                    icon: const Icon(Icons.home_rounded, size: 16),
                    label: const Text(
                      'Beranda',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF334155),
                      side: const BorderSide(color: Color(0xFFCBD5E1)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: SizedBox(
                  height: 46,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      Navigator.pushAndRemoveUntil(
                        context,
                        MaterialPageRoute(
                          builder: (_) => MainShellPage(
                            authController: widget.authController,
                            initialIndex: 1, // Tab Mabar
                          ),
                        ),
                        (route) => false,
                      );
                    },
                    icon: const Icon(Icons.sports_tennis_rounded, size: 16),
                    label: const Text(
                      'Lihat di Jadwal Mabar',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 30),
        ],
      ),
    );
  }

  Widget _buildMetaChip(String label, IconData icon) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.8),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: const Color(0xFF047857)),
          const SizedBox(width: 4),
          Text(label, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF047857))),
        ],
      ),
    );
  }

  Widget _buildMatchRow(String teamNames, int score, bool isWinner) {
    return Row(
      children: [
        Container(
          width: 6,
          height: 6,
          decoration: BoxDecoration(
            color: isWinner ? const Color(0xFF22C55E) : const Color(0xFFCBD5E1),
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Row(
            children: [
              Flexible(
                child: Text(
                  teamNames,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: isWinner ? FontWeight.w800 : FontWeight.normal,
                    color: isWinner ? const Color(0xFF0F172A) : const Color(0xFF64748B),
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (isWinner) ...[
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCFCE7),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: const Text('WIN', style: TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: Color(0xFF15803D))),
                ),
              ],
            ],
          ),
        ),
        Text(
          '$score',
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.bold,
            color: isWinner ? const Color(0xFF0F172A) : const Color(0xFF64748B),
          ),
        ),
      ],
    );
  }

  Widget _buildTeamPodiumAvatar(List<GamePlayerItem> members) {
    return SizedBox(
      width: 44,
      height: 32,
      child: Stack(
        children: [
          Positioned(
            left: 0,
            child: CircleAvatar(
              radius: 14,
              backgroundColor: AppColors.matchaSoftLime,
              child: Text(
                members.isNotEmpty && members[0].name.isNotEmpty ? members[0].name[0].toUpperCase() : 'A',
                style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
              ),
            ),
          ),
          if (members.length > 1)
            Positioned(
              right: 0,
              child: CircleAvatar(
                radius: 14,
                backgroundColor: const Color(0xFFD1FAE5),
                child: Text(
                  members[1].name.isNotEmpty ? members[1].name[0].toUpperCase() : 'B',
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF065F46)),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildTeamPodiumColumn({
    required _TeamScoreSummary summary,
    required int rank,
    required Color podiumColor,
    required Color textColor,
    required double height,
  }) {
    return Column(
      children: [
        _buildTeamPodiumAvatar(summary.members),
        const SizedBox(height: 4),
        Text(
          summary.teamName,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
        ),
        Text(
          '${summary.wins} Win • ${summary.totalPoints} Poin',
          style: const TextStyle(fontSize: 9, color: Color(0xFF64748B)),
        ),
        const SizedBox(height: 6),
        Container(
          height: height,
          width: double.infinity,
          decoration: BoxDecoration(
            color: podiumColor,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(10)),
          ),
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  rank == 1 ? Icons.emoji_events_rounded : Icons.military_tech_rounded,
                  size: 20,
                  color: textColor,
                ),
                Text(
                  rank == 1 ? 'JUARA 1' : '$rank${rank == 2 ? "nd" : "rd"}',
                  style: TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: textColor),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildPodiumSection() {
    if (_isTeamAmericano) {
      final t1 = _teamRanking.isNotEmpty ? _teamRanking[0] : null;
      final t2 = _teamRanking.length > 1 ? _teamRanking[1] : null;
      final t3 = _teamRanking.length > 2 ? _teamRanking[2] : null;

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Podium & Klasemen Akhir',
              style: AppTextStyles.h2.copyWith(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
            ),
            Text(
              'Diurutkan: Match Menang — Total Point / Game (Klasemen Tim Tetap)',
              style: AppTextStyles.caption.copyWith(fontSize: 11, color: const Color(0xFF64748B)),
            ),
            const SizedBox(height: 16),

            // 3D Podium Bars
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // 2nd Place (Silver)
                if (t2 != null)
                  Expanded(
                    child: _buildTeamPodiumColumn(
                      summary: t2,
                      rank: 2,
                      podiumColor: const Color(0xFFE2E8F0),
                      textColor: const Color(0xFF475569),
                      height: 80,
                    ),
                  ),
                const SizedBox(width: 8),

                // 1st Place (Gold)
                if (t1 != null)
                  Expanded(
                    child: _buildTeamPodiumColumn(
                      summary: t1,
                      rank: 1,
                      podiumColor: const Color(0xFFFBBF24),
                      textColor: const Color(0xFF78350F),
                      height: 110,
                    ),
                  ),
                const SizedBox(width: 8),

                // 3rd Place (Bronze)
                if (t3 != null)
                  Expanded(
                    child: _buildTeamPodiumColumn(
                      summary: t3,
                      rank: 3,
                      podiumColor: const Color(0xFFFED7AA),
                      textColor: const Color(0xFF9A3412),
                      height: 65,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 20),

            // Ranking Table
            Text(
              'RANKING TIM & STATISTIK',
              style: AppTextStyles.caption.copyWith(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.8,
                color: const Color(0xFF94A3B8),
              ),
            ),
            const SizedBox(height: 8),
            ...List.generate(_teamRanking.length, (idx) {
              final t = _teamRanking[idx];
              return Container(
                margin: const EdgeInsets.only(bottom: 6),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Text(
                      '#${idx + 1}',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF64748B)),
                    ),
                    const SizedBox(width: 10),
                    _buildTeamPodiumAvatar(t.members),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            t.teamName,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF0F172A)),
                          ),
                          Text(
                            'Tim • ${t.matchesPlayed} match',
                            style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                          ),
                        ],
                      ),
                    ),
                    _buildStatCell('Win', '${t.wins}'),
                    const SizedBox(width: 12),
                    _buildStatCell('Sets', '${t.setsWon}'),
                    const SizedBox(width: 12),
                    _buildStatCell('Poin', '${t.totalPoints}'),
                    const SizedBox(width: 12),
                    _buildStatCell('Diff', '${t.diff >= 0 ? "+${t.diff}" : t.diff}'),
                  ],
                ),
              );
            }),
          ],
        ),
      );
    }

    final p1 = _ranking.isNotEmpty ? _ranking[0] : null;
    final p2 = _ranking.length > 1 ? _ranking[1] : null;
    final p3 = _ranking.length > 2 ? _ranking[2] : null;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Podium & Klasemen Akhir',
            style: AppTextStyles.h2.copyWith(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: const Color(0xFF0F172A),
            ),
          ),
          Text(
            'Diurutkan: Match Menang — Total Point / Game',
            style: AppTextStyles.caption.copyWith(fontSize: 11, color: const Color(0xFF64748B)),
          ),
          const SizedBox(height: 16),

          // 3D Podium Bars
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // 2nd Place (Silver)
              if (p2 != null)
                Expanded(
                  child: _buildPodiumColumn(
                    summary: p2,
                    rank: 2,
                    podiumColor: const Color(0xFFE2E8F0),
                    textColor: const Color(0xFF475569),
                    height: 80,
                  ),
                ),
              const SizedBox(width: 8),

              // 1st Place (Gold)
              if (p1 != null)
                Expanded(
                  child: _buildPodiumColumn(
                    summary: p1,
                    rank: 1,
                    podiumColor: const Color(0xFFFBBF24),
                    textColor: const Color(0xFF78350F),
                    height: 110,
                  ),
                ),
              const SizedBox(width: 8),

              // 3rd Place (Bronze)
              if (p3 != null)
                Expanded(
                  child: _buildPodiumColumn(
                    summary: p3,
                    rank: 3,
                    podiumColor: const Color(0xFFFED7AA),
                    textColor: const Color(0xFF9A3412),
                    height: 65,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 20),

          // Ranking Table
          Text(
            'RANKING & STATISTIK',
            style: AppTextStyles.caption.copyWith(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.8,
              color: const Color(0xFF94A3B8),
            ),
          ),
          const SizedBox(height: 8),
          ...List.generate(_ranking.length, (idx) {
            final s = _ranking[idx];
            return Container(
              margin: const EdgeInsets.only(bottom: 6),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Text(
                    '#${idx + 1}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF64748B)),
                  ),
                  const SizedBox(width: 10),
                  CircleAvatar(
                    radius: 14,
                    backgroundColor: AppColors.matchaSoftLime,
                    child: Text(
                      s.player.name.isNotEmpty ? s.player.name[0].toUpperCase() : 'P',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          s.player.name,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF0F172A)),
                        ),
                        Text(
                          '${s.player.level} • ${s.matchesPlayed} match',
                          style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                        ),
                      ],
                    ),
                  ),
                  _buildStatCell('Win', '${s.wins}'),
                  const SizedBox(width: 12),
                  _buildStatCell('Sets', '${s.setsWon}'),
                  const SizedBox(width: 12),
                  _buildStatCell('Poin', '${s.totalPoints}'),
                  const SizedBox(width: 12),
                  _buildStatCell('Diff', '${s.diff >= 0 ? "+${s.diff}" : s.diff}'),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildPodiumColumn({
    required _PlayerScoreSummary summary,
    required int rank,
    required Color podiumColor,
    required Color textColor,
    required double height,
  }) {
    return Column(
      children: [
        CircleAvatar(
          radius: 16,
          backgroundColor: AppColors.matchaSoftLime,
          child: Text(
            summary.player.name.isNotEmpty ? summary.player.name[0].toUpperCase() : 'P',
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          summary.player.name,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
        ),
        Text(
          '${summary.wins} Win • ${summary.totalPoints} Poin',
          style: const TextStyle(fontSize: 9, color: Color(0xFF64748B)),
        ),
        const SizedBox(height: 6),
        Container(
          height: height,
          width: double.infinity,
          decoration: BoxDecoration(
            color: podiumColor,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(10)),
          ),
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  rank == 1 ? Icons.emoji_events_rounded : Icons.military_tech_rounded,
                  size: 20,
                  color: textColor,
                ),
                Text(
                  rank == 1 ? 'JUARA 1' : '$rank${rank == 2 ? "nd" : "rd"}',
                  style: TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: textColor),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildStatCell(String label, String value) {
    return Column(
      children: [
        Text(value, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
        Text(label, style: const TextStyle(fontSize: 9, color: Color(0xFF94A3B8))),
      ],
    );
  }

  Widget _buildKudosSection() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Beri Kudos untuk Pemain',
                style: AppTextStyles.h2.copyWith(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: const Color(0xFF0F172A),
                ),
              ),
              Text(
                'Kudos System',
                style: AppTextStyles.caption.copyWith(fontSize: 11, color: const Color(0xFF94A3B8)),
              ),
            ],
          ),
          Text(
            'Apresiasi skill & sportivitas seluruh pemain di lapangan',
            style: AppTextStyles.caption.copyWith(fontSize: 11, color: const Color(0xFF64748B)),
          ),
          const SizedBox(height: 14),
          ...List.generate(_ranking.length, (idx) {
            final s = _ranking[idx];
            final givenKudos = _playerKudos[s.player.id] ?? {};

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
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 14,
                        backgroundColor: AppColors.matchaSoftLime,
                        child: Text(
                          s.player.name.isNotEmpty ? s.player.name[0].toUpperCase() : 'P',
                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        s.player.name,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF0F172A)),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        '• Juara ${idx + 1}',
                        style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: _kudosOptions.map((opt) {
                      final name = opt['name'] as String;
                      final icon = opt['icon'] as IconData;
                      final isSelected = givenKudos.contains(name);

                      return GestureDetector(
                        onTap: () => _toggleKudos(s.player.id, name),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: isSelected ? AppColors.matchaSoftLime : Colors.white,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(
                              color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                            ),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(icon, size: 12, color: isSelected ? AppColors.matchaDark : const Color(0xFF64748B)),
                              const SizedBox(width: 4),
                              Text(
                                name,
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                  color: isSelected ? AppColors.matchaDark : const Color(0xFF475569),
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  void _showShareModal(BuildContext context) {
    final sessionId = widget.config.sessionId;
    final url = sessionId != null && sessionId > 0
        ? 'https://matcha.siproduktif.com/scoring/recap/$sessionId'
        : 'https://matcha.siproduktif.com';
    final winnerName = _isTeamAmericano && _teamRanking.isNotEmpty
        ? _teamRanking.first.teamName
        : (_ranking.isNotEmpty ? _ranking.first.player.name : 'Pemain');
    final activityName = widget.config.activityName.isEmpty ? '${widget.config.sport} Tournament' : widget.config.activityName;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Handle bar
              Center(
                child: Container(
                  width: 44,
                  height: 4.5,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Header with close button
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEBF8D8),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.share_rounded, size: 12, color: Color(0xFF063B00)),
                              SizedBox(width: 4),
                              Text(
                                'Share Game',
                                style: TextStyle(
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF063B00),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Bagikan Hasil Pertandingan',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFF0F172A),
                            letterSpacing: -0.3,
                          ),
                        ),
                        const SizedBox(height: 3),
                        const Text(
                          'Rayakan serunya momen mabar dan kemenangan bersama teman atau komunitasmu!',
                          style: TextStyle(
                            fontSize: 11.5,
                            color: Color(0xFF64748B),
                            height: 1.35,
                          ),
                        ),
                      ],
                    ),
                  ),
                  GestureDetector(
                    onTap: () => Navigator.pop(ctx),
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: const BoxDecoration(
                        shape: BoxShape.circle,
                        color: Color(0xFFF1F5F9),
                      ),
                      child: const Icon(Icons.close_rounded, size: 18, color: Color(0xFF64748B)),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),

              // Web Preview Card
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 36,
                          height: 36,
                          decoration: BoxDecoration(
                            color: const Color(0xFFD1FAE5),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFA7F3D0)),
                          ),
                          child: const Icon(Icons.language_rounded, size: 19, color: Color(0xFF063B00)),
                        ),
                        const SizedBox(width: 10),
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Share as Web Preview',
                                style: TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 12.5,
                                  color: Color(0xFF0F172A),
                                ),
                              ),
                              SizedBox(height: 2),
                              Text(
                                'Tampilkan hasil pertandingan lengkap dalam format web. Cocok untuk grup WhatsApp / Telegram.',
                                style: TextStyle(fontSize: 11, color: Color(0xFF64748B), height: 1.3),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        // Salin Link Button
                        Expanded(
                          child: GestureDetector(
                            onTap: () {
                              Clipboard.setData(ClipboardData(text: url));
                              Navigator.pop(ctx);
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text('Tautan rekap web berhasil disalin! 🔗'),
                                  backgroundColor: Color(0xFF063B00),
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            },
                            child: Container(
                              padding: const EdgeInsets.symmetric(vertical: 9),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: const Color(0xFFE2E8F0)),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.03),
                                    blurRadius: 4,
                                    offset: const Offset(0, 1),
                                  ),
                                ],
                              ),
                              child: const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.copy_rounded, size: 14, color: Color(0xFF64748B)),
                                  SizedBox(width: 6),
                                  Text(
                                    'Salin Link',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 11.5,
                                      color: Color(0xFF334155),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),

                        // Bagikan Link Direct Button
                        Expanded(
                          child: GestureDetector(
                            onTap: () {
                              final shareText = '🎾 Hasil Mabar: $activityName\n🏆 Juara 1: $winnerName\nLihat rekap selengkapnya di: $url';
                              Clipboard.setData(ClipboardData(text: shareText));
                              Navigator.pop(ctx);
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text('Teks & tautan siap dibagikan ke WhatsApp/Telegram! 🚀'),
                                  backgroundColor: Color(0xFF063B00),
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            },
                            child: Container(
                              padding: const EdgeInsets.symmetric(vertical: 9),
                              decoration: BoxDecoration(
                                color: const Color(0xFF063B00),
                                borderRadius: BorderRadius.circular(12),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF063B00).withValues(alpha: 0.25),
                                    blurRadius: 6,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.send_rounded, size: 14, color: Color(0xFFA8E63A)),
                                  SizedBox(width: 6),
                                  Text(
                                    'Bagikan Link',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 11.5,
                                      color: Colors.white,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _PlayerScoreSummary {
  final GamePlayerItem player;
  int matchesPlayed = 0;
  int wins = 0;
  int setsWon = 0;
  int totalPoints = 0;
  int totalGames = 0;
  int diff = 0;

  _PlayerScoreSummary({required this.player});
}

class _TeamScoreSummary {
  final String teamId;
  final String teamName;
  final List<GamePlayerItem> members;
  int matchesPlayed = 0;
  int wins = 0;
  int setsWon = 0;
  int totalPoints = 0;
  int totalGames = 0;
  int diff = 0;

  _TeamScoreSummary({
    required this.teamId,
    required this.teamName,
    required this.members,
  });
}
