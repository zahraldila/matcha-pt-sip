import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../session/presentation/create_session_page.dart';
import '../data/venue_service.dart';
import '../domain/venue_model.dart';

class VenueDetailPage extends StatefulWidget {
  final int venueId;
  final VenueModel? initialVenue;
  final AuthController? authController;

  const VenueDetailPage({
    super.key,
    required this.venueId,
    this.initialVenue,
    this.authController,
  });

  @override
  State<VenueDetailPage> createState() => _VenueDetailPageState();
}

class _VenueDetailPageState extends State<VenueDetailPage> {
  final VenueService _venueService = VenueService();

  late VenueModel? _venue;
  bool _isLoading = false;
  String? _errorMessage;
  int _selectedPhotoIndex = 0;

  @override
  void initState() {
    super.initState();
    _venue = widget.initialVenue;
    _loadVenueDetail();
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

  Future<void> _loadVenueDetail() async {
    if (_venue == null) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    try {
      final freshVenue = await _venueService.getVenueById(widget.venueId);
      if (!mounted) return;
      setState(() {
        _venue = freshVenue;
        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        if (_venue == null) {
          _errorMessage = e.toString();
        }
      });
    }
  }

  void _handleCreateSessionAtVenue() {
    final user = widget.authController?.currentUser;
    if (user == null) {
      _showLoginRequiredModal();
    } else if (user.isHost) {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CreateSessionPage(
            authController: widget.authController,
            initialVenueId: _venue?.venueId,
          ),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Aktifkan Mode Host di halaman Profil untuk membuat sesi mabar.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _showLoginRequiredModal() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
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
                border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2), width: 2),
              ),
              child: const Icon(Icons.sports_tennis_rounded, color: AppColors.matchaDark, size: 28),
            ),
            const SizedBox(height: 16),
            Text(
              'Buat Sesi Mabar di Sini',
              style: AppTextStyles.h2.copyWith(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Masuk sebagai Host Game untuk membuat jadwal mabar dan mengundang pemain di venue ini.',
              textAlign: TextAlign.center,
              style: AppTextStyles.caption.copyWith(fontSize: 13, color: const Color(0xFF64748B), height: 1.4),
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
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  elevation: 0,
                ),
                child: const Text('Masuk / Daftar Akun', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final venue = _venue;

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
          'Detail Venue & Court',
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
            onPressed: _loadVenueDetail,
            tooltip: 'Perbarui data',
          ),
          IconButton(
            icon: const Icon(Icons.share_outlined, color: Color(0xFF64748B)),
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Tautan venue disalin! Siap dibagikan ke teman mabar 🎾'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
          ),
        ],
      ),
      body: _isLoading && venue == null
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : _errorMessage != null && venue == null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline_rounded, color: Colors.redAccent, size: 48),
                        const SizedBox(height: 12),
                        Text('Gagal memuat venue', style: AppTextStyles.h2),
                        const SizedBox(height: 6),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption.copyWith(color: const Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _loadVenueDetail,
                          style: ElevatedButton.styleFrom(backgroundColor: AppColors.matchaDark),
                          child: const Text('Coba Lagi', style: TextStyle(color: Colors.white)),
                        ),
                      ],
                    ),
                  ),
                )
              : venue == null
                  ? const SizedBox.shrink()
                  : RefreshIndicator(
                      onRefresh: _loadVenueDetail,
                      color: AppColors.matchaDark,
                      child: SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // 1. Venue Title & Address Header
                            _buildVenueHeader(venue),

                            const SizedBox(height: 16),

                            // 2. Featured Image & Interactive Gallery
                            _buildGallerySection(venue),

                            const SizedBox(height: 16),

                            // 3. Jam Operasi & Availability Box
                            _buildOperatingHoursBox(venue),

                            const SizedBox(height: 16),

                            // 4. Daftar Lapangan / Court
                            _buildCourtListSection(venue),

                            const SizedBox(height: 30),
                          ],
                        ),
                      ),
                    ),
    );
  }

  Widget _buildVenueHeader(VenueModel venue) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    venue.namaVenue,
                    style: AppTextStyles.h1.copyWith(
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.location_on_outlined, size: 14, color: Color(0xFF64748B)),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          '${venue.alamat ?? ""}${venue.kota != null ? ", ${venue.kota}" : ""}',
                          style: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF64748B),
                            fontSize: 12,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            ElevatedButton.icon(
              onPressed: _handleCreateSessionAtVenue,
              icon: const Icon(Icons.add_rounded, size: 16),
              label: const Text(
                'Buat Mabar',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.matchaDark,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                elevation: 0,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildGallerySection(VenueModel venue) {
    final photos = venue.photoList;
    final activePhoto = (_selectedPhotoIndex >= 0 && _selectedPhotoIndex < photos.length)
        ? photos[_selectedPhotoIndex]
        : venue.mainPhoto;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Main Featured Photo
        Container(
          height: 200,
          width: double.infinity,
          decoration: BoxDecoration(
            color: const Color(0xFF0F172A),
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          clipBehavior: Clip.antiAlias,
          child: Stack(
            fit: StackFit.expand,
            children: [
              Image.network(
                activePhoto,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => _buildPlaceholderCover(),
              ),

              // Sport Badge on Top Left
              Positioned(
                top: 12,
                left: 12,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.matchaSoftLime,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                  ),
                  child: Text(
                    venue.sportName,
                    style: const TextStyle(
                      color: AppColors.matchaDark,
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),

              // Photo Count Badge on Bottom Right
              if (photos.length > 1)
                Positioned(
                  bottom: 12,
                  right: 12,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.65),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.camera_alt_rounded, size: 12, color: Color(0xFFA8E63A)),
                        const SizedBox(width: 4),
                        Text(
                          '${photos.length} Foto Suasana',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),

        // Gallery Thumbnails Row (if > 1 photo)
        if (photos.length > 1) ...[
          const SizedBox(height: 10),
          SizedBox(
            height: 60,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: photos.length,
              itemBuilder: (context, index) {
                final photoUrl = photos[index];
                final isSelected = _selectedPhotoIndex == index;

                return GestureDetector(
                  onTap: () => setState(() => _selectedPhotoIndex = index),
                  child: Container(
                    width: 80,
                    margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                        width: isSelected ? 2 : 1,
                      ),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        Image.network(
                          photoUrl,
                          fit: BoxFit.cover,
                          errorBuilder: (_, _, _) => Container(color: const Color(0xFFE2E8F0)),
                        ),
                        if (index == 0)
                          Positioned(
                            bottom: 2,
                            left: 2,
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                              decoration: BoxDecoration(
                                color: AppColors.matchaDark,
                                borderRadius: BorderRadius.circular(3),
                              ),
                              child: const Text(
                                'Cover',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 8,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildOperatingHoursBox(VenueModel venue) {
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
            'Jam Operasi & Availability',
            style: AppTextStyles.h3.copyWith(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: const Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 12),

          // Operating Hours Pill Box
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Jam Operasional Reguler:',
                  style: TextStyle(color: Color(0xFF64748B), fontSize: 11),
                ),
                const SizedBox(height: 2),
                Text(
                  venue.jamOperasional ?? '06:00 - 23:00 WIB',
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),

          // Operational Notes (Amber alert)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFFFFBEB),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFFDE68A)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(Icons.info_outline_rounded, size: 14, color: Color(0xFFD97706)),
                    SizedBox(width: 6),
                    Text(
                      'Catatan Khusus & Ketentuan Operasional:',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFFB45309),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  venue.catatan ?? 'Sesuai jadwal ketersediaan lapangan reguler.',
                  style: const TextStyle(
                    fontSize: 11,
                    color: Color(0xFF92400E),
                    height: 1.3,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // PIC & Pengelola
          if (venue.namaPic != null && venue.namaPic!.isNotEmpty) ...[
            Row(
              children: [
                const Icon(Icons.person_outline_rounded, size: 16, color: Color(0xFF64748B)),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('PIC / Pengelola:', style: TextStyle(fontSize: 10, color: Color(0xFF64748B))),
                    Text(
                      '${venue.namaPic!} ${venue.noWhatsapp != null ? "(${venue.noWhatsapp})" : ""}',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 12),
          ],

          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 10),

          // Fasilitas Venue
          Text(
            'Fasilitas Venue',
            style: AppTextStyles.caption.copyWith(
              fontWeight: FontWeight.bold,
              color: const Color(0xFF0F172A),
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 6),
          ...venue.facilitiesList.map(
            (facility) => Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Row(
                children: [
                  const Icon(Icons.check_rounded, size: 14, color: AppColors.matchaDark),
                  const SizedBox(width: 6),
                  Text(
                    facility,
                    style: const TextStyle(fontSize: 11, color: Color(0xFF475569)),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCourtListSection(VenueModel venue) {
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
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Daftar Lapangan / Court',
                    style: AppTextStyles.h3.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 2),
                  const Text(
                    'Daftar court aktif dan jenis arena lapangan.',
                    style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 14),

          if (venue.courts.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: const Center(
                child: Text(
                  'Belum ada court yang terdaftar untuk venue ini.',
                  style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                ),
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: venue.courts.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final court = venue.courts[index];
                final isTennis = court.sportId == 2;

                return Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(
                            court.namaCourt,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF0F172A),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: isTennis ? const Color(0xFFEFF6FF) : AppColors.matchaSoftLime,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              isTennis ? 'Tennis' : 'Padel',
                              style: TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.bold,
                                color: isTennis ? const Color(0xFF1D4ED8) : AppColors.matchaDark,
                              ),
                            ),
                          ),
                          const Spacer(),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF0FDF4),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(color: const Color(0xFFBBF7D0)),
                            ),
                            child: Text(
                              court.statusKetersediaan ?? 'Available',
                              style: const TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF16A34A),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Tipe: ${court.tipeCourt ?? 'Outdoor'}',
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                          ),
                          Text(
                            (court.hargaPerJam ?? 0) > 0
                                ? 'Rp ${((court.hargaPerJam ?? 0) / 1000).toStringAsFixed(0)}.000/jam'
                                : 'Siap Pakai',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: AppColors.matchaDark,
                            ),
                          ),
                        ],
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

  Widget _buildPlaceholderCover() {
    return Container(
      color: const Color(0xFF063B00),
      child: const Center(
        child: Icon(Icons.sports_tennis_rounded, color: Color(0xFFA8E63A), size: 36),
      ),
    );
  }
}
