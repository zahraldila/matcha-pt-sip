import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/app_error_handler.dart';
import '../../../core/widgets/offline_state_widget.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import '../../match/presentation/match_scoring_page.dart';
import '../../recap/presentation/session_match_recap_page.dart';
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


  Future<void> _handleShareSession() async {
    final session = _session;
    if (session == null) return;

    String? shareToken = session.shareToken?.trim();

    // Jika sesi belum memiliki share_token di database, buat otomatis on-the-fly
    if (shareToken == null || shareToken.isEmpty) {
      try {
        shareToken = await _sessionService.ensureShareToken(session.sessionId);
        if (mounted) {
          setState(() {
            _session = session.copyWith(shareToken: shareToken);
          });
        }
      } catch (e) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Gagal menyiapkan link share sesi. Silakan coba lagi.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }
    }

    final shareUrl = 'https://matcha.siproduktif.com/games/share/$shareToken';
    final shareText = 'Mabar yuk di MATCHA!\n\n${session.namaSession}\n\n$shareUrl';

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
                          'Bagikan Sesi Mabar',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                        ),
                        Text(
                          session.namaSession,
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
                title: const Text('Salin Link Sesi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                subtitle: const Text('Salin tautan langsung untuk gabung sesi ini', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                onTap: () {
                  Clipboard.setData(ClipboardData(text: shareUrl));
                  Navigator.pop(ctx);
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Link sesi berhasil disalin ke clipboard! 🔗'),
                      backgroundColor: Color(0xFF063B00),
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                },
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.copy_rounded, color: Color(0xFF0F172A), size: 18),
                ),
                title: const Text('Salin Teks Undangan Lengkap', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                subtitle: const Text('Salin pesan ajakan mabar beserta tautan', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                onTap: () {
                  Clipboard.setData(ClipboardData(text: shareText));
                  Navigator.pop(ctx);
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Pesan undangan mabar berhasil disalin! 📋'),
                      backgroundColor: Color(0xFF063B00),
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                },
              ),
              const SizedBox(height: 8),
            ],
          ),
        );
      },
    );
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

  Future<void> _handleDeleteSession() async {
    final s = _session;
    if (s == null) return;

    if (s.statusSession.toLowerCase() == 'completed' || s.statusSession.toLowerCase() == 'selesai') {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text('Sesi Telah Selesai', style: TextStyle(fontWeight: FontWeight.bold)),
          content: const Text(
            'Sesi ini telah selesai dan memiliki data historis pertandingan serta skor. Sesi yang telah selesai tidak dapat dihapus demi menjaga konsistensi leaderboard dan rekap poin.',
            style: TextStyle(fontSize: 13, color: Color(0xFF475569)),
          ),
          actions: [
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.matchaDark,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Mengerti', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Hapus Jadwal Mabar?', style: TextStyle(fontWeight: FontWeight.bold)),
        content: Text(
          'Sesi "${s.namaSession}" akan dihapus secara permanen dari database. Seluruh data drawing dan pendaftaran pada sesi ini akan dibersihkan.',
          style: const TextStyle(fontSize: 13, color: Color(0xFF475569)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Kembali'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Ya, Hapus Sesi'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      setState(() => _isLoading = true);
      await _sessionService.deleteSession(widget.sessionId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Jadwal sesi mabar berhasil dihapus dari database.'),
          backgroundColor: AppColors.matchaDark,
        ),
      );
      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      AppErrorHandler.showErrorSnackBar(context, e);
    }
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
      ),
      body: _isLoading && session == null
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : _errorMessage != null && session == null
              ? OfflineStateWidget(
                  error: _errorMessage,
                  onRetry: _loadSessionDetail,
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

                          // --- 5. Sticky Bottom Action Bar (Hidden for Admin) ---
                          if (user?.isAdmin != true)
                            _buildBottomActionBar(session, isUserJoined, user?.playerId),
                        ],
                      ),
                    ),
    );
  }

  /// Header matching `show.blade.php` (Title, Sport Badge, Status Badge, Share Action)
  Widget _buildWebMatchingHeader(SessionModel session) {
    final statusLower = session.statusSession.trim().toLowerCase();
    final isFinished = statusLower == 'finished' ||
        statusLower == 'completed' ||
        statusLower == 'selesai';
    final isLive = !isFinished &&
        (statusLower == 'in progress' ||
            statusLower == 'in_progress' ||
            statusLower == 'live');
    final isFull = !isFinished && session.isFull;
    final slotLeft = session.availableSlots;

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
      statusBadgeLabel = 'Open ($slotLeft Slot Left)';
      statusBg = const Color(0xFFF0FDF4);
      statusBorder = const Color(0xFFBBF7D0);
      statusText = const Color(0xFF16A34A);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          session.namaSession,
          style: AppTextStyles.h1.copyWith(
            fontSize: 22,
            fontWeight: FontWeight.w900,
            color: const Color(0xFF0F172A),
          ),
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
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
            // Status Badge
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: statusBg,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: statusBorder),
              ),
              child: Text(
                statusBadgeLabel,
                style: TextStyle(
                  color: statusText,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            // Compact Share Button
            Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              child: InkWell(
                onTap: _handleShareSession,
                borderRadius: BorderRadius.circular(20),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0xFFCBD5E1)),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.share_outlined, size: 12, color: Color(0xFF475569)),
                      SizedBox(width: 4),
                      Text(
                        'Share',
                        style: TextStyle(
                          color: Color(0xFF475569),
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
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
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
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
          ),
          const SizedBox(height: 8),

          // Grid Item 3 & 4: Durasi & Kuota and Format
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(
                  child: _buildGridInfoTile(
                    label: 'Durasi & Kuota',
                    mainValue: RegExp(r'\(([^)]+)\)').firstMatch(session.waktuSession ?? '')?.group(1) ?? '-',
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
      constraints: const BoxConstraints(minHeight: 70),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
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

  /// Card Drawing & Pertandingan (matching web)
  Widget _buildDrawingScoringCard(SessionModel session) {
    final statusLower = session.statusSession.trim().toLowerCase();
    final isFinished = statusLower == 'finished' ||
        statusLower == 'completed' ||
        statusLower == 'selesai';
    final isLive = !isFinished &&
        (statusLower == 'in progress' ||
            statusLower == 'in_progress' ||
            statusLower == 'live');

    final canManage = (widget.authController?.currentUser?.isAdmin == true ||
            (widget.authController?.currentUser != null &&
                widget.authController?.currentUser?.userId == session.hostUserId)) &&
        statusLower != 'cancelled' &&
        !isFinished;

    final isDrawingReady = !isFinished &&
        (session.isFull ||
            statusLower == 'ready' ||
            statusLower == 'ready for drawing' ||
            isLive);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isFinished ? const Color(0xFFFFFBEB) : Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isFinished ? const Color(0xFFFDE68A) : const Color(0xFFE2E8F0),
          width: isFinished ? 1.5 : 1.0,
        ),
        boxShadow: [
          BoxShadow(
            color: isFinished
                ? const Color(0xFFD97706).withValues(alpha: 0.05)
                : Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (isFinished) ...[
            const Row(
              children: [
                Icon(Icons.emoji_events_rounded, size: 14, color: Color(0xFFB45309)),
                SizedBox(width: 5),
                Text(
                  'HASIL AKHIR MABAR',
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFFB45309),
                    letterSpacing: 0.5,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
          ],
          Text(
            isFinished
                ? 'Hasil Akhir & Podium'
                : (isLive ? 'Pertandingan Berlangsung' : 'Drawing & Pertandingan'),
            style: AppTextStyles.h3.copyWith(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: const Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 3),
          Text(
            isFinished
                ? 'Seluruh pertandingan pada sesi mabar ini telah selesai dimainkan dan skor akhir telah direkap secara resmi.'
                : (isDrawingReady
                    ? 'Kuota pemain telah lengkap (${session.currentPlayersCount}/${session.jumlahPemain}). Host dapat mengacak susunan tim dan memulai scoring pertandingan.'
                    : 'Sesi mabar masih membuka pendaftaran (${session.currentPlayersCount}/${session.jumlahPemain}). Masih dibutuhkan ${session.availableSlots} pemain lagi untuk memulai drawing.'),
            style: AppTextStyles.caption.copyWith(
              color: const Color(0xFF64748B),
              fontSize: 11.5,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 14),

          // 1. Jika sesi Selesai (Finished) -> Tampilan Persis Seperti di Web
          if (isFinished) ...[
            SizedBox(
              width: double.infinity,
              height: 44,
              child: ElevatedButton.icon(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => SessionMatchRecapPage(
                        sessionId: session.sessionId,
                        session: session,
                        authController: widget.authController,
                      ),
                    ),
                  );
                },
                icon: const Icon(Icons.emoji_events_rounded, size: 16),
                label: const Text(
                  'Buka Hasil Akhir & Podium',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF063B00),
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
            const SizedBox(height: 12),
            const Row(
              children: [
                Icon(Icons.check_rounded, size: 14, color: Color(0xFF16A34A)),
                SizedBox(width: 6),
                Text(
                  'Rekap skor & statistik total per set',
                  style: TextStyle(fontSize: 11, color: Color(0xFF475569)),
                ),
              ],
            ),
            const SizedBox(height: 4),
            const Row(
              children: [
                Icon(Icons.check_rounded, size: 14, color: Color(0xFF16A34A)),
                SizedBox(width: 6),
                Text(
                  'Peringkat klasemen & juara turnamen',
                  style: TextStyle(fontSize: 11, color: Color(0xFF475569)),
                ),
              ],
            ),
          ]
          // 2. Jika sesi Kuota Lengkap / Sedang Live
          else if (isDrawingReady) ...[
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
            if (canManage) const SizedBox(height: 8),
          ]
          // 3. Jika sesi Belum Lengkap
          else ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.lock_outline_rounded, size: 18, color: Color(0xFF64748B)),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Drawing dan live scoring belum dapat dimulai karena kuota pemain belum lengkap (${session.currentPlayersCount}/${session.jumlahPemain}).',
                      style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                    ),
                  ),
                ],
              ),
            ),
            if (canManage) const SizedBox(height: 10),
          ],

          // Tombol Hapus Jadwal Mabar (Shown if admin/host and not finished)
          if (canManage) ...[
            SizedBox(
              width: double.infinity,
              height: 40,
              child: OutlinedButton.icon(
                onPressed: _handleDeleteSession,
                icon: const Icon(Icons.delete_outline_rounded, size: 16),
                label: const Text(
                  'Hapus Jadwal Mabar',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                ),
                style: OutlinedButton.styleFrom(
                  backgroundColor: const Color(0xFFFEF2F2),
                  foregroundColor: const Color(0xFFDC2626),
                  side: const BorderSide(color: Color(0xFFFEE2E2)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
              ),
            ),
          ],

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
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: session.isFull ? const Color(0xFFFEF2F2) : const Color(0xFFFEF3C7),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: session.isFull ? const Color(0xFFFECACA) : const Color(0xFFFDE68A),
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        session.isFull ? Icons.check_circle_outline_rounded : Icons.person_outline_rounded,
                        size: 13,
                        color: session.isFull ? Colors.redAccent : const Color(0xFFD97706),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        session.isFull ? 'Kuota Penuh' : 'Tersisa ${session.availableSlots} Slot',
                        style: TextStyle(
                          color: session.isFull ? Colors.redAccent : const Color(0xFFD97706),
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
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
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: player.isMember ? const Color(0xFFF0FDF4) : const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(12),
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
        bg = const Color(0xFFECFCCB);
        fg = const Color(0xFF3F6212);
        border = const Color(0xFFD9F99D);
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
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
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
    if (widget.authController?.currentUser?.isAdmin == true) {
      return const SizedBox.shrink();
    }

    final statusLower = session.statusSession.trim().toLowerCase();
    final isFinished = statusLower == 'finished' ||
        statusLower == 'completed' ||
        statusLower == 'selesai';

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
          child: isFinished
              ? Container(
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFCBD5E1), width: 1.2),
                  ),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.flag_rounded, color: Color(0xFF64748B), size: 20),
                      SizedBox(width: 8),
                      Text(
                        'Sesi Mabar Telah Selesai',
                        style: TextStyle(
                          color: Color(0xFF475569),
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                )
              : isUserJoined
                  ? Container(
                      width: double.infinity,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF0FDF4),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: const Color(0xFF86EFAC), width: 1.2),
                      ),
                      child: const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 20),
                          SizedBox(width: 8),
                          Text(
                            'Kamu Sudah Terdaftar di Sesi Ini',
                            style: TextStyle(
                              color: Color(0xFF16A34A),
                              fontWeight: FontWeight.bold,
                              fontSize: 14,
                            ),
                          ),
                        ],
                      ),
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
