import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/domain/models/user_model.dart';
import '../../match/data/match_service.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

class HomePage extends StatefulWidget {
  final UserModel? user;
  final VoidCallback? onCreateSessionTap;
  final Function? onLiveSessionTap;
  final VoidCallback? onManagePlayersTap;
  final VoidCallback? onManageCourtsTap;
  final VoidCallback? onCommunityTap;

  const HomePage({
    super.key,
    this.user,
    this.onCreateSessionTap,
    this.onLiveSessionTap,
    this.onManagePlayersTap,
    this.onManageCourtsTap,
    this.onCommunityTap,
  });

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final MatchService _matchService = MatchService();
  RealtimeChannel? _realtimeChannel;

  bool _isLoading = true;
  Map<String, dynamic>? _activeSession;
  List<Map<String, dynamic>> _matches = [];

  @override
  void initState() {
    super.initState();
    _loadLiveSessionAndSubscribe();
  }

  Future<void> _loadLiveSessionAndSubscribe() async {
    try {
      final session = await _matchService.getSession(null);
      if (session != null) {
        final matches = await _matchService.getMatchesForSession(
          session['session_id'],
        );
        if (mounted) {
          setState(() {
            _activeSession = session;
            _matches = matches;
            _isLoading = false;
          });
          _subscribeRealtime(session['session_id']);
        }
      } else {
        if (mounted) {
          setState(() {
            _activeSession = null;
            _matches = [];
            _isLoading = false;
          });
        }
      }
    } catch (_) {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  void _subscribeRealtime(dynamic sessionId) {
    _matchService.unsubscribe(_realtimeChannel);
    _realtimeChannel = _matchService.subscribeLiveSession(
      sessionId: sessionId,
      onDataChanged: () {
        if (mounted) _refreshScoresSilently(sessionId);
      },
    );
  }

  Future<void> _refreshScoresSilently(dynamic sessionId) async {
    try {
      final matches = await _matchService.getMatchesForSession(sessionId);
      if (mounted) {
        setState(() {
          _matches = matches;
        });
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _matchService.unsubscribe(_realtimeChannel);
    super.dispose();
  }

  void _handleLiveSessionTap() {
    final sessionIdStr = _activeSession?['session_id']?.toString() ?? '';
    if (widget.onLiveSessionTap != null) {
      try {
        (widget.onLiveSessionTap as dynamic)(sessionIdStr);
      } catch (_) {
        (widget.onLiveSessionTap as dynamic)();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isHost = widget.user?.isHost ?? true;
    final userName = widget.user?.nama.isNotEmpty == true
        ? widget.user!.nama
        : 'Host';

    return Scaffold(
      backgroundColor: context.bg,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Header (Greeting & Notification)
              _buildHeader(context, userName),
              const SizedBox(height: 24),

              // 2. Live Session Card (Prominent Section)
              _buildLiveSessionCard(context),
              const SizedBox(height: 24),

              // 3. Upcoming Session Card
              _buildUpcomingSessionCard(context),
              const SizedBox(height: 24),

              // 4. Quick Action Grid (Khusus Host / Sesuai Permission)
              if (isHost) ...[
                _buildQuickActionSection(context),
                const SizedBox(height: 24),
              ],

              // 5. Ringkasan Statistik
              _buildSummaryStats(context),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  // --- WIDGET SECTIONS ---

  Widget _buildHeader(BuildContext context, String userName) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  'Halo, $userName!',
                  style: AppTextStyles.pageTitle.copyWith(
                    fontSize: 22,
                    color: context.txtPrimary,
                  ),
                ),
                const SizedBox(width: 6),
                const Text('👋', style: TextStyle(fontSize: 20)),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              'Sabtu, 6 September 2026',
              style: AppTextStyles.caption.copyWith(
                color: context.txtSecondary,
              ),
            ),
          ],
        ),
        Container(
          decoration: BoxDecoration(
            color: context.surf,
            shape: BoxShape.circle,
            border: Border.all(color: context.surfBorder),
          ),
          child: IconButton(
            icon: Icon(
              Icons.notifications_outlined,
              color: context.txtPrimary,
              size: 22,
            ),
            onPressed: () {},
          ),
        ),
      ],
    );
  }

  Widget _buildLiveSessionCard(BuildContext context) {
    if (_isLoading) {
      return Container(
        height: 160,
        alignment: Alignment.center,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: context.surfBorder),
        ),
        child: CircularProgressIndicator(color: context.brandColor),
      );
    }

    if (_activeSession == null) {
      return Container(
        padding: const EdgeInsets.all(18),
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
                  'LIVE SESSION',
                  style: AppTextStyles.badge.copyWith(
                    color: context.txtSecondary,
                    letterSpacing: 1.5,
                  ),
                ),

                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: context.surfSec,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    'INACTIVE',
                    style: AppTextStyles.badge.copyWith(
                      color: context.txtSecondary,
                      fontSize: 10,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              'Tidak Ada Sesi Live',
              style: AppTextStyles.sectionTitle.copyWith(
                fontSize: 17,
                color: context.txtPrimary,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'Saat ini belum ada pertandingan live yang sedang berlangsung.',
              style: AppTextStyles.bodySecondary.copyWith(
                fontSize: 13,
                color: context.txtSecondary,
              ),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: widget.onCreateSessionTap,
              child: const Text('BUAT SESSION BARU'),
            ),
          ],
        ),
      );
    }

    final sessionTitle = _activeSession!['nama_session'] ?? 'Saturday Morning';
    final sportName = _activeSession!['sport_id'] != null ? 'Tennis' : 'Sports';

    final court1 = _matches.isNotEmpty ? _matches[0] : null;
    final court2 = _matches.length > 1 ? _matches[1] : null;

    final c1Name = court1 != null
        ? (court1['nomorMatch'] != null
              ? 'Court ${court1['nomorMatch']}'
              : 'Court 1')
        : 'Court 1';
    final c1SideA = court1 != null ? court1['sideA'] : 'Belum Mulai';
    final c1SideB = court1 != null ? court1['sideB'] : 'Belum Mulai';
    final c1Score = court1 != null
        ? '${court1['scoreA']} — ${court1['scoreB']}'
        : '-';

    final c2Name = court2 != null
        ? (court2['nomorMatch'] != null
              ? 'Court ${court2['nomorMatch']}'
              : 'Court 2')
        : 'Court 2';
    final c2SideA = court2 != null ? court2['sideA'] : 'Belum Mulai';
    final c2SideB = court2 != null ? court2['sideB'] : 'Belum Mulai';
    final c2Score = court2 != null
        ? '${court2['scoreA']} — ${court2['scoreB']}'
        : '-';

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.brandColor.withValues(alpha: 0.35)),
        boxShadow: [
          BoxShadow(
            color: context.brandColor.withValues(alpha: 0.05),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Badges
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'LIVE SESSION',
                style: AppTextStyles.badge.copyWith(
                  color: context.brandColor,
                  letterSpacing: 1.5,
                ),
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
                    const SizedBox(width: 5),
                    Text(
                      'LIVE',
                      style: AppTextStyles.badge.copyWith(
                        color: context.brandColor,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Session Title & Meta
          Text(
            sessionTitle,
            style: AppTextStyles.sectionTitle.copyWith(
              fontSize: 17,
              color: context.txtPrimary,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              Icon(
                Icons.sports_tennis_rounded,
                size: 15,
                color: context.brandColor,
              ),
              const SizedBox(width: 6),
              Text(
                '$sportName · ${_matches.length} Courts Aktif',
                style: AppTextStyles.bodySecondary.copyWith(
                  fontSize: 13,
                  color: context.txtSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Court 1 & Court 2 Live Score Cards
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: context.surfSec,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: context.surfBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        c1Name,
                        style: AppTextStyles.caption.copyWith(
                          color: context.brandColor,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        c1SideA,
                        style: AppTextStyles.caption.copyWith(
                          color: context.txtPrimary,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            c1Score,
                            style: AppTextStyles.caption.copyWith(
                              fontWeight: FontWeight.bold,
                              color: context.brandColor,
                            ),
                          ),
                          Flexible(
                            child: Text(
                              c1SideB,
                              style: AppTextStyles.caption.copyWith(
                                fontSize: 10,
                                color: context.txtSecondary,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: context.surfSec,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: context.surfBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        c2Name,
                        style: AppTextStyles.caption.copyWith(
                          color: context.brandColor,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        c2SideA,
                        style: AppTextStyles.caption.copyWith(
                          color: context.txtPrimary,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            c2Score,
                            style: AppTextStyles.caption.copyWith(
                              fontWeight: FontWeight.bold,
                              color: context.brandColor,
                            ),
                          ),
                          Flexible(
                            child: Text(
                              c2SideB,
                              style: AppTextStyles.caption.copyWith(
                                fontSize: 10,
                                color: context.txtSecondary,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Action Button
          ElevatedButton(
            onPressed: _handleLiveSessionTap,
            child: const Text('MASUK KE SESSION'),
          ),
        ],
      ),
    );
  }

  Widget _buildUpcomingSessionCard(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'UPCOMING SESSION',
              style: AppTextStyles.badge.copyWith(
                color: context.txtSecondary,
                letterSpacing: 1.5,
              ),
            ),
            Text(
              'Lihat Semua >',
              style: AppTextStyles.caption.copyWith(
                color: context.brandColor,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: context.surf,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: context.surfBorder),
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
                  size: 24,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Friday Night Play',
                      style: AppTextStyles.cardTitle.copyWith(
                        fontSize: 15,
                        color: context.txtPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Padel · 6 Players · 1 Court',
                      style: AppTextStyles.caption.copyWith(
                        color: context.txtSecondary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '📅 7 Sep 2026 · 18:00',
                      style: AppTextStyles.caption.copyWith(
                        color: context.brandColor,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: context.txtSecondary),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildQuickActionSection(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'QUICK ACTION',
          style: AppTextStyles.badge.copyWith(
            color: context.txtSecondary,
            letterSpacing: 1.5,
          ),
        ),
        const SizedBox(height: 12),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            _buildActionItem(
              context: context,
              icon: Icons.add_circle_outline_rounded,
              label: 'Buat Session',
              onTap: widget.onCreateSessionTap,
            ),
            _buildActionItem(
              context: context,
              icon: Icons.people_alt_outlined,
              label: 'Kelola Player',
              onTap: widget.onManagePlayersTap,
            ),
            _buildActionItem(
              context: context,
              icon: Icons.stadium_outlined,
              label: 'Kelola Court',
              onTap: widget.onManageCourtsTap,
            ),
            _buildActionItem(
              context: context,
              icon: Icons.diversity_3_outlined,
              label: 'Community',
              onTap: widget.onCommunityTap,
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildActionItem({
    required BuildContext context,
    required IconData icon,
    required String label,
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: 76,
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: context.surfBorder),
        ),
        child: Column(
          children: [
            Icon(icon, color: context.brandColor, size: 24),
            const SizedBox(height: 8),
            Text(
              label,
              textAlign: TextAlign.center,
              style: AppTextStyles.caption.copyWith(
                fontSize: 10,
                fontWeight: FontWeight.w500,
                color: context.txtPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSummaryStats(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'RINGKASAN',
          style: AppTextStyles.badge.copyWith(
            color: context.txtSecondary,
            letterSpacing: 1.5,
          ),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
          decoration: BoxDecoration(
            color: context.surf,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: context.surfBorder),
          ),
          child: const Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _StatItem(count: '8', label: 'Players'),
              _StatDivider(),
              _StatItem(count: '2', label: 'Courts'),
              _StatDivider(),
              _StatItem(count: '3', label: 'Session\nAktif'),
              _StatDivider(),
              _StatItem(count: '12', label: 'Total\nSesi'),
            ],
          ),
        ),
      ],
    );
  }
}

class _StatItem extends StatelessWidget {
  final String count;
  final String label;

  const _StatItem({required this.count, required this.label});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          count,
          style: AppTextStyles.pageTitle.copyWith(
            fontSize: 20,
            color: context.brandColor,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          textAlign: TextAlign.center,
          style: AppTextStyles.caption.copyWith(
            fontSize: 10,
            color: context.txtSecondary,
          ),
        ),
      ],
    );
  }
}

class _StatDivider extends StatelessWidget {
  const _StatDivider();

  @override
  Widget build(BuildContext context) {
    return Container(width: 1, height: 30, color: context.surfBorder);
  }
}
