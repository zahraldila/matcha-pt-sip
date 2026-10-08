import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:gal/gal.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/utils/app_error_handler.dart';
import '../../../core/widgets/offline_state_widget.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../main/presentation/main_shell_page.dart';
import '../../session/domain/session_model.dart';
import '../data/recap_service.dart';
import '../domain/recap_models.dart';

class SessionMatchRecapPage extends StatefulWidget {
  final int sessionId;
  final SessionModel? session;
  final AuthController? authController;

  const SessionMatchRecapPage({
    super.key,
    required this.sessionId,
    this.session,
    this.authController,
  });

  @override
  State<SessionMatchRecapPage> createState() => _SessionMatchRecapPageState();
}

class _SessionMatchRecapPageState extends State<SessionMatchRecapPage> {
  final RecapService _recapService = RecapService();

  bool _isLoading = true;
  String? _errorMessage;
  SessionMatchRecapData? _data;

  // Kudos state: playerId -> Set of kudos tag names given by user
  final Map<int, Set<String>> _givenKudos = {};
  final Map<int, Map<String, int>> _kudosCounts = {};

  final List<String> _kudosOptions = const [
    '🎾 Super Forehand',
    '🛡️ Solid Defense',
    '🤝 Fun Partner',
    '💥 Killer Smash',
    '⭐ MVP Play',
    '✨ Fair Play',
  ];

  @override
  void initState() {
    super.initState();
    _loadRecap();
  }

  Future<void> _loadRecap() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final currentUserId = widget.authController?.currentUser?.userId;
      final recap = await _recapService.getSessionMatchRecap(
        widget.sessionId,
        currentUserId: currentUserId,
      );

      if (!mounted) return;
      setState(() {
        _data = recap;
        _givenKudos.clear();
        for (final entry in recap.userGivenKudos.entries) {
          _givenKudos[entry.key] = Set<String>.from(entry.value);
        }
        _kudosCounts.clear();
        for (final entry in recap.kudosMap.entries) {
          _kudosCounts[entry.key] = Map<String, int>.from(entry.value);
        }
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

  void _toggleKudos(int playerId, String playerName, String kudosTag) {
    final currentUserId = widget.authController?.currentUser?.userId;
    setState(() {
      final userTags = _givenKudos.putIfAbsent(playerId, () => <String>{});
      final playerCounts = _kudosCounts.putIfAbsent(playerId, () => <String, int>{});

      if (userTags.contains(kudosTag)) {
        userTags.remove(kudosTag);
        final currentCount = playerCounts[kudosTag] ?? 1;
        playerCounts[kudosTag] = (currentCount - 1) > 0 ? (currentCount - 1) : 0;
      } else {
        userTags.add(kudosTag);
        playerCounts[kudosTag] = (playerCounts[kudosTag] ?? 0) + 1;
        AppErrorHandler.showSuccessSnackBar(
          context,
          'Kudos "$kudosTag" berhasil diberikan ke $playerName! 👏',
        );
      }
    });

    // Panggil service untuk persistensi ke database tb_kudos
    _recapService.toggleKudos(
      sessionId: widget.sessionId,
      giverUserId: currentUserId,
      recipientPlayerId: playerId,
      recipientName: playerName,
      badge: kudosTag,
    );
  }

  void _handleBackNavigation() {
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(
        builder: (_) => MainShellPage(
          authController: widget.authController,
          initialIndex: 1, // Tab 1: Sesi Mabar
        ),
      ),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          _handleBackNavigation();
        }
      },
      child: Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          backgroundColor: Colors.white,
          elevation: 0,
          scrolledUnderElevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A), size: 20),
            onPressed: _handleBackNavigation,
          ),
          title: const Text(
            'Detail Mabar',
            style: TextStyle(
              color: Color(0xFF0F172A),
              fontWeight: FontWeight.bold,
              fontSize: 15,
            ),
          ),
        ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : _errorMessage != null && _data == null
              ? OfflineStateWidget(
                  error: _errorMessage,
                  customTitle: 'Gagal Memuat Rekap Pertandingan',
                  onRetry: _loadRecap,
                )
              : _data == null
                  ? const SizedBox.shrink()
                  : RefreshIndicator(
                      onRefresh: _loadRecap,
                      color: AppColors.matchaDark,
                      child: SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // 0. Top Action Breadcrumbs Row (Kembali ke Daftar Mabar & Bagikan)
                            _buildTopActionBreadcrumbsRow(_data!),

                            const SizedBox(height: 12),

                            // 1. Hero Winner Card
                            _buildHeroWinnerCard(_data!),

                            const SizedBox(height: 18),

                            // 2. Section 1: Riwayat Hasil Pertandingan Seluruh Ronde
                            _buildRoundsHistorySection(_data!),

                            const SizedBox(height: 18),

                            // 3. Section 2: Podium & Klasemen Akhir
                            _buildPodiumAndStandingsSection(_data!),

                            const SizedBox(height: 18),

                            // 4. Section 3: Kudos System
                            _buildKudosSection(_data!),

                            const SizedBox(height: 18),

                            // 5. Section 4: Statistik Pribadi Pemain
                            if (_data!.myStats != null) ...[
                              _buildPersonalStatsCard(_data!.myStats!),
                              const SizedBox(height: 20),
                            ],
                          ],
                        ),
                      ),
                    ),
      ),
    );
  }

  /// 0. Top Action Breadcrumbs Row (Matching Web /scoring/recap layout)
  Widget _buildTopActionBreadcrumbsRow(SessionMatchRecapData data) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        // Badges & Actions (Match Finished + Bagikan Button)
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Badge Match Finished
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: const Color(0xFFEBF8D8),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.25)),
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.check_circle_rounded, size: 11, color: Color(0xFF063B00)),
                  SizedBox(width: 3),
                  Text(
                    'Match Finished',
                    style: TextStyle(
                      color: Color(0xFF063B00),
                      fontSize: 9.5,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),

            // Button Bagikan (Dark Green with Lime Share Icon)
            GestureDetector(
              onTap: () => _showShareModal(context, data),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: const Color(0xFF063B00),
                  borderRadius: BorderRadius.circular(10),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF063B00).withValues(alpha: 0.2),
                      blurRadius: 4,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.share_rounded, size: 12, color: Color(0xFFA8E63A)),
                    SizedBox(width: 4),
                    Text(
                      'Bagikan',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  void _showShareModal(BuildContext context, SessionMatchRecapData data) {
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

              // Option 1: Share as Web Preview
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
                              final url = 'https://matcha.siproduktif.com/scoring/recap/${data.sessionId}';
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
                              final url = 'https://matcha.siproduktif.com/scoring/recap/${data.sessionId}';
                              final shareText = '🎾 Hasil Mabar: ${data.sessionName}\n🏆 Pemenang: ${data.standings.isNotEmpty ? data.standings.first.nama : '-'}\nLihat rekap selengkapnya di: $url';
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
              const SizedBox(height: 12),

              // Option 2: Share as Image / Story (Template Studio)
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFFECFDF5), Color(0xFFF7FEE7), Colors.white],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.25)),
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
                            color: const Color(0xFF063B00),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Icon(Icons.auto_awesome_rounded, size: 19, color: Color(0xFFA8E63A)),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  const Text(
                                    'Share as Image / Story',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12.5,
                                      color: Color(0xFF0F172A),
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFF063B00),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: const Text(
                                      '9:16',
                                      style: TextStyle(
                                        fontSize: 8.5,
                                        fontWeight: FontWeight.w900,
                                        color: Colors.white,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 2),
                              const Text(
                                'Ubah hasil mabar jadi kartu gambar story ala Strava/SKOR dengan foto dari galerimu!',
                                style: TextStyle(fontSize: 11, color: Color(0xFF475569), height: 1.3),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Pilih Template Story Button
                    GestureDetector(
                      onTap: () {
                        Navigator.pop(ctx);
                        _showStoryTemplateModal(context, data);
                      },
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(vertical: 10.5),
                        decoration: BoxDecoration(
                          color: const Color(0xFF063B00),
                          borderRadius: BorderRadius.circular(12),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF063B00).withValues(alpha: 0.3),
                              blurRadius: 8,
                              offset: const Offset(0, 3),
                            ),
                          ],
                        ),
                        child: const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.style_rounded, size: 15, color: Color(0xFFA8E63A)),
                            SizedBox(width: 6),
                            Text(
                              'Pilih Template Story',
                              style: TextStyle(
                                fontWeight: FontWeight.w900,
                                fontSize: 12,
                                color: Colors.white,
                              ),
                            ),
                          ],
                        ),
                      ),
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

  /// 2. Modal: Template Studio (9:16 Story Customizer)
  void _showStoryTemplateModal(BuildContext context, SessionMatchRecapData data) {
    int selectedTemplate = 0; // 0: Minimalist Podium, 1: Glass Leaderboard, 2: Match Highlights, 3: Strava Athletic
    File? pickedBgImage;
    String overlayFilter = 'contrast'; // 'contrast', 'matcha', 'clean'
    SessionPlayerStanding? selectedPlayer = data.standings.isNotEmpty ? data.standings.first : null;
    final GlobalKey storyCardKey = GlobalKey();
    bool isProcessing = false;

    Future<Uint8List?> capturePng() async {
      try {
        final boundary = storyCardKey.currentContext?.findRenderObject() as RenderRepaintBoundary?;
        if (boundary == null) return null;
        final image = await boundary.toImage(pixelRatio: 3.5);
        final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
        return byteData?.buffer.asUint8List();
      } catch (e) {
        debugPrint('Error capturing story: $e');
        return null;
      }
    }

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Container(
              height: MediaQuery.of(context).size.height * 0.92,
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              child: Column(
                children: [
                  // Top Handle bar & Header
                  Container(
                    padding: const EdgeInsets.fromLTRB(20, 12, 16, 12),
                    decoration: const BoxDecoration(
                      color: Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                      border: Border(bottom: BorderSide(color: Color(0xFFE2E8F0))),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Center(
                          child: Container(
                            width: 44,
                            height: 4.5,
                            margin: const EdgeInsets.only(bottom: 10),
                            decoration: BoxDecoration(
                              color: const Color(0xFFCBD5E1),
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                        ),
                        Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      const Text(
                                        'Select Template Story',
                                        style: TextStyle(
                                          fontSize: 15,
                                          fontWeight: FontWeight.w900,
                                          color: Color(0xFF0F172A),
                                        ),
                                      ),
                                      const SizedBox(width: 8),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFEBF8D8),
                                          borderRadius: BorderRadius.circular(20),
                                          border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                                        ),
                                        child: const Text(
                                          '9:16 HD',
                                          style: TextStyle(
                                            fontSize: 9,
                                            fontWeight: FontWeight.w900,
                                            color: Color(0xFF063B00),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 2),
                                  const Text(
                                    'Pilih template, pasang foto dokumentasi mabar dari galeri, dan unduh/bagikan.',
                                    style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                  ),
                                ],
                              ),
                            ),
                            GestureDetector(
                              onTap: () => Navigator.pop(ctx),
                              child: Container(
                                width: 32,
                                height: 32,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: Colors.white,
                                  border: Border.all(color: const Color(0xFFE2E8F0)),
                                ),
                                child: const Icon(Icons.close_rounded, size: 18, color: Color(0xFF64748B)),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  // Scrollable Workspace
                  Expanded(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // 1. Live 9:16 Story Card Preview with Navigation Chevrons
                          Center(
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                // Prev Button (<)
                                GestureDetector(
                                  onTap: () {
                                    setModalState(() {
                                      selectedTemplate = (selectedTemplate - 1 + 4) % 4;
                                    });
                                  },
                                  child: Container(
                                    width: 34,
                                    height: 34,
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      color: const Color(0xFFF1F5F9),
                                      border: Border.all(color: const Color(0xFFE2E8F0)),
                                    ),
                                    child: const Icon(Icons.chevron_left_rounded, size: 20, color: Color(0xFF334155)),
                                  ),
                                ),
                                const SizedBox(width: 8),

                                // 9:16 Card Container wrapped in RepaintBoundary for high-res screenshot capture
                                RepaintBoundary(
                                  key: storyCardKey,
                                  child: _buildStoryCardContainer(
                                    data: data,
                                    templateIdx: selectedTemplate,
                                    pickedImage: pickedBgImage,
                                    overlayFilter: overlayFilter,
                                    selectedPlayer: selectedPlayer,
                                  ),
                                ),

                                const SizedBox(width: 8),

                                // Next Button (>)
                                GestureDetector(
                                  onTap: () {
                                    setModalState(() {
                                      selectedTemplate = (selectedTemplate + 1) % 4;
                                    });
                                  },
                                  child: Container(
                                    width: 34,
                                    height: 34,
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      color: const Color(0xFFF1F5F9),
                                      border: Border.all(color: const Color(0xFFE2E8F0)),
                                    ),
                                    child: const Icon(Icons.chevron_right_rounded, size: 20, color: Color(0xFF334155)),
                                  ),
                                ),
                              ],
                            ),
                          ),

                          const SizedBox(height: 20),

                          // 2. Pilih Template Grid (2x2)
                          const Text(
                            'PILIH TEMPLATE',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF475569),
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(height: 8),
                          GridView.count(
                            crossAxisCount: 2,
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            mainAxisSpacing: 8,
                            crossAxisSpacing: 8,
                            childAspectRatio: 2.3,
                            children: [
                              _buildTemplateTabButton(
                                index: 0,
                                title: '1. Minimalist Podium',
                                subtitle: 'Bottom 3-avatar overlay',
                                isSelected: selectedTemplate == 0,
                                onTap: () => setModalState(() => selectedTemplate = 0),
                              ),
                              _buildTemplateTabButton(
                                index: 1,
                                title: '2. Glass Leaderboard',
                                subtitle: 'Tabel ranking 1st - 4th',
                                isSelected: selectedTemplate == 1,
                                onTap: () => setModalState(() => selectedTemplate = 1),
                              ),
                              _buildTemplateTabButton(
                                index: 2,
                                title: '3. Match Highlights',
                                subtitle: 'Rekap skor tiap match',
                                isSelected: selectedTemplate == 2,
                                onTap: () => setModalState(() => selectedTemplate = 2),
                              ),
                              _buildTemplateTabButton(
                                index: 3,
                                title: '4. Strava Athletic',
                                subtitle: 'Personal performance card',
                                isSelected: selectedTemplate == 3,
                                onTap: () => setModalState(() => selectedTemplate = 3),
                              ),
                            ],
                          ),

                          // 3. Player Selector (Only for Template 4 / Strava Athletic)
                          if (selectedTemplate == 3) ...[
                            const SizedBox(height: 16),
                            const Text(
                              'PILIH PEMAIN UNTUK HIGHLIGHT',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF475569),
                                letterSpacing: 0.5,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 12),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: const Color(0xFFE2E8F0)),
                              ),
                              child: DropdownButtonHideUnderline(
                                child: DropdownButton<SessionPlayerStanding>(
                                  value: selectedPlayer,
                                  isExpanded: true,
                                  icon: const Icon(Icons.arrow_drop_down_rounded, color: Color(0xFF063B00)),
                                  items: data.standings.map((p) {
                                    return DropdownMenuItem<SessionPlayerStanding>(
                                      value: p,
                                      child: Text(
                                        '#${p.rank} • ${p.nama} (${p.pointsFor} pts)',
                                        style: const TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.bold,
                                          color: Color(0xFF0F172A),
                                        ),
                                      ),
                                    );
                                  }).toList(),
                                  onChanged: (val) {
                                    if (val != null) {
                                      setModalState(() => selectedPlayer = val);
                                    }
                                  },
                                ),
                              ),
                            ),
                          ],

                          const SizedBox(height: 16),

                          // 4. Background Foto Lapangan
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
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text(
                                      'BACKGROUND FOTO LAPANGAN',
                                      style: TextStyle(
                                        fontSize: 10.5,
                                        fontWeight: FontWeight.w800,
                                        color: Color(0xFF334155),
                                        letterSpacing: 0.5,
                                      ),
                                    ),
                                    if (pickedBgImage != null)
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFEBF8D8),
                                          borderRadius: BorderRadius.circular(10),
                                          border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                                        ),
                                        child: const Text(
                                          'Foto Terpasang',
                                          style: TextStyle(
                                            fontSize: 9,
                                            fontWeight: FontWeight.bold,
                                            color: Color(0xFF063B00),
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 3),
                                const Text(
                                  'Pasang foto momen mabar dari galeri HP atau komputermu.',
                                  style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                ),
                                const SizedBox(height: 10),
                                Row(
                                  children: [
                                    Expanded(
                                      child: GestureDetector(
                                        onTap: () async {
                                          final picker = ImagePicker();
                                          final picked = await picker.pickImage(source: ImageSource.gallery);
                                          if (picked != null) {
                                            setModalState(() {
                                              pickedBgImage = File(picked.path);
                                            });
                                          }
                                        },
                                        child: Container(
                                          padding: const EdgeInsets.symmetric(vertical: 9),
                                          decoration: BoxDecoration(
                                            color: Colors.white,
                                            borderRadius: BorderRadius.circular(12),
                                            border: Border.all(color: const Color(0xFFE2E8F0)),
                                            boxShadow: [
                                              BoxShadow(
                                                color: Colors.black.withValues(alpha: 0.02),
                                                blurRadius: 4,
                                                offset: const Offset(0, 1),
                                              ),
                                            ],
                                          ),
                                          child: Row(
                                            mainAxisAlignment: MainAxisAlignment.center,
                                            children: [
                                              const Icon(Icons.camera_alt_rounded, size: 14, color: Color(0xFF063B00)),
                                              const SizedBox(width: 6),
                                              Text(
                                                pickedBgImage != null ? 'Ganti Foto Galeri' : 'Insert Photo dari Galeri',
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 11.5,
                                                  color: Color(0xFF0F172A),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ),
                                    ),
                                    if (pickedBgImage != null) ...[
                                      const SizedBox(width: 8),
                                      GestureDetector(
                                        onTap: () => setModalState(() => pickedBgImage = null),
                                        child: Container(
                                          padding: const EdgeInsets.all(9),
                                          decoration: BoxDecoration(
                                            color: const Color(0xFFFFF1F2),
                                            borderRadius: BorderRadius.circular(12),
                                            border: Border.all(color: const Color(0xFFFECDD3)),
                                          ),
                                          child: const Icon(Icons.delete_outline_rounded, size: 18, color: Color(0xFFE11D48)),
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ],
                            ),
                          ),

                          const SizedBox(height: 16),

                          // 5. Filter Gelap Overlay
                          const Text(
                            'FILTER GELAP OVERLAY',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF475569),
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              _buildFilterOptionButton(
                                label: 'Dark Contrast',
                                isSelected: overlayFilter == 'contrast',
                                onTap: () => setModalState(() => overlayFilter = 'contrast'),
                              ),
                              const SizedBox(width: 8),
                              _buildFilterOptionButton(
                                label: 'Matcha Glow',
                                isSelected: overlayFilter == 'matcha',
                                onTap: () => setModalState(() => overlayFilter = 'matcha'),
                              ),
                              const SizedBox(width: 8),
                              _buildFilterOptionButton(
                                label: 'Minimal',
                                isSelected: overlayFilter == 'clean',
                                onTap: () => setModalState(() => overlayFilter = 'clean'),
                              ),
                            ],
                          ),

                          const SizedBox(height: 22),

                          // 6. Export Actions
                          GestureDetector(
                            onTap: isProcessing
                                ? null
                                : () async {
                                    setModalState(() => isProcessing = true);
                                    try {
                                      final pngBytes = await capturePng();
                                      if (pngBytes == null) {
                                        if (context.mounted) {
                                          ScaffoldMessenger.of(context).showSnackBar(
                                            const SnackBar(content: Text('Gagal membuat gambar template story.')),
                                          );
                                        }
                                        return;
                                      }
                                      final tempDir = await getTemporaryDirectory();
                                      final fileName = 'matcha_story_${data.sessionId}_${DateTime.now().millisecondsSinceEpoch}.png';
                                      final file = File('${tempDir.path}/$fileName');
                                      await file.writeAsBytes(pngBytes);

                                      if (context.mounted) {
                                        Navigator.pop(ctx);
                                        await Share.shareXFiles(
                                          [XFile(file.path)],
                                          text: '🎾 Rekap Mabar: ${data.sessionName}\n🏆 Juara: ${data.standings.isNotEmpty ? data.standings.first.nama : '-'}\n#MatchaApp #MatchaStory',
                                        );
                                      }
                                    } catch (e) {
                                      if (context.mounted) {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(content: Text('Gagal membagikan story: $e')),
                                        );
                                      }
                                    } finally {
                                      setModalState(() => isProcessing = false);
                                    }
                                  },
                            child: Container(
                              width: double.infinity,
                              padding: const EdgeInsets.symmetric(vertical: 13),
                              decoration: BoxDecoration(
                                color: const Color(0xFF063B00),
                                borderRadius: BorderRadius.circular(16),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF063B00).withValues(alpha: 0.3),
                                    blurRadius: 10,
                                    offset: const Offset(0, 4),
                                  ),
                                ],
                              ),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  if (isProcessing)
                                    const SizedBox(
                                      width: 16,
                                      height: 16,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFA8E63A)),
                                    )
                                  else
                                    const Icon(Icons.share_rounded, size: 16, color: Color(0xFFA8E63A)),
                                  const SizedBox(width: 8),
                                  Text(
                                    isProcessing ? 'Memproses Story...' : 'Share Image / Story',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w900,
                                      fontSize: 13,
                                      color: Colors.white,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                          GestureDetector(
                            onTap: isProcessing
                                ? null
                                : () async {
                                    setModalState(() => isProcessing = true);
                                    try {
                                      final pngBytes = await capturePng();
                                      if (pngBytes == null) {
                                        if (context.mounted) {
                                          ScaffoldMessenger.of(context).showSnackBar(
                                            const SnackBar(content: Text('Gagal merender gambar template story.')),
                                          );
                                        }
                                        return;
                                      }

                                      // 1. Cek & minta izin akses foto/galeri via permission_handler
                                      bool hasAccess = false;
                                      try {
                                        if (Platform.isAndroid) {
                                          final photosStatus = await Permission.photos.status;
                                          final storageStatus = await Permission.storage.status;
                                          if (photosStatus.isGranted || storageStatus.isGranted || photosStatus.isLimited) {
                                            hasAccess = true;
                                          } else {
                                            final reqPhotos = await Permission.photos.request();
                                            final reqStorage = await Permission.storage.request();
                                            hasAccess = reqPhotos.isGranted || reqStorage.isGranted || reqPhotos.isLimited;
                                          }
                                        } else if (Platform.isIOS) {
                                          final photosStatus = await Permission.photos.status;
                                          if (photosStatus.isGranted || photosStatus.isLimited) {
                                            hasAccess = true;
                                          } else {
                                            final reqPhotos = await Permission.photos.request();
                                            hasAccess = reqPhotos.isGranted || reqPhotos.isLimited;
                                          }
                                        } else {
                                          hasAccess = await Gal.hasAccess();
                                          if (!hasAccess) {
                                            hasAccess = await Gal.requestAccess();
                                          }
                                        }
                                      } catch (permError) {
                                        debugPrint('Permission error: $permError');
                                        hasAccess = false;
                                      }

                                      if (!hasAccess) {
                                        if (context.mounted) {
                                          Navigator.pop(ctx);
                                          ScaffoldMessenger.of(context).showSnackBar(
                                            SnackBar(
                                              content: const Text('Izin penyimpanan ditolak. Silakan aktifkan izin galeri/foto di Pengaturan HP.'),
                                              backgroundColor: const Color(0xFFE11D48),
                                              behavior: SnackBarBehavior.floating,
                                              duration: const Duration(seconds: 4),
                                              action: SnackBarAction(
                                                label: 'Pengaturan',
                                                textColor: Colors.white,
                                                onPressed: () => openAppSettings(),
                                              ),
                                            ),
                                          );
                                        }
                                        return;
                                      }

                                      // 2. Simpan gambar ke Galeri HP
                                      try {
                                        await Gal.putImageBytes(
                                          pngBytes,
                                          name: 'matcha_story_${data.sessionId}_${DateTime.now().millisecondsSinceEpoch}',
                                        );
                                        if (context.mounted) {
                                          Navigator.pop(ctx);
                                          ScaffoldMessenger.of(context).showSnackBar(
                                            const SnackBar(
                                              content: Text('Gambar story berhasil tersimpan di Galeri foto HP! 🖼️✨'),
                                              backgroundColor: Color(0xFF063B00),
                                              behavior: SnackBarBehavior.floating,
                                              duration: Duration(seconds: 4),
                                            ),
                                          );
                                        }
                                      } catch (galError) {
                                        debugPrint('Gal save error: $galError');
                                      }
                                    } catch (e) {
                                      debugPrint('Render error: $e');
                                    } finally {
                                      setModalState(() => isProcessing = false);
                                    }
                                  },
                            child: Container(
                              width: double.infinity,
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: const Color(0xFFCBD5E1)),
                              ),
                              child: const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.download_rounded, size: 15, color: Color(0xFF063B00)),
                                  SizedBox(width: 6),
                                  Text(
                                    'Download PNG (1080x1920)',
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 11.5,
                                      color: Color(0xFF334155),
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
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildTemplateTabButton({
    required int index,
    required String title,
    required String subtitle,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF063B00) : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
            width: isSelected ? 1.5 : 1.0,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: const Color(0xFF063B00).withValues(alpha: 0.2),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              title,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w900,
                color: isSelected ? Colors.white : const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 1),
            Text(
              subtitle,
              style: TextStyle(
                fontSize: 9.5,
                color: isSelected ? const Color(0xFFCBD5E1) : const Color(0xFF64748B),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFilterOptionButton({
    required String label,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: isSelected ? const Color(0xFF063B00) : Colors.white,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: isSelected ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
              width: isSelected ? 1.5 : 1.0,
            ),
          ),
          child: Center(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.bold,
                color: isSelected ? Colors.white : const Color(0xFF334155),
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// Live Story 9:16 Card Container & Sub-template Renderers
  Widget _buildStoryCardContainer({
    required SessionMatchRecapData data,
    required int templateIdx,
    required File? pickedImage,
    required String overlayFilter,
    required SessionPlayerStanding? selectedPlayer,
  }) {
    // Determine Gradient Tint Overlay
    List<Color> gradientColors;
    if (overlayFilter == 'matcha') {
      gradientColors = [
        const Color(0xFF063B00).withValues(alpha: 0.88),
        Colors.black.withValues(alpha: 0.4),
        const Color(0xFF063B00).withValues(alpha: 0.95),
      ];
    } else if (overlayFilter == 'clean') {
      gradientColors = [
        Colors.black.withValues(alpha: 0.65),
        Colors.black.withValues(alpha: 0.2),
        Colors.black.withValues(alpha: 0.75),
      ];
    } else {
      // Dark contrast (default)
      gradientColors = [
        Colors.black.withValues(alpha: 0.85),
        Colors.black.withValues(alpha: 0.35),
        Colors.black.withValues(alpha: 0.92),
      ];
    }

    return Container(
      width: 220,
      height: 391, // 9:16 ratio (220 x 391.1)
      decoration: BoxDecoration(
        color: const Color(0xFF090D10),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFF1E293B)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.5),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(21),
        child: Stack(
          children: [
            // 1. Background Photo or Pattern
            if (pickedImage != null)
              Positioned.fill(
                child: Image.file(pickedImage, fit: BoxFit.cover),
              )
            else
              Positioned.fill(
                child: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [Color(0xFF0F172A), Color(0xFF020617)],
                    ),
                  ),
                ),
              ),

            // 2. Tint Overlay
            Positioned.fill(
              child: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: gradientColors,
                  ),
                ),
              ),
            ),

            // 3. Template Content
            Padding(
              padding: const EdgeInsets.all(12),
              child: _buildStoryTemplateContent(
                data: data,
                templateIdx: templateIdx,
                selectedPlayer: selectedPlayer,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStoryTemplateContent({
    required SessionMatchRecapData data,
    required int templateIdx,
    required SessionPlayerStanding? selectedPlayer,
  }) {
    switch (templateIdx) {
      case 0:
        return _buildStoryTemplatePodium(data);
      case 1:
        return _buildStoryTemplateLeaderboard(data);
      case 2:
        return _buildStoryTemplateHighlights(data);
      case 3:
        return _buildStoryTemplateStrava(data, selectedPlayer);
      default:
        return _buildStoryTemplatePodium(data);
    }
  }

  /// Template 1: Minimalist Podium (Bottom 3-avatar overlay)
  Widget _buildStoryTemplatePodium(SessionMatchRecapData data) {
    final p1 = data.standings.isNotEmpty ? data.standings[0] : null;
    final p2 = data.standings.length > 1 ? data.standings[1] : null;
    final p3 = data.standings.length > 2 ? data.standings[2] : null;

    return Column(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // Top Header
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    data.sessionName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 10.5, color: Colors.white),
                  ),
                  Text(
                    '${data.standings.length} Players • ${data.totalRounds} Rounds • ${data.scoringSystem}',
                    style: const TextStyle(fontSize: 7.5, fontWeight: FontWeight.bold, color: Color(0xFFA8E63A)),
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(6),
                border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
              ),
              child: const Text('MATCHA', style: TextStyle(fontSize: 7.5, fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: 1)),
            ),
          ],
        ),

        // Bottom Frosted Podium Box
        Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(
                color: const Color(0xFFEBF8D8).withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFA8E63A).withValues(alpha: 0.4)),
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text('🏆', style: TextStyle(fontSize: 8)),
                  SizedBox(width: 3),
                  Text('Match Results', style: TextStyle(fontSize: 7.5, fontWeight: FontWeight.w900, color: Color(0xFFA8E63A))),
                ],
              ),
            ),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: 0.65),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  // 2nd Place
                  Expanded(
                    child: p2 == null
                        ? const SizedBox()
                        : Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Stack(
                                alignment: Alignment.topRight,
                                children: [
                                  CircleAvatar(
                                    radius: 14,
                                    backgroundColor: const Color(0xFF475569),
                                    child: Text(p2.nama.isNotEmpty ? p2.nama[0] : '2', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.all(2),
                                    decoration: const BoxDecoration(shape: BoxShape.circle, color: Color(0xFFCBD5E1)),
                                    child: const Text('2', style: TextStyle(fontSize: 6, fontWeight: FontWeight.w900, color: Colors.black)),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 2),
                              Text(p2.nama, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: Colors.white)),
                              Text('${p2.matchesWon}-${p2.matchesLost}', style: const TextStyle(fontSize: 6.5, color: Color(0xFF94A3B8))),
                              Container(
                                margin: const EdgeInsets.only(top: 2),
                                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                                decoration: BoxDecoration(color: const Color(0xFF1E293B), borderRadius: BorderRadius.circular(4)),
                                child: Text('${p2.pointsFor} pts', style: const TextStyle(fontSize: 7, fontWeight: FontWeight.bold, color: Colors.white)),
                              ),
                            ],
                          ),
                  ),

                  // 1st Place (Center / Taller)
                  Expanded(
                    child: p1 == null
                        ? const SizedBox()
                        : Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Stack(
                                alignment: Alignment.topRight,
                                children: [
                                  CircleAvatar(
                                    radius: 18,
                                    backgroundColor: const Color(0xFFD97706),
                                    child: Text(p1.nama.isNotEmpty ? p1.nama[0] : '1', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
                                  ),
                                  const Text('🥇', style: TextStyle(fontSize: 10)),
                                ],
                              ),
                              const SizedBox(height: 2),
                              Text(p1.nama, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: Color(0xFFFDE047))),
                              Text('${p1.matchesWon}-${p1.matchesLost}', style: const TextStyle(fontSize: 7, color: Color(0xFFFEF08A))),
                              Container(
                                margin: const EdgeInsets.only(top: 2),
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                                decoration: BoxDecoration(color: const Color(0xFFFACC15), borderRadius: BorderRadius.circular(4)),
                                child: Text('${p1.pointsFor} pts', style: const TextStyle(fontSize: 8, fontWeight: FontWeight.w900, color: Color(0xFF78350F))),
                              ),
                            ],
                          ),
                  ),

                  // 3rd Place
                  Expanded(
                    child: p3 == null
                        ? const SizedBox()
                        : Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Stack(
                                alignment: Alignment.topRight,
                                children: [
                                  CircleAvatar(
                                    radius: 14,
                                    backgroundColor: const Color(0xFF7C2D12),
                                    child: Text(p3.nama.isNotEmpty ? p3.nama[0] : '3', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.all(2),
                                    decoration: const BoxDecoration(shape: BoxShape.circle, color: Color(0xFFFB923C)),
                                    child: const Text('3', style: TextStyle(fontSize: 6, fontWeight: FontWeight.w900, color: Colors.black)),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 2),
                              Text(p3.nama, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: Colors.white)),
                              Text('${p3.matchesWon}-${p3.matchesLost}', style: const TextStyle(fontSize: 6.5, color: Color(0xFF94A3B8))),
                              Container(
                                margin: const EdgeInsets.only(top: 2),
                                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                                decoration: BoxDecoration(color: const Color(0xFF1E293B), borderRadius: BorderRadius.circular(4)),
                                child: Text('${p3.pointsFor} pts', style: const TextStyle(fontSize: 7, fontWeight: FontWeight.bold, color: Colors.white)),
                              ),
                            ],
                          ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ],
    );
  }

  /// Template 2: Glass Leaderboard (Tabel ranking 1st - 4th)
  Widget _buildStoryTemplateLeaderboard(SessionMatchRecapData data) {
    final topPlayers = data.standings.take(4).toList();

    return Column(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // Top Header
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('MATCHA', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: Colors.white, letterSpacing: 1)),
                Text('LEADERBOARD', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 7, color: Color(0xFFA8E63A), letterSpacing: 1.5)),
              ],
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: const Color(0xFF063B00),
                borderRadius: BorderRadius.circular(6),
                border: Border.all(color: const Color(0xFFA8E63A).withValues(alpha: 0.3)),
              ),
              child: Text(data.sportName, style: const TextStyle(fontSize: 7.5, fontWeight: FontWeight.bold, color: Color(0xFFA8E63A))),
            ),
          ],
        ),

        // Glass Leaderboard Table
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: Colors.black.withValues(alpha: 0.65),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
          ),
          child: Column(
            children: [
              // Table Header
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                child: Row(
                  children: [
                    SizedBox(width: 16, child: Text('POS', style: TextStyle(fontSize: 6.5, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)))),
                    Expanded(child: Text('PLAYER', style: TextStyle(fontSize: 6.5, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)))),
                    SizedBox(width: 24, child: Text('W-L', textAlign: TextAlign.center, style: TextStyle(fontSize: 6.5, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)))),
                    SizedBox(width: 22, child: Text('DIFF', textAlign: TextAlign.center, style: TextStyle(fontSize: 6.5, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)))),
                    SizedBox(width: 24, child: Text('PTS', textAlign: TextAlign.right, style: TextStyle(fontSize: 6.5, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)))),
                  ],
                ),
              ),
              const Divider(color: Colors.white12, height: 6),

              // Rows
              ...topPlayers.asMap().entries.map((entry) {
                final idx = entry.key;
                final rp = entry.value;
                final isFirst = idx == 0;

                return Container(
                  margin: const EdgeInsets.only(bottom: 4),
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 3),
                  decoration: BoxDecoration(
                    color: isFirst ? const Color(0xFFFACC15).withValues(alpha: 0.15) : Colors.white.withValues(alpha: 0.04),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: isFirst ? const Color(0xFFFACC15).withValues(alpha: 0.3) : Colors.white.withValues(alpha: 0.05)),
                  ),
                  child: Row(
                    children: [
                      SizedBox(
                        width: 16,
                        child: Text(
                          idx == 0 ? '🥇' : idx == 1 ? '🥈' : idx == 2 ? '🥉' : '#${idx + 1}',
                          style: const TextStyle(fontSize: 8, fontWeight: FontWeight.bold),
                        ),
                      ),
                      Expanded(
                        child: Text(
                          rp.nama,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 8,
                            fontWeight: FontWeight.bold,
                            color: isFirst ? const Color(0xFFFEF08A) : Colors.white,
                          ),
                        ),
                      ),
                      SizedBox(
                        width: 24,
                        child: Text(
                          '${rp.matchesWon}-${rp.matchesLost}',
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 7.5, color: Color(0xFFCBD5E1)),
                        ),
                      ),
                      SizedBox(
                        width: 22,
                        child: Text(
                          '${rp.gameDiff >= 0 ? '+' : ''}${rp.gameDiff}',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 7.5,
                            fontWeight: FontWeight.bold,
                            color: rp.gameDiff >= 0 ? const Color(0xFFA8E63A) : const Color(0xFFFB7185),
                          ),
                        ),
                      ),
                      SizedBox(
                        width: 24,
                        child: Text(
                          '${rp.pointsFor}',
                          textAlign: TextAlign.right,
                          style: const TextStyle(fontSize: 8.5, fontWeight: FontWeight.w900, color: Colors.white),
                        ),
                      ),
                    ],
                  ),
                );
              }),
            ],
          ),
        ),

        // Footer
        Column(
          children: [
            Text(data.sessionName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: Colors.white)),
            Text('${data.venueName} • Matcha Match Arena', style: const TextStyle(fontSize: 6.5, color: Color(0xFF94A3B8))),
          ],
        ),
      ],
    );
  }

  /// Template 3: Match Highlights (Rekap skor tiap match)
  Widget _buildStoryTemplateHighlights(SessionMatchRecapData data) {
    final sampleMatches = data.rounds.expand((r) => r.matches).take(3).toList();

    return Column(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // Top Header
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(data.sessionName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: Colors.white)),
                  const Text('Match Recap Highlights', style: TextStyle(fontSize: 7, fontWeight: FontWeight.bold, color: Color(0xFFA8E63A))),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(6),
                border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
              ),
              child: Text('${data.totalRounds} Rounds', style: const TextStyle(fontSize: 7.5, fontWeight: FontWeight.bold, color: Colors.white)),
            ),
          ],
        ),

        // Match Result Cards List (with fallback if empty)
        if (sampleMatches.isNotEmpty)
          Column(
            children: sampleMatches.map((m) {
              return Container(
                margin: const EdgeInsets.only(bottom: 6),
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.65),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('Round ${m.roundNumber}', style: const TextStyle(fontSize: 7, fontWeight: FontWeight.w900, color: Color(0xFFA8E63A))),
                        Text(m.courtName.isNotEmpty ? m.courtName : 'Court 1', style: const TextStyle(fontSize: 6.5, color: Color(0xFF94A3B8))),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Expanded(
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 3),
                            decoration: BoxDecoration(
                              color: m.isSideAWinner ? const Color(0xFF063B00) : Colors.white.withValues(alpha: 0.05),
                              borderRadius: BorderRadius.circular(6),
                              border: m.isSideAWinner ? Border.all(color: const Color(0xFFA8E63A).withValues(alpha: 0.4)) : null,
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    m.sideANames.join(' & '),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(fontSize: 7, fontWeight: FontWeight.bold, color: Colors.white),
                                  ),
                                ),
                                Text(
                                  '${m.scoreA}',
                                  style: TextStyle(
                                    fontSize: 8.5,
                                    fontWeight: FontWeight.w900,
                                    color: m.isSideAWinner ? const Color(0xFFA8E63A) : const Color(0xFF94A3B8),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 3),
                            decoration: BoxDecoration(
                              color: m.isSideBWinner ? const Color(0xFF063B00) : Colors.white.withValues(alpha: 0.05),
                              borderRadius: BorderRadius.circular(6),
                              border: m.isSideBWinner ? Border.all(color: const Color(0xFFA8E63A).withValues(alpha: 0.4)) : null,
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    m.sideBNames.join(' & '),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(fontSize: 7, fontWeight: FontWeight.bold, color: Colors.white),
                                  ),
                                ),
                                Text(
                                  '${m.scoreB}',
                                  style: TextStyle(
                                    fontSize: 8.5,
                                    fontWeight: FontWeight.w900,
                                    color: m.isSideBWinner ? const Color(0xFFA8E63A) : const Color(0xFF94A3B8),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              );
            }).toList(),
          )
        else
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.65),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('MATCH SUMMARY', style: TextStyle(fontSize: 7, fontWeight: FontWeight.w900, color: Color(0xFFA8E63A), letterSpacing: 0.5)),
                    Text('${data.sportName} • ${data.scoringSystem}', style: const TextStyle(fontSize: 6.5, color: Color(0xFF94A3B8))),
                  ],
                ),
                const SizedBox(height: 8),
                if (data.standings.isNotEmpty) ...[
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFF063B00),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: const Color(0xFFA8E63A).withValues(alpha: 0.3)),
                    ),
                    child: Row(
                      children: [
                        const Text('🥇', style: TextStyle(fontSize: 9)),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            data.standings.first.nama,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 8, fontWeight: FontWeight.w900, color: Color(0xFFFEF08A)),
                          ),
                        ),
                        Text(
                          '${data.standings.first.matchesWon} Win • ${data.standings.first.pointsFor} Pts',
                          style: const TextStyle(fontSize: 7.5, fontWeight: FontWeight.bold, color: Color(0xFFA8E63A)),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 5),
                  if (data.standings.length > 1)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.05),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          const Text('🥈', style: TextStyle(fontSize: 9)),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              data.standings[1].nama,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: Colors.white),
                            ),
                          ),
                          Text(
                            '${data.standings[1].matchesWon} Win • ${data.standings[1].pointsFor} Pts',
                            style: const TextStyle(fontSize: 7.5, color: Color(0xFFCBD5E1)),
                          ),
                        ],
                      ),
                    ),
                ],
              ],
            ),
          ),

        // Footer
        Text(
          '🏆 Winner: ${data.standings.isNotEmpty ? data.standings.first.nama : '-'}',
          style: const TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: Color(0xFFA8E63A)),
        ),
      ],
    );
  }

  /// Template 4: Strava Athletic (Personal Performance Card)
  Widget _buildStoryTemplateStrava(SessionMatchRecapData data, SessionPlayerStanding? player) {
    final p = player ?? (data.standings.isNotEmpty ? data.standings.first : null);
    final playerName = p?.nama ?? 'Pemain Matcha';
    final points = p?.pointsFor ?? data.myStats?.totalPoints ?? 0;
    final totalMatches = p?.matchesPlayed ?? (p != null ? p.matchesWon + p.matchesLost : 0);
    final winRate = totalMatches > 0 && p != null ? ((p.matchesWon / totalMatches) * 100).round() : (data.myStats?.winRatePercent ?? 0);
    final wins = p?.matchesWon ?? data.myStats?.wins ?? 0;
    final losses = p?.matchesLost ?? data.myStats?.losses ?? 0;
    final duration = data.myStats?.durationPlayed ?? '45m';

    return Column(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // Top Header Strava Style
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Row(
                  children: [
                    CircleAvatar(radius: 3, backgroundColor: Color(0xFFA8E63A)),
                    SizedBox(width: 4),
                    Text('MATCHA ACTIVITY', style: TextStyle(fontSize: 7, fontWeight: FontWeight.w900, color: Color(0xFFA8E63A), letterSpacing: 1.2)),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                  decoration: BoxDecoration(color: Colors.black45, borderRadius: BorderRadius.circular(4)),
                  child: const Text('MATCHA', style: TextStyle(fontSize: 7, fontWeight: FontWeight.bold, color: Colors.white)),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                CircleAvatar(
                  radius: 12,
                  backgroundColor: const Color(0xFF059669),
                  child: Text(playerName.isNotEmpty ? playerName[0] : 'P', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(playerName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w900, color: Colors.white)),
                      Text('${data.sportName} • ${data.venueName}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 7, color: Color(0xFFCBD5E1))),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),

        // 2x2 Athletic Metrics Grid
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: Colors.black.withValues(alpha: 0.65),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
          ),
          child: Column(
            children: [
              Row(
                children: [
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(8)),
                      child: Column(
                        children: [
                          const Text('TOTAL POIN', style: TextStyle(fontSize: 6, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8), letterSpacing: 0.5)),
                          const SizedBox(height: 2),
                          Text('$points', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: Colors.white)),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      decoration: BoxDecoration(
                        color: const Color(0xFF063B00).withValues(alpha: 0.7),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFA8E63A).withValues(alpha: 0.4)),
                      ),
                      child: Column(
                        children: [
                          const Text('WIN RATE', style: TextStyle(fontSize: 6, fontWeight: FontWeight.bold, color: Color(0xFFA8E63A), letterSpacing: 0.5)),
                          const SizedBox(height: 2),
                          Text('$winRate%', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: Color(0xFFA8E63A))),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 5),
                      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(8)),
                      child: Column(
                        children: [
                          const Text('MATCH RECORD', style: TextStyle(fontSize: 6, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8), letterSpacing: 0.5)),
                          const SizedBox(height: 2),
                          Text('${wins}W - ${losses}L', style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Colors.white)),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 5),
                      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(8)),
                      child: Column(
                        children: [
                          const Text('DURASI MAIN', style: TextStyle(fontSize: 6, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8), letterSpacing: 0.5)),
                          const SizedBox(height: 2),
                          Text(duration, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Colors.white)),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),

        // Footer
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text('MATCHA Tennis & Padel', style: TextStyle(fontSize: 6.5, color: Color(0xFF94A3B8))),
            Text('${DateTime.now().day}/${DateTime.now().month}/${DateTime.now().year}', style: const TextStyle(fontSize: 6.5, fontWeight: FontWeight.bold, color: Color(0xFFA8E63A))),
          ],
        ),
      ],
    );
  }

  /// 1. Hero Winner Card (matching web gold gradient banner)
  Widget _buildHeroWinnerCard(SessionMatchRecapData data) {
    final winner = data.standings.isNotEmpty ? data.standings.first : null;
    final winnerName = winner?.nama ?? 'Juara';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFFFFFBEB),
            Color(0xFFFEF3C7),
            Color(0xFFFFFDF5),
          ],
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFFDE68A), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFD97706).withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Juara 1 Pill
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
            decoration: BoxDecoration(
              color: const Color(0xFFFEF9C3),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFFACC15), width: 1.2),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text('🏆', style: TextStyle(fontSize: 14)),
                const SizedBox(width: 6),
                Text(
                  'Juara 1 - $winnerName',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 12.5,
                    color: Color(0xFF854D0E),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // Session Title
          Text(
            data.sessionName,
            textAlign: TextAlign.center,
            style: AppTextStyles.h1.copyWith(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color: const Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 6),

          // Venue & Format info
          Text(
            '${data.totalRounds} Ronde Selesai • ${data.venueName}${data.courtName != null ? ' (${data.courtName})' : ''} • ${data.sportName} • ${data.scoringSystem}',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 12,
              color: Color(0xFF64748B),
              height: 1.35,
            ),
          ),
          const SizedBox(height: 14),

          // Badges Row
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              _buildSummaryPill('👥 ${data.totalPlayers} Peserta'),
              _buildSummaryPill('🔄 ${data.totalRounds} Ronde'),
              _buildSummaryPill('✨ Rekap Final Selesai', isGreen: true),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryPill(String label, {bool isGreen = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: isGreen ? const Color(0xFFF0FDF4) : Colors.white.withValues(alpha: 0.9),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isGreen ? const Color(0xFF86EFAC) : const Color(0xFFE2E8F0),
        ),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: isGreen ? const Color(0xFF16A34A) : const Color(0xFF475569),
        ),
      ),
    );
  }

  /// 2. Section: Riwayat Hasil Pertandingan Seluruh Ronde
  Widget _buildRoundsHistorySection(SessionMatchRecapData data) {
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
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(7),
                decoration: BoxDecoration(
                  color: AppColors.matchaSoftLime,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.history_rounded, size: 18, color: AppColors.matchaDark),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Riwayat Hasil Pertandingan Seluruh Ronde',
                      style: AppTextStyles.h3.copyWith(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Rekap hasil skor tiap ronde yang telah di-scoring dalam sesi ini',
                      style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // List of Round Cards
          if (data.rounds.isEmpty)
            Container(
              padding: const EdgeInsets.all(16),
              alignment: Alignment.center,
              child: const Text('Belum ada data ronde pertandingan.', style: TextStyle(color: Color(0xFF64748B), fontSize: 12)),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: data.rounds.length,
              separatorBuilder: (context, index) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final round = data.rounds[index];
                return _buildSingleRoundCard(round);
              },
            ),
        ],
      ),
    );
  }

  Widget _buildSingleRoundCard(SessionRoundRecapItem round) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Round Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Round ${round.roundNumber}',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 13,
                  color: Color(0xFF0F172A),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFF0FDF4),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFBBF7D0)),
                ),
                child: const Text(
                  'Selesai',
                  style: TextStyle(
                    color: Color(0xFF16A34A),
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Matches in this round
          ...round.matches.map((m) => _buildMatchRow(m)),
        ],
      ),
    );
  }

  Widget _buildMatchRow(SessionMatchItem match) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFF1F5F9)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Side A
          Row(
            children: [
              Icon(
                match.isSideAWinner ? Icons.check_circle_rounded : Icons.circle_outlined,
                size: 14,
                color: match.isSideAWinner ? const Color(0xFF16A34A) : const Color(0xFF94A3B8),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  match.sideANames.join(' & '),
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: match.isSideAWinner ? FontWeight.bold : FontWeight.w600,
                    color: match.isSideAWinner ? const Color(0xFF0F172A) : const Color(0xFF475569),
                  ),
                ),
              ),
              if (match.isSideAWinner) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCFCE7),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text('WIN', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: Color(0xFF15803D))),
                ),
                const SizedBox(width: 8),
              ],
              Text(
                '${match.scoreA}',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                  color: match.isSideAWinner ? const Color(0xFF15803D) : const Color(0xFF64748B),
                ),
              ),
            ],
          ),
          const Divider(height: 10, color: Color(0xFFF1F5F9)),

          // Side B
          Row(
            children: [
              Icon(
                match.isSideBWinner ? Icons.check_circle_rounded : Icons.circle_outlined,
                size: 14,
                color: match.isSideBWinner ? const Color(0xFF16A34A) : const Color(0xFF94A3B8),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  match.sideBNames.join(' & '),
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: match.isSideBWinner ? FontWeight.bold : FontWeight.w600,
                    color: match.isSideBWinner ? const Color(0xFF0F172A) : const Color(0xFF475569),
                  ),
                ),
              ),
              if (match.isSideBWinner) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCFCE7),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text('WIN', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: Color(0xFF15803D))),
                ),
                const SizedBox(width: 8),
              ],
              Text(
                '${match.scoreB}',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                  color: match.isSideBWinner ? const Color(0xFF15803D) : const Color(0xFF64748B),
                ),
              ),
            ],
          ),

          if (match.setDetails.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              match.setDetails,
              style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
            ),
          ],
        ],
      ),
    );
  }

  /// 3. Section: Podium & Klasemen Akhir
  Widget _buildPodiumAndStandingsSection(SessionMatchRecapData data) {
    final standings = data.standings;

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
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(7),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.emoji_events_rounded, size: 18, color: Color(0xFFD97706)),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Podium & Klasemen Akhir',
                      style: AppTextStyles.h3.copyWith(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Distribusi skor: Match Menang -> Total Game -> Selisih Game',
                      style: TextStyle(fontSize: 10.5, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Visual Podium for Top 3
          if (standings.isNotEmpty) _buildPodiumVisual(standings),

          const SizedBox(height: 20),

          // Table Ranking & Statistik
          const Text(
            'RANKING & STATISTIK',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: Color(0xFF64748B),
              letterSpacing: 0.5,
            ),
          ),
          const SizedBox(height: 10),

          // Ranking Rows
          ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: standings.length,
            separatorBuilder: (context, index) => const Divider(height: 1, color: Color(0xFFF1F5F9)),
            itemBuilder: (context, index) {
              final player = standings[index];
              return _buildStandingRow(player);
            },
          ),
        ],
      ),
    );
  }

  Widget _buildPodiumVisual(List<SessionPlayerStanding> standings) {
    final first = standings.isNotEmpty ? standings[0] : null;
    final second = standings.length > 1 ? standings[1] : null;
    final third = standings.length > 2 ? standings[2] : null;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        // 2nd Place (Silver - Left)
        if (second != null)
          Expanded(
            child: Column(
              children: [
                _buildPodiumAvatar(second.foto, second.nama, '🥈'),
                const SizedBox(height: 6),
                Text(
                  second.nama,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF0F172A)),
                ),
                Text(
                  '${second.matchesWon} Win • ${second.gamesWon} Games',
                  style: const TextStyle(fontSize: 9.5, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 6),
                Container(
                  height: 75,
                  decoration: const BoxDecoration(
                    color: Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
                  ),
                  alignment: Alignment.center,
                  child: const Text('2nd', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: Color(0xFF475569))),
                ),
              ],
            ),
          )
        else
          const Spacer(),

        const SizedBox(width: 8),

        // 1st Place (Gold - Center)
        if (first != null)
          Expanded(
            child: Column(
              children: [
                _buildPodiumAvatar(first.foto, first.nama, '👑', isGold: true),
                const SizedBox(height: 6),
                Text(
                  first.nama,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 12, color: Color(0xFF0F172A)),
                ),
                Text(
                  '${first.matchesWon} Win • ${first.gamesWon} Games',
                  style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w600, color: Color(0xFFD97706)),
                ),
                const SizedBox(height: 6),
                Container(
                  height: 105,
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Color(0xFFFDE047), Color(0xFFEAB308)],
                    ),
                    borderRadius: BorderRadius.vertical(top: Radius.circular(14)),
                  ),
                  alignment: Alignment.center,
                  child: const Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text('🌱', style: TextStyle(fontSize: 18)),
                      SizedBox(height: 2),
                      Text('JUARA 1', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, color: Color(0xFF713F12))),
                    ],
                  ),
                ),
              ],
            ),
          ),

        const SizedBox(width: 8),

        // 3rd Place (Bronze - Right)
        if (third != null)
          Expanded(
            child: Column(
              children: [
                _buildPodiumAvatar(third.foto, third.nama, '🥉'),
                const SizedBox(height: 6),
                Text(
                  third.nama,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF0F172A)),
                ),
                Text(
                  '${third.matchesWon} Win • ${third.gamesWon} Games',
                  style: const TextStyle(fontSize: 9.5, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 6),
                Container(
                  height: 60,
                  decoration: const BoxDecoration(
                    color: Color(0xFFFED7AA),
                    borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
                  ),
                  alignment: Alignment.center,
                  child: const Text('3rd', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: Color(0xFF9A3412))),
                ),
              ],
            ),
          )
        else
          const Spacer(),
      ],
    );
  }

  Widget _buildPodiumAvatar(String? foto, String name, String badgeEmoji, {bool isGold = false}) {
    return Stack(
      alignment: Alignment.topRight,
      clipBehavior: Clip.none,
      children: [
        Container(
          width: isGold ? 52 : 44,
          height: isGold ? 52 : 44,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
              color: isGold ? const Color(0xFFEAB308) : const Color(0xFF94A3B8),
              width: isGold ? 2.5 : 1.5,
            ),
          ),
          child: ClipOval(
            child: (foto != null && foto.isNotEmpty)
                ? Image.network(
                    foto,
                    width: isGold ? 52 : 44,
                    height: isGold ? 52 : 44,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) => Container(
                      color: AppColors.matchaSoftLime,
                      alignment: Alignment.center,
                      child: Text(name.isNotEmpty ? name[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  )
                : Container(
                    color: AppColors.matchaSoftLime,
                    alignment: Alignment.center,
                    child: Text(name.isNotEmpty ? name[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold)),
                  ),
          ),
        ),
        Positioned(
          top: -4,
          right: -4,
          child: Text(badgeEmoji, style: TextStyle(fontSize: isGold ? 16 : 13)),
        ),
      ],
    );
  }

  Widget _buildStandingRow(SessionPlayerStanding player) {
    String rankMedal = '#${player.rank}';
    if (player.rank == 1) rankMedal = '🥇 1';
    if (player.rank == 2) rankMedal = '🥈 2';
    if (player.rank == 3) rankMedal = '🥉 3';

    final isSets = _data?.isSets ?? false;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          // Rank Badge
          SizedBox(
            width: 36,
            child: Text(
              rankMedal,
              style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13, color: Color(0xFF0F172A)),
            ),
          ),
          const SizedBox(width: 8),

          // Avatar
          Container(
            width: 32,
            height: 32,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.matchaSoftLime,
            ),
            child: ClipOval(
              child: (player.foto != null && player.foto!.isNotEmpty)
                  ? Image.network(
                      player.foto!,
                      width: 32,
                      height: 32,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) => Center(
                        child: Text(player.nama.isNotEmpty ? player.nama[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11)),
                      ),
                    )
                  : Center(
                      child: Text(player.nama.isNotEmpty ? player.nama[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11)),
                    ),
            ),
          ),
          const SizedBox(width: 10),

          // Name & Level
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  player.nama,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5, color: Color(0xFF0F172A)),
                ),
                Text(
                  '${player.level} • ${player.matchesPlayed} match',
                  style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                ),
              ],
            ),
          ),

          // Stats Columns (Win, Sets?, Game, Selisih)
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              _buildStatCol('${player.matchesWon}', 'Win'),
              if (isSets) ...[
                const SizedBox(width: 8),
                _buildStatCol('${player.setsWon}', 'Sets'),
              ],
              const SizedBox(width: 8),
              _buildStatCol('${player.gamesWon}', 'Games'),
              const SizedBox(width: 8),
              _buildStatCol(
                player.gameDiff >= 0 ? '+${player.gameDiff}' : '${player.gameDiff}',
                'Selisih',
                isHighlight: true,
                isPositive: player.gameDiff >= 0,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatCol(String val, String label, {bool isHighlight = false, bool isPositive = true}) {
    return Column(
      children: [
        Text(
          val,
          style: TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 12,
            color: isHighlight
                ? (isPositive ? const Color(0xFF15803D) : const Color(0xFFDC2626))
                : const Color(0xFF0F172A),
          ),
        ),
        Text(
          label,
          style: const TextStyle(fontSize: 8.5, color: Color(0xFF94A3B8)),
        ),
      ],
    );
  }

  /// 4. Section: Kudos System (Beri Kudos untuk Seluruh Pemain)
  Widget _buildKudosSection(SessionMatchRecapData data) {
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
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(7),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF9C3),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text('🏅', style: TextStyle(fontSize: 16)),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Beri Kudos untuk Seluruh Pemain (Kudos System)',
                      style: AppTextStyles.h3.copyWith(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Apresiasi skill & sportivitas sesama pemain di lapangan',
                      style: TextStyle(fontSize: 10.5, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Player Kudos Cards
          ...data.standings.map((p) => _buildPlayerKudosCard(p)),
        ],
      ),
    );
  }

  Widget _buildPlayerKudosCard(SessionPlayerStanding player) {
    final userGiven = _givenKudos[player.playerId] ?? <String>{};

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
          // Header Player
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.matchaSoftLime,
                ),
                child: ClipOval(
                  child: (player.foto != null && player.foto!.isNotEmpty)
                      ? Image.network(player.foto!, width: 28, height: 28, fit: BoxFit.cover, errorBuilder: (context, error, stackTrace) => Center(child: Text(player.nama[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 10))))
                      : Center(child: Text(player.nama.isNotEmpty ? player.nama[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 10))),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: RichText(
                  text: TextSpan(
                    text: player.nama,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF0F172A)),
                    children: [
                      TextSpan(
                        text: ' • Juara ${player.rank} (${player.level})',
                        style: const TextStyle(fontWeight: FontWeight.normal, fontSize: 10, color: Color(0xFF64748B)),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Kudos Chips
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: _kudosOptions.map((tag) {
              final isSelected = userGiven.contains(tag);
              final kCount = _kudosCounts[player.playerId]?[tag] ?? 0;

              return GestureDetector(
                onTap: () => _toggleKudos(player.playerId, player.nama, tag),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: isSelected ? const Color(0xFFFEF3C7) : Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: isSelected ? const Color(0xFFF59E0B) : const Color(0xFFE2E8F0),
                      width: isSelected ? 1.2 : 1.0,
                    ),
                    boxShadow: isSelected
                        ? [
                            BoxShadow(
                              color: const Color(0xFFF59E0B).withValues(alpha: 0.15),
                              blurRadius: 4,
                              offset: const Offset(0, 1),
                            ),
                          ]
                        : null,
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        tag,
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: isSelected ? FontWeight.bold : FontWeight.w600,
                          color: isSelected ? const Color(0xFF92400E) : const Color(0xFF475569),
                        ),
                      ),
                      if (kCount > 0) ...[
                        const SizedBox(width: 5),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                          decoration: BoxDecoration(
                            color: isSelected ? const Color(0xFFF59E0B) : const Color(0xFFE2E8F0),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            '$kCount',
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w900,
                              color: isSelected ? Colors.white : const Color(0xFF475569),
                            ),
                          ),
                        ),
                      ],
                      if (isSelected) ...[
                        const SizedBox(width: 4),
                        const Icon(Icons.check_rounded, size: 12, color: Color(0xFFD97706)),
                      ],
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  /// 5. Section: Statistik Pribadi Pemain
  Widget _buildPersonalStatsCard(SessionPersonalStat stat) {
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
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.matchaSoftLime,
                ),
                child: ClipOval(
                  child: (stat.foto != null && stat.foto!.isNotEmpty)
                      ? Image.network(stat.foto!, width: 36, height: 36, fit: BoxFit.cover, errorBuilder: (context, error, stackTrace) => Center(child: Text(stat.nama[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold))))
                      : Center(child: Text(stat.nama.isNotEmpty ? stat.nama[0].toUpperCase() : 'P', style: const TextStyle(fontWeight: FontWeight.bold))),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      stat.nama,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A)),
                    ),
                    Text(
                      'Statistik Pertandingan • ${stat.sportName}',
                      style: const TextStyle(fontSize: 10.5, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: AppColors.matchaSoftLime,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                ),
                child: Text(
                  stat.level,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: AppColors.matchaDark,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Stat boxes
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      const Text('Total Poin', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                      const SizedBox(height: 2),
                      Text(
                        '${stat.totalPoints}',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      const Text('Win Rate', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                      const SizedBox(height: 2),
                      Text(
                        '${stat.winRatePercent}%',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF16A34A)),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      const Text('Durasi', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                      const SizedBox(height: 2),
                      Text(
                        stat.durationPlayed,
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
