import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../court/data/venue_service.dart';
import '../../court/domain/venue_model.dart';
import '../../court/presentation/court_detail_page.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import '../../match/presentation/match_scoring_page.dart';
import '../../session/data/session_service.dart';
import '../../session/domain/session_model.dart';
import '../../session/presentation/create_session_page.dart';

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

  void _showAuthRequiredModal({
    required String title,
    required String message,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFCBD5E1),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: AppColors.matchaSoftLime,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: AppColors.matchaDark.withValues(alpha: 0.2),
                    width: 2,
                  ),
                ),
                child: const Icon(
                  Icons.lock_outline_rounded,
                  color: AppColors.matchaDark,
                  size: 28,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                title,
                textAlign: TextAlign.center,
                style: AppTextStyles.h2.copyWith(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: const Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: AppTextStyles.caption.copyWith(
                  fontSize: 13,
                  color: const Color(0xFF64748B),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 46,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(ctx);
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => LoginPage(authController: widget.authController),
                      ),
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.matchaDark,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    elevation: 0,
                  ),
                  child: const Text(
                    'Masuk / Daftar Akun',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
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
                    CircleAvatar(
                      radius: 22,
                      backgroundImage: (avatarUrl != null && avatarUrl.isNotEmpty)
                          ? NetworkImage(avatarUrl)
                          : null,
                      backgroundColor: AppColors.matchaSoftLime,
                      child: (avatarUrl == null || avatarUrl.isEmpty)
                          ? Text(
                              userFirstName[0].toUpperCase(),
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                                color: AppColors.matchaDark,
                              ),
                            )
                          : null,
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
                    if (isGuest)
                      TextButton.icon(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => LoginPage(authController: widget.authController),
                            ),
                          );
                        },
                        icon: const Icon(Icons.login_rounded, size: 14, color: AppColors.matchaDark),
                        label: const Text(
                          'Masuk',
                          style: TextStyle(
                            color: AppColors.matchaDark,
                            fontWeight: FontWeight.bold,
                            fontSize: 12,
                          ),
                        ),
                        style: TextButton.styleFrom(
                          backgroundColor: AppColors.matchaSoftLime,
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        ),
                      ),
                  ],
                ),
              ),
            ),

            // --- Quick Actions ---
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Aksi Cepat',
                      style: AppTextStyles.h2.copyWith(
                        color: const Color(0xFF0F172A),
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: _buildActionButton(
                            title: 'Buat Sesi Mabar',
                            subtitle: isHost ? 'Jadwal & Kuota' : 'Khusus Host',
                            icon: Icons.add_circle_outline_rounded,
                            accentColor: AppColors.matchaDark,
                            onTap: () {
                              if (isGuest) {
                                _showAuthRequiredModal(
                                  title: 'Buat Jadwal Mabar Baru',
                                  message: 'Kamu harus masuk atau mendaftar akun terlebih dahulu untuk membuat sesi mabar dan mengundang pemain.',
                                );
                              } else if (isHost) {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(builder: (_) => const CreateSessionPage()),
                                );
                              } else {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text('Aktifkan Mode Host di halaman Profil untuk membuat sesi mabar.'),
                                    behavior: SnackBarBehavior.floating,
                                  ),
                                );
                              }
                            },
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: _buildActionButton(
                            title: 'Drawing & Live',
                            subtitle: 'Papan Bagan Tim',
                            icon: Icons.shuffle_rounded,
                            accentColor: const Color(0xFF047857),
                            onTap: () {
                              if (isGuest) {
                                _showAuthRequiredModal(
                                  title: 'Drawing & Match Console',
                                  message: 'Drawing bagan tim dan konsol live scoring dikelola oleh Host sesi. Masuk untuk mengelola drawing atau buka jadwal mabar untuk menonton.',
                                );
                              } else {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(builder: (_) => const DrawingResultPage()),
                                );
                              }
                            },
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            const SliverToBoxAdapter(child: SizedBox(height: 20)),

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

            // --- Session Cards / Loading / Empty State ---
            if (_isLoading)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(40),
                  child: Center(
                    child: CircularProgressIndicator(color: AppColors.matchaDark),
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

  Widget _buildActionButton({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color accentColor,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(12),
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
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: accentColor.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: accentColor, size: 18),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: AppTextStyles.caption.copyWith(
                      fontWeight: FontWeight.bold,
                      color: const Color(0xFF0F172A),
                      fontSize: 12,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: AppTextStyles.caption.copyWith(
                      color: const Color(0xFF64748B),
                      fontSize: 10,
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

  Widget _buildRealSessionCard(SessionModel session) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            // Navigate to detail
          },
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
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
                    const SizedBox(width: 6),
                    Text(
                      session.scoringSystem,
                      style: AppTextStyles.caption.copyWith(
                        color: const Color(0xFF64748B),
                        fontSize: 11,
                      ),
                    ),
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: session.statusSession.toLowerCase() == 'in progress'
                            ? const Color(0xFFFEF2F2)
                            : const Color(0xFFF0FDF4),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        session.statusSession,
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: session.statusSession.toLowerCase() == 'in progress'
                              ? Colors.redAccent
                              : const Color(0xFF16A34A),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  session.namaSession,
                  style: AppTextStyles.h3.copyWith(
                    color: const Color(0xFF0F172A),
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    const Icon(Icons.location_on_outlined, size: 13, color: Color(0xFF94A3B8)),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        session.venueName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTextStyles.caption.copyWith(
                          color: const Color(0xFF64748B),
                          fontSize: 11,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Row(
                  children: [
                    const Icon(Icons.access_time_rounded, size: 13, color: Color(0xFF94A3B8)),
                    const SizedBox(width: 4),
                    Text(
                      session.waktuSession ?? 'Waktu segera',
                      style: AppTextStyles.caption.copyWith(
                        color: const Color(0xFF64748B),
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Text(
                      '${session.currentPlayersCount}/${session.jumlahPemain} Slot Terisi',
                      style: AppTextStyles.caption.copyWith(
                        color: session.isFull ? Colors.redAccent : AppColors.matchaDark,
                        fontWeight: FontWeight.bold,
                        fontSize: 11,
                      ),
                    ),
                    const Spacer(),
                    const Text(
                      'Buka Detail',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: AppColors.matchaDark,
                      ),
                    ),
                    const SizedBox(width: 3),
                    const Icon(Icons.chevron_right_rounded, size: 16, color: AppColors.matchaDark),
                  ],
                ),
              ],
            ),
          ),
        ),
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
          if (venue.courts.isNotEmpty) {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => CourtDetailPage(courtId: venue.courts.first.courtId),
              ),
            );
          }
        },
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image / Placeholder
            if (venue.foto != null && venue.foto!.isNotEmpty)
              Image.network(
                venue.foto!,
                height: 90,
                width: double.infinity,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => _buildPlaceholderVenueImage(),
              )
            else
              _buildPlaceholderVenueImage(),

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
                  Text(
                    '${venue.courtCount} Court Tersedia',
                    style: AppTextStyles.caption.copyWith(
                      color: AppColors.matchaDark,
                      fontWeight: FontWeight.bold,
                      fontSize: 10,
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
