import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
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
    final user = authController?.currentUser;

    if (user != null && user.isVenueOwner) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Venue Owner tidak dapat bergabung sebagai peserta slot mabar.'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return Future.value();
    }

    final isAlreadyJoined = (user != null && user.playerId != null && user.playerId! > 0)
        ? session.registeredPlayers.any((p) => p.playerId == user.playerId || (p.userId != null && p.userId == user.userId))
        : false;

    if (isAlreadyJoined) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Kamu sudah terdaftar di sesi mabar ini!'),
          backgroundColor: Color(0xFF15803D),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return Future.value();
    }

    if (session.isFull) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Maaf, kuota slot sesi mabar ini sudah penuh!'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return Future.value();
    }

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
  String? _selectedGender;
  String? _selectedLevel;

  bool _isSubmitting = false;

  String? _normalizeGender(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    final clean = raw.trim().toLowerCase();
    if (clean == 'male' || clean == 'laki-laki' || clean == 'l') {
      return 'Laki-laki';
    }
    if (clean == 'female' || clean == 'perempuan' || clean == 'p') {
      return 'Perempuan';
    }
    return raw;
  }

  @override
  void initState() {
    super.initState();
    final user = widget.authController?.currentUser;
    _nameController = TextEditingController(text: user?.nama ?? '');
    _ageController = TextEditingController(
      text: (user?.usia != null && user!.usia! > 0) ? user.usia.toString() : '',
    );
    _selectedGender = _normalizeGender(user?.gender);
    if (user != null && user.level != null && user.level!.isNotEmpty) {
      _selectedLevel = user.level!;
    }
    if (user != null) {
      _loadUserProfile();
    }
  }

  Future<void> _loadUserProfile() async {
    final user = widget.authController?.currentUser;
    if (user == null) return;

    try {
      final supabase = Supabase.instance.client;
      final playerRows = await supabase
          .from('tb_player')
          .select()
          .eq('user_id', user.userId)
          .order('player_id', ascending: false)
          .limit(1);

      if (playerRows.isNotEmpty && mounted) {
        final p = playerRows.first;
        setState(() {
          if (_nameController.text.trim().isEmpty && p['nama'] != null) {
            _nameController.text = p['nama'].toString();
          }
          if (_ageController.text.trim().isEmpty && p['usia'] != null) {
            _ageController.text = p['usia'].toString();
          }
          if (_selectedGender == null && p['gender'] != null) {
            _selectedGender = _normalizeGender(p['gender'].toString());
          }
          if (_selectedLevel == null && p['level'] != null) {
            _selectedLevel = p['level'].toString();
          }
        });
      }
    } catch (_) {}
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
      if (user != null && user.isVenueOwner) {
        throw Exception('Venue Owner tidak dapat bergabung sebagai peserta slot mabar.');
      }

      final isAlreadyJoined = (user != null && user.playerId != null && user.playerId! > 0)
          ? widget.session.registeredPlayers.any((p) => p.playerId == user.playerId || (p.userId != null && p.userId == user.userId))
          : false;

      if (isAlreadyJoined) {
        throw Exception('Kamu sudah terdaftar di sesi mabar ini!');
      }

      int playerIdToJoin;

      if (user != null) {
        // Logged in user - ensure we use the official user player_id
        final supabase = Supabase.instance.client;
        int? resolvedPlayerId = user.playerId;

        if (resolvedPlayerId == null || resolvedPlayerId <= 0) {
          final existingRows = await supabase
              .from('tb_player')
              .select('player_id')
              .eq('user_id', user.userId)
              .order('player_id', ascending: false)
              .limit(1);

          if (existingRows.isNotEmpty) {
            resolvedPlayerId = existingRows.first['player_id'] as int;
          }
        }

        final genderDb = _selectedGender == 'Perempuan' ? 'Female' : 'Male';
        final parsedAge = int.tryParse(_ageController.text.trim()) ?? user.usia ?? 25;

        if (resolvedPlayerId != null && resolvedPlayerId > 0) {
          playerIdToJoin = resolvedPlayerId;
          // Sync any updated fields to tb_player
          try {
            await supabase.from('tb_player').update({
              if (_nameController.text.trim().isNotEmpty) 'nama': _nameController.text.trim(),
              'gender': genderDb,
              'usia': parsedAge,
              if (_selectedLevel != null) 'level': _selectedLevel,
              if (user.foto != null) 'foto': user.foto,
              'updated_at': DateTime.now().toIso8601String(),
            }).eq('player_id', resolvedPlayerId);
          } catch (_) {}
        } else {
          // Create linked player for logged-in user
          final playerRes = await supabase.from('tb_player').insert({
            'user_id': user.userId,
            'nama': _nameController.text.trim().isNotEmpty ? _nameController.text.trim() : user.nama,
            'email': user.email,
            'gender': genderDb,
            'usia': parsedAge,
            'level': _selectedLevel ?? user.level ?? 'Intermediate',
            'foto': user.foto,
            'rating': 1200,
            'created_at': DateTime.now().toIso8601String(),
            'updated_at': DateTime.now().toIso8601String(),
          }).select('player_id').single();

          playerIdToJoin = playerRes['player_id'] is int
              ? playerRes['player_id'] as int
              : int.parse(playerRes['player_id'].toString());
        }
      } else {
        // Guest user - register player instantly
        final genderDb = _selectedGender == 'Laki-laki' ? 'Male' : 'Female';
        final parsedAge = int.parse(_ageController.text.trim());

        playerIdToJoin = await _sessionService.registerGuestPlayer(
          nama: _nameController.text.trim(),
          gender: genderDb,
          level: _selectedLevel ?? 'Beginner',
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
      final errorMsg = e.toString().replaceFirst('Exception: ', '');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal gabung sesi: $errorMsg'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }
  Widget _buildGuestDropdown({
    required String hint,
    required String? value,
    required Map<String, String> options,
    required ValueChanged<String> onChanged,
    required String errorMessage,
  }) {
    return FormField<String>(
      initialValue: value,
      validator: (selected) {
        final playerId =
            widget.authController?.currentUser?.playerId;

        if (playerId != null && playerId > 0) return null;

        if (selected == null || selected.isEmpty) {
          return errorMessage;
        }
        return null;
      },
      builder: (field) {
        return LayoutBuilder(
          builder: (context, constraints) {
            return PopupMenuButton<String>(
              enabled: !_isSubmitting,
              position: PopupMenuPosition.under,
              offset: const Offset(0, 4),
              tooltip: hint,
              color: Colors.white,
              constraints: BoxConstraints(
                minWidth: constraints.maxWidth,
                maxWidth: constraints.maxWidth,
              ),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              onSelected: (selected) {
                field.didChange(selected);
                onChanged(selected);
              },
              itemBuilder: (_) => options.entries.map((entry) {
                return PopupMenuItem<String>(
                  value: entry.key,
                  child: Text(
                    entry.value,
                    style: const TextStyle(fontSize: 13),
                  ),
                );
              }).toList(),
              child: InputDecorator(
                decoration: InputDecoration(
                  errorText: field.errorText,
                  filled: true,
                  fillColor: const Color(0xFFF8FAFC),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 12,
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(
                      color: Color(0xFFE2E8F0),
                    ),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(
                      color: Color(0xFFE2E8F0),
                    ),
                  ),
                  errorBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(
                      color: Colors.redAccent,
                    ),
                  ),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        options[field.value] ?? hint,
                        style: TextStyle(
                          fontSize: 13,
                          color: field.value == null
                              ? const Color(0xFF94A3B8)
                              : const Color(0xFF0F172A),
                        ),
                      ),
                    ),
                    const Icon(
                      Icons.keyboard_arrow_down_rounded,
                      size: 20,
                      color: Color(0xFF64748B),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
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
              if (widget.authController?.currentUser != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF0FDF4),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFBBF7D0)),
                  ),
                  child: const Row(
                    children: [
                      Icon(Icons.check_circle_rounded, size: 16, color: Color(0xFF16A34A)),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Data terisi otomatis dari profil akunmu.',
                          style: TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF15803D),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 16),

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
                        _buildGuestDropdown(
                          hint: 'Pilih gender',
                          value: _selectedGender,
                          options: const {
                            'Laki-laki': 'Laki-laki',
                            'Perempuan': 'Perempuan',
                          },
                          onChanged: (value) {
                            setState(() => _selectedGender = value);
                          },
                          errorMessage: 'Gender wajib dipilih',
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
                          validator: (value) {
                            final text = value?.trim() ?? '';

                            if (text.isEmpty) {
                              return 'Usia wajib diisi';
                            }

                            final usia = int.tryParse(text);
                            if (usia == null || usia < 10 || usia > 90) {
                              return 'Usia harus 10–90 tahun';
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
              _buildGuestDropdown(
                hint: 'Pilih level',
                value: _selectedLevel,
                options: const {
                  'Newbie': 'Newbie',
                  'Beginner': 'Beginner',
                  'Intermediate': 'Intermediate',
                  'Advanced': 'Advanced',
                },
                onChanged: (value) {
                  setState(() => _selectedLevel = value);
                },
                errorMessage: 'Level wajib dipilih',
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
