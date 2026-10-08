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
    if (mounted) {
      setState(() {});
      _loadData();
    }
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
        userEmail: user.email,
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
        actions: const [],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : _errorMessage != null
              ? RefreshIndicator(
                  onRefresh: _loadData,
                  color: AppColors.matchaDark,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    child: Container(
                      alignment: Alignment.center,
                      constraints: BoxConstraints(
                        minHeight: MediaQuery.of(context).size.height * 0.75,
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: OfflineStateWidget(
                        error: _errorMessage,
                        customTitle: AppErrorHandler.isNetworkError(_errorMessage)
                            ? 'Koneksi Internet Terputus'
                            : 'Gagal Memuat Rekap',
                        customMessage: AppErrorHandler.isNetworkError(_errorMessage)
                            ? 'Tidak dapat terhubung ke server. Periksa jaringan internet Anda lalu coba lagi.'
                            : AppErrorHandler.getReadableMessage(_errorMessage),
                        onRetry: _loadData,
                      ),
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
                        // Page-level action header (like web: title + button inline)
                        _buildPageHeader(isHost),
                        const SizedBox(height: 16),

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

  Widget _buildPageHeader(bool isHost) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Match Recap & Statistik',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 2),
              Text(
                isHost && _activeTab == 'host'
                    ? 'Riwayat penyelenggaraan sesi mabar yang kamu pimpin'
                    : 'Ringkasan performa karier bertanding dan statistik kemenangan',
                style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
              ),
            ],
          ),
        ),
        if (isHost && _activeTab == 'host') ...[  
          const SizedBox(width: 10),
          GestureDetector(
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => CreateSessionPage(authController: widget.authController),
                ),
              ).then((_) => _loadData());
            },
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFF063B00),
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF063B00).withValues(alpha: 0.25),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.add_rounded, size: 13, color: Color(0xFFA8E63A)),
                  SizedBox(width: 5),
                  Text(
                    'Buat Sesi Mabar Baru',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ] else ...[
          // Tombol "Bagikan Rekap" persis seperti di matcha-pt-web
          const SizedBox(width: 10),
          GestureDetector(
            onTap: _showShareRecapModal,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFF063B00),
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF063B00).withValues(alpha: 0.25),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.ios_share_rounded, size: 13, color: Color(0xFFA8E63A)),
                  SizedBox(width: 5),
                  Text(
                    'Bagikan Rekap',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ],
    );
  }

  // ==========================================
  // SHARE MODAL (Career Recap)
  // ==========================================

  void _showShareRecapModal() {
    final career = _careerRecap;
    if (career == null) return;

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

              // Header row
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
                              Icon(Icons.ios_share_rounded, size: 12, color: Color(0xFF063B00)),
                              SizedBox(width: 4),
                              Text(
                                'Bagikan Rekap',
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
                          'Bagikan Rekap Karir Pemain',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFF0F172A),
                            letterSpacing: -0.3,
                          ),
                        ),
                        const SizedBox(height: 3),
                        const Text(
                          'Pamerkan pencapaian statistik dan performa karier bertandingmu ke teman atau media sosial!',
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
                                'Bagikan link rekap karir pemain dalam format web. Cocok untuk grup WhatsApp / Telegram.',
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
                        // Salin Link
                        Expanded(
                          child: GestureDetector(
                            onTap: () {
                              const url = 'https://matcha.siproduktif.com/player/recap';
                              Clipboard.setData(const ClipboardData(text: url));
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
                        // Bagikan Link + Teks
                        Expanded(
                          child: GestureDetector(
                            onTap: () {
                              final shareText =
                                  '🎾 *Rekap Karir Pemain: ${career.playerName}*\n'
                                  '📊 ${career.totalMatches} Match | ${career.wins}W ${career.losses}L\n'
                                  '📈 Win Rate: ${career.winRate} | Streak: ${career.streak}\n'
                                  '⏱️ Jam Bermain: ${career.totalHours}\n'
                                  'Lihat rekap selengkapnya: https://matcha.siproduktif.com/player/recap\n'
                                  '#MatchaApp #PadelTennis #PlayerStats';
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

              // Option 2: Share as Image / Story
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
                                'Ubah rekap karir jadi kartu gambar story ala Strava/SKOR dengan foto dari galerimu!',
                                style: TextStyle(fontSize: 11, color: Color(0xFF475569), height: 1.3),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    GestureDetector(
                      onTap: () {
                        Navigator.pop(ctx);
                        _showCareerStoryTemplateModal(career);
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

  /// Career Story Template Studio (9:16)
  void _showCareerStoryTemplateModal(PlayerCareerRecapData career) {
    int selectedTemplate = 0; // 0: Stats Card, 1: Athletic Dark, 2: Minimal Clean
    File? pickedBgImage;
    String overlayFilter = 'contrast'; // 'contrast', 'matcha', 'clean'
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
        debugPrint('Error capturing career story: $e');
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
                  // Header
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
                                    'Pilih template karir, pasang foto dari galeri, lalu unduh atau bagikan.',
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
                          // Live 9:16 Story Preview
                          Center(
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                GestureDetector(
                                  onTap: () => setModalState(() => selectedTemplate = (selectedTemplate - 1 + 3) % 3),
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
                                RepaintBoundary(
                                  key: storyCardKey,
                                  child: _buildCareerStoryCard(
                                    career: career,
                                    templateIdx: selectedTemplate,
                                    pickedImage: pickedBgImage,
                                    overlayFilter: overlayFilter,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                GestureDetector(
                                  onTap: () => setModalState(() => selectedTemplate = (selectedTemplate + 1) % 3),
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

                          // Template List (3 templates - stacked vertically matching web screenshot)
                          Row(
                            children: const [
                              Icon(Icons.category_rounded, size: 14, color: Color(0xFF063B00)),
                              SizedBox(width: 6),
                              Text(
                                'PILIH TEMPLATE',
                                style: TextStyle(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF0F172A),
                                  letterSpacing: 0.5,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          _buildCareerTemplateVerticalCard(
                            title: '1. Athletic Strava',
                            subtitle: 'Personal performance',
                            isSelected: selectedTemplate == 0,
                            onTap: () => setModalState(() => selectedTemplate = 0),
                          ),
                          const SizedBox(height: 8),
                          _buildCareerTemplateVerticalCard(
                            title: '2. Career Card',
                            subtitle: 'Player hero & rivals',
                            isSelected: selectedTemplate == 1,
                            onTap: () => setModalState(() => selectedTemplate = 1),
                          ),
                          const SizedBox(height: 8),
                          _buildCareerTemplateVerticalCard(
                            title: '3. Match Highlights',
                            subtitle: 'Rekap skor terakhir',
                            isSelected: selectedTemplate == 2,
                            onTap: () => setModalState(() => selectedTemplate = 2),
                          ),

                          const SizedBox(height: 16),

                          // Background Photo Picker
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
                                    Row(
                                      children: const [
                                        Icon(Icons.image_rounded, size: 14, color: Color(0xFF063B00)),
                                        SizedBox(width: 6),
                                        Text(
                                          'BACKGROUND FOTO LAPANGAN',
                                          style: TextStyle(
                                            fontSize: 10.5,
                                            fontWeight: FontWeight.w900,
                                            color: Color(0xFF334155),
                                            letterSpacing: 0.5,
                                          ),
                                        ),
                                      ],
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
                                          'Foto Terpasang ✓',
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
                                  'Pasang foto profil atau momen bermain dari galeri HP.',
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
                                            setModalState(() => pickedBgImage = File(picked.path));
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

                          // Filter Overlay
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
                              _buildCareerFilterButton(
                                label: 'Dark Contrast',
                                isSelected: overlayFilter == 'contrast',
                                onTap: () => setModalState(() => overlayFilter = 'contrast'),
                              ),
                              const SizedBox(width: 8),
                              _buildCareerFilterButton(
                                label: 'Matcha Glow',
                                isSelected: overlayFilter == 'matcha',
                                onTap: () => setModalState(() => overlayFilter = 'matcha'),
                              ),
                              const SizedBox(width: 8),
                              _buildCareerFilterButton(
                                label: 'Minimal',
                                isSelected: overlayFilter == 'clean',
                                onTap: () => setModalState(() => overlayFilter = 'clean'),
                              ),
                            ],
                          ),

                          const SizedBox(height: 22),

                          // Export: Share
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
                                      final fileName = 'matcha_career_story_${DateTime.now().millisecondsSinceEpoch}.png';
                                      final file = File('${tempDir.path}/$fileName');
                                      await file.writeAsBytes(pngBytes);
                                      if (context.mounted) {
                                        Navigator.pop(ctx);
                                        await Share.shareXFiles(
                                          [XFile(file.path)],
                                          text: '🎾 Rekap Karir: ${career.playerName} | ${career.wins}W ${career.losses}L | WR: ${career.winRate}\n#MatchaApp #MatchaStory',
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

                          // Export: Save to Storage / Galeri
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

                                      // 1. Minta izin akses foto/galeri langsung ke sistem HP
                                      bool hasAccess = false;
                                      try {
                                        if (Platform.isAndroid) {
                                          final status = await Permission.photos.request();
                                          final storage = await Permission.storage.request();
                                          hasAccess = status.isGranted || storage.isGranted || status.isLimited;
                                        } else if (Platform.isIOS) {
                                          final status = await Permission.photos.request();
                                          hasAccess = status.isGranted || status.isLimited;
                                        } else {
                                          hasAccess = await Gal.requestAccess();
                                        }
                                      } catch (_) {
                                        hasAccess = false;
                                      }

                                      if (!hasAccess) {
                                        // Pengguna memilih 'Jangan izinkan' pada dialog bawaan OS HP
                                        return;
                                      }

                                      // 2. Simpan gambar ke Galeri HP
                                      try {
                                        await Gal.putImageBytes(
                                          pngBytes,
                                          name: 'matcha_career_story_${DateTime.now().millisecondsSinceEpoch}',
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
                              padding: const EdgeInsets.symmetric(vertical: 11),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(color: const Color(0xFFE2E8F0)),
                              ),
                              child: const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.download_rounded, size: 16, color: Color(0xFF063B00)),
                                  SizedBox(width: 8),
                                  Text(
                                    'Simpan ke Galeri / Storage',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12,
                                      color: Color(0xFF0F172A),
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

  /// Builds the 9:16 career story card for a given template
  Widget _buildCareerStoryCard({
    required PlayerCareerRecapData career,
    required int templateIdx,
    File? pickedImage,
    required String overlayFilter,
  }) {
    const double cardWidth = 230;
    const double cardHeight = cardWidth * (16 / 9);

    Color overlayColor;
    switch (overlayFilter) {
      case 'matcha':
        overlayColor = const Color(0xFF063B00).withValues(alpha: 0.55);
        break;
      case 'clean':
        overlayColor = Colors.black.withValues(alpha: 0.25);
        break;
      default:
        overlayColor = Colors.black.withValues(alpha: 0.55);
    }

    return Container(
      width: cardWidth,
      height: cardHeight,
      decoration: BoxDecoration(
        color: const Color(0xFF090D10),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF1E252E), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.45),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(22.5),
        child: Stack(
          fit: StackFit.expand,
          children: [
            // Layer 1: Base background
            Container(color: const Color(0xFF090D10)),

            // Layer 2: Custom user photo if uploaded
            if (pickedImage != null)
              Image.file(
                pickedImage,
                width: cardWidth,
                height: cardHeight,
                fit: BoxFit.cover,
              ),

            // Layer 3: Contrast gradient overlay
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.black.withValues(alpha: 0.82),
                    Colors.black.withValues(alpha: 0.40),
                    Colors.black.withValues(alpha: 0.90),
                  ],
                ),
              ),
            ),

            // Layer 4: Tint filter
            if (overlayFilter != 'contrast')
              Container(color: overlayColor),

            // Layer 5: Dot Grid Pattern
            Positioned.fill(
              child: CustomPaint(
                painter: DotGridPainter(
                  dotColor: const Color(0xFFA8E63A).withValues(alpha: 0.16),
                  spacing: 14,
                  radius: 0.85,
                ),
              ),
            ),

            // Layer 6: Story template content
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
              child: templateIdx == 0
                  ? _buildAthleticStravaTemplate(career, cardWidth, cardHeight)
                  : templateIdx == 1
                      ? _buildCareerCardTemplate(career, cardWidth, cardHeight)
                      : _buildMatchHighlightsTemplate(career, cardWidth, cardHeight),
            ),
          ],
        ),
      ),
    );
  }

  /// Template 1: Athletic Strava
  Widget _buildAthleticStravaTemplate(PlayerCareerRecapData career, double w, double h) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // 1. Top Header
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Container(
                  width: 6,
                  height: 6,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: Color(0xFFA8E63A),
                  ),
                ),
                const SizedBox(width: 5),
                const Text(
                  'MATCHA ATHLETE',
                  style: TextStyle(
                    color: Color(0xFFA8E63A),
                    fontSize: 8.5,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.8,
                  ),
                ),
              ],
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.sports_tennis_rounded, size: 10, color: Color(0xFFA8E63A)),
                  SizedBox(width: 4),
                  Text(
                    'MATCHA',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 8,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 0.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),

        // Main content centered between header and footer
        const Spacer(),

        // 2. Player Row
        Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFA8E63A), width: 1.8),
              ),
              child: ClipOval(
                child: career.avatar.isNotEmpty
                    ? Image.network(
                        career.avatar,
                        width: 44,
                        height: 44,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) => _buildStoryInitial(career.playerName),
                      )
                    : _buildStoryInitial(career.playerName),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: Text(
                          career.playerName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.2,
                          ),
                        ),
                      ),
                      const SizedBox(width: 5),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                        decoration: BoxDecoration(
                          color: const Color(0xFFA8E63A),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          career.level,
                          style: const TextStyle(
                            fontSize: 7.5,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFF063B00),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  RichText(
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    text: TextSpan(
                      style: const TextStyle(fontSize: 8.5),
                      children: [
                        TextSpan(
                          text: '@${career.username.replaceAll('@', '')}',
                          style: const TextStyle(color: Color(0xFF94A3B8), fontWeight: FontWeight.w500),
                        ),
                        const TextSpan(
                          text: ' • ',
                          style: TextStyle(color: Color(0xFF64748B)),
                        ),
                        TextSpan(
                          text: career.role,
                          style: const TextStyle(color: Color(0xFFA8E63A), fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),

        const SizedBox(height: 14),

        // 3. 2x2 Stats Card
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: const Color(0xFF101418).withValues(alpha: 0.9),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFF1E252E)),
          ),
          child: Column(
            children: [
              Row(
                children: [
                  // Top-Left: WIN RATE (Lime highlight)
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
                      decoration: BoxDecoration(
                        color: const Color(0xFF072403).withValues(alpha: 0.7),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFA8E63A).withValues(alpha: 0.45)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          const Text(
                            'WIN RATE',
                            style: TextStyle(
                              fontSize: 7.5,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFFA8E63A),
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            career.winRate,
                            style: const TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                              color: Color(0xFFA8E63A),
                              height: 1.1,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  // Top-Right: MATCH RECORD
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
                      decoration: BoxDecoration(
                        color: const Color(0xFF161B22),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFF222832)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          const Text(
                            'MATCH RECORD',
                            style: TextStyle(
                              fontSize: 7.5,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF94A3B8),
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${career.wins}W – ${career.losses}L',
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w900,
                              color: Colors.white,
                              height: 1.1,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  // Bottom-Left: TOTAL TANDING
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
                      decoration: BoxDecoration(
                        color: const Color(0xFF161B22),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFF222832)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          const Text(
                            'TOTAL TANDING',
                            style: TextStyle(
                              fontSize: 7.5,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF94A3B8),
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${career.totalMatches} Match',
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w900,
                              color: Colors.white,
                              height: 1.1,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  // Bottom-Right: WAKTU MAIN
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
                      decoration: BoxDecoration(
                        color: const Color(0xFF161B22),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFF222832)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          const Text(
                            'WAKTU MAIN',
                            style: TextStyle(
                              fontSize: 7.5,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF94A3B8),
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            career.totalHours,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w900,
                              color: Colors.white,
                              height: 1.1,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              // Streak Pill Badge
              Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1A1408),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0xFFF59E0B).withValues(alpha: 0.6)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        '🔥 ${career.streak.replaceAll('🔥', '').trim()}',
                        style: const TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFFFBBF24),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),

        const Spacer(),

        // 4. Footer
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'MATCHA Tennis & Padel',
              style: TextStyle(
                fontSize: 7.5,
                fontWeight: FontWeight.w600,
                color: Color(0xFF64748B),
              ),
            ),
            Text(
              _formatCurrentDate(),
              style: const TextStyle(
                fontSize: 7.5,
                fontWeight: FontWeight.w800,
                color: Color(0xFFA8E63A),
              ),
            ),
          ],
        ),
      ],
    );
  }

  /// Template 2: Career Card
  Widget _buildCareerCardTemplate(PlayerCareerRecapData career, double w, double h) {
    final rivals = career.headToHead.take(2).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        // 1. Top Header
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.6),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
                  ),
                  child: const Icon(Icons.sports_tennis_rounded, size: 12, color: Color(0xFFA8E63A)),
                ),
                const SizedBox(width: 6),
                const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'MATCHA',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 9.5,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.6,
                      ),
                    ),
                    Text(
                      'CAREER CARD',
                      style: TextStyle(
                        color: Color(0xFFA8E63A),
                        fontSize: 7,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.8,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
              decoration: BoxDecoration(
                color: const Color(0xFF072403),
                borderRadius: BorderRadius.circular(6),
                border: Border.all(color: const Color(0xFFA8E63A).withValues(alpha: 0.4)),
              ),
              child: Text(
                career.level,
                style: const TextStyle(
                  fontSize: 8,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFFA8E63A),
                ),
              ),
            ),
          ],
        ),
        // Center main content between header and footer
        const Spacer(),

        // 2. Centered Big Avatar
        Container(
          width: 56,
          height: 56,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: const Color(0xFF2A3340), width: 2),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.5),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: ClipOval(
            child: career.avatar.isNotEmpty
                ? Image.network(
                    career.avatar,
                    width: 56,
                    height: 56,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) => _buildStoryInitial(career.playerName, size: 22),
                  )
                : _buildStoryInitial(career.playerName, size: 22),
          ),
        ),

        const SizedBox(height: 6),

        // 3. Player Name & Subtitle
        Text(
          career.playerName,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 13.5,
            fontWeight: FontWeight.w900,
            letterSpacing: -0.2,
          ),
        ),
        const SizedBox(height: 1),
        Text(
          career.role.isNotEmpty && career.role != 'Member'
              ? career.role
              : 'Personal (Non-Community)',
          style: const TextStyle(
            color: Color(0xFF94A3B8),
            fontSize: 8.5,
            fontWeight: FontWeight.w500,
          ),
        ),

        const SizedBox(height: 12),

        // 4. Career Performance & H2H Box
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(9),
          decoration: BoxDecoration(
            color: const Color(0xFF101418).withValues(alpha: 0.95),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFF1E252E)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Top: Performa Karir & Rekor
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFF0B0F13),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFF1C222B)),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'PERFORMA KARIR',
                          style: TextStyle(
                            fontSize: 7,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF94A3B8),
                            letterSpacing: 0.5,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${career.winRate} Win Rate',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFFA8E63A),
                          ),
                        ),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text(
                          'REKOR',
                          style: TextStyle(
                            fontSize: 7,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF94A3B8),
                            letterSpacing: 0.5,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${career.wins}W – ${career.losses}L',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                            color: Colors.white,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 8),

              const Text(
                'TOP RIVALS (H2H)',
                style: TextStyle(
                  fontSize: 7,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF94A3B8),
                  letterSpacing: 0.6,
                ),
              ),
              const SizedBox(height: 5),

              if (rivals.isEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF161B22),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Center(
                    child: Text(
                      'Belum ada rekor lawan',
                      style: TextStyle(fontSize: 8, color: Color(0xFF64748B)),
                    ),
                  ),
                )
              else
                ...rivals.map((h2h) => Container(
                      margin: const EdgeInsets.only(bottom: 4),
                      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5.5),
                      decoration: BoxDecoration(
                        color: const Color(0xFF161B22),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFF222933)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              h2h.opponent,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.w700,
                                color: Colors.white,
                              ),
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            '${h2h.win}W – ${h2h.lose}L',
                            style: const TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w900,
                              color: Color(0xFFA8E63A),
                            ),
                          ),
                        ],
                      ),
                    )),
            ],
          ),
        ),

        // spacer before footer
        const Spacer(),

        // 5. Footer
        const Center(
          child: Text(
            'Matcha Match Arena • Verified Player',
            style: TextStyle(
              fontSize: 7.5,
              fontWeight: FontWeight.w600,
              color: Color(0xFF64748B),
            ),
          ),
        ),
      ],
    );
  }

  /// Template 3: Match Highlights
  Widget _buildMatchHighlightsTemplate(PlayerCareerRecapData career, double w, double h) {
    final matches = career.recentMatches.take(3).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // 1. Top Header
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.6),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
                  ),
                  child: const Icon(Icons.sports_tennis_rounded, size: 12, color: Color(0xFFA8E63A)),
                ),
                const SizedBox(width: 6),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      career.playerName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const Text(
                      'Recent Match Highlights',
                      style: TextStyle(
                        color: Color(0xFFA8E63A),
                        fontSize: 7.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
              decoration: BoxDecoration(
                color: const Color(0xFF161B22),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFF262D38)),
              ),
              child: Text(
                '${career.totalMatches} Matches',
                style: const TextStyle(
                  fontSize: 8,
                  fontWeight: FontWeight.w800,
                  color: Colors.white,
                ),
              ),
            ),
          ],
        ),
        // Center main content between header and footer
        const Spacer(),

        // 2. Recent Matches List
        if (matches.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFF101418),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFF1E252E)),
            ),
            child: const Center(
              child: Text(
                'Belum ada riwayat pertandingan',
                style: TextStyle(fontSize: 9, color: Color(0xFF64748B)),
              ),
            ),
          )
        else
          ...matches.map((m) {
            final isWin = m.result.toUpperCase() == 'WIN';
            final isDraw = m.result.toUpperCase() == 'DRAW';

            return Container(
              margin: const EdgeInsets.only(bottom: 6),
              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
              decoration: BoxDecoration(
                color: const Color(0xFF101418).withValues(alpha: 0.95),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFF1E252E)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Flexible(
                        child: Text(
                          '${m.sport} • ${m.venue}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 7.5,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF94A3B8),
                          ),
                        ),
                      ),
                      const SizedBox(width: 4),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                        decoration: BoxDecoration(
                          color: isWin
                              ? const Color(0xFFA8E63A)
                              : (isDraw ? const Color(0xFF334155) : const Color(0xFFE11D48)),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          m.result,
                          style: TextStyle(
                            fontSize: 7,
                            fontWeight: FontWeight.w900,
                            color: isWin ? const Color(0xFF063B00) : Colors.white,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          'vs ${m.opponents.join(' & ')}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        m.score,
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFFA8E63A),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            );
          }),

        // spacer before footer
        const Spacer(),

        // 3. Footer
        Center(
          child: Text(
            'Record: ${career.wins}W – ${career.losses}L • Matcha App',
            style: const TextStyle(
              fontSize: 8,
              fontWeight: FontWeight.w800,
              color: Color(0xFFA8E63A),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildStoryInitial(String name, {double size = 16}) {
    final initial = name.trim().isNotEmpty ? name.trim()[0].toUpperCase() : 'M';
    return Container(
      color: const Color(0xFF063B00),
      child: Center(
        child: Text(
          initial,
          style: TextStyle(
            color: const Color(0xFFA8E63A),
            fontSize: size,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }

  String _formatCurrentDate() {
    final now = DateTime.now();
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    final day = now.day.toString().padLeft(2, '0');
    final month = months[now.month - 1];
    final year = now.year.toString();
    return '$day $month $year';
  }

  Widget _buildCareerTemplateVerticalCard({
    required String title,
    required String subtitle,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF063B00) : Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
            width: 1.5,
          ),
          boxShadow: [
            BoxShadow(
              color: isSelected
                  ? const Color(0xFF063B00).withValues(alpha: 0.18)
                  : Colors.black.withValues(alpha: 0.02),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w900,
                color: isSelected ? Colors.white : const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w500,
                color: isSelected ? const Color(0xFFA8E63A).withValues(alpha: 0.9) : const Color(0xFF64748B),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCareerFilterButton({
    required String label,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: isSelected ? const Color(0xFF063B00) : Colors.white,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: isSelected ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
            ),
          ),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: FontWeight.w700,
              color: isSelected ? Colors.white : const Color(0xFF475569),
            ),
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
          Builder(builder: (context) {
            final isFinished = statusLower.contains('complete') || statusLower.contains('finish');
            return Row(
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

                // Drawing (disabled if finished)
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: isFinished
                        ? null
                        : () {
                            final currentUserId = widget.authController?.currentUser?.userId;
                            final isSessionHost = widget.authController?.currentUser != null &&
                                (widget.authController?.currentUser?.isAdmin == true ||
                                    _hostRecap?.sessions.any((s) => s.sessionId == session.sessionId) == true);

                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => DrawingResultPage(
                                  sessionId: session.sessionId,
                                  authController: widget.authController,
                                  hostUserId: isSessionHost ? currentUserId : null,
                                  isHost: isSessionHost ? true : null,
                                ),
                              ),
                            );
                          },
                    icon: Icon(
                      Icons.shuffle_rounded,
                      size: 14,
                      color: isFinished ? const Color(0xFFCBD5E1) : const Color(0xFF475569),
                    ),
                    label: Text(
                      'Drawing',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: isFinished ? const Color(0xFFCBD5E1) : const Color(0xFF1E293B),
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      backgroundColor: isFinished ? const Color(0xFFF8FAFC) : Colors.white,
                      side: BorderSide(color: isFinished ? const Color(0xFFE2E8F0) : const Color(0xFFCBD5E1)),
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      disabledForegroundColor: const Color(0xFFCBD5E1),
                      disabledBackgroundColor: const Color(0xFFF8FAFC),
                    ),
                  ),
                ),
                const SizedBox(width: 6),

                // Scoring Live (disabled if finished)
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: isFinished
                        ? null
                        : () {
                            final currentUserId = widget.authController?.currentUser?.userId;
                            final isSessionHost = widget.authController?.currentUser != null &&
                                (widget.authController?.currentUser?.isAdmin == true ||
                                    _hostRecap?.sessions.any((s) => s.sessionId == session.sessionId) == true);

                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => MatchScoringPage(
                                  sessionId: session.sessionId,
                                  authController: widget.authController,
                                  hostUserId: currentUserId,
                                  isHost: isSessionHost ? true : null,
                                ),
                              ),
                            );
                          },
                    icon: Icon(
                      Icons.play_circle_fill_rounded,
                      size: 14,
                      color: isFinished ? const Color(0xFFCBD5E1) : const Color(0xFFA8E63A),
                    ),
                    label: const Text(
                      'Scoring Live',
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: isFinished ? const Color(0xFFF1F5F9) : const Color(0xFF063B00),
                      foregroundColor: isFinished ? const Color(0xFFCBD5E1) : Colors.white,
                      disabledBackgroundColor: const Color(0xFFF1F5F9),
                      disabledForegroundColor: const Color(0xFFCBD5E1),
                      elevation: 0,
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                ),
              ],
            );
          }),
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
                    child: ClipOval(
                      child: career.avatar.isNotEmpty
                          ? Image.network(
                              career.avatar,
                              width: 48,
                              height: 48,
                              fit: BoxFit.cover,
                              errorBuilder: (_, _, _) => Center(
                                child: Text(
                                  career.playerName.isNotEmpty ? career.playerName[0].toUpperCase() : 'U',
                                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
                                ),
                              ),
                            )
                          : Center(
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

class DotGridPainter extends CustomPainter {
  final Color dotColor;
  final double spacing;
  final double radius;

  const DotGridPainter({
    this.dotColor = const Color(0x28A8E63A),
    this.spacing = 14.0,
    this.radius = 0.85,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = dotColor;
    for (double y = spacing / 2; y < size.height; y += spacing) {
      for (double x = spacing / 2; x < size.width; x += spacing) {
        canvas.drawCircle(Offset(x, y), radius, paint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant DotGridPainter oldDelegate) =>
      oldDelegate.dotColor != dotColor || oldDelegate.spacing != spacing || oldDelegate.radius != radius;
}


