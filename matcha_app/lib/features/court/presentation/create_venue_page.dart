import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show rootBundle;
import 'package:image_picker/image_picker.dart';

import '../../../core/theme/app_colors.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../data/venue_service.dart';
import 'create_court_page.dart';

// ---------------------------------------------------------------------------
// Facility options – same list as the web
// ---------------------------------------------------------------------------
const _facilityOptions = [
  {'icon': Icons.lightbulb_outline_rounded, 'name': 'Lampu Malam (LED)'},
  {'icon': Icons.shower_outlined, 'name': 'Shower & Toilet'},
  {'icon': Icons.coffee_outlined, 'name': 'Kantin / Cafe'},
  {'icon': Icons.directions_car_outlined, 'name': 'Parkir Mobil/Motor'},
  {'icon': Icons.ac_unit_rounded, 'name': 'AC Waiting Lounge'},
  {'icon': Icons.sports_tennis_rounded, 'name': 'Rental Raket & Bola'},
  {'icon': Icons.lock_outline_rounded, 'name': 'Loker Barang'},
  {'icon': Icons.wifi_rounded, 'name': 'Free High-speed WiFi'},
];

// ---------------------------------------------------------------------------
// Colour palette (matching web: #063B00, #A8E63A, slate tones)
// ---------------------------------------------------------------------------
const _kDark = AppColors.matchaDark;
const _kLime = Color(0xFFA8E63A);
const _kSoftLime = Color(0xFFEBF8D8);
const _kSlate50 = Color(0xFFF8FAFC);
const _kSlate100 = Color(0xFFF1F5F9);
const _kSlate200 = Color(0xFFE2E8F0);
const _kSlate400 = Color(0xFF94A3B8);
const _kSlate700 = Color(0xFF334155);
const _kSlate900 = Color(0xFF0F172A);
const _kRose = Color(0xFFEF4444);

class CreateVenuePage extends StatefulWidget {
  final AuthController? authController;

  const CreateVenuePage({super.key, this.authController});

  @override
  State<CreateVenuePage> createState() => _CreateVenuePageState();
}

class _VenuePhoto {
  final XFile file;
  final Uint8List bytes;

  const _VenuePhoto({required this.file, required this.bytes});
}

class _RegencyItem {
  final String id;
  final String name;
  final String province;

  const _RegencyItem({
    required this.id,
    required this.name,
    required this.province,
  });

  factory _RegencyItem.fromJson(Map<String, dynamic> json) {
    return _RegencyItem(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      province: json['province'] as String? ?? '',
    );
  }
}

class _CreateVenuePageState extends State<CreateVenuePage> {
  final _formKey = GlobalKey<FormState>();
  final _venueService = VenueService();
  final _imagePicker = ImagePicker();
  final _nameController = TextEditingController();
  final _addressController = TextEditingController();
  final _picController = TextEditingController();
  final _phoneController = TextEditingController();
  final _notesController = TextEditingController();
  final _mapsController = TextEditingController();
  final _otherSurfaceController = TextEditingController();

  final Set<String> _selectedFacilities = {};
  final List<_VenuePhoto> _photos = [];

  List<_RegencyItem> _allRegencies = [];
  String? _selectedCity;
  bool _cityHasError = false;

  String _sportType = 'Padel';
  String _arenaType = 'Semi-Indoor';
  String _surfaceType = 'Artificial Turf';
  String _openingDays = 'Setiap Hari (Senin - Minggu)';
  int _courtCount = 1;
  TimeOfDay _openingTime = const TimeOfDay(hour: 6, minute: 0);
  TimeOfDay _closingTime = const TimeOfDay(hour: 23, minute: 0);
  bool _isSaving = false;

  int get _defaultCourtSportId => _sportType == 'Tennis' ? 2 : 1;

  @override
  void initState() {
    super.initState();
    _loadRegencies();
  }

  Future<void> _loadRegencies() async {
    try {
      final jsonStr = await rootBundle.loadString('assets/indonesia-regencies.json');
      final List<dynamic> data = jsonDecode(jsonStr);
      if (!mounted) return;
      setState(() {
        _allRegencies = data
            .map((e) => _RegencyItem.fromJson(e as Map<String, dynamic>))
            .toList();
      });
    } catch (e) {
      debugPrint('Error loading regencies: $e');
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _addressController.dispose();
    _picController.dispose();
    _phoneController.dispose();
    _notesController.dispose();
    _mapsController.dispose();
    _otherSurfaceController.dispose();
    super.dispose();
  }

  String _formatTime(TimeOfDay time) =>
      '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}';

  Future<void> _pickTime({required bool isOpening}) async {
    final picked = await showTimePicker(
      context: context,
      initialTime: isOpening ? _openingTime : _closingTime,
    );
    if (picked == null || !mounted) return;
    setState(() {
      if (isOpening) {
        _openingTime = picked;
      } else {
        _closingTime = picked;
      }
    });
  }

  Future<void> _openCitySearchPicker() async {
    if (_allRegencies.isEmpty) {
      await _loadRegencies();
    }
    if (!mounted) return;

    final selected = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _CitySearchBottomSheet(
        regencies: _allRegencies,
        selectedCity: _selectedCity,
      ),
    );

    if (selected != null && mounted) {
      setState(() {
        _selectedCity = selected;
        _cityHasError = false;
      });
    }
  }

  Future<void> _pickPhotos() async {
    try {
      final pickedFiles = await _imagePicker.pickMultiImage(
        maxWidth: 1600,
        maxHeight: 1200,
        imageQuality: 85,
      );
      if (pickedFiles.isEmpty || !mounted) return;

      var rejectedFile = false;
      for (final file in pickedFiles) {
        if (_photos.length >= 10) {
          rejectedFile = true;
          break;
        }
        final extension = file.name.split('.').last.toLowerCase();
        if (!{'jpg', 'jpeg', 'png', 'webp'}.contains(extension)) {
          rejectedFile = true;
          continue;
        }
        final bytes = await file.readAsBytes();
        if (bytes.lengthInBytes > 5 * 1024 * 1024) {
          rejectedFile = true;
          continue;
        }
        if (_photos.any((photo) => photo.file.name == file.name)) continue;
        _photos.add(_VenuePhoto(file: file, bytes: bytes));
      }

      if (!mounted) return;
      setState(() {});
      if (rejectedFile) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Foto harus JPG, PNG, atau WEBP, maksimal 5 MB per foto dan 10 foto.',
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Gagal memilih foto: $e')));
    }
  }

  Future<void> _registerVenue() async {
    final isFormValid = _formKey.currentState!.validate();
    final isCityValid = _selectedCity != null && _selectedCity!.trim().isNotEmpty;

    if (!isCityValid) {
      setState(() => _cityHasError = true);
    }

    if (!isFormValid || !isCityValid) return;

    final user = widget.authController?.currentUser;
    if (user == null || user.role.toLowerCase() != 'venue_owner') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Pendaftaran venue hanya tersedia untuk pemilik venue.',
          ),
        ),
      );
      return;
    }

    setState(() => _isSaving = true);
    final messenger = ScaffoldMessenger.of(context);

    try {
      final photoUrls = <String>[];
      for (final photo in _photos) {
        photoUrls.add(
          await _venueService.uploadVenuePhoto(photo.bytes, photo.file.name),
        );
      }

      final operatingHours =
          '${_formatTime(_openingTime)} - ${_formatTime(_closingTime)} WIB';

      final venue = await _venueService.createVenue(
        ownerUserId: user.userId,
        namaVenue: _nameController.text,
        alamat: _addressController.text,
        kota: _selectedCity!,
        jamOperasional: operatingHours,
        hariBuka: _openingDays,
        namaPic: _picController.text,
        noWhatsapp: _phoneController.text,
        fasilitas: _selectedFacilities.toList(),
        photos: photoUrls,
        catatan: _notesController.text,
      );

      if (!mounted) return;
      await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => CreateCourtPage(
            venue: venue,
            authController: widget.authController,
            initialCourtCount: _courtCount,
            initialSportId: _defaultCourtSportId,
            initialArenaType: _arenaType,
            requirePriceInput: true,
          ),
        ),
      );

      if (!mounted) return;
      Navigator.pop(context, true);
      messenger.showSnackBar(
        SnackBar(
          content: Text('Venue "${venue.namaVenue}" berhasil didaftarkan.'),
          backgroundColor: _kDark,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      messenger.showSnackBar(
        SnackBar(
          content: Text('Gagal mendaftarkan venue: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  // =========================================================================
  // Helpers
  // =========================================================================

  /// Field label widget placed above the input field (matching web style)
  Widget _buildFieldLabel(String label, {bool isRequired = false, String? badge}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          RichText(
            text: TextSpan(
              text: label,
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: _kSlate700,
              ),
              children: [
                if (isRequired)
                  const TextSpan(
                    text: ' *',
                    style: TextStyle(
                      color: _kRose,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
              ],
            ),
          ),
          if (badge != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: _kSlate100,
                borderRadius: BorderRadius.circular(4),
              ),
              child: Text(
                badge,
                style: const TextStyle(
                  fontSize: 9,
                  fontWeight: FontWeight.w600,
                  color: _kSlate400,
                ),
              ),
            ),
        ],
      ),
    );
  }

  /// Standard input decoration: uses hintText ONLY (no floating label inside box)
  InputDecoration _fieldDecoration({
    required IconData icon,
    required String hint,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(
        fontSize: 11,
        color: _kSlate400,
        fontWeight: FontWeight.w500,
      ),
      prefixIcon: Icon(icon, size: 16, color: _kSlate400),
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: _kSlate200),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: _kSlate200),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: _kDark, width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: _kRose),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: _kRose, width: 1.5),
      ),
      errorStyle: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: _kRose),
    );
  }

  /// Section header matching the web's numbered section style
  Widget _sectionHeader(String number, String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 24,
                height: 24,
                decoration: BoxDecoration(
                  color: _kDark,
                  borderRadius: BorderRadius.circular(7),
                ),
                alignment: Alignment.center,
                child: Text(
                  number,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text(
                title.toUpperCase(),
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w900,
                  color: _kSlate900,
                  letterSpacing: 0.5,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          const Divider(height: 1, color: _kSlate100),
        ],
      ),
    );
  }

  /// Compact dropdown with external label, rounded white popup menu and clean styling
  Widget _compactDropdown<T>({
    required String label,
    required T value,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?> onChanged,
    IconData? icon,
    bool isRequired = false,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildFieldLabel(label, isRequired: isRequired),
        DropdownButtonFormField<T>(
          initialValue: value,
          isExpanded: true,
          menuMaxHeight: 220,
          dropdownColor: Colors.white,
          borderRadius: BorderRadius.circular(14),
          elevation: 4,
          icon: const Icon(
            Icons.keyboard_arrow_down_rounded,
            size: 18,
            color: _kSlate400,
          ),
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: _kSlate900,
          ),
          decoration: InputDecoration(
            prefixIcon: icon != null ? Icon(icon, size: 16, color: _kSlate400) : null,
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
            isDense: true,
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: _kSlate200),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: _kSlate200),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: _kDark, width: 1.5),
            ),
          ),
          items: items,
          onChanged: onChanged,
        ),
      ],
    );
  }

  /// Kota / Kabupaten searchable selector (matching web modal/dropdown)
  Widget _buildCitySelector() {
    final hasSelected = _selectedCity != null && _selectedCity!.isNotEmpty;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildFieldLabel('Kota / Kabupaten', isRequired: true),
        InkWell(
          onTap: _openCitySearchPicker,
          borderRadius: BorderRadius.circular(14),
          child: Container(
            height: 44,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: _cityHasError ? _kRose : _kSlate200,
                width: _cityHasError ? 1.5 : 1.0,
              ),
            ),
            child: Row(
              children: [
                const Icon(Icons.location_city_outlined, size: 16, color: _kSlate400),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    hasSelected ? _selectedCity! : 'Pilih Kota / Kabupaten',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: hasSelected ? FontWeight.w700 : FontWeight.w500,
                      color: hasSelected ? _kSlate900 : _kSlate400,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const Icon(Icons.keyboard_arrow_down_rounded, size: 18, color: _kSlate400),
              ],
            ),
          ),
        ),
        if (_cityHasError)
          const Padding(
            padding: EdgeInsets.only(top: 4, left: 4),
            child: Text(
              'Kota / Kabupaten wajib dipilih.',
              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: _kRose),
            ),
          ),
      ],
    );
  }

  // =========================================================================
  // Sport-type radio cards (matching web's 3-column radio cards)
  // =========================================================================
  Widget _buildSportTypeCards() {
    const sports = [
      {'value': 'Padel', 'label': 'Padel Court', 'sub': 'Khusus Lapangan Padel', 'icon': Icons.sports_tennis_rounded},
      {'value': 'Tennis', 'label': 'Tennis Court', 'sub': 'Khusus Lapangan Tenis', 'icon': Icons.sports_baseball_outlined},
      {'value': 'Multi-Racquet', 'label': 'Multi-Racquet', 'sub': 'Padel & Tennis Gabungan', 'icon': Icons.emoji_events_outlined},
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildFieldLabel('Pilih Kategori Lapangan Utama', isRequired: true),
        Row(
          children: sports.map((sport) {
            final isSelected = _sportType == sport['value'];
            return Expanded(
              child: GestureDetector(
                onTap: () => setState(() => _sportType = sport['value'] as String),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  margin: const EdgeInsets.only(right: 8),
                  padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 6),
                  decoration: BoxDecoration(
                    color: isSelected ? _kSoftLime : const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: isSelected ? _kDark : _kSlate200,
                      width: isSelected ? 1.5 : 1,
                    ),
                  ),
                  child: Column(
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: _kSlate200),
                        ),
                        alignment: Alignment.center,
                        child: Icon(
                          sport['icon'] as IconData,
                          size: 18,
                          color: _kDark,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        sport['label'] as String,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          color: _kSlate900,
                        ),
                      ),
                      Text(
                        sport['sub'] as String,
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 9, color: _kSlate400),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }).toList(),
        ),
      ],
    );
  }

  // =========================================================================
  // Facility grid cards (2 columns, matching web's card toggle style)
  // =========================================================================
  Widget _buildFacilitiesGrid() {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisSpacing: 8,
      mainAxisSpacing: 8,
      childAspectRatio: 3.2,
      children: _facilityOptions.map((fac) {
        final name = fac['name'] as String;
        final icon = fac['icon'] as IconData;
        final isSelected = _selectedFacilities.contains(name);

        return GestureDetector(
          onTap: () {
            setState(() {
              if (isSelected) {
                _selectedFacilities.remove(name);
              } else {
                _selectedFacilities.add(name);
              }
            });
          },
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 150),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: isSelected ? const Color(0xFFEBF8D8).withValues(alpha: 0.7) : const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: isSelected ? _kDark : _kSlate200,
                width: isSelected ? 1.5 : 1,
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: _kSlate200),
                  ),
                  alignment: Alignment.center,
                  child: Icon(
                    icon,
                    size: 14,
                    color: isSelected ? _kDark : _kSlate400,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    name,
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: isSelected ? _kDark : _kSlate700,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  // =========================================================================
  // Time-picker tile
  // =========================================================================
  Widget _timeTile({
    required TimeOfDay time,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        height: 44,
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          color: _kSlate50,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: _kSlate200),
        ),
        child: Row(
          children: [
            const Icon(Icons.access_time_rounded, size: 16, color: _kSlate400),
            const SizedBox(width: 8),
            Text(
              _formatTime(time),
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: _kSlate900,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // =========================================================================
  // Build
  // =========================================================================
  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;
    if (user == null || user.role.toLowerCase() != 'venue_owner') {
      return Scaffold(
        appBar: AppBar(title: const Text('Form Pendaftaran Venue Baru')),
        body: const Center(
          child: Text('Fitur ini hanya tersedia untuk pemilik venue.'),
        ),
      );
    }

    return Scaffold(
      backgroundColor: _kSlate50,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 1,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: _kSlate100,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.arrow_back_ios_new_rounded, size: 16, color: _kSlate900),
          ),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Form Pendaftaran Venue Baru',
          style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: _kSlate900),
        ),
        centerTitle: true,
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 40),
          children: [
            // ---------------------------------------------------------------
            // SECTION 1 – Informasi Utama Venue
            // ---------------------------------------------------------------
            _sectionHeader('1', 'Informasi Utama Venue'),

            // Nama Tempat / Venue
            _buildFieldLabel('Nama Tempat / Venue', isRequired: true),
            TextFormField(
              controller: _nameController,
              textCapitalization: TextCapitalization.words,
              maxLength: 100,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
              decoration: _fieldDecoration(
                icon: Icons.storefront_outlined,
                hint: 'Contoh: Gelora Racquet & Padel Club',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Nama venue wajib diisi.'
                  : null,
            ),
            const SizedBox(height: 12),

            // Alamat Lengkap Venue
            _buildFieldLabel('Alamat Lengkap Venue', isRequired: true),
            TextFormField(
              controller: _addressController,
              textCapitalization: TextCapitalization.sentences,
              maxLines: 2,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
              decoration: _fieldDecoration(
                icon: Icons.location_on_outlined,
                hint: 'Jl. Raya Utama No. 88, Kebayoran Baru, Jakarta Selatan...',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Alamat venue wajib diisi.'
                  : null,
            ),
            const SizedBox(height: 12),

            // Kota / Kabupaten (Searchable Dropdown)
            _buildCitySelector(),
            const SizedBox(height: 12),

            // Link Google Maps
            _buildFieldLabel('Link Google Maps', badge: 'Opsional'),
            TextFormField(
              controller: _mapsController,
              keyboardType: TextInputType.url,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
              decoration: _fieldDecoration(
                icon: Icons.link_rounded,
                hint: 'https://maps.app.goo.gl/...',
              ),
            ),

            const SizedBox(height: 22),

            // ---------------------------------------------------------------
            // SECTION 2 – Cabang Olahraga & Karakteristik Lapangan
            // ---------------------------------------------------------------
            _sectionHeader('2', 'Cabang Olahraga & Karakteristik Lapangan'),
            _buildSportTypeCards(),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: _compactDropdown<int>(
                    label: 'Jumlah Court',
                    value: _courtCount,
                    icon: Icons.stadium_outlined,
                    items: const [1, 2, 3, 4, 6]
                        .map((c) => DropdownMenuItem(
                              value: c,
                              child: Text(
                                c == 6 ? '6+ Courts (Arena Besar)' : '$c Court${c > 1 ? 's' : ''}',
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900),
                              ),
                            ))
                        .toList(),
                    onChanged: (v) { if (v != null) setState(() => _courtCount = v); },
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _compactDropdown<String>(
                    label: 'Tipe Arena',
                    value: _arenaType,
                    icon: Icons.roofing_outlined,
                    items: const [
                      DropdownMenuItem(
                        value: 'Semi-Indoor',
                        child: Text('Semi-Indoor (Atap Pelindung)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900)),
                      ),
                      DropdownMenuItem(
                        value: 'Indoor',
                        child: Text('Indoor (Full AC / Tertutup)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900)),
                      ),
                      DropdownMenuItem(
                        value: 'Outdoor',
                        child: Text('Outdoor (Terbuka)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900)),
                      ),
                    ],
                    onChanged: (v) { if (v != null) setState(() => _arenaType = v); },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _compactDropdown<String>(
              label: 'Jenis Permukaan',
              value: _surfaceType,
              icon: Icons.texture_rounded,
              items: const [
                DropdownMenuItem(
                  value: 'Artificial Turf',
                  child: Text('Artificial Turf (Rumput Sintetis Padel)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900)),
                ),
                DropdownMenuItem(
                  value: 'Hard Court',
                  child: Text('Hard Court (Plexipave / Acrylic)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900)),
                ),
                DropdownMenuItem(
                  value: 'Clay',
                  child: Text('Clay Court (Tanah Liat)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900)),
                ),
                DropdownMenuItem(
                  value: 'Grass',
                  child: Text('Grass Court (Rumput Alami)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900)),
                ),
                DropdownMenuItem(
                  value: 'Other',
                  child: Text('Lainnya', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900)),
                ),
              ],
              onChanged: (v) { if (v != null) setState(() => _surfaceType = v); },
            ),
            if (_surfaceType == 'Other') ...[
              const SizedBox(height: 10),
              _buildFieldLabel('Sebutkan Jenis Permukaan', isRequired: true),
              TextFormField(
                controller: _otherSurfaceController,
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                decoration: _fieldDecoration(
                  icon: Icons.texture_rounded,
                  hint: 'Contoh: Rubber Court',
                ),
                validator: (value) {
                  if (_surfaceType == 'Other' && (value == null || value.trim().isEmpty)) {
                    return 'Silakan sebutkan jenis permukaan.';
                  }
                  return null;
                },
              ),
            ],

            const SizedBox(height: 22),

            // ---------------------------------------------------------------
            // SECTION 3 – Jam Operasional & Kontak
            // ---------------------------------------------------------------
            _sectionHeader('3', 'Jam Operasional & Kontak Pengelola'),
            _buildFieldLabel('Jam Operasional Reguler', isRequired: true, badge: 'WIB'),
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildFieldLabel('Buka'),
                      _timeTile(time: _openingTime, onTap: () => _pickTime(isOpening: true)),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildFieldLabel('Tutup'),
                      _timeTile(time: _closingTime, onTap: () => _pickTime(isOpening: false)),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _compactDropdown<String>(
              label: 'Hari Operasional',
              value: _openingDays,
              icon: Icons.calendar_month_outlined,
              items: const [
                'Setiap Hari (Senin - Minggu)',
                'Senin - Sabtu (Minggu Libur)',
                'Senin - Jumat (Hari Kerja)',
                'Selasa - Minggu (Senin Libur)',
                'Sabtu & Minggu (Weekend Saja)',
              ]
                  .map((d) => DropdownMenuItem(
                        value: d,
                        child: Text(
                          d,
                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: _kSlate900),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ))
                  .toList(),
              onChanged: (v) { if (v != null) setState(() => _openingDays = v); },
            ),
            const SizedBox(height: 12),
            _buildFieldLabel('Nama PIC Venue / Pengelola', isRequired: true),
            TextFormField(
              controller: _picController,
              textCapitalization: TextCapitalization.words,
              maxLength: 150,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
              decoration: _fieldDecoration(
                icon: Icons.person_outline_rounded,
                hint: 'Contoh: Budi Santoso',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Nama PIC wajib diisi.'
                  : null,
            ),
            const SizedBox(height: 12),
            _buildFieldLabel('Nomor WhatsApp PIC Venue', isRequired: true),
            TextFormField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              maxLength: 50,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
              decoration: _fieldDecoration(
                icon: Icons.phone_outlined,
                hint: 'Contoh: 081234567890',
              ),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Nomor WhatsApp wajib diisi.'
                  : null,
            ),
            const SizedBox(height: 12),
            _buildFieldLabel('Catatan Jam Operasional Khusus & Ketentuan Lapangan', badge: 'Opsional'),
            TextFormField(
              controller: _notesController,
              maxLines: 2,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
              decoration: _fieldDecoration(
                icon: Icons.notes_rounded,
                hint: 'Contoh: Khusus hari Jumat buka mulai pukul 13.00 WIB (setelah sholat Jumat). Lapangan outdoor maintenance setiap Selasa jam 08.00 - 10.00.',
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Gunakan untuk menginfokan penyesuaian jam buka atau jadwal maintenance rutin.',
              style: TextStyle(fontSize: 10, color: _kSlate400),
            ),

            const SizedBox(height: 22),

            // ---------------------------------------------------------------
            // SECTION 4 – Fasilitas yang Tersedia
            // ---------------------------------------------------------------
            _sectionHeader('4', 'Fasilitas yang Tersedia'),
            _buildFacilitiesGrid(),

            const SizedBox(height: 22),

            // ---------------------------------------------------------------
            // SECTION 5 – Foto Banner & Galeri
            // ---------------------------------------------------------------
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _sectionHeader('5', 'Foto Banner & Galeri Lapangan'),
                      const Text(
                        'Upload foto banner utama dan foto suasana lapangan / fasilitas (ala Google Maps).',
                        style: TextStyle(fontSize: 10, color: _kSlate400),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: _kSlate100,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text(
                    'Opsional (Maks. 10 Foto)',
                    style: TextStyle(fontSize: 9, fontWeight: FontWeight.w600, color: _kSlate400),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            // Dropzone-style upload area
            GestureDetector(
              onTap: _photos.length >= 10 ? null : _pickPhotos,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                width: double.infinity,
                padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 16),
                decoration: BoxDecoration(
                  color: _photos.isEmpty ? _kSlate50 : _kSoftLime.withValues(alpha: 0.3),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color: _photos.isEmpty ? _kSlate200 : _kDark,
                    width: 1.5,
                    style: BorderStyle.solid,
                  ),
                ),
                child: _photos.isEmpty
                    ? Column(
                        children: [
                          Container(
                            width: 50,
                            height: 50,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: _kSlate200),
                            ),
                            alignment: Alignment.center,
                            child: const Icon(Icons.photo_library_outlined, size: 22, color: _kSlate400),
                          ),
                          const SizedBox(height: 10),
                          const Text(
                            'Klik untuk upload foto lapangan',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: _kSlate900),
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            'Format JPG, PNG, WEBP (maks. 5MB/foto). Foto pertama jadi Banner Utama.',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 10, color: _kSlate400),
                          ),
                          const SizedBox(height: 10),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(color: _kSlate200),
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.upload_rounded, size: 12, color: _kDark),
                                SizedBox(width: 5),
                                Text('Pilih 1 atau beberapa foto sekaligus', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: _kSlate700)),
                              ],
                            ),
                          ),
                        ],
                      )
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'Foto Dipilih (${_photos.length}/10)',
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: _kSlate900),
                              ),
                              Row(
                                children: [
                                  if (_photos.length < 10)
                                    GestureDetector(
                                      onTap: _pickPhotos,
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                                        decoration: BoxDecoration(
                                          color: Colors.white,
                                          borderRadius: BorderRadius.circular(10),
                                          border: Border.all(color: _kSlate200),
                                        ),
                                        child: const Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            Icon(Icons.add_rounded, size: 12, color: _kDark),
                                            SizedBox(width: 4),
                                            Text('Tambah Foto', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: _kDark)),
                                          ],
                                        ),
                                      ),
                                    ),
                                  const SizedBox(width: 8),
                                  GestureDetector(
                                    onTap: () => setState(() => _photos.clear()),
                                    child: Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFFFF1F2),
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: const Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Icon(Icons.delete_outline_rounded, size: 12, color: Colors.redAccent),
                                          SizedBox(width: 4),
                                          Text('Hapus Semua', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.redAccent)),
                                        ],
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: List.generate(_photos.length, (index) {
                              final photo = _photos[index];
                              return Stack(
                                children: [
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(10),
                                    child: Image.memory(
                                      photo.bytes,
                                      width: 80,
                                      height: 80,
                                      fit: BoxFit.cover,
                                    ),
                                  ),
                                  if (index == 0)
                                    const Positioned(
                                      left: 4,
                                      bottom: 4,
                                      child: DecoratedBox(
                                        decoration: BoxDecoration(
                                          color: Colors.white,
                                          borderRadius: BorderRadius.all(Radius.circular(5)),
                                        ),
                                        child: Padding(
                                          padding: EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                                          child: Text(
                                            'Banner',
                                            style: TextStyle(fontSize: 8, fontWeight: FontWeight.bold),
                                          ),
                                        ),
                                      ),
                                    ),
                                  Positioned(
                                    top: 0,
                                    right: 0,
                                    child: GestureDetector(
                                      onTap: () => setState(() => _photos.removeAt(index)),
                                      child: Container(
                                        decoration: BoxDecoration(
                                          color: Colors.black54,
                                          borderRadius: BorderRadius.circular(20),
                                        ),
                                        padding: const EdgeInsets.all(2),
                                        child: const Icon(Icons.close_rounded, size: 14, color: Colors.white),
                                      ),
                                    ),
                                  ),
                                ],
                              );
                            }),
                          ),
                        ],
                      ),
              ),
            ),

            const SizedBox(height: 28),

            // ---------------------------------------------------------------
            // Submit button
            // ---------------------------------------------------------------
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: OutlinedButton(
                    onPressed: () {
                      _formKey.currentState?.reset();
                      _nameController.clear();
                      _addressController.clear();
                      _mapsController.clear();
                      _picController.clear();
                      _phoneController.clear();
                      _notesController.clear();
                      _otherSurfaceController.clear();
                      setState(() {
                        _selectedCity = null;
                        _cityHasError = false;
                        _selectedFacilities.clear();
                        _photos.clear();
                        _sportType = 'Padel';
                        _arenaType = 'Semi-Indoor';
                        _surfaceType = 'Artificial Turf';
                        _openingDays = 'Setiap Hari (Senin - Minggu)';
                        _courtCount = 1;
                        _openingTime = const TimeOfDay(hour: 6, minute: 0);
                        _closingTime = const TimeOfDay(hour: 23, minute: 0);
                      });
                    },
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _kSlate700,
                      side: const BorderSide(color: _kSlate200),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                    ),
                    child: const Text('Reset Form', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 4,
                  child: ElevatedButton(
                    onPressed: _isSaving ? null : _registerVenue,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _kDark,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                    ),
                    child: _isSaving
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.send_rounded, size: 15, color: _kLime),
                              SizedBox(width: 6),
                              Flexible(
                                child: Text(
                                  'Daftarkan Venue Sekarang',
                                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
                                  overflow: TextOverflow.ellipsis,
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
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Searchable Kota / Kabupaten Bottom Sheet
// ---------------------------------------------------------------------------
class _CitySearchBottomSheet extends StatefulWidget {
  final List<_RegencyItem> regencies;
  final String? selectedCity;

  const _CitySearchBottomSheet({
    required this.regencies,
    this.selectedCity,
  });

  @override
  State<_CitySearchBottomSheet> createState() => _CitySearchBottomSheetState();
}

class _CitySearchBottomSheetState extends State<_CitySearchBottomSheet> {
  final _searchController = TextEditingController();
  List<_RegencyItem> _filtered = [];

  @override
  void initState() {
    super.initState();
    _filtered = widget.regencies;
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _searchController.removeListener(_onSearchChanged);
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    final q = _searchController.text.trim().toLowerCase();
    setState(() {
      if (q.isEmpty) {
        _filtered = widget.regencies;
      } else {
        _filtered = widget.regencies
            .where((item) =>
                item.name.toLowerCase().contains(q) ||
                item.province.toLowerCase().contains(q))
            .toList();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Container(
      height: MediaQuery.of(context).size.height * 0.8,
      padding: EdgeInsets.only(bottom: bottomInset),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: _kSlate200,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Pilih Kota / Kabupaten',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: _kSlate900,
                      ),
                    ),
                    Text(
                      'Daftar wilayah se-Indonesia (${widget.regencies.length} kota/kabupaten)',
                      style: const TextStyle(fontSize: 10, color: _kSlate400),
                    ),
                  ],
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded, size: 20, color: _kSlate700),
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: TextField(
              controller: _searchController,
              autofocus: false,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
              decoration: InputDecoration(
                hintText: 'Cari kota atau kabupaten...',
                hintStyle: const TextStyle(fontSize: 12, color: _kSlate400),
                prefixIcon: const Icon(Icons.search_rounded, size: 18, color: _kSlate400),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear_rounded, size: 16, color: _kSlate400),
                        onPressed: () => _searchController.clear(),
                      )
                    : null,
                filled: true,
                fillColor: _kSlate50,
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: const BorderSide(color: _kSlate200),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: const BorderSide(color: _kSlate200),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: const BorderSide(color: _kDark, width: 1.5),
                ),
              ),
            ),
          ),
          const SizedBox(height: 8),
          const Divider(height: 1, color: _kSlate100),
          Expanded(
            child: _filtered.isEmpty
                ? const Center(
                    child: Padding(
                      padding: EdgeInsets.all(24),
                      child: Text(
                        'Kota / Kabupaten tidak ditemukan.\nCoba kata kunci lain.',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 12, color: _kSlate400),
                      ),
                    ),
                  )
                : ListView.builder(
                    itemCount: _filtered.length,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    itemBuilder: (context, index) {
                      final item = _filtered[index];
                      final isSelected = widget.selectedCity != null &&
                          widget.selectedCity!.toLowerCase() == item.name.toLowerCase();
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: InkWell(
                          onTap: () => Navigator.pop(context, item.name),
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                            decoration: BoxDecoration(
                              color: isSelected ? _kSoftLime.withValues(alpha: 0.7) : Colors.transparent,
                              borderRadius: BorderRadius.circular(12),
                              border: isSelected
                                  ? Border.all(color: _kDark, width: 1.2)
                                  : null,
                            ),
                            child: Row(
                              children: [
                                Icon(
                                  Icons.location_city_outlined,
                                  size: 16,
                                  color: isSelected ? _kDark : _kSlate400,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        item.name,
                                        style: TextStyle(
                                          fontSize: 12,
                                          fontWeight: isSelected ? FontWeight.w800 : FontWeight.w700,
                                          color: isSelected ? _kDark : _kSlate900,
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        item.province,
                                        style: TextStyle(
                                          fontSize: 10,
                                          fontWeight: FontWeight.w500,
                                          color: isSelected ? _kDark.withValues(alpha: 0.8) : _kSlate400,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                if (isSelected)
                                  const Icon(
                                    Icons.check_circle_rounded,
                                    size: 18,
                                    color: _kDark,
                                  ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
