import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/app_error_handler.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../data/community_remote_data_source.dart';
import '../domain/community_model.dart';
import '../../court/data/venue_service.dart';
import '../../court/domain/venue_model.dart';

class CommunityDetailPage extends StatefulWidget {
  final CommunityModel community;
  final AuthController? authController;

  const CommunityDetailPage({
    super.key,
    required this.community,
    this.authController,
  });

  @override
  State<CommunityDetailPage> createState() => _CommunityDetailPageState();
}

class _CommunityDetailPageState extends State<CommunityDetailPage> {
  final CommunityRemoteDataSource _dataSource = CommunityRemoteDataSource();
  final VenueService _venueService = VenueService();

  late CommunityModel _community;
  List<Map<String, dynamic>> _members = [];
  List<VenueModel> _venues = [];
  bool _isLoading = false;
  bool _isActionLoading = false;

  @override
  void initState() {
    super.initState();
    _community = widget.community;
    _loadDetail();
    _loadVenues();
  }

  Future<void> _loadVenues() async {
    try {
      final venues = await _venueService.getVenues();
      if (!mounted) return;
      setState(() {
        _venues = venues;
      });
    } catch (_) {}
  }

  Future<void> _loadDetail() async {
    setState(() => _isLoading = true);
    try {
      final user = widget.authController?.currentUser;
      final result = await _dataSource.getCommunityDetail(
        _community.communityId,
        currentUserId: user?.userId,
      );

      if (!mounted) return;
      setState(() {
        _community = result['community'] as CommunityModel;
        _members = result['members'] as List<Map<String, dynamic>>;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
    }
  }

  Future<void> _handleToggleMembership() async {
    final user = widget.authController?.currentUser;
    if (user == null) {
      final loggedIn = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => LoginPage(authController: widget.authController ?? AuthController()),
        ),
      );
      if (loggedIn != true || !mounted) return;
    }

    final currentUser = widget.authController?.currentUser;
    if (currentUser == null) return;

    setState(() => _isActionLoading = true);
    final messenger = ScaffoldMessenger.of(context);

    try {
      if (_community.isMember) {
        await _dataSource.leaveCommunity(
          communityId: _community.communityId,
          userId: currentUser.userId,
        );
        messenger.showSnackBar(
          SnackBar(
            content: Text('Anda telah meninggalkan komunitas "${_community.namaCommunity}".'),
            backgroundColor: const Color(0xFF64748B),
          ),
        );
      } else {
        await _dataSource.joinCommunity(
          communityId: _community.communityId,
          userId: currentUser.userId,
          nama: currentUser.nama,
          noHp: currentUser.noHp,
          email: currentUser.email,
        );
        messenger.showSnackBar(
          SnackBar(
            content: Text('Selamat! Anda telah bergabung ke komunitas "${_community.namaCommunity}" 🎉'),
            backgroundColor: AppColors.matchaDark,
          ),
        );
      }

      await _loadDetail();
    } catch (e) {
      if (mounted) {
        AppErrorHandler.showErrorSnackBar(context, e);
      }
    } finally {
      if (mounted) setState(() => _isActionLoading = false);
    }
  }

  Future<void> _handleDeactivateOrDelete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        contentPadding: const EdgeInsets.fromLTRB(20, 20, 20, 16),
        content: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: const Color(0xFFFFF1F2),
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFFECDD3)),
              ),
              child: const Icon(
                Icons.error_outline_rounded,
                color: Color(0xFFE11D48),
                size: 22,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Hapus / Nonaktifkan Komunitas',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 6),
                  RichText(
                    text: TextSpan(
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xFF475569),
                        height: 1.4,
                      ),
                      children: [
                        const TextSpan(
                          text: 'Apakah Anda yakin ingin menghapus atau menonaktifkan komunitas ',
                        ),
                        TextSpan(
                          text: _community.namaCommunity,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        const TextSpan(
                          text:
                              '? Jika komunitas memiliki anggota terdaftar, statusnya akan dinonaktifkan (Inactive) untuk menjaga keutuhan relasi pemain.',
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        actionsPadding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
        actions: [
          OutlinedButton(
            onPressed: () => Navigator.pop(ctx, false),
            style: OutlinedButton.styleFrom(
              foregroundColor: const Color(0xFF475569),
              side: const BorderSide(color: Color(0xFFCBD5E1)),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            ),
            child: const Text('Batal', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
          ),
          ElevatedButton.icon(
            onPressed: () => Navigator.pop(ctx, true),
            icon: const Icon(Icons.delete_outline_rounded, size: 15),
            label: const Text('Ya, Hapus / Nonaktifkan', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFE11D48),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            ),
          ),
        ],
      ),
    );

    if (confirmed != true) return;
    if (!mounted) return;

    final nav = Navigator.of(context);
    setState(() => _isLoading = true);

    try {
      await _dataSource.deleteCommunity(_community.communityId);
      if (!mounted) return;
      AppErrorHandler.showSuccessSnackBar(
        context,
        'Komunitas "${_community.namaCommunity}" berhasil dihapus.',
      );
      nav.pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      AppErrorHandler.showErrorSnackBar(context, e);
    }
  }

  Future<String?> _showVenueSearchPickerModal(BuildContext context, String? currentSelected) async {
    return showModalBottomSheet<String?>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        String searchQuery = '';
        final searchCtrl = TextEditingController();
        return StatefulBuilder(
          builder: (context, setPickerState) {
            final query = searchQuery.toLowerCase().trim();
            final tokens = query.isEmpty ? <String>[] : query.split(RegExp(r'\s+')).where((t) => t.isNotEmpty).toList();

            final filteredVenues = _venues.where((v) {
              if (tokens.isEmpty) return true;
              final searchableText = [
                v.namaVenue,
                v.alamat ?? '',
                v.kota ?? '',
                v.fasilitas ?? '',
                v.catatan ?? '',
                v.namaPic ?? '',
                v.sportName,
                ...v.courts.map((c) => '${c.namaCourt} ${c.tipeCourt ?? ''}'),
              ].join(' ').toLowerCase();

              return tokens.every((token) => searchableText.contains(token));
            }).toList();

            return Container(
              height: MediaQuery.of(context).size.height * 0.78,
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
              child: Column(
                children: [
                  const SizedBox(height: 12),
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: const Color(0xFFCBD5E1),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Pilih Homebase Venue',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${_venues.length} venue aktif tersedia',
                              style: const TextStyle(
                                fontSize: 11,
                                color: Color(0xFF64748B),
                              ),
                            ),
                          ],
                        ),
                        IconButton(
                          icon: const Icon(Icons.close_rounded, color: Color(0xFF64748B), size: 22),
                          onPressed: () => Navigator.pop(context),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Container(
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: TextField(
                        controller: searchCtrl,
                        autofocus: false,
                        onChanged: (val) {
                          setPickerState(() {
                            searchQuery = val;
                          });
                        },
                        style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
                        decoration: InputDecoration(
                          hintText: 'Cari nama venue, kota, atau alamat...',
                          hintStyle: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
                          prefixIcon: const Icon(Icons.search_rounded, color: Color(0xFF64748B), size: 20),
                          suffixIcon: searchQuery.isNotEmpty
                              ? IconButton(
                                  icon: const Icon(Icons.clear_rounded, color: Color(0xFF94A3B8), size: 18),
                                  onPressed: () {
                                    searchCtrl.clear();
                                    setPickerState(() {
                                      searchQuery = '';
                                    });
                                  },
                                )
                              : null,
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.symmetric(vertical: 12, horizontal: 12),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Divider(height: 1, color: Color(0xFFF1F5F9)),
                  Expanded(
                    child: filteredVenues.isEmpty
                        ? Center(
                            child: Padding(
                              padding: const EdgeInsets.all(24),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.search_off_rounded, size: 48, color: Colors.grey.shade400),
                                  const SizedBox(height: 12),
                                  const Text(
                                    'Venue tidak ditemukan',
                                    style: TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFF0F172A),
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'Tidak ada venue yang sesuai dengan kata kunci "$searchQuery"',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
                                  ),
                                ],
                              ),
                            ),
                          )
                        : ListView.separated(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            itemCount: filteredVenues.length + 1,
                            separatorBuilder: (_, _) => const Divider(height: 1, color: Color(0xFFF1F5F9)),
                            itemBuilder: (context, index) {
                              if (index == 0) {
                                final isSelected = currentSelected == null || currentSelected.isEmpty;
                                return InkWell(
                                  onTap: () => Navigator.pop(context, ''),
                                  borderRadius: BorderRadius.circular(12),
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                                    decoration: BoxDecoration(
                                      color: isSelected ? AppColors.matchaSoftLime.withValues(alpha: 0.5) : Colors.transparent,
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Row(
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.all(10),
                                          decoration: BoxDecoration(
                                            color: isSelected ? AppColors.matchaDark : const Color(0xFFF1F5F9),
                                            borderRadius: BorderRadius.circular(10),
                                          ),
                                          child: Icon(
                                            Icons.clear_rounded,
                                            color: isSelected ? const Color(0xFFA8E63A) : const Color(0xFF64748B),
                                            size: 18,
                                          ),
                                        ),
                                        const SizedBox(width: 12),
                                        const Expanded(
                                          child: Text(
                                            'Tidak Ada / Belum Ditentukan',
                                            style: TextStyle(
                                              fontSize: 13,
                                              fontStyle: FontStyle.italic,
                                              color: Color(0xFF64748B),
                                            ),
                                          ),
                                        ),
                                        if (isSelected)
                                          const Icon(
                                            Icons.check_circle_rounded,
                                            color: AppColors.matchaDark,
                                            size: 20,
                                          ),
                                      ],
                                    ),
                                  ),
                                );
                              }

                              final venue = filteredVenues[index - 1];
                              final isSelected = currentSelected == venue.namaVenue;

                              return InkWell(
                                onTap: () => Navigator.pop(context, venue.namaVenue),
                                borderRadius: BorderRadius.circular(12),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                                  decoration: BoxDecoration(
                                    color: isSelected ? AppColors.matchaSoftLime.withValues(alpha: 0.5) : Colors.transparent,
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Row(
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.all(10),
                                        decoration: BoxDecoration(
                                          color: isSelected ? AppColors.matchaDark : const Color(0xFFF1F5F9),
                                          borderRadius: BorderRadius.circular(10),
                                        ),
                                        child: Icon(
                                          Icons.sports_tennis_rounded,
                                          color: isSelected ? const Color(0xFFA8E63A) : const Color(0xFF64748B),
                                          size: 18,
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              venue.namaVenue,
                                              style: TextStyle(
                                                fontSize: 13,
                                                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                                                color: isSelected ? AppColors.matchaDark : const Color(0xFF0F172A),
                                              ),
                                            ),
                                            const SizedBox(height: 3),
                                            Text(
                                              '${venue.kota ?? venue.alamat ?? 'Semua Lokasi'} • ${venue.courtCount} Court Tersedia',
                                              style: TextStyle(
                                                fontSize: 11,
                                                color: isSelected ? AppColors.matchaDark.withValues(alpha: 0.8) : const Color(0xFF64748B),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      if (isSelected)
                                        const Icon(
                                          Icons.check_circle_rounded,
                                          color: AppColors.matchaDark,
                                          size: 20,
                                        ),
                                    ],
                                  ),
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

  void _openEditCommunityModal() {
    final nameCtrl = TextEditingController(text: _community.namaCommunity);
    final taglineCtrl = TextEditingController(text: _community.tagline ?? '');
    final descCtrl = TextEditingController(text: _community.deskripsi ?? '');
    final cityCtrl = TextEditingController(text: _community.kotaHomebase ?? 'Bandung');
    final scheduleCtrl = TextEditingController(text: _community.jadwalRutin ?? '');
    final venueCtrl = TextEditingController(text: _community.homebaseVenue ?? '');
    String? selectedVenue = _community.homebaseVenue?.trim().isNotEmpty == true ? _community.homebaseVenue!.trim() : null;
    String selectedLevel = _community.targetLevel ?? 'All Levels';
    String selectedStatus = _community.statusKeanggotaan;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) => Container(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 16,
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
          ),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: const Color(0xFFCBD5E1),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Edit Informasi Komunitas',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close_rounded, size: 20, color: Color(0xFF64748B)),
                      onPressed: () => Navigator.pop(ctx),
                    ),
                  ],
                ),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                const SizedBox(height: 14),

                // Nama Komunitas
                const Text('Nama Komunitas *', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                const SizedBox(height: 6),
                TextField(
                  controller: nameCtrl,
                  decoration: InputDecoration(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                  ),
                ),
                const SizedBox(height: 12),

                // Tagline
                const Text('Tagline', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                const SizedBox(height: 6),
                TextField(
                  controller: taglineCtrl,
                  decoration: InputDecoration(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                  ),
                ),
                const SizedBox(height: 12),

                // Kota Homebase & Jadwal
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Kota Homebase', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                          const SizedBox(height: 6),
                          TextField(
                            controller: cityCtrl,
                            decoration: InputDecoration(
                              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Jadwal Rutin', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                          const SizedBox(height: 6),
                          TextField(
                            controller: scheduleCtrl,
                            decoration: InputDecoration(
                              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),                // Homebase Venue
                const Text('Homebase Venue / Lapangan', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                const SizedBox(height: 6),
                InkWell(
                  onTap: () async {
                    final picked = await _showVenueSearchPickerModal(context, selectedVenue);
                    if (picked != null) {
                      setModalState(() {
                        selectedVenue = picked.isEmpty ? null : picked;
                        venueCtrl.text = selectedVenue ?? '';
                        if (selectedVenue != null) {
                          final matched = _venues.where((v) => v.namaVenue == selectedVenue).firstOrNull;
                          if (matched != null && matched.kota != null && matched.kota!.isNotEmpty && cityCtrl.text.trim().isEmpty) {
                            cityCtrl.text = matched.kota!;
                          }
                        }
                      });
                    }
                  },
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: selectedVenue != null ? AppColors.matchaDark.withValues(alpha: 0.4) : const Color(0xFFCBD5E1),
                      ),
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
                            color: AppColors.matchaSoftLime,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(Icons.location_on_rounded, color: AppColors.matchaDark, size: 18),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                selectedVenue ?? 'Pilih Homebase Venue Utama...',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: selectedVenue != null ? FontWeight.w700 : FontWeight.normal,
                                  color: selectedVenue != null ? const Color(0xFF0F172A) : const Color(0xFF94A3B8),
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                              if (selectedVenue != null) ...[
                                const SizedBox(height: 2),
                                Builder(
                                  builder: (_) {
                                    final matched = _venues.where((v) => v.namaVenue == selectedVenue).firstOrNull;
                                    final sub = matched != null
                                        ? '${matched.kota ?? matched.alamat ?? 'Lokasi Terdaftar'} • ${matched.courtCount} Court Tersedia'
                                        : 'Lokasi Terdaftar';
                                    return Text(
                                      sub,
                                      style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    );
                                  },
                                ),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.all(6),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Icon(Icons.search_rounded, size: 16, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                // Deskripsi
                const Text('Deskripsi Komunitas', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                const SizedBox(height: 6),
                TextField(
                  controller: descCtrl,
                  maxLines: 3,
                  decoration: InputDecoration(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                  ),
                ),
                const SizedBox(height: 18),

                // Save button
                SizedBox(
                  width: double.infinity,
                  height: 46,
                  child: ElevatedButton(
                    onPressed: () async {
                      if (nameCtrl.text.trim().isEmpty) return;
                      final messenger = ScaffoldMessenger.of(context);
                      Navigator.pop(ctx);
                      setState(() => _isLoading = true);

                      try {
                        await _dataSource.updateCommunity(
                          communityId: _community.communityId,
                          namaCommunity: nameCtrl.text.trim(),
                          tagline: taglineCtrl.text.trim(),
                          deskripsi: descCtrl.text.trim(),
                          sport: _community.sport,
                          kotaHomebase: cityCtrl.text.trim(),
                          jadwalRutin: scheduleCtrl.text.trim(),
                          homebaseVenue: (selectedVenue != null && selectedVenue!.isNotEmpty)
                              ? selectedVenue
                              : (venueCtrl.text.trim().isEmpty ? null : venueCtrl.text.trim()),
                          targetLevel: selectedLevel,
                          statusKeanggotaan: selectedStatus,
                        );
                        if (!mounted) return;
                        messenger.showSnackBar(
                          const SnackBar(
                            content: Text('Informasi komunitas berhasil diperbarui!'),
                            backgroundColor: AppColors.matchaDark,
                          ),
                        );
                        await _loadDetail();
                      } catch (e) {
                        if (!mounted) return;
                        setState(() => _isLoading = false);
                        messenger.showSnackBar(
                          SnackBar(
                            content: Text('Gagal memperbarui komunitas: $e'),
                            backgroundColor: Colors.redAccent,
                          ),
                        );
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      elevation: 0,
                    ),
                    child: const Text('Simpan Perubahan Komunitas', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;
    final isLoggedIn = user != null;
    final isAdmin = user != null && user.isAdmin;
    final canManage = user != null && (user.isAdmin || user.userId == _community.createdBy);
    final isMember = _community.isMember;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 1,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: const Color(0xFFF1F5F9),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.arrow_back_ios_new_rounded, size: 16, color: Color(0xFF0F172A)),
          ),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Detail Komunitas',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
        ),
        centerTitle: true,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : RefreshIndicator(
              onRefresh: _loadDetail,
              color: AppColors.matchaDark,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // HEADER SECTION: Logo, Title, Subtitle, Sport pill & Admin Actions
                    _buildHeaderSection(canManage),

                    const SizedBox(height: 16),

                    // CARD 1: TENTANG KOMUNITAS
                    _buildAboutCard(),

                    const SizedBox(height: 16),

                    // CARD 2: DAFTAR ANGGOTA KOMUNITAS
                    _buildMembersCard(),

                    const SizedBox(height: 16),

                    // CARD 3: MODE ADMINISTRATOR (for admin) or MEMBERSHIP CARD (for non-admin)
                    if (isAdmin)
                      _buildAdminModeCard()
                    else
                      _buildMembershipCard(isLoggedIn, isMember),

                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
    );
  }

  // --- HEADER SECTION ---
  Widget _buildHeaderSection(bool canManage) {
    final scheduleText = _community.jadwalRutin != null && _community.jadwalRutin!.isNotEmpty
        ? _community.jadwalRutin!
        : 'Rutin Setiap Pekan';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.03),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Community Info Row (Logo + Name + Subtitle)
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Image.network(
                    _community.displayImage,
                    fit: BoxFit.cover,
                    errorBuilder: (_, error, stackTrace) => const Center(
                      child: Icon(Icons.groups_rounded, size: 28, color: Color(0xFF94A3B8)),
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
                      _community.namaCommunity,
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                        color: Color(0xFF0F172A),
                        letterSpacing: -0.4,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        const Icon(Icons.group_rounded, size: 13, color: Color(0xFF64748B)),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            'Komunitas ${_community.sport} • Jadwal: $scheduleText',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w500,
                              color: Color(0xFF64748B),
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),

          // Action row: Sport Badge + Admin Buttons (Edit, Hapus)
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              // Sport Pill
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFFEBF8D8),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                ),
                child: Text(
                  _community.sport,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF063B00),
                  ),
                ),
              ),

              // If can manage (Admin or Creator)
              if (canManage) ...[
                // Edit Button
                InkWell(
                  onTap: _openEditCommunityModal,
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.02),
                          blurRadius: 4,
                          offset: const Offset(0, 1),
                        ),
                      ],
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.edit_outlined, size: 14, color: Color(0xFF063B00)),
                        SizedBox(width: 4),
                        Text(
                          'Edit Komunitas',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                // Delete / Deactivate Button
                InkWell(
                  onTap: _handleDeactivateOrDelete,
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF1F2),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: const Color(0xFFFECDD3)),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.delete_outline_rounded, size: 14, color: Color(0xFFE11D48)),
                        SizedBox(width: 4),
                        Text(
                          'Hapus / Nonaktifkan',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFFE11D48),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  // --- CARD 1: TENTANG KOMUNITAS ---
  Widget _buildAboutCard() {
    final scheduleText = _community.jadwalRutin != null && _community.jadwalRutin!.isNotEmpty
        ? _community.jadwalRutin!
        : 'Rutin Setiap Pekan';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.03),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Tentang Komunitas',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            _community.deskripsi != null && _community.deskripsi!.isNotEmpty
                ? _community.deskripsi!
                : 'Tidak ada deskripsi komunitas.',
            style: const TextStyle(
              fontSize: 12.5,
              color: Color(0xFF334155),
              height: 1.4,
            ),
          ),
          const SizedBox(height: 16),

          // 2 Sub-boxes: Cabang Olahraga Utama & Jadwal Rutin Mabar
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Cabang Olahraga Utama',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF64748B),
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        _community.sport,
                        style: const TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Jadwal Rutin Mabar',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF64748B),
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        scheduleText,
                        style: const TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F172A),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
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

  // --- CARD 2: DAFTAR ANGGOTA KOMUNITAS ---
  Widget _buildMembersCard() {
    final memberCount = _members.isNotEmpty ? _members.length : _community.memberCount;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.03),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header: Title + Member Count Pill
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Daftar Anggota Komunitas',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFEBF8D8),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.group_rounded, size: 13, color: Color(0xFF063B00)),
                    const SizedBox(width: 4),
                    Text(
                      '$memberCount Anggota',
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF063B00),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          if (_members.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: const Column(
                children: [
                  Icon(Icons.person_off_rounded, size: 28, color: Color(0xFF94A3B8)),
                  SizedBox(height: 8),
                  Text(
                    'Belum ada anggota bergabung di komunitas ini.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                  ),
                ],
              ),
            )
          else ...[
            // Table Header Bar
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: const BoxDecoration(
                border: Border(bottom: BorderSide(color: Color(0xFFF1F5F9), width: 1.5)),
              ),
              child: const Row(
                children: [
                  SizedBox(
                    width: 22,
                    child: Text('#', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                  ),
                  Expanded(
                    flex: 3,
                    child: Text('NAMA', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                  ),
                  Expanded(
                    flex: 2,
                    child: Center(
                      child: Text('STATUS', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                    ),
                  ),
                  Expanded(
                    flex: 2,
                    child: Center(
                      child: Text('SKILL LEVEL', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                    ),
                  ),
                  Expanded(
                    flex: 2,
                    child: Align(
                      alignment: Alignment.centerRight,
                      child: Text('GENDER / USIA', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 6),

            // Member Rows
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _members.length,
              separatorBuilder: (_, i) => const Divider(height: 1, color: Color(0xFFF8FAFC)),
              itemBuilder: (context, index) {
                final m = _members[index];
                final name = m['nama'] as String? ?? 'Pemain';
                final initial = name.isNotEmpty ? name[0].toUpperCase() : 'P';
                final level = (m['level'] as String? ?? 'Advanced');
                final gender = m['gender'] as String? ?? 'Male';
                final age = m['usia'] != null ? '${m['usia']} th' : '22 th';

                return Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10),
                  child: Row(
                    children: [
                      // # Index
                      SizedBox(
                        width: 22,
                        child: Text(
                          '${index + 1}',
                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF94A3B8)),
                        ),
                      ),

                      // Avatar + Name
                      Expanded(
                        flex: 3,
                        child: Row(
                          children: [
                            Container(
                              width: 28,
                              height: 28,
                              decoration: BoxDecoration(
                                gradient: const LinearGradient(
                                  colors: [AppColors.matchaDark, Color(0xFF064E3B)],
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                ),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                initial,
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Colors.white),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                name,
                                style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: Color(0xFF0F172A)),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ),

                      // Status Pill
                      Expanded(
                        flex: 2,
                        child: Center(
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEBF8D8),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'Member',
                              style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w700, color: Color(0xFF166534)),
                            ),
                          ),
                        ),
                      ),

                      // Skill Level Pill
                      Expanded(
                        flex: 2,
                        child: Center(
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFEF3C7),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              level,
                              style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.w700, color: Color(0xFFB45309)),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ),
                      ),

                      // Gender / Usia
                      Expanded(
                        flex: 2,
                        child: Align(
                          alignment: Alignment.centerRight,
                          child: Text(
                            '$gender, $age',
                            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w500, color: Color(0xFF64748B)),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ],
        ],
      ),
    );
  }

  // --- CARD 3A: MODE ADMINISTRATOR (For Admin) ---
  Widget _buildAdminModeCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.03),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Mode Administrator',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 12),

          // Amber Notice Box
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFFFFBEB),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFFDE68A)),
            ),
            child: const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.workspace_premium_rounded, size: 16, color: Color(0xFFB45309)),
                    SizedBox(width: 6),
                    Text(
                      'Akses Pengelola',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF92400E)),
                    ),
                  ],
                ),
                SizedBox(height: 4),
                Text(
                  'Admin mengelola komunitas ini secara sistem dan tidak bergabung sebagai anggota pemain.',
                  style: TextStyle(fontSize: 11, color: Color(0xFF78350F), height: 1.4),
                ),
              ],
            ),
          ),

          const SizedBox(height: 14),

          // Bottom Info Note
          const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.info_outline_rounded, size: 15, color: Color(0xFF063B00)),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Sebagai anggota, Anda dapat mengikuti sesi mabar, turnamen, dan melihat leaderboard komunitas.',
                  style: TextStyle(fontSize: 11, color: Color(0xFF64748B), height: 1.4),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // --- CARD 3B: STATUS KEANGGOTAAN / SIAP BERGABUNG (For Non-Admin) ---
  Widget _buildMembershipCard(bool isLoggedIn, bool isMember) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.03),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (isLoggedIn && isMember) ...[
            const Text(
              'Status Keanggotaan',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                color: const Color(0xFFDCFCE7),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFF86EFAC)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.check_circle_rounded, size: 18, color: Color(0xFF15803D)),
                  SizedBox(width: 8),
                  Text(
                    'Anda adalah Anggota',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF166534)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: OutlinedButton.icon(
                onPressed: _isActionLoading ? null : _handleToggleMembership,
                icon: _isActionLoading
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.redAccent),
                      )
                    : const Icon(Icons.logout_rounded, size: 16, color: Color(0xFFB91C1C)),
                label: Text(
                  _isActionLoading ? 'Memproses...' : 'Keluar dari Komunitas',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFFB91C1C)),
                ),
                style: OutlinedButton.styleFrom(
                  backgroundColor: const Color(0xFFFEF2F2),
                  side: const BorderSide(color: Color(0xFFFECACA)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
              ),
            ),
          ] else if (isLoggedIn && !isMember) ...[
            const Text(
              'Siap Bergabung?',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 4),
            const Text(
              'Bergabunglah dengan komunitas ini untuk mengikuti sesi mabar dan turnamen!',
              style: TextStyle(fontSize: 12, color: Color(0xFF334155), height: 1.4),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: ElevatedButton.icon(
                onPressed: _isActionLoading ? null : _handleToggleMembership,
                icon: _isActionLoading
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.person_add_rounded, size: 18, color: Color(0xFFA8E63A)),
                label: Text(
                  _isActionLoading ? 'Memproses...' : 'Bergabung Sekarang',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.matchaDark,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
              ),
            ),
          ] else ...[
            const Text(
              'Akses Komunitas',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 4),
            const Text(
              'Silakan login terlebih dahulu untuk bergabung dengan komunitas ini.',
              style: TextStyle(fontSize: 12, color: Color(0xFF334155), height: 1.4),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: ElevatedButton.icon(
                onPressed: _handleToggleMembership,
                icon: const Icon(Icons.login_rounded, size: 18, color: Color(0xFFA8E63A)),
                label: const Text('Login Terlebih Dahulu', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.matchaDark,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
              ),
            ),
          ],

          const SizedBox(height: 14),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 12),

          const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.info_outline_rounded, size: 15, color: Color(0xFF063B00)),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Sebagai anggota, Anda dapat mengikuti sesi mabar, turnamen, dan melihat leaderboard komunitas.',
                  style: TextStyle(fontSize: 11, color: Color(0xFF64748B), height: 1.4),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}