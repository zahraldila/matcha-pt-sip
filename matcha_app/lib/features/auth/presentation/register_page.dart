import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import 'controllers/auth_controller.dart';

class RegisterPage extends StatefulWidget {
  final AuthController authController;

  RegisterPage({super.key, AuthController? authController})
      : authController = authController ?? AuthController();

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final _formKey = GlobalKey<FormState>();

  // 1. Role / Peran Utama
  String _selectedRole = 'member'; // 'member' atau 'venue_owner'

  // 2. Data Profil & Kontak
  final _namaController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();

  // 3. Parameter Pertandingan & Skill Level
  String? _selectedGender;
  final _usiaController = TextEditingController();
  String? _selectedLevel;
  int? _selectedCommunityId;

  // 4. Keamanan Password Akun
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  // Daftar Komunitas
  List<Map<String, dynamic>> _communities = [];
  bool _loadingCommunities = false;

  @override
  void initState() {
    super.initState();
    _fetchCommunities();
  }

  Future<void> _fetchCommunities() async {
    setState(() => _loadingCommunities = true);
    try {
      final list = await widget.authController.getCommunities();
      if (mounted) {
        setState(() {
          _communities = list;
          _loadingCommunities = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _loadingCommunities = false);
      }
    }
  }

  @override
  void dispose() {
    _namaController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _usiaController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleRegister() async {
    widget.authController.clearError();
    if (_formKey.currentState?.validate() ?? false) {
      final usia = int.tryParse(_usiaController.text.trim());

      final success = await widget.authController.register(
        nama: _namaController.text.trim(),
        email: _emailController.text.trim(),
        noHp: _phoneController.text.trim(),
        password: _passwordController.text,
        gender: _selectedGender!,
        usia: usia!,
        level: _selectedLevel!,
        role: _selectedRole,
        communityId: (_selectedCommunityId != null && _selectedCommunityId! > 0)
            ? _selectedCommunityId
            : null,
      );

      if (success && mounted) {
        final registeredEmail = _emailController.text.trim();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('🎉 Pendaftaran berhasil! Selamat datang di MATCHA.'),
            backgroundColor: AppColors.matchaDark,
            behavior: SnackBarBehavior.floating,
            duration: Duration(seconds: 3),
          ),
        );

        Navigator.of(context).pop(registeredEmail);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: widget.authController,
      builder: (context, _) {
        final isLoading = widget.authController.isLoading;
        final errorMessage = widget.authController.errorMessage;

        return Scaffold(
          backgroundColor: const Color(0xFFF8FAFC),
          body: Stack(
            children: [
              // Ambient Radial Glow Orbs
              Positioned(
                top: -60,
                right: -40,
                child: Container(
                  width: 260,
                  height: 260,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: RadialGradient(
                      colors: [
                        AppColors.matchaLime.withValues(alpha: 0.28),
                        AppColors.matchaSoftLime.withValues(alpha: 0.12),
                        Colors.transparent,
                      ],
                    ),
                  ),
                ),
              ),
              Positioned(
                bottom: 80,
                left: -60,
                child: Container(
                  width: 240,
                  height: 240,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: RadialGradient(
                      colors: [
                        AppColors.matchaDark.withValues(alpha: 0.12),
                        AppColors.matchaSoftLime.withValues(alpha: 0.06),
                        Colors.transparent,
                      ],
                    ),
                  ),
                ),
              ),

              SafeArea(
                child: SingleChildScrollView(
                  physics: const BouncingScrollPhysics(),
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Header Navigation (Back & Masuk)
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          GestureDetector(
                            onTap: () => Navigator.pop(context),
                            child: Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.9),
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(color: const Color(0xFFE2E8F0)),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.03),
                                    blurRadius: 8,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: const Icon(
                                Icons.arrow_back_rounded,
                                size: 20,
                                color: Color(0xFF050608),
                              ),
                            ),
                          ),
                          GestureDetector(
                            onTap: () => Navigator.pop(context),
                            child: const Padding(
                              padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              child: Text(
                                'Masuk',
                                style: TextStyle(
                                  color: AppColors.matchaDark,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 13,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      // Badge & Header
                      Center(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                          decoration: BoxDecoration(
                            color: AppColors.matchaSoftLime,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(
                              color: const Color(0xFF063B00).withValues(alpha: 0.2),
                            ),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.auto_awesome_rounded, size: 13, color: AppColors.matchaDark),
                              SizedBox(width: 6),
                              Text(
                                'MATCHA MEMBER HUB',
                                style: TextStyle(
                                  color: AppColors.matchaDark,
                                  fontSize: 10,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: 1.2,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),

                      Text(
                        'Pendaftaran Akun Baru',
                        style: AppTextStyles.h1.copyWith(
                          fontSize: 22,
                          color: const Color(0xFF050608),
                          fontWeight: FontWeight.w900,
                          letterSpacing: -0.3,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 4),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 16.0),
                        child: Text(
                          'Daftarkan akun dan profil pemain Anda untuk sinkronisasi otomatis saat drawing, live scoring, dan pencatatan rating',
                          style: TextStyle(
                            fontSize: 12,
                            color: Color(0xFF64748B),
                            height: 1.4,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ),

                      const SizedBox(height: 20),

                      // Frosted Glass Form Card
                      ClipRRect(
                        borderRadius: BorderRadius.circular(28),
                        child: BackdropFilter(
                          filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
                          child: Container(
                            padding: const EdgeInsets.all(22),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.95),
                              borderRadius: BorderRadius.circular(28),
                              border: Border.all(
                                color: Colors.white.withValues(alpha: 0.95),
                                width: 1.5,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: const Color(0xFF063B00).withValues(alpha: 0.08),
                                  blurRadius: 36,
                                  offset: const Offset(0, 12),
                                ),
                              ],
                            ),
                            child: Form(
                              key: _formKey,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (errorMessage != null) ...[
                                    Container(
                                      padding: const EdgeInsets.all(12),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFFEF2F2),
                                        borderRadius: BorderRadius.circular(14),
                                        border: Border.all(color: const Color(0xFFFECACA)),
                                      ),
                                      child: Row(
                                        children: [
                                          const Icon(
                                            Icons.error_outline_rounded,
                                            color: Color(0xFFDC2626),
                                            size: 18,
                                          ),
                                          const SizedBox(width: 8),
                                          Expanded(
                                            child: Text(
                                              errorMessage,
                                              style: const TextStyle(
                                                color: Color(0xFFDC2626),
                                                fontSize: 12,
                                                fontWeight: FontWeight.w600,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(height: 18),
                                  ],

                                  // ==================== STEP 1: PILIH PERAN UTAMA ANDA ====================
                                  _buildSectionHeader('1', 'PILIH PERAN UTAMA ANDA'),
                                  const SizedBox(height: 12),

                                  _buildRoleCard(
                                    roleKey: 'member',
                                    title: 'Pemain / Member',
                                    subtitle: 'Ikut mabar & rekap statistik',
                                    icon: Icons.person_rounded,
                                  ),
                                  const SizedBox(height: 10),

                                  _buildRoleCard(
                                    roleKey: 'venue_owner',
                                    title: 'Pemilik Venue',
                                    subtitle: 'Daftarkan & kelola lapangan',
                                    icon: Icons.domain_rounded,
                                  ),
                                  const SizedBox(height: 20),

                                  // ==================== STEP 2: DATA PROFIL & KONTAK ====================
                                  _buildSectionHeader('2', 'DATA PROFIL & KONTAK'),
                                  const SizedBox(height: 12),

                                  // Nama Lengkap
                                  _buildFieldLabel('Nama Lengkap'),
                                  const SizedBox(height: 6),
                                  TextFormField(
                                    controller: _namaController,
                                    textCapitalization: TextCapitalization.words,
                                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                    decoration: _inputDecoration(
                                      hint: 'Contoh: Billy Santoso',
                                      icon: Icons.person_outline_rounded,
                                    ),
                                    validator: (value) {
                                      if (value == null || value.trim().isEmpty) {
                                        return 'Nama lengkap wajib diisi.';
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 4),
                                  const Text(
                                    '*Nama ini akan tercatat konsisten di papan drawing dan rekap pertandingan.',
                                    style: TextStyle(
                                      color: Color(0xFF94A3B8),
                                      fontSize: 10,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                  const SizedBox(height: 14),

                                  // Nomor WhatsApp / HP
                                  _buildFieldLabel('Nomor WhatsApp / HP'),
                                  const SizedBox(height: 6),
                                  TextFormField(
                                    controller: _phoneController,
                                    keyboardType: TextInputType.phone,
                                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                                    maxLength: 15,
                                    buildCounter: (context, {required currentLength, required isFocused, maxLength}) => null,
                                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                    decoration: _inputDecoration(
                                      hint: '0812xxxxxxxx (9-15 digit angka)',
                                      icon: Icons.phone_outlined,
                                    ),
                                    validator: (value) {
                                      final cleanPhone = (value ?? '').replaceAll(RegExp(r'[^0-9]'), '');
                                      if (cleanPhone.isEmpty) {
                                        return 'Nomor WhatsApp / HP wajib diisi.';
                                      }
                                      if (cleanPhone.length < 9 || cleanPhone.length > 15) {
                                        return 'Nomor WhatsApp hanya boleh berupa angka (9 - 15 digit).';
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 14),

                                  // Alamat Email
                                  _buildFieldLabel('Alamat Email'),
                                  const SizedBox(height: 6),
                                  TextFormField(
                                    controller: _emailController,
                                    keyboardType: TextInputType.emailAddress,
                                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                    decoration: _inputDecoration(
                                      hint: 'nama@email.com',
                                      icon: Icons.mail_outline_rounded,
                                    ),
                                    validator: (value) {
                                      final email = value?.trim() ?? '';
                                      if (email.isEmpty) {
                                        return 'Alamat email wajib diisi.';
                                      }
                                      final emailRegex = RegExp(r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$');
                                      if (!emailRegex.hasMatch(email)) {
                                        return 'Format alamat email tidak valid (contoh: nama@domain.com).';
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 20),

                                  // ==================== STEP 3: PARAMETER PERTANDINGAN & SKILL LEVEL ====================
                                  _buildSectionHeader('3', 'PARAMETER PERTANDINGAN & SKILL LEVEL'),
                                  const SizedBox(height: 12),

                                  // Jenis Kelamin
                                  _buildFieldLabel('Jenis Kelamin'),
                                  const SizedBox(height: 6),
                                  DropdownButtonFormField<String>(
                                    initialValue: _selectedGender,
                                    icon: const Icon(Icons.keyboard_arrow_down_rounded, size: 20, color: Color(0xFF64748B)),
                                    decoration: _inputDecoration(hint: 'Pilih Jenis Kelamin', icon: Icons.wc_rounded),
                                    hint: const Text(
                                      'Pilih Jenis Kelamin',
                                      style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13, fontWeight: FontWeight.normal),
                                    ),
                                    dropdownColor: Colors.white,
                                    borderRadius: BorderRadius.circular(16),
                                    items: const [
                                      DropdownMenuItem(
                                        value: 'Male',
                                        child: Text('Laki-laki 🚹', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
                                      ),
                                      DropdownMenuItem(
                                        value: 'Female',
                                        child: Text('Perempuan 🚺', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
                                      ),
                                    ],
                                    onChanged: (val) => setState(() => _selectedGender = val),
                                    validator: (val) => (val == null || val.isEmpty) ? 'Jenis kelamin wajib dipilih.' : null,
                                  ),
                                  const SizedBox(height: 4),
                                  const Text(
                                    '*Digunakan algoritma format Mixicano / Mix Americano.',
                                    style: TextStyle(
                                      color: Color(0xFF94A3B8),
                                      fontSize: 10,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                  const SizedBox(height: 14),

                                  // Usia (Tahun)
                                  _buildFieldLabel('Usia (Tahun)'),
                                  const SizedBox(height: 6),
                                  TextFormField(
                                    controller: _usiaController,
                                    keyboardType: TextInputType.number,
                                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                                    maxLength: 3,
                                    buildCounter: (context, {required currentLength, required isFocused, maxLength}) => null,
                                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                    decoration: _inputDecoration(
                                      hint: 'Contoh: 28',
                                      icon: Icons.cake_outlined,
                                    ),
                                    validator: (value) {
                                      final text = value?.trim() ?? '';
                                      if (text.isEmpty) {
                                        return 'Usia wajib diisi.';
                                      }
                                      final age = int.tryParse(text);
                                      if (age == null || age < 10 || age > 90) {
                                        return 'Usia harus di antara 10 - 90 tahun.';
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 14),

                                  // Kategori Skill Level
                                  _buildFieldLabel('Kategori Skill Level'),
                                  const SizedBox(height: 6),
                                  DropdownButtonFormField<String>(
                                    initialValue: _selectedLevel,
                                    icon: const Icon(Icons.keyboard_arrow_down_rounded, size: 20, color: Color(0xFF64748B)),
                                    decoration: _inputDecoration(hint: 'Pilih Kategori Skill Level', icon: Icons.sports_tennis_rounded),
                                    hint: const Text(
                                      'Pilih Kategori Skill Level',
                                      style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13, fontWeight: FontWeight.normal),
                                    ),
                                    dropdownColor: Colors.white,
                                    borderRadius: BorderRadius.circular(16),
                                    isExpanded: true,
                                    items: const [
                                      DropdownMenuItem(
                                        value: 'Newbie',
                                        child: Text('Newbie (Baru mulai / belajar)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
                                      ),
                                      DropdownMenuItem(
                                        value: 'Beginner',
                                        child: Text('Beginner (Rally dasar lancar)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
                                      ),
                                      DropdownMenuItem(
                                        value: 'Intermediate',
                                        child: Text('Intermediate (Konsisten match play)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
                                      ),
                                      DropdownMenuItem(
                                        value: 'Advanced',
                                        child: Text('Advanced (Turnamen & Kompetitif)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
                                      ),
                                    ],
                                    onChanged: (val) => setState(() => _selectedLevel = val),
                                    validator: (val) => (val == null || val.isEmpty) ? 'Kategori skill level wajib dipilih.' : null,
                                  ),
                                  const SizedBox(height: 4),
                                  const Text(
                                    '*Membantu sistem menyusun drawing tim yang seimbang.',
                                    style: TextStyle(
                                      color: Color(0xFF94A3B8),
                                      fontSize: 10,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                  const SizedBox(height: 14),

                                  // Pilihan Komunitas (Opsional)
                                  _buildFieldLabel('Pilihan Komunitas (Opsional)', isRequired: false),
                                  const SizedBox(height: 6),
                                  DropdownButtonFormField<int?>(
                                    initialValue: _selectedCommunityId,
                                    icon: _loadingCommunities
                                        ? const SizedBox(
                                            width: 16,
                                            height: 16,
                                            child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.matchaDark),
                                          )
                                        : const Icon(Icons.keyboard_arrow_down_rounded, size: 20, color: Color(0xFF64748B)),
                                    decoration: _inputDecoration(hint: 'Pilih Komunitas', icon: Icons.groups_outlined),
                                    hint: const Text(
                                      'Pilih Komunitas',
                                      style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13, fontWeight: FontWeight.normal),
                                    ),
                                    dropdownColor: Colors.white,
                                    borderRadius: BorderRadius.circular(16),
                                    isExpanded: true,
                                    items: [
                                      const DropdownMenuItem<int?>(
                                        value: null,
                                        child: Text('Pilih Komunitas', style: TextStyle(fontSize: 13, color: Color(0xFF94A3B8))),
                                      ),
                                      const DropdownMenuItem<int?>(
                                        value: -1,
                                        child: Text('Personal (Non-Community / Belum Ada)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A))),
                                      ),
                                      ..._communities.map((c) {
                                        final id = (c['community_id'] as num).toInt();
                                        final name = c['nama_community']?.toString() ?? 'Komunitas #$id';
                                        return DropdownMenuItem<int?>(
                                          value: id,
                                          child: Text(
                                            name,
                                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        );
                                      }),
                                    ],
                                    onChanged: (val) => setState(() => _selectedCommunityId = val),
                                  ),
                                  const SizedBox(height: 20),

                                  // ==================== STEP 4: KEAMANAN PASSWORD AKUN ====================
                                  _buildSectionHeader('4', 'KEAMANAN PASSWORD AKUN'),
                                  const SizedBox(height: 12),

                                  // Password Login
                                  _buildFieldLabel('Password Login'),
                                  const SizedBox(height: 6),
                                  TextFormField(
                                    controller: _passwordController,
                                    obscureText: _obscurePassword,
                                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                    decoration: InputDecoration(
                                      hintText: 'Minimal 6 karakter',
                                      hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                                      prefixIcon: const Icon(Icons.lock_outline_rounded, size: 18, color: Color(0xFF94A3B8)),
                                      suffixIcon: IconButton(
                                        icon: Icon(
                                          _obscurePassword
                                              ? Icons.visibility_off_outlined
                                              : Icons.visibility_outlined,
                                          size: 18,
                                          color: const Color(0xFF94A3B8),
                                        ),
                                        onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                      ),
                                      filled: true,
                                      fillColor: const Color(0xFFF8FAFC),
                                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
                                      border: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(16),
                                        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                                      ),
                                      enabledBorder: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(16),
                                        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                                      ),
                                      focusedBorder: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(16),
                                        borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.6),
                                      ),
                                      errorStyle: const TextStyle(color: Color(0xFFE11D48), fontSize: 11, fontWeight: FontWeight.w600),
                                    ),
                                    validator: (value) {
                                      if (value == null || value.isEmpty) {
                                        return 'Password wajib diisi.';
                                      }
                                      if (value.length < 6) {
                                        return 'Password minimal 6 karakter.';
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 24),

                                  // Submit CTA Button
                                  SizedBox(
                                    width: double.infinity,
                                    height: 52,
                                    child: ElevatedButton(
                                      onPressed: isLoading ? null : _handleRegister,
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: const Color(0xFF063B00),
                                        foregroundColor: Colors.white,
                                        elevation: 2,
                                        shadowColor: const Color(0xFF063B00).withValues(alpha: 0.35),
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(18),
                                        ),
                                      ),
                                      child: isLoading
                                          ? const SizedBox(
                                              height: 20,
                                              width: 20,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2.2,
                                                valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                              ),
                                            )
                                          : Row(
                                              mainAxisAlignment: MainAxisAlignment.center,
                                              children: [
                                                const Icon(
                                                  Icons.person_add_alt_1_rounded,
                                                  size: 18,
                                                  color: Color(0xFFA8E63A),
                                                ),
                                                const SizedBox(width: 8),
                                                Text(
                                                  _selectedRole == 'venue_owner'
                                                      ? 'Selesaikan Registrasi Pemilik Venue'
                                                      : 'Selesaikan Registrasi Member',
                                                  style: const TextStyle(
                                                    fontWeight: FontWeight.w900,
                                                    fontSize: 13,
                                                    letterSpacing: 0.2,
                                                  ),
                                                ),
                                              ],
                                            ),
                                    ),
                                  ),

                                  const SizedBox(height: 18),

                                  // Footer Back to Login
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      const Text(
                                        'Sudah memiliki akun? ',
                                        style: TextStyle(
                                          color: Color(0xFF64748B),
                                          fontSize: 13,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                      GestureDetector(
                                        onTap: () => Navigator.pop(context),
                                        child: const Text(
                                          'Masuk di sini',
                                          style: TextStyle(
                                            color: Color(0xFF063B00),
                                            fontWeight: FontWeight.w900,
                                            fontSize: 13,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 28),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  // Section Header: Number badge + Uppercase Title
  Widget _buildSectionHeader(String stepNumber, String title) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 20,
              height: 20,
              decoration: const BoxDecoration(
                color: Color(0xFF063B00),
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Text(
                stepNumber,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            const SizedBox(width: 8),
            Text(
              title,
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w900,
                color: Color(0xFF050608),
                letterSpacing: 0.6,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        const Divider(color: Color(0xFFF1F5F9), height: 1, thickness: 1),
      ],
    );
  }

  // Animated Role Selection Card
  Widget _buildRoleCard({
    required String roleKey,
    required String title,
    required String subtitle,
    required IconData icon,
  }) {
    final isSelected = _selectedRole == roleKey;
    return GestureDetector(
      onTap: () => setState(() => _selectedRole = roleKey),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeInOut,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: isSelected
              ? const Color(0xFFEBF8D8).withValues(alpha: 0.85)
              : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: isSelected ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
            width: isSelected ? 1.8 : 1.0,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: const Color(0xFF063B00).withValues(alpha: 0.08),
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  )
                ]
              : null,
        ),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: isSelected
                      ? const Color(0xFF063B00).withValues(alpha: 0.3)
                      : const Color(0xFFE2E8F0),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Icon(
                icon,
                size: 20,
                color: const Color(0xFF063B00),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: isSelected ? const Color(0xFF050608) : const Color(0xFF1E293B),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 11,
                      color: Color(0xFF64748B),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            if (isSelected)
              const Icon(
                Icons.check_circle_rounded,
                color: Color(0xFF063B00),
                size: 20,
              ),
          ],
        ),
      ),
    );
  }

  InputDecoration _inputDecoration({required String hint, IconData? icon}) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
      prefixIcon: icon != null ? Icon(icon, size: 18, color: const Color(0xFF94A3B8)) : null,
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.6),
      ),
      errorStyle: const TextStyle(color: Color(0xFFE11D48), fontSize: 11, fontWeight: FontWeight.w600),
    );
  }

  Widget _buildFieldLabel(String text, {bool isRequired = true}) {
    return RichText(
      text: TextSpan(
        text: text,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w800,
          color: Color(0xFF1E293B),
        ),
        children: [
          if (isRequired)
            const TextSpan(
              text: ' *',
              style: TextStyle(
                color: Color(0xFFE11D48),
                fontWeight: FontWeight.bold,
              ),
            ),
        ],
      ),
    );
  }
}
