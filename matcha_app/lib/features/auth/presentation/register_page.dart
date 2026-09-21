import 'dart:ui';
import 'package:flutter/material.dart';
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

  final _namaController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _usiaController = TextEditingController(text: '25');

  String _selectedGender = 'Male';
  String _selectedLevel = 'Beginner';
  bool _obscurePassword = true;

  @override
  void dispose() {
    _namaController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _usiaController.dispose();
    super.dispose();
  }

  Future<void> _handleRegister() async {
    widget.authController.clearError();
    if (_formKey.currentState?.validate() ?? false) {
      final usia = int.tryParse(_usiaController.text.trim()) ?? 25;

      final success = await widget.authController.register(
        nama: _namaController.text.trim(),
        email: _emailController.text.trim(),
        noHp: _phoneController.text.trim(),
        password: _passwordController.text,
        gender: _selectedGender,
        usia: usia,
        level: _selectedLevel,
      );

      if (success && mounted) {
        final registeredEmail = _emailController.text.trim();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('🎉 Pendaftaran berhasil! Silakan masuk dengan akun barumu.'),
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
                      // Back Button
                      Align(
                        alignment: Alignment.centerLeft,
                        child: GestureDetector(
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
                          'Daftarkan akun dan profil pemain Anda untuk sinkronisasi otomatis saat drawing & live scoring',
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
                              color: Colors.white.withValues(alpha: 0.9),
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
                                          const Icon(Icons.error_outline_rounded, color: Color(0xFFDC2626), size: 18),
                                          const SizedBox(width: 8),
                                          Expanded(
                                            child: Text(
                                              errorMessage,
                                              style: const TextStyle(
                                                color: Color(0xFFB91C1C),
                                                fontSize: 12,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(height: 16),
                                  ],

                                  // Nama Lengkap
                                  _buildFieldLabel('Nama Lengkap'),
                                  const SizedBox(height: 6),
                                  TextFormField(
                                    controller: _namaController,
                                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                    decoration: _inputDecoration(
                                      hint: 'Masukkan nama lengkap',
                                      icon: Icons.person_outline_rounded,
                                    ),
                                    validator: (value) {
                                      if (value == null || value.trim().isEmpty) {
                                        return 'Nama lengkap wajib diisi';
                                      }
                                      return null;
                                    },
                                  ),

                                  const SizedBox(height: 14),

                                  // Email
                                  _buildFieldLabel('Alamat Email'),
                                  const SizedBox(height: 6),
                                  TextFormField(
                                    controller: _emailController,
                                    keyboardType: TextInputType.emailAddress,
                                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                    decoration: _inputDecoration(
                                      hint: 'nama@email.com',
                                      icon: Icons.alternate_email_rounded,
                                    ),
                                    validator: (value) {
                                      if (value == null || value.trim().isEmpty) {
                                        return 'Email wajib diisi';
                                      }
                                      if (!value.contains('@') || !value.contains('.')) {
                                        return 'Format email tidak valid';
                                      }
                                      return null;
                                    },
                                  ),

                                  const SizedBox(height: 14),

                                  // Nomor WhatsApp
                                  _buildFieldLabel('Nomor WhatsApp (No HP)'),
                                  const SizedBox(height: 6),
                                  TextFormField(
                                    controller: _phoneController,
                                    keyboardType: TextInputType.phone,
                                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                    decoration: _inputDecoration(
                                      hint: '081234567890',
                                      icon: Icons.phone_outlined,
                                    ),
                                    validator: (value) {
                                      if (value == null || value.trim().isEmpty) {
                                        return 'Nomor WhatsApp wajib diisi';
                                      }
                                      return null;
                                    },
                                  ),

                                  const SizedBox(height: 14),

                                  // Password
                                  _buildFieldLabel('Kata Sandi'),
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
                                    ),
                                    validator: (value) {
                                      if (value == null || value.length < 6) {
                                        return 'Kata sandi minimal 6 karakter';
                                      }
                                      return null;
                                    },
                                  ),

                                  const SizedBox(height: 14),

                                  // Gender & Usia
                                  Row(
                                    children: [
                                      // Gender
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            _buildFieldLabel('Jenis Kelamin'),
                                            const SizedBox(height: 6),
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 14),
                                              decoration: BoxDecoration(
                                                color: const Color(0xFFF8FAFC),
                                                borderRadius: BorderRadius.circular(16),
                                                border: Border.all(color: const Color(0xFFE2E8F0)),
                                              ),
                                              child: DropdownButtonHideUnderline(
                                                child: DropdownButton<String>(
                                                  value: _selectedGender,
                                                  isExpanded: true,
                                                  style: const TextStyle(
                                                    color: Color(0xFF0F172A),
                                                    fontSize: 13,
                                                    fontWeight: FontWeight.w600,
                                                  ),
                                                  items: const [
                                                    DropdownMenuItem(value: 'Male', child: Text('Laki-laki')),
                                                    DropdownMenuItem(value: 'Female', child: Text('Perempuan')),
                                                  ],
                                                  onChanged: (val) {
                                                    if (val != null) setState(() => _selectedGender = val);
                                                  },
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      // Usia
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            _buildFieldLabel('Usia'),
                                            const SizedBox(height: 6),
                                            TextFormField(
                                              controller: _usiaController,
                                              keyboardType: TextInputType.number,
                                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                                              decoration: _inputDecoration(
                                                hint: 'Tahun',
                                                icon: Icons.cake_outlined,
                                              ),
                                              validator: (value) {
                                                final num = int.tryParse(value ?? '');
                                                if (num == null || num < 10 || num > 90) {
                                                  return 'Usia 10-90';
                                                }
                                                return null;
                                              },
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),

                                  const SizedBox(height: 14),

                                  // Level / Tingkat Kemampuan
                                  _buildFieldLabel('Tingkat Kemampuan Olahraga'),
                                  const SizedBox(height: 6),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 14),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFF8FAFC),
                                      borderRadius: BorderRadius.circular(16),
                                      border: Border.all(color: const Color(0xFFE2E8F0)),
                                    ),
                                    child: DropdownButtonHideUnderline(
                                      child: DropdownButton<String>(
                                        value: _selectedLevel,
                                        isExpanded: true,
                                        style: const TextStyle(
                                          color: Color(0xFF0F172A),
                                          fontSize: 13,
                                          fontWeight: FontWeight.w600,
                                        ),
                                        items: const [
                                          DropdownMenuItem(value: 'Newbie', child: Text('🌱 Newbie (Baru Mulai)')),
                                          DropdownMenuItem(value: 'Beginner', child: Text('🥉 Beginner (Pemula)')),
                                          DropdownMenuItem(value: 'Intermediate', child: Text('🥈 Intermediate (Menengah)')),
                                          DropdownMenuItem(value: 'Advanced', child: Text('🥇 Advanced (Mahir)')),
                                        ],
                                        onChanged: (val) {
                                          if (val != null) setState(() => _selectedLevel = val);
                                        },
                                      ),
                                    ),
                                  ),

                                  const SizedBox(height: 24),

                                  // Submit Button
                                  SizedBox(
                                    width: double.infinity,
                                    height: 50,
                                    child: ElevatedButton(
                                      onPressed: isLoading ? null : _handleRegister,
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: AppColors.matchaDark,
                                        foregroundColor: Colors.white,
                                        elevation: 3,
                                        shadowColor: AppColors.matchaDark.withValues(alpha: 0.35),
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(16),
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
                                          : const Row(
                                              mainAxisAlignment: MainAxisAlignment.center,
                                              children: [
                                                Text(
                                                  'Daftar Sekarang',
                                                  style: TextStyle(
                                                    fontWeight: FontWeight.w900,
                                                    fontSize: 14,
                                                    letterSpacing: 0.2,
                                                  ),
                                                ),
                                                SizedBox(width: 8),
                                                Icon(
                                                  Icons.arrow_forward_rounded,
                                                  size: 16,
                                                  color: AppColors.matchaLime,
                                                ),
                                              ],
                                            ),
                                    ),
                                  ),

                                  const SizedBox(height: 16),

                                  // Footer Back to Login
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      const Text(
                                        'Sudah punya akun? ',
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
                                            color: AppColors.matchaDark,
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
                      const SizedBox(height: 24),
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

  InputDecoration _inputDecoration({required String hint, required IconData icon}) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
      prefixIcon: Icon(icon, size: 18, color: const Color(0xFF94A3B8)),
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
    );
  }

  Widget _buildFieldLabel(String text) {
    return Text(
      text,
      style: const TextStyle(
        fontWeight: FontWeight.w800,
        fontSize: 12,
        color: Color(0xFF1E293B),
      ),
    );
  }
}
