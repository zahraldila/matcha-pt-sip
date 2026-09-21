import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import '../../match/presentation/match_scoring_page.dart';
import '../../session/presentation/create_session_page.dart';
import '../../session/presentation/session_detail_page.dart';

class HomePage extends StatefulWidget {
  final VoidCallback? onExploreSessions;
  final VoidCallback? onExploreCommunity;

  const HomePage({
    super.key,
    this.onExploreSessions,
    this.onExploreCommunity,
  });

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final MockDataService _dataService = MockDataService();
  String _selectedSport = 'all'; // 'all', 'padel', 'tennis', 'badminton'

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
    final user = _dataService.currentUser;
    final isHost = _dataService.isHostMode;
    final liveSession = _dataService.activeLiveSession;

    final filteredSessions = _dataService.upcomingSessions.where((s) {
      if (_selectedSport == 'all') return true;
      return s.sport.toLowerCase() == _selectedSport.toLowerCase();
    }).toList();

    return Scaffold(
      backgroundColor: context.bg,
      body: CustomScrollView(
        physics: const BouncingScrollPhysics(),
        slivers: [
          // --- App Bar Header ---
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
              child: Row(
                children: [
                  // User Avatar & Greeting
                  CircleAvatar(
                    radius: 22,
                    backgroundImage: NetworkImage(user.avatarUrl),
                    backgroundColor: context.surfBorder,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Halo, ${user.name.split(' ').first} 👋',
                          style: AppTextStyles.h2.copyWith(
                            color: context.txtPrimary,
                            fontSize: 17,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          isHost
                              ? 'Mode Host Aktif • Kelola mabar & skor'
                              : 'Tier ${user.tier} • Winrate ${user.winRate}%',
                          style: AppTextStyles.caption.copyWith(
                            color: context.txtSecondary,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  // Host / Member Mode Switch Indicator
                  GestureDetector(
                    onTap: () {
                      _dataService.toggleHostMode();
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text(
                            _dataService.isHostMode
                                ? 'Beralih ke Mode Host 🎾'
                                : 'Beralih ke Mode Pemain / Member 👤',
                          ),
                          duration: const Duration(seconds: 1),
                          behavior: SnackBarBehavior.floating,
                        ),
                      );
                    },
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: isHost
                            ? AppColors.matchaSoftLime
                            : const Color(0xFFEFF6FF),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: isHost
                              ? const Color(0xFF063B00).withValues(alpha: 0.3)
                              : const Color(0xFF93C5FD),
                          width: 1,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            isHost ? Icons.sports_tennis : Icons.person_outline,
                            size: 13,
                            color: isHost ? AppColors.matchaDark : const Color(0xFF1D4ED8),
                          ),
                          const SizedBox(width: 4),
                          Text(
                            isHost ? 'HOST' : 'MEMBER',
                            style: AppTextStyles.badge.copyWith(
                              fontSize: 10,
                              color: isHost ? AppColors.matchaDark : const Color(0xFF1D4ED8),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // --- Hero Banner / Live Active Match Card ---
          if (liveSession != null)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                child: _buildLiveMatchCard(context, liveSession),
              ),
            ),

          // --- Quick Stats Summary Row ---
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                children: [
                  _buildStatPill(
                    context,
                    label: 'Sesi Aktif',
                    value: '1 Live',
                    icon: Icons.flash_on_rounded,
                    color: Colors.redAccent,
                  ),
                  const SizedBox(width: 10),
                  _buildStatPill(
                    context,
                    label: 'Mabar Ikut',
                    value: '${user.totalMatches}',
                    icon: Icons.sports_tennis_rounded,
                    color: AppColors.matchaDark,
                  ),
                  const SizedBox(width: 10),
                  _buildStatPill(
                    context,
                    label: 'Total Kudos',
                    value: '🔥 ${user.kudosCount}',
                    icon: Icons.local_fire_department_rounded,
                    color: Colors.deepOrange,
                  ),
                ],
              ),
            ),
          ),

          const SliverToBoxAdapter(child: SizedBox(height: 20)),

          // --- Quick Action Grid (Host vs Member) ---
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Aksi Cepat',
                    style: AppTextStyles.h2.copyWith(
                      color: context.txtPrimary,
                      fontSize: 15,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: _buildActionButton(
                          context,
                          title: 'Buat Sesi Mabar',
                          subtitle: 'Jadwal & Kuota',
                          icon: Icons.add_circle_outline_rounded,
                          accentColor: AppColors.matchaDark,
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const CreateSessionPage(),
                              ),
                            );
                          },
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _buildActionButton(
                          context,
                          title: 'Drawing & Tim',
                          subtitle: 'Bagan & Acak',
                          icon: Icons.shuffle_rounded,
                          accentColor: const Color(0xFF047857),
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const DrawingResultPage(),
                              ),
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),

          const SliverToBoxAdapter(child: SizedBox(height: 24)),

          // --- Upcoming Sessions Header & Sport Filter Pills ---
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Jadwal Mabar Terbuka',
                            style: AppTextStyles.h2.copyWith(
                              color: context.txtPrimary,
                              fontSize: 15,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Pilih sesi dan amankan kuota slotmu',
                            style: AppTextStyles.caption.copyWith(
                              color: context.txtSecondary,
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                      TextButton(
                        onPressed: widget.onExploreSessions,
                        child: Text(
                          'Lihat Semua',
                          style: AppTextStyles.button.copyWith(
                            color: AppColors.matchaDark,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  // Filter Pills (Matching matcha-pt-web)
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    physics: const BouncingScrollPhysics(),
                    child: Row(
                      children: [
                        _buildSportFilterChip('all', 'Semua Cabang', Icons.grid_view_rounded),
                        const SizedBox(width: 8),
                        _buildSportFilterChip('padel', '🏓 Padel', null),
                        const SizedBox(width: 8),
                        _buildSportFilterChip('tennis', '🎾 Tennis', null),
                        const SizedBox(width: 8),
                        _buildSportFilterChip('badminton', '🏸 Badminton', null),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),

          const SliverToBoxAdapter(child: SizedBox(height: 14)),

          // --- List Sesi Mabar Terbuka ---
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
            sliver: SliverList(
              delegate: SliverChildBuilderDelegate(
                (context, index) {
                  final session = filteredSessions[index];
                  return _buildSessionCard(context, session);
                },
                childCount: filteredSessions.length,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSportFilterChip(String key, String label, IconData? icon) {
    final isSelected = _selectedSport == key;
    return GestureDetector(
      onTap: () => setState(() => _selectedSport = key),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected
              ? AppColors.matchaDark
              : context.surf,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : context.surfBorder,
            width: 1,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: AppColors.matchaDark.withValues(alpha: 0.2),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(
                icon,
                size: 13,
                color: isSelected ? Colors.white : context.txtSecondary,
              ),
              const SizedBox(width: 6),
            ],
            Text(
              label,
              style: AppTextStyles.bodySmall.copyWith(
                fontSize: 12,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                color: isSelected ? Colors.white : context.txtSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatPill(
    BuildContext context, {
    required String label,
    required String value,
    required IconData icon,
    required Color color,
  }) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: context.surfBorder, width: 1),
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
                Icon(icon, size: 14, color: color),
                const SizedBox(width: 4),
                Text(
                  label,
                  style: AppTextStyles.caption.copyWith(
                    color: context.txtSecondary,
                    fontSize: 10,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              value,
              style: AppTextStyles.h3.copyWith(
                color: context.txtPrimary,
                fontSize: 14,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionButton(
    BuildContext context, {
    required String title,
    required String subtitle,
    required IconData icon,
    required Color accentColor,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: context.surfBorder, width: 1),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: accentColor.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: accentColor, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: AppTextStyles.bodyMedium.copyWith(
                      fontWeight: FontWeight.bold,
                      color: context.txtPrimary,
                      fontSize: 13,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: AppTextStyles.caption.copyWith(
                      color: context.txtSecondary,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLiveMatchCard(BuildContext context, MatchaSession session) {
    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF063B00), Color(0xFF0A4F01)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.25),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const MatchScoringPage()),
            );
          },
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.redAccent.withValues(alpha: 0.25),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.redAccent.withValues(alpha: 0.6), width: 1),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 6,
                            height: 6,
                            decoration: const BoxDecoration(
                              color: Colors.redAccent,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 5),
                          const Text(
                            'MATCH LIVE',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: AppColors.matchaLime.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Text(
                        'Court 1 • Set 1',
                        style: TextStyle(
                          color: AppColors.matchaLime,
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  session.title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${session.venueName} • ${session.time}',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.8),
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 12),
                // Score Preview Pill
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.25),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Marcel / Budi (Tim A)',
                            style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                          ),
                          Text(
                            'Dimas / Kevin (Tim B)',
                            style: TextStyle(color: Colors.white70, fontSize: 11),
                          ),
                        ],
                      ),
                      Row(
                        children: [
                          Text(
                            '${_dataService.teamAPoints} - ${_dataService.teamBPoints}',
                            style: const TextStyle(
                              color: AppColors.matchaLime,
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                              letterSpacing: 1.5,
                            ),
                          ),
                          const SizedBox(width: 8),
                          const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: AppColors.matchaLime),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildSessionCard(BuildContext context, MatchaSession session) {
    final user = _dataService.currentUser;
    final isJoined = session.participants.any((p) => p.id == user.id);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: context.surfBorder, width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => SessionDetailPage(session: session),
              ),
            );
          },
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Sport & Status Badges
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: AppColors.matchaSoftLime,
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                      ),
                      child: Text(
                        session.sport.toUpperCase(),
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: AppColors.matchaDark,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      session.matchFormat,
                      style: AppTextStyles.caption.copyWith(
                        color: context.txtSecondary,
                        fontSize: 11,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      'Rp ${(session.pricePerPerson / 1000).toStringAsFixed(0)}k/org',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: AppColors.matchaDark,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                // Title
                Text(
                  session.title,
                  style: AppTextStyles.h3.copyWith(
                    color: context.txtPrimary,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 6),
                // Location & Time
                Row(
                  children: [
                    Icon(Icons.location_on_outlined, size: 14, color: context.txtSecondary),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        '${session.venueName}, ${session.location}',
                        style: AppTextStyles.caption.copyWith(
                          color: context.txtSecondary,
                          fontSize: 11,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.access_time_rounded, size: 14, color: context.txtSecondary),
                    const SizedBox(width: 4),
                    Text(
                      '${session.date} • ${session.time}',
                      style: AppTextStyles.caption.copyWith(
                        color: context.txtSecondary,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                // Participants & Join Button
                Row(
                  children: [
                    // Avatar stack
                    SizedBox(
                      height: 26,
                      child: Row(
                        children: [
                          for (int i = 0; i < session.participants.length && i < 3; i++)
                            Align(
                              widthFactor: 0.7,
                              child: CircleAvatar(
                                radius: 13,
                                backgroundColor: context.surfBorder,
                                backgroundImage: NetworkImage(session.participants[i].avatarUrl),
                              ),
                            ),
                          const SizedBox(width: 8),
                          Text(
                            '${session.participants.length}/${session.maxParticipants} Kuota',
                            style: AppTextStyles.caption.copyWith(
                              color: session.isFull ? Colors.redAccent : AppColors.matchaDark,
                              fontWeight: FontWeight.bold,
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Spacer(),
                    // Join 1-Tap Button
                    GestureDetector(
                      onTap: () {
                        _dataService.joinSession(session.id);
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              isJoined
                                  ? 'Batal bergabung dari ${session.title}'
                                  : 'Berhasil bergabung ke ${session.title}! 🎉',
                            ),
                            duration: const Duration(seconds: 1),
                            behavior: SnackBarBehavior.floating,
                          ),
                        );
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                        decoration: BoxDecoration(
                          color: isJoined
                              ? context.surfSec
                              : AppColors.matchaDark,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                            color: isJoined ? context.surfBorder : Colors.transparent,
                          ),
                        ),
                        child: Text(
                          isJoined ? 'Batal Join' : 'Join Sesi',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: isJoined
                                ? context.txtSecondary
                                : Colors.white,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
