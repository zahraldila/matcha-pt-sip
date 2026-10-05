import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/offline_state_widget.dart';
import '../data/session_service.dart';
import '../domain/session_model.dart';
import 'create_session_page.dart';
import 'session_detail_page.dart';
import 'widgets/join_session_modal.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';

class SessionListPage extends StatefulWidget {
  final AuthController? authController;
  final Function(int sessionId)? onSessionTap;

  const SessionListPage({
    super.key,
    this.authController,
    this.onSessionTap,
  });

  @override
  State<SessionListPage> createState() => _SessionListPageState();
}

class _SessionListPageState extends State<SessionListPage> {
  final SessionService _sessionService = SessionService();

  final TextEditingController _searchController = TextEditingController();
  bool _isLoading = true;
  String? _errorMessage;
  List<SessionModel> _allSessions = [];
  List<SessionModel> _filteredSessions = [];

  String _searchQuery = '';
  String _selectedSport = 'Semua Cabang'; // 'Semua Cabang', 'Padel', 'Tennis'
  String _selectedTab = 'all'; // 'all', 'venue', 'joined', 'hosted'

  @override
  void initState() {
    super.initState();
    _loadSessions();
    widget.authController?.addListener(_onAuthChanged);
  }

  @override
  void dispose() {
    _searchController.dispose();
    widget.authController?.removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
    if (mounted) setState(() {});
  }

  Future<void> _loadSessions() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final sessions = await _sessionService.getSessions();
      if (!mounted) return;

      setState(() {
        _allSessions = sessions;
        _applyFilters();
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

  bool _isJoinedByMe(SessionModel s) {
    final user = widget.authController?.currentUser;
    if (user == null) return false;
    final userId = user.userId;
    final playerId = user.playerId;
    return s.registeredPlayers.any((p) {
      if (userId > 0 && p.userId == userId) return true;
      if (playerId != null && playerId > 0 && p.playerId == playerId) return true;
      return false;
    });
  }

  bool _isHostedByMe(SessionModel s) {
    final user = widget.authController?.currentUser;
    if (user == null) return false;
    final userId = user.userId;
    if (userId > 0 && s.hostUserId == userId) return true;
    final uName = user.nama.trim().toLowerCase();
    if (uName.isNotEmpty && s.hostName.trim().toLowerCase() == uName) return true;
    return false;
  }

  bool _isAtMyVenue(SessionModel s) {
    final user = widget.authController?.currentUser;
    if (user == null) return false;
    final userId = user.userId;
    if (userId > 0 && s.venueOwnerUserId == userId) return true;
    return false;
  }

  List<SessionModel> _getSportAndSearchFilteredSessions() {
    final query = _searchQuery.trim().toLowerCase();
    final tokens = query.isEmpty ? <String>[] : query.split(RegExp(r'\s+')).where((t) => t.isNotEmpty).toList();

    return _allSessions.where((s) {
      final matchesSearch = tokens.isEmpty || () {
        final searchableText = [
          s.namaSession,
          s.venueName,
          s.venueCity ?? '',
          s.venueAddress ?? '',
          s.courtName ?? '',
          s.sportName,
          s.scoringSystem,
          s.hostName,
        ].join(' ').toLowerCase();

        return tokens.every((token) => searchableText.contains(token));
      }();

      final matchesSport = _selectedSport == 'Semua Cabang' ||
          s.sportName.toLowerCase() == _selectedSport.toLowerCase();

      return matchesSearch && matchesSport;
    }).toList();
  }

  void _applyFilters() {
    final baseSessions = _getSportAndSearchFilteredSessions();

    if (_selectedTab == 'joined') {
      _filteredSessions = baseSessions.where(_isJoinedByMe).toList();
    } else if (_selectedTab == 'hosted') {
      _filteredSessions = baseSessions.where(_isHostedByMe).toList();
    } else if (_selectedTab == 'venue') {
      _filteredSessions = baseSessions.where(_isAtMyVenue).toList();
    } else {
      _filteredSessions = baseSessions;
    }
  }

  void _onSearch(String query) {
    setState(() {
      _searchQuery = query;
      _applyFilters();
    });
  }

  void _onSportSelect(String sport) {
    setState(() {
      _selectedSport = sport;
      _applyFilters();
    });
  }

  String get _emptyStateTitle {
    if (_selectedTab == 'joined') return 'Belum ada mabar yang kamu ikuti';
    if (_selectedTab == 'hosted') return 'Belum ada mabar yang kamu kelola';
    if (_selectedTab == 'venue') return 'Tidak ada sesi mabar di venue milikmu';
    return 'Tidak ada sesi mabar ditemukan';
  }

  String get _emptyStateSubtitle {
    if (_selectedTab == 'joined') return 'Jelajahi tab "Semua Sesi" dan gabung ke mabar seru!';
    if (_selectedTab == 'hosted') return 'Buat jadwal mabar baru dengan menekan tombol di bawah.';
    if (_selectedTab == 'venue') return 'Belum ada sesi yang dijadwalkan di venue kamu.';
    return 'Coba ubah kata kunci atau cabang olahraga.';
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;
    final isHost = user?.isHost == true;
    final isVenueOwner = user != null &&
        (user.role.toLowerCase() == 'venue_owner' || user.role.toLowerCase().contains('venue'));

    final baseForCounts = _getSportAndSearchFilteredSessions();
    final countAll = baseForCounts.length;
    final countVenue = baseForCounts.where(_isAtMyVenue).length;
    final countJoined = baseForCounts.where(_isJoinedByMe).length;
    final countHosted = baseForCounts.where(_isHostedByMe).length;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: RefreshIndicator(
        onRefresh: _loadSessions,
        color: AppColors.matchaDark,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            // Header & Filter Section
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Badge
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppColors.matchaSoftLime,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: AppColors.matchaDark.withValues(alpha: 0.2),
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.sports_tennis_rounded, size: 12, color: AppColors.matchaDark),
                          const SizedBox(width: 5),
                          Text(
                            'JADWAL & TURNAMEN',
                            style: AppTextStyles.badge.copyWith(
                              color: AppColors.matchaDark,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Jadwal Mabar & Turnamen',
                      style: AppTextStyles.h1.copyWith(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Temukan sesi mabar aktif dan amankan slot kuota bermainmu.',
                      style: AppTextStyles.caption.copyWith(
                        color: const Color(0xFF64748B),
                        height: 1.4,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Primary Filter Tabs (Mirip Web Matcha)
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      clipBehavior: Clip.none,
                      child: Row(
                        children: [
                          _buildPrimaryTab(
                            tabKey: 'all',
                            label: 'Semua Sesi',
                            icon: Icons.public_rounded,
                            count: countAll,
                            iconColor: const Color(0xFF64748B),
                            badgeBg: const Color(0xFFF1F5F9),
                            badgeText: const Color(0xFF475569),
                          ),
                          if (isVenueOwner || countVenue > 0) ...[
                            const SizedBox(width: 8),
                            _buildPrimaryTab(
                              tabKey: 'venue',
                              label: 'Sesi di Venue Saya',
                              icon: Icons.location_on_rounded,
                              count: countVenue,
                              iconColor: const Color(0xFF64748B),
                              badgeBg: const Color(0xFFF1F5F9),
                              badgeText: const Color(0xFF475569),
                            ),
                          ],
                          const SizedBox(width: 8),
                          _buildPrimaryTab(
                            tabKey: 'joined',
                            label: 'Mabar Saya / Diikuti',
                            icon: Icons.check_circle_rounded,
                            count: countJoined,
                            iconColor: const Color(0xFF10B981),
                            badgeBg: const Color(0xFFECFDF5),
                            badgeText: const Color(0xFF047857),
                            badgeBorder: const Color(0xFFA7F3D0),
                          ),
                          const SizedBox(width: 8),
                          _buildPrimaryTab(
                            tabKey: 'hosted',
                            label: 'Dikelola Saya (Host)',
                            icon: Icons.workspace_premium_rounded,
                            count: countHosted,
                            iconColor: const Color(0xFFF59E0B),
                            badgeBg: const Color(0xFFFFFBEB),
                            badgeText: const Color(0xFFB45309),
                            badgeBorder: const Color(0xFFFDE68A),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),

                    // Search Bar
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.02),
                            blurRadius: 6,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: TextField(
                        controller: _searchController,
                        onChanged: _onSearch,
                        decoration: InputDecoration(
                          hintText: 'Cari sesi mabar, venue, kota...',
                          hintStyle: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF94A3B8),
                            fontSize: 13,
                          ),
                          prefixIcon: const Icon(Icons.search, color: Color(0xFF94A3B8), size: 20),
                          suffixIcon: _searchQuery.isNotEmpty
                              ? IconButton(
                                  icon: const Icon(Icons.close_rounded, color: Color(0xFF94A3B8), size: 18),
                                  onPressed: () {
                                    _searchController.clear();
                                    _onSearch('');
                                  },
                                  )
                              : null,
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Sport Filters
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          _buildFilterChip('Semua Cabang', Icons.grid_view_rounded),
                          const SizedBox(width: 8),
                          _buildFilterChip('Padel', Icons.sports_kabaddi),
                          const SizedBox(width: 8),
                          _buildFilterChip('Tennis', Icons.sports_tennis),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                ),
              ),
            ),

            // Content: Loading, Error, Empty, or List
            if (_isLoading)
              const SliverFillRemaining(
                child: Center(
                  child: CircularProgressIndicator(color: AppColors.matchaDark),
                ),
              )
            else if (_errorMessage != null)
              SliverFillRemaining(
                child: OfflineStateWidget(
                  error: _errorMessage,
                  onRetry: _loadSessions,
                ),
              )
            else if (_filteredSessions.isEmpty)
              SliverFillRemaining(
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 32),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.event_busy_rounded, color: Color(0xFF94A3B8), size: 48),
                        const SizedBox(height: 12),
                        Text(
                          _emptyStateTitle,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.cardTitle.copyWith(color: const Color(0xFF334155)),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _emptyStateSubtitle,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption.copyWith(color: const Color(0xFF94A3B8)),
                        ),
                      ],
                    ),
                  ),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 90),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final session = _filteredSessions[index];
                      return _buildSessionCard(session);
                    },
                    childCount: _filteredSessions.length,
                  ),
                ),
              ),
          ],
        ),
      ),
      floatingActionButton: isHost
          ? FloatingActionButton.extended(
              onPressed: () {
                Navigator.push<bool>(
                  context,
                  MaterialPageRoute(
                    builder: (_) => CreateSessionPage(
                      authController: widget.authController,
                    ),
                  ),
                ).then((val) {
                  if (val == true) _loadSessions();
                });
              },
              backgroundColor: AppColors.matchaDark,
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add_rounded, color: Color(0xFFA8E63A)),
              label: const Text(
                'Buat Mabar',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
            )
          : null,
    );
  }

  Widget _buildPrimaryTab({
    required String tabKey,
    required String label,
    required IconData icon,
    required int count,
    required Color iconColor,
    required Color badgeBg,
    required Color badgeText,
    Color? badgeBorder,
  }) {
    final isSelected = _selectedTab == tabKey;

    return GestureDetector(
      onTap: () {
        setState(() {
          _selectedTab = tabKey;
          _applyFilters();
        });
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF063B00) : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
            width: 1,
          ),
          boxShadow: [
            BoxShadow(
              color: isSelected
                  ? const Color(0xFF063B00).withValues(alpha: 0.18)
                  : Colors.black.withValues(alpha: 0.02),
              blurRadius: isSelected ? 8 : 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 14,
              color: isSelected ? const Color(0xFFA8E63A) : iconColor,
            ),
            const SizedBox(width: 8),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: isSelected ? FontWeight.w700 : FontWeight.w600,
                color: isSelected ? Colors.white : const Color(0xFF334155),
              ),
            ),
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: isSelected ? Colors.white.withValues(alpha: 0.2) : badgeBg,
                borderRadius: BorderRadius.circular(12),
                border: !isSelected && badgeBorder != null
                    ? Border.all(color: badgeBorder, width: 1)
                    : null,
              ),
              child: Text(
                '$count',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w900,
                  color: isSelected ? Colors.white : badgeText,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFilterChip(String label, IconData icon) {
    final isSelected = _selectedSport == label;
    return GestureDetector(
      onTap: () => _onSportSelect(label),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.matchaDark : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: AppColors.matchaDark.withValues(alpha: 0.2),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  )
                ]
              : null,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 14,
              color: isSelected ? const Color(0xFFA8E63A) : const Color(0xFF64748B),
            ),
            const SizedBox(width: 6),
            Text(
              label,
              style: AppTextStyles.caption.copyWith(
                color: isSelected ? Colors.white : const Color(0xFF475569),
                fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatCardDate(DateTime? dt) {
    if (dt == null) return 'Jadwal belum ditentukan';
    final days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    final months = [
      '', 'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'
    ];
    final dayName = days[dt.weekday % 7];
    final monthName = months[dt.month];
    return '$dayName, ${dt.day} $monthName ${dt.year}';
  }

  Widget _buildPlayerAvatarStack(SessionModel session) {
    final players = session.registeredPlayers;
    if (players.isEmpty) {
      return const SizedBox(height: 24);
    }
    final displayPlayers = players.take(4).toList();
    return SizedBox(
      height: 24,
      width: (displayPlayers.length * 16.0) + 12,
      child: Stack(
        children: List.generate(displayPlayers.length, (idx) {
          final p = displayPlayers[idx];
          final avatarUrl = p.foto;
          final initial = p.nama.isNotEmpty ? p.nama[0].toUpperCase() : 'P';
          return Positioned(
            left: idx * 14.0,
            child: Container(
              width: 24,
              height: 24,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 1.5),
                color: AppColors.matchaSoftLime,
              ),
              child: ClipOval(
                child: (avatarUrl != null && avatarUrl.isNotEmpty)
                    ? Image.network(
                        avatarUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) => Center(
                          child: Text(
                            initial,
                            style: const TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.bold,
                              color: AppColors.matchaDark,
                            ),
                          ),
                        ),
                      )
                    : Center(
                        child: Text(
                          initial,
                          style: const TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.bold,
                            color: AppColors.matchaDark,
                          ),
                        ),
                      ),
              ),
            ),
          );
        }),
      ),
    );
  }


  Widget _buildSessionCard(SessionModel session) {
    final user = widget.authController?.currentUser;
    final isAdmin = user?.isAdmin ?? false;
    final isJoined = (user != null && user.playerId != null && user.playerId! > 0)
        ? session.registeredPlayers.any((p) => p.playerId == user.playerId || (p.userId != null && p.userId == user.userId))
        : false;

    final statusLower = session.statusSession.trim().toLowerCase();
    final isFinished = statusLower == 'finished' ||
        statusLower == 'completed' ||
        statusLower == 'selesai';
    final isLive = !isFinished &&
        (statusLower == 'in progress' ||
            statusLower == 'in_progress' ||
            statusLower == 'live');
    final isFull = !isFinished && session.isFull;
    final progress = session.jumlahPemain > 0
        ? (session.currentPlayersCount / session.jumlahPemain).clamp(0.0, 1.0)
        : 0.0;

    void openDetail() {
      if (widget.onSessionTap != null) {
        widget.onSessionTap!(session.sessionId);
      } else {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => SessionDetailPage(
              sessionId: session.sessionId,
              initialSession: session,
              authController: widget.authController,
            ),
          ),
        ).then((_) => _loadSessions());
      }
    }

    void handleJoin() {
      if (user == null) {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => LoginPage(authController: widget.authController)),
        );
        return;
      }
      JoinSessionModal.show(
        context: context,
        session: session,
        authController: widget.authController,
        onJoinedSuccess: _loadSessions,
      );
    }

    // Status Badge Label
    String statusBadgeLabel;
    Color statusBg;
    Color statusBorder;
    Color statusText;

    if (isFinished) {
      statusBadgeLabel = 'Selesai';
      statusBg = const Color(0xFFF1F5F9);
      statusBorder = const Color(0xFFCBD5E1);
      statusText = const Color(0xFF64748B);
    } else if (isLive) {
      statusBadgeLabel = 'LIVE NOW';
      statusBg = Colors.redAccent.withValues(alpha: 0.1);
      statusBorder = Colors.redAccent.withValues(alpha: 0.4);
      statusText = Colors.redAccent;
    } else if (isFull) {
      statusBadgeLabel = 'Ready for Drawing';
      statusBg = const Color(0xFFFEF2F2);
      statusBorder = const Color(0xFFFECACA);
      statusText = const Color(0xFFDC2626);
    } else {
      statusBadgeLabel = 'Open (${session.availableSlots} Slot Left)';
      statusBg = const Color(0xFFF0FDF4);
      statusBorder = const Color(0xFFBBF7D0);
      statusText = const Color(0xFF16A34A);
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: const Color(0xFFE2E8F0),
          width: 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: openDetail,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Top Badges (Sport, Format, Status)
                Row(
                  children: [
                    // Sport Pill
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3.5),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEBF8D8),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                      ),
                      child: Text(
                        session.sportName,
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF063B00),
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    // Format Pill
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3.5),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Text(
                        '${session.scoringSystem} / ${session.jenisPermainan}',
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF475569),
                        ),
                      ),
                    ),
                    const Spacer(),
                    // Status Pill
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3.5),
                      decoration: BoxDecoration(
                        color: statusBg,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: statusBorder),
                      ),
                      child: Text(
                        statusBadgeLabel,
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.bold,
                          color: statusText,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),

                // Session Name
                Text(
                  session.namaSession,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                    color: Color(0xFF063B00),
                  ),
                ),
                const SizedBox(height: 10),

                // Rounded Info Box (Venue, Date, Time)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFF1F5F9)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Location
                      Row(
                        children: [
                          const Icon(Icons.location_on_rounded, size: 15, color: Color(0xFF64748B)),
                          const SizedBox(width: 6),
                          Expanded(
                            child: RichText(
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              text: TextSpan(
                                style: const TextStyle(fontSize: 12, color: Color(0xFF1E293B)),
                                children: [
                                  TextSpan(
                                    text: session.venueName,
                                    style: const TextStyle(fontWeight: FontWeight.w700),
                                  ),
                                  TextSpan(
                                    text: ' • ${session.courtName ?? 'Court 1'}',
                                    style: const TextStyle(color: Color(0xFF64748B)),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 5),
                      // Date
                      Row(
                        children: [
                          const Icon(Icons.calendar_today_rounded, size: 13.5, color: Color(0xFF64748B)),
                          const SizedBox(width: 7),
                          Text(
                            _formatCardDate(session.datetime),
                            style: const TextStyle(fontSize: 11.5, color: Color(0xFF475569)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 5),
                      // Time
                      Row(
                        children: [
                          const Icon(Icons.access_time_rounded, size: 14, color: Color(0xFF64748B)),
                          const SizedBox(width: 7),
                          Text(
                            session.waktuSession ?? '18:30 WIB',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF0F172A),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),

                // Slot Progress
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Ketersediaan Slot',
                      style: TextStyle(fontSize: 11.5, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                    ),
                    Text(
                      '${session.currentPlayersCount} / ${session.jumlahPemain} Pemain',
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w800,
                        color: isFull ? const Color(0xFFDC2626) : const Color(0xFF0F172A),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),

                // Progress Bar
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: progress,
                    minHeight: 5,
                    backgroundColor: const Color(0xFFE2E8F0),
                    valueColor: AlwaysStoppedAnimation<Color>(
                      isFull ? Colors.redAccent : const Color(0xFF65A30D),
                    ),
                  ),
                ),
                const SizedBox(height: 10),

                // Players Avatar Stack & Host Row
                Row(
                  children: [
                    _buildPlayerAvatarStack(session),
                    const Spacer(),
                    RichText(
                      text: TextSpan(
                        style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
                        children: [
                          const TextSpan(text: 'Host: '),
                          TextSpan(
                            text: session.hostName,
                            style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),

                // Action Buttons
                if (isAdmin)
                  SizedBox(
                    width: double.infinity,
                    height: 40,
                    child: OutlinedButton(
                      onPressed: openDetail,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF334155),
                        side: const BorderSide(color: Color(0xFFCBD5E1)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      child: const Text('Detail', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                    ),
                  )
                else
                  Row(
                    children: [
                      SizedBox(
                        width: 80,
                        height: 42,
                        child: OutlinedButton(
                          onPressed: openDetail,
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 10),
                            foregroundColor: const Color(0xFF334155),
                            side: const BorderSide(color: Color(0xFFCBD5E1)),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          child: const Text('Detail', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: SizedBox(
                          height: 42,
                          child: ElevatedButton(
                            onPressed: isFinished
                                ? openDetail
                                : (isJoined
                                    ? openDetail
                                    : (isFull ? null : handleJoin)),
                            style: ElevatedButton.styleFrom(
                              padding: const EdgeInsets.symmetric(horizontal: 12),
                              backgroundColor: isFinished
                                  ? const Color(0xFFF1F5F9)
                                  : (isJoined ? const Color(0xFF15803D) : AppColors.matchaDark),
                              foregroundColor: isFinished ? const Color(0xFF64748B) : Colors.white,
                              disabledBackgroundColor: const Color(0xFFE2E8F0),
                              disabledForegroundColor: const Color(0xFF94A3B8),
                              elevation: 0,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Text(
                                isFinished
                                    ? 'Selesai'
                                    : (isJoined
                                        ? 'Sudah Bergabung'
                                        : (isFull
                                            ? 'Slot Penuh'
                                            : 'Gabung Slot')),
                                maxLines: 1,
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 13,
                                  color: isFinished ? const Color(0xFF64748B) : Colors.white,
                                ),
                              ),
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