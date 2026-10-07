import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../data/venue_service.dart';
import '../domain/venue_model.dart';

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

  // FocusNodes & GlobalKeys for Auto-Focus & Auto-Scroll
  final _nameFocusNode = FocusNode();
  final _addressFocusNode = FocusNode();
  final _picFocusNode = FocusNode();
  final _phoneFocusNode = FocusNode();

  final _nameKey = GlobalKey();
  final _addressKey = GlobalKey();
  final _picKey = GlobalKey();
  final _phoneKey = GlobalKey();

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

  final List<String> _arenaTypeOptions = [
    'Semi-Indoor (Beratap Kanopi)',
    'Indoor (Gedung Tertutup)',
    'Outdoor (Lapangan Terbuka)',
    'Rooftop Outdoor',
  ];

  final List<String> _surfaceOptions = [
    'Artificial Grass / Rumput Sintetis (Padel)',
    'Hard Court (Acrylic / Beton Berpori)',
    'Clay / Tanah Liat',
    'Vinyl / Taraflex Court',
    'Rumput Alami (Natural Grass)',
    'Parquet / Kayu',
  ];

  final List<String> _operatingDayOptions = [
    'Setiap Hari (Senin – Minggu)',
    'Senin – Jumat (Weekdays Saja)',
    'Sabtu – Minggu (Weekend Saja)',
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
      if (_operatingDayOptions.contains(v.hariBuka)) {
        _selectedOperatingDays = v.hariBuka!;
      }
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

    _nameFocusNode.dispose();
    _addressFocusNode.dispose();
    _picFocusNode.dispose();
    _phoneFocusNode.dispose();

    super.dispose();
  }

  void _scrollToAndFocus(GlobalKey key, FocusNode focusNode) {
    focusNode.requestFocus();
    if (key.currentContext != null) {
      Scrollable.ensureVisible(
        key.currentContext!,
        duration: const Duration(milliseconds: 350),
        curve: Curves.easeInOut,
        alignment: 0.15,
      );
    }
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

    final isNameValid = _nameController.text.trim().isNotEmpty;
    final isAddressValid = _addressController.text.trim().isNotEmpty;
    final isPicValid = _picNameController.text.trim().isNotEmpty;
    final isPhoneValid = _picPhoneController.text.trim().isNotEmpty;

    if (!_formKey.currentState!.validate()) {
      if (!isNameValid) {
        _scrollToAndFocus(_nameKey, _nameFocusNode);
      } else if (!isAddressValid) {
        _scrollToAndFocus(_addressKey, _addressFocusNode);
      } else if (!isPicValid) {
        _scrollToAndFocus(_picKey, _picFocusNode);
      } else if (!isPhoneValid) {
        _scrollToAndFocus(_phoneKey, _phoneFocusNode);
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Harap lengkapi field wajib bertanda bintang (*) yang belum terisi.'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
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
                  Container(
                    key: _nameKey,
                    child: TextFormField(
                      controller: _nameController,
                      focusNode: _nameFocusNode,
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Nama venue wajib diisi' : null,
                      decoration: _inputDecoration(
                        hint: 'Contoh: Bandung Arena',
                        prefixIcon: Icons.apartment_rounded,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),

                  _buildInputLabel('Alamat Lengkap Venue', isRequired: true),
                  const SizedBox(height: 6),
                  Container(
                    key: _addressKey,
                    child: TextFormField(
                      controller: _addressController,
                      focusNode: _addressFocusNode,
                      maxLines: 2,
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Alamat venue wajib diisi' : null,
                      decoration: _inputDecoration(
                        hint: 'Alamat lengkap lokasi venue...',
                        prefixIcon: Icons.location_on_outlined,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),

                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _buildInputLabel('Kota / Kabupaten', isRequired: true),
                            const SizedBox(height: 6),
                            DropdownButtonFormField<String>(
                              initialValue: _selectedCity,
                              isExpanded: true,
                              items: _cityOptions.map((city) {
                                return DropdownMenuItem(
                                  value: city,
                                  child: Text(city, style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A))),
                                );
                              }).toList(),
                              onChanged: (val) {
                                if (val != null) setState(() => _selectedCity = val);
                              },
                              decoration: _inputDecoration(
                                hint: 'Pilih Kota',
                                prefixIcon: Icons.location_city_rounded,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
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

                  _buildInputLabel('Tipe Arena Lapangan'),
                  const SizedBox(height: 6),
                  DropdownButtonFormField<String>(
                    initialValue: _selectedArenaType,
                    isExpanded: true,
                    items: _arenaTypeOptions.map((type) {
                      return DropdownMenuItem(
                        value: type,
                        child: Text(type, style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A))),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setState(() => _selectedArenaType = val);
                    },
                    decoration: _inputDecoration(hint: 'Pilih Tipe Arena'),
                  ),
                  const SizedBox(height: 14),

                  _buildInputLabel('Jenis Permukaan / Karpet'),
                  const SizedBox(height: 6),
                  DropdownButtonFormField<String>(
                    initialValue: _selectedSurface,
                    isExpanded: true,
                    items: _surfaceOptions.map((surf) {
                      return DropdownMenuItem(
                        value: surf,
                        child: Text(surf, style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A))),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setState(() => _selectedSurface = val);
                    },
                    decoration: _inputDecoration(hint: 'Pilih Jenis Permukaan'),
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

                  _buildInputLabel('Hari Operasional'),
                  const SizedBox(height: 6),
                  DropdownButtonFormField<String>(
                    initialValue: _selectedOperatingDays,
                    isExpanded: true,
                    items: _operatingDayOptions.map((day) {
                      return DropdownMenuItem(
                        value: day,
                        child: Text(day, style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A))),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setState(() => _selectedOperatingDays = val);
                    },
                    decoration: _inputDecoration(hint: 'Pilih Hari Operasional'),
                  ),
                  const SizedBox(height: 14),

                  _buildInputLabel('Nama PIC / Pengelola Lapangan', isRequired: true),
                  const SizedBox(height: 6),
                  Container(
                    key: _picKey,
                    child: TextFormField(
                      controller: _picNameController,
                      focusNode: _picFocusNode,
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Nama PIC wajib diisi' : null,
                      decoration: _inputDecoration(
                        hint: 'Contoh: Bpk. Bambang Pamungkas',
                        prefixIcon: Icons.person_outline_rounded,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),

                  _buildInputLabel('Nomor WhatsApp / Hotline PIC', isRequired: true),
                  const SizedBox(height: 6),
                  Container(
                    key: _phoneKey,
                    child: TextFormField(
                      controller: _picPhoneController,
                      focusNode: _phoneFocusNode,
                      keyboardType: TextInputType.phone,
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Nomor WhatsApp PIC wajib diisi' : null,
                      decoration: _inputDecoration(
                        hint: 'Contoh: 081234567890',
                        prefixIcon: Icons.phone_outlined,
                      ),
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
