import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/domain/models/user_model.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../../auth/presentation/register_page.dart';
import '../../recap/presentation/match_recap_page.dart';

class ProfilePage extends StatefulWidget {
  final AuthController? authController;

  const ProfilePage({super.key, this.authController});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> with SingleTickerProviderStateMixin {
  late AnimationController _animController;
  late Animation<double> _pulseAnimation;

  final _formKey = GlobalKey<FormState>();
  late TextEditingController _namaController;
  late TextEditingController _emailController;
  late TextEditingController _noHpController;
  late TextEditingController _usiaController;

  String _selectedGender = 'Male';
  String _selectedLevel = 'Intermediate';
  int? _selectedCommunityId;

  String? _currentFotoUrl;
  Uint8List? _pickedImageBytes;
  String? _pickedImageExt;
  bool _removeFoto = false;

  bool _isSaving = false;
  bool _isTogglingHost = false;
  List<Map<String, dynamic>> _communities = [];

  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _animController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 2),
    )..repeat(reverse: true);

    _pulseAnimation = Tween<double>(begin: 0.95, end: 1.05).animate(
      CurvedAnimation(parent: _animController, curve: Curves.easeInOut),
    );

    _initFormValues();
    _loadCommunities();
    widget.authController?.addListener(_onAuthChanged);
  }

  void _initFormValues() {
    final user = widget.authController?.currentUser;
    _namaController = TextEditingController(text: user?.nama ?? '');
    _emailController = TextEditingController(text: user?.email ?? '');
    _noHpController = TextEditingController(text: user?.noHp ?? '');
    _usiaController = TextEditingController(text: user?.usia != null ? user!.usia.toString() : '25');

    final rawGender = (user?.gender ?? 'Male').toLowerCase();
    _selectedGender = rawGender.contains('fem') || rawGender.contains('peremp') ? 'Female' : 'Male';

    final rawLevel = user?.level ?? 'Intermediate';
    if (['Newbie', 'Beginner', 'Intermediate', 'Advanced'].contains(rawLevel)) {
      _selectedLevel = rawLevel;
    } else {
      _selectedLevel = 'Intermediate';
    }

    _selectedCommunityId = user?.communityId;
    _currentFotoUrl = user?.foto;
    _removeFoto = false;
    _pickedImageBytes = null;
  }

  Future<void> _loadCommunities() async {
    final authCtrl = widget.authController;
    if (authCtrl != null) {
      final list = await authCtrl.getCommunities();
      if (mounted) {
        setState(() {
          _communities = list;
        });
      }
    }
  }

  @override
  void dispose() {
    _animController.dispose();
    _namaController.dispose();
    _emailController.dispose();
    _noHpController.dispose();
    _usiaController.dispose();
    widget.authController?.removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
    if (mounted && !_isSaving) {
      setState(() {});
    }
  }

  Future<void> _handleToggleHost() async {
    final authCtrl = widget.authController;
    if (authCtrl == null || authCtrl.currentUser == null) return;

    final isCurrentlyHost = authCtrl.currentUser!.isHost;
    final actionText = isCurrentlyHost ? 'Nonaktifkan Mode Host' : 'Aktifkan Mode Host';
    final confirmMsg = isCurrentlyHost
        ? 'Apakah kamu yakin ingin menonaktifkan status Host? Kamu tidak akan bisa membuat jadwal mabar baru sampai diaktifkan kembali.'
        : 'Aktifkan Mode Host untuk mulai membuat jadwal mabar, mengatur drawing tim, dan input live score!';

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: Text(actionText, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17)),
        content: Text(confirmMsg, style: const TextStyle(fontSize: 13.5, color: Color(0xFF475569))),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal', style: TextStyle(color: Color(0xFF64748B), fontWeight: FontWeight.w600)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: isCurrentlyHost ? const Color(0xFFDC2626) : const Color(0xFF063B00),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: Text(isCurrentlyHost ? 'Nonaktifkan' : 'Aktifkan'),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      setState(() => _isTogglingHost = true);
      try {
        await authCtrl.toggleHost();
        if (mounted) {
          final isNowHost = authCtrl.currentUser?.isHost ?? false;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Row(
                children: [
                  Icon(
                    isNowHost ? Icons.check_circle_rounded : Icons.info_outline_rounded,
                    color: Colors.white,
                    size: 20,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      isNowHost
                          ? '👑 Mode Host Game Aktif! Kamu sekarang bisa membuat jadwal mabar.'
                          : 'Status Mode Host dinonaktifkan (Pemain Biasa).',
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
              backgroundColor: isNowHost ? const Color(0xFF065F46) : const Color(0xFF334155),
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
          );
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Gagal mengubah status host: $e'),
              backgroundColor: Colors.redAccent,
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      } finally {
        if (mounted) setState(() => _isTogglingHost = false);
      }
    }
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? file = await _picker.pickImage(
        source: source,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 85,
      );
      if (file != null) {
        final bytes = await file.readAsBytes();
        final ext = file.name.split('.').last.toLowerCase();
        setState(() {
          _pickedImageBytes = bytes;
          _pickedImageExt = (ext == 'png' || ext == 'webp') ? ext : 'jpg';
          _removeFoto = false;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Gagal mengambil gambar: $e'),
            backgroundColor: Colors.redAccent,
          ),
        );
      }
    }
  }

  void _showImageSourceDialog() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Pilih Foto Profil',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
              ),
              const SizedBox(height: 14),
              ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFECFDF5),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.photo_library_rounded, color: Color(0xFF065F46)),
                ),
                title: const Text('Buka Galeri Foto', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                onTap: () {
                  Navigator.pop(ctx);
                  _pickImage(ImageSource.gallery);
                },
              ),
              ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.camera_alt_rounded, color: Color(0xFF1D4ED8)),
                ),
                title: const Text('Ambil Foto Kamera', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                onTap: () {
                  Navigator.pop(ctx);
                  _pickImage(ImageSource.camera);
                },
              ),
              ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.link_rounded, color: Color(0xFF475569)),
                ),
                title: const Text('Input URL Foto Online', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                onTap: () {
                  Navigator.pop(ctx);
                  _showUrlInputDialog();
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showUrlInputDialog() {
    final urlCtrl = TextEditingController(text: _currentFotoUrl ?? '');
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('URL Foto Profil', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        content: TextField(
          controller: urlCtrl,
          decoration: InputDecoration(
            hintText: 'https://...',
            prefixIcon: const Icon(Icons.image_outlined, size: 20),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            onPressed: () {
              final val = urlCtrl.text.trim();
              if (val.isNotEmpty) {
                setState(() {
                  _currentFotoUrl = val;
                  _pickedImageBytes = null;
                  _removeFoto = false;
                });
              }
              Navigator.pop(ctx);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF063B00),
              foregroundColor: Colors.white,
            ),
            child: const Text('Gunakan'),
          ),
        ],
      ),
    );
  }

  void _handleRemoveFoto() {
    setState(() {
      _currentFotoUrl = null;
      _pickedImageBytes = null;
      _removeFoto = true;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text('Foto profil diset untuk dihapus saat disimpan.'),
        duration: const Duration(seconds: 2),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  Future<void> _handleSaveProfile() async {
    if (!_formKey.currentState!.validate()) return;

    final authCtrl = widget.authController;
    if (authCtrl == null || authCtrl.currentUser == null) return;

    setState(() => _isSaving = true);

    try {
      String? finalFotoUrl = _currentFotoUrl;

      // Jika ada gambar baru yang dipilih dari galeri/kamera, upload ke Supabase Storage
      if (_pickedImageBytes != null && !_removeFoto) {
        final uploadedUrl = await authCtrl.uploadAvatar(
          _pickedImageBytes!,
          _pickedImageExt ?? 'jpg',
        );
        if (uploadedUrl != null) {
          finalFotoUrl = uploadedUrl;
        }
      }

      final cleanPhone = _noHpController.text.replaceAll(RegExp(r'[^0-9]'), '');
      final usiaVal = int.tryParse(_usiaController.text.trim()) ?? 25;

      final success = await authCtrl.updateProfile(
        nama: _namaController.text.trim(),
        noHp: cleanPhone,
        gender: _selectedGender,
        usia: usiaVal,
        level: _selectedLevel,
        communityId: _selectedCommunityId,
        fotoUrl: finalFotoUrl,
        removeFoto: _removeFoto,
      );

      if (success && mounted) {
        _pickedImageBytes = null;
        _currentFotoUrl = authCtrl.currentUser?.foto;
        _removeFoto = false;

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Row(
              children: [
                Icon(Icons.check_circle_rounded, color: Colors.white, size: 20),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    '🎉 Profil pemain berhasil diperbarui!',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5),
                  ),
                ),
              ],
            ),
            backgroundColor: const Color(0xFF065F46),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
      } else if (mounted && authCtrl.errorMessage != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(authCtrl.errorMessage!),
            backgroundColor: Colors.redAccent,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Gagal menyimpan profil: $e'),
            backgroundColor: Colors.redAccent,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  Future<void> _handleLogout() async {
    final shouldLogout = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Keluar dari Akun?'),
        content: const Text('Kamu perlu masuk kembali untuk mengakses fitur mabar dan kelola skor.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal', style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.redAccent,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );

    if (shouldLogout == true && mounted) {
      final authCtrl = widget.authController ?? AuthController();
      await authCtrl.logout();

      if (mounted) {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(
            builder: (ctx) => LoginPage(authController: authCtrl),
          ),
          (route) => false,
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;

    if (user == null) {
      return _buildGuestProfileView();
    }

    return _buildLoggedInProfileView(user);
  }

  /// Tampilan Profil untuk Pengguna yang Sudah Login (1:1 Web Mirror)
  Widget _buildLoggedInProfileView(UserModel user) {
    final isHost = user.isHost;
    final isVenueOwner = user.role == 'venue_owner';
    final currentCommunityName = _getCommunityName(_selectedCommunityId);
    final usernameTag = '@${user.nama.toLowerCase().replaceAll(RegExp(r'[^a-z0-9_]'), '_')}';

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () => Navigator.maybePop(context),
        ),
        title: const Text(
          'Profil Member Pemain',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            color: Color(0xFF0F172A),
          ),
        ),
        actions: [
          // Quick Button: Lihat Match Recap (Matching web)
          Container(
            margin: const EdgeInsets.only(right: 12),
            child: InkWell(
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => MatchRecapPage(authController: widget.authController),
                  ),
                );
              },
              borderRadius: BorderRadius.circular(12),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.show_chart_rounded, size: 15, color: Color(0xFF063B00)),
                    SizedBox(width: 4),
                    Text(
                      'Match Recap',
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF063B00),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(color: const Color(0xFFE2E8F0), height: 1),
        ),
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Tag & Header Judul (Matching web)
              _buildPageHeader(),
              const SizedBox(height: 16),

              // 2. Status Akses Host Game Card (Matching web)
              _buildHostStatusCard(isHost),
              const SizedBox(height: 16),

              // 3. Profil & Form Data Pemain Card (Matching web)
              _buildProfileFormCard(user, isHost, isVenueOwner, usernameTag, currentCommunityName),
              const SizedBox(height: 20),

              // 4. Action Save Button
              _buildSaveButton(),
              const SizedBox(height: 16),

              // 5. Logout Button
              _buildLogoutButton(),
              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
    );
  }

  String _getCommunityName(int? id) {
    if (id == null) return 'Personal';
    final found = _communities.firstWhere(
      (c) => c['community_id'] == id,
      orElse: () => {'nama_community': 'Personal'},
    );
    return found['nama_community']?.toString() ?? 'Personal';
  }

  Widget _buildPageHeader() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            color: const Color(0xFFEAF8E6),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFBBF7D0)),
          ),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('🪪 ', style: TextStyle(fontSize: 11)),
              Text(
                'AKUN & PROFIL PEMAIN',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF166534),
                  letterSpacing: 0.5,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Profil Member Pemain',
          style: TextStyle(
            fontSize: 21,
            fontWeight: FontWeight.w900,
            color: Color(0xFF0F172A),
            letterSpacing: -0.3,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Kelola data identitas, kontak WhatsApp, skill level, dan status keanggotaan Host Anda',
          style: TextStyle(
            fontSize: 12.5,
            color: Color(0xFF64748B),
            height: 1.4,
          ),
        ),
      ],
    );
  }

  Widget _buildHostStatusCard(bool isHost) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Expanded(
                child: Wrap(
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 6,
                  runSpacing: 4,
                  children: [
                    const Text(
                      'Status Akses Host Game:',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: isHost ? const Color(0xFFDCFCE7) : const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: isHost ? const Color(0xFF86EFAC) : const Color(0xFFCBD5E1),
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 6,
                            height: 6,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: isHost ? const Color(0xFF16A34A) : const Color(0xFF94A3B8),
                            ),
                          ),
                          const SizedBox(width: 5),
                          Text(
                            isHost ? 'Host Game Active' : 'Mode Member Biasa',
                            style: TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w800,
                              color: isHost ? const Color(0xFF15803D) : const Color(0xFF475569),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            isHost
                ? 'Mode Host Aktif. Kamu diizinkan untuk membuat jadwal mabar baru, mengelola drawing tim, dan live scoring.'
                : 'Mode Member Biasa. Aktifkan Mode Host untuk mulai membuat jadwal mabar baru, mengelola drawing tim, dan live scoring.',
            style: const TextStyle(
              fontSize: 12,
              color: Color(0xFF64748B),
              height: 1.4,
            ),
          ),
          const SizedBox(height: 14),
          // Toggle Host Button (Matching web)
          SizedBox(
            width: double.infinity,
            height: 38,
            child: OutlinedButton(
              onPressed: _isTogglingHost ? null : _handleToggleHost,
              style: OutlinedButton.styleFrom(
                foregroundColor: isHost ? const Color(0xFFDC2626) : const Color(0xFF063B00),
                backgroundColor: isHost ? const Color(0xFFFEF2F2) : const Color(0xFFECFDF5),
                side: BorderSide(
                  color: isHost ? const Color(0xFFFECACA) : const Color(0xFFA7F3D0),
                ),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: _isTogglingHost
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.power_settings_new_rounded,
                          size: 16,
                          color: isHost ? const Color(0xFFDC2626) : const Color(0xFF065F46),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          isHost ? 'Nonaktifkan Mode Host' : 'Aktifkan Mode Host',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.bold,
                            color: isHost ? const Color(0xFFDC2626) : const Color(0xFF065F46),
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProfileFormCard(
    UserModel user,
    bool isHost,
    bool isVenueOwner,
    String usernameTag,
    String currentCommunityName,
  ) {
    ImageProvider? displayImage;
    if (_pickedImageBytes != null) {
      displayImage = MemoryImage(_pickedImageBytes!);
    } else if (!_removeFoto && _currentFotoUrl != null && _currentFotoUrl!.isNotEmpty) {
      displayImage = NetworkImage(_currentFotoUrl!);
    }

    // Bangun daftar opsi Komunitas secara aman
    final communityItems = <DropdownMenuItem<int?>>[
      const DropdownMenuItem<int?>(
        value: null,
        child: Text('Personal (Non-Community / Belum Ada)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
      ),
    ];

    // Jika community_id user belum ada di list (karena masih loading atau khusus), tambahkan secara aman
    if (_selectedCommunityId != null && !_communities.any((c) => c['community_id'] == _selectedCommunityId)) {
      communityItems.add(
        DropdownMenuItem<int?>(
          value: _selectedCommunityId,
          child: Text(
            currentCommunityName != 'Personal' ? currentCommunityName : 'Komunitas (ID: $_selectedCommunityId)',
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      );
    }

    for (final c in _communities) {
      final cId = c['community_id'] as int?;
      if (cId != null && !communityItems.any((item) => item.value == cId)) {
        communityItems.add(
          DropdownMenuItem<int?>(
            value: cId,
            child: Text(
              c['nama_community']?.toString() ?? 'Komunitas',
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        );
      }
    }

    // Validasi value agar pasti ada di dalam items
    final safeCommunityValue = communityItems.any((i) => i.value == _selectedCommunityId)
        ? _selectedCommunityId
        : null;

    final safeGenderValue = ['Male', 'Female'].contains(_selectedGender) ? _selectedGender : 'Male';
    final safeLevelValue = ['Newbie', 'Beginner', 'Intermediate', 'Advanced'].contains(_selectedLevel)
        ? _selectedLevel
        : 'Intermediate';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Avatar + Summary Header
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Avatar with camera badge
              Stack(
                children: [
                  Container(
                    width: 76,
                    height: 76,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFF063B00),
                      border: Border.all(color: const Color(0xFFBEF264), width: 2.5),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: displayImage != null
                        ? Image(
                            image: displayImage,
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) => _buildAvatarFallback(user.nama),
                          )
                        : _buildAvatarFallback(user.nama),
                  ),
                  Positioned(
                    bottom: 0,
                    right: 0,
                    child: GestureDetector(
                      onTap: _showImageSourceDialog,
                      child: Container(
                        padding: const EdgeInsets.all(5),
                        decoration: BoxDecoration(
                          color: const Color(0xFF063B00),
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 2),
                        ),
                        child: const Icon(Icons.camera_alt_rounded, size: 13, color: Color(0xFFA8E63A)),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(width: 14),

              // Name, Role Badge, Username
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      crossAxisAlignment: WrapCrossAlignment.center,
                      spacing: 6,
                      runSpacing: 4,
                      children: [
                        Text(
                          _namaController.text.isNotEmpty ? _namaController.text : user.nama,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                          decoration: BoxDecoration(
                            color: isHost
                                ? const Color(0xFFFEF3C7)
                                : (isVenueOwner ? const Color(0xFFE0F2FE) : const Color(0xFFF1F5F9)),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            isVenueOwner
                                ? '🏢 Venue Owner'
                                : (isHost ? '👑 Host Game & Player' : '👤 Member Pemain'),
                            style: TextStyle(
                              fontSize: 9.5,
                              fontWeight: FontWeight.w800,
                              color: isHost
                                  ? const Color(0xFFB45309)
                                  : (isVenueOwner ? const Color(0xFF0369A1) : const Color(0xFF475569)),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '$usernameTag • ${user.email}',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF64748B),
                        fontWeight: FontWeight.w500,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 10),

                    // Avatar action buttons (Pilih Foto & Hapus Foto)
                    Row(
                      children: [
                        InkWell(
                          onTap: _showImageSourceDialog,
                          borderRadius: BorderRadius.circular(8),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFFCBD5E1)),
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.cloud_upload_outlined, size: 13, color: Color(0xFF334155)),
                                SizedBox(width: 4),
                                Text(
                                  'Pilih Foto',
                                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF334155)),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        if (displayImage != null)
                          InkWell(
                            onTap: _handleRemoveFoto,
                            borderRadius: BorderRadius.circular(8),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFEF2F2),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: const Color(0xFFFECACA)),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.delete_outline_rounded, size: 13, color: Color(0xFFDC2626)),
                                  SizedBox(width: 4),
                                  Text(
                                    'Hapus Foto',
                                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFFDC2626)),
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
            ],
          ),

          const SizedBox(height: 14),

          // 3 Info Chips (Skill, Komunitas, Usia)
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              _buildInfoPill('⭐ Skill: $_selectedLevel', const Color(0xFFFEF3C7), const Color(0xFFB45309)),
              _buildInfoPill('👥 Komunitas: $currentCommunityName', const Color(0xFFF0FDF4), const Color(0xFF15803D)),
              _buildInfoPill('🎂 Usia: ${_usiaController.text.trim()} thn', const Color(0xFFF8FAFC), const Color(0xFF475569)),
            ],
          ),

          const SizedBox(height: 18),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 18),

          // Form Field 1: Nama Lengkap *
          _buildFormFieldTitle('Nama Lengkap', isRequired: true),
          const SizedBox(height: 6),
          TextFormField(
            controller: _namaController,
            onChanged: (_) => setState(() {}),
            style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
            decoration: _inputDecoration(hint: 'Masukkan nama lengkap'),
            validator: (val) => (val == null || val.trim().isEmpty) ? 'Nama lengkap wajib diisi' : null,
          ),
          _buildFieldCaption('Nama ini akan digunakan pada papan drawing pertandingan, bracket turnamen, dan leaderboard.'),
          const SizedBox(height: 14),

          // Form Field 2: Alamat Email (Akun Utama) - Read Only
          _buildFormFieldTitle('Alamat Email (Akun Utama)'),
          const SizedBox(height: 6),
          TextFormField(
            controller: _emailController,
            readOnly: true,
            style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
            decoration: _inputDecoration(
              hint: 'email@matcha.id',
              fillColor: const Color(0xFFF8FAFC),
              suffixIcon: const Icon(Icons.lock_outline_rounded, size: 16, color: Color(0xFF94A3B8)),
            ),
          ),
          _buildFieldCaption('Email akun terhubung dan digunakan untuk masuk ke sistem.'),
          const SizedBox(height: 14),

          // Form Field 3: Nomor WhatsApp / HP *
          _buildFormFieldTitle('Nomor WhatsApp / HP', isRequired: true),
          const SizedBox(height: 6),
          TextFormField(
            controller: _noHpController,
            keyboardType: TextInputType.phone,
            style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
            decoration: _inputDecoration(hint: '08xxxxxxxxxx', prefixText: ''),
            validator: (val) {
              if (val == null || val.trim().isEmpty) return 'Nomor WhatsApp wajib diisi';
              final clean = val.replaceAll(RegExp(r'[^0-9]'), '');
              if (clean.length < 9 || clean.length > 15) return 'Nomor WhatsApp harus 9 - 15 digit angka';
              return null;
            },
          ),
          _buildFieldCaption('Nomor kontak untuk koordinasi grup mabar & notifikasi sesi.'),
          const SizedBox(height: 14),

          // Form Field 4 & 5: Jenis Kelamin & Usia (Row)
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Jenis Kelamin
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildFormFieldTitle('Jenis Kelamin', isRequired: true),
                    const SizedBox(height: 6),
                    DropdownButtonFormField<String>(
                      value: safeGenderValue,
                      isExpanded: true,
                      decoration: _inputDecoration(),
                      items: const [
                        DropdownMenuItem(
                          value: 'Male',
                          child: Text('Laki-laki 🚹', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                        ),
                        DropdownMenuItem(
                          value: 'Female',
                          child: Text('Perempuan 🚺', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                        ),
                      ],
                      onChanged: (val) {
                        if (val != null) setState(() => _selectedGender = val);
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),

              // Usia (Tahun)
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildFormFieldTitle('Usia (Tahun)', isRequired: true),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _usiaController,
                      keyboardType: TextInputType.number,
                      onChanged: (_) => setState(() {}),
                      style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                      decoration: _inputDecoration(hint: '25'),
                      validator: (val) {
                        if (val == null || val.trim().isEmpty) return 'Wajib diisi';
                        final numVal = int.tryParse(val.trim());
                        if (numVal == null || numVal < 10 || numVal > 90) return '10 - 90 thn';
                        return null;
                      },
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Form Field 6: Kategori Skill Level *
          _buildFormFieldTitle('Kategori Skill Level', isRequired: true),
          const SizedBox(height: 6),
          DropdownButtonFormField<String>(
            value: safeLevelValue,
            isExpanded: true,
            decoration: _inputDecoration(),
            items: const [
              DropdownMenuItem(
                value: 'Newbie',
                child: Text('Newbie (Pemula Sekali)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
              ),
              DropdownMenuItem(
                value: 'Beginner',
                child: Text('Beginner (Bisa Reli Dasar)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
              ),
              DropdownMenuItem(
                value: 'Intermediate',
                child: Text('Intermediate (Paham Rotasi & Taktik)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
              ),
              DropdownMenuItem(
                value: 'Advanced',
                child: Text('Advanced (Turnamen & Kompetitif)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
              ),
            ],
            onChanged: (val) {
              if (val != null) setState(() => _selectedLevel = val);
            },
          ),
          const SizedBox(height: 14),

          // Form Field 7: Afiliasi Komunitas (Safe against missing IDs)
          _buildFormFieldTitle('Afiliasi Komunitas'),
          const SizedBox(height: 6),
          DropdownButtonFormField<int?>(
            value: safeCommunityValue,
            isExpanded: true,
            decoration: _inputDecoration(),
            items: communityItems,
            onChanged: (val) {
              setState(() => _selectedCommunityId = val);
            },
          ),
          _buildFieldCaption('Pilih komunitas jika kamu tergabung dalam klub resmi.'),
        ],
      ),
    );
  }

  Widget _buildAvatarFallback(String name) {
    return Center(
      child: Text(
        name.isNotEmpty ? name[0].toUpperCase() : 'U',
        style: const TextStyle(
          fontSize: 28,
          fontWeight: FontWeight.w900,
          color: Colors.white,
        ),
      ),
    );
  }

  Widget _buildInfoPill(String label, Color bg, Color text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: text.withValues(alpha: 0.2)),
      ),
      child: Text(
        label,
        style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: text),
      ),
    );
  }

  Widget _buildFormFieldTitle(String title, {bool isRequired = false}) {
    return Row(
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w800,
            color: Color(0xFF0F172A),
          ),
        ),
        if (isRequired)
          const Text(
            ' *',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.bold,
              color: Color(0xFFE11D48),
            ),
          ),
      ],
    );
  }

  Widget _buildFieldCaption(String caption) {
    return Padding(
      padding: const EdgeInsets.only(top: 4, left: 2),
      child: Text(
        caption,
        style: const TextStyle(
          fontSize: 10.5,
          color: Color(0xFF94A3B8),
          height: 1.3,
        ),
      ),
    );
  }

  InputDecoration _inputDecoration({
    String? hint,
    Color? fillColor,
    Widget? suffixIcon,
    String? prefixText,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
      prefixText: prefixText,
      filled: true,
      fillColor: fillColor ?? const Color(0xFFFFFFFF),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      suffixIcon: suffixIcon,
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFCBD5E1), width: 1.2),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFF063B00), width: 1.8),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFE11D48), width: 1.2),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFE11D48), width: 1.8),
      ),
    );
  }

  Widget _buildSaveButton() {
    return SizedBox(
      width: double.infinity,
      height: 48,
      child: ElevatedButton(
        onPressed: _isSaving ? null : _handleSaveProfile,
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFF063B00),
          foregroundColor: Colors.white,
          disabledBackgroundColor: const Color(0xFF063B00).withValues(alpha: 0.6),
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
        child: _isSaving
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
              )
            : const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.save_outlined, size: 18),
                  SizedBox(width: 8),
                  Text(
                    'Simpan Perubahan Profil',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
      ),
    );
  }

  Widget _buildLogoutButton() {
    return SizedBox(
      width: double.infinity,
      height: 44,
      child: OutlinedButton(
        onPressed: _handleLogout,
        style: OutlinedButton.styleFrom(
          foregroundColor: const Color(0xFFDC2626),
          side: const BorderSide(color: Color(0xFFFECACA), width: 1.2),
          backgroundColor: const Color(0xFFFEF2F2),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
        child: const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.logout_rounded, size: 16),
            SizedBox(width: 6),
            Text(
              'Keluar dari Akun (Logout)',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      ),
    );
  }

  /// Tampilan Profil untuk Pemain Tamu (Guest)
  Widget _buildGuestProfileView() {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Profil Member',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
        ),
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
        child: Column(
          children: [
            const SizedBox(height: 10),
            ScaleTransition(
              scale: _pulseAnimation,
              child: Container(
                width: 90,
                height: 90,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    colors: [Color(0xFF063B00), Color(0xFF1E5B10)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.matchaDark.withValues(alpha: 0.3),
                      blurRadius: 18,
                      spreadRadius: 2,
                    ),
                  ],
                ),
                child: const Center(
                  child: Icon(
                    Icons.sports_tennis_rounded,
                    size: 44,
                    color: Color(0xFFA8E63A),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF3C7),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFF59E0B).withValues(alpha: 0.4)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.lock_outline_rounded, size: 13, color: Color(0xFFD97706)),
                  const SizedBox(width: 5),
                  Text(
                    'MODE TAMU (GUEST)',
                    style: AppTextStyles.badge.copyWith(
                      color: const Color(0xFFD97706),
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.5,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Text(
              'Akses Profil & Statistik Mabar',
              textAlign: TextAlign.center,
              style: AppTextStyles.h1.copyWith(
                fontSize: 19,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Masuk atau buat akun MATCHA untuk menyimpan riwayat bermain, tracking win rate, dan mengaktifkan hak akses Host Game.',
              textAlign: TextAlign.center,
              style: AppTextStyles.caption.copyWith(
                fontSize: 12.5,
                color: const Color(0xFF64748B),
                height: 1.5,
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => LoginPage(authController: widget.authController),
                    ),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.matchaDark,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.login_rounded, size: 18),
                    SizedBox(width: 8),
                    Text('Masuk ke Akun', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: OutlinedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => RegisterPage(authController: widget.authController),
                    ),
                  );
                },
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.matchaDark,
                  side: const BorderSide(color: AppColors.matchaDark, width: 1.5),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text('Daftar Member Baru', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
