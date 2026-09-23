import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/theme/app_colors.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../data/community_remote_data_source.dart';

class CreateCommunityPage extends StatefulWidget {
  final AuthController? authController;

  const CreateCommunityPage({super.key, this.authController});

  @override
  State<CreateCommunityPage> createState() => _CreateCommunityPageState();
}

class _CreateCommunityPageState extends State<CreateCommunityPage> {
  final CommunityRemoteDataSource _dataSource = CommunityRemoteDataSource();
  final ImagePicker _picker = ImagePicker();
  final _formKey = GlobalKey<FormState>();

  // Controllers
  final _namaController = TextEditingController();
  final _taglineController = TextEditingController();
  final _kotaController = TextEditingController(text: 'Bandung');
  final _deskripsiController = TextEditingController();
  final _jadwalRutinController = TextEditingController();
  final _homebaseVenueController = TextEditingController();

  // Photo
  XFile? _pickedImage;
  Uint8List? _pickedImageBytes;

  // Selections
  String _selectedSport = 'padel'; // 'padel', 'tennis', 'all_racquet'
  String _selectedTargetLevel = 'All Levels';
  String _selectedStatusKeanggotaan = 'Open';

  // Benefits
  final Set<String> _selectedBenefits = {
    'weekly_mabar',
    'whatsapp_group',
  };

  bool _isSubmitting = false;

  final List<Map<String, dynamic>> _benefitOptions = [
    {'key': 'weekly_mabar', 'name': 'Sesi Mabar Mingguan', 'icon': Icons.calendar_today_rounded},
    {'key': 'internal_tournament', 'name': 'Internal Tournament', 'icon': Icons.emoji_events_rounded},
    {'key': 'coaching_clinic', 'name': 'Coaching Clinic', 'icon': Icons.school_rounded},
    {'key': 'whatsapp_group', 'name': 'WhatsApp Group Aktif', 'icon': Icons.chat_bubble_rounded},
    {'key': 'court_discount', 'name': 'Diskon Sewa Court', 'icon': Icons.local_offer_rounded},
    {'key': 'official_jersey', 'name': 'Jersey Official Club', 'icon': Icons.checkroom_rounded},
    {'key': 'rating_tracking', 'name': 'Tracking Rating Pemain', 'icon': Icons.trending_up_rounded},
    {'key': 'networking', 'name': 'Networking Profesional', 'icon': Icons.handshake_rounded},
  ];

  @override
  void dispose() {
    _namaController.dispose();
    _taglineController.dispose();
    _kotaController.dispose();
    _deskripsiController.dispose();
    _jadwalRutinController.dispose();
    _homebaseVenueController.dispose();
    super.dispose();
  }

  void _resetForm() {
    setState(() {
      _namaController.clear();
      _taglineController.clear();
      _kotaController.text = 'Bandung';
      _deskripsiController.clear();
      _jadwalRutinController.clear();
      _homebaseVenueController.clear();
      _pickedImage = null;
      _pickedImageBytes = null;
      _selectedSport = 'padel';
      _selectedTargetLevel = 'All Levels';
      _selectedStatusKeanggotaan = 'Open';
      _selectedBenefits.clear();
      _selectedBenefits.addAll(['weekly_mabar', 'whatsapp_group']);
    });
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? image = await _picker.pickImage(
        source: source,
        maxWidth: 1200,
        maxHeight: 800,
        imageQuality: 85,
      );
      if (image != null) {
        final bytes = await image.readAsBytes();
        setState(() {
          _pickedImage = image;
          _pickedImageBytes = bytes;
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

  void _showImagePickerSheet() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Pilih Foto Komunitas',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
              ),
              const SizedBox(height: 6),
              const Text(
                'Upload foto horizontal logo atau lapangan klub Anda',
                style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
              ),
              const SizedBox(height: 16),
              ListTile(
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
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.matchaSoftLime,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.camera_alt_rounded, color: AppColors.matchaDark, size: 20),
                ),
                title: const Text('Ambil Foto Kamera', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
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

  Future<void> _submitForm() async {
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

    if (!_formKey.currentState!.validate()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Mohon lengkapi semua kolom bertanda bintang (*) dengan benar.'),
          backgroundColor: Colors.redAccent,
        ),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      String? uploadedLogoUrl;
      if (_pickedImageBytes != null && _pickedImage != null) {
        uploadedLogoUrl = await _dataSource.uploadCommunityLogo(
          _pickedImageBytes!,
          _pickedImage!.name,
        );
      }

      final newCom = await _dataSource.createCommunity(
        namaCommunity: _namaController.text.trim(),
        deskripsi: _deskripsiController.text.trim(),
        sport: _selectedSport,
        tagline: _taglineController.text.trim().isEmpty ? null : _taglineController.text.trim(),
        kotaHomebase: _kotaController.text.trim(),
        targetLevel: _selectedTargetLevel,
        statusKeanggotaan: _selectedStatusKeanggotaan,
        jadwalRutin: _jadwalRutinController.text.trim().isEmpty ? null : _jadwalRutinController.text.trim(),
        homebaseVenue: _homebaseVenueController.text.trim().isEmpty ? null : _homebaseVenueController.text.trim(),
        benefits: _selectedBenefits.toList(),
        logo: uploadedLogoUrl,
        createdBy: widget.authController?.currentUser?.userId,
        creatorName: widget.authController?.currentUser?.nama,
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Komunitas "${newCom.namaCommunity}" berhasil didaftarkan! 🎉'),
          backgroundColor: AppColors.matchaDark,
        ),
      );

      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal mendaftarkan komunitas: $e'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;
    final userName = user?.nama ?? 'Marcello Este Camaro';
    final userInitial = userName.isNotEmpty ? userName[0].toUpperCase() : 'M';

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
          'Daftar Komunitas Baru',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
        ),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Header Hero Card
              _buildHeroHeader(userName, userInitial),

              const SizedBox(height: 20),

              // 2. Main Form Card Container
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF063B00).withValues(alpha: 0.04),
                      blurRadius: 20,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // SECTION 1: Identitas & Profil Komunitas
                    _buildSectionHeader(1, 'Identitas & Profil Komunitas'),
                    const SizedBox(height: 14),

                    _buildFieldLabel('Nama Komunitas / Klub', isRequired: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _namaController,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      decoration: _inputDecoration(
                        hint: 'Contoh: JTK Padel Club Bandung',
                        prefixIcon: Icons.groups_rounded,
                      ),
                      validator: (v) {
                        if (v == null || v.trim().isEmpty) {
                          return 'Nama komunitas wajib diisi.';
                        }
                        return null;
                      },
                    ),

                    const SizedBox(height: 14),

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

                    _buildFieldLabel('Kota Homebase', isRequired: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _kotaController,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      decoration: _inputDecoration(
                        hint: 'Contoh: Bandung, Jakarta, Surabaya',
                        prefixIcon: Icons.location_on_rounded,
                      ),
                      validator: (v) {
                        if (v == null || v.trim().isEmpty) {
                          return 'Kota homebase wajib diisi.';
                        }
                        return null;
                      },
                    ),

                    const SizedBox(height: 28),

                    // SECTION 2: Fokus Olahraga & Tingkat Kemampuan
                    _buildSectionHeader(2, 'Fokus Olahraga & Tingkat Kemampuan'),
                    const SizedBox(height: 14),

                    _buildFieldLabel('Cabang Olahraga Utama', isRequired: true),
                    const SizedBox(height: 10),
                    _buildSportSelector(),

                    const SizedBox(height: 16),

                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildFieldLabel('Target Level Member'),
                              const SizedBox(height: 6),
                              _buildDropdown(
                                value: _selectedTargetLevel,
                                items: const [
                                  DropdownMenuItem(value: 'All Levels', child: Text('Semua Level')),
                                  DropdownMenuItem(value: 'Beginners', child: Text('Newbie / Beginner')),
                                  DropdownMenuItem(value: 'Intermediate', child: Text('Intermediate+')),
                                ],
                                onChanged: (v) {
                                  if (v != null) setState(() => _selectedTargetLevel = v);
                                },
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildFieldLabel('Status Keanggotaan'),
                              const SizedBox(height: 6),
                              _buildDropdown(
                                value: _selectedStatusKeanggotaan,
                                items: const [
                                  DropdownMenuItem(value: 'Open', child: Text('Terbuka (Free)')),
                                  DropdownMenuItem(value: 'Approval', child: Text('Approval')),
                                  DropdownMenuItem(value: 'Private', child: Text('Private')),
                                ],
                                onChanged: (v) {
                                  if (v != null) setState(() => _selectedStatusKeanggotaan = v);
                                },
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 28),

                    // SECTION 3: Deskripsi & Jadwal Rutin Mabar
                    _buildSectionHeader(3, 'Deskripsi & Jadwal Rutin Mabar'),
                    const SizedBox(height: 14),

                    _buildFieldLabel('Deskripsi Lengkap Komunitas', isRequired: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _deskripsiController,
                      maxLines: 4,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
                      decoration: _inputDecoration(
                        hint: 'Jelaskan mengenai komunitas Anda, visi bermain, suasana mabar, aturan fair play, dan fasilitas yang biasa dinikmati...',
                      ),
                      validator: (v) {
                        if (v == null || v.trim().isEmpty) {
                          return 'Deskripsi lengkap komunitas wajib diisi.';
                        }
                        return null;
                      },
                    ),

                    const SizedBox(height: 14),

                    _buildFieldLabel('Jadwal Mabar Rutin'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _jadwalRutinController,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      decoration: _inputDecoration(
                        hint: 'Contoh: Tiap Rabu Malam & Sabtu Pagi',
                        prefixIcon: Icons.calendar_month_rounded,
                      ),
                    ),

                    const SizedBox(height: 14),

                    _buildFieldLabel('Homebase Venue Utama'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _homebaseVenueController,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                      decoration: _inputDecoration(
                        hint: 'Contoh: Gelora Racquet Arena',
                        prefixIcon: Icons.place_rounded,
                      ),
                    ),

                    const SizedBox(height: 28),

                    // SECTION 4: Benefit & Kegiatan Komunitas
                    _buildSectionHeader(4, 'Benefit & Kegiatan Komunitas'),
                    const SizedBox(height: 12),
                    _buildBenefitsGrid(),

                    const SizedBox(height: 28),

                    // SECTION 5: Admin Komunitas & Logo Badge
                    _buildSectionHeader(5, 'Admin Komunitas & Logo Badge'),
                    const SizedBox(height: 14),
                    _buildAdminAndLogoSection(userName, userInitial),

                    const SizedBox(height: 28),

                    // Footer confirmation text
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.matchaSoftLime.withValues(alpha: 0.5),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.15)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.check_circle_rounded, size: 18, color: AppColors.matchaDark),
                          const SizedBox(width: 8),
                          const Expanded(
                            child: Text(
                              'Komunitas akan langsung aktif dan dapat mulai membuka sesi mabar.',
                              style: TextStyle(fontSize: 11, color: AppColors.matchaDark, fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 20),

                    // Action buttons
                    Row(
                      children: [
                        Expanded(
                          flex: 2,
                          child: OutlinedButton(
                            onPressed: _isSubmitting ? null : _resetForm,
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFF475569),
                              side: const BorderSide(color: Color(0xFFCBD5E1)),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                              padding: const EdgeInsets.symmetric(vertical: 14),
                            ),
                            child: const Text('Reset Form', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          flex: 4,
                          child: ElevatedButton.icon(
                            onPressed: _isSubmitting ? null : _submitForm,
                            icon: _isSubmitting
                                ? const SizedBox(
                                    width: 16,
                                    height: 16,
                                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                  )
                                : const Icon(Icons.add_rounded, size: 18, color: AppColors.matchaSoftLime),
                            label: Text(
                              _isSubmitting ? 'Mendaftarkan...' : 'Daftarkan Komunitas',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.matchaDark,
                              foregroundColor: Colors.white,
                              elevation: 0,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                              padding: const EdgeInsets.symmetric(vertical: 14),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 40),
            ],
          ),
        ),
      ),
    );
  }

  // --- WIDGET HELPERS ---

  Widget _buildHeroHeader(String userName, String userInitial) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppColors.matchaSoftLime.withValues(alpha: 0.7),
            Colors.white,
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.15)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: AppColors.matchaSoftLime,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                ),
                child: const Text(
                  'COMMUNITY CREATOR HUB',
                  style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: AppColors.matchaDark, letterSpacing: 0.5),
                ),
              ),
              const SizedBox(width: 8),
              const Text('•', style: TextStyle(color: Color(0xFF94A3B8))),
              const SizedBox(width: 8),
              const Text('Mulai Klub Baru', style: TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600)),
            ],
          ),
          const SizedBox(height: 10),
          const Text(
            'Pendaftaran Komunitas Baru',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), letterSpacing: -0.5),
          ),
          const SizedBox(height: 6),
          const Text(
            'Buat wadah mabar dan turnamen untuk para pecinta Tennis dan Padel di kota Anda. Anda akan otomatis menjadi Admin Komunitas.',
            style: TextStyle(fontSize: 12, color: Color(0xFF475569), height: 1.4),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 14,
                  backgroundColor: AppColors.matchaDark,
                  child: Text(userInitial, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('PERAN ANDA', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                      Text(
                        '$userName (Club Founder & Admin)',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: AppColors.matchaDark),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(int number, String title) {
    return Row(
      children: [
        Container(
          width: 24,
          height: 24,
          decoration: BoxDecoration(
            color: AppColors.matchaDark,
            borderRadius: BorderRadius.circular(8),
          ),
          alignment: Alignment.center,
          child: Text(
            '$number',
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Colors.white),
          ),
        ),
        const SizedBox(width: 10),
        Text(
          title.toUpperCase(),
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), letterSpacing: 0.3),
        ),
      ],
    );
  }

  Widget _buildFieldLabel(String text, {bool isRequired = false}) {
    return RichText(
      text: TextSpan(
        text: text,
        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF334155)),
        children: [
          if (isRequired)
            const TextSpan(
              text: ' *',
              style: TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold),
            ),
        ],
      ),
    );
  }

  InputDecoration _inputDecoration({required String hint, IconData? prefixIcon}) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8), fontWeight: FontWeight.normal),
      prefixIcon: prefixIcon != null ? Icon(prefixIcon, size: 18, color: const Color(0xFF94A3B8)) : null,
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
      errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: Colors.redAccent)),
      focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: Colors.redAccent, width: 1.5)),
    );
  }

  Widget _buildDropdown({
    required String value,
    required List<DropdownMenuItem<String>> items,
    required ValueChanged<String?> onChanged,
  }) {
    return DropdownButtonFormField<String>(
      initialValue: value,
      items: items,
      onChanged: onChanged,
      icon: const Icon(Icons.keyboard_arrow_down_rounded, size: 18, color: Color(0xFF64748B)),
      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
      decoration: InputDecoration(
        filled: true,
        fillColor: const Color(0xFFF8FAFC),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
      ),
    );
  }

  Widget _buildSportSelector() {
    final sports = [
      {
        'key': 'padel',
        'title': 'Padel Community',
        'subtitle': 'Khusus Pecinta Padel',
        'icon': Icons.sports_tennis_rounded,
      },
      {
        'key': 'tennis',
        'title': 'Tennis Community',
        'subtitle': 'Khusus Lapangan Tenis',
        'icon': Icons.sports_baseball_rounded,
      },
      {
        'key': 'all_racquet',
        'title': 'All Racquet Club',
        'subtitle': 'Padel & Tennis Campuran',
        'icon': Icons.layers_rounded,
      },
    ];

    return Column(
      children: sports.map((s) {
        final isSelected = _selectedSport == s['key'];
        return GestureDetector(
          onTap: () => setState(() => _selectedSport = s['key'] as String),
          child: Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: isSelected ? AppColors.matchaSoftLime.withValues(alpha: 0.6) : const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                width: isSelected ? 1.5 : 1,
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: isSelected ? Colors.white : const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: isSelected ? AppColors.matchaDark.withValues(alpha: 0.2) : const Color(0xFFE2E8F0)),
                  ),
                  child: Icon(
                    s['icon'] as IconData,
                    size: 20,
                    color: isSelected ? AppColors.matchaDark : const Color(0xFF64748B),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        s['title'] as String,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: isSelected ? AppColors.matchaDark : const Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        s['subtitle'] as String,
                        style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                      ),
                    ],
                  ),
                ),
                Icon(
                  isSelected ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                  size: 20,
                  color: isSelected ? AppColors.matchaDark : const Color(0xFFCBD5E1),
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildBenefitsGrid() {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: _benefitOptions.map((b) {
        final key = b['key'] as String;
        final name = b['name'] as String;
        final icon = b['icon'] as IconData;
        final isSelected = _selectedBenefits.contains(key);

        return GestureDetector(
          onTap: () {
            setState(() {
              if (isSelected) {
                _selectedBenefits.remove(key);
              } else {
                _selectedBenefits.add(key);
              }
            });
          },
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 150),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: isSelected ? AppColors.matchaSoftLime.withValues(alpha: 0.8) : const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                width: isSelected ? 1.2 : 1,
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
                    fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                    color: isSelected ? AppColors.matchaDark : const Color(0xFF334155),
                  ),
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildAdminAndLogoSection(String userName, String userInitial) {
    return Column(
      children: [
        // Admin Card
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Row(
            children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: AppColors.matchaDark,
                child: Text(
                  userInitial,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: Colors.white),
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
                            userName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                          ),
                        ),
                        const SizedBox(width: 4),
                        const Icon(Icons.check_circle_rounded, size: 14, color: AppColors.matchaDark),
                      ],
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Admin Utama (Pendaftar Akun Ini)',
                      style: TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                    ),
                    const SizedBox(height: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppColors.matchaSoftLime,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Text(
                        'Verified Founder',
                        style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: AppColors.matchaDark),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 14),

        // Photo Upload Dropzone Box matching Web
        _buildFieldLabel('Logo / Foto Komunitas (Opsional)'),
        const SizedBox(height: 6),
        GestureDetector(
          onTap: _showImagePickerSheet,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: _pickedImageBytes != null ? AppColors.matchaDark : const Color(0xFFCBD5E1),
                width: 1.5,
              ),
            ),
            child: _pickedImageBytes != null
                ? Column(
                    children: [
                      Stack(
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(14),
                            child: Image.memory(
                              _pickedImageBytes!,
                              height: 140,
                              width: double.infinity,
                              fit: BoxFit.cover,
                            ),
                          ),
                          Positioned(
                            top: 8,
                            right: 8,
                            child: GestureDetector(
                              onTap: () {
                                setState(() {
                                  _pickedImage = null;
                                  _pickedImageBytes = null;
                                });
                              },
                              child: Container(
                                padding: const EdgeInsets.all(6),
                                decoration: const BoxDecoration(
                                  color: Colors.white,
                                  shape: BoxShape.circle,
                                  boxShadow: [
                                    BoxShadow(color: Colors.black26, blurRadius: 4),
                                  ],
                                ),
                                child: const Icon(Icons.close_rounded, size: 16, color: Colors.redAccent),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.check_circle_rounded, size: 14, color: AppColors.matchaDark),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              _pickedImage?.name ?? 'Foto Terpilih',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                            ),
                          ),
                          const SizedBox(width: 4),
                          const Text('• Ketuk untuk ganti', style: TextStyle(fontSize: 10, color: Color(0xFF64748B))),
                        ],
                      ),
                    ],
                  )
                : Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: const Icon(
                          Icons.add_photo_alternate_rounded,
                          size: 22,
                          color: AppColors.matchaDark,
                        ),
                      ),
                      const SizedBox(height: 10),
                      const Text(
                        'Upload Foto Komunitas',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                      ),
                      const SizedBox(height: 2),
                      const Text(
                        'Rasio horizontal format JPG / PNG',
                        style: TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                      ),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppColors.matchaSoftLime,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.touch_app_rounded, size: 12, color: AppColors.matchaDark),
                            SizedBox(width: 4),
                            Text(
                              'Pilih dari Galeri / Kamera',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      ],
    );
  }
}
