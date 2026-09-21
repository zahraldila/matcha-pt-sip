import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import '../../match/presentation/match_scoring_page.dart';
import '../data/session_service.dart';
import '../domain/session_model.dart';
import 'widgets/join_session_modal.dart';

class SessionDetailPage extends StatefulWidget {
  final int sessionId;
  final SessionModel? initialSession;
  final AuthController? authController;

  const SessionDetailPage({
    super.key,
    required this.sessionId,
    this.initialSession,
    this.authController,
  });

  @override
  State<SessionDetailPage> createState() => _SessionDetailPageState();
}

class _SessionDetailPageState extends State<SessionDetailPage> {
  final SessionService _sessionService = SessionService();

  late SessionModel? _session;
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _session = widget.initialSession;
    _loadSessionDetail();
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

  Future<void> _loadSessionDetail() async {
    if (_session == null) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final freshSession = await _sessionService.getSessionDetail(widget.sessionId);
      if (!mounted) return;
      setState(() {
        _session = freshSession;
        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        if (_session == null) {
          _errorMessage = e.toString();
        }
      });
    }
  }

  void _openJoinModal() {
    final s = _session;
    if (s == null) return;

    JoinSessionModal.show(
      context: context,
      session: s,
      authController: widget.authController,
      onJoinedSuccess: _loadSessionDetail,
    );
  }

  Future<void> _handleLeaveSession(int playerId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Batalkan Keikutsertaan?'),
        content: const Text('Slot kuota kamu akan dikosongkan dan dapat diisi oleh pemain lain.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Ya, Batalkan', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      await _sessionService.leaveSession(
        sessionId: widget.sessionId,
        playerId: playerId,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Kamu telah keluar dari sesi mabar ini.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      _loadSessionDetail();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal keluar sesi: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  String _formatDisplayDate(DateTime? dt) {
    if (dt == null) return 'Selasa, 22 Sep 2026';
    const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
      'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    final dayName = days[(dt.weekday - 1).clamp(0, 6)];
    final monthName = months[(dt.month - 1).clamp(0, 11)];
    return '$dayName, ${dt.day} $monthName ${dt.year}';
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;
    final session = _session;

    final isUserJoined = (session != null && user != null && user.playerId != null && user.playerId! > 0)
        ? session.registeredPlayers.any((p) => p.playerId == user.playerId || (p.userId != null && p.userId == user.userId))
        : false;

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
        title: Text(
          'Detail Sesi Mabar',
          style: AppTextStyles.h2.copyWith(
            fontSize: 16,
            color: const Color(0xFF0F172A),
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Color(0xFF64748B)),
            onPressed: _loadSessionDetail,
            tooltip: 'Perbarui data',
          ),
          IconButton(
            icon: const Icon(Icons.share_outlined, color: Color(0xFF64748B)),
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Tautan sesi mabar disalin! Siap dibagikan ke WhatsApp 📲'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
          ),
        ],
      ),
      body: _isLoading && session == null
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : _errorMessage != null && session == null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline_rounded, color: Colors.redAccent, size: 48),
                        const SizedBox(height: 12),
                        Text('Gagal memuat detail sesi', style: AppTextStyles.h2),
                        const SizedBox(height: 6),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption.copyWith(color: const Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _loadSessionDetail,
                          style: ElevatedButton.styleFrom(backgroundColor: AppColors.matchaDark),
                          child: const Text('Coba Lagi', style: TextStyle(color: Colors.white)),
                        ),
                      ],
                    ),
                  ),
                )
              : session == null
                  ? const SizedBox.shrink()
                  : RefreshIndicator(
                      onRefresh: _loadSessionDetail,
                      color: AppColors.matchaDark,
                      child: Column(
                        children: [
                          Expanded(
                            child: SingleChildScrollView(
                              physics: const AlwaysScrollableScrollPhysics(),
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // --- 1. Header Section (Title & Badges like Web show.blade.php) ---
                                  _buildWebMatchingHeader(session),

                                  const SizedBox(height: 16),

                                  // --- 2. Informasi Pelaksanaan (Grid 2/3 Cols + Host banner) ---
                                  _buildExecutionInfoBox(session),

                                  const SizedBox(height: 16),

                                  // --- 3. Drawing & Mulai Pertandingan (Action Card) ---
                                  _buildDrawingScoringCard(session),

                                  const SizedBox(height: 20),

                                  // --- 4. Daftar Peserta Table (Real DB matching Web show.blade.php) ---
                                  _buildParticipantTable(session, user?.playerId),

                                  const SizedBox(height: 30),
                                ],
                              ),
                            ),
                          ),

                          // --- 5. Sticky Bottom Action Bar ---
                          _buildBottomActionBar(session, isUserJoined, user?.playerId),
                        ],
                      ),
                    ),
    );
  }

  /// Header matching `show.blade.php` (Title, Sport Badge, Status Badge)
  Widget _buildWebMatchingHeader(SessionModel session) {
    final isFull = session.isFull;
    final slotLeft = session.availableSlots;
    final statusBadgeLabel = isFull
        ? 'Ready for Drawing'
        : 'Open ($slotLeft Slot Left)';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: Text(
                session.namaSession,
                style: AppTextStyles.h1.copyWith(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  color: const Color(0xFF0F172A),
                ),
              ),
            ),
            const SizedBox(width: 8),
            // Sport Badge
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: AppColors.matchaSoftLime,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
              ),
              child: Text(
                session.sportName,
                style: const TextStyle(
                  color: AppColors.matchaDark,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            const SizedBox(width: 6),
            // Status Badge
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: isFull ? const Color(0xFFFEF2F2) : const Color(0xFFF0FDF4),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: isFull ? const Color(0xFFFECACA) : const Color(0xFFBBF7D0),
                ),
              ),
              child: Text(
                statusBadgeLabel,
                style: TextStyle(
                  color: isFull ? Colors.redAccent : const Color(0xFF16A34A),
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  /// Informasi Pelaksanaan Box (matching web clean-card & 5-item grid)
  Widget _buildExecutionInfoBox(SessionModel session) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Informasi Pelaksanaan',
            style: AppTextStyles.h3.copyWith(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: const Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 12),

          // Grid Item 1 & 2: Venue & Jadwal
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _buildGridInfoTile(
                  label: 'Venue',
                  mainValue: session.venueName,
                  subValue: session.courtName ?? 'Court 1',
                  isSubHighlighted: true,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _buildGridInfoTile(
                  label: 'Jadwal',
                  mainValue: session.waktuSession ?? '18:30 WIB',
                  subValue: _formatDisplayDate(session.datetime),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),

          // Grid Item 3 & 4: Durasi & Kuota and Format
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _buildGridInfoTile(
                  label: 'Durasi & Kuota',
                  mainValue: '-',
                  subValue: '${session.currentPlayersCount} / ${session.jumlahPemain} Pemain',
                  isSubBold: true,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _buildGridInfoTile(
                  label: 'Format',
                  mainValue: 'Americano / ${session.jenisPermainan}',
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),

          // Grid Item 5: Sistem Scoring
          _buildGridInfoTile(
            label: 'Sistem Scoring',
            mainValue: session.scoringSystem,
          ),

          const SizedBox(height: 12),

          // Host Sesi Mabar Box (Emerald pill like Web show.blade.php line 62)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: const Color(0xFFF0FDF4),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFDCFCE7)),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 18,
                  backgroundImage: (session.hostAvatar != null && session.hostAvatar!.isNotEmpty)
                      ? NetworkImage(session.hostAvatar!)
                      : null,
                  backgroundColor: AppColors.matchaSoftLime,
                  child: (session.hostAvatar == null || session.hostAvatar!.isEmpty)
                      ? Text(
                          session.hostName.isNotEmpty ? session.hostName[0].toUpperCase() : 'H',
                          style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                        )
                      : null,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Host Sesi Mabar:',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF166534),
                        ),
                      ),
                      const SizedBox(height: 1),
                      RichText(
                        text: TextSpan(
                          text: session.hostName,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF0F172A),
                          ),
                          children: [
                            TextSpan(
                              text: ' (${session.hostLevel})',
                              style: const TextStyle(
                                fontWeight: FontWeight.normal,
                                color: Color(0xFF64748B),
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGridInfoTile({
    required String label,
    required String mainValue,
    String? subValue,
    bool isSubHighlighted = false,
    bool isSubBold = false,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: Color(0xFF64748B),
              fontSize: 10,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            mainValue,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Color(0xFF0F172A),
              fontSize: 12,
              fontWeight: FontWeight.bold,
            ),
          ),
          if (subValue != null && subValue.isNotEmpty) ...[
            const SizedBox(height: 1),
            Text(
              subValue,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: isSubHighlighted
                    ? const Color(0xFF047857)
                    : (isSubBold ? const Color(0xFF334155) : const Color(0xFF64748B)),
                fontSize: 10,
                fontWeight: (isSubHighlighted || isSubBold) ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          ],
        ],
      ),
    );
  }

  /// Card Drawing & Mulai Pertandingan (matching web lines 130-158)
  Widget _buildDrawingScoringCard(SessionModel session) {
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
          Text(
            'Drawing & Mulai Pertandingan',
            style: AppTextStyles.h3.copyWith(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: const Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 3),
          Text(
            session.isFull
                ? 'Pemain telah lengkap. Host dapat mengacak tim dan memulai scoring poin.'
                : 'Pemain sedang mengumpulkan kuota (${session.currentPlayersCount}/${session.jumlahPemain}). Buka drawing untuk melihat simulasi atau susunan bagan.',
            style: AppTextStyles.caption.copyWith(
              color: const Color(0xFF64748B),
              fontSize: 11,
              height: 1.3,
            ),
          ),
          const SizedBox(height: 12),

          // Tombol Buka Drawing Tim
          SizedBox(
            width: double.infinity,
            height: 40,
            child: ElevatedButton.icon(
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const DrawingResultPage()),
                );
              },
              icon: const Icon(Icons.shuffle_rounded, size: 16),
              label: const Text('Buka Drawing Tim', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.matchaDark,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ),
          const SizedBox(height: 8),

          // Tombol Live Match Scoring
          SizedBox(
            width: double.infinity,
            height: 40,
            child: OutlinedButton.icon(
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const MatchScoringPage(),
                  ),
                );
              },
              icon: const Icon(Icons.timer_outlined, size: 16),
              label: const Text('Live Match Scoring', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.matchaDark,
                side: const BorderSide(color: AppColors.matchaDark, width: 1.5),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ),

          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 8),

          // Feature Checkmarks
          Row(
            children: [
              const Icon(Icons.check_rounded, size: 14, color: AppColors.matchaDark),
              const SizedBox(width: 6),
              Text(
                'Drawing otomatis seimbang',
                style: AppTextStyles.caption.copyWith(color: const Color(0xFF64748B), fontSize: 11),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.check_rounded, size: 14, color: AppColors.matchaDark),
              const SizedBox(width: 6),
              Text(
                'Visualisasi lapangan tennis/padel',
                style: AppTextStyles.caption.copyWith(color: const Color(0xFF64748B), fontSize: 11),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// Participant Table (matching web lines 74-126)
  Widget _buildParticipantTable(SessionModel session, int? currentUserId) {
    return Container(
      width: double.infinity,
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Table Header Bar
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Daftar Peserta (${session.currentPlayersCount}/${session.jumlahPemain})',
                  style: AppTextStyles.h3.copyWith(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF0F172A),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: session.isFull ? const Color(0xFFFEF2F2) : const Color(0xFFF0FDF4),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: session.isFull ? const Color(0xFFFECACA) : const Color(0xFFBBF7D0),
                    ),
                  ),
                  child: Text(
                    session.isFull ? 'Kuota Lengkap' : 'Slot Terbuka',
                    style: TextStyle(
                      color: session.isFull ? Colors.redAccent : const Color(0xFF16A34A),
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Table Column Titles Header
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: const BoxDecoration(
              color: Color(0xFFF8FAFC),
              border: Border.symmetric(
                horizontal: BorderSide(color: Color(0xFFE2E8F0), width: 1),
              ),
            ),
            child: const Row(
              children: [
                SizedBox(width: 20, child: Text('#', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF64748B)))),
                SizedBox(width: 8),
                Expanded(flex: 3, child: Text('NAMA', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF64748B)))),
                Expanded(flex: 2, child: Text('STATUS', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF64748B)))),
                Expanded(flex: 2, child: Text('SKILL LEVEL', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF64748B)))),
                Expanded(flex: 3, child: Text('GENDER / USIA', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF64748B)))),
              ],
            ),
          ),

          // Table Rows
          if (session.registeredPlayers.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: Text(
                  'Belum ada pemain bergabung',
                  style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                ),
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: session.registeredPlayers.length,
              separatorBuilder: (_, _) => const Divider(height: 1, color: Color(0xFFF1F5F9)),
              itemBuilder: (context, index) {
                final player = session.registeredPlayers[index];
                final isMe = (currentUserId != null && currentUserId > 0 && player.playerId == currentUserId);

                return Container(
                  color: isMe ? const Color(0xFFF0FDF4) : Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  child: Row(
                    children: [
                      // # Column
                      SizedBox(
                        width: 20,
                        child: Text(
                          '${index + 1}',
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF94A3B8),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),

                      // NAMA Column (Avatar + Name)
                      Expanded(
                        flex: 3,
                        child: Row(
                          children: [
                            CircleAvatar(
                              radius: 12,
                              backgroundImage: (player.foto != null && player.foto!.isNotEmpty)
                                  ? NetworkImage(player.foto!)
                                  : null,
                              backgroundColor: AppColors.matchaSoftLime,
                              child: (player.foto == null || player.foto!.isEmpty)
                                  ? Text(
                                      player.nama.isNotEmpty ? player.nama[0].toUpperCase() : 'P',
                                      style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                                    )
                                  : null,
                            ),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(
                                player.nama,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFF0F172A),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),

                      // STATUS Column (Member vs Guest Badge)
                      Expanded(
                        flex: 2,
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: player.isMember ? const Color(0xFFF0FDF4) : const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(
                                color: player.isMember ? const Color(0xFFBBF7D0) : const Color(0xFFE2E8F0),
                              ),
                            ),
                            child: Text(
                              player.isMember ? 'Member' : 'Guest',
                              style: TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.bold,
                                color: player.isMember ? const Color(0xFF166534) : const Color(0xFF475569),
                              ),
                            ),
                          ),
                        ),
                      ),

                      // SKILL LEVEL Column
                      Expanded(
                        flex: 2,
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: _buildSkillBadge(player.level ?? 'Beginner'),
                        ),
                      ),

                      // GENDER / USIA Column
                      Expanded(
                        flex: 3,
                        child: Text(
                          '${player.gender == "Male" ? "Male" : "Female"}, ${player.usia ?? 25} th',
                          style: const TextStyle(
                            fontSize: 10,
                            color: Color(0xFF64748B),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
        ],
      ),
    );
  }

  Widget _buildSkillBadge(String level) {
    Color bg;
    Color fg;
    Color border;

    switch (level.toLowerCase()) {
      case 'advanced':
        bg = const Color(0xFFFEF3C7);
        fg = const Color(0xFFB45309);
        border = const Color(0xFFFDE68A);
        break;
      case 'intermediate':
        bg = const Color(0xFFEFF6FF);
        fg = const Color(0xFF1D4ED8);
        border = const Color(0xFFBFDBFE);
        break;
      case 'beginner':
        bg = const Color(0xFFF0FDF4);
        fg = const Color(0xFF15803D);
        border = const Color(0xFFBBF7D0);
        break;
      default:
        bg = const Color(0xFFF8FAFC);
        fg = const Color(0xFF475569);
        border = const Color(0xFFE2E8F0);
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(4),
        border: Border.all(color: border),
      ),
      child: Text(
        level,
        style: TextStyle(
          fontSize: 9,
          fontWeight: FontWeight.bold,
          color: fg,
        ),
      ),
    );
  }

  Widget _buildBottomActionBar(SessionModel session, bool isUserJoined, int? myPlayerId) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: const Border(top: BorderSide(color: Color(0xFFE2E8F0), width: 1)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        child: SizedBox(
          width: double.infinity,
          height: 48,
          child: isUserJoined
              ? Row(
                  children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14),
                        alignment: Alignment.centerLeft,
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0FDF4),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFF86EFAC)),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 18),
                            SizedBox(width: 8),
                            Text(
                              'Kamu Sudah Bergabung',
                              style: TextStyle(
                                color: Color(0xFF16A34A),
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    ElevatedButton(
                      onPressed: () {
                        if (myPlayerId != null) {
                          _handleLeaveSession(myPlayerId);
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFFEF2F2),
                        foregroundColor: Colors.redAccent,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: const BorderSide(color: Color(0xFFFECACA)),
                        ),
                      ),
                      child: const Text('Batal', style: TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  ],
                )
              : ElevatedButton(
                  onPressed: session.isFull ? null : _openJoinModal,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: session.isFull ? const Color(0xFFE2E8F0) : AppColors.matchaDark,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: const Color(0xFFE2E8F0),
                    disabledForegroundColor: const Color(0xFF94A3B8),
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  child: Text(
                    session.isFull ? 'Slot Kuota Penuh' : 'Gabung Slot Sesi Mabar 🎾',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ),
        ),
      ),
    );
  }
}
