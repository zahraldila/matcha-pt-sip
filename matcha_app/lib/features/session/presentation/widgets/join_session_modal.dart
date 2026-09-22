import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_text_styles.dart';
import '../../../auth/presentation/controllers/auth_controller.dart';
import '../../data/session_service.dart';
import '../../domain/session_model.dart';

class JoinSessionModal extends StatefulWidget {
  final SessionModel session;
  final AuthController? authController;
  final VoidCallback onJoinedSuccess;

  const JoinSessionModal({
    super.key,
    required this.session,
    this.authController,
    required this.onJoinedSuccess,
  });

  static Future<void> show({
    required BuildContext context,
    required SessionModel session,
    AuthController? authController,
    required VoidCallback onJoinedSuccess,
  }) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => JoinSessionModal(
        session: session,
        authController: authController,
        onJoinedSuccess: onJoinedSuccess,
      ),
    );
  }

  @override
  State<JoinSessionModal> createState() => _JoinSessionModalState();
}

class _JoinSessionModalState extends State<JoinSessionModal> {
  final SessionService _sessionService = SessionService();
  final _formKey = GlobalKey<FormState>();

  late final TextEditingController _nameController;
  late final TextEditingController _ageController;
  String _selectedGender = 'Laki-laki';
  String _selectedLevel = 'Intermediate';

  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    final user = widget.authController?.currentUser;
    _nameController = TextEditingController(text: user?.nama ?? '');
    _ageController = TextEditingController(
      text: user?.usia != null ? user!.usia.toString() : '',
    );
    if (user != null && user.level != null && user.level!.isNotEmpty) {
      _selectedLevel = user.level!;
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _ageController.dispose();
    super.dispose();
  }

  Future<void> _handleConfirmJoin() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSubmitting = true);

    try {
      final user = widget.authController?.currentUser;
      int playerIdToJoin;

      if (user != null && user.playerId != null && user.playerId! > 0) {
        // Logged in user
        playerIdToJoin = user.playerId!;
      } else {
        // Guest user - register player instantly
        final genderDb = _selectedGender == 'Laki-laki' ? 'Male' : 'Female';
        final parsedAge = int.tryParse(_ageController.text.trim());

        playerIdToJoin = await _sessionService.registerGuestPlayer(
          nama: _nameController.text.trim(),
          gender: genderDb,
          level: _selectedLevel,
          usia: parsedAge,
        );
      }

      // Join to tb_session_player
      await _sessionService.joinSession(
        sessionId: widget.session.sessionId,
        playerId: playerIdToJoin,
      );

      if (!mounted) return;
      Navigator.pop(context);
      widget.onJoinedSuccess();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Berhasil bergabung ke sesi ${widget.session.namaSession}! 🎉'),
          backgroundColor: AppColors.matchaDark,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal gabung sesi: $e'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: Container(
        padding: const EdgeInsets.fromLTRB(22, 20, 22, 28),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header Row
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: AppColors.matchaSoftLime,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(
                          Icons.person_add_rounded,
                          size: 20,
                          color: AppColors.matchaDark,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Text(
                        'Gabung Sesi Mabar',
                        style: AppTextStyles.h2.copyWith(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF0F172A),
                        ),
                      ),
                    ],
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded, color: Color(0xFF94A3B8), size: 20),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Sesi mabar: ${widget.session.namaSession}',
                style: AppTextStyles.caption.copyWith(
                  color: const Color(0xFF64748B),
                  fontSize: 12,
                ),
              ),
              const SizedBox(height: 18),

              // Field 1: Nama Pemain
              Text(
                'Nama Pemain *',
                style: AppTextStyles.caption.copyWith(
                  color: const Color(0xFF334155),
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
              const SizedBox(height: 6),
              TextFormField(
                controller: _nameController,
                validator: (val) {
                  if (val == null || val.trim().isEmpty) {
                    return 'Nama pemain wajib diisi';
                  }
                  return null;
                },
                decoration: InputDecoration(
                  hintText: 'Masukkan nama lengkap...',
                  hintStyle: AppTextStyles.caption.copyWith(color: const Color(0xFF94A3B8)),
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
                ),
              ),
              const SizedBox(height: 14),

              // Field 2 & 3: Gender & Umur (Row)
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Gender
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Gender',
                          style: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF334155),
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(height: 6),
                        DropdownButtonFormField<String>(
                          initialValue: _selectedGender,
                          decoration: InputDecoration(
                            filled: true,
                            fillColor: const Color(0xFFF8FAFC),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                            ),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                            ),
                          ),
                          items: const [
                            DropdownMenuItem(value: 'Laki-laki', child: Text('Laki-laki')),
                            DropdownMenuItem(value: 'Perempuan', child: Text('Perempuan')),
                          ],
                          onChanged: (val) {
                            if (val != null) setState(() => _selectedGender = val);
                          },
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  // Umur
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Umur',
                          style: AppTextStyles.caption.copyWith(
                            color: const Color(0xFF334155),
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(height: 6),
                        TextFormField(
                          controller: _ageController,
                          keyboardType: TextInputType.number,
                          validator: (val) {
                            if (val != null && val.trim().isNotEmpty) {
                              final age = int.tryParse(val.trim());
                              if (age == null || age < 10 || age > 90) {
                                return 'Usia 10-90 th';
                              }
                            }
                            return null;
                          },
                          decoration: InputDecoration(
                            hintText: 'Contoh: 24',
                            suffixText: 'th',
                            suffixStyle: AppTextStyles.caption.copyWith(
                              color: const Color(0xFF64748B),
                              fontWeight: FontWeight.w600,
                            ),
                            hintStyle: AppTextStyles.caption.copyWith(color: const Color(0xFF94A3B8)),
                            filled: true,
                            fillColor: const Color(0xFFF8FAFC),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
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
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),

              // Field 4: Level Permainan
              Text(
                'Level Permainan',
                style: AppTextStyles.caption.copyWith(
                  color: const Color(0xFF334155),
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
              const SizedBox(height: 6),
              DropdownButtonFormField<String>(
                initialValue: _selectedLevel,
                decoration: InputDecoration(
                  filled: true,
                  fillColor: const Color(0xFFF8FAFC),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
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
                ),
                items: const [
                  DropdownMenuItem(value: 'Newbie', child: Text('Newbie')),
                  DropdownMenuItem(value: 'Beginner', child: Text('Beginner')),
                  DropdownMenuItem(value: 'Intermediate', child: Text('Intermediate')),
                  DropdownMenuItem(value: 'Advanced', child: Text('Advanced')),
                ],
                onChanged: (val) {
                  if (val != null) setState(() => _selectedLevel = val);
                },
              ),
              const SizedBox(height: 24),

              // Action Buttons
              Row(
                children: [
                  Expanded(
                    child: SizedBox(
                      height: 44,
                      child: OutlinedButton(
                        onPressed: _isSubmitting ? null : () => Navigator.pop(context),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: const Color(0xFF64748B),
                          side: const BorderSide(color: Color(0xFFE2E8F0)),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        child: const Text(
                          'Batal',
                          style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: SizedBox(
                      height: 44,
                      child: ElevatedButton(
                        onPressed: _isSubmitting ? null : _handleConfirmJoin,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.matchaDark,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        child: _isSubmitting
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Text(
                                'Konfirmasi Gabung',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
