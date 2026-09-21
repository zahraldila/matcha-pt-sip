import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../main/presentation/main_shell_page.dart';
import 'controllers/auth_controller.dart';

class RegisterPage extends StatefulWidget {
  final AuthController authController;

  const RegisterPage({super.key, required this.authController});

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
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(
            builder: (context) => MainShellPage(authController: widget.authController),
          ),
          (route) => false,
        );
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
          backgroundColor: context.bg,
          appBar: AppBar(
            backgroundColor: context.bg,
            elevation: 0,
            leading: IconButton(
              icon: const Icon(Icons.arrow_back_rounded),
              onPressed: () => Navigator.pop(context),
            ),
          ),
          body: SafeArea(
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Title
                    Text(
                      'Daftar Akun Matcha 🎾',
                      style: AppTextStyles.h1.copyWith(
                        fontSize: 24,
                        color: context.txtPrimary,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Gabung bersama komunitas mabar tenis & padel terbesar.',
                      style: AppTextStyles.bodyMedium.copyWith(
                        color: context.txtSecondary,
                      ),
                    ),

                    const SizedBox(height: 24),

                    if (errorMessage != null) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.error.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.error),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline_rounded, color: AppColors.error, size: 20),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                errorMessage,
                                style: const TextStyle(
                                  color: AppColors.error,
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
                    _buildLabel('Nama Lengkap'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _namaController,
                      decoration: const InputDecoration(
                        hintText: 'Masukkan nama lengkap',
                        prefixIcon: Icon(Icons.person_outline_rounded),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Nama lengkap wajib diisi';
                        }
                        return null;
                      },
                    ),

                    const SizedBox(height: 16),

                    // Email
                    _buildLabel('Alamat Email'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(
                        hintText: 'nama@email.com',
                        prefixIcon: Icon(Icons.email_outlined),
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

                    const SizedBox(height: 16),

                    // Nomor WhatsApp
                    _buildLabel('Nomor WhatsApp (No HP)'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: const InputDecoration(
                        hintText: '081234567890',
                        prefixIcon: Icon(Icons.phone_outlined),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Nomor WhatsApp wajib diisi';
                        }
                        return null;
                      },
                    ),

                    const SizedBox(height: 16),

                    // Password
                    _buildLabel('Kata Sandi'),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: _obscurePassword,
                      decoration: InputDecoration(
                        hintText: 'Minimal 6 karakter',
                        prefixIcon: const Icon(Icons.lock_outline_rounded),
                        suffixIcon: IconButton(
                          icon: Icon(
                            _obscurePassword
                                ? Icons.visibility_off_outlined
                                : Icons.visibility_outlined,
                          ),
                          onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.length < 6) {
                          return 'Kata sandi minimal 6 karakter';
                        }
                        return null;
                      },
                    ),

                    const SizedBox(height: 16),

                    // Gender & Usia
                    Row(
                      children: [
                        // Gender
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildLabel('Jenis Kelamin'),
                              const SizedBox(height: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 14),
                                decoration: BoxDecoration(
                                  color: context.surf,
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(color: context.surfBorder),
                                ),
                                child: DropdownButtonHideUnderline(
                                  child: DropdownButton<String>(
                                    value: _selectedGender,
                                    isExpanded: true,
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
                        const SizedBox(width: 14),
                        // Usia
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildLabel('Usia'),
                              const SizedBox(height: 6),
                              TextFormField(
                                controller: _usiaController,
                                keyboardType: TextInputType.number,
                                decoration: const InputDecoration(
                                  hintText: 'Tahun',
                                  prefixIcon: Icon(Icons.cake_outlined),
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

                    const SizedBox(height: 16),

                    // Level / Tingkat Kemampuan
                    _buildLabel('Tingkat Kemampuan Olahraga'),
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      decoration: BoxDecoration(
                        color: context.surf,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: context.surfBorder),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          value: _selectedLevel,
                          isExpanded: true,
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

                    const SizedBox(height: 32),

                    // Tombol Daftar
                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton(
                        onPressed: isLoading ? null : _handleRegister,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.matchaDark,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                        child: isLoading
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                ),
                              )
                            : const Text(
                                'Daftar Sekarang',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                              ),
                      ),
                    ),

                    const SizedBox(height: 16),

                    // Kembali ke login
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          'Sudah punya akun? ',
                          style: AppTextStyles.bodyMedium.copyWith(color: context.txtSecondary),
                        ),
                        GestureDetector(
                          onTap: () => Navigator.pop(context),
                          child: Text(
                            'Masuk di sini',
                            style: AppTextStyles.bodyMedium.copyWith(
                              color: AppColors.matchaDark,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 32),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildLabel(String text) {
    return Text(
      text,
      style: AppTextStyles.caption.copyWith(
        fontWeight: FontWeight.bold,
        color: context.txtPrimary,
      ),
    );
  }
}
