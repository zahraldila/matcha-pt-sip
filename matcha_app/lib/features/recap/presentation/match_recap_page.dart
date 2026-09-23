import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import '../../match/presentation/match_scoring_page.dart';
import '../../session/presentation/create_session_page.dart';
import '../../session/presentation/session_detail_page.dart';
import '../data/recap_service.dart';
import '../domain/recap_models.dart';

class MatchRecapPage extends StatefulWidget {
  final AuthController? authController;
  final String initialTab; // 'host' or 'career'

  const MatchRecapPage({
    super.key,
    this.authController,
    this.initialTab = 'host',
  });

  @override
  State<MatchRecapPage> createState() => _MatchRecapPageState();
}

class _MatchRecapPageState extends State<MatchRecapPage> {
  final RecapService _recapService = RecapService();

  late String _activeTab;
  bool _isLoading = true;
  String? _errorMessage;

  HostRecapData? _hostRecap;
  PlayerCareerRecapData? _careerRecap;

  @override
  void initState() {
    super.initState();
    final user = widget.authController?.currentUser;
    final isHost = user?.isHost ?? false;
    _activeTab = isHost ? widget.initialTab : 'career';

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

    final user = widget.authController?.currentUser;
    if (user == null) {
      setState(() {
        _isLoading = false;
        _errorMessage = 'Silakan masuk untuk melihat rekap pertandingan.';
      });
      return;
    }

    try {
      final hostFuture = user.isHost ? _recapService.getHostRecap(user.userId) : null;
      final careerFuture = _recapService.getPlayerCareerRecap(
        user.userId,
        playerId: user.playerId,
        userNama: user.nama,
        userFoto: user.foto,
        userRole: user.role == 'venue_owner'
            ? 'Venue Owner'
            : (user.isHost ? 'Host Game' : 'Member'),
        userLevel: user.level,
      );

      final hostRes = hostFuture != null ? await hostFuture : null;
      final careerRes = await careerFuture;

      if (!mounted) return;
      setState(() {
        _hostRecap = hostRes;
        _careerRecap = careerRes;
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

  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;
    final isHost = user?.isHost ?? false;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () => Navigator.pop(context),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Match Recap & Statistik',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w900,
                color: Color(0xFF0F172A),
              ),
            ),
            Text(
              isHost && _activeTab == 'host'
                  ? 'Riwayat sesi mabar yang kamu pimpin'
                  : 'Ringkasan karier bertanding dan win rate',
              style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Color(0xFF64748B)),
            onPressed: _loadData,
            tooltip: 'Perbarui Data',
          ),
          if (isHost && _activeTab == 'host')
            IconButton(
              icon: const Icon(Icons.add_circle_outline_rounded, color: AppColors.matchaDark),
              tooltip: 'Buat Sesi Mabar Baru',
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => CreateSessionPage(authController: widget.authController),
                  ),
                ).then((_) => _loadData());
              },
            )
          else
            IconButton(
              icon: const Icon(Icons.share_outlined, color: Color(0xFF64748B)),
              tooltip: 'Bagikan Rekap',
              onPressed: () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Kartu rekap siap dibagikan! 🚀'),
                    behavior: SnackBarBehavior.floating,
                  ),
                );
              },
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline_rounded, color: Colors.redAccent, size: 48),
                        const SizedBox(height: 12),
                        Text('Gagal memuat rekap', style: AppTextStyles.h3),
                        const SizedBox(height: 6),
                        Text(_errorMessage!, textAlign: TextAlign.center, style: AppTextStyles.caption),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _loadData,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.matchaDark,
                            foregroundColor: Colors.white,
                          ),
                          child: const Text('Coba Lagi'),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadData,
                  color: AppColors.matchaDark,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Dual-Mode Tab Switcher (Host only)
                        if (isHost) ...[
                          _buildTabSwitcher(user?.nama ?? 'Pemain'),
                          const SizedBox(height: 16),
                        ],

                        // Tab Content
                        if (isHost && _activeTab == 'host')
                          _buildHostTabView()
                        else
                          _buildCareerTabView(user),
                      ],
                    ),
                  ),
                ),
    );
  }

  Widget _buildTabSwitcher(String userName) {
    final totalHostSessions = _hostRecap?.totalSessions ?? 0;

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          // Tab 1: Host
          InkWell(
            onTap: () => setState(() => _activeTab = 'host'),
            borderRadius: BorderRadius.circular(14),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: _activeTab == 'host' ? const Color(0xFF063B00) : Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: _activeTab == 'host' ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.02),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.military_tech_rounded,
                    size: 15,
                    color: _activeTab == 'host' ? const Color(0xFFA8E63A) : const Color(0xFFD97706),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    'Riwayat Sesi Mabar (Host)',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: _activeTab == 'host' ? Colors.white : const Color(0xFF475569),
                    ),
                  ),
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: _activeTab == 'host' ? Colors.white.withValues(alpha: 0.2) : const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '$totalHostSessions',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: _activeTab == 'host' ? Colors.white : const Color(0xFF475569),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 8),

          // Tab 2: Career
          InkWell(
            onTap: () => setState(() => _activeTab = 'career'),
            borderRadius: BorderRadius.circular(14),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: _activeTab == 'career' ? const Color(0xFF063B00) : Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: _activeTab == 'career' ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.02),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.show_chart_rounded,
                    size: 15,
                    color: _activeTab == 'career' ? const Color(0xFFA8E63A) : const Color(0xFF16A34A),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    'Rekap Karir Pemain ($userName)',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: _activeTab == 'career' ? Colors.white : const Color(0xFF475569),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ==========================================
  // TAB 1: HOST SESSIONS RECAP VIEW
  // ==========================================
  Widget _buildHostTabView() {
    final host = _hostRecap;
    if (host == null) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // 4 KPI Summary Grid Cards (matching web)
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 1.6,
          children: [
            _buildKpiCard(
              label: 'TOTAL SESI DI-HOST',
              value: '${host.totalSessions}',
              subtitle: 'Sesi Mabar',
              valueColor: const Color(0xFF063B00),
            ),
            _buildKpiCard(
              label: 'PEMAIN TERLAYANI',
              value: '${host.totalPlayers}',
              subtitle: 'Total Peserta',
              valueColor: const Color(0xFF0F172A),
            ),
            _buildKpiCard(
              label: 'SESI SIAP / SELESAI',
              value: '${host.completedSessions}',
              subtitle: 'Match Selesai',
              valueColor: const Color(0xFF047857),
            ),
            _buildKpiCard(
              label: 'VENUE FAVORIT',
              value: host.favoriteVenue,
              subtitle: 'Sering Digunakan',
              valueColor: const Color(0xFF0F172A),
              isTextValue: true,
            ),
          ],
        ),

        const SizedBox(height: 20),

        // Session List Header
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Row(
              children: [
                Icon(Icons.checklist_rounded, size: 16, color: Color(0xFF063B00)),
                SizedBox(width: 6),
                Text(
                  'DAFTAR SESI YANG KAMU SELENGGARAKAN',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.5,
                    color: Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
            Text(
              '${host.sessions.length} Sesi Tercatat',
              style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
            ),
          ],
        ),

        const SizedBox(height: 12),

        if (host.sessions.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(32),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Column(
              children: [
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(
                    color: const Color(0xFFECFDF5),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Icon(Icons.emoji_events_outlined, color: Color(0xFF047857), size: 28),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Belum Ada Sesi yang Dibuat',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Kamu belum menyelenggarakan sesi mabar. Buat sesi pertama sekarang!',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 16),
                ElevatedButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => CreateSessionPage(authController: widget.authController),
                      ),
                    ).then((_) => _loadData());
                  },
                  icon: const Icon(Icons.add_rounded, size: 16),
                  label: const Text('Buat Sesi Mabar Pertama'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.matchaDark,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ],
            ),
          )
        else
          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: host.sessions.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final session = host.sessions[index];
              return _buildHostedSessionCard(session);
            },
          ),
      ],
    );
  }

  Widget _buildKpiCard({
    required String label,
    required String value,
    required String subtitle,
    required Color valueColor,
    bool isTextValue = false,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
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
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.bold,
              letterSpacing: 0.5,
              color: Color(0xFF94A3B8),
            ),
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: TextStyle(
              fontSize: isTextValue ? 13 : 20,
              fontWeight: FontWeight.w900,
              color: valueColor,
            ),
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 2),
          Text(
            subtitle,
            style: const TextStyle(fontSize: 9, color: Color(0xFF94A3B8)),
          ),
        ],
      ),
    );
  }

  Widget _buildHostedSessionCard(HostedSessionItem session) {
    final statusLower = session.status.toLowerCase();
    Color statusBg = const Color(0xFFF1F5F9);
    Color statusFg = const Color(0xFF475569);
    Color dotColor = const Color(0xFF64748B);

    if (statusLower.contains('ready')) {
      statusBg = const Color(0xFFECFDF5);
      statusFg = const Color(0xFF047857);
      dotColor = const Color(0xFF10B981);
    } else if (statusLower.contains('progress') || statusLower.contains('live')) {
      statusBg = const Color(0xFFFEF2F2);
      statusFg = const Color(0xFFDC2626);
      dotColor = const Color(0xFFEF4444);
    } else if (statusLower.contains('complete') || statusLower.contains('finish')) {
      statusBg = const Color(0xFFF1F5F9);
      statusFg = const Color(0xFF475569);
      dotColor = const Color(0xFF64748B);
    } else {
      statusBg = const Color(0xFFEFF6FF);
      statusFg = const Color(0xFF1D4ED8);
      dotColor = const Color(0xFF3B82F6);
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header: Sport, Title, Status
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFF063B00),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  session.sport.toUpperCase(),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 0.5,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  session.title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 13,
                    color: Color(0xFF0F172A),
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: statusBg,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: dotColor,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      session.status,
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: statusFg,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 6),

          // Venue, Court, Date, Time
          Row(
            children: [
              const Icon(Icons.location_on, size: 12, color: Color(0xFFEF4444)),
              const SizedBox(width: 3),
              Expanded(
                child: Text(
                  '${session.venue} (${session.court}) • 📅 ${session.date}, ${session.time}',
                  style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 10),

          // Registered Players
          Text(
            'PEMAIN TERDAFTAR (${session.joinedCount} / ${session.quota})',
            style: const TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.bold,
              letterSpacing: 0.5,
              color: Color(0xFF94A3B8),
            ),
          ),
          const SizedBox(height: 6),

          if (session.players.isEmpty)
            const Text(
              'Belum ada pemain yang bergabung',
              style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontStyle: FontStyle.italic),
            )
          else
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: session.players.map((p) {
                final isFemale = p.gender.toLowerCase().contains('female') ||
                    p.gender.toLowerCase().contains('wanita') ||
                    p.gender.toLowerCase().contains('perempuan');

                return Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        isFemale ? Icons.woman_rounded : Icons.man_rounded,
                        size: 13,
                        color: isFemale ? const Color(0xFFEC4899) : const Color(0xFF2563EB),
                      ),
                      const SizedBox(width: 3),
                      Text(
                        p.name,
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF334155),
                        ),
                      ),
                    ],
                  ),
                );
              }).toList(),
            ),

          const SizedBox(height: 14),

          // Action Buttons (Rekap Juara, Drawing, Scoring Live)
          Row(
            children: [
              // Rekap Juara
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => SessionDetailPage(
                          sessionId: session.sessionId,
                          authController: widget.authController,
                        ),
                      ),
                    );
                  },
                  icon: const Icon(Icons.military_tech_rounded, size: 14, color: Color(0xFFD97706)),
                  label: const Text(
                    'Rekap Juara',
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                  ),
                  style: OutlinedButton.styleFrom(
                    backgroundColor: const Color(0xFFFEF3C7).withValues(alpha: 0.5),
                    side: const BorderSide(color: Color(0xFFFDE68A)),
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
              ),
              const SizedBox(width: 6),

              // Drawing
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const DrawingResultPage()),
                    );
                  },
                  icon: const Icon(Icons.shuffle_rounded, size: 14, color: Color(0xFF475569)),
                  label: const Text(
                    'Drawing',
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
                  ),
                  style: OutlinedButton.styleFrom(
                    backgroundColor: Colors.white,
                    side: const BorderSide(color: Color(0xFFCBD5E1)),
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
              ),
              const SizedBox(width: 6),

              // Scoring Live
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const MatchScoringPage()),
                    );
                  },
                  icon: const Icon(Icons.play_circle_fill_rounded, size: 14, color: Color(0xFFA8E63A)),
                  label: const Text(
                    'Scoring Live',
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF063B00),
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ==========================================
  // TAB 2: PLAYER CAREER RECAP VIEW
  // ==========================================
  Widget _buildCareerTabView(dynamic user) {
    final career = _careerRecap;
    if (career == null) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Strava-Style Player Performance Summary Card (matching web)
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: const Color(0xFFE2E8F0)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.03),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Card Top Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          color: const Color(0xFF063B00),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Icon(Icons.sports_tennis_rounded, color: Color(0xFFA8E63A), size: 16),
                      ),
                      const SizedBox(width: 8),
                      const Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'PLAYER PERFORMANCE SUMMARY',
                            style: TextStyle(
                              fontSize: 8.5,
                              fontWeight: FontWeight.bold,
                              letterSpacing: 0.5,
                              color: Color(0xFF063B00),
                            ),
                          ),
                          Text(
                            'Rekap Pertandingan & Statistik Bermain',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF0F172A),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      RecapService.formatDate(DateTime.now()),
                      style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 14),
              const Divider(height: 1, color: Color(0xFFF1F5F9)),
              const SizedBox(height: 14),

              // Player Info & Win Streak
              Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFF063B00),
                      border: Border.all(color: const Color(0xFFBEF264), width: 2),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: Image.network(
                      career.avatar,
                      fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => Center(
                        child: Text(
                          career.playerName.isNotEmpty ? career.playerName[0].toUpperCase() : 'U',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Flexible(
                              child: Text(
                                career.playerName,
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF0F172A),
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFEBF8D8),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                              ),
                              child: Text(
                                career.level,
                                style: const TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFF063B00),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${career.username} • Role: ${career.role}',
                          style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFFBEB),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFFDE68A)),
                    ),
                    child: Text(
                      career.streak,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFFB45309),
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 16),

              // 4 KPI Big Stats Grid
              GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 8,
                crossAxisSpacing: 8,
                childAspectRatio: 1.8,
                children: [
                  _buildStatBox(
                    label: 'TOTAL TANDING',
                    value: '${career.totalMatches}',
                    subtitle: 'Pertandingan',
                    valueColor: const Color(0xFF0F172A),
                  ),
                  _buildStatBox(
                    label: 'KEMENANGAN',
                    value: '${career.wins}W',
                    subtitle: '${career.losses}x Kalah',
                    valueColor: const Color(0xFF063B00),
                  ),
                  _buildStatBox(
                    label: 'WIN RATE %',
                    value: career.winRate,
                    subtitle: 'Persentase',
                    valueColor: const Color(0xFF047857),
                  ),
                  _buildStatBox(
                    label: 'WAKTU BERMAIN',
                    value: career.totalHours,
                    subtitle: 'Total di Lapangan',
                    valueColor: const Color(0xFF0F172A),
                  ),
                ],
              ),
            ],
          ),
        ),

        const SizedBox(height: 20),

        if (!career.hasMatches)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Column(
              children: [
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEBF8D8),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Icon(Icons.sports_tennis_rounded, color: Color(0xFF063B00), size: 28),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Belum Ada Riwayat Pertandingan',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Ikuti dan selesaikan sesi mabar padel atau tenis untuk mulai mencatat performa karier dan win rate Anda di sini!',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                ),
              ],
            ),
          )
        else ...[
          // Recent Matches Section
          const Text(
            'Riwayat Pertandingan Terakhir',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w900,
              color: Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 8),

          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: career.recentMatches.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final match = career.recentMatches[index];
              final isWin = match.result == 'WIN';
              final isDraw = match.result == 'DRAW';

              return Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.01),
                      blurRadius: 4,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: isWin
                                      ? const Color(0xFFEBF8D8)
                                      : (isDraw ? const Color(0xFFF1F5F9) : const Color(0xFFFEF2F2)),
                                  borderRadius: BorderRadius.circular(6),
                                  border: Border.all(
                                    color: isWin
                                        ? const Color(0xFF063B00).withValues(alpha: 0.25)
                                        : (isDraw ? const Color(0xFFCBD5E1) : const Color(0xFFFCA5A5)),
                                  ),
                                ),
                                child: Text(
                                  match.result,
                                  style: TextStyle(
                                    fontSize: 9,
                                    fontWeight: FontWeight.w900,
                                    color: isWin
                                        ? const Color(0xFF063B00)
                                        : (isDraw ? const Color(0xFF475569) : const Color(0xFFDC2626)),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 6),
                              Text(
                                match.sport,
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                              ),
                              const SizedBox(width: 4),
                              const Text('•', style: TextStyle(color: Color(0xFFCBD5E1))),
                              const SizedBox(width: 4),
                              Expanded(
                                child: Text(
                                  match.venue,
                                  style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Partner: ${match.partner} vs Lawan: ${match.opponents.join(" & ")}',
                            style: const TextStyle(fontSize: 10.5, color: Color(0xFF64748B)),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          match.matchDate,
                          style: const TextStyle(fontSize: 9.5, color: Color(0xFF94A3B8)),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Skor: ${match.score}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              );
            },
          ),

          const SizedBox(height: 20),

          // Head-to-Head Section
          const Text(
            'Rekor Lawan (Head-to-Head)',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w900,
              color: Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Statistik kemenangan vs lawan bermain yang tercatat di sistem:',
            style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
          ),
          const SizedBox(height: 8),

          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: career.headToHead.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final h2h = career.headToHead[index];
                return Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            h2h.opponent,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF0F172A),
                            ),
                          ),
                          Text(
                            '${h2h.win}W - ${h2h.lose}L',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w900,
                              color: Color(0xFF063B00),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          value: (h2h.winRatePercent / 100).clamp(0.0, 1.0),
                          minHeight: 5,
                          backgroundColor: const Color(0xFFE2E8F0),
                          color: const Color(0xFF063B00),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            '${h2h.played}x main',
                            style: const TextStyle(fontSize: 9.5, color: Color(0xFF94A3B8)),
                          ),
                          Text(
                            '${h2h.winRatePercent.round()}% win',
                            style: const TextStyle(fontSize: 9.5, color: Color(0xFF94A3B8), fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildStatBox({
    required String label,
    required String value,
    required String subtitle,
    required Color valueColor,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 8.5,
              fontWeight: FontWeight.bold,
              letterSpacing: 0.5,
              color: Color(0xFF94A3B8),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
              color: valueColor,
            ),
          ),
          const SizedBox(height: 1),
          Text(
            subtitle,
            style: const TextStyle(fontSize: 8.5, color: Color(0xFF94A3B8)),
          ),
        ],
      ),
    );
  }
}
