import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/offline_state_widget.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../court/data/venue_service.dart';
import '../../court/domain/venue_model.dart';
import '../../court/presentation/venue_detail_page.dart';
import '../../session/data/session_service.dart';
import '../../session/domain/session_model.dart';
import '../../session/presentation/session_detail_page.dart';
import '../../session/presentation/widgets/join_session_modal.dart';

class HomePage extends StatefulWidget {
  final AuthController? authController;
  final VoidCallback? onExploreSessions;
  final VoidCallback? onExploreCommunity;

  const HomePage({
    super.key,
    this.authController,
    this.onExploreSessions,
    this.onExploreCommunity,
  });

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final SessionService _sessionService = SessionService();
  final VenueService _venueService = VenueService();

  bool _isLoading = true;
  String? _errorMessage;

  List<SessionModel> _sessions = [];
  List<VenueModel> _venues = [];
  String _selectedSport = 'Semua Cabang'; // 'Semua Cabang', 'Padel', 'Tennis'

  @override
  void initState() {
    super.initState();
    _loadData();
    widget.authController?.addListener(_onAuthChanged);
  }

  @override
  void dispose() {
    widget.authController?.removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
    if (mounted) setState(() {});
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final results = await Future.wait([
        _sessionService.getSessions(),
        _venueService.getVenues(),
      ]);

      if (!mounted) return;

      setState(() {
        _sessions = results[0] as List<SessionModel>;
        _venues = results[1] as List<VenueModel>;
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

  List<SessionModel> get _filteredSessions {
    if (_selectedSport == 'Semua Cabang') return _sessions;
    return _sessions.where((s) => s.sportName.toLowerCase() == _selectedSport.toLowerCase()).toList();
  }



  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;
    final isGuest = user == null;
    final isHost = user?.isHost ?? false;
    final userName = user?.nama ?? 'Tamu';
    final userFirstName = userName.trim().isNotEmpty ? userName.trim().split(' ').first : 'Tamu';
    final userLevel = user?.level ?? 'Newbie';
    final avatarUrl = user?.foto;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: RefreshIndicator(
        onRefresh: _loadData,
        color: AppColors.matchaDark,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            // --- Header & Greeting ---
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
                child: Row(
                  children: [
                    // Avatar
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: (avatarUrl != null && avatarUrl.isNotEmpty) ? Colors.transparent : AppColors.matchaSoftLime,
                        border: Border.all(color: const Color(0xFFE2E8F0), width: 1.5),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.04),
                            blurRadius: 4,
                            offset: const Offset(0, 1),
                          ),
                        ],
                      ),
                      child: ClipOval(
                        child: (avatarUrl != null && avatarUrl.isNotEmpty)
                            ? Image.network(
                                avatarUrl,
                                width: 44,
                                height: 44,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) => Container(
                                  color: AppColors.matchaSoftLime,
                                  alignment: Alignment.center,
                                  child: Text(
                                    userFirstName.isNotEmpty ? userFirstName[0].toUpperCase() : 'U',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 16,
                                      color: AppColors.matchaDark,
                                    ),
                                  ),
                                ),
                              )
                            : Center(
                                child: Text(
                                  userFirstName.isNotEmpty ? userFirstName[0].toUpperCase() : 'U',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: 16,
                                    color: AppColors.matchaDark,
                                  ),
                                ),
                              ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            isGuest ? 'Halo, Pemain Tamu 👋' : 'Halo, $userFirstName 👋',
                            style: AppTextStyles.h2.copyWith(
                              color: const Color(0xFF0F172A),
                              fontSize: 17,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            isGuest
                                ? 'Jelajahi mabar terbuka & direktori venue'
                                : (isHost
                                    ? 'Mode Host Aktif • Kelola mabar & skor 👑'
                                    : 'Skill $userLevel • Siap Mabar 🎾'),
                            style: AppTextStyles.caption.copyWith(
                              color: const Color(0xFF64748B),
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),

                  ],
                ),
              ),
            ),

            const SliverToBoxAdapter(child: SizedBox(height: 8)),

            // --- Jadwal Mabar Section ---
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
                                color: const Color(0xFF0F172A),
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Data live murni dari Supabase',
                              style: AppTextStyles.caption.copyWith(
                                color: const Color(0xFF64748B),
                                fontSize: 11,
                              ),
                            ),
                          ],
                        ),
                        TextButton(
                          onPressed: widget.onExploreSessions,
                          child: Text(
                            'Lihat Semua',
                            style: AppTextStyles.caption.copyWith(
                              color: AppColors.matchaDark,
                              fontWeight: FontWeight.bold,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    // Filter Chips
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          _buildSportFilterChip('Semua Cabang', Icons.grid_view_rounded),
                          const SizedBox(width: 8),
                          _buildSportFilterChip('Padel', Icons.sports_kabaddi),
                          const SizedBox(width: 8),
                          _buildSportFilterChip('Tennis', Icons.sports_tennis),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SliverToBoxAdapter(child: SizedBox(height: 12)),

            // --- Session Cards / Loading / Error / Empty State ---
            if (_isLoading)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(40),
                  child: Center(
                    child: CircularProgressIndicator(color: AppColors.matchaDark),
                  ),
                ),
              )
            else if (_errorMessage != null)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  child: OfflineStateWidget(
                    error: _errorMessage,
                    customTitle: 'Gagal Memuat Jadwal Mabar',
                    onRetry: _loadData,
                    isCompact: true,
                  ),
                ),
              )
            else if (_filteredSessions.isEmpty)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(32),
                  child: Center(
                    child: Column(
                      children: [
                        const Icon(Icons.event_busy_rounded, size: 40, color: Color(0xFF94A3B8)),
                        const SizedBox(height: 8),
                        Text(
                          'Belum ada jadwal mabar terbuka',
                          style: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF64748B),
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final session = _filteredSessions[index];
                      return _buildRealSessionCard(session);
                    },
                    childCount: _filteredSessions.length.clamp(0, 5),
                  ),
                ),
              ),

            const SliverToBoxAdapter(child: SizedBox(height: 16)),

            // --- Venue Mitra Populer Header ---
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Direktori Venue Mitra',
                          style: AppTextStyles.h2.copyWith(
                            color: const Color(0xFF0F172A),
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Fasilitas lapangan Padel & Tennis resmi',
                          style: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF64748B),
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                    TextButton(
                      onPressed: widget.onExploreCommunity,
                      child: Text(
                        'Lihat Direktori',
                        style: AppTextStyles.caption.copyWith(
                          color: AppColors.matchaDark,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SliverToBoxAdapter(child: SizedBox(height: 10)),

            // --- Venue Horizontal Scroll ---
            if (_venues.isNotEmpty)
              SliverToBoxAdapter(
                child: SizedBox(
                  height: 180,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    itemCount: _venues.length,
                    itemBuilder: (context, index) {
                      final venue = _venues[index];
                      return _buildHorizontalVenueCard(venue);
                    },
                  ),
                ),
              ),

            const SliverToBoxAdapter(child: SizedBox(height: 40)),
          ],
        ),
      ),
    );
  }

  Widget _buildSportFilterChip(String label, IconData icon) {
    final isSelected = _selectedSport == label;
    return GestureDetector(
      onTap: () => setState(() => _selectedSport = label),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.matchaDark : Colors.white,
          borderRadius: BorderRadius.circular(16),
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
              size: 13,
              color: isSelected ? const Color(0xFFA8E63A) : const Color(0xFF64748B),
            ),
            const SizedBox(width: 5),
            Text(
              label,
              style: AppTextStyles.caption.copyWith(
                color: isSelected ? Colors.white : const Color(0xFF475569),
                fontWeight: isSelected ? FontWeight.bold : FontWeight.w600,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }


  Widget _buildRealSessionCard(SessionModel session) {
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
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => SessionDetailPage(
            sessionId: session.sessionId,
            initialSession: session,
            authController: widget.authController,
          ),
        ),
      ).then((_) => _loadData());
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
        onJoinedSuccess: _loadData,
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
                      Expanded(
                        child: SizedBox(
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
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: SizedBox(
                          height: 40,
                          child: ElevatedButton(
                            onPressed: isFinished
                                ? openDetail
                                : (isJoined
                                    ? openDetail
                                    : (isFull ? null : handleJoin)),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: isFinished
                                  ? const Color(0xFFF1F5F9)
                                  : (isJoined ? const Color(0xFF15803D) : AppColors.matchaDark),
                              foregroundColor: isFinished ? const Color(0xFF64748B) : Colors.white,
                              disabledBackgroundColor: const Color(0xFFE2E8F0),
                              disabledForegroundColor: const Color(0xFF94A3B8),
                              elevation: 0,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: Text(
                              isFinished
                                  ? 'Selesai'
                                  : (isJoined
                                      ? 'Sudah Bergabung'
                                      : (isFull
                                          ? 'Slot Penuh'
                                          : 'Gabung Slot')),
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 13,
                                color: isFinished ? const Color(0xFF64748B) : Colors.white,
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

  String _formatCardDate(DateTime? dt) {
    if (dt == null) return 'Jadwal belum ditentukan';
    final days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    final months = [
      'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    final dayName = days[(dt.weekday - 1) % 7];
    final monthName = months[(dt.month - 1) % 12];
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
                        errorBuilder: (_, _, _) => Center(
                          child: Text(initial, style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: AppColors.matchaDark)),
                        ),
                      )
                    : Center(
                        child: Text(initial, style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: AppColors.matchaDark)),
                      ),
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget _buildHorizontalVenueCard(VenueModel venue) {
    return Container(
      width: 220,
      margin: const EdgeInsets.only(right: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => VenueDetailPage(
                venueId: venue.venueId,
                initialVenue: venue,
                authController: widget.authController,
              ),
            ),
          );
        },
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image with fallback
            Image.network(
              venue.mainPhoto,
              height: 90,
              width: double.infinity,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => _buildPlaceholderVenueImage(),
            ),

            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    venue.namaVenue,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.caption.copyWith(
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF0F172A),
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    venue.kota ?? 'Bandung',
                    style: AppTextStyles.caption.copyWith(
                      color: const Color(0xFF64748B),
                      fontSize: 11,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        '${venue.courtCount} Court Tersedia',
                        style: AppTextStyles.caption.copyWith(
                          color: AppColors.matchaDark,
                          fontWeight: FontWeight.bold,
                          fontSize: 10,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                        decoration: BoxDecoration(
                          color: AppColors.matchaSoftLime,
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          venue.sportName,
                          style: const TextStyle(
                            fontSize: 8,
                            fontWeight: FontWeight.bold,
                            color: AppColors.matchaDark,
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
      ),
    );
  }

  Widget _buildPlaceholderVenueImage() {
    return Container(
      height: 90,
      width: double.infinity,
      color: const Color(0xFF063B00),
      child: const Center(
        child: Icon(Icons.sports_tennis_rounded, color: Color(0xFFA8E63A), size: 28),
      ),
    );
  }
}
