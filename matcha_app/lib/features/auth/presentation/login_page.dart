import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../main/presentation/main_shell_page.dart';
import 'controllers/auth_controller.dart';
import 'register_page.dart';

class LoginPage extends StatefulWidget {
  final AuthController authController;

  const LoginPage({super.key, required this.authController});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _loginIdController = TextEditingController(text: 'marcel@matcha.id');
  final _passwordController = TextEditingController(text: '123456');
  bool _obscurePassword = true;

  @override
  void dispose() {
    _loginIdController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    widget.authController.clearError();
    if (_formKey.currentState?.validate() ?? false) {
      final success = await widget.authController.login(
        _loginIdController.text,
        _passwordController.text,
      );

      if (success && mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (context) => MainShellPage(authController: widget.authController),
          ),
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
          body: SafeArea(
            child: Center(
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 16.0),
                child: Form(
                  key: _formKey,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Matcha Logo & Icon
                      Center(
                        child: Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: AppColors.matchaSoftLime,
                            shape: BoxShape.circle,
                          ),
                          child: Image.asset(
                            'assets/images/logo.png',
                            height: 60,
                            fit: BoxFit.contain,
                            errorBuilder: (context, error, stackTrace) => const Text(
                              '🎾',
                              style: TextStyle(fontSize: 48),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // App Title
                      Text(
                        'Selamat Datang di Matcha',
                        style: AppTextStyles.h1.copyWith(
                          color: context.txtPrimary,
                          fontSize: 22,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Masuk untuk kelola jadwal mabar & live scoring',
                        style: AppTextStyles.bodyMedium.copyWith(
                          color: context.txtSecondary,
                          fontSize: 13,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 32),

                      // Error Banner
                      if (errorMessage != null) ...[
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AppColors.error.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppColors.error.withValues(alpha: 0.5)),
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

                      // Input Login ID (Email / WhatsApp)
                      _buildLabel('Email atau Nomor WhatsApp'),
                      const SizedBox(height: 6),
                      TextFormField(
                        controller: _loginIdController,
                        decoration: const InputDecoration(
                          hintText: 'nama@email.com atau 08123456789',
                          prefixIcon: Icon(Icons.person_outline_rounded),
                        ),
                        validator: (value) {
                          if (value == null || value.trim().isEmpty) {
                            return 'Email atau Nomor WhatsApp wajib diisi';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),

                      // Input Password
                      _buildLabel('Kata Sandi'),
                      const SizedBox(height: 6),
                      TextFormField(
                        controller: _passwordController,
                        obscureText: _obscurePassword,
                        decoration: InputDecoration(
                          hintText: 'Masukkan kata sandi',
                          prefixIcon: const Icon(Icons.lock_outline_rounded),
                          suffixIcon: IconButton(
                            icon: Icon(
                              _obscurePassword
                                  ? Icons.visibility_off_outlined
                                  : Icons.visibility_outlined,
                            ),
                            onPressed: () {
                              setState(() => _obscurePassword = !_obscurePassword);
                            },
                          ),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Kata sandi wajib diisi';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 24),

                      // Login Button
                      SizedBox(
                        height: 50,
                        child: ElevatedButton(
                          onPressed: isLoading ? null : _handleLogin,
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
                                  'Masuk Sekarang',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                ),
                        ),
                      ),

                      const SizedBox(height: 24),

                      // Register Navigation
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            'Belum punya akun? ',
                            style: AppTextStyles.bodyMedium.copyWith(color: context.txtSecondary),
                          ),
                          GestureDetector(
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) => RegisterPage(
                                    authController: widget.authController,
                                  ),
                                ),
                              );
                            },
                            child: Text(
                              'Daftar Akun',
                              style: AppTextStyles.bodyMedium.copyWith(
                                color: AppColors.matchaDark,
                                fontWeight: FontWeight.bold,
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
