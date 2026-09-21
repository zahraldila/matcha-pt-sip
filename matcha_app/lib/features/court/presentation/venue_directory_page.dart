import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../data/venue_service.dart';
import '../domain/venue_model.dart';
import 'court_detail_page.dart';

class VenueDirectoryPage extends StatefulWidget {
  const VenueDirectoryPage({super.key});

  @override
  State<VenueDirectoryPage> createState() => _VenueDirectoryPageState();
}

class _VenueDirectoryPageState extends State<VenueDirectoryPage> {
  final VenueService _venueService = VenueService();

  bool _isLoading = true;
  String? _errorMessage;
  List<VenueModel> _venues = [];
  List<VenueModel> _filteredVenues = [];

  String _searchQuery = '';
  String _selectedSport = 'Semua Cabang';

  @override
  void initState() {
    super.initState();
    _loadVenues();
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
    _filteredVenues = _venues.where((v) {
      final matchesSearch = _searchQuery.isEmpty ||
          v.namaVenue.toLowerCase().contains(_searchQuery.toLowerCase()) ||
          (v.alamat != null && v.alamat!.toLowerCase().contains(_searchQuery.toLowerCase())) ||
          (v.kota != null && v.kota!.toLowerCase().contains(_searchQuery.toLowerCase())) ||
          (v.fasilitas != null && v.fasilitas!.toLowerCase().contains(_searchQuery.toLowerCase()));

      final matchesSport = _selectedSport == 'Semua Cabang' ||
          (v.courts.any((c) =>
              (_selectedSport == 'Padel' && c.sportId == 1) ||
              (_selectedSport == 'Tennis' && c.sportId == 2)));

      return matchesSearch && matchesSport;
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: RefreshIndicator(
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
                    // Badge Direktori Mitra Lapangan
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
                        onChanged: _onSearch,
                        decoration: InputDecoration(
                          hintText: 'Cari venue, kota, fasilitas...',
                          hintStyle: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF94A3B8),
                            fontSize: 13,
                          ),
                          prefixIcon: const Icon(Icons.search, color: Color(0xFF94A3B8), size: 20),
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

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
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline_rounded, color: Colors.redAccent, size: 40),
                        const SizedBox(height: 12),
                        Text(
                          'Gagal memuat venue',
                          style: AppTextStyles.cardTitle.copyWith(color: const Color(0xFF0F172A)),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption.copyWith(color: const Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _loadVenues,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.matchaDark,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          child: const Text('Coba Lagi'),
                        ),
                      ],
                    ),
                  ),
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

  Widget _buildVenueCard(VenueModel venue) {
    final courtCount = venue.courts.isNotEmpty ? venue.courts.length : 1;
    final facilitiesList = venue.fasilitas != null && venue.fasilitas!.isNotEmpty
        ? venue.fasilitas!.split(',').map((e) => e.trim()).take(4).toList()
        : <String>[];

    return Container(
      margin: const EdgeInsets.only(bottom: 18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Image / Cover Header
          Stack(
            children: [
              if (venue.foto != null && venue.foto!.isNotEmpty)
                Image.network(
                  venue.foto!,
                  height: 150,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => _buildPlaceholderCover(),
                )
              else
                _buildPlaceholderCover(),

              // Badge Kota / Area
              Positioned(
                top: 12,
                left: 12,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.65),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.location_on, size: 12, color: Color(0xFFA8E63A)),
                      const SizedBox(width: 4),
                      Text(
                        venue.kota ?? 'Bandung',
                        style: AppTextStyles.caption.copyWith(
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

          // Content
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  venue.namaVenue,
                  style: AppTextStyles.cardTitle.copyWith(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF0F172A),
                  ),
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
                            'Jam Operasional:',
                            style: AppTextStyles.caption.copyWith(
                              color: const Color(0xFF94A3B8),
                              fontSize: 11,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            venue.jamOperasional ?? '06:00 - 22:00 WIB',
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

                // Action Button
                SizedBox(
                  width: double.infinity,
                  height: 42,
                  child: ElevatedButton(
                    onPressed: () {
                      if (venue.courts.isNotEmpty) {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => CourtDetailPage(
                              courtId: venue.courts.first.courtId,
                            ),
                          ),
                        );
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text(
                          'Lihat Court & Jadwal',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(width: 6),
                        const Icon(Icons.arrow_forward_rounded, size: 16),
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
