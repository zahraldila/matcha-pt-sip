import 'dart:async';
import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../court/data/venue_service.dart';
import '../../court/domain/venue_model.dart';
import '../../drawing/domain/matcha_drawing_engine.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import '../../session/data/session_service.dart';
import '../domain/game_wizard_model.dart';

class CreateGameWizardPage extends StatefulWidget {
  final AuthController? authController;

  const CreateGameWizardPage({super.key, this.authController});

  @override
  State<CreateGameWizardPage> createState() => _CreateGameWizardPageState();
}

class _CreateGameWizardPageState extends State<CreateGameWizardPage> {
  int _currentStep = 1; // 1 to 4
  final GameWizardConfig _config = GameWizardConfig();
  final VenueService _venueService = VenueService();
  final SessionService _sessionService = SessionService();
  bool _isSavingHostGame = false;

  List<VenueModel> _venues = [];
  bool _isLoadingVenues = true;
  final TextEditingController _activityNameController = TextEditingController();

  static const List<DropdownMenuItem<String>> _scoringSystemItems = [
    // Header 1: Sistem Rotasi Poin (Total of X)
    DropdownMenuItem<String>(
      enabled: false,
      value: '__header_total_of__',
      child: Text(
        'Sistem Rotasi Poin (Total of X)',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: Color(0xFF64748B),
          letterSpacing: 0.3,
        ),
      ),
    ),
    DropdownMenuItem<String>(
      value: 'Total of 3 Poin',
      child: Padding(
        padding: EdgeInsets.only(left: 8),
        child: Text('Total of 3 Poin', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      ),
    ),
    DropdownMenuItem<String>(
      value: 'Total of 4 Poin',
      child: Padding(
        padding: EdgeInsets.only(left: 8),
        child: Text('Total of 4 Poin', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      ),
    ),
    DropdownMenuItem<String>(
      value: 'Total of 5 Poin',
      child: Padding(
        padding: EdgeInsets.only(left: 8),
        child: Text('Total of 5 Poin', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      ),
    ),
    DropdownMenuItem<String>(
      value: 'Total of 6 Poin',
      child: Padding(
        padding: EdgeInsets.only(left: 8),
        child: Text('Total of 6 Poin', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      ),
    ),
    DropdownMenuItem<String>(
      value: 'Total of 7 Poin',
      child: Padding(
        padding: EdgeInsets.only(left: 8),
        child: Text('Total of 7 Poin', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      ),
    ),

    // Header 2: Sistem Langsung Tuntas (First to X)
    DropdownMenuItem<String>(
      enabled: false,
      value: '__header_first_to__',
      child: Text(
        'Sistem Langsung Tuntas (First to X)',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: Color(0xFF64748B),
          letterSpacing: 0.3,
        ),
      ),
    ),
    DropdownMenuItem<String>(
      value: 'First to 8 Poin (Tuntas)',
      child: Padding(
        padding: EdgeInsets.only(left: 8),
        child: Text('First to 8 Poin (Tuntas)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      ),
    ),
    DropdownMenuItem<String>(
      value: 'First to 11 Poin (Tuntas)',
      child: Padding(
        padding: EdgeInsets.only(left: 8),
        child: Text('First to 11 Poin (Tuntas)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      ),
    ),
    DropdownMenuItem<String>(
      value: 'First to 15 Poin (Tuntas)',
      child: Padding(
        padding: EdgeInsets.only(left: 8),
        child: Text('First to 15 Poin (Tuntas)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      ),
    ),
    DropdownMenuItem<String>(
      value: 'First to 21 Poin (Tuntas)',
      child: Padding(
        padding: EdgeInsets.only(left: 8),
        child: Text('First to 21 Poin (Tuntas)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
      ),
    ),
  ];

  @override
  void initState() {
    super.initState();
    _activityNameController.addListener(_onActivityNameChanged);
    _loadVenues();
  }

  void _onActivityNameChanged() {
    setState(() {
      _config.activityName = _activityNameController.text;
    });
  }

  @override
  void dispose() {
    _activityNameController.removeListener(_onActivityNameChanged);
    _activityNameController.dispose();
    super.dispose();
  }

  Future<void> _loadVenues() async {
    try {
      final venues = await _venueService.getVenues();
      if (mounted) {
        setState(() {
          _venues = venues;
          _isLoadingVenues = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _isLoadingVenues = false);
      }
    }
  }

  void _showVenueSearchPickerModal() {
    final sportId = _config.sport.toLowerCase() == 'tennis' ? 2 : 1;
    final availableVenues = _venues.where((v) {
      return v.courts.isEmpty || v.courts.any((c) => c.sportId == sportId);
    }).toList();
    final targetVenues = availableVenues.isNotEmpty ? availableVenues : _venues;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        String searchQuery = '';
        return StatefulBuilder(
          builder: (context, setModalState) {
            final filteredVenues = targetVenues.where((v) {
              final query = searchQuery.toLowerCase().trim();
              if (query.isEmpty) return true;
              final nameMatch = v.namaVenue.toLowerCase().contains(query);
              final cityMatch = v.kota != null && v.kota!.toLowerCase().contains(query);
              final addressMatch = v.alamat != null && v.alamat!.toLowerCase().contains(query);
              return nameMatch || cityMatch || addressMatch;
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
                            Text(
                              'Pilih Venue Lapangan',
                              style: AppTextStyles.h2.copyWith(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: const Color(0xFF0F172A),
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${targetVenues.length} venue tersedia untuk ${_config.sport}',
                              style: AppTextStyles.caption.copyWith(
                                fontSize: 11,
                                color: const Color(0xFF64748B),
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
                        autofocus: false,
                        onChanged: (val) {
                          setModalState(() {
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
                                    setModalState(() {
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
                                  const SizedBox(height: 16),
                                  ElevatedButton.icon(
                                    onPressed: () {
                                      Navigator.pop(context);
                                      _showQuickAddVenueModal();
                                    },
                                    icon: const Icon(Icons.add_rounded, size: 16),
                                    label: const Text('Tambah Venue Cepat', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: AppColors.matchaDark,
                                      foregroundColor: const Color(0xFFA8E63A),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          )
                        : ListView.separated(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            itemCount: filteredVenues.length,
                            separatorBuilder: (_, __) => const Divider(height: 1, color: Color(0xFFF1F5F9)),
                            itemBuilder: (context, index) {
                              final venue = filteredVenues[index];
                              final isSelected = _config.venueId == venue.venueId;
                              final courtCount = venue.courts.isNotEmpty ? venue.courts.length : 1;

                              return InkWell(
                                onTap: () {
                                  setState(() {
                                    _config.venueId = venue.venueId;
                                    _config.venueName = venue.namaVenue;
                                  });
                                  Navigator.pop(context);
                                },
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
                                              '${venue.kota ?? venue.alamat ?? 'Semua Lokasi'} • $courtCount Court',
                                              style: TextStyle(
                                                fontSize: 11,
                                                color: isSelected ? AppColors.matchaDark.withValues(alpha: 0.8) : const Color(0xFF64748B),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      if (isSelected)
                                        Icon(
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

                  // Bottom sticky banner: Input Venue Baru (Persis seperti web)
                  Container(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      border: Border(top: BorderSide(color: Colors.grey.shade100)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.04),
                          offset: const Offset(0, -3),
                          blurRadius: 8,
                        ),
                      ],
                    ),
                    child: InkWell(
                      onTap: () {
                        Navigator.pop(context);
                        _showQuickAddVenueModal();
                      },
                      borderRadius: BorderRadius.circular(12),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF4FBEA),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFD6F3B0)),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.add_circle_outline_rounded, size: 18, color: AppColors.matchaDark),
                                const SizedBox(width: 8),
                                const Text(
                                  'Input Venue Baru',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.matchaDark,
                                  ),
                                ),
                              ],
                            ),
                            Container(
                              padding: EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: AppColors.matchaDark,
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                '+ Baru',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white,
                                ),
                              ),
                            ),
                          ],
                        ),
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

  void _showQuickAddVenueModal() {
    final venueNameCtrl = TextEditingController();
    final cityCtrl = TextEditingController();
    final addressCtrl = TextEditingController();
    String selectedSportName = _config.sport.isNotEmpty ? _config.sport : 'Padel';
    int? numberOfCourts = 2; // Default 2 courts like web
    bool isAddingVenue = false;
    String? errorMessage;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (modalCtx, setModalState) {
            return Padding(
              padding: EdgeInsets.only(bottom: MediaQuery.of(modalCtx).viewInsets.bottom),
              child: Container(
                padding: const EdgeInsets.fromLTRB(22, 20, 22, 28),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Header (Matches Web)
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: const Color(0xFFEBF8D8),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Icon(
                                Icons.add_business_rounded,
                                size: 20,
                                color: AppColors.matchaDark,
                              ),
                            ),
                            const SizedBox(width: 10),
                            const Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Tambah Venue Baru',
                                  style: TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w800,
                                    color: Color(0xFF0F172A),
                                  ),
                                ),
                                Text(
                                  'Daftarkan venue & court secara instan',
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: Color(0xFF94A3B8),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(modalCtx),
                          icon: const Icon(Icons.close_rounded, color: Color(0xFF94A3B8), size: 20),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),

                    // Nama Venue *
                    _buildSectionLabel('Nama Venue *'),
                    const SizedBox(height: 6),
                    TextField(
                      controller: venueNameCtrl,
                      onChanged: (_) {
                        if (errorMessage != null) {
                          setModalState(() => errorMessage = null);
                        }
                      },
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                      decoration: InputDecoration(
                        hintText: 'Contoh: Matcha Padel Arena Dago',
                        hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        prefixIcon: const Icon(Icons.apartment_rounded, color: Color(0xFF94A3B8), size: 18),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Cabang Olahraga & Jumlah Court
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildSectionLabel('Cabang Olahraga *'),
                              const SizedBox(height: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF8FAFC),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: const Color(0xFFE2E8F0)),
                                ),
                                child: DropdownButtonHideUnderline(
                                  child: DropdownButton<String>(
                                    value: selectedSportName,
                                    isExpanded: true,
                                    items: const [
                                      DropdownMenuItem(value: 'Padel', child: Text('Padel', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                                      DropdownMenuItem(value: 'Tennis', child: Text('Tennis', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                                    ],
                                    onChanged: (val) {
                                      if (val != null) setModalState(() => selectedSportName = val);
                                    },
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildSectionLabel('Jumlah Court *'),
                              const SizedBox(height: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF8FAFC),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: const Color(0xFFE2E8F0)),
                                ),
                                child: DropdownButtonHideUnderline(
                                  child: DropdownButton<int>(
                                    value: numberOfCourts,
                                    isExpanded: true,
                                    items: const [
                                      DropdownMenuItem(value: 1, child: Text('1 Court', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                                      DropdownMenuItem(value: 2, child: Text('2 Courts', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                                      DropdownMenuItem(value: 3, child: Text('3 Courts', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                                      DropdownMenuItem(value: 4, child: Text('4 Courts', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                                      DropdownMenuItem(value: 5, child: Text('5 Courts', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                                      DropdownMenuItem(value: 6, child: Text('6 Courts', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                                    ],
                                    onChanged: (val) {
                                      if (val != null) setModalState(() => numberOfCourts = val);
                                    },
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Kota / Wilayah & Alamat Singkat
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildSectionLabel('Kota / Wilayah'),
                              const SizedBox(height: 6),
                              TextField(
                                controller: cityCtrl,
                                style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
                                decoration: InputDecoration(
                                  hintText: 'Contoh: Bandung',
                                  hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                                  filled: true,
                                  fillColor: const Color(0xFFF8FAFC),
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                                  enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                                  focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildSectionLabel('Alamat Singkat'),
                              const SizedBox(height: 6),
                              TextField(
                                controller: addressCtrl,
                                style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
                                decoration: InputDecoration(
                                  hintText: 'Contoh: Jl. Ir. H. Juanda No. 10',
                                  hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                                  filled: true,
                                  fillColor: const Color(0xFFF8FAFC),
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                                  enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                                  focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),

                    // Info Box (Matches Web)
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF4FBEA),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFD6F3B0)),
                      ),
                      child: const Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.info_outline_rounded, color: AppColors.matchaDark, size: 16),
                          SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Sistem otomatis mendaftarkan court (Court 1, Court 2, dst) dan langsung memilih venue ini untuk sesi game Anda.',
                              style: TextStyle(
                                color: AppColors.matchaDark,
                                fontSize: 11,
                                height: 1.3,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                    if (errorMessage != null) ...[
                      const SizedBox(height: 12),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFFFECACA)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline_rounded, color: Colors.redAccent, size: 16),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                errorMessage!,
                                style: const TextStyle(
                                  color: Colors.redAccent,
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 20),

                    // Action Buttons (Batal & Simpan & Pilih)
                    Row(
                      children: [
                        Expanded(
                          flex: 1,
                          child: SizedBox(
                            height: 44,
                            child: OutlinedButton(
                              onPressed: () => Navigator.pop(modalCtx),
                              style: OutlinedButton.styleFrom(
                                side: const BorderSide(color: Color(0xFFE2E8F0)),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              child: const Text('Batal', style: TextStyle(color: Color(0xFF475569), fontWeight: FontWeight.bold, fontSize: 13)),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          flex: 2,
                          child: SizedBox(
                            height: 44,
                            child: ElevatedButton(
                              onPressed: isAddingVenue
                                  ? null
                                  : () async {
                                      if (venueNameCtrl.text.trim().isEmpty) {
                                        setModalState(() => errorMessage = 'Nama venue wajib diisi');
                                        return;
                                      }
                                      if (numberOfCourts == null || numberOfCourts! <= 0) {
                                        setModalState(() => errorMessage = 'Jumlah court wajib dipilih');
                                        return;
                                      }

                                      setModalState(() {
                                        isAddingVenue = true;
                                        errorMessage = null;
                                      });

                                      try {
                                        final targetSportId = selectedSportName.toLowerCase() == 'tennis' ? 2 : 1;
                                        final newVenue = await _venueService.quickAddVenue(
                                          namaVenue: venueNameCtrl.text.trim(),
                                          kota: cityCtrl.text.trim().isNotEmpty ? cityCtrl.text.trim() : 'Bandung',
                                          alamat: addressCtrl.text.trim(),
                                          numberOfCourts: numberOfCourts!,
                                          sportId: targetSportId,
                                          ownerUserId: widget.authController?.currentUser?.userId,
                                        );

                                        if (modalCtx.mounted) {
                                          Navigator.pop(modalCtx);
                                        }
                                        if (!mounted) return;

                                        final updatedVenues = await _venueService.getVenues();
                                        if (!mounted) return;

                                        setState(() {
                                          _venues = updatedVenues;
                                          _config.venueId = newVenue.venueId;
                                          _config.venueName = newVenue.namaVenue;
                                        });

                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(
                                            content: Text('Venue "${newVenue.namaVenue}" berhasil ditambahkan & dipilih! 🎉'),
                                            backgroundColor: AppColors.matchaDark,
                                            behavior: SnackBarBehavior.floating,
                                          ),
                                        );
                                      } catch (e) {
                                        if (modalCtx.mounted) {
                                          setModalState(() {
                                            isAddingVenue = false;
                                            errorMessage = 'Gagal menambah venue: $e';
                                          });
                                        }
                                      }
                                    },
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.matchaDark,
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                elevation: 0,
                              ),
                              child: isAddingVenue
                                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                  : const Row(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Icon(Icons.save_rounded, size: 16),
                                        SizedBox(width: 6),
                                        Text('Simpan & Pilih', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
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
            );
          },
        );
      },
    );
  }

  void _addCurrentHost() {
    final user = widget.authController?.currentUser;
    if (user == null) return;

    final isFirstTo = _config.scoringSystem.toLowerCase().startsWith('first to');
    final maxAllowed = isFirstTo
        ? (_config.playMode == 'Single' ? _config.courtCount * 2 : _config.courtCount * 4)
        : null;

    if (maxAllowed != null && _config.players.length >= maxAllowed) {
      final modeLabel = _config.playMode == 'Single' ? 'Single (1 vs 1)' : 'Double (2 vs 2)';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Kapasitas pemain untuk format ${_config.scoringSystem} ($modeLabel) dengan ${_config.courtCount} court sudah penuh (maksimal $maxAllowed pemain).',
          ),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    // Check if already added
    if (_config.players.any((p) => p.userId == user.userId || p.name.toLowerCase() == user.nama.toLowerCase())) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Kamu sudah masuk ke dalam daftar pemain.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() {
      final genderStr = user.gender?.toLowerCase() ?? '';
      _config.players.insert(
        0,
        GamePlayerItem(
          id: 'user_${user.userId}',
          playerId: user.playerId,
          userId: user.userId,
          name: user.nama.isNotEmpty ? user.nama : 'Host Player',
          gender: (genderStr == 'female' || genderStr == 'perempuan')
              ? 'Perempuan'
              : 'Laki-laki',
          level: (user.level != null && user.level!.isNotEmpty) ? user.level! : 'Beginner',
          isGuest: false,
          avatarUrl: user.foto,
        ),
      );
    });
  }

  void _openAddPlayerModal() {
    final isFirstTo = _config.scoringSystem.toLowerCase().startsWith('first to');
    final maxAllowed = isFirstTo
        ? (_config.playMode == 'Single' ? _config.courtCount * 2 : _config.courtCount * 4)
        : null;
    final modeLabel = _config.playMode == 'Single' ? 'Single (1 vs 1)' : 'Double (2 vs 2)';

    if (maxAllowed != null && _config.players.length >= maxAllowed) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Kapasitas pemain untuk format ${_config.scoringSystem} ($modeLabel) dengan ${_config.courtCount} court sudah penuh (maksimal $maxAllowed pemain).',
          ),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _AddPlayerBottomSheet(
        currentPlayers: _config.players,
        sessionId: _config.sessionId,
        maxAllowed: maxAllowed,
        scoringSystem: _config.scoringSystem,
        modeLabel: modeLabel,
        courtCount: _config.courtCount,
        onAddPlayer: (player) {
          final isDuplicate = _config.players.any((p) =>
              (player.playerId != null && p.playerId == player.playerId) ||
              (player.userId != null && p.userId == player.userId) ||
              p.name.trim().toLowerCase() == player.name.trim().toLowerCase());
          if (isDuplicate) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Pemain "${player.name}" sudah ada di daftar.'),
                behavior: SnackBarBehavior.floating,
              ),
            );
            return;
          }
          setState(() {
            _config.players.add(player);
          });
        },
      ),
    );
  }

  Future<void> _onGenerateDrawing() async {
    if (_config.venueId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Silakan pilih venue terlebih dahulu.'),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
      setState(() => _currentStep = 3);
      return;
    }

    final isFirstTo = _config.scoringSystem.toLowerCase().startsWith('first to');
    final isTeamAmericano = _config.gameType.toLowerCase().contains('team');
    final numCourts = _config.courtCount;
    final isSingle = _config.playMode == 'Single';
    final exactRequired = isSingle ? (numCourts * 2) : (numCourts * 4);
    final modeLabel = isSingle ? 'Single (1 vs 1)' : 'Double (2 vs 2)';

    // Validasi 1: First to X (harus TEPAT kapasitas court)
    if (isFirstTo) {
      if (_config.players.length != exactRequired) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Untuk format ${_config.scoringSystem} ($modeLabel) dengan $numCourts court, jumlah pemain harus tepat $exactRequired orang (saat ini: ${_config.players.length} pemain).',
            ),
            backgroundColor: Colors.orange,
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }
    }

    // Validasi 2: Team Americano (harus genap & minimal 4)
    if (isTeamAmericano) {
      if (_config.players.length < 4) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Team Americano membutuhkan minimal 4 pemain (2 tim berpasangan).'),
            backgroundColor: Colors.orange,
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }
      if (_config.players.length % 2 != 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Jumlah pemain Team Americano harus genap (4, 6, 8, dst) karena setiap tim terdiri dari 2 orang pasangan tetap (saat ini: ${_config.players.length} pemain).',
            ),
            backgroundColor: Colors.orange,
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }
    }

    // Validasi 3: Minimal Pemain Umum
    final minRequired = isSingle ? 2 : 4;
    if (_config.players.length < minRequired) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Minimal $minRequired pemain untuk membuat drawing ${_config.playMode}.'),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() => _isSavingHostGame = true);

    try {
      final user = widget.authController?.currentUser;
      final sportId = _config.sport.toLowerCase().contains('tennis') ? 2 : 1;

      // 1. Pastikan semua pemain guest terdaftar dan memiliki player_id database
      final List<int> validPlayerIds = [];
      for (int i = 0; i < _config.players.length; i++) {
        final p = _config.players[i];
        int? effectivePid = p.playerId;
        if (effectivePid == null || effectivePid <= 0) {
          try {
            final genderDb = (p.gender.toLowerCase() == 'perempuan' || p.gender.toLowerCase() == 'female')
                ? 'Female'
                : 'Male';
            effectivePid = await _sessionService.registerGuestPlayer(
              nama: p.name,
              gender: genderDb,
              level: p.level,
            );
            _config.players[i] = GamePlayerItem(
              id: 'guest_db_$effectivePid',
              name: p.name,
              gender: p.gender,
              level: p.level,
              isGuest: true,
              avatarUrl: p.avatarUrl,
              userId: p.userId,
              playerId: effectivePid,
            );
          } catch (_) {}
        }
        if (effectivePid != null && effectivePid > 0) {
          validPlayerIds.add(effectivePid);
        }
      }

      // 2. Simpan sesi ke Supabase (tb_session, tb_session_court, tb_session_player)
      if (_config.sessionId == null) {
        final newSessionId = await _sessionService.createHostGameSession(
          sportId: sportId,
          venueId: _config.venueId!,
          namaSession: _config.activityName.trim().isNotEmpty
              ? _config.activityName.trim()
              : '${_config.sport} ${_config.gameType}',
          scoringSystem: _config.scoringSystem,
          jenisPermainan: _config.gameType,
          playMode: _config.playMode,
          jumlahPemain: _config.players.length,
          statusSession: 'In Progress',
          hostUserId: user?.userId,
          hostPlayerId: user?.playerId,
          playerIds: validPlayerIds,
        );
        _config.sessionId = newSessionId;
      }

      if (!mounted) return;
      setState(() => _isSavingHostGame = false);

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: _config.players,
        courtCount: _config.courtCount,
        gameType: _config.gameType,
        playMode: _config.playMode,
        roundCount: _config.totalRounds,
      );

      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => DrawingResultPage(
            config: _config,
            initialRounds: rounds,
            authController: widget.authController,
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSavingHostGame = false);

      final rounds = MatchaDrawingEngine.generateDrawing(
        players: _config.players,
        courtCount: _config.courtCount,
        gameType: _config.gameType,
        playMode: _config.playMode,
        roundCount: _config.totalRounds,
      );

      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => DrawingResultPage(
            config: _config,
            initialRounds: rounds,
            authController: widget.authController,
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () {
            if (_currentStep > 1) {
              setState(() => _currentStep--);
            } else {
              Navigator.pop(context);
            }
          },
        ),
        title: Column(
          children: [
            Text(
              'LANGKAH $_currentStep DARI 4',
              style: AppTextStyles.caption.copyWith(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.1,
                color: const Color(0xFF94A3B8),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              'Create new game',
              style: AppTextStyles.h2.copyWith(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
            ),
          ],
        ),
        centerTitle: true,
        actions: [
          // Step dots
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: Row(
              children: List.generate(4, (index) {
                final isSelected = index + 1 == _currentStep;
                return Container(
                  margin: const EdgeInsets.symmetric(horizontal: 2.5),
                  width: isSelected ? 8 : 6,
                  height: isSelected ? 8 : 6,
                  decoration: BoxDecoration(
                    color: isSelected ? AppColors.matchaDark : const Color(0xFFCBD5E1),
                    shape: BoxShape.circle,
                  ),
                );
              }),
            ),
          ),
        ],
      ),
      body: SafeArea(
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 250),
          child: _buildCurrentStepView(),
        ),
      ),
    );
  }

  Widget _buildCurrentStepView() {
    switch (_currentStep) {
      case 1:
        return _buildStep1SportSelection();
      case 2:
        return _buildStep2GameTypeSelection();
      case 3:
        return _buildStep3GameConfig();
      case 4:
        return _buildStep4PlayerRoster();
      default:
        return const SizedBox();
    }
  }

  // --- STEP 1: SPORT SELECTION ---
  Widget _buildStep1SportSelection() {
    return ListView(
      key: const ValueKey(1),
      padding: const EdgeInsets.all(20),
      children: [
        Text(
          'Select sport type',
          style: AppTextStyles.h2.copyWith(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF0F172A),
          ),
        ),
        const SizedBox(height: 14),
        _buildSportCard(
          title: 'Padel',
          subtitle: 'Match Padel Tournament & Americano format',
          icon: Icons.sports_kabaddi,
          isSelected: _config.sport == 'Padel',
          onTap: () {
            setState(() {
              _config.sport = 'Padel';
              _currentStep = 2;
            });
          },
        ),
        const SizedBox(height: 12),
        _buildSportCard(
          title: 'Tennis',
          subtitle: 'Tennis single, double, sets & game scoring',
          icon: Icons.sports_tennis,
          isSelected: _config.sport == 'Tennis',
          onTap: () {
            setState(() {
              _config.sport = 'Tennis';
              _currentStep = 2;
            });
          },
        ),
      ],
    );
  }

  Widget _buildSportCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : Colors.transparent,
            width: isSelected ? 1.8 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.matchaSoftLime,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: AppColors.matchaDark, size: 24),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: AppTextStyles.h2.copyWith(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: AppTextStyles.caption.copyWith(
                      fontSize: 12,
                      color: const Color(0xFF64748B),
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFF94A3B8)),
          ],
        ),
      ),
    );
  }

  // --- STEP 2: GAME TYPE SELECTION ---
  Widget _buildStep2GameTypeSelection() {
    final gameTypes = [
      {
        'title': 'Americano',
        'badge': 'POPULAR',
        'badgeColor': const Color(0xFF22C55E),
        'desc': 'Semua pemain berpasangan secara bergantian (Round Robin)',
        'icon': Icons.swap_horiz_rounded,
        'isEnabled': true,
      },
      {
        'title': 'Team Americano',
        'badge': 'FIXED TEAM',
        'badgeColor': const Color(0xFF3B82F6),
        'desc': 'Format pasangan tetap/tim tetap saling melawan seluruh tim lainnya',
        'icon': Icons.group_rounded,
        'isEnabled': true,
      },
      {
        'title': 'Mexicano',
        'badge': 'SEGERA HADIR',
        'badgeColor': const Color(0xFF94A3B8),
        'desc': 'Sistem berpasangan peringkat sementara agar pertandingan selalu imbang',
        'icon': Icons.trending_up_rounded,
        'isEnabled': false,
      },
      {
        'title': 'Team Mexicano',
        'badge': 'SEGERA HADIR',
        'badgeColor': const Color(0xFF94A3B8),
        'desc': 'Format Mexicano kompetitif dengan pasangan tim yang tetap',
        'icon': Icons.view_comfortable_rounded,
        'isEnabled': false,
      },
      {
        'title': 'Mixicano',
        'badge': 'SEGERA HADIR',
        'badgeColor': const Color(0xFF94A3B8),
        'desc': 'Sistem selalu memasangkan 1 pria & 1 wanita dalam tiap tim secara dinamis',
        'icon': Icons.favorite_border_rounded,
        'isEnabled': false,
      },
      {
        'title': 'Mix Americano',
        'badge': 'SEGERA HADIR',
        'badgeColor': const Color(0xFF94A3B8),
        'desc': 'Dilarang berpasangan sama, selalu dengan komposisi pria dan wanita seimbang',
        'icon': Icons.volunteer_activism_rounded,
        'isEnabled': false,
      },
      {
        'title': 'King of the Court',
        'badge': 'SEGERA HADIR',
        'badgeColor': const Color(0xFF94A3B8),
        'desc': 'Pemenang court naik ke court atas, tim kalah turun ke court bawah',
        'icon': Icons.emoji_events_outlined,
        'isEnabled': false,
      },
    ];

    return ListView(
      key: const ValueKey(2),
      padding: const EdgeInsets.all(20),
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              _config.sport.isNotEmpty
                  ? 'Select game type (${_config.sport})'
                  : 'Select game type',
              style: AppTextStyles.h2.copyWith(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: const Color(0xFF0F172A),
              ),
            ),
            Text(
              'Pilih format turnamen/mabar',
              style: AppTextStyles.caption.copyWith(
                fontSize: 11,
                color: const Color(0xFF94A3B8),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        ...gameTypes.map((item) {
          final title = item['title'] as String;
          final isEnabled = item['isEnabled'] as bool? ?? true;
          final isSelected = _config.gameType.isNotEmpty && _config.gameType == title;

          return Container(
            margin: const EdgeInsets.only(bottom: 12),
            child: Opacity(
              opacity: isEnabled ? 1.0 : 0.55,
              child: InkWell(
                borderRadius: BorderRadius.circular(16),
                onTap: isEnabled
                    ? () {
                        setState(() {
                          _config.gameType = title;
                          if (title.toLowerCase().contains('team')) {
                            _config.playMode = 'Double';
                          }
                          _currentStep = 3;
                        });
                      }
                    : null,
                child: Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: isEnabled ? Colors.white : const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: isSelected
                          ? AppColors.matchaDark
                          : isEnabled
                              ? Colors.transparent
                              : const Color(0xFFE2E8F0).withValues(alpha: 0.6),
                      width: isSelected ? 1.8 : 1,
                    ),
                    boxShadow: isEnabled
                        ? [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.04),
                              blurRadius: 6,
                              offset: const Offset(0, 2),
                            ),
                          ]
                        : null,
                  ),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: isEnabled ? AppColors.matchaSoftLime : const Color(0xFFE2E8F0),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Icon(
                          item['icon'] as IconData,
                          color: isEnabled ? AppColors.matchaDark : const Color(0xFF94A3B8),
                          size: 20,
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
                                  title,
                                  style: AppTextStyles.h2.copyWith(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w800,
                                    color: isEnabled ? const Color(0xFF0F172A) : const Color(0xFF64748B),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: (item['badgeColor'] as Color).withValues(alpha: 0.12),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    item['badge'] as String,
                                    style: TextStyle(
                                      fontSize: 9,
                                      fontWeight: FontWeight.w800,
                                      color: item['badgeColor'] as Color,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 3),
                            Text(
                              item['desc'] as String,
                              style: AppTextStyles.caption.copyWith(
                                fontSize: 11,
                                color: const Color(0xFF64748B),
                              ),
                            ),
                          ],
                        ),
                      ),
                      Icon(
                        isEnabled ? Icons.chevron_right_rounded : Icons.lock_outline_rounded,
                        color: const Color(0xFF94A3B8),
                        size: isEnabled ? 20 : 18,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        }),
      ],
    );
  }

  // --- STEP 3: GAME CONFIG ---
  Widget _buildStep3GameConfig() {
    return ListView(
      key: const ValueKey(3),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
      children: [
        // Selected Format Banner
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFE2E8F0)),
            boxShadow: [
              BoxShadow(
                color: AppColors.matchaDark.withValues(alpha: 0.03),
                blurRadius: 10,
                offset: const Offset(0, 4),
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
                child: const Icon(Icons.sports_tennis_rounded, color: AppColors.matchaDark, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'FORMAT PERTANDINGAN',
                      style: AppTextStyles.caption.copyWith(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.1,
                        color: const Color(0xFF94A3B8),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${_config.sport} • ${_config.gameType}',
                      style: AppTextStyles.h2.copyWith(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF0F172A),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // Form Card (matching create_session_page style)
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: const Color(0xFFE2E8F0)),
            boxShadow: [
              BoxShadow(
                color: AppColors.matchaDark.withValues(alpha: 0.04),
                blurRadius: 20,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Activity Name
              _buildSectionLabel('Activity Name *'),
              const SizedBox(height: 6),
              TextField(
                controller: _activityNameController,
                onChanged: (val) => _config.activityName = val,
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                decoration: InputDecoration(
                  hintText: 'Contoh: ${_config.sport} Weekend Fun / Tenis ITB',
                  hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                  filled: true,
                  fillColor: const Color(0xFFF8FAFC),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5),
                  ),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '*Nama ini akan menjadi judul kartu game di Dashboard.',
                style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
              ),
              const SizedBox(height: 20),
              const Divider(height: 1, color: Color(0xFFF1F5F9)),
              const SizedBox(height: 20),

              // Numbers of Court
              _buildSectionLabel('Numbers of Court *'),
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    value: _config.courtCount,
                    isExpanded: true,
                    icon: const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF94A3B8)),
                    items: [1, 2, 3, 4].map((count) {
                      return DropdownMenuItem<int>(
                        value: count,
                        child: Text('$count Court', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setState(() => _config.courtCount = val);
                    },
                  ),
                ),
              ),
              const SizedBox(height: 20),
              const Divider(height: 1, color: Color(0xFFF1F5F9)),
              const SizedBox(height: 20),

              // Venue
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _buildSectionLabel('Venue *'),
                  InkWell(
                    onTap: () => _showQuickAddVenueModal(),
                    borderRadius: BorderRadius.circular(20),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppColors.matchaDark,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.add_rounded, size: 13, color: Color(0xFFA8E63A)),
                          SizedBox(width: 4),
                          Text(
                            'Tambah Venue',
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
              ),
              const SizedBox(height: 6),
              InkWell(
                onTap: _isLoadingVenues ? null : _showVenueSearchPickerModal,
                borderRadius: BorderRadius.circular(14),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: _config.venueId != null ? AppColors.matchaDark.withValues(alpha: 0.5) : const Color(0xFFE2E8F0),
                      width: _config.venueId != null ? 1.5 : 1,
                    ),
                  ),
                  child: _isLoadingVenues
                      ? const Padding(
                          padding: EdgeInsets.symmetric(vertical: 4),
                          child: Center(child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.matchaDark))),
                        )
                      : Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: _config.venueId != null ? AppColors.matchaSoftLime : const Color(0xFFE2E8F0),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Icon(
                                Icons.location_on_rounded,
                                color: _config.venueId != null ? AppColors.matchaDark : const Color(0xFF94A3B8),
                                size: 18,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _config.venueName ?? 'Pilih Lapangan Venue...',
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: _config.venueName != null ? FontWeight.w700 : FontWeight.normal,
                                      color: _config.venueName != null ? const Color(0xFF0F172A) : const Color(0xFF94A3B8),
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  if (_config.venueId != null && _venues.any((v) => v.venueId == _config.venueId)) ...[
                                    const SizedBox(height: 2),
                                    Text(
                                      _venues.firstWhere((v) => v.venueId == _config.venueId).kota ??
                                          _venues.firstWhere((v) => v.venueId == _config.venueId).alamat ??
                                          "Lokasi Terdaftar",
                                      style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.all(6),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: const Color(0xFFE2E8F0)),
                              ),
                              child: const Icon(Icons.search_rounded, size: 16, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                ),
              ),
              const SizedBox(height: 20),
              const Divider(height: 1, color: Color(0xFFF1F5F9)),
              const SizedBox(height: 20),

              // Scoring System
              _buildSectionLabel('Scoring System'),
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<String>(
                    value: _scoringSystemItems.any((item) => item.value == _config.scoringSystem)
                        ? _config.scoringSystem
                        : 'Total of 3 Poin',
                    isExpanded: true,
                    icon: const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF94A3B8)),
                    items: _scoringSystemItems,
                    onChanged: (val) {
                      if (val != null && !val.startsWith('__header_')) {
                        setState(() => _config.scoringSystem = val);
                      }
                    },
                  ),
                ),
              ),
              const SizedBox(height: 20),
              const Divider(height: 1, color: Color(0xFFF1F5F9)),
              const SizedBox(height: 20),

              // Leaderboard Ranked by
              _buildSectionLabel('Leaderboard Ranked by'),
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(
                    child: _buildSegmentButton(
                      label: 'Point',
                      icon: Icons.check_circle_outline_rounded,
                      isSelected: _config.leaderboardRankedBy == 'Point',
                      onTap: () => setState(() => _config.leaderboardRankedBy = 'Point'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _buildSegmentButton(
                      label: 'Win',
                      icon: Icons.emoji_events_outlined,
                      isSelected: _config.leaderboardRankedBy == 'Win',
                      onTap: () => setState(() => _config.leaderboardRankedBy = 'Win'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              const Divider(height: 1, color: Color(0xFFF1F5F9)),
              const SizedBox(height: 20),

              // Jenis Permainan
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _buildSectionLabel('Jenis Permainan'),
                  if (_config.gameType.toLowerCase().contains('team'))
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEFF6FF),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: const Color(0xFFBFDBFE)),
                      ),
                      child: const Text(
                        'Double Only (Team Format)',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF1D4ED8),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(
                    child: _buildPlayModeCard(
                      title: 'Double',
                      subtitle: '2vs2',
                      icon: Icons.people_alt_outlined,
                      isSelected: _config.playMode == 'Double',
                      isEnabled: true,
                      onTap: () => setState(() => _config.playMode = 'Double'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _buildPlayModeCard(
                      title: 'Single',
                      subtitle: _config.gameType.toLowerCase().contains('team') ? 'Tidak Tersedia' : '1vs1',
                      icon: Icons.person_outline_rounded,
                      isSelected: _config.playMode == 'Single',
                      isEnabled: !_config.gameType.toLowerCase().contains('team'),
                      onTap: _config.gameType.toLowerCase().contains('team')
                          ? null
                          : () => setState(() => _config.playMode = 'Single'),
                    ),
                  ),
                ],
              ),
              if (_config.gameType.toLowerCase().contains('team')) ...[
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFDBEAFE)),
                  ),
                  child: const Row(
                    children: [
                      Icon(Icons.info_outline_rounded, size: 14, color: Color(0xFF2563EB)),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Team Americano adalah format tim berpasangan tetap, sehingga otomatis terkunci pada mode Double (2 vs 2).',
                          style: TextStyle(fontSize: 10.5, color: Color(0xFF1E40AF), fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 24),

        // Submit Button
        SizedBox(
          width: double.infinity,
          height: 48,
          child: ElevatedButton(
            onPressed: () {
              final trimmedName = _activityNameController.text.trim();
              if (trimmedName.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Nama aktivitas wajib diisi.'),
                    backgroundColor: Colors.orange,
                    behavior: SnackBarBehavior.floating,
                  ),
                );
                return;
              }
              if (_config.venueId == null) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Silakan pilih venue terlebih dahulu.'),
                    backgroundColor: Colors.orange,
                    behavior: SnackBarBehavior.floating,
                  ),
                );
                return;
              }
              _config.activityName = trimmedName;
              setState(() => _currentStep = 4);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.matchaDark,
              disabledBackgroundColor: const Color(0xFFCBD5E1),
              foregroundColor: Colors.white,
              disabledForegroundColor: const Color(0xFF94A3B8),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              elevation: 0,
            ),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('Lanjut & Atur Daftar Pemain', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                SizedBox(width: 8),
                Icon(Icons.arrow_forward_rounded, size: 18),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSectionLabel(String label) {
    return Text(
      label,
      style: const TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w700,
        color: Color(0xFF334155),
      ),
    );
  }

  Widget _buildSegmentButton({
    required String label,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.matchaDark : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 16, color: isSelected ? Colors.white : const Color(0xFF64748B)),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.bold,
                color: isSelected ? Colors.white : const Color(0xFF334155),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPlayModeCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool isSelected,
    bool isEnabled = true,
    VoidCallback? onTap,
  }) {
    return GestureDetector(
      onTap: isEnabled ? onTap : null,
      child: Opacity(
        opacity: isEnabled ? 1.0 : 0.45,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
          decoration: BoxDecoration(
            color: isSelected ? const Color(0xFFF0FDF4) : const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
              width: isSelected ? 1.5 : 1,
            ),
          ),
          child: Column(
            children: [
              Icon(icon, size: 22, color: isSelected ? AppColors.matchaDark : const Color(0xFF64748B)),
              const SizedBox(height: 6),
              Text(
                title,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                  color: isSelected ? AppColors.matchaDark : const Color(0xFF0F172A),
                ),
              ),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 11,
                  color: isSelected ? AppColors.matchaDark.withValues(alpha: 0.8) : const Color(0xFF64748B),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // --- STEP 4: PLAYER ROSTER ---
  Widget _buildStep4PlayerRoster() {
    final isFirstTo = _config.scoringSystem.toLowerCase().startsWith('first to');
    final isTeamAmericano = _config.gameType.toLowerCase().contains('team');
    final isSingle = _config.playMode == 'Single';
    final numCourts = _config.courtCount;
    final exactRequired = isSingle ? (numCourts * 2) : (numCourts * 4);
    final minRequired = isSingle ? 2 : 4;
    final modeLabel = isSingle ? 'Single (1 vs 1)' : 'Double (2 vs 2)';

    bool canGenerate = false;
    String ctaButtonText = 'Generate Drawing & Start Game';
    Color ctaButtonBg = AppColors.matchaDark;
    Color ctaButtonFg = Colors.white;
    String quotaSubtitle = '*Minimal $minRequired pemain untuk generate drawing';
    String? warningBanner;

    if (isFirstTo) {
      quotaSubtitle = '*Wajib tepat $exactRequired pemain ($modeLabel, $numCourts Court)';
      if (_config.players.length < exactRequired) {
        canGenerate = false;
        final remaining = exactRequired - _config.players.length;
        ctaButtonText = '${_config.scoringSystem}: Butuh $remaining Pemain Lagi (${_config.players.length}/$exactRequired)';
      } else if (_config.players.length > exactRequired) {
        canGenerate = false;
        final excess = _config.players.length - exactRequired;
        ctaButtonText = '${_config.scoringSystem}: Kelebihan $excess Pemain (Wajib $exactRequired)';
        ctaButtonBg = const Color(0xFFFEE2E2);
        ctaButtonFg = const Color(0xFF991B1B);
        warningBanner = 'Kelebihan $excess pemain untuk format ${_config.scoringSystem} ($modeLabel). Hapus pemain yang berlebih agar pas tepat $exactRequired orang.';
      } else {
        canGenerate = true;
        ctaButtonText = 'Generate Drawing & Start Game (${_config.scoringSystem} • ${isSingle ? "1v1" : "2v2"})';
      }
    } else if (isTeamAmericano) {
      quotaSubtitle = '*Minimal 4 pemain & harus genap (Pasangan Tetap)';
      if (_config.players.length < 4) {
        canGenerate = false;
        ctaButtonText = 'Team Americano: Minimal 4 Pemain (${_config.players.length}/4)';
      } else if (_config.players.length % 2 != 0) {
        canGenerate = false;
        ctaButtonText = 'Team Americano Butuh Pemain Genap (${_config.players.length} Pemain)';
        ctaButtonBg = const Color(0xFFFEF3C7);
        ctaButtonFg = const Color(0xFF92400E);
        warningBanner = 'Jumlah pemain Team Americano harus genap (4, 6, 8, dst) karena setiap tim beranggotakan 2 orang tetap.';
      } else {
        canGenerate = true;
        ctaButtonText = 'Generate Drawing & Start Game (Team Americano • Fixed Pairs)';
      }
    } else {
      quotaSubtitle = isSingle
          ? '*Minimal 2 pemain untuk Americano Single (1 vs 1)'
          : '*Minimal 4 pemain untuk rotasi Americano Double (2 vs 2)';
      if (_config.players.length < minRequired) {
        canGenerate = false;
        ctaButtonText = 'Tambahkan Minimal $minRequired Pemain (${isSingle ? "Single 1v1" : "Double 2v2"})';
      } else {
        canGenerate = true;
        ctaButtonText = 'Generate Drawing & Start Game (${isSingle ? "Single 1v1" : "Double 2v2"})';
      }
    }

    return Column(
      key: const ValueKey(4),
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              // Summary card
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Text(
                            _config.activityName.isEmpty ? '${_config.sport} ${_config.gameType}' : _config.activityName,
                            style: AppTextStyles.h2.copyWith(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              color: const Color(0xFF0F172A),
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppColors.matchaSoftLime,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            isFirstTo ? 'Direct Match' : (isTeamAmericano ? 'Fixed Pairs' : 'Rotasi Tim'),
                            style: const TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              color: AppColors.matchaDark,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${_config.gameType} • ${_config.playMode} (${_config.playMode == "Double" ? "2v2" : "1v1"}) • ${_config.courtCount} Court • ${_config.venueName ?? "Venue"} • ${_config.scoringSystem}',
                      style: AppTextStyles.caption.copyWith(
                        fontSize: 11,
                        color: const Color(0xFF64748B),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),

              // Warning Banner (If any)
              if (warningBanner != null) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  margin: const EdgeInsets.only(bottom: 14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEF2F2),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFFECACA)),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.warning_amber_rounded, color: Color(0xFFDC2626), size: 18),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          warningBanner,
                          style: const TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFFB91C1C),
                            height: 1.35,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              // Player List Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    isFirstTo
                        ? 'Player List ( ${_config.players.length} / $exactRequired )'
                        : 'Player List ( ${_config.players.length} )',
                    style: AppTextStyles.h2.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                  Text(
                    quotaSubtitle,
                    style: AppTextStyles.caption.copyWith(
                      fontSize: 10,
                      color: isFirstTo && _config.players.length > exactRequired
                          ? const Color(0xFFDC2626)
                          : const Color(0xFF94A3B8),
                      fontWeight: isFirstTo && _config.players.length > exactRequired
                          ? FontWeight.bold
                          : FontWeight.normal,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),

              // Add Yourself & Add Player Buttons
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _addCurrentHost,
                      icon: const Icon(Icons.person_add_outlined, size: 16),
                      label: const Text('+ ADD YOURSELF', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.matchaDark,
                        side: const BorderSide(color: Color(0xFFCBD5E1)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: _openAddPlayerModal,
                      icon: const Icon(Icons.group_add_outlined, size: 16),
                      label: const Text('+ ADD PLAYER / GUEST', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.matchaSoftLime,
                        foregroundColor: AppColors.matchaDark,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // List of Added Players or Empty State
              if (_config.players.isEmpty)
                Container(
                  padding: const EdgeInsets.all(28),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      const Icon(Icons.sports_tennis_rounded, size: 40, color: Color(0xFFCBD5E1)),
                      const SizedBox(height: 10),
                      Text(
                        'Great moments are meant to be shared.',
                        style: AppTextStyles.caption.copyWith(
                          fontWeight: FontWeight.bold,
                          color: const Color(0xFF475569),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        isFirstTo
                            ? 'Tambahkan tepat $exactRequired pemain untuk memulai pertandingan First to Point.'
                            : 'Tambahkan minimal $minRequired pemain untuk memulai/mengaktifkan drawing live.',
                        textAlign: TextAlign.center,
                        style: AppTextStyles.caption.copyWith(
                          fontSize: 11,
                          color: const Color(0xFF94A3B8),
                        ),
                      ),
                    ],
                  ),
                )
              else
                ...List.generate(_config.players.length, (index) {
                  final player = _config.players[index];
                  final isExcess = isFirstTo && (index >= exactRequired);

                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    decoration: BoxDecoration(
                      color: isExcess ? const Color(0xFFFFF1F2) : Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: isExcess ? const Color(0xFFFECDD3) : const Color(0xFFE2E8F0),
                        width: isExcess ? 1.5 : 1.0,
                      ),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 24,
                          height: 24,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: isExcess ? const Color(0xFFFFE4E6) : const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            '${index + 1}',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: isExcess ? const Color(0xFFE11D48) : const Color(0xFF64748B),
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
                                      player.name,
                                      style: AppTextStyles.h2.copyWith(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w800,
                                        color: isExcess ? const Color(0xFF9F1239) : const Color(0xFF0F172A),
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  if (isExcess) ...[
                                    const SizedBox(width: 6),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFFFE4E6),
                                        borderRadius: BorderRadius.circular(4),
                                      ),
                                      child: const Text(
                                        'Kelebihan',
                                        style: TextStyle(
                                          fontSize: 9,
                                          fontWeight: FontWeight.bold,
                                          color: Color(0xFFE11D48),
                                        ),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              Text(
                                '${player.gender} • ${player.isGuest ? "Guest" : "Member"}',
                                style: AppTextStyles.caption.copyWith(
                                  fontSize: 10,
                                  color: isExcess ? const Color(0xFFFB7185) : const Color(0xFF94A3B8),
                                ),
                              ),
                            ],
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: isExcess ? const Color(0xFFFFE4E6) : AppColors.matchaSoftLime,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            player.level,
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              color: isExcess ? const Color(0xFFBE123C) : AppColors.matchaDark,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton(
                          icon: const Icon(Icons.delete_outline_rounded, size: 18, color: Color(0xFFEF4444)),
                          onPressed: () {
                            setState(() {
                              _config.players.removeAt(index);
                            });
                          },
                        ),
                      ],
                    ),
                  );
                }),
            ],
          ),
        ),

        // Action Button
        Container(
          padding: const EdgeInsets.all(20),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
          ),
          child: SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton(
              onPressed: (canGenerate && !_isSavingHostGame) ? _onGenerateDrawing : null,
              style: ElevatedButton.styleFrom(
                backgroundColor: canGenerate ? ctaButtonBg : const Color(0xFFCBD5E1),
                disabledBackgroundColor: (warningBanner != null) ? ctaButtonBg : const Color(0xFFCBD5E1),
                foregroundColor: canGenerate ? ctaButtonFg : const Color(0xFF94A3B8),
                disabledForegroundColor: (warningBanner != null) ? ctaButtonFg : const Color(0xFF94A3B8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                elevation: 0,
              ),
              child: _isSavingHostGame
                  ? const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.2,
                            color: Colors.white,
                          ),
                        ),
                        SizedBox(width: 12),
                        Text(
                          'Menyimpan Sesi & Menyiapkan Jadwal...',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                      ],
                    )
                  : Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          canGenerate ? Icons.shuffle_rounded : Icons.lock_outline_rounded,
                          size: 18,
                        ),
                        const SizedBox(width: 8),
                        Flexible(
                          child: Text(
                            ctaButtonText,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        ),
      ],
    );
  }
}

// --- ADD PLAYER MODAL (DUAL TAB) ---
class _AddPlayerBottomSheet extends StatefulWidget {
  final List<GamePlayerItem> currentPlayers;
  final int? sessionId;
  final int? maxAllowed;
  final String? scoringSystem;
  final String? modeLabel;
  final int? courtCount;
  final ValueChanged<GamePlayerItem> onAddPlayer;

  const _AddPlayerBottomSheet({
    required this.onAddPlayer,
    this.currentPlayers = const [],
    this.sessionId,
    this.maxAllowed,
    this.scoringSystem,
    this.modeLabel,
    this.courtCount,
  });

  @override
  State<_AddPlayerBottomSheet> createState() => _AddPlayerBottomSheetState();
}

class _AddPlayerBottomSheetState extends State<_AddPlayerBottomSheet> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _manualNameController = TextEditingController();
  final SessionService _sessionService = SessionService();

  static const int _minSearchQueryLength = 2;

  String _selectedGender = 'Laki-laki';
  String _selectedLevel = 'Beginner';

  List<Map<String, dynamic>> _searchResults = [];
  bool _isSearching = false;
  String? _searchErrorMessage;
  Timer? _debounceTimer;

  bool _isSubmittingManual = false;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _searchController.dispose();
    _manualNameController.dispose();
    _debounceTimer?.cancel();
    super.dispose();
  }

  Future<void> _fetchDatabasePlayers(String query) async {
    final cleanQuery = query.trim();
    if (cleanQuery.length < _minSearchQueryLength) {
      if (mounted) {
        setState(() {
          _searchResults = [];
          _isSearching = false;
          _searchErrorMessage = null;
        });
      }
      return;
    }

    setState(() {
      _isSearching = true;
      _searchErrorMessage = null;
    });

    try {
      final res = await _sessionService.searchPlayers(query: cleanQuery, limit: 30);
      if (mounted) {
        setState(() {
          _searchResults = res;
          _isSearching = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _isSearching = false;
          _searchErrorMessage = 'Terjadi kesalahan saat mengambil data player';
        });
      }
    }
  }

  void _onSearchChanged(String query) {
    _debounceTimer?.cancel();
    final cleanQuery = query.trim();

    if (cleanQuery.length < _minSearchQueryLength) {
      setState(() {
        _searchResults = [];
        _isSearching = false;
        _searchErrorMessage = null;
      });
      return;
    }

    _debounceTimer = Timer(const Duration(milliseconds: 300), () {
      _fetchDatabasePlayers(cleanQuery);
    });
  }

  bool _isPlayerAlreadyAdded(Map<String, dynamic> dbPlayer) {
    final dbPlayerId = dbPlayer['player_id'] is int
        ? dbPlayer['player_id'] as int
        : int.tryParse(dbPlayer['player_id']?.toString() ?? '');
    final dbUserId = dbPlayer['user_id'] is int
        ? dbPlayer['user_id'] as int
        : int.tryParse(dbPlayer['user_id']?.toString() ?? '');
    final dbName = (dbPlayer['nama'] ?? '').toString().trim().toLowerCase();

    return widget.currentPlayers.any((p) {
      if (dbPlayerId != null && p.playerId == dbPlayerId) return true;
      if (dbUserId != null && p.userId == dbUserId) return true;
      return p.name.trim().toLowerCase() == dbName;
    });
  }

  Future<void> _onSelectDatabasePlayer(Map<String, dynamic> u) async {
    if (widget.maxAllowed != null && widget.currentPlayers.length >= widget.maxAllowed!) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Kapasitas pemain untuk format ${widget.scoringSystem ?? "ini"} (${widget.modeLabel ?? ""}) dengan ${widget.courtCount ?? 1} court sudah penuh (maksimal ${widget.maxAllowed} pemain).',
          ),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final name = (u['nama'] ?? 'Player').toString();
    final rawGender = (u['gender'] ?? '').toString();
    final gender = (rawGender.toLowerCase() == 'female' || rawGender.toLowerCase() == 'perempuan')
        ? 'Perempuan'
        : 'Laki-laki';
    final rawLevel = (u['level'] ?? '').toString();
    final level = rawLevel.isNotEmpty ? rawLevel : 'Beginner';
    final playerId = u['player_id'] is int ? u['player_id'] as int : int.tryParse(u['player_id'].toString());
    final userId = u['user_id'] is int ? u['user_id'] as int : int.tryParse(u['user_id']?.toString() ?? '');
    final isGuest = userId == null;
    final avatarUrl = u['foto'] as String?;

    if (_isPlayerAlreadyAdded(u)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Pemain "$name" sudah ada dalam daftar game.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    if (widget.sessionId != null && playerId != null) {
      try {
        await _sessionService.joinSession(
          sessionId: widget.sessionId!,
          playerId: playerId,
        );
      } catch (_) {
        // Fallback gracefully if already joined or offline
      }
    }

    widget.onAddPlayer(
      GamePlayerItem(
        id: isGuest ? 'guest_db_$playerId' : 'user_$userId',
        playerId: playerId,
        userId: userId,
        name: name,
        gender: gender,
        level: level,
        isGuest: isGuest,
        avatarUrl: avatarUrl,
      ),
    );

    if (mounted) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Pemain "$name" berhasil ditambahkan.'),
          behavior: SnackBarBehavior.floating,
          backgroundColor: AppColors.matchaDark,
        ),
      );
    }
  }

  Future<void> _onSubmitManualPlayer() async {
    if (widget.maxAllowed != null && widget.currentPlayers.length >= widget.maxAllowed!) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Kapasitas pemain untuk format ${widget.scoringSystem ?? "ini"} (${widget.modeLabel ?? ""}) dengan ${widget.courtCount ?? 1} court sudah penuh (maksimal ${widget.maxAllowed} pemain).',
          ),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final name = _manualNameController.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Nama player tidak boleh kosong.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final isDuplicate = widget.currentPlayers.any(
      (p) => p.name.trim().toLowerCase() == name.toLowerCase(),
    );
    if (isDuplicate) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Pemain dengan nama "$name" sudah ada di daftar.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() => _isSubmittingManual = true);

    try {
      final genderDb = _selectedGender == 'Laki-laki' ? 'Male' : 'Female';
      final guestPlayerId = await _sessionService.registerGuestPlayer(
        nama: name,
        gender: genderDb,
        level: _selectedLevel,
      );

      if (widget.sessionId != null) {
        try {
          await _sessionService.joinSession(
            sessionId: widget.sessionId!,
            playerId: guestPlayerId,
          );
        } catch (_) {
          // Gracefully continue
        }
      }

      final playerItem = GamePlayerItem(
        id: 'guest_$guestPlayerId',
        playerId: guestPlayerId,
        name: name,
        gender: _selectedGender,
        level: _selectedLevel,
        isGuest: true,
      );

      if (!mounted) return;
      widget.onAddPlayer(playerItem);
      Navigator.pop(context);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Pemain tamu "$name" berhasil didaftarkan & ditambahkan.'),
          behavior: SnackBarBehavior.floating,
          backgroundColor: AppColors.matchaDark,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmittingManual = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal mendaftarkan pemain tamu: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _showGenderPicker() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 36,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const Text(
                'Pilih Gender',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 12),
              ...['Laki-laki', 'Perempuan'].map((gender) {
                final isSelected = _selectedGender == gender;
                return InkWell(
                  onTap: () {
                    setState(() => _selectedGender = gender);
                    Navigator.pop(ctx);
                  },
                  borderRadius: BorderRadius.circular(10),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    margin: const EdgeInsets.only(bottom: 8),
                    decoration: BoxDecoration(
                      color: isSelected ? const Color(0xFFF0FDF4) : Colors.transparent,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          gender,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                            color: isSelected ? AppColors.matchaDark : const Color(0xFF1E293B),
                          ),
                        ),
                        if (isSelected)
                          const Icon(Icons.check_circle_rounded, size: 18, color: AppColors.matchaDark),
                      ],
                    ),
                  ),
                );
              }),
            ],
          ),
        );
      },
    );
  }

  void _showLevelPicker() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 36,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const Text(
                'Pilih Skill Level',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 12),
              ...['Beginner', 'Intermediate', 'Advanced'].map((lvl) {
                final isSelected = _selectedLevel == lvl;
                return InkWell(
                  onTap: () {
                    setState(() => _selectedLevel = lvl);
                    Navigator.pop(ctx);
                  },
                  borderRadius: BorderRadius.circular(10),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    margin: const EdgeInsets.only(bottom: 8),
                    decoration: BoxDecoration(
                      color: isSelected ? const Color(0xFFF0FDF4) : Colors.transparent,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          lvl,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                            color: isSelected ? AppColors.matchaDark : const Color(0xFF1E293B),
                          ),
                        ),
                        if (isSelected)
                          const Icon(Icons.check_circle_rounded, size: 18, color: AppColors.matchaDark),
                      ],
                    ),
                  ),
                );
              }),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
        top: 20,
        left: 20,
        right: 20,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Tambahkan Player',
                    style: AppTextStyles.h2.copyWith(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                  Text(
                    'Tambahkan pemain ke sesi ini',
                    style: AppTextStyles.caption.copyWith(fontSize: 11, color: const Color(0xFF64748B)),
                  ),
                ],
              ),
              IconButton(
                icon: const Icon(Icons.close_rounded, size: 20, color: Color(0xFF64748B)),
                onPressed: () => Navigator.pop(context),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Dual Tab Selector (Clean Underline Tab)
          Container(
            decoration: const BoxDecoration(
              border: Border(
                bottom: BorderSide(
                  color: AppColors.lightSurfaceBorder,
                  width: 1.0,
                ),
              ),
            ),
            child: TabBar(
              controller: _tabController,
              indicatorSize: TabBarIndicatorSize.label,
              indicator: const UnderlineTabIndicator(
                borderSide: BorderSide(
                  width: 2.5,
                  color: AppColors.matchaDark,
                ),
                borderRadius: BorderRadius.all(Radius.circular(2)),
              ),
              dividerColor: Colors.transparent,
              splashFactory: NoSplash.splashFactory,
              overlayColor: WidgetStateProperty.all(Colors.transparent),
              labelColor: AppColors.matchaDark,
              unselectedLabelColor: AppColors.textSecondary,
              labelStyle: AppTextStyles.button.copyWith(
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
              unselectedLabelStyle: AppTextStyles.bodyMedium.copyWith(
                fontSize: 13,
                fontWeight: FontWeight.w500,
                color: AppColors.textSecondary,
              ),
              tabs: const [
                Tab(
                  height: 38,
                  text: 'Dari Database',
                ),
                Tab(
                  height: 38,
                  text: 'Input Manual',
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Tab Content
          SizedBox(
            height: 300,
            child: TabBarView(
              controller: _tabController,
              children: [
                _buildDatabaseTab(),
                _buildManualTab(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Tab 1: Database Search
  Widget _buildDatabaseTab() {
    final cleanQuery = _searchController.text.trim();
    final availableResults = _searchResults.where((p) => !_isPlayerAlreadyAdded(p)).toList();

    return Column(
      children: [
        TextField(
          controller: _searchController,
          onChanged: _onSearchChanged,
          decoration: InputDecoration(
            hintText: 'Cari nama player...',
            hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
            prefixIcon: const Icon(Icons.search_rounded, size: 18, color: Color(0xFF64748B)),
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5),
            ),
          ),
        ),
        const SizedBox(height: 10),
        Expanded(
          child: _isSearching
              ? const Center(
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.matchaDark,
                  ),
                )
              : _searchErrorMessage != null
                  ? Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.error_outline_rounded, size: 30, color: Colors.redAccent),
                          const SizedBox(height: 8),
                          Text(
                            _searchErrorMessage!,
                            style: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 8),
                          TextButton.icon(
                            onPressed: () => _fetchDatabasePlayers(_searchController.text),
                            icon: const Icon(Icons.refresh_rounded, size: 16, color: AppColors.matchaDark),
                            label: const Text(
                              'Coba Lagi',
                              style: TextStyle(color: AppColors.matchaDark, fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ],
                      ),
                    )
                  : cleanQuery.isEmpty
                      ? const Center(
                          child: Text(
                            'Ketik nama player untuk mencari',
                            style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                          ),
                        )
                      : cleanQuery.length < _minSearchQueryLength
                          ? const Center(
                              child: Text(
                                'Ketik minimal 2 karakter untuk mencari',
                                style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                              ),
                            )
                          : _searchResults.isEmpty
                              ? const Center(
                                  child: Text(
                                    'Player tidak ditemukan',
                                    style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                                  ),
                                )
                              : availableResults.isEmpty
                                  ? const Center(
                                      child: Text(
                                        'Semua player yang cocok sudah ditambahkan ke game',
                                        style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                                      ),
                                    )
                                  : ListView.builder(
                              itemCount: availableResults.length,
                              itemBuilder: (ctx, idx) {
                                final u = availableResults[idx];
                                final name = (u['nama'] ?? 'Player').toString();
                                final rawGender = (u['gender'] ?? '').toString();
                                final gender = (rawGender.toLowerCase() == 'female' || rawGender.toLowerCase() == 'perempuan')
                                    ? 'Perempuan'
                                    : 'Laki-laki';
                                final rawLevel = (u['level'] ?? '').toString();
                                final level = rawLevel.isNotEmpty ? rawLevel : 'Beginner';
                                final isGuest = u['user_id'] == null;

                                return ListTile(
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 0),
                                  leading: CircleAvatar(
                                    backgroundColor: isGuest ? const Color(0xFFFEF3C7) : AppColors.matchaSoftLime,
                                    child: Text(
                                      name.isNotEmpty ? name[0].toUpperCase() : 'P',
                                      style: TextStyle(
                                        fontWeight: FontWeight.bold,
                                        color: isGuest ? const Color(0xFFD97706) : AppColors.matchaDark,
                                      ),
                                    ),
                                  ),
                                  title: Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          name,
                                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                      if (isGuest)
                                        Container(
                                          margin: const EdgeInsets.only(left: 6),
                                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: const Color(0xFFFEF3C7),
                                            borderRadius: BorderRadius.circular(4),
                                          ),
                                          child: const Text(
                                            'Guest',
                                            style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                                          ),
                                        ),
                                    ],
                                  ),
                                  subtitle: Text(
                                    '$gender • $level',
                                    style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                  ),
                                  trailing: IconButton(
                                    icon: const Icon(Icons.add_circle_outline_rounded, color: AppColors.matchaDark),
                                    onPressed: () => _onSelectDatabasePlayer(u),
                                  ),
                                );
                              },
                            ),
        ),
      ],
    );
  }

  // Tab 2: Manual Input
  Widget _buildManualTab() {
    return ListView(
      children: [
        const Text(
          'Nama Player',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: _manualNameController,
          decoration: InputDecoration(
            hintText: 'Masukkan nama player...',
            hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5),
            ),
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Gender',
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                  ),
                  const SizedBox(height: 4),
                  InkWell(
                    onTap: _showGenderPicker,
                    borderRadius: BorderRadius.circular(10),
                    child: Container(
                      height: 42,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            _selectedGender,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w500,
                              color: Color(0xFF1E293B),
                            ),
                          ),
                          const Icon(
                            Icons.keyboard_arrow_down_rounded,
                            size: 18,
                            color: Color(0xFF64748B),
                          ),
                        ],
                      ),
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
                  const Text(
                    'Skill Level',
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                  ),
                  const SizedBox(height: 4),
                  InkWell(
                    onTap: _showLevelPicker,
                    borderRadius: BorderRadius.circular(10),
                    child: Container(
                      height: 42,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            _selectedLevel,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w500,
                              color: Color(0xFF1E293B),
                            ),
                          ),
                          const Icon(
                            Icons.keyboard_arrow_down_rounded,
                            size: 18,
                            color: Color(0xFF64748B),
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
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: const Color(0xFFFFFBEB),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: const Color(0xFFFDE68A)),
          ),
          child: const Row(
            children: [
              Icon(Icons.info_outline_rounded, size: 16, color: Color(0xFFD97706)),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Pemain manual akan didaftarkan sebagai Guest Player di database MATCHA dan ditambahkan ke daftar pemain.',
                  style: TextStyle(fontSize: 10, color: Color(0xFF92400E)),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          height: 42,
          child: ElevatedButton(
            onPressed: _isSubmittingManual ? null : _onSubmitManualPlayer,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.matchaDark,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              elevation: 0,
              disabledBackgroundColor: AppColors.matchaDark.withValues(alpha: 0.6),
            ),
            child: _isSubmittingManual
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : const Text('+ Tambahkan Player', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          ),
        ),
      ],
    );
  }
}
