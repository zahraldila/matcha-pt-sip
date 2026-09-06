import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import 'controllers/session_controller.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import '../../auth/domain/models/user_model.dart';

class CreateSessionPage extends StatefulWidget {
  final Function()? onGenerateDrawing;
  final UserModel currentUser;

  const CreateSessionPage({
    super.key,
    required this.currentUser,
    this.onGenerateDrawing,
  });

  @override
  State<CreateSessionPage> createState() => _CreateSessionPageState();
}

class _CreateSessionPageState extends State<CreateSessionPage> {
  late final SessionController _sessionController;

  final TextEditingController _nameController = TextEditingController(
    text: 'Saturday Morning Session',
  );

  String _selectedSport = '';
  int? _selectedSportId;

  String _selectedMethod = 'Americano';
  String _selectedFormat = 'Doubles';

  DateTime _selectedDate = DateTime.now();
  TimeOfDay _selectedTime = const TimeOfDay(hour: 8, minute: 0);

  final Set<int> _selectedPlayerIds = {};
  final Set<int> _selectedCourtIds = {};

  bool _isCreating = false;

  @override
  void initState() {
    super.initState();

    _sessionController = SessionController();

    _sessionController.addListener(_onControllerChanged);

    _loadInitialData();
  }

  Future<void> _loadInitialData() async {
    await _sessionController.loadSports();
    await _sessionController.loadPlayers();

    if (!mounted) return;

    if (_sessionController.sports.isEmpty) {
      return;
    }

    // Prioritaskan Tennis agar default UI tetap mirip prototype.
    final tennisSport = _sessionController.sports.firstWhere(
      (sport) =>
          (sport['nama_sport']?.toString().toLowerCase() ?? '') == 'tennis',
      orElse: () => _sessionController.sports.first,
    );

    final sportId = tennisSport['sport_id'] as int;
    final sportName = tennisSport['nama_sport']?.toString() ?? '';

    setState(() {
      _selectedSportId = sportId;
      _selectedSport = sportName;
    });

    await _sessionController.loadCourts(sportId: sportId);

    if (!mounted) return;

    _setDefaultSelections();
  }

  void _onControllerChanged() {
    if (!mounted) return;

    setState(() {});
  }

  void _setDefaultSelections() {
    // Mempertahankan perilaku prototype:
    // default memilih maksimal 8 pemain dan 2 court.
    _selectedPlayerIds
      ..clear()
      ..addAll(
        _sessionController.players
            .take(8)
            .map((player) => player['player_id'] as int),
      );

    _selectedCourtIds
      ..clear()
      ..addAll(
        _sessionController.courts
            .take(2)
            .map((court) => court['court_id'] as int),
      );

    setState(() {});
  }

  @override
  void dispose() {
    _nameController.dispose();

    _sessionController.removeListener(_onControllerChanged);
    _sessionController.dispose();

    super.dispose();
  }

  Future<void> _changeSport(
    Map<String, dynamic> sport,
  ) async {
    final sportId = sport['sport_id'] as int;
    final sportName = sport['nama_sport']?.toString() ?? '';

    setState(() {
      _selectedSportId = sportId;
      _selectedSport = sportName;

      // Court bergantung pada sport.
      _selectedCourtIds.clear();
    });

    await _sessionController.loadCourts(
      sportId: sportId,
    );

    if (!mounted) return;

    // Setelah court baru berhasil di-load,
    // pertahankan behavior prototype dengan memilih maksimal 2.
    setState(() {
      _selectedCourtIds.addAll(
        _sessionController.courts
            .take(2)
            .map((court) => court['court_id'] as int),
      );
    });
  }

  Future<void> _pickDate() async {
    final pickedDate = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(
        const Duration(days: 365),
      ),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: Theme.of(context).colorScheme.copyWith(
              primary: context.brandColor,
            ),
          ),
          child: child!,
        );
      },
    );

    if (pickedDate != null && mounted) {
      setState(() {
        _selectedDate = pickedDate;
      });
    }
  }

  Future<void> _pickTime() async {
    final pickedTime = await showTimePicker(
      context: context,
      initialTime: _selectedTime,
    );

    if (pickedTime != null && mounted) {
      setState(() {
        _selectedTime = pickedTime;
      });
    }
  }

  String _formatDate() {
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'Mei',
      'Jun',
      'Jul',
      'Agu',
      'Sep',
      'Okt',
      'Nov',
      'Des',
    ];

    return '${_selectedDate.day} ${months[_selectedDate.month - 1]} '
        '${_selectedDate.year}';
  }

  String _formatTime() {
    final hour = _selectedTime.hour.toString().padLeft(2, '0');
    final minute = _selectedTime.minute.toString().padLeft(2, '0');

    return '$hour:$minute';
  }

  DateTime _getSessionDateTime() {
    return DateTime(
      _selectedDate.year,
      _selectedDate.month,
      _selectedDate.day,
      _selectedTime.hour,
      _selectedTime.minute,
    );
  }

  Future<void> _generateDrawing() async {
    FocusScope.of(context).unfocus();

    if (_isCreating) return;

    if (_selectedSportId == null) {
      _showError('Silakan pilih cabang olahraga.');
      return;
    }

    if (_nameController.text.trim().isEmpty) {
      _showError('Nama session tidak boleh kosong.');
      return;
    }

    if (_selectedPlayerIds.isEmpty) {
      _showError('Silakan pilih minimal satu pemain.');
      return;
    }

    if (_selectedCourtIds.isEmpty) {
      _showError('Silakan pilih minimal satu lapangan.');
      return;
    }

    final sessionDateTime = _getSessionDateTime();

    if (sessionDateTime.isBefore(DateTime.now())) {
      _showError('Waktu session tidak boleh berada di masa lalu.');
      return;
    }

    setState(() {
      _isCreating = true;
    });

    try {
      // Mapping UI -> nilai yang sesuai CHECK constraint database.
      final jenisPermainan =
          _selectedFormat == 'Singles' ? 'Single' : 'Double';

      final sessionId = await _sessionController.createSession(
        currentUser: widget.currentUser,
        sportId: _selectedSportId!,
        namaSession: _nameController.text.trim(),
        drawingMethod: _selectedMethod,
        statusSession: 'Upcoming',
        waktuSession: sessionDateTime,
        jenisPermainan: jenisPermainan,
        playerIds: _selectedPlayerIds.toList(),
        courtIds: _selectedCourtIds.toList(),
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Session berhasil dibuat. ID: $sessionId',
          ),
        ),
      );

      if (widget.onGenerateDrawing != null) {
        widget.onGenerateDrawing!();
        return;
      }

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (context) => DrawingResultPage(
            sessionName: _nameController.text.trim(),
            sportName: _selectedSport,
            drawingMethod: _selectedMethod,
            jenisPermainan: _selectedFormat == 'Singles' ? 'Single' : 'Double',
            waktuSession: sessionDateTime,
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;

      _showError(
        e.toString().replaceFirst('Exception: ', ''),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isCreating = false;
        });
      }
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  Future<void> _showSportPicker() async {
    if (_sessionController.sports.isEmpty) {
      _showError('Data cabang olahraga belum tersedia.');
      return;
    }

    await showModalBottomSheet(
      context: context,
      backgroundColor: context.surf,
      showDragHandle: true,
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Pilih Cabang Olahraga',
                  style: AppTextStyles.pageTitle.copyWith(
                    fontSize: 20,
                    color: context.txtPrimary,
                  ),
                ),
                const SizedBox(height: 16),
                ..._sessionController.sports.map(
                  (sport) {
                    final sportId = sport['sport_id'] as int;
                    final sportName =
                        sport['nama_sport']?.toString() ?? '';

                    final selected =
                        sportId == _selectedSportId;

                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        sportName,
                        style: TextStyle(
                          color: context.txtPrimary,
                          fontWeight: selected
                              ? FontWeight.bold
                              : FontWeight.normal,
                        ),
                      ),
                      trailing: selected
                          ? Icon(
                              Icons.check_rounded,
                              color: context.brandColor,
                            )
                          : null,
                      onTap: () async {
                        Navigator.pop(context);
                        await _changeSport(sport);
                      },
                    );
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _showPlayerPicker() async {
    if (_sessionController.players.isEmpty) {
      _showError('Belum ada pemain aktif.');
      return;
    }

    final tempSelected = Set<int>.from(
      _selectedPlayerIds,
    );

    await showModalBottomSheet(
      context: context,
      backgroundColor: context.surf,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return SafeArea(
              child: SizedBox(
                height: MediaQuery.of(context).size.height * 0.75,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(
                    20,
                    8,
                    20,
                    20,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment:
                            MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Pilih Pemain',
                            style: AppTextStyles.pageTitle.copyWith(
                              fontSize: 20,
                              color: context.txtPrimary,
                            ),
                          ),
                          Text(
                            '${tempSelected.length} dipilih',
                            style: TextStyle(
                              color: context.brandColor,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Expanded(
                        child: ListView.separated(
                          itemCount:
                              _sessionController.players.length,
                          separatorBuilder: (_, __) =>
                              const Divider(height: 1),
                          itemBuilder: (context, index) {
                            final player =
                                _sessionController.players[index];

                            final playerId =
                                player['player_id'] as int;

                            final playerName =
                                player['nama_player']
                                    ?.toString() ??
                                '';

                            final status =
                                player['status_member']
                                    ?.toString() ??
                                '';

                            final selected =
                                tempSelected.contains(playerId);

                            return ListTile(
                              contentPadding:
                                  const EdgeInsets.symmetric(
                                vertical: 4,
                              ),
                              leading: CircleAvatar(
                                backgroundColor:
                                    context.surfSec,
                                child: Icon(
                                  Icons.person_rounded,
                                  color: context.txtSecondary,
                                ),
                              ),
                              title: Text(
                                playerName,
                                style: TextStyle(
                                  color: context.txtPrimary,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              subtitle: Text(
                                status,
                                style: TextStyle(
                                  color: context.txtSecondary,
                                  fontSize: 12,
                                ),
                              ),
                              trailing: Checkbox(
                                value: selected,
                                activeColor:
                                    context.brandColor,
                                onChanged: (value) {
                                  setModalState(() {
                                    if (value == true) {
                                      tempSelected.add(
                                        playerId,
                                      );
                                    } else {
                                      tempSelected.remove(
                                        playerId,
                                      );
                                    }
                                  });
                                },
                              ),
                              onTap: () {
                                setModalState(() {
                                  if (selected) {
                                    tempSelected.remove(
                                      playerId,
                                    );
                                  } else {
                                    tempSelected.add(
                                      playerId,
                                    );
                                  }
                                });
                              },
                            );
                          },
                        ),
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor:
                                context.brandColor,
                            foregroundColor: Colors.black,
                            shape: RoundedRectangleBorder(
                              borderRadius:
                                  BorderRadius.circular(14),
                            ),
                          ),
                          onPressed: () {
                            setState(() {
                              _selectedPlayerIds
                                ..clear()
                                ..addAll(tempSelected);
                            });

                            Navigator.pop(context);
                          },
                          child: const Text(
                            'Simpan Pilihan',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _showCourtPicker() async {
    if (_selectedSportId == null) {
      _showError(
        'Pilih cabang olahraga terlebih dahulu.',
      );
      return;
    }

    if (_sessionController.courts.isEmpty) {
      _showError(
        'Belum ada lapangan tersedia untuk olahraga ini.',
      );
      return;
    }

    final tempSelected = Set<int>.from(
      _selectedCourtIds,
    );

    await showModalBottomSheet(
      context: context,
      backgroundColor: context.surf,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return SafeArea(
              child: SizedBox(
                height: MediaQuery.of(context).size.height * 0.65,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(
                    20,
                    8,
                    20,
                    20,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment:
                            MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Pilih Lapangan',
                            style: AppTextStyles.pageTitle.copyWith(
                              fontSize: 20,
                              color: context.txtPrimary,
                            ),
                          ),
                          Text(
                            '${tempSelected.length} dipilih',
                            style: TextStyle(
                              color: context.brandColor,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Expanded(
                        child: ListView.separated(
                          itemCount:
                              _sessionController.courts.length,
                          separatorBuilder: (_, __) =>
                              const Divider(height: 1),
                          itemBuilder: (context, index) {
                            final court =
                                _sessionController.courts[index];

                            final courtId =
                                court['court_id'] as int;

                            final courtName =
                                court['nama_court']
                                    ?.toString() ??
                                '';

                            final location =
                                court['lokasi']?.toString() ??
                                '';

                            final selected =
                                tempSelected.contains(courtId);

                            return ListTile(
                              contentPadding:
                                  const EdgeInsets.symmetric(
                                vertical: 4,
                              ),
                              leading: CircleAvatar(
                                backgroundColor:
                                    context.surfSec,
                                child: Icon(
                                  Icons.sports_tennis_rounded,
                                  color: context.txtSecondary,
                                ),
                              ),
                              title: Text(
                                courtName,
                                style: TextStyle(
                                  color: context.txtPrimary,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              subtitle: Text(
                                location.isEmpty
                                    ? 'Lokasi tidak tersedia'
                                    : location,
                                style: TextStyle(
                                  color: context.txtSecondary,
                                  fontSize: 12,
                                ),
                              ),
                              trailing: Checkbox(
                                value: selected,
                                activeColor:
                                    context.brandColor,
                                onChanged: (value) {
                                  setModalState(() {
                                    if (value == true) {
                                      tempSelected.add(
                                        courtId,
                                      );
                                    } else {
                                      tempSelected.remove(
                                        courtId,
                                      );
                                    }
                                  });
                                },
                              ),
                              onTap: () {
                                setModalState(() {
                                  if (selected) {
                                    tempSelected.remove(
                                      courtId,
                                    );
                                  } else {
                                    tempSelected.add(
                                      courtId,
                                    );
                                  }
                                });
                              },
                            );
                          },
                        ),
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor:
                                context.brandColor,
                            foregroundColor: Colors.black,
                            shape: RoundedRectangleBorder(
                              borderRadius:
                                  BorderRadius.circular(14),
                            ),
                          ),
                          onPressed: () {
                            setState(() {
                              _selectedCourtIds
                                ..clear()
                                ..addAll(tempSelected);
                            });

                            Navigator.pop(context);
                          },
                          child: const Text(
                            'Simpan Pilihan',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildSelectionCard({
    required BuildContext context,
    required IconData icon,
    required String title,
    required String value,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: context.surfBorder,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: context.surfSec,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                icon,
                size: 20,
                color: context.brandColor,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment:
                    CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: context.txtSecondary,
                      fontSize: 11,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    value,
                    style: TextStyle(
                      color: context.txtPrimary,
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              color: context.txtSecondary,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSportChips() {
    if (_sessionController.sports.isEmpty) {
      return Text(
        _sessionController.isLoading
            ? 'Memuat cabang olahraga...'
            : 'Tidak ada cabang olahraga aktif.',
        style: TextStyle(
          color: context.txtSecondary,
          fontSize: 13,
        ),
      );
    }

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: _sessionController.sports.map(
        (sport) {
          final sportId = sport['sport_id'] as int;
          final sportName =
              sport['nama_sport']?.toString() ?? '';

          final selected =
              sportId == _selectedSportId;

          return ChoiceChip(
            label: Text(sportName),
            selected: selected,
            selectedColor: context.brandColor,
            backgroundColor: context.surf,
            labelStyle: TextStyle(
              color: selected
                  ? Colors.black
                  : context.txtSecondary,
              fontWeight: selected
                  ? FontWeight.bold
                  : FontWeight.normal,
            ),
            side: BorderSide(
              color: selected
                  ? context.brandColor
                  : context.surfBorder,
            ),
            onSelected: (_) {
              _changeSport(sport);
            },
          );
        },
      ).toList(),
    );
  }

  Widget _buildMethodChips() {
    const methods = [
      'Americano',
      'Mexicano',
    ];

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: methods.map(
        (method) {
          final selected =
              _selectedMethod == method;

          return ChoiceChip(
            label: Text(method),
            selected: selected,
            selectedColor: context.brandColor,
            backgroundColor: context.surf,
            labelStyle: TextStyle(
              color: selected
                  ? Colors.black
                  : context.txtSecondary,
              fontWeight: selected
                  ? FontWeight.bold
                  : FontWeight.normal,
            ),
            side: BorderSide(
              color: selected
                  ? context.brandColor
                  : context.surfBorder,
            ),
            onSelected: (_) {
              setState(() {
                _selectedMethod = method;
              });
            },
          );
        },
      ).toList(),
    );
  }

  Widget _buildFormatChips() {
    const formats = [
      'Singles',
      'Doubles',
    ];

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: formats.map(
        (format) {
          final selected =
              _selectedFormat == format;

          return ChoiceChip(
            label: Text(format),
            selected: selected,
            selectedColor: context.brandColor,
            backgroundColor: context.surf,
            labelStyle: TextStyle(
              color: selected
                  ? Colors.black
                  : context.txtSecondary,
              fontWeight: selected
                  ? FontWeight.bold
                  : FontWeight.normal,
            ),
            side: BorderSide(
              color: selected
                  ? context.brandColor
                  : context.surfBorder,
            ),
            onSelected: (_) {
              setState(() {
                _selectedFormat = format;
              });
            },
          );
        },
      ).toList(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final selectedPlayerCount =
        _selectedPlayerIds.length;

    final selectedCourtCount =
        _selectedCourtIds.length;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        backgroundColor: context.bg,
        elevation: 0,
        title: Text(
          'Buat Session',
          style: AppTextStyles.pageTitle.copyWith(
            fontSize: 20,
            color: context.txtPrimary,
          ),
        ),
        iconTheme: IconThemeData(
          color: context.txtPrimary,
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(
            20,
            8,
            20,
            120,
          ),
          child: Column(
            crossAxisAlignment:
                CrossAxisAlignment.start,
            children: [
              Text(
                'Nama Session',
                style: AppTextStyles.cardTitle.copyWith(
                  color: context.txtPrimary,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _nameController,
                style: TextStyle(
                  color: context.txtPrimary,
                ),
                decoration: InputDecoration(
                  hintText: 'Contoh: Saturday Morning',
                  hintStyle: TextStyle(
                    color: context.txtSecondary,
                  ),
                  filled: true,
                  fillColor: context.surf,
                  border: OutlineInputBorder(
                    borderRadius:
                        BorderRadius.circular(14),
                    borderSide: BorderSide(
                      color: context.surfBorder,
                    ),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius:
                        BorderRadius.circular(14),
                    borderSide: BorderSide(
                      color: context.surfBorder,
                    ),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius:
                        BorderRadius.circular(14),
                    borderSide: BorderSide(
                      color: context.brandColor,
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 24),

              Text(
                'Cabang Olahraga',
                style: AppTextStyles.cardTitle.copyWith(
                  color: context.txtPrimary,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 10),

              _buildSportChips(),

              const SizedBox(height: 24),

              Text(
                'Drawing Method',
                style: AppTextStyles.cardTitle.copyWith(
                  color: context.txtPrimary,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 10),

              _buildMethodChips(),

              const SizedBox(height: 24),

              Text(
                'Format Permainan',
                style: AppTextStyles.cardTitle.copyWith(
                  color: context.txtPrimary,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 10),

              _buildFormatChips(),

              const SizedBox(height: 24),

              Text(
                'Jadwal Session',
                style: AppTextStyles.cardTitle.copyWith(
                  color: context.txtPrimary,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 10),

              Row(
                children: [
                  Expanded(
                    child: _buildSelectionCard(
                      context: context,
                      icon: Icons.calendar_today_rounded,
                      title: 'Tanggal',
                      value: _formatDate(),
                      onTap: _pickDate,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _buildSelectionCard(
                      context: context,
                      icon: Icons.access_time_rounded,
                      title: 'Waktu',
                      value: _formatTime(),
                      onTap: _pickTime,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),

              Text(
                'Pemain',
                style: AppTextStyles.cardTitle.copyWith(
                  color: context.txtPrimary,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 10),

              _buildSelectionCard(
                context: context,
                icon: Icons.people_alt_rounded,
                title: 'Pemain Session',
                value: selectedPlayerCount == 0
                    ? 'Pilih pemain'
                    : '$selectedPlayerCount pemain dipilih',
                onTap: _showPlayerPicker,
              ),

              const SizedBox(height: 24),

              Text(
                'Lapangan',
                style: AppTextStyles.cardTitle.copyWith(
                  color: context.txtPrimary,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 10),

              _buildSelectionCard(
                context: context,
                icon: Icons.sports_tennis_rounded,
                title: 'Lapangan Session',
                value: selectedCourtCount == 0
                    ? 'Pilih lapangan'
                    : '$selectedCourtCount lapangan dipilih',
                onTap: _showCourtPicker,
              ),

              const SizedBox(height: 24),

              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: context.surf,
                  borderRadius:
                      BorderRadius.circular(16),
                  border: Border.all(
                    color: context.surfBorder,
                  ),
                ),
                child: Column(
                  crossAxisAlignment:
                      CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Ringkasan',
                      style:
                          AppTextStyles.cardTitle.copyWith(
                        color: context.txtPrimary,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 12),
                    _buildSummaryRow(
                      context,
                      'Session',
                      _nameController.text.isEmpty
                          ? '-'
                          : _nameController.text,
                    ),
                    _buildSummaryRow(
                      context,
                      'Sport',
                      _selectedSport.isEmpty
                          ? '-'
                          : _selectedSport,
                    ),
                    _buildSummaryRow(
                      context,
                      'Method',
                      _selectedMethod,
                    ),
                    _buildSummaryRow(
                      context,
                      'Format',
                      _selectedFormat,
                    ),
                    _buildSummaryRow(
                      context,
                      'Jadwal',
                      '${_formatDate()} · ${_formatTime()}',
                    ),
                    _buildSummaryRow(
                      context,
                      'Pemain',
                      '$selectedPlayerCount pemain',
                    ),
                    _buildSummaryRow(
                      context,
                      'Lapangan',
                      '$selectedCourtCount lapangan',
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),

      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            20,
            10,
            20,
            16,
          ),
          child: SizedBox(
            height: 54,
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor:
                    context.brandColor,
                foregroundColor: Colors.black,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius:
                      BorderRadius.circular(15),
                ),
              ),
              onPressed:
                  _isCreating ? null : _generateDrawing,
              child: _isCreating
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child:
                          CircularProgressIndicator(
                        strokeWidth: 2.5,
                      ),
                    )
                  : const Text(
                      'NEXT: GENERATE DRAWING',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        letterSpacing: 0.3,
                      ),
                    ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildSummaryRow(
    BuildContext context,
    String label,
    String value,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment:
            CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 85,
            child: Text(
              label,
              style: TextStyle(
                color: context.txtSecondary,
                fontSize: 12,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                color: context.txtPrimary,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}