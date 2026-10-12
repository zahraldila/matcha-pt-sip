import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../data/venue_service.dart';
import '../domain/venue_model.dart';

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

class EditVenuePage extends StatefulWidget {
  final VenueModel venue;
  final AuthController? authController;

  const EditVenuePage({
    super.key,
    required this.venue,
    this.authController,
  });

  @override
  State<EditVenuePage> createState() => _EditVenuePageState();
}

class _EditVenuePageState extends State<EditVenuePage> {
  final _formKey = GlobalKey<FormState>();
  final VenueService _venueService = VenueService();

  late TextEditingController _nameController;
  late TextEditingController _addressController;
  late TextEditingController _googleMapsController;
  late TextEditingController _picNameController;
  late TextEditingController _picPhoneController;
  late TextEditingController _notesController;

  List<_RegencyItem> _allRegencies = [];
  String _selectedCity = 'Bandung';
  String _selectedCategory = 'Padel Court'; // 'Padel Court', 'Tennis Court', 'Multi-Racquet'
  String _selectedArenaType = 'Semi-Indoor (Beratap Kanopi)';
  String _selectedSurface = 'Artificial Grass / Rumput Sintetis (Padel)';
  TimeOfDay _openTime = const TimeOfDay(hour: 8, minute: 0);
  TimeOfDay _closeTime = const TimeOfDay(hour: 22, minute: 0);
  String _selectedOperatingDays = 'Setiap Hari (Senin – Minggu)';
  Set<String> _selectedFacilities = {};

  bool _isSaving = false;

  final List<String> _cityOptions = [
    'Bandung',
    'Kab. Bandung',
    'Kota Cimahi',
    'Jakarta Selatan',
    'Jakarta Pusat',
    'Jakarta Barat',
    'Jakarta Utara',
    'Jakarta Timur',
    'Tangerang',
    'Tangerang Selatan',
    'Bekasi',
    'Depok',
    'Bogor',
    'Surabaya',
    'Semarang',
    'Yogyakarta',
    'Bali / Badung',
  ];

  static final List<Map<String, dynamic>> _arenaTypeDetailedOptions = [
    {
      'value': 'Semi-Indoor (Beratap Kanopi)',
      'title': 'Semi-Indoor (Beratap Kanopi)',
      'subtitle': 'Beratap pelindung kanopi dengan sirkulasi udara samping terbuka',
      'badge': 'POPULER',
      'icon': Icons.roofing_rounded,
    },
    {
      'value': 'Indoor (Gedung Tertutup)',
      'title': 'Indoor (Gedung Tertutup)',
      'subtitle': 'Gedung olahraga tertutup penuh dengan tata cahaya & sirkulasi AC/exhaust',
      'badge': 'ALL-WEATHER',
      'icon': Icons.domain_rounded,
    },
    {
      'value': 'Outdoor (Lapangan Terbuka)',
      'title': 'Outdoor (Lapangan Terbuka)',
      'subtitle': 'Lapangan terbuka alami dengan pencahayaan sinar matahari dan angin segar',
      'badge': 'TERBUKA',
      'icon': Icons.wb_sunny_rounded,
    },
    {
      'value': 'Rooftop Outdoor',
      'title': 'Rooftop Outdoor',
      'subtitle': 'Lapangan di atap gedung dengan pemandangan kota dan suasana eksklusif',
      'badge': 'PREMIUM',
      'icon': Icons.location_city_rounded,
    },
  ];

  static final List<Map<String, dynamic>> _surfaceDetailedOptions = [
    {
      'value': 'Artificial Grass / Rumput Sintetis (Padel)',
      'title': 'Rumput Sintetis (Padel)',
      'subtitle': 'Karpet rumput sintetis berpasir silika standar FIP/WPT, ideal untuk padel',
      'badge': 'STANDAR PADEL',
      'icon': Icons.grass_rounded,
    },
    {
      'value': 'Hard Court (Acrylic / Beton Berpori)',
      'title': 'Hard Court (Acrylic / Beton)',
      'subtitle': 'Permukaan keras beraspal/akrilik dengan pantulan bola cepat & konsisten',
      'badge': 'STANDAR TENIS',
      'icon': Icons.layers_rounded,
    },
    {
      'value': 'Clay / Tanah Liat',
      'title': 'Clay / Tanah Liat',
      'subtitle': 'Permukaan tanah liat merah klasik dengan tempo permainan lambat & slide',
      'badge': 'KLASIK',
      'icon': Icons.terrain_rounded,
    },
    {
      'value': 'Vinyl / Taraflex Court',
      'title': 'Vinyl / Taraflex Court',
      'subtitle': 'Lantai sintetis empuk dengan peredam kejut maksimal untuk sendi lutut',
      'badge': 'INDOOR PRO',
      'icon': Icons.crop_square_rounded,
    },
    {
      'value': 'Rumput Alami (Natural Grass)',
      'title': 'Rumput Alami (Natural Grass)',
      'subtitle': 'Permukaan rumput alami hijau tradisional gaya Wimbledon',
      'badge': 'TRADISIONAL',
      'icon': Icons.yard_rounded,
    },
    {
      'value': 'Parquet / Kayu',
      'title': 'Parquet / Lantai Kayu',
      'subtitle': 'Lantai kayu solid indoor dengan elastisitas pantulan tinggi',
      'badge': 'MULTI-SPORT',
      'icon': Icons.grid_view_rounded,
    },
  ];

  static final List<Map<String, dynamic>> _operatingDayDetailedOptions = [
    {
      'value': 'Setiap Hari (Senin – Minggu)',
      'title': 'Setiap Hari (Senin – Minggu)',
      'subtitle': 'Buka setiap hari 7 hari seminggu tanpa libur reguler',
      'badge': 'REKOMENDASI',
      'icon': Icons.calendar_today_rounded,
    },
    {
      'value': 'Senin – Jumat (Weekdays Saja)',
      'title': 'Senin – Jumat (Weekdays Saja)',
      'subtitle': 'Khusus hari kerja reguler, akhir pekan libur',
      'badge': 'WEEKDAYS',
      'icon': Icons.work_outline_rounded,
    },
    {
      'value': 'Sabtu – Minggu (Weekend Saja)',
      'title': 'Sabtu – Minggu (Weekend Saja)',
      'subtitle': 'Khusus akhir pekan sabtu dan minggu saja',
      'badge': 'WEEKEND ONLY',
      'icon': Icons.weekend_outlined,
    },
  ];

  final List<Map<String, dynamic>> _facilityMaster = [
    {'name': 'Parkir Luas', 'icon': Icons.local_parking_rounded},
    {'name': 'Toilet / Restroom', 'icon': Icons.wc_rounded},
    {'name': 'Ruang Ganti', 'icon': Icons.checkroom_rounded},
    {'name': 'Kantin / Coffee Shop', 'icon': Icons.local_cafe_outlined},
    {'name': 'Musholla', 'icon': Icons.mosque_outlined},
    {'name': 'Wi-Fi Gratis', 'icon': Icons.wifi_rounded},
    {'name': 'Pro Shop / Raket Rental', 'icon': Icons.sports_tennis_rounded},
    {'name': 'Loker Penyimpanan', 'icon': Icons.lock_outline_rounded},
    {'name': 'Shower Air Hangat', 'icon': Icons.shower_rounded},
    {'name': 'Tribun Penonton', 'icon': Icons.groups_outlined},
    {'name': 'Penerangan Lampu Malam', 'icon': Icons.lightbulb_outline_rounded},
    {'name': 'First Aid / P3K', 'icon': Icons.medical_services_outlined},
  ];

  @override
  void initState() {
    super.initState();
    _loadRegencies();
    final v = widget.venue;
    _nameController = TextEditingController(text: v.namaVenue);
    _addressController = TextEditingController(text: v.alamat ?? '');
    _googleMapsController = TextEditingController();
    _picNameController = TextEditingController(
      text: (v.namaPic?.isNotEmpty == true) ? v.namaPic! : 'Marcello Este Camaro',
    );
    _picPhoneController = TextEditingController(
      text: (v.noWhatsapp?.isNotEmpty == true) ? v.noWhatsapp! : '082119765944',
    );
    _notesController = TextEditingController(
      text: (v.catatan?.isNotEmpty == true) ? v.catatan! : 'Sesuai jadwal ketersediaan lapangan reguler.',
    );

    // City initial
    if (v.kota != null && v.kota!.isNotEmpty) {
      if (_cityOptions.contains(v.kota)) {
        _selectedCity = v.kota!;
      } else {
        _cityOptions.insert(0, v.kota!);
        _selectedCity = v.kota!;
      }
    }

    // Category initial
    if (v.sportName == 'Padel & Tennis') {
      _selectedCategory = 'Multi-Racquet';
    } else if (v.sportName == 'Tennis') {
      _selectedCategory = 'Tennis Court';
    } else {
      _selectedCategory = 'Padel Court';
    }

    // Operating days & hours parse
    if (v.hariBuka != null && v.hariBuka!.isNotEmpty) {
      _selectedOperatingDays = v.hariBuka!;
    }

    if (v.jamOperasional != null && v.jamOperasional!.contains('-')) {
      final parts = v.jamOperasional!.replaceAll('WIB', '').trim().split('-');
      if (parts.length == 2) {
        _openTime = _parseTime(parts[0].trim(), const TimeOfDay(hour: 8, minute: 0));
        _closeTime = _parseTime(parts[1].trim(), const TimeOfDay(hour: 22, minute: 0));
      }
    }

    // Facilities Smart Fuzzy Matching
    final rawFacilities = (v.fasilitas ?? 'Parkir, Toilet, Ruang Ganti')
        .split(',')
        .map((e) => e.trim())
        .where((e) => e.isNotEmpty)
        .toList();

    _selectedFacilities = {};
    for (final raw in rawFacilities) {
      final rawLower = raw.toLowerCase();
      for (final master in _facilityMaster) {
        final masterName = master['name'] as String;
        final masterLower = masterName.toLowerCase();
        if (masterLower == rawLower ||
            masterLower.contains(rawLower) ||
            rawLower.contains(masterLower) ||
            (rawLower.contains('parkir') && masterLower.contains('parkir')) ||
            (rawLower.contains('toilet') && masterLower.contains('toilet')) ||
            (rawLower.contains('ganti') && masterLower.contains('ganti')) ||
            (rawLower.contains('kantin') && masterLower.contains('kantin')) ||
            (rawLower.contains('musholla') && masterLower.contains('musholla')) ||
            (rawLower.contains('wifi') && masterLower.contains('wi-fi')) ||
            (rawLower.contains('raket') && masterLower.contains('raket')) ||
            (rawLower.contains('loker') && masterLower.contains('loker')) ||
            (rawLower.contains('shower') && masterLower.contains('shower')) ||
            (rawLower.contains('lampu') && masterLower.contains('lampu')) ||
            (rawLower.contains('tribun') && masterLower.contains('tribun')) ||
            (rawLower.contains('p3k') && masterLower.contains('p3k'))) {
          _selectedFacilities.add(masterName);
        }
      }
    }
  }

  TimeOfDay _parseTime(String raw, TimeOfDay fallback) {
    try {
      final cleaned = raw.replaceAll('.', ':').trim();
      final p = cleaned.split(':');
      if (p.length >= 2) {
        return TimeOfDay(hour: int.parse(p[0]), minute: int.parse(p[1]));
      }
    } catch (_) {}
    return fallback;
  }

  String _formatTimeOfDay(TimeOfDay t) {
    final h = t.hour.toString().padLeft(2, '0');
    final m = t.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }

  @override
  void dispose() {
    _nameController.dispose();
    _addressController.dispose();
    _googleMapsController.dispose();
    _picNameController.dispose();
    _picPhoneController.dispose();
    _notesController.dispose();
    super.dispose();
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
      debugPrint('Error loading regencies in EditVenuePage: $e');
    }
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
        fallbackCities: _cityOptions,
        selectedCity: _selectedCity,
      ),
    );

    if (selected != null && mounted) {
      setState(() {
        _selectedCity = selected;
      });
    }
  }

  Widget _buildCitySelectorTile() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildInputLabel('Kota / Kabupaten', isRequired: true),
        const SizedBox(height: 6),
        InkWell(
          onTap: _openCitySearchPicker,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            height: 48,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                const Icon(Icons.location_city_rounded, size: 18, color: Color(0xFF64748B)),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _selectedCity.isNotEmpty ? _selectedCity : 'Pilih Kota / Kabupaten',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: _selectedCity.isNotEmpty ? const Color(0xFF0F172A) : const Color(0xFF94A3B8),
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const Icon(Icons.keyboard_arrow_down_rounded, size: 20, color: Color(0xFF94A3B8)),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSelectorTile<T>({
    required String label,
    required T value,
    required List<Map<String, dynamic>> options,
    required VoidCallback onTap,
    IconData? icon,
    bool isRequired = false,
  }) {
    Map<String, dynamic> selectedOption = options.first;
    for (final opt in options) {
      if (opt['value'] == value) {
        selectedOption = opt;
        break;
      }
    }
    if (selectedOption['value'] != value && value is String) {
      final normVal = value.replaceAll('–', '-').trim().toLowerCase();
      for (final opt in options) {
        final optVal = (opt['value'] as String).replaceAll('–', '-').trim().toLowerCase();
        if (optVal == normVal || optVal.contains(normVal) || normVal.contains(optVal)) {
          selectedOption = opt;
          break;
        }
      }
    }
    final displayText = (selectedOption['label'] ?? selectedOption['title'] ?? '$value') as String;
    final itemIcon = icon ?? (selectedOption['icon'] as IconData? ?? Icons.tune_rounded);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildInputLabel(label, isRequired: isRequired),
        const SizedBox(height: 6),
        InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            height: 48,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                Icon(itemIcon, size: 18, color: const Color(0xFF64748B)),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    displayText,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF0F172A),
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const Icon(Icons.keyboard_arrow_down_rounded, size: 20, color: Color(0xFF94A3B8)),
              ],
            ),
          ),
        ),
      ],
    );
  }

  void _showMatchaPickerBottomSheet<T>({
    required String title,
    required String subtitle,
    required List<Map<String, dynamic>> items,
    required T selectedValue,
    required ValueChanged<T> onSelect,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          subtitle,
                          style: const TextStyle(
                            fontSize: 11,
                            color: Color(0xFF94A3B8),
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close_rounded, color: Color(0xFF94A3B8), size: 22),
                    onPressed: () => Navigator.pop(ctx),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              const Divider(height: 1, color: Color(0xFFF1F5F9)),
              const SizedBox(height: 12),
              Flexible(
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: items.map((item) {
                      final val = item['value'] as T;
                      final isSelected = val == selectedValue ||
                          (val is String &&
                              selectedValue is String &&
                              val.replaceAll('–', '-').trim().toLowerCase() ==
                                  selectedValue.replaceAll('–', '-').trim().toLowerCase());
                      final itemTitle = (item['label'] ?? item['title']) as String;
                      final itemSub = item['subtitle'] as String? ?? '';
                      final itemBadge = item['badge'] as String?;
                      final itemIcon = item['icon'] as IconData? ?? Icons.check_circle_outline_rounded;

                      return Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: InkWell(
                          onTap: () {
                            onSelect(val);
                            Navigator.pop(ctx);
                          },
                          borderRadius: BorderRadius.circular(14),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 150),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: isSelected ? AppColors.matchaSoftLime.withValues(alpha: 0.45) : Colors.white,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                                width: isSelected ? 1.5 : 1,
                              ),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(9),
                                  decoration: BoxDecoration(
                                    color: isSelected ? AppColors.matchaDark : const Color(0xFFF1F5F9),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Icon(
                                    itemIcon,
                                    size: 18,
                                    color: isSelected ? AppColors.matchaLime : const Color(0xFF94A3B8),
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
                                              itemTitle,
                                              style: TextStyle(
                                                fontSize: 12,
                                                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                                                color: isSelected ? AppColors.matchaDark : const Color(0xFF0F172A),
                                              ),
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                          if (itemBadge != null) ...[
                                            const SizedBox(width: 6),
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                                              decoration: BoxDecoration(
                                                color: isSelected ? AppColors.matchaDark : const Color(0xFFF1F5F9),
                                                borderRadius: BorderRadius.circular(5),
                                              ),
                                              child: Text(
                                                itemBadge,
                                                style: TextStyle(
                                                  fontSize: 8.5,
                                                  fontWeight: FontWeight.w700,
                                                  color: isSelected ? AppColors.matchaLime : const Color(0xFF334155),
                                                ),
                                              ),
                                            ),
                                          ],
                                        ],
                                      ),
                                      if (itemSub.isNotEmpty) ...[
                                        const SizedBox(height: 3),
                                        Text(
                                          itemSub,
                                          style: TextStyle(
                                            fontSize: 10.5,
                                            color: isSelected ? AppColors.matchaDark.withValues(alpha: 0.8) : const Color(0xFF94A3B8),
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Icon(
                                  isSelected ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                                  color: isSelected ? AppColors.matchaDark : const Color(0xFFCBD5E1),
                                  size: 20,
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _handleSave() async {
    final user = widget.authController?.currentUser;
    final isOwner = user != null && widget.venue.ownerUserId != null && widget.venue.ownerUserId == user.userId;
    final isAdmin = user?.isAdmin == true;
    if (!isOwner && !isAdmin) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Akses ditolak: Anda bukan pemilik venue ini.'),
          backgroundColor: Colors.redAccent,
        ),
      );
      return;
    }

    if (!_formKey.currentState!.validate()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Harap lengkapi semua data wajib bertanda bintang (*)'),
          backgroundColor: Colors.redAccent,
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      final jamString = '${_formatTimeOfDay(_openTime)} - ${_formatTimeOfDay(_closeTime)} WIB';
      final fasilitasString = _selectedFacilities.join(', ');

      final updatedVenue = await _venueService.updateVenue(
        venueId: widget.venue.venueId,
        namaVenue: _nameController.text.trim(),
        alamat: _addressController.text.trim(),
        kota: _selectedCity,
        jamOperasional: jamString,
        hariBuka: _selectedOperatingDays,
        namaPic: _picNameController.text.trim().isEmpty ? null : _picNameController.text.trim(),
        noWhatsapp: _picPhoneController.text.trim().isEmpty ? null : _picPhoneController.text.trim(),
        fasilitas: fasilitasString.isEmpty ? null : fasilitasString,
        catatan: _notesController.text.trim().isEmpty ? null : _notesController.text.trim(),
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Informasi venue berhasil diperbarui! 🎉'),
          backgroundColor: AppColors.matchaDark,
          behavior: SnackBarBehavior.floating,
        ),
      );

      Navigator.pop(context, updatedVenue);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal memperbarui venue: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
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
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Edit Informasi Venue',
          style: AppTextStyles.h2.copyWith(
            fontSize: 16,
            color: const Color(0xFF0F172A),
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: true,
      ),
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Page Subtitle
              Text(
                'Edit Informasi Venue',
                style: AppTextStyles.h1.copyWith(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: const Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Perbarui rincian venue, jam operasional reguler, fasilitas, serta kontak PIC penanggung jawab lapangan Anda.',
                style: AppTextStyles.caption.copyWith(
                  fontSize: 12,
                  color: const Color(0xFF64748B),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 16),

              // Section 1: INFORMASI UTAMA VENUE
              _buildCardSection(
                number: '1',
                title: 'INFORMASI UTAMA VENUE',
                children: [
                  _buildInputLabel('Nama Tempat / Venue', isRequired: true),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _nameController,
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Nama venue wajib diisi' : null,
                    decoration: _inputDecoration(
                      hint: 'Contoh: Bandung Arena',
                      prefixIcon: Icons.apartment_rounded,
                    ),
                  ),
                  const SizedBox(height: 14),

                  _buildInputLabel('Alamat Lengkap Venue', isRequired: true),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _addressController,
                    maxLines: 2,
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Alamat venue wajib diisi' : null,
                    decoration: _inputDecoration(
                      hint: 'Alamat lengkap lokasi venue...',
                      prefixIcon: Icons.location_on_outlined,
                    ),
                  ),
                  const SizedBox(height: 14),

                  _buildCitySelectorTile(),
                  const SizedBox(height: 14),

                  _buildInputLabel('Link Google Maps (Opsional)'),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _googleMapsController,
                    decoration: _inputDecoration(
                      hint: 'https://maps.app.goo.gl/...',
                      prefixIcon: Icons.link_rounded,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Section 2: CABANG OLAHRAGA & KARAKTERISTIK LAPANGAN
              _buildCardSection(
                number: '2',
                title: 'CABANG OLAHRAGA & KARAKTERISTIK LAPANGAN',
                children: [
                  _buildInputLabel('Kategori Lapangan Utama', isRequired: true),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: _buildSportCategoryCard(
                          title: 'Padel Court',
                          subtitle: 'Khusus Lapangan Padel',
                          icon: Icons.sports_tennis_rounded,
                          isSelected: _selectedCategory == 'Padel Court',
                          onTap: () => setState(() => _selectedCategory = 'Padel Court'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _buildSportCategoryCard(
                          title: 'Tennis Court',
                          subtitle: 'Khusus Lapangan Tenis',
                          icon: Icons.sports_tennis_outlined,
                          isSelected: _selectedCategory == 'Tennis Court',
                          onTap: () => setState(() => _selectedCategory = 'Tennis Court'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _buildSportCategoryCard(
                          title: 'Multi-Racquet',
                          subtitle: 'Padel & Tennis Gabungan',
                          icon: Icons.emoji_events_outlined,
                          isSelected: _selectedCategory == 'Multi-Racquet',
                          onTap: () => setState(() => _selectedCategory = 'Multi-Racquet'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),

                  _buildSelectorTile(
                    label: 'Tipe Arena Lapangan',
                    value: _selectedArenaType,
                    options: _arenaTypeDetailedOptions,
                    icon: Icons.roofing_rounded,
                    onTap: () {
                      _showMatchaPickerBottomSheet(
                        title: 'Tipe Arena Lapangan',
                        subtitle: 'Pilih tipe pelindung dan struktur venue Anda',
                        items: _arenaTypeDetailedOptions,
                        selectedValue: _selectedArenaType,
                        onSelect: (val) {
                          setState(() => _selectedArenaType = val);
                        },
                      );
                    },
                  ),
                  const SizedBox(height: 14),

                  _buildSelectorTile(
                    label: 'Jenis Permukaan / Karpet',
                    value: _selectedSurface,
                    options: _surfaceDetailedOptions,
                    icon: Icons.grass_rounded,
                    onTap: () {
                      _showMatchaPickerBottomSheet(
                        title: 'Jenis Permukaan / Karpet',
                        subtitle: 'Pilih material karpet lapangan yang digunakan',
                        items: _surfaceDetailedOptions,
                        selectedValue: _selectedSurface,
                        onSelect: (val) {
                          setState(() => _selectedSurface = val);
                        },
                      );
                    },
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Section 3: JADWAL OPERASIONAL & KONTAK PENGELOLA
              _buildCardSection(
                number: '3',
                title: 'JADWAL OPERASIONAL & KONTAK PENGELOLA',
                children: [
                  _buildInputLabel('Jam Operasional Reguler', isRequired: true),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Expanded(
                        child: InkWell(
                          onTap: () async {
                            final picked = await showTimePicker(
                              context: context,
                              initialTime: _openTime,
                              initialEntryMode: TimePickerEntryMode.dialOnly,
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
                            if (picked != null) setState(() => _openTime = picked);
                          },
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAFC),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.access_time_rounded, size: 16, color: Color(0xFF64748B)),
                                const SizedBox(width: 8),
                                Text(
                                  'Buka: ${_formatTimeOfDay(_openTime)}',
                                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: InkWell(
                          onTap: () async {
                            final picked = await showTimePicker(
                              context: context,
                              initialTime: _closeTime,
                              initialEntryMode: TimePickerEntryMode.dialOnly,
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
                            if (picked != null) setState(() => _closeTime = picked);
                          },
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAFC),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.access_time_rounded, size: 16, color: Color(0xFF64748B)),
                                const SizedBox(width: 8),
                                Text(
                                  'Tutup: ${_formatTimeOfDay(_closeTime)}',
                                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),

                  _buildSelectorTile(
                    label: 'Hari Operasional',
                    value: _selectedOperatingDays,
                    options: _operatingDayDetailedOptions,
                    icon: Icons.calendar_today_rounded,
                    onTap: () {
                      _showMatchaPickerBottomSheet(
                        title: 'Hari Operasional',
                        subtitle: 'Tentukan hari buka rutin venue Anda setiap minggunya',
                        items: _operatingDayDetailedOptions,
                        selectedValue: _selectedOperatingDays,
                        onSelect: (val) {
                          setState(() => _selectedOperatingDays = val);
                        },
                      );
                    },
                  ),
                  const SizedBox(height: 14),

                  _buildInputLabel('Nama PIC / Pengelola Lapangan', isRequired: true),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _picNameController,
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Nama PIC wajib diisi' : null,
                    decoration: _inputDecoration(
                      hint: 'Contoh: Bpk. Bambang Pamungkas',
                      prefixIcon: Icons.person_outline_rounded,
                    ),
                  ),
                  const SizedBox(height: 14),

                  _buildInputLabel('Nomor WhatsApp / Hotline PIC', isRequired: true),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _picPhoneController,
                    keyboardType: TextInputType.phone,
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Nomor WhatsApp PIC wajib diisi' : null,
                    decoration: _inputDecoration(
                      hint: 'Contoh: 081234567890',
                      prefixIcon: Icons.phone_outlined,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Section 4: FASILITAS & CATATAN TAMBAHAN
              _buildCardSection(
                number: '4',
                title: 'FASILITAS & CATATAN TAMBAHAN',
                children: [
                  _buildInputLabel('Pilih Fasilitas yang Tersedia di Venue'),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: _facilityMaster.map((fac) {
                      final name = fac['name'] as String;
                      final icon = fac['icon'] as IconData;
                      final isSelected = _selectedFacilities.contains(name);

                      return InkWell(
                        onTap: () {
                          setState(() {
                            if (isSelected) {
                              _selectedFacilities.remove(name);
                            } else {
                              _selectedFacilities.add(name);
                            }
                          });
                        },
                        borderRadius: BorderRadius.circular(10),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                          decoration: BoxDecoration(
                            color: isSelected ? const Color(0xFFF0FDF4) : const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(
                              color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                              width: isSelected ? 1.5 : 1,
                            ),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                icon,
                                size: 14,
                                color: isSelected ? AppColors.matchaDark : const Color(0xFF64748B),
                              ),
                              const SizedBox(width: 6),
                              Text(
                                name,
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                                  color: isSelected ? AppColors.matchaDark : const Color(0xFF475569),
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 16),

                  _buildInputLabel('Catatan Khusus & Ketentuan Operasional (Opsional)'),
                  const SizedBox(height: 6),
                  TextFormField(
                    controller: _notesController,
                    maxLines: 3,
                    decoration: _inputDecoration(
                      hint: 'Contoh: Wajib menggunakan sepatu khusus tennis/padel non-marking sole. Pembatalan sewa maksimal 24 jam sebelum jadwal sesi...',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),

              // Bottom Buttons
              Row(
                children: [
                  Expanded(
                    flex: 1,
                    child: OutlinedButton(
                      onPressed: _isSaving ? null : () => Navigator.pop(context),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF475569),
                        side: const BorderSide(color: Color(0xFFCBD5E1)),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text('Batal', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: ElevatedButton.icon(
                      onPressed: _isSaving ? null : _handleSave,
                      icon: _isSaving
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Icon(Icons.save_rounded, size: 18),
                      label: Text(
                        _isSaving ? 'Menyimpan...' : 'Simpan Perubahan Venue',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.matchaDark,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        elevation: 0,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCardSection({
    required String number,
    required String title,
    required List<Widget> children,
  }) {
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
            children: [
              Container(
                width: 22,
                height: 22,
                decoration: const BoxDecoration(
                  color: Color(0xFF0F172A),
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text(
                    number,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F172A),
                    letterSpacing: 0.2,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          ...children,
        ],
      ),
    );
  }

  Widget _buildInputLabel(String label, {bool isRequired = false}) {
    return RichText(
      text: TextSpan(
        text: label,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: Color(0xFF334155),
        ),
        children: isRequired
            ? const [
                TextSpan(
                  text: ' *',
                  style: TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold),
                ),
              ]
            : null,
      ),
    );
  }

  InputDecoration _inputDecoration({required String hint, IconData? prefixIcon}) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
      prefixIcon: prefixIcon != null ? Icon(prefixIcon, size: 18, color: const Color(0xFF94A3B8)) : null,
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

  Widget _buildSportCategoryCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFF0FDF4) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Column(
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: isSelected ? AppColors.matchaSoftLime : Colors.white,
                shape: BoxShape.circle,
                border: Border.all(
                  color: isSelected ? AppColors.matchaDark.withValues(alpha: 0.3) : const Color(0xFFE2E8F0),
                ),
              ),
              child: Icon(
                icon,
                size: 16,
                color: isSelected ? AppColors.matchaDark : const Color(0xFF64748B),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              title,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.bold,
                color: isSelected ? AppColors.matchaDark : const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              textAlign: TextAlign.center,
              maxLines: 2,
              style: TextStyle(
                fontSize: 9,
                color: isSelected ? const Color(0xFF166534) : const Color(0xFF64748B),
              ),
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
  final List<String> fallbackCities;
  final String? selectedCity;

  const _CitySearchBottomSheet({
    required this.regencies,
    required this.fallbackCities,
    this.selectedCity,
  });

  @override
  State<_CitySearchBottomSheet> createState() => _CitySearchBottomSheetState();
}

class _CitySearchBottomSheetState extends State<_CitySearchBottomSheet> {
  final _searchController = TextEditingController();
  List<_RegencyItem> _all = [];
  List<_RegencyItem> _filtered = [];

  @override
  void initState() {
    super.initState();
    if (widget.regencies.isNotEmpty) {
      _all = widget.regencies;
    } else {
      _all = widget.fallbackCities
          .map((c) => _RegencyItem(id: c, name: c, province: 'Indonesia'))
          .toList();
    }
    _filtered = _all;
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
        _filtered = _all;
      } else {
        _filtered = _all
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
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFE2E8F0),
                borderRadius: BorderRadius.circular(2),
              ),
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
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    Text(
                      'Daftar wilayah se-Indonesia (${_all.length} kota/kabupaten)',
                      style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                    ),
                  ],
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded, size: 20, color: Color(0xFF334155)),
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
                hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                prefixIcon: const Icon(Icons.search_rounded, size: 18, color: Color(0xFF94A3B8)),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear_rounded, size: 16, color: Color(0xFF94A3B8)),
                        onPressed: () => _searchController.clear(),
                      )
                    : null,
                filled: true,
                fillColor: const Color(0xFFF8FAFC),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                ),
                focusedBorder: const OutlineInputBorder(
                  borderRadius: BorderRadius.all(Radius.circular(14)),
                  borderSide: BorderSide(color: AppColors.matchaDark, width: 1.5),
                ),
              ),
            ),
          ),
          const SizedBox(height: 8),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          Expanded(
            child: _filtered.isEmpty
                ? const Center(
                    child: Padding(
                      padding: EdgeInsets.all(24),
                      child: Text(
                        'Kota / Kabupaten tidak ditemukan.\nCoba kata kunci lain.',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
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
                              color: isSelected ? AppColors.matchaSoftLime.withValues(alpha: 0.7) : Colors.transparent,
                              borderRadius: BorderRadius.circular(12),
                              border: isSelected
                                  ? Border.all(color: AppColors.matchaDark, width: 1.2)
                                  : null,
                            ),
                            child: Row(
                              children: [
                                Icon(
                                  Icons.location_city_outlined,
                                  size: 16,
                                  color: isSelected ? AppColors.matchaDark : const Color(0xFF94A3B8),
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
                                          color: isSelected ? AppColors.matchaDark : const Color(0xFF0F172A),
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        item.province,
                                        style: TextStyle(
                                          fontSize: 10,
                                          fontWeight: FontWeight.w500,
                                          color: isSelected ? AppColors.matchaDark.withValues(alpha: 0.8) : const Color(0xFF94A3B8),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                if (isSelected)
                                  const Icon(
                                    Icons.check_circle_rounded,
                                    size: 18,
                                    color: AppColors.matchaDark,
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
