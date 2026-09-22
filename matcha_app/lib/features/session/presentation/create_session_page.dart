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

  // Form Fields
  int _selectedSportId = 1; // 1: Padel, 2: Tennis
  VenueModel? _selectedVenue;
  CourtModel? _selectedCourt;

  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _descController = TextEditingController();

  DateTime _selectedDate = DateTime.now().add(const Duration(days: 1));
  TimeOfDay _selectedTime = const TimeOfDay(hour: 18, minute: 30);
  String _selectedDuration = '2 Jam';
  String _selectedGameType = 'Double'; // Double or Single
  int _selectedQuota = 6;
  String _selectedLevel = 'All Level';

  @override
  void initState() {
    super.initState();
    if (widget.initialSportId != null) {
      _selectedSportId = widget.initialSportId!;
    }
    _titleController.text = _selectedSportId == 1
        ? 'Mabar Padel Fun Match'
        : 'Mabar Tennis Regular Match';
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

    // Update dynamic title suggestion
    final sportName = _selectedSportId == 1 ? 'Padel' : 'Tennis';
    final venueName = _selectedVenue?.namaVenue ?? '';
    if (venueName.isNotEmpty) {
      _titleController.text = 'Mabar $sportName $venueName $_selectedQuota Players';
    } else {
      _titleController.text = 'Mabar $sportName Fun $_selectedQuota Players';
    }

    setState(() {});
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

      final sportName = _selectedSportId == 1 ? 'Padel' : 'Tennis';
      _titleController.text =
          'Mabar $sportName ${_selectedVenue!.namaVenue} $_selectedQuota Players';
    });
  }

  void _onGameTypeChanged(String type) {
    setState(() {
      _selectedGameType = type;
      if (type == 'Single') {
        _selectedQuota = 2;
      } else {
        _selectedQuota = 6;
      }

      final sportName = _selectedSportId == 1 ? 'Padel' : 'Tennis';
      final venueName = _selectedVenue?.namaVenue ?? '';
      if (venueName.isNotEmpty) {
        _titleController.text =
            'Mabar $sportName $venueName $_selectedQuota Players';
      }
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
                      // 1. PILIH CABANG OLAHRAGA
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

                      // 2. INFORMASI & JUDUL MABAR
                      _buildSectionHeader('2', 'INFORMASI & JUDUL MABAR'),
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

                      // 3. LOKASI VENUE & LAPANGAN
                      _buildSectionHeader('3', 'LOKASI VENUE & LAPANGAN'),
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

                      // 4. JADWAL & KUOTA PEMAIN
                      _buildSectionHeader('4', 'JADWAL & KUOTA PEMAIN'),
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
                              child: InkWell(
                                onTap: () => _onGameTypeChanged('Single'),
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
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),

                      // Kuota Maksimal Pemain
                      _buildFieldLabel('Kuota Maksimal Pemain'),
                      const SizedBox(height: 6),
                      DropdownButtonFormField<int>(
                        initialValue: _selectedQuota,
                        decoration: _buildInputDecoration(hint: 'Pilih Kuota'),
                        isExpanded: true,
                        items: _selectedGameType == 'Double'
                            ? const [
                                DropdownMenuItem(value: 4, child: Text('4 Pemain (1 Court Non-Stop 2v2)', style: TextStyle(fontSize: 12))),
                                DropdownMenuItem(value: 6, child: Text('6 Pemain (1 Court Rotasi Bench 2 Istirahat)', style: TextStyle(fontSize: 12))),
                                DropdownMenuItem(value: 8, child: Text('8 Pemain (1 Court / 2 Court Double)', style: TextStyle(fontSize: 12))),
                                DropdownMenuItem(value: 12, child: Text('12 Pemain (Multi-Court Tournament)', style: TextStyle(fontSize: 12))),
                              ]
                            : const [
                                DropdownMenuItem(value: 2, child: Text('2 Pemain (1 Court Non-Stop 1v1)', style: TextStyle(fontSize: 12))),
                                DropdownMenuItem(value: 3, child: Text('3 Pemain (1 Court Rotasi 1 Istirahat)', style: TextStyle(fontSize: 12))),
                                DropdownMenuItem(value: 4, child: Text('4 Pemain (1 Court / 2 Court Single)', style: TextStyle(fontSize: 12))),
                                DropdownMenuItem(value: 5, child: Text('5 Pemain (Single Rotasi)', style: TextStyle(fontSize: 12))),
                                DropdownMenuItem(value: 6, child: Text('6 Pemain (Single Multi-Court)', style: TextStyle(fontSize: 12))),
                              ],
                        onChanged: (val) {
                          if (val != null) {
                            setState(() {
                              _selectedQuota = val;
                              final sportName = _selectedSportId == 1 ? 'Padel' : 'Tennis';
                              final venueName = _selectedVenue?.namaVenue ?? '';
                              if (venueName.isNotEmpty) {
                                _titleController.text =
                                    'Mabar $sportName $venueName $_selectedQuota Players';
                              }
                            });
                          }
                        },
                      ),
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
}