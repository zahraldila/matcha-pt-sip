import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/widgets/offline_state_widget.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../data/venue_service.dart';
import '../domain/venue_model.dart';
import 'create_court_page.dart';
import 'create_venue_page.dart';
import 'venue_detail_page.dart';

class VenueDirectoryPage extends StatefulWidget {
  final AuthController? authController;
  final String? initialOwnerFilter;

  const VenueDirectoryPage({
    super.key,
    this.authController,
    this.initialOwnerFilter,
  });

  @override
  State<VenueDirectoryPage> createState() => _VenueDirectoryPageState();
}

class _VenueDirectoryPageState extends State<VenueDirectoryPage> {
  final VenueService _venueService = VenueService();

  final TextEditingController _searchController = TextEditingController();
  bool _isLoading = true;
  String? _errorMessage;
  List<VenueModel> _venues = [];
  List<VenueModel> _filteredVenues = [];

  bool _isSelectionMode = false;
  final Set<int> _selectedVenueIds = {};
  bool _isBulkDeleting = false;

  String _searchQuery = '';
  String _selectedSport = 'Semua Cabang';
  String _selectedOwnerFilter = 'all';

  @override
  void initState() {
    super.initState();
    if (widget.initialOwnerFilter != null) {
      _selectedOwnerFilter = widget.initialOwnerFilter!;
    }
    _loadVenues();
    widget.authController?.addListener(_onAuthChanged);
  }

  @override
  void dispose() {
    _searchController.dispose();
    widget.authController?.removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
    if (!mounted) return;
    setState(() {
      if (!_canFilterByOwner) _selectedOwnerFilter = 'all';
      _applyFilter();
    });
  }

  bool _canDeleteVenue(VenueModel v) {
    final user = widget.authController?.currentUser;
    if (user == null) return false;
    if (user.role.toLowerCase().contains('admin') || user.isAdmin) return true;
    if (v.ownerUserId != null && v.ownerUserId == user.userId) return true;
    return false;
  }

  bool get _canEnterSelectionMode {
    final user = widget.authController?.currentUser;
    if (user == null) return false;
    if (user.role.toLowerCase().contains('admin') || user.isAdmin) return true;
    return _filteredVenues.any(_canDeleteVenue);
  }

  void _toggleSelectionMode() {
    setState(() {
      _isSelectionMode = !_isSelectionMode;
      _selectedVenueIds.clear();
    });
  }

  void _toggleSelectVenue(int id) {
    setState(() {
      if (_selectedVenueIds.contains(id)) {
        _selectedVenueIds.remove(id);
      } else {
        _selectedVenueIds.add(id);
      }
    });
  }

  void _toggleSelectAll() {
    final deletableIds = _filteredVenues.where(_canDeleteVenue).map((v) => v.venueId).toSet();
    setState(() {
      if (_selectedVenueIds.length == deletableIds.length) {
        _selectedVenueIds.clear();
      } else {
        _selectedVenueIds.addAll(deletableIds);
      }
    });
  }

  Future<void> _handleBulkDelete() async {
    if (_selectedVenueIds.isEmpty) return;

    final count = _selectedVenueIds.length;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        child: Padding(
          padding: const EdgeInsets.all(22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFE4E6),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFFDA4AF)),
                ),
                child: const Icon(
                  Icons.delete_sweep_rounded,
                  color: Color(0xFFE11D48),
                  size: 26,
                ),
              ),
              const SizedBox(height: 14),
              Text(
                'Hapus $count Venue & Lapangan?',
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                'Sebanyak $count venue yang dipilih beserta seluruh data court/lapangan di dalamnya akan dihapus secara permanen dari database. Tindakan ini tidak dapat dibatalkan.',
                style: const TextStyle(
                  fontSize: 12.5,
                  color: Color(0xFF64748B),
                  height: 1.45,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(ctx, false),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF475569),
                        side: const BorderSide(color: Color(0xFFCBD5E1)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        padding: const EdgeInsets.symmetric(vertical: 11),
                      ),
                      child: const Text('Batal', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => Navigator.pop(ctx, true),
                      icon: const Icon(Icons.delete_forever_rounded, size: 16),
                      label: const Text('Hapus', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFE11D48),
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        padding: const EdgeInsets.symmetric(vertical: 11),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _isBulkDeleting = true);
    final messenger = ScaffoldMessenger.of(context);

    int successCount = 0;
    final idsToDelete = _selectedVenueIds.toList();

    for (final id in idsToDelete) {
      try {
        await _venueService.deleteVenue(id);
        successCount++;
      } catch (e) {
        debugPrint('Error deleting venue $id: $e');
      }
    }

    if (!mounted) return;
    setState(() {
      _isBulkDeleting = false;
      _isSelectionMode = false;
      _selectedVenueIds.clear();
    });

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          successCount == count
              ? 'Berhasil menghapus $count venue.'
              : (successCount > 0
                  ? 'Berhasil menghapus $successCount dari $count venue.'
                  : 'Gagal menghapus venue terpilih.'),
        ),
        backgroundColor: successCount > 0 ? AppColors.matchaDark : const Color(0xFFE11D48),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );

    await _loadVenues();
  }

  Widget _buildBulkActionBar(int deletableCount) {
    final selectedCount = _selectedVenueIds.length;
    final isAllSelected = deletableCount > 0 && selectedCount == deletableCount;

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
        border: const Border(
          top: BorderSide(color: Color(0xFFE2E8F0)),
        ),
      ),
      child: SafeArea(
        top: false,
        child: Row(
          children: [
            // Cancel / Undo Selection Button
            InkWell(
              onTap: _toggleSelectionMode,
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFCBD5E1)),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.close_rounded, size: 15, color: Color(0xFF475569)),
                    SizedBox(width: 4),
                    Text(
                      'Batal',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF475569),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 6),

            // Select All Checkbox
            InkWell(
              onTap: _toggleSelectAll,
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      isAllSelected ? Icons.check_box_rounded : Icons.check_box_outline_blank_rounded,
                      size: 17,
                      color: isAllSelected ? const Color(0xFF063B00) : const Color(0xFF64748B),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      isAllSelected ? 'Batal Semua' : 'Pilih Semua',
                      style: const TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF334155),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 6),

            // Counter Badge
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 7),
              decoration: BoxDecoration(
                color: const Color(0xFFEBF8D8),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFF86EFAC)),
              ),
              child: Text(
                '$selectedCount',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF063B00),
                ),
              ),
            ),
            const Spacer(),

            // Delete Button
            ElevatedButton.icon(
              onPressed: (selectedCount == 0 || _isBulkDeleting) ? null : _handleBulkDelete,
              icon: _isBulkDeleting
                  ? const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.delete_sweep_rounded, size: 15),
              label: Text(
                'Hapus ($selectedCount)',
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFE11D48),
                foregroundColor: Colors.white,
                disabledBackgroundColor: const Color(0xFFFDA4AF).withValues(alpha: 0.5),
                disabledForegroundColor: Colors.white70,
                elevation: 0,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool get _canFilterByOwner {
    final user = widget.authController?.currentUser;
    if (user == null) return false;
    return user.role.toLowerCase() == 'venue_owner' ||
        _venues.any((venue) => venue.ownerUserId == user.userId);
  }

  int get _myVenuesCount {
    final userId = widget.authController?.currentUser?.userId;
    if (userId == null) return 0;
    return _venues.where((venue) => venue.ownerUserId == userId).length;
  }

  Future<void> _loadVenues() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final venues = await _venueService.getVenues();
      if (!mounted) return;

      setState(() {
        _venues = venues;
        _applyFilter();
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

  void _applyFilter() {
    final userId = widget.authController?.currentUser?.userId;
    final query = _searchQuery.trim().toLowerCase();
    final tokens = query.isEmpty ? <String>[] : query.split(RegExp(r'\s+')).where((t) => t.isNotEmpty).toList();

    _filteredVenues = _venues.where((v) {
      final matchesSearch = tokens.isEmpty || () {
        final searchableText = [
          v.namaVenue,
          v.alamat ?? '',
          v.kota ?? '',
          v.fasilitas ?? '',
          v.catatan ?? '',
          v.namaPic ?? '',
          v.sportName,
          ...v.courts.map((c) => '${c.namaCourt} ${c.deskripsi ?? ''} ${c.tipeCourt ?? ''}'),
        ].join(' ').toLowerCase();

        return tokens.every((token) => searchableText.contains(token));
      }();

      final matchesSport = _selectedSport == 'Semua Cabang' ||
          v.sportName.toLowerCase().contains(_selectedSport.toLowerCase()) ||
          v.courts.any((c) =>
              (_selectedSport == 'Padel' && c.sportId == 1) ||
              (_selectedSport == 'Tennis' && c.sportId == 2));

        final matchesOwner = _selectedOwnerFilter != 'mine' ||
          (userId != null && v.ownerUserId == userId);

        return matchesSearch && matchesSport && matchesOwner;
    }).toList();
  }

  void _onSearch(String val) {
    setState(() {
      _searchQuery = val;
      _applyFilter();
    });
  }

  void _onSportSelect(String sport) {
    setState(() {
      _selectedSport = sport;
      _applyFilter();
    });
  }

  void _onOwnerFilterSelect(String filter) {
    setState(() {
      _selectedOwnerFilter = filter;
      _applyFilter();
    });
  }

  Future<void> _openCreateVenue() async {
    final created = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => CreateVenuePage(authController: widget.authController),
      ),
    );
    if (created == true) await _loadVenues();
  }

  @override
  Widget build(BuildContext context) {
    final deletableCount = _filteredVenues.where(_canDeleteVenue).length;

    return PopScope(
      canPop: !_isSelectionMode,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop && _isSelectionMode) {
          _toggleSelectionMode();
        }
      },
      child: Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        bottomNavigationBar: _isSelectionMode ? _buildBulkActionBar(deletableCount) : null,
      appBar: Navigator.canPop(context)
          ? AppBar(
              backgroundColor: Colors.white,
              elevation: 0,
              scrolledUnderElevation: 0,
              leading: IconButton(
                icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
                onPressed: () => Navigator.pop(context),
              ),
              title: Text(
                _selectedOwnerFilter == 'mine' ? 'Kelola Venue Saya' : 'Direktori Venue',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                ),
              ),
              centerTitle: true,
            )
          : null,
      body: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () {
          if (_isSelectionMode) {
            _toggleSelectionMode();
          }
        },
        child: RefreshIndicator(
          onRefresh: _loadVenues,
          color: AppColors.matchaDark,
          child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Badge Direktori Mitra Lapangan & Selection Button
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
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
                              const Icon(Icons.stadium_rounded, size: 12, color: AppColors.matchaDark),
                              const SizedBox(width: 5),
                              Text(
                                'DIREKTORI MITRA LAPANGAN',
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
                        if (_canEnterSelectionMode)
                          InkWell(
                            onTap: _toggleSelectionMode,
                            borderRadius: BorderRadius.circular(12),
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 200),
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                              decoration: BoxDecoration(
                                color: _isSelectionMode ? const Color(0xFFE11D48) : Colors.white,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: _isSelectionMode ? const Color(0xFFE11D48) : const Color(0xFFCBD5E1),
                                ),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    _isSelectionMode ? Icons.close_rounded : Icons.checklist_rtl_rounded,
                                    size: 14,
                                    color: _isSelectionMode ? Colors.white : const Color(0xFF475569),
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    _isSelectionMode ? 'Batal' : 'Pilih',
                                    style: TextStyle(
                                      fontSize: 11.5,
                                      fontWeight: FontWeight.bold,
                                      color: _isSelectionMode ? Colors.white : const Color(0xFF475569),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Direktori Venue & Court',
                      style: AppTextStyles.h1.copyWith(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Daftar partner lapangan Tennis & Padel dengan informasi fasilitas, jumlah court, dan jam operasional.',
                      style: AppTextStyles.caption.copyWith(
                        color: const Color(0xFF64748B),
                        height: 1.4,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 16),

                    if (widget.authController?.currentUser?.role.toLowerCase() == 'venue_owner') ...[
                      SizedBox(
                        width: double.infinity,
                        height: 44,
                        child: ElevatedButton.icon(
                          onPressed: _openCreateVenue,
                          icon: const Icon(Icons.add_business_rounded, size: 18),
                          label: const Text(
                            'Daftarkan Venue Baru',
                            style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.matchaDark,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            elevation: 0,
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                    ],

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
                        controller: _searchController,
                        onChanged: _onSearch,
                        decoration: InputDecoration(
                          hintText: 'Cari venue, kota, fasilitas...',
                          hintStyle: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF94A3B8),
                            fontSize: 13,
                          ),
                          prefixIcon: const Icon(Icons.search, color: Color(0xFF94A3B8), size: 20),
                          suffixIcon: _searchQuery.isNotEmpty
                              ? IconButton(
                                  icon: const Icon(Icons.close_rounded, color: Color(0xFF94A3B8), size: 18),
                                  onPressed: () {
                                    _searchController.clear();
                                    _onSearch('');
                                  },
                                )
                              : null,
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

                    if (_canFilterByOwner) ...[
                      Row(
                        children: [
                          Expanded(
                            child: _buildOwnerFilterChip(
                              'all',
                              'Semua Venue',
                              _venues.length,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _buildOwnerFilterChip(
                              'mine',
                              'Venue Saya',
                              _myVenuesCount,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                    ],

                    // Sport Filters
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          _buildFilterChip('Semua Cabang', Icons.sports_tennis),
                          const SizedBox(width: 8),
                          _buildFilterChip('Tennis', Icons.sports_tennis),
                          const SizedBox(width: 8),
                          _buildFilterChip('Padel', Icons.sports_kabaddi),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                ),
              ),
            ),

            if (_isLoading)
              const SliverFillRemaining(
                child: Center(
                  child: CircularProgressIndicator(color: AppColors.matchaDark),
                ),
              )
            else if (_errorMessage != null)
              SliverFillRemaining(
                hasScrollBody: false,
                child: OfflineStateWidget(
                  error: _errorMessage,
                  customTitle: 'Gagal Memuat Venue',
                  onRetry: _loadVenues,
                ),
              )
            else if (_filteredVenues.isEmpty)
              SliverFillRemaining(
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.location_off_outlined, color: Color(0xFF94A3B8), size: 48),
                      const SizedBox(height: 12),
                      Text(
                        'Tidak ada venue ditemukan',
                        style: AppTextStyles.cardTitle.copyWith(color: const Color(0xFF334155)),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Coba gunakan kata kunci pencarian lain',
                        style: AppTextStyles.caption.copyWith(color: const Color(0xFF94A3B8)),
                      ),
                    ],
                  ),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 30),
                sliver: SliverList(
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final venue = _filteredVenues[index];
                      return _buildVenueCard(venue);
                    },
                    childCount: _filteredVenues.length,
                  ),
                ),
              ),
          ],
        ),
      ),
    ),
    ),
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

  Widget _buildOwnerFilterChip(String value, String label, int count) {
    final isSelected = _selectedOwnerFilter == value;
    return GestureDetector(
      onTap: () => _onOwnerFilterSelect(value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.matchaDark : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: isSelected ? Colors.white : const Color(0xFF475569),
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 6),
            Text(
              '$count',
              style: TextStyle(
                color: isSelected ? const Color(0xFFA8E63A) : const Color(0xFF64748B),
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildVenueCard(VenueModel venue) {
    final courtCount = venue.courtCount;
    final facilitiesList = venue.facilitiesList.take(3).toList();
    final user = widget.authController?.currentUser;
    final isOwner = user != null && venue.ownerUserId != null && venue.ownerUserId == user.userId;
    final canDelete = _canDeleteVenue(venue);
    final isSelected = _selectedVenueIds.contains(venue.venueId);

    void openDetail() {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => VenueDetailPage(
            venueId: venue.venueId,
            initialVenue: venue,
            authController: widget.authController,
          ),
        ),
      ).then((_) => _loadVenues());
    }

    void handleCardTap() {
      if (_isSelectionMode) {
        if (canDelete) {
          _toggleSelectVenue(venue.venueId);
        }
      } else {
        openDetail();
      }
    }

    void handleCardLongPress() {
      if (!_isSelectionMode && canDelete) {
        setState(() {
          _isSelectionMode = true;
          _selectedVenueIds.add(venue.venueId);
        });
      }
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 18),
      decoration: BoxDecoration(
        color: isSelected ? const Color(0xFFF0FDF4) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isSelected
              ? const Color(0xFF063B00)
              : (_isSelectionMode && canDelete ? const Color(0xFF86EFAC) : const Color(0xFFE2E8F0)),
          width: isSelected ? 2.0 : 1.0,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: handleCardTap,
          onLongPress: handleCardLongPress,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Image / Cover Header with Real Photos & Badges
              Stack(
                children: [
                  Image.network(
                    venue.mainPhoto,
                    height: 160,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => _buildPlaceholderCover(),
                  ),

                  // Checkbox when in selection mode
                  if (_isSelectionMode)
                    Positioned(
                      top: 12,
                      left: 12,
                      child: InkWell(
                        onTap: canDelete ? () => _toggleSelectVenue(venue.venueId) : null,
                        borderRadius: BorderRadius.circular(8),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          width: 26,
                          height: 26,
                          decoration: BoxDecoration(
                            color: isSelected
                                ? const Color(0xFF063B00)
                                : (canDelete
                                    ? Colors.white.withValues(alpha: 0.95)
                                    : const Color(0xFFF1F5F9).withValues(alpha: 0.95)),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: isSelected
                                  ? const Color(0xFF063B00)
                                  : (canDelete ? const Color(0xFF94A3B8) : const Color(0xFFCBD5E1)),
                              width: 1.5,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.15),
                                blurRadius: 4,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: isSelected
                              ? const Icon(Icons.check_rounded, size: 18, color: Color(0xFFA8E63A))
                              : (!canDelete
                                  ? const Icon(Icons.lock_outline_rounded, size: 14, color: Color(0xFF94A3B8))
                                  : null),
                        ),
                      ),
                    ),

                  // Badge Sport on Top Left
                  Positioned(
                    top: 12,
                    left: _isSelectionMode ? 46 : 12,
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

                  // Badge "👑 Venue Anda" on Top Right
                  if (isOwner)
                    Positioned(
                      top: 12,
                      right: 12,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFF047857),
                        borderRadius: BorderRadius.circular(20),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.2),
                            blurRadius: 4,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text('👑', style: TextStyle(fontSize: 10)),
                          SizedBox(width: 4),
                          Text(
                            'Venue Anda',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                // Badge Kota on Bottom Left
                Positioned(
                  bottom: 12,
                  left: 12,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.65),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.location_on, size: 12, color: Color(0xFFA8E63A)),
                        const SizedBox(width: 4),
                        Text(
                          venue.kota ?? 'Bandung',
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

                // Photo Count Badge on Bottom Right (if not owner or beside it)
                if (venue.photoList.length > 1)
                  Positioned(
                    bottom: 12,
                    right: 12,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.65),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.camera_alt_rounded, size: 11, color: Color(0xFFA8E63A)),
                          const SizedBox(width: 4),
                          Text(
                            '${venue.photoList.length} Foto',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),

            // Content
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          venue.namaVenue,
                          style: AppTextStyles.cardTitle.copyWith(
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                            color: const Color(0xFF0F172A),
                          ),
                        ),
                      ),
                      if (isOwner) ...[
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppColors.matchaSoftLime,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                          ),
                          child: const Text(
                            'Milik Anda',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              color: AppColors.matchaDark,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  if (venue.alamat != null && venue.alamat!.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        const Icon(Icons.pin_drop_outlined, size: 13, color: Color(0xFF94A3B8)),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            venue.alamat!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTextStyles.caption.copyWith(
                              color: const Color(0xFF64748B),
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],

                  const SizedBox(height: 14),
                  const Divider(height: 1, color: Color(0xFFF1F5F9)),
                  const SizedBox(height: 12),

                  // Info Grid: Jam Operasional & Jumlah Court
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Jam Operasi:',
                              style: AppTextStyles.caption.copyWith(
                                color: const Color(0xFF94A3B8),
                                fontSize: 11,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              venue.jamOperasional ?? '06:00 - 23:00 WIB',
                              style: AppTextStyles.caption.copyWith(
                                fontWeight: FontWeight.bold,
                                color: const Color(0xFF1E293B),
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              'Jumlah Court:',
                              style: AppTextStyles.caption.copyWith(
                                color: const Color(0xFF94A3B8),
                                fontSize: 11,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '$courtCount Lapangan',
                              style: AppTextStyles.caption.copyWith(
                                fontWeight: FontWeight.bold,
                                color: AppColors.matchaDark,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),

                  // Facilities Chips
                  if (facilitiesList.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: facilitiesList.map((f) {
                        return Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            f,
                            style: AppTextStyles.caption.copyWith(
                              color: const Color(0xFF475569),
                              fontSize: 10,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        );
                      }).toList(),
                    ),
                  ],

                  const SizedBox(height: 16),

                  // Action Buttons (Kelola Venue & + Button if Owner)
                  if (isOwner)
                    Row(
                      children: [
                        Expanded(
                          child: SizedBox(
                            height: 44,
                            child: ElevatedButton.icon(
                              onPressed: openDetail,
                              icon: const Icon(Icons.settings_rounded, size: 16),
                              label: const Text(
                                'Kelola Venue',
                                style: TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 13,
                                ),
                              ),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF063B00),
                                foregroundColor: Colors.white,
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(14),
                                ),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        InkWell(
                          onTap: () async {
                            final added = await Navigator.push<bool>(
                              context,
                              MaterialPageRoute(
                                builder: (_) => CreateCourtPage(
                                  venue: venue,
                                  authController: widget.authController,
                                ),
                              ),
                            );
                            if (added == true) {
                              _loadVenues();
                            }
                          },
                          borderRadius: BorderRadius.circular(14),
                          child: Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: const Color(0xFFEBF8D8),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                            ),
                            child: const Icon(Icons.add_rounded, color: Color(0xFF063B00), size: 22),
                          ),
                        ),
                      ],
                    )
                  else
                    SizedBox(
                      width: double.infinity,
                      height: 44,
                      child: ElevatedButton(
                        onPressed: openDetail,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.matchaDark,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                        child: const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              'Lihat Venue & Jadwal',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 13,
                              ),
                            ),
                            SizedBox(width: 6),
                            Icon(Icons.arrow_forward_rounded, size: 16),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

  Widget _buildPlaceholderCover() {
    return Container(
      height: 150,
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF063B00), Color(0xFF1E5B10)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.sports_tennis_rounded,
              size: 44,
              color: const Color(0xFFA8E63A).withValues(alpha: 0.8),
            ),
            const SizedBox(height: 6),
            Text(
              'MATCHA VENUE PARTNER',
              style: AppTextStyles.badge.copyWith(
                color: Colors.white.withValues(alpha: 0.9),
                fontSize: 10,
                letterSpacing: 1.5,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
