import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../session/presentation/create_session_page.dart';
import '../data/venue_service.dart';
import '../domain/venue_model.dart';
import 'create_court_page.dart';
import 'edit_venue_page.dart';

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
  final ImagePicker _imagePicker = ImagePicker();

  late VenueModel? _venue;
  bool _isLoading = false;
  bool _isUploadingPhoto = false;
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

  Future<void> _pickAndUploadPhoto(VenueModel venue, ImageSource source) async {
    try {
      final XFile? picked = await _imagePicker.pickImage(
        source: source,
        maxWidth: 1600,
        maxHeight: 1200,
        imageQuality: 85,
      );
      if (picked == null) return;

      setState(() => _isUploadingPhoto = true);
      final bytes = await picked.readAsBytes();

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Row(
            children: [
              SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              ),
              SizedBox(width: 12),
              Text('Mengunggah foto venue...'),
            ],
          ),
          duration: Duration(seconds: 4),
          behavior: SnackBarBehavior.floating,
        ),
      );

      final uploadedUrl = await _venueService.uploadVenuePhoto(bytes, picked.name);

      final currentList = List<String>.from(venue.rawPhotoList);
      currentList.add(uploadedUrl);

      await _venueService.updateVenuePhotos(
        venueId: venue.venueId,
        photos: currentList,
      );

      await _loadVenueDetail();

      if (!mounted) return;
      setState(() {
        _isUploadingPhoto = false;
        _selectedPhotoIndex = currentList.length - 1;
      });

      ScaffoldMessenger.of(context).hideCurrentSnackBar();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Foto venue berhasil ditambahkan!'),
          backgroundColor: Color(0xFF047857),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isUploadingPhoto = false);
      ScaffoldMessenger.of(context).hideCurrentSnackBar();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal mengunggah foto: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _deletePhoto(VenueModel venue, int index) async {
    try {
      final currentList = List<String>.from(venue.rawPhotoList);
      if (index < 0 || index >= currentList.length) return;

      final bool? confirm = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text('Hapus Foto Ini?', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          content: const Text('Foto ini akan dihapus dari galeri venue.', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Batal', style: TextStyle(color: Color(0xFF64748B))),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.redAccent,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: const Text('Hapus'),
            ),
          ],
        ),
      );

      if (confirm != true) return;

      currentList.removeAt(index);
      await _venueService.updateVenuePhotos(venueId: venue.venueId, photos: currentList);
      await _loadVenueDetail();

      if (!mounted) return;
      setState(() {
        _selectedPhotoIndex = 0;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Foto berhasil dihapus.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal menghapus foto: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _setAsCover(VenueModel venue, int index) async {
    try {
      final currentList = List<String>.from(venue.rawPhotoList);
      if (index <= 0 || index >= currentList.length) return;

      final selected = currentList.removeAt(index);
      currentList.insert(0, selected);

      await _venueService.updateVenuePhotos(venueId: venue.venueId, photos: currentList);
      await _loadVenueDetail();

      if (!mounted) return;
      setState(() {
        _selectedPhotoIndex = 0;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Foto berhasil dijadikan Cover Utama!'),
          backgroundColor: Color(0xFF047857),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal mengubah cover: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _showManagePhotosModal(VenueModel venue) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            final rawPhotos = _venue?.rawPhotoList ?? venue.rawPhotoList;
            final displayPhotos = _venue?.photoList ?? venue.photoList;

            return Container(
              height: MediaQuery.of(context).size.height * 0.75,
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
              child: Column(
                children: [
                  const SizedBox(height: 12),
                  Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: const Color(0xFFE2E8F0),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Kelola Foto Suasana Venue',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${rawPhotos.length} foto terdaftar untuk venue ini',
                              style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(ctx),
                          icon: const Icon(Icons.close_rounded, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                  const Divider(height: 1, color: Color(0xFFF1F5F9)),
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: _isUploadingPhoto
                                ? null
                                : () async {
                                    Navigator.pop(ctx);
                                    await _pickAndUploadPhoto(_venue ?? venue, ImageSource.gallery);
                                  },
                            icon: const Icon(Icons.photo_library_rounded, size: 16),
                            label: const Text(
                              'Pilih Galeri',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.matchaDark,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              elevation: 0,
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _isUploadingPhoto
                                ? null
                                : () async {
                                    Navigator.pop(ctx);
                                    await _pickAndUploadPhoto(_venue ?? venue, ImageSource.camera);
                                  },
                            icon: const Icon(Icons.camera_alt_rounded, size: 16),
                            label: const Text(
                              'Ambil Kamera',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                            ),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: AppColors.matchaDark,
                              side: const BorderSide(color: AppColors.matchaDark),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: rawPhotos.isEmpty
                        ? Center(
                            child: Padding(
                              padding: const EdgeInsets.all(32),
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.add_photo_alternate_outlined, size: 48, color: Colors.grey.shade400),
                                  const SizedBox(height: 12),
                                  const Text(
                                    'Belum ada foto yang diunggah',
                                    style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                  ),
                                  const SizedBox(height: 4),
                                  const Text(
                                    'Upload foto suasana venue agar pemain lebih tertarik mabar di sini.',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                                  ),
                                ],
                              ),
                            ),
                          )
                        : ListView.separated(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            itemCount: rawPhotos.length,
                            separatorBuilder: (_, _) => const SizedBox(height: 12),
                            itemBuilder: (context, index) {
                              final photoUrl = displayPhotos.length > index ? displayPhotos[index] : rawPhotos[index];
                              final isCover = index == 0;

                              return Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF8FAFC),
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(
                                    color: isCover ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                                    width: isCover ? 1.5 : 1,
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(10),
                                      child: Image.network(
                                        photoUrl,
                                        width: 70,
                                        height: 70,
                                        fit: BoxFit.cover,
                                        errorBuilder: (_, _, _) => Container(
                                          width: 70,
                                          height: 70,
                                          color: const Color(0xFFCBD5E1),
                                          child: const Icon(Icons.broken_image_rounded, color: Colors.white),
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
                                              Text(
                                                'Foto #${index + 1}',
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 13,
                                                  color: Color(0xFF0F172A),
                                                ),
                                              ),
                                              const SizedBox(width: 6),
                                              if (isCover)
                                                Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                  decoration: BoxDecoration(
                                                    color: AppColors.matchaSoftLime,
                                                    borderRadius: BorderRadius.circular(6),
                                                    border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                                                  ),
                                                  child: const Text(
                                                    'Cover Utama',
                                                    style: TextStyle(
                                                      fontSize: 10,
                                                      fontWeight: FontWeight.bold,
                                                      color: AppColors.matchaDark,
                                                    ),
                                                  ),
                                                ),
                                            ],
                                          ),
                                          const SizedBox(height: 6),
                                          Wrap(
                                            spacing: 6,
                                            children: [
                                              if (!isCover)
                                                InkWell(
                                                  onTap: () async {
                                                    await _setAsCover(_venue ?? venue, index);
                                                    setModalState(() {});
                                                  },
                                                  child: Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                                    decoration: BoxDecoration(
                                                      color: Colors.white,
                                                      borderRadius: BorderRadius.circular(6),
                                                      border: Border.all(color: const Color(0xFFCBD5E1)),
                                                    ),
                                                    child: const Row(
                                                      mainAxisSize: MainAxisSize.min,
                                                      children: [
                                                        Icon(Icons.star_border_rounded, size: 12, color: Color(0xFF475569)),
                                                        SizedBox(width: 3),
                                                        Text('Jadikan Cover', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF475569))),
                                                      ],
                                                    ),
                                                  ),
                                                ),
                                              InkWell(
                                                onTap: () async {
                                                  await _deletePhoto(_venue ?? venue, index);
                                                  setModalState(() {});
                                                },
                                                child: Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                                  decoration: BoxDecoration(
                                                    color: const Color(0xFFFEF2F2),
                                                    borderRadius: BorderRadius.circular(6),
                                                    border: Border.all(color: const Color(0xFFFCA5A5)),
                                                  ),
                                                  child: const Row(
                                                    mainAxisSize: MainAxisSize.min,
                                                    children: [
                                                      Icon(Icons.delete_outline_rounded, size: 12, color: Color(0xFFDC2626)),
                                                      SizedBox(width: 3),
                                                      Text('Hapus', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFFDC2626))),
                                                    ],
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
                              );
                            },
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

  Future<void> _openEditVenuePage(VenueModel venue) async {
    final updated = await Navigator.push<VenueModel>(
      context,
      MaterialPageRoute(
        builder: (_) => EditVenuePage(
          venue: venue,
          authController: widget.authController,
        ),
      ),
    );

    if (updated != null) {
      setState(() => _venue = updated);
      _loadVenueDetail();
    }
  }

  Future<void> _handleDeleteVenue(VenueModel venue) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Hapus / Nonaktifkan Venue?', style: TextStyle(fontWeight: FontWeight.bold)),
        content: Text(
          'Venue "${venue.namaVenue}" akan dihapus dari sistem. Tindakan ini hanya dapat dilakukan oleh Administrator / Pemilik Venue.',
          style: const TextStyle(fontSize: 13, color: Color(0xFF475569)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Ya, Hapus Venue'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      setState(() => _isLoading = true);
      await _venueService.deleteVenue(venue.venueId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Venue "${venue.namaVenue}" berhasil dihapus.'),
          backgroundColor: AppColors.matchaDark,
        ),
      );
      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal menghapus venue: $e'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  Widget _buildVenueHeader(VenueModel venue) {
    final user = widget.authController?.currentUser;
    final isOwner = user != null && venue.ownerUserId != null && venue.ownerUserId == user.userId;
    final isAdmin = user?.isAdmin == true || user?.isVenueOwner == true;
    final canManage = isOwner || isAdmin;

    final locationText = [
      if (venue.alamat != null && venue.alamat!.trim().isNotEmpty) venue.alamat!.trim(),
      if (venue.kota != null && venue.kota!.trim().isNotEmpty) venue.kota!.trim(),
    ].join(' • ');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Title & Location
        Text(
          venue.namaVenue,
          style: AppTextStyles.h1.copyWith(
            fontSize: 24,
            fontWeight: FontWeight.w900,
            color: const Color(0xFF0F172A),
            height: 1.2,
          ),
        ),
        const SizedBox(height: 4),
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            const Icon(Icons.location_on_rounded, size: 14, color: Color(0xFF94A3B8)),
            const SizedBox(width: 4),
            Expanded(
              child: Text(
                locationText.isNotEmpty ? locationText : 'Lokasi belum ditentukan',
                style: AppTextStyles.caption.copyWith(
                  color: const Color(0xFF64748B),
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),

        // Host Action Button (Buat Mabar di Sini)
        if (!canManage && (user?.isHost == true || user == null)) ...[
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _handleCreateSessionAtVenue,
              icon: const Icon(Icons.add_rounded, size: 16),
              label: const Text(
                'Buat Mabar di Sini',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.matchaDark,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 10),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                elevation: 0,
              ),
            ),
          ),
        ],

        // Admin & Owner Action Buttons matching Web (Edit Venue & Hapus / Nonaktifkan)
        if (canManage) ...[
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _openEditVenuePage(venue),
                  icon: const Icon(Icons.edit_note_rounded, size: 18, color: Color(0xFF063B00)),
                  label: const Text(
                    'Edit Venue',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 12,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    backgroundColor: Colors.white,
                    side: const BorderSide(color: Color(0xFFE2E8F0), width: 1.2),
                    padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                    elevation: 0,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _handleDeleteVenue(venue),
                  icon: const Icon(Icons.delete_rounded, size: 16, color: Color(0xFFE11D48)),
                  label: const Text(
                    'Hapus / Nonaktifkan',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 12,
                      color: Color(0xFFE11D48),
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    backgroundColor: const Color(0xFFFFF1F2),
                    side: const BorderSide(color: Color(0xFFFECDD3), width: 1.2),
                    padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                    elevation: 0,
                  ),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }

  Widget _buildGallerySection(VenueModel venue) {
    final user = widget.authController?.currentUser;
    final isOwner = user != null && venue.ownerUserId != null && venue.ownerUserId == user.userId;
    final isAdmin = user?.isAdmin == true;
    final canManage = isOwner || isAdmin;

    final photos = venue.photoList;
    final activePhoto = (_selectedPhotoIndex >= 0 && _selectedPhotoIndex < photos.length)
        ? photos[_selectedPhotoIndex]
        : venue.mainPhoto;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Main Featured Photo
        Container(
          height: 220,
          width: double.infinity,
          decoration: BoxDecoration(
            color: const Color(0xFF0F172A),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE2E8F0)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.04),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
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

              // Sport Badge Pill on Top Left (Padel / Tennis)
              Positioned(
                top: 14,
                left: 14,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                  decoration: BoxDecoration(
                    color: const Color(0xFF365314).withValues(alpha: 0.85),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0xFFBEF264).withValues(alpha: 0.4)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.2),
                        blurRadius: 4,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Text(
                    venue.sportName,
                    style: const TextStyle(
                      color: Color(0xFFD9F99D),
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.3,
                    ),
                  ),
                ),
              ),

              // Button "📷 Kelola Foto" on Top Right (for Admin & Owner)
              if (canManage)
                Positioned(
                  top: 14,
                  right: 14,
                  child: InkWell(
                    onTap: () => _showManagePhotosModal(venue),
                    borderRadius: BorderRadius.circular(20),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.65),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: Colors.white.withValues(alpha: 0.25)),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.3),
                            blurRadius: 6,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.photo_camera_rounded, size: 13, color: Color(0xFFA8E63A)),
                          SizedBox(width: 5),
                          Text(
                            'Kelola Foto',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
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
            height: 64,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: photos.length,
              itemBuilder: (context, index) {
                final photoUrl = photos[index];
                final isSelected = _selectedPhotoIndex == index;

                return GestureDetector(
                  onTap: () => setState(() => _selectedPhotoIndex = index),
                  child: Container(
                    width: 86,
                    margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                        width: isSelected ? 2.5 : 1,
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
                              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                              decoration: BoxDecoration(
                                color: AppColors.matchaDark,
                                borderRadius: BorderRadius.circular(4),
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
    final cleanHours = venue.jamOperasional?.isNotEmpty == true
        ? (venue.jamOperasional!.contains('WIB') ? venue.jamOperasional! : '${venue.jamOperasional!} WIB')
        : '08:00 – 22:00 WIB';

    return Column(
      children: [
        // 1. Jam Operasi & Availability Card
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE2E8F0)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.02),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Jam Operasi & Availability',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 12),

              // Jam Operasional Reguler Box
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Jam Operasional Reguler:',
                      style: TextStyle(color: Color(0xFF64748B), fontSize: 11, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      cleanHours,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),

              // Catatan Khusus & Ketentuan Operasional Box (Amber Alert)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFFBEB),
                  borderRadius: BorderRadius.circular(12),
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
                      venue.catatan?.isNotEmpty == true
                          ? venue.catatan!
                          : 'Sesuai jadwal ketersediaan lapangan reguler.',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF92400E),
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),

              // PIC / Pengelola
              const Text(
                'PIC / Pengelola:',
                style: TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 2),
              Text(
                venue.namaPic?.isNotEmpty == true ? venue.namaPic! : 'Marcello Este Camaro',
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
              ),
              const SizedBox(height: 1),
              Text(
                venue.noWhatsapp?.isNotEmpty == true ? venue.noWhatsapp! : '082119765944',
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF063B00),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // 2. Fasilitas Venue Card
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE2E8F0)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.02),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Fasilitas Venue',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 10),

              ...venue.facilitiesList.map(
                (facility) => Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    children: [
                      const Icon(Icons.check_rounded, size: 15, color: Color(0xFF063B00)),
                      const SizedBox(width: 8),
                      Text(
                        facility,
                        style: const TextStyle(fontSize: 12, color: Color(0xFF334155), fontWeight: FontWeight.w500),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildCourtListSection(VenueModel venue) {
    final user = widget.authController?.currentUser;
    final isOwner = user != null && venue.ownerUserId != null && venue.ownerUserId == user.userId;
    final isAdmin = user?.isAdmin == true;
    final canManage = isOwner || isAdmin;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
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
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Daftar Lapangan / Court',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  SizedBox(height: 2),
                  Text(
                    'Daftar court aktif dan jenis arena lapangan.',
                    style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                  ),
                ],
              ),
              if (canManage)
                ElevatedButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => CreateCourtPage(
                          venue: venue,
                          authController: widget.authController,
                        ),
                      ),
                    ).then((result) {
                      if (result == true) {
                        _loadVenueDetail();
                      }
                    });
                  },
                  icon: const Icon(Icons.add_rounded, size: 14),
                  label: const Text(
                    'Tambah Court',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFEBF8D8),
                    foregroundColor: const Color(0xFF063B00),
                    elevation: 0,
                    side: BorderSide(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
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
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: isTennis
                                  ? const Color(0xFFA8E63A).withValues(alpha: 0.25)
                                  : const Color(0xFF7FAF25).withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFF7FAF25).withValues(alpha: 0.35)),
                            ),
                            child: Text(
                              isTennis ? 'Tennis' : 'Padel',
                              style: const TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF050608),
                              ),
                            ),
                          ),
                          const Spacer(),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEBF8D8),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.25)),
                            ),
                            child: Text(
                              court.statusKetersediaan ?? 'Available',
                              style: const TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF063B00),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          RichText(
                            text: TextSpan(
                              text: 'Tipe: ',
                              style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                              children: [
                                TextSpan(
                                  text: court.tipeCourt ?? 'Indoor',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFF334155),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Text(
                            (court.hargaPerJam ?? 0) > 0
                                ? 'Rp ${((court.hargaPerJam ?? 0) / 1000).toStringAsFixed(0)}.000/jam'
                                : 'Siap Pakai',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF063B00),
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
