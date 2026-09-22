import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../data/session_service.dart';
import '../domain/session_model.dart';
import 'create_session_page.dart';
import 'session_detail_page.dart';
import 'widgets/join_session_modal.dart';
import '../../auth/presentation/controllers/auth_controller.dart';

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

  bool _isLoading = true;
  String? _errorMessage;
  List<SessionModel> _allSessions = [];
  List<SessionModel> _filteredSessions = [];

  String _searchQuery = '';
  String _selectedSport = 'Semua Cabang'; // 'Semua Cabang', 'Padel', 'Tennis'

  @override
  void initState() {
    super.initState();
    _loadSessions();
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

  void _applyFilters() {
    _filteredSessions = _allSessions.where((s) {
      final matchesSearch = _searchQuery.isEmpty ||
          s.namaSession.toLowerCase().contains(_searchQuery.toLowerCase()) ||
          s.venueName.toLowerCase().contains(_searchQuery.toLowerCase()) ||
          (s.venueCity != null && s.venueCity!.toLowerCase().contains(_searchQuery.toLowerCase()));

      final matchesSport = _selectedSport == 'Semua Cabang' ||
          s.sportName.toLowerCase() == _selectedSport.toLowerCase();

      return matchesSearch && matchesSport;
    }).toList();
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

  @override
  Widget build(BuildContext context) {
    final isHost = widget.authController?.currentUser?.isHost == true;

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
                        onChanged: _onSearch,
                        decoration: InputDecoration(
                          hintText: 'Cari sesi mabar, venue, kota...',
                          hintStyle: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF94A3B8),
                            fontSize: 13,
                          ),
                          prefixIcon: const Icon(Icons.search, color: Color(0xFF94A3B8), size: 20),
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
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline_rounded, color: Colors.redAccent, size: 40),
                        const SizedBox(height: 12),
                        Text(
                          'Gagal memuat sesi mabar',
                          style: AppTextStyles.cardTitle.copyWith(color: const Color(0xFF0F172A)),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption.copyWith(color: const Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _loadSessions,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.matchaDark,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          child: const Text('Coba Lagi'),
                        ),
                      ],
                    ),
                  ),
                ),
              )
            else if (_filteredSessions.isEmpty)
              SliverFillRemaining(
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.event_busy_rounded, color: Color(0xFF94A3B8), size: 48),
                      const SizedBox(height: 12),
                      Text(
                        'Tidak ada sesi mabar ditemukan',
                        style: AppTextStyles.cardTitle.copyWith(color: const Color(0xFF334155)),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Coba ubah kata kunci atau cabang olahraga',
                        style: AppTextStyles.caption.copyWith(color: const Color(0xFF94A3B8)),
                      ),
                    ],
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

  Widget _buildSessionCard(SessionModel session) {
    final isLive = session.statusSession.toLowerCase() == 'in progress' ||
        session.statusSession.toLowerCase() == 'live';
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

    void openJoin() {
      if (session.isFull) return;
      JoinSessionModal.show(
        context: context,
        session: session,
        authController: widget.authController,
        onJoinedSuccess: _loadSessions,
      );
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isLive ? AppColors.matchaDark.withValues(alpha: 0.3) : const Color(0xFFE2E8F0),
          width: 1,
        ),
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
          onTap: openDetail,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Top Badges
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: AppColors.matchaSoftLime,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        session.sportName.toUpperCase(),
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: AppColors.matchaDark,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '${session.jenisPermainan} • ${session.scoringSystem}',
                      style: AppTextStyles.caption.copyWith(
                        color: const Color(0xFF64748B),
                        fontSize: 11,
                      ),
                    ),
                    const Spacer(),
                    if (isLive)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: Colors.redAccent.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: Colors.redAccent.withValues(alpha: 0.4),
                            width: 0.8,
                          ),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.circle, color: Colors.redAccent, size: 7),
                            SizedBox(width: 4),
                            Text(
                              'LIVE NOW',
                              style: TextStyle(
                                color: Colors.redAccent,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      )
                    else
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0FDF4),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          session.statusSession,
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF16A34A),
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 10),

                // Session Name
                Text(
                  session.namaSession,
                  style: AppTextStyles.h3.copyWith(
                    color: const Color(0xFF0F172A),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),

                // Location & Venue
                Row(
                  children: [
                    const Icon(Icons.location_on_outlined, size: 14, color: Color(0xFF94A3B8)),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        session.venueName,
                        style: AppTextStyles.caption.copyWith(
                          color: const Color(0xFF64748B),
                          fontSize: 12,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),

                // Time / Schedule
                Row(
                  children: [
                    const Icon(Icons.access_time_rounded, size: 14, color: Color(0xFF94A3B8)),
                    const SizedBox(width: 5),
                    Text(
                      session.waktuSession ?? 'Jadwal belum ditentukan',
                      style: AppTextStyles.caption.copyWith(
                        color: const Color(0xFF64748B),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),

                // Host Info & Slot Progress
                Row(
                  children: [
                    CircleAvatar(
                      radius: 10,
                      backgroundImage: (session.hostAvatar != null && session.hostAvatar!.isNotEmpty)
                          ? NetworkImage(session.hostAvatar!)
                          : null,
                      backgroundColor: AppColors.matchaSoftLime,
                      child: (session.hostAvatar == null || session.hostAvatar!.isEmpty)
                          ? Text(
                              session.hostName.isNotEmpty ? session.hostName[0].toUpperCase() : 'H',
                              style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                            )
                          : null,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'Host: ${session.hostName}',
                      style: AppTextStyles.caption.copyWith(
                        color: const Color(0xFF475569),
                        fontWeight: FontWeight.w600,
                        fontSize: 11,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      '${session.currentPlayersCount}/${session.jumlahPemain} Slot Terisi',
                      style: TextStyle(
                        color: session.isFull ? Colors.redAccent : AppColors.matchaDark,
                        fontWeight: FontWeight.bold,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),

                // Progress Bar
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: progress,
                    minHeight: 4,
                    backgroundColor: const Color(0xFFE2E8F0),
                    valueColor: AlwaysStoppedAnimation<Color>(
                      session.isFull ? Colors.redAccent : AppColors.matchaDark,
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                const SizedBox(height: 10),

                // Action Buttons Row: Detail & Gabung Slot
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: openDetail,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: const Color(0xFF334155),
                          side: const BorderSide(color: Color(0xFFE2E8F0)),
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        child: const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text('Detail', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                            SizedBox(width: 4),
                            Icon(Icons.chevron_right_rounded, size: 16),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: session.isFull ? null : openJoin,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.matchaDark,
                          foregroundColor: Colors.white,
                          disabledBackgroundColor: const Color(0xFFE2E8F0),
                          disabledForegroundColor: const Color(0xFF94A3B8),
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          elevation: 0,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        child: Text(
                          session.isFull ? 'Penuh' : 'Gabung Slot 🎾',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
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