import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../court/data/venue_service.dart';
import '../../court/domain/court_model.dart';
import '../../court/domain/venue_model.dart';
import '../data/session_service.dart';

class CreateSessionPage extends StatefulWidget {
  final AuthController? authController;
  final int? initialVenueId;
  final int? initialSportId;

  const CreateSessionPage({
    super.key,
    this.authController,
    this.initialVenueId,
    this.initialSportId,
  });

  @override
  State<CreateSessionPage> createState() => _CreateSessionPageState();
}

class _CreateSessionPageState extends State<CreateSessionPage> {
  final _formKey = GlobalKey<FormState>();
  final SessionService _sessionService = SessionService();
  final VenueService _venueService = VenueService();

  // State Data
  List<VenueModel> _allVenues = [];
  bool _isLoading = true;
  bool _isSubmitting = false;

  // 1. Cabang Olahraga
  int _selectedSportId = 1; // 1: Padel, 2: Tennis

  // 2. Format Pertandingan & Sistem Skor
  String _selectedFormat = 'Americano'; // 'Americano' or 'Team Americano'
  String _selectedScoringSystem = 'Total of 3 Poin'; // 'Total of 3 Poin', 'Total of 4 Poin', 'Total of 7 Sets', 'First to 15 Points', 'First to 21 Points'

  // 3. Lokasi Venue & Lapangan
  VenueModel? _selectedVenue;
  CourtModel? _selectedCourt;

  // 4. Informasi & Judul
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _descController = TextEditingController();

  // 5. Jadwal & Kuota
  DateTime _selectedDate = DateTime.now().add(const Duration(days: 1));
  TimeOfDay _selectedTime = const TimeOfDay(hour: 18, minute: 30);
  String _selectedDuration = '2 Jam';
  String _selectedGameType = 'Double'; // 'Double' or 'Single'
  int _selectedQuota = 6;
  String _selectedLevel = 'All Level';

  bool get _isFirstToSystem =>
      _selectedScoringSystem.toLowerCase().startsWith('first to');

  bool get _isTeamAmericano => _selectedFormat == 'Team Americano';

  @override
  void initState() {
    super.initState();
    if (widget.initialSportId != null) {
      _selectedSportId = widget.initialSportId!;
    }
    _loadVenues();
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descController.dispose();
    super.dispose();
  }

  Future<void> _loadVenues() async {
    try {
      final venues = await _venueService.getVenues();
      if (!mounted) return;

      setState(() {
        _allVenues = venues;
        _isLoading = false;
      });

      _applySportSelection(_selectedSportId, initialVenueId: widget.initialVenueId);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal memuat venue: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _applySportSelection(int sportId, {int? initialVenueId}) {
    _selectedSportId = sportId;

    // Filter venues that have available courts for this sport
    final matchingVenues = _allVenues.where((v) {
      return v.courts.any((c) =>
          c.sportId == _selectedSportId &&
          (c.statusKetersediaan == null ||
              c.statusKetersediaan!.toLowerCase() == 'available'));
    }).toList();

    VenueModel? targetVenue;
    if (initialVenueId != null) {
      targetVenue = matchingVenues.where((v) => v.venueId == initialVenueId).firstOrNull;
    }
    targetVenue ??= matchingVenues.firstOrNull;

    _selectedVenue = targetVenue;

    if (_selectedVenue != null) {
      final matchingCourts = _selectedVenue!.courts.where((c) =>
          c.sportId == _selectedSportId &&
          (c.statusKetersediaan == null ||
              c.statusKetersediaan!.toLowerCase() == 'available')).toList();
      _selectedCourt = matchingCourts.firstOrNull;
    } else {
      _selectedCourt = null;
    }

    setState(() {});
  }

  void _onFormatChanged(String format) {
    setState(() {
      _selectedFormat = format;

      if (_isTeamAmericano) {
        // Team Americano is strictly Double
        _selectedGameType = 'Double';
        if (_isFirstToSystem) {
          _selectedQuota = 4;
        } else {
          // Default to 6 if odd or invalid
          if (_selectedQuota % 2 != 0 || _selectedQuota < 4) {
            _selectedQuota = 6;
          }
        }
      } else {
        // Americano regular
        if (_isFirstToSystem) {
          _selectedQuota = _selectedGameType == 'Double' ? 4 : 2;
        } else {
          _selectedQuota = _selectedGameType == 'Double' ? 6 : 2;
        }
      }
    });
  }

  void _onScoringSystemChanged(String? newSystem) {
    if (newSystem == null) return;
    setState(() {
      _selectedScoringSystem = newSystem;

      if (_isFirstToSystem) {
        // First to X: Exact constraint (Double: 4, Single: 2)
        if (_selectedGameType == 'Double') {
          _selectedQuota = 4;
        } else {
          _selectedQuota = 2;
        }
      } else {
        // Total of X: Flexible
        if (_isTeamAmericano) {
          _selectedQuota = 6;
        } else if (_selectedGameType == 'Double') {
          _selectedQuota = 6;
        } else {
          _selectedQuota = 2;
        }
      }
    });
  }

  void _onGameTypeChanged(String type) {
    if (_isTeamAmericano && type == 'Single') return; // Team Americano cannot be Single

    setState(() {
      _selectedGameType = type;

      if (_isFirstToSystem) {
        _selectedQuota = type == 'Double' ? 4 : 2;
      } else {
        _selectedQuota = type == 'Double' ? 6 : 2;
      }
    });
  }

  void _onVenueChanged(VenueModel? newVenue) {
    if (newVenue == null) return;
    setState(() {
      _selectedVenue = newVenue;
      final matchingCourts = _selectedVenue!.courts.where((c) =>
          c.sportId == _selectedSportId &&
          (c.statusKetersediaan == null ||
              c.statusKetersediaan!.toLowerCase() == 'available')).toList();
      _selectedCourt = matchingCourts.firstOrNull;
    });
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate.isBefore(now) ? now : _selectedDate,
      firstDate: now,
      lastDate: now.add(const Duration(days: 90)),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: AppColors.matchaDark,
              onPrimary: Colors.white,
              onSurface: Color(0xFF0F172A),
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() => _selectedDate = picked);
    }
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _selectedTime,
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: AppColors.matchaDark,
              onPrimary: Colors.white,
              onSurface: Color(0xFF0F172A),
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() => _selectedTime = picked);
    }
  }

  void _showQuickAddVenueModal() {
    final venueNameCtrl = TextEditingController();
    final cityCtrl = TextEditingController(text: 'Bandung');
    final addressCtrl = TextEditingController();
    int numberOfCourts = 2;
    bool isAddingVenue = false;

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
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: AppColors.matchaSoftLime,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Icon(
                                Icons.add_business_rounded,
                                size: 20,
                                color: AppColors.matchaDark,
                              ),
                            ),
                            const SizedBox(width: 10),
                            const Text(
                              'Tambah Venue Cepat',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0F172A),
                              ),
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
                    _buildFieldLabel('Nama Venue *'),
                    const SizedBox(height: 6),
                    TextField(
                      controller: venueNameCtrl,
                      decoration: _buildInputDecoration(hint: 'Contoh: Bonang Padel Arena & Club'),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildFieldLabel('Kota / Wilayah *'),
                              const SizedBox(height: 6),
                              TextField(
                                controller: cityCtrl,
                                decoration: _buildInputDecoration(hint: 'Contoh: Kota Bandung'),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildFieldLabel('Jumlah Court'),
                              const SizedBox(height: 6),
                              DropdownButtonFormField<int>(
                                initialValue: numberOfCourts,
                                decoration: _buildInputDecoration(hint: 'Jumlah'),
                                items: const [
                                  DropdownMenuItem(value: 1, child: Text('1 Court')),
                                  DropdownMenuItem(value: 2, child: Text('2 Court')),
                                  DropdownMenuItem(value: 3, child: Text('3 Court')),
                                  DropdownMenuItem(value: 4, child: Text('4 Court')),
                                ],
                                onChanged: (val) {
                                  if (val != null) setModalState(() => numberOfCourts = val);
                                },
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _buildFieldLabel('Alamat Lengkap'),
                    const SizedBox(height: 6),
                    TextField(
                      controller: addressCtrl,
                      decoration: _buildInputDecoration(hint: 'Contoh: Jl. Riau No. 123'),
                    ),
                    const SizedBox(height: 20),
                    SizedBox(
                      width: double.infinity,
                      height: 44,
                      child: ElevatedButton(
                        onPressed: isAddingVenue
                            ? null
                            : () async {
                                if (venueNameCtrl.text.trim().isEmpty) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(
                                      content: Text('Nama venue wajib diisi'),
                                      backgroundColor: Colors.orange,
                                    ),
                                  );
                                  return;
                                }

                                setModalState(() => isAddingVenue = true);
                                try {
                                  final messenger = ScaffoldMessenger.of(context);
                                  final newVenue = await _venueService.quickAddVenue(
                                    namaVenue: venueNameCtrl.text.trim(),
                                    kota: cityCtrl.text.trim(),
                                    alamat: addressCtrl.text.trim(),
                                    numberOfCourts: numberOfCourts,
                                    sportId: _selectedSportId,
                                    ownerUserId: widget.authController?.currentUser?.userId,
                                  );

                                  if (modalCtx.mounted) {
                                    Navigator.pop(modalCtx);
                                  }
                                  if (!mounted) return;

                                  // Reload venues & select newly added venue
                                  final updatedVenues = await _venueService.getVenues();
                                  if (!mounted) return;
                                  setState(() {
                                    _allVenues = updatedVenues;
                                    _applySportSelection(_selectedSportId, initialVenueId: newVenue.venueId);
                                  });

                                  messenger.showSnackBar(
                                    SnackBar(
                                      content: Text('Venue "${newVenue.namaVenue}" berhasil ditambahkan! 🎉'),
                                      backgroundColor: AppColors.matchaDark,
                                    ),
                                  );
                                } catch (e) {
                                  if (modalCtx.mounted) {
                                    setModalState(() => isAddingVenue = false);
                                  }
                                  if (mounted) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text('Gagal menambah venue: $e'),
                                        backgroundColor: Colors.redAccent,
                                      ),
                                    );
                                  }
                                }
                              },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.matchaDark,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: isAddingVenue
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                              )
                            : const Text('Simpan & Gunakan Venue', style: TextStyle(fontWeight: FontWeight.bold)),
                      ),
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

  Future<void> _submitForm() async {
    if (!_formKey.currentState!.validate()) return;

    if (_selectedVenue == null || _selectedCourt == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pilih venue dan court yang tersedia terlebih dahulu.'),
          backgroundColor: Colors.orange,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    // Constraint Validation for First to X
    if (_isFirstToSystem) {
      final requiredCount = _selectedGameType == 'Double' ? 4 : 2;
      if (_selectedQuota != requiredCount) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Format $_selectedScoringSystem ($_selectedGameType) membutuhkan tepat $requiredCount pemain.',
            ),
            backgroundColor: Colors.redAccent,
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }
    }

    // Constraint Validation for Team Americano: Must be even
    if (_isTeamAmericano && _selectedQuota % 2 != 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Jumlah pemain Team Americano harus genap (4, 6, 8, dst) karena setiap tim terdiri dari pasangan tetap.',
          ),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final user = widget.authController?.currentUser;
      final timeStr =
          '${_selectedTime.hour.toString().padLeft(2, '0')}:${_selectedTime.minute.toString().padLeft(2, '0')}';

      await _sessionService.createScheduleSession(
        sportId: _selectedSportId,
        venueId: _selectedVenue!.venueId,
        courtId: _selectedCourt!.courtId,
        namaSession: _titleController.text.trim(),
        tanggal: _selectedDate,
        jam: timeStr,
        durasi: _selectedDuration,
        jumlahPemain: _selectedQuota,
        jenisPermainan: _selectedGameType,
        scoringSystem: _selectedScoringSystem,
        levelRekomendasi: _selectedLevel,
        deskripsi: _descController.text.trim(),
        hostUserId: user?.userId,
        hostPlayerId: user?.playerId,
      );

      if (!mounted) return;
      Navigator.pop(context, true);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Sesi mabar "${_titleController.text.trim()}" berhasil dibuat dan dipublikasikan! 🎉',
          ),
          backgroundColor: AppColors.matchaDark,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal membuat sesi: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    // Matching venues for current sport
    final matchingVenues = _allVenues.where((v) {
      return v.courts.any((c) =>
          c.sportId == _selectedSportId &&
          (c.statusKetersediaan == null ||
              c.statusKetersediaan!.toLowerCase() == 'available'));
    }).toList();

    // Matching courts for current selected venue
    final matchingCourts = _selectedVenue != null
        ? _selectedVenue!.courts.where((c) =>
            c.sportId == _selectedSportId &&
            (c.statusKetersediaan == null ||
                c.statusKetersediaan!.toLowerCase() == 'available')).toList()
        : <CourtModel>[];

    final dateFormatted =
        '${_selectedDate.year}-${_selectedDate.month.toString().padLeft(2, '0')}-${_selectedDate.day.toString().padLeft(2, '0')}';
    final timeFormatted =
        '${_selectedTime.hour.toString().padLeft(2, '0')}:${_selectedTime.minute.toString().padLeft(2, '0')} WIB';

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 1,
        shadowColor: Colors.black12,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () => Navigator.pop(context),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'HOST OPEN SCHEDULE',
              style: TextStyle(
                fontSize: 9,
                fontWeight: FontWeight.w900,
                letterSpacing: 1.2,
                color: AppColors.matchaDark,
              ),
            ),
            const Text(
              'Buka Jadwal Mabar Baru',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w900,
                color: Color(0xFF0F172A),
              ),
            ),
          ],
        ),
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 16),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: AppColors.matchaSoftLime,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.calendar_month_rounded, size: 13, color: AppColors.matchaDark),
                const SizedBox(width: 4),
                Text(
                  'Publikasikan',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: AppColors.matchaDark,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.matchaDark),
            )
          : SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
              child: Form(
                key: _formKey,
                child: Container(
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
                      // ==========================================
                      // 1. PILIH CABANG OLAHRAGA
                      // ==========================================
                      _buildSectionHeader('1', 'PILIH CABANG OLAHRAGA'),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: _buildSportCard(
                              sportId: 1,
                              title: 'Padel',
                              subtitle: 'Pertandingan Padel',
                              icon: Icons.sports_tennis_rounded,
                              isSelected: _selectedSportId == 1,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildSportCard(
                              sportId: 2,
                              title: 'Tennis',
                              subtitle: 'Pertandingan Tennis',
                              icon: Icons.sports_baseball_rounded,
                              isSelected: _selectedSportId == 2,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 22),
                      const Divider(height: 1, color: Color(0xFFF1F5F9)),
                      const SizedBox(height: 20),

                      // ==========================================
                      // 2. PILIH FORMAT PERTANDINGAN & SISTEM SKOR
                      // ==========================================
                      _buildSectionHeader('2', 'PILIH FORMAT PERTANDINGAN & SISTEM SKOR'),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: _buildFormatCard(
                              formatKey: 'Americano',
                              badgeText: 'POPULER',
                              badgeColor: const Color(0xFF063B00),
                              badgeBg: const Color(0xFFEBF8D8),
                              title: 'Americano',
                              subtitle: 'Semua pemain berpasangan secara bergantian (Round-Robin)',
                              icon: Icons.sync_rounded,
                              isSelected: _selectedFormat == 'Americano',
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildFormatCard(
                              formatKey: 'Team Americano',
                              badgeText: 'FIXED TEAMS',
                              badgeColor: const Color(0xFF1D4ED8),
                              badgeBg: const Color(0xFFEFF6FF),
                              title: 'Team Americano',
                              subtitle: 'Setiap tim 2 orang tetap bertanding melawan seluruh tim lainnya',
                              icon: Icons.groups_rounded,
                              isSelected: _selectedFormat == 'Team Americano',
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),

                      // Dropdown Sistem Skor
                      _buildFieldLabel('Sistem Skor Pertandingan'),
                      const SizedBox(height: 6),
                      DropdownButtonFormField<String>(
                        key: ValueKey('scoring_system_dropdown_$_selectedScoringSystem'),
                        initialValue: _selectedScoringSystem,
                        decoration: _buildInputDecoration(hint: 'Pilih sistem poin yang digunakan'),
                        isExpanded: true,
                        items: _buildScoringDropdownItems(),
                        selectedItemBuilder: (BuildContext context) {
                          return _allScoringSystemItems.map((item) {
                            return Text(
                              item.value ?? '',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                            );
                          }).toList();
                        },
                        onChanged: (val) {
                          if (val != null && !val.startsWith('__header')) {
                            _onScoringSystemChanged(val);
                          }
                        },
                      ),
                      const SizedBox(height: 22),
                      const Divider(height: 1, color: Color(0xFFF1F5F9)),
                      const SizedBox(height: 20),

                      // ==========================================
                      // 3. INFORMASI & JUDUL MABAR
                      // ==========================================
                      _buildSectionHeader('3', 'INFORMASI & JUDUL MABAR'),
                      const SizedBox(height: 12),
                      _buildFieldLabel('Judul Sesi Mabar *'),
                      const SizedBox(height: 6),
                      TextFormField(
                        controller: _titleController,
                        validator: (val) {
                          if (val == null || val.trim().isEmpty) {
                            return 'Judul sesi mabar wajib diisi';
                          }
                          return null;
                        },
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                        decoration: _buildInputDecoration(
                          hint: 'Contoh: Mabar Padel JTK Bonang 6 Players',
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '*Nama ini akan menjadi judul kartu mabar di Dashboard dan Jadwal Mabar.',
                        style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
                      ),
                      const SizedBox(height: 22),
                      const Divider(height: 1, color: Color(0xFFF1F5F9)),
                      const SizedBox(height: 20),

                      // ==========================================
                      // 4. LOKASI VENUE & LAPANGAN
                      // ==========================================
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          _buildSectionHeader('4', 'LOKASI VENUE & LAPANGAN'),
                          InkWell(
                            onTap: _showQuickAddVenueModal,
                            borderRadius: BorderRadius.circular(20),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: AppColors.matchaDark,
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: const [
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
                      const SizedBox(height: 12),
                      _buildFieldLabel('Pilih Venue / Tempat *'),
                      const SizedBox(height: 6),
                      if (matchingVenues.isEmpty)
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEF2F2),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFFECACA)),
                          ),
                          child: const Text(
                            'Tidak ada venue dengan lapangan tersedia untuk cabang olahraga ini.',
                            style: TextStyle(fontSize: 12, color: Color(0xFFDC2626)),
                          ),
                        )
                      else
                        DropdownButtonFormField<VenueModel>(
                          initialValue: _selectedVenue != null &&
                                  matchingVenues.any((v) => v.venueId == _selectedVenue!.venueId)
                              ? matchingVenues.firstWhere((v) => v.venueId == _selectedVenue!.venueId)
                              : matchingVenues.firstOrNull,
                          decoration: _buildInputDecoration(hint: 'Pilih venue sesuai olahraga'),
                          isExpanded: true,
                          items: matchingVenues.map((v) {
                            final availableCount = v.courts
                                .where((c) =>
                                    c.sportId == _selectedSportId &&
                                    (c.statusKetersediaan == null ||
                                        c.statusKetersediaan!.toLowerCase() == 'available'))
                                .length;
                            return DropdownMenuItem<VenueModel>(
                              value: v,
                              child: Text(
                                '${v.namaVenue} ($availableCount Court Tersedia)',
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                                overflow: TextOverflow.ellipsis,
                              ),
                            );
                          }).toList(),
                          onChanged: _onVenueChanged,
                        ),
                      const SizedBox(height: 14),

                      _buildFieldLabel('Pilih Court / Lapangan *'),
                      const SizedBox(height: 6),
                      if (matchingCourts.isEmpty)
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEF2F2),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFFECACA)),
                          ),
                          child: const Text(
                            'Tidak ada court yang tersedia untuk cabang olahraga ini pada venue terpilih.',
                            style: TextStyle(fontSize: 12, color: Color(0xFFDC2626)),
                          ),
                        )
                      else
                        DropdownButtonFormField<CourtModel>(
                          initialValue: _selectedCourt != null &&
                                  matchingCourts.any((c) => c.courtId == _selectedCourt!.courtId)
                              ? matchingCourts.firstWhere((c) => c.courtId == _selectedCourt!.courtId)
                              : matchingCourts.firstOrNull,
                          decoration: _buildInputDecoration(hint: 'Pilih court'),
                          isExpanded: true,
                          items: matchingCourts.map((c) {
                            return DropdownMenuItem<CourtModel>(
                              value: c,
                              child: Text(
                                '${c.namaCourt} (${c.tipeCourt ?? 'Outdoor'})',
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                              ),
                            );
                          }).toList(),
                          onChanged: (val) => setState(() => _selectedCourt = val),
                        ),
                      const SizedBox(height: 22),
                      const Divider(height: 1, color: Color(0xFFF1F5F9)),
                      const SizedBox(height: 20),

                      // ==========================================
                      // 5. JADWAL & KUOTA PEMAIN
                      // ==========================================
                      _buildSectionHeader('5', 'JADWAL & KUOTA PEMAIN'),
                      const SizedBox(height: 12),

                      // Tanggal, Jam, Durasi Row
                      Row(
                        children: [
                          // Tanggal
                          Expanded(
                            flex: 4,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _buildFieldLabel('Tanggal Mabar'),
                                const SizedBox(height: 6),
                                InkWell(
                                  onTap: _pickDate,
                                  borderRadius: BorderRadius.circular(12),
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 11),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFF8FAFC),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(color: const Color(0xFFE2E8F0)),
                                    ),
                                    child: Row(
                                      children: [
                                        const Icon(Icons.calendar_today_rounded, size: 14, color: Color(0xFF64748B)),
                                        const SizedBox(width: 6),
                                        Expanded(
                                          child: Text(
                                            dateFormatted,
                                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),

                          // Jam
                          Expanded(
                            flex: 3,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _buildFieldLabel('Jam Mulai'),
                                const SizedBox(height: 6),
                                InkWell(
                                  onTap: _pickTime,
                                  borderRadius: BorderRadius.circular(12),
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 11),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFF8FAFC),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(color: const Color(0xFFE2E8F0)),
                                    ),
                                    child: Row(
                                      children: [
                                        const Icon(Icons.access_time_rounded, size: 14, color: Color(0xFF64748B)),
                                        const SizedBox(width: 4),
                                        Expanded(
                                          child: Text(
                                            timeFormatted,
                                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),

                          // Durasi
                          Expanded(
                            flex: 3,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _buildFieldLabel('Durasi'),
                                const SizedBox(height: 6),
                                DropdownButtonFormField<String>(
                                  initialValue: _selectedDuration,
                                  decoration: _buildInputDecoration(hint: 'Durasi').copyWith(
                                    contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                                  ),
                                  items: const [
                                    DropdownMenuItem(value: '1 Jam', child: Text('1 Jam', style: TextStyle(fontSize: 11))),
                                    DropdownMenuItem(value: '2 Jam', child: Text('2 Jam', style: TextStyle(fontSize: 11))),
                                    DropdownMenuItem(value: '3 Jam', child: Text('3 Jam', style: TextStyle(fontSize: 11))),
                                    DropdownMenuItem(value: '4 Jam', child: Text('4 Jam', style: TextStyle(fontSize: 11))),
                                  ],
                                  onChanged: (val) {
                                    if (val != null) setState(() => _selectedDuration = val);
                                  },
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),

                      // Jenis Permainan Toggle (Double / Single)
                      _buildFieldLabel('Jenis Permainan'),
                      const SizedBox(height: 6),
                      Container(
                        padding: const EdgeInsets.all(4),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF1F5F9),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: InkWell(
                                onTap: () => _onGameTypeChanged('Double'),
                                borderRadius: BorderRadius.circular(10),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(vertical: 9),
                                  decoration: BoxDecoration(
                                    color: _selectedGameType == 'Double' ? AppColors.matchaDark : Colors.transparent,
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Icon(
                                        Icons.people_alt_rounded,
                                        size: 15,
                                        color: _selectedGameType == 'Double' ? Colors.white : const Color(0xFF64748B),
                                      ),
                                      const SizedBox(width: 6),
                                      Text(
                                        'Double (2v2)',
                                        style: TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.bold,
                                          color: _selectedGameType == 'Double' ? Colors.white : const Color(0xFF64748B),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Opacity(
                                opacity: _isTeamAmericano ? 0.4 : 1.0,
                                child: InkWell(
                                  onTap: _isTeamAmericano ? null : () => _onGameTypeChanged('Single'),
                                  borderRadius: BorderRadius.circular(10),
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(vertical: 9),
                                    decoration: BoxDecoration(
                                      color: _selectedGameType == 'Single' ? AppColors.matchaDark : Colors.transparent,
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: Row(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Icon(
                                          Icons.person_rounded,
                                          size: 15,
                                          color: _selectedGameType == 'Single' ? Colors.white : const Color(0xFF64748B),
                                        ),
                                        const SizedBox(width: 6),
                                        Text(
                                          'Single (1v1)',
                                          style: TextStyle(
                                            fontSize: 12,
                                            fontWeight: FontWeight.bold,
                                            color: _selectedGameType == 'Single' ? Colors.white : const Color(0xFF64748B),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),

                      // Kuota Maksimal Pemain
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          _buildFieldLabel('Kuota Maksimal Pemain'),
                          if (_isFirstToSystem)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFEF3C7),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: const Color(0xFFFDE68A)),
                              ),
                              child: const Text(
                                'Kapasitas Terkunci (First to X)',
                                style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      DropdownButtonFormField<int>(
                        key: ValueKey('quota_dropdown_${_selectedFormat}_${_selectedScoringSystem}_${_selectedGameType}_$_selectedQuota'),
                        initialValue: _selectedQuota,
                        decoration: _buildInputDecoration(hint: 'Pilih Kuota'),
                        isExpanded: true,
                        items: _buildQuotaDropdownItems(),
                        onChanged: _isFirstToSystem
                            ? null // Disabled if First to X
                            : (val) {
                                if (val != null) {
                                  setState(() => _selectedQuota = val);
                                }
                              },
                      ),
                      const SizedBox(height: 10),

                      // Banner Info Dinamis
                      _buildDynamicInfoBanner(),
                      const SizedBox(height: 14),

                      // Rekomendasi Level
                      _buildFieldLabel('Rekomendasi Level'),
                      const SizedBox(height: 6),
                      DropdownButtonFormField<String>(
                        initialValue: _selectedLevel,
                        decoration: _buildInputDecoration(hint: 'Rekomendasi Level'),
                        isExpanded: true,
                        items: const [
                          DropdownMenuItem(value: 'All Level', child: Text('All Level Welcome (Bebas Semua Level)', style: TextStyle(fontSize: 12))),
                          DropdownMenuItem(value: 'Newbie - Beginner', child: Text('Newbie & Beginner Only', style: TextStyle(fontSize: 12))),
                          DropdownMenuItem(value: 'Intermediate', child: Text('Intermediate Only', style: TextStyle(fontSize: 12))),
                          DropdownMenuItem(value: 'Advanced', child: Text('Advanced / Competitive Only', style: TextStyle(fontSize: 12))),
                        ],
                        onChanged: (val) {
                          if (val != null) setState(() => _selectedLevel = val);
                        },
                      ),
                      const SizedBox(height: 14),

                      // Catatan Khusus / Deskripsi Mabar
                      _buildFieldLabel('Catatan Khusus / Deskripsi Mabar'),
                      const SizedBox(height: 6),
                      TextFormField(
                        controller: _descController,
                        maxLines: 3,
                        style: const TextStyle(fontSize: 12),
                        decoration: _buildInputDecoration(
                          hint: 'Contoh: Harap hadir 15 menit sebelum mabar dimulai. Bola sudah disediakan oleh host, sewa raket tersedia di tempat.',
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Submit Button
                      SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: ElevatedButton(
                          onPressed: _isSubmitting ? null : _submitForm,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.matchaDark,
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          child: _isSubmitting
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: const [
                                    Icon(Icons.send_rounded, size: 16, color: Color(0xFFA8E63A)),
                                    SizedBox(width: 8),
                                    Text(
                                      'Publikasikan Sesi Mabar ke Jadwal',
                                      style: TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w800,
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
            ),
    );
  }

  static const List<DropdownMenuItem<String>> _allScoringSystemItems = [
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

  List<DropdownMenuItem<String>> _buildScoringDropdownItems() {
    return _allScoringSystemItems;
  }

  List<DropdownMenuItem<int>> _buildQuotaDropdownItems() {
    if (_isFirstToSystem) {
      if (_selectedGameType == 'Double') {
        return const [
          DropdownMenuItem(value: 4, child: Text('4 Pemain (1 Court Non-Stop 2v2 - Langsung Tuntas)', style: TextStyle(fontSize: 12))),
        ];
      } else {
        return const [
          DropdownMenuItem(value: 2, child: Text('2 Pemain (1 Court Non-Stop 1v1 - Langsung Tuntas)', style: TextStyle(fontSize: 12))),
        ];
      }
    }

    if (_isTeamAmericano) {
      return const [
        DropdownMenuItem(value: 4, child: Text('4 Pemain (2 Tim Tetap - 1 Court)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 6, child: Text('6 Pemain (3 Tim Tetap - 1 Court Rotasi)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 8, child: Text('8 Pemain (4 Tim Tetap - 1/2 Court)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 10, child: Text('10 Pemain (5 Tim Tetap)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 12, child: Text('12 Pemain (6 Tim Tetap)', style: TextStyle(fontSize: 12))),
      ];
    }

    if (_selectedGameType == 'Double') {
      return const [
        DropdownMenuItem(value: 4, child: Text('4 Pemain (1 Court Non-Stop 2v2)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 6, child: Text('6 Pemain (1 Court Rotasi Bench 2 Istirahat)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 8, child: Text('8 Pemain (1 Court / 2 Court Double)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 12, child: Text('12 Pemain (Multi-Court Tournament)', style: TextStyle(fontSize: 12))),
      ];
    } else {
      return const [
        DropdownMenuItem(value: 2, child: Text('2 Pemain (1 Court Non-Stop 1v1)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 3, child: Text('3 Pemain (1 Court Rotasi 1 Istirahat)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 4, child: Text('4 Pemain (1 Court / 2 Court Single)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 5, child: Text('5 Pemain (Single Rotasi)', style: TextStyle(fontSize: 12))),
        DropdownMenuItem(value: 6, child: Text('6 Pemain (Single Multi-Court)', style: TextStyle(fontSize: 12))),
      ];
    }
  }

  Widget _buildDynamicInfoBanner() {
    String message;
    Color bgColor = const Color(0xFFF0FDF4);
    Color borderColor = const Color(0xFFBBF7D0);
    Color textColor = const Color(0xFF166534);
    IconData icon = Icons.info_outline_rounded;

    if (_isFirstToSystem) {
      final req = _selectedGameType == 'Double' ? '4 pemain (2 vs 2)' : '2 pemain (1 vs 1)';
      message = 'Format $_selectedScoringSystem: Pertandingan 1 set langsung tuntas tanpa rotasi cadangan. Memerlukan tepat $req.';
      bgColor = const Color(0xFFFEF3C7);
      borderColor = const Color(0xFFFDE68A);
      textColor = const Color(0xFF92400E);
      icon = Icons.bolt_rounded;
    } else if (_isTeamAmericano) {
      message = 'Team Americano: Setiap tim 2 orang tetap saling bertanding melawan tim lainnya. Kuota pemain harus genap (4, 6, 8, dst).';
      bgColor = const Color(0xFFEFF6FF);
      borderColor = const Color(0xFFBFDBFE);
      textColor = const Color(0xFF1E40AF);
      icon = Icons.groups_rounded;
    } else if (_selectedGameType == 'Double') {
      message = 'Americano Double: Pasangan partner berganti tiap ronde secara dinamis dan adil.';
    } else {
      message = 'Americano Single: Pertandingan 1 lawan 1 round-robin.';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: borderColor),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 15, color: textColor),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: textColor,
                height: 1.3,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String number, String title) {
    return Row(
      children: [
        Container(
          width: 20,
          height: 20,
          decoration: const BoxDecoration(
            color: AppColors.matchaDark,
            shape: BoxShape.circle,
          ),
          alignment: Alignment.center,
          child: Text(
            number,
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
        ),
        const SizedBox(width: 8),
        Text(
          title,
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w900,
            letterSpacing: 0.5,
            color: Color(0xFF0F172A),
          ),
        ),
      ],
    );
  }

  Widget _buildFieldLabel(String label) {
    return Text(
      label,
      style: const TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w700,
        color: Color(0xFF334155),
      ),
    );
  }

  InputDecoration _buildInputDecoration({required String hint}) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
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
    );
  }

  Widget _buildSportCard({
    required int sportId,
    required String title,
    required String subtitle,
    required IconData icon,
    required bool isSelected,
  }) {
    return InkWell(
      onTap: () => _applySportSelection(sportId),
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFF0FDF4) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Icon(icon, size: 18, color: AppColors.matchaDark),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 9,
                      color: Color(0xFF64748B),
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFormatCard({
    required String formatKey,
    required String badgeText,
    required Color badgeColor,
    required Color badgeBg,
    required String title,
    required String subtitle,
    required IconData icon,
    required bool isSelected,
  }) {
    return InkWell(
      onTap: () => _onFormatChanged(formatKey),
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFF0FDF4) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Icon(icon, size: 17, color: isSelected ? AppColors.matchaDark : const Color(0xFF64748B)),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: badgeBg,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(
                    badgeText,
                    style: TextStyle(
                      fontSize: 8,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 0.5,
                      color: badgeColor,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              title,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              style: const TextStyle(
                fontSize: 9,
                color: Color(0xFF64748B),
                height: 1.25,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}