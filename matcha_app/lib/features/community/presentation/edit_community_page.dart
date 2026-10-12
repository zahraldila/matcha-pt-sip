import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/theme/app_colors.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../court/data/venue_service.dart';
import '../../court/domain/venue_model.dart';
import '../data/community_remote_data_source.dart';
import '../domain/community_model.dart';

class EditCommunityPage extends StatefulWidget {
  final CommunityModel community;
  final AuthController? authController;

  const EditCommunityPage({
    super.key,
    required this.community,
    this.authController,
  });

  @override
  State<EditCommunityPage> createState() => _EditCommunityPageState();
}

class _EditCommunityPageState extends State<EditCommunityPage> {
  final _formKey = GlobalKey<FormState>();
  final CommunityRemoteDataSource _dataSource = CommunityRemoteDataSource();
  final VenueService _venueService = VenueService();
  final ImagePicker _picker = ImagePicker();

  late TextEditingController _namaController;
  late TextEditingController _taglineController;
  late TextEditingController _kotaController;
  late TextEditingController _jadwalRutinController;
  late TextEditingController _deskripsiController;

  String _selectedSport = 'padel';
  String _selectedTargetLevel = 'Semua Level (Open to All)';
  String _selectedStatusKeanggotaan = 'Open';
  String? _selectedHomebaseVenue;

  XFile? _pickedLogo;
  Uint8List? _pickedLogoBytes;

  List<VenueModel> _venues = [];
  bool _isSaving = false;

  // Sport Options
  static final List<Map<String, dynamic>> _sportOptions = [
    {
      'value': 'padel',
      'label': '🏓 Padel',
      'title': 'Padel Court',
      'subtitle': 'Khusus olahraga & game padel',
      'badge': 'PADEL',
      'icon': Icons.sports_tennis_rounded,
    },
    {
      'value': 'tennis',
      'label': '🎾 Tennis',
      'title': 'Tennis Court',
      'subtitle': 'Khusus olahraga & game tenis lapangan',
      'badge': 'TENIS',
      'icon': Icons.sports_tennis_outlined,
    },
    {
      'value': 'all_racquet',
      'label': '🏓🎾 Keduanya (All Racquet)',
      'title': 'All Racquet (Padel & Tennis)',
      'subtitle': 'Mendukung kedua cabang olahraga raket',
      'badge': 'ALL-RACQUET',
      'icon': Icons.emoji_events_outlined,
    },
  ];

  // Target Level Options
  static final List<Map<String, dynamic>> _targetLevelOptions = [
    {
      'value': 'Semua Level (Open to All)',
      'label': '🌟 Semua Level (Open to All)',
      'title': 'Semua Level (Open to All)',
      'subtitle': 'Terbuka untuk semua pemain baru maupun mahir',
      'badge': 'ALL LEVEL',
      'icon': Icons.all_inclusive_rounded,
    },
    {
      'value': 'Beginner Friendly',
      'label': '🟢 Beginner Friendly (Newbie - Pemula)',
      'title': 'Beginner Friendly (Newbie - Pemula)',
      'subtitle': 'Fokus belajar dasar & fun sparring santai',
      'badge': 'PEMULA',
      'icon': Icons.sentiment_satisfied_alt_rounded,
    },
    {
      'value': 'Intermediate',
      'label': '🟡 Intermediate (Menengah)',
      'title': 'Intermediate (Menengah)',
      'subtitle': 'Pemain berpengalaman dengan rally stabil & kompetitif',
      'badge': 'MENENGAH',
      'icon': Icons.sports_tennis_rounded,
    },
    {
      'value': 'Advanced & Competitive',
      'label': '🔴 Advanced & Competitive',
      'title': 'Advanced & Competitive',
      'subtitle': 'Pemain level mahir & kompetisi resmi turnamen',
      'badge': 'KOMPETITIF',
      'icon': Icons.military_tech_rounded,
    },
  ];

  // Status Keanggotaan Options
  static final List<Map<String, dynamic>> _statusOptions = [
    {
      'value': 'Open',
      'label': '🟢 Terbuka (Open to Public)',
      'title': 'Terbuka (Open to Public)',
      'subtitle': 'Pemain publik dapat bergabung secara langsung',
      'badge': 'TERBUKA',
      'icon': Icons.lock_open_rounded,
    },
    {
      'value': 'Closed',
      'label': '🟡 Tertutup Sementara (Closed)',
      'title': 'Tertutup Sementara (Closed)',
      'subtitle': 'Pendaftaran anggota baru ditutup sementara waktu',
      'badge': 'TERTUTUP',
      'icon': Icons.lock_outline_rounded,
    },
    {
      'value': 'Inactive',
      'label': '⚪ Nonaktif (Inactive)',
      'title': 'Nonaktif (Inactive)',
      'subtitle': 'Klub sedang masa rehat / tidak aktif berkegiatan',
      'badge': 'NONAKTIF',
      'icon': Icons.pause_circle_outline_rounded,
    },
  ];

  @override
  void initState() {
    super.initState();
    final c = widget.community;
    _namaController = TextEditingController(text: c.namaCommunity);
    _taglineController = TextEditingController(text: c.tagline ?? '');
    _kotaController = TextEditingController(text: c.kotaHomebase ?? '');
    _jadwalRutinController = TextEditingController(text: c.jadwalRutin ?? '');
    _deskripsiController = TextEditingController(text: c.deskripsi ?? '');
    _selectedHomebaseVenue = (c.homebaseVenue?.trim().isNotEmpty == true) ? c.homebaseVenue!.trim() : null;

    // Normalisasi sport
    final s = c.sport.toLowerCase().trim();
    if (s.contains('tennis') && s.contains('padel')) {
      _selectedSport = 'all_racquet';
    } else if (s == 'all_racquet' || s == 'both' || s == 'all racquet') {
      _selectedSport = 'all_racquet';
    } else if (s == 'tennis') {
      _selectedSport = 'tennis';
    } else {
      _selectedSport = 'padel';
    }

    // Normalisasi target level
    final lvl = c.targetLevel?.trim() ?? '';
    if (lvl.contains('Beginner') || lvl.contains('Pemula')) {
      _selectedTargetLevel = 'Beginner Friendly';
    } else if (lvl.contains('Intermediate') || lvl.contains('Menengah')) {
      _selectedTargetLevel = 'Intermediate';
    } else if (lvl.contains('Advanced') || lvl.contains('Competitive')) {
      _selectedTargetLevel = 'Advanced & Competitive';
    } else {
      _selectedTargetLevel = 'Semua Level (Open to All)';
    }

    // Normalisasi status keanggotaan
    final st = c.statusKeanggotaan.trim();
    if (st.toLowerCase() == 'closed' || st.toLowerCase().contains('tertutup')) {
      _selectedStatusKeanggotaan = 'Closed';
    } else if (st.toLowerCase() == 'inactive' || st.toLowerCase().contains('nonaktif')) {
      _selectedStatusKeanggotaan = 'Inactive';
    } else {
      _selectedStatusKeanggotaan = 'Open';
    }

    _loadVenues();
  }

  Future<void> _loadVenues() async {
    try {
      final list = await _venueService.getVenues();
      if (!mounted) return;
      setState(() {
        _venues = list;
      });
    } catch (_) {}
  }

  @override
  void dispose() {
    _namaController.dispose();
    _taglineController.dispose();
    _kotaController.dispose();
    _jadwalRutinController.dispose();
    _deskripsiController.dispose();
    super.dispose();
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? image = await _picker.pickImage(
        source: source,
        maxWidth: 1000,
        maxHeight: 1000,
        imageQuality: 85,
      );
      if (image != null) {
        final bytes = await image.readAsBytes();
        setState(() {
          _pickedLogo = image;
          _pickedLogoBytes = bytes;
        });
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal memilih foto: $e'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  void _showImagePickerModal() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
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
              const SizedBox(height: 16),
              const Text(
                'Pilih Logo Komunitas',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
              ),
              const SizedBox(height: 4),
              const Text(
                'Ambil foto atau pilih file gambar dari galeri Anda',
                style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
              ),
              const SizedBox(height: 16),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.matchaSoftLime,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.photo_library_rounded, color: AppColors.matchaDark, size: 20),
                ),
                title: const Text('Buka Galeri Foto', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                onTap: () {
                  Navigator.pop(ctx);
                  _pickImage(ImageSource.gallery);
                },
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.matchaSoftLime,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.camera_alt_rounded, color: AppColors.matchaDark, size: 20),
                ),
                title: const Text('Gunakan Kamera', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                onTap: () {
                  Navigator.pop(ctx);
                  _pickImage(ImageSource.camera);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showVenueSearchPicker() async {
    final searchCtrl = TextEditingController();
    List<VenueModel> filtered = List.from(_venues);

    final selected = await showModalBottomSheet<String?>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            final bottomInset = MediaQuery.of(context).viewInsets.bottom;
            return Container(
              height: MediaQuery.of(context).size.height * 0.75,
              padding: EdgeInsets.only(bottom: bottomInset),
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
                        const Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Pilih Homebase Venue',
                              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                            ),
                            Text(
                              'Venue utama tempat komunitas Anda rutin bermain',
                              style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                        IconButton(
                          icon: const Icon(Icons.close_rounded, size: 20, color: Color(0xFF64748B)),
                          onPressed: () => Navigator.pop(ctx),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: TextField(
                      controller: searchCtrl,
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                      onChanged: (q) {
                        setSheetState(() {
                          final clean = q.toLowerCase().trim();
                          if (clean.isEmpty) {
                            filtered = List.from(_venues);
                          } else {
                            filtered = _venues.where((v) {
                              final name = v.namaVenue.toLowerCase();
                              final city = (v.kota ?? '').toLowerCase();
                              return name.contains(clean) || city.contains(clean);
                            }).toList();
                          }
                        });
                      },
                      decoration: InputDecoration(
                        hintText: 'Cari nama venue atau kota...',
                        hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                        prefixIcon: const Icon(Icons.search_rounded, size: 18, color: Color(0xFF94A3B8)),
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Divider(height: 1, color: Color(0xFFF1F5F9)),

                  // Clear venue option
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    child: InkWell(
                      onTap: () => Navigator.pop(ctx, ''),
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF1F5F9),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.clear_rounded, size: 16, color: Color(0xFF64748B)),
                            SizedBox(width: 8),
                            Text(
                              'Kosongkan Homebase Venue',
                              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF475569)),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),

                  Expanded(
                    child: filtered.isEmpty
                        ? const Center(
                            child: Text('Venue tidak ditemukan', style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8))),
                          )
                        : ListView.builder(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                            itemCount: filtered.length,
                            itemBuilder: (context, idx) {
                              final v = filtered[idx];
                              final isSelected = _selectedHomebaseVenue != null &&
                                  _selectedHomebaseVenue!.toLowerCase() == v.namaVenue.toLowerCase();

                              return Padding(
                                padding: const EdgeInsets.only(bottom: 6),
                                child: InkWell(
                                  onTap: () => Navigator.pop(ctx, v.namaVenue),
                                  borderRadius: BorderRadius.circular(12),
                                  child: Container(
                                    padding: const EdgeInsets.all(12),
                                    decoration: BoxDecoration(
                                      color: isSelected ? AppColors.matchaSoftLime.withValues(alpha: 0.6) : const Color(0xFFF8FAFC),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(
                                        color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                                        width: isSelected ? 1.5 : 1,
                                      ),
                                    ),
                                    child: Row(
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.all(8),
                                          decoration: BoxDecoration(
                                            color: isSelected ? AppColors.matchaDark : Colors.white,
                                            borderRadius: BorderRadius.circular(8),
                                          ),
                                          child: Icon(
                                            Icons.location_on_rounded,
                                            size: 16,
                                            color: isSelected ? AppColors.matchaLime : const Color(0xFF64748B),
                                          ),
                                        ),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                v.namaVenue,
                                                style: TextStyle(
                                                  fontSize: 12,
                                                  fontWeight: isSelected ? FontWeight.w800 : FontWeight.w700,
                                                  color: isSelected ? AppColors.matchaDark : const Color(0xFF0F172A),
                                                ),
                                              ),
                                              const SizedBox(height: 2),
                                              Text(
                                                '${v.kota ?? 'Lokasi Terdaftar'} • ${v.courtCount} Court Tersedia',
                                                style: const TextStyle(fontSize: 10.5, color: Color(0xFF64748B)),
                                              ),
                                            ],
                                          ),
                                        ),
                                        if (isSelected)
                                          const Icon(Icons.check_circle_rounded, color: AppColors.matchaDark, size: 18),
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
          },
        );
      },
    );

    if (selected != null) {
      setState(() {
        _selectedHomebaseVenue = selected.isEmpty ? null : selected;
        // Jika kota belum diisi, otomatis isi dari venue terpilih
        if (_selectedHomebaseVenue != null && _kotaController.text.trim().isEmpty) {
          final matched = _venues.where((v) => v.namaVenue == _selectedHomebaseVenue).firstOrNull;
          if (matched != null && matched.kota != null && matched.kota!.isNotEmpty) {
            _kotaController.text = matched.kota!;
          }
        }
      });
    }
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
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          subtitle,
                          style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
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
                      final isSelected = val == selectedValue;
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
    final displayText = (selectedOption['label'] ?? selectedOption['title'] ?? '$value') as String;
    final itemIcon = icon ?? (selectedOption['icon'] as IconData? ?? Icons.tune_rounded);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildFieldLabel(label, isRequired: isRequired),
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
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
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

  Future<void> _handleSave() async {
    if (!_formKey.currentState!.validate()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Harap lengkapi semua data wajib bertanda bintang (*)'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      String? logoUrl = widget.community.logo;
      if (_pickedLogoBytes != null && _pickedLogo != null) {
        final uploaded = await _dataSource.uploadCommunityLogo(_pickedLogoBytes!, _pickedLogo!.name);
        if (uploaded != null && uploaded.isNotEmpty) {
          logoUrl = uploaded;
        }
      }

      await _dataSource.updateCommunity(
        communityId: widget.community.communityId,
        namaCommunity: _namaController.text.trim(),
        tagline: _taglineController.text.trim().isEmpty ? null : _taglineController.text.trim(),
        deskripsi: _deskripsiController.text.trim(),
        sport: _selectedSport,
        kotaHomebase: _kotaController.text.trim().isEmpty ? null : _kotaController.text.trim(),
        targetLevel: _selectedTargetLevel,
        statusKeanggotaan: _selectedStatusKeanggotaan,
        homebaseVenue: _selectedHomebaseVenue,
        jadwalRutin: _jadwalRutinController.text.trim().isEmpty ? null : _jadwalRutinController.text.trim(),
        logo: logoUrl,
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Profil komunitas berhasil diperbarui! 🎉'),
          backgroundColor: AppColors.matchaDark,
          behavior: SnackBarBehavior.floating,
        ),
      );

      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal memperbarui komunitas: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final communityName = widget.community.namaCommunity;

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
          'Edit Profil Komunitas',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
        ),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Breadcrumb & Page Subtitle (Mirrors Web)
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: AppColors.matchaSoftLime,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Text(
                      'COMMUNITY MANAGER',
                      style: TextStyle(
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                        color: AppColors.matchaDark,
                        letterSpacing: 0.4,
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  const Icon(Icons.chevron_right_rounded, size: 14, color: Color(0xFF94A3B8)),
                  const SizedBox(width: 2),
                  const Text(
                    'Edit Data Komunitas',
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
                  ),
                ],
              ),
              const SizedBox(height: 8),

              Text(
                'Edit Profil Komunitas',
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                  letterSpacing: -0.3,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Perbarui informasi dan identitas resmi klub / komunitas $communityName',
                style: const TextStyle(
                  fontSize: 12,
                  color: Color(0xFF64748B),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 16),

              // Main Glassmorphism Form Card
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF063B00).withValues(alpha: 0.04),
                      blurRadius: 16,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Section Header: 1 IDENTITAS & PROFIL KOMUNITAS
                    Row(
                      children: [
                        Container(
                          width: 24,
                          height: 24,
                          decoration: BoxDecoration(
                            color: AppColors.matchaDark,
                            borderRadius: BorderRadius.circular(7),
                          ),
                          child: const Center(
                            child: Text(
                              '1',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        const Expanded(
                          child: Text(
                            'IDENTITAS & PROFIL KOMUNITAS',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF0F172A),
                              letterSpacing: 0.2,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),

                    // 1. Nama Komunitas / Klub
                    _buildFieldLabel('Nama Komunitas / Klub', isRequired: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _namaController,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Nama komunitas wajib diisi.' : null,
                      decoration: _inputDecoration(
                        hint: 'Contoh: Dokja\'s Company',
                        prefixIcon: Icons.groups_rounded,
                      ),
                    ),
                    const SizedBox(height: 14),

                    // 2. Slogan / Tagline Komunitas
                    _buildFieldLabel('Slogan / Tagline Komunitas'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _taglineController,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      decoration: _inputDecoration(
                        hint: 'Contoh: Mabar Seru, Keringat Bareng, Rating Naik!',
                        prefixIcon: Icons.format_quote_rounded,
                      ),
                    ),
                    const SizedBox(height: 14),

                    // 3. Kota Homebase
                    _buildFieldLabel('Kota Homebase', isRequired: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _kotaController,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Kota homebase wajib diisi.' : null,
                      decoration: _inputDecoration(
                        hint: 'Contoh: Bandung, Jakarta Selatan, Seoul',
                        prefixIcon: Icons.location_on_rounded,
                      ),
                    ),
                    const SizedBox(height: 14),

                    // 4. Cabang Olahraga
                    _buildSelectorTile(
                      label: 'Cabang Olahraga',
                      value: _selectedSport,
                      options: _sportOptions,
                      icon: Icons.sports_tennis_rounded,
                      isRequired: true,
                      onTap: () {
                        _showMatchaPickerBottomSheet(
                          title: 'Cabang Olahraga',
                          subtitle: 'Pilih fokus cabang olahraga utama klub Anda',
                          items: _sportOptions,
                          selectedValue: _selectedSport,
                          onSelect: (val) {
                            setState(() => _selectedSport = val);
                          },
                        );
                      },
                    ),
                    const SizedBox(height: 14),

                    // 5. Target Level Pemain
                    _buildSelectorTile(
                      label: 'Target Level Pemain',
                      value: _selectedTargetLevel,
                      options: _targetLevelOptions,
                      icon: Icons.all_inclusive_rounded,
                      onTap: () {
                        _showMatchaPickerBottomSheet(
                          title: 'Target Level Pemain',
                          subtitle: 'Tentukan kualifikasi kemampuan anggota',
                          items: _targetLevelOptions,
                          selectedValue: _selectedTargetLevel,
                          onSelect: (val) {
                            setState(() => _selectedTargetLevel = val);
                          },
                        );
                      },
                    ),
                    const SizedBox(height: 14),

                    // 6. Status Keanggotaan
                    _buildSelectorTile(
                      label: 'Status Keanggotaan',
                      value: _selectedStatusKeanggotaan,
                      options: _statusOptions,
                      icon: Icons.lock_open_rounded,
                      onTap: () {
                        _showMatchaPickerBottomSheet(
                          title: 'Status Keanggotaan',
                          subtitle: 'Ketersediaan slot keanggotaan publik',
                          items: _statusOptions,
                          selectedValue: _selectedStatusKeanggotaan,
                          onSelect: (val) {
                            setState(() => _selectedStatusKeanggotaan = val);
                          },
                        );
                      },
                    ),
                    const SizedBox(height: 14),

                    // 7. Homebase Venue Utama
                    _buildFieldLabel('Homebase Venue Utama'),
                    const SizedBox(height: 6),
                    InkWell(
                      onTap: _showVenueSearchPicker,
                      borderRadius: BorderRadius.circular(12),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: _selectedHomebaseVenue != null
                                ? AppColors.matchaDark.withValues(alpha: 0.4)
                                : const Color(0xFFE2E8F0),
                          ),
                        ),
                        child: Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(7),
                              decoration: BoxDecoration(
                                color: _selectedHomebaseVenue != null ? AppColors.matchaSoftLime : const Color(0xFFE2E8F0),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Icon(
                                Icons.apartment_rounded,
                                color: _selectedHomebaseVenue != null ? AppColors.matchaDark : const Color(0xFF64748B),
                                size: 16,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _selectedHomebaseVenue ?? 'Pilih Homebase Venue Utama (Opsional)...',
                                    style: TextStyle(
                                      fontSize: 12.5,
                                      fontWeight: _selectedHomebaseVenue != null ? FontWeight.w700 : FontWeight.w500,
                                      color: _selectedHomebaseVenue != null ? const Color(0xFF0F172A) : const Color(0xFF94A3B8),
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  if (_selectedHomebaseVenue != null) ...[
                                    const SizedBox(height: 2),
                                    Builder(
                                      builder: (_) {
                                        final matched = _venues.where((v) => v.namaVenue == _selectedHomebaseVenue).firstOrNull;
                                        final sub = matched != null
                                            ? '${matched.kota ?? 'Lokasi Terdaftar'} • ${matched.courtCount} Court Tersedia'
                                            : 'Venue Terverifikasi';
                                        return Text(
                                          sub,
                                          style: const TextStyle(fontSize: 10.5, color: Color(0xFF64748B)),
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
                            const Icon(Icons.search_rounded, size: 18, color: Color(0xFF94A3B8)),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),

                    // 8. Jadwal Mabar Rutin
                    _buildFieldLabel('Jadwal Mabar Rutin'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _jadwalRutinController,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      decoration: _inputDecoration(
                        hint: 'Contoh: Setiap Selasa & Jumat malam pukul 19:00 WIB',
                        prefixIcon: Icons.calendar_today_rounded,
                      ),
                    ),
                    const SizedBox(height: 14),

                    // 9. Deskripsi Lengkap Komunitas
                    _buildFieldLabel('Deskripsi Lengkap Komunitas', isRequired: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _deskripsiController,
                      maxLines: 4,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500, height: 1.4),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Deskripsi komunitas wajib diisi.' : null,
                      decoration: InputDecoration(
                        hintText: 'Ceritakan latar belakang klub, visi misi, suasana mabar, dan tata cara bergabung...',
                        hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        contentPadding: const EdgeInsets.all(14),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // 10. Logo Komunitas
                    _buildFieldLabel('Logo Komunitas'),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        children: [
                          // Thumbnail Preview
                          ClipRRect(
                            borderRadius: BorderRadius.circular(12),
                            child: Container(
                              width: 60,
                              height: 60,
                              color: const Color(0xFFE2E8F0),
                              child: _pickedLogoBytes != null
                                  ? Image.memory(_pickedLogoBytes!, fit: BoxFit.cover)
                                  : (widget.community.logo != null && widget.community.logo!.isNotEmpty)
                                      ? Image.network(
                                          widget.community.logo!,
                                          fit: BoxFit.cover,
                                          errorBuilder: (context, error, stackTrace) => const Icon(Icons.groups_rounded, color: Color(0xFF94A3B8)),
                                        )
                                      : const Icon(Icons.groups_rounded, color: Color(0xFF94A3B8), size: 28),
                            ),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                OutlinedButton.icon(
                                  onPressed: _showImagePickerModal,
                                  icon: const Icon(Icons.camera_alt_outlined, size: 16, color: AppColors.matchaDark),
                                  label: Text(
                                    (_pickedLogoBytes != null || (widget.community.logo != null && widget.community.logo!.isNotEmpty))
                                        ? 'Ganti Logo Komunitas'
                                        : 'Upload Logo Komunitas',
                                    style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                                  ),
                                  style: OutlinedButton.styleFrom(
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                    side: const BorderSide(color: AppColors.matchaDark),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                    backgroundColor: AppColors.matchaSoftLime.withValues(alpha: 0.4),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                const Text(
                                  'Format: JPG, JPEG, PNG (Maksimal 2 MB). Biarkan kosong jika tidak ingin mengubah logo.',
                                  style: TextStyle(fontSize: 10, color: Color(0xFF94A3B8), height: 1.25),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Action Buttons: Batal & Simpan
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
                          : const Icon(Icons.save_rounded, size: 18, color: AppColors.matchaLime),
                      label: Text(
                        _isSaving ? 'Menyimpan...' : 'Simpan Perubahan Komunitas',
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
              const SizedBox(height: 36),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFieldLabel(String label, {bool isRequired = false}) {
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
}
