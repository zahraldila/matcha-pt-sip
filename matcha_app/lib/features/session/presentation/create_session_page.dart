import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../drawing/presentation/drawing_result_page.dart';

class CreateSessionPage extends StatefulWidget {
  final VoidCallback? onGenerateDrawing;

  const CreateSessionPage({super.key, this.onGenerateDrawing});

  @override
  State<CreateSessionPage> createState() => _CreateSessionPageState();
}

class _CreateSessionPageState extends State<CreateSessionPage> {
  final _nameController = TextEditingController(text: 'Saturday Morning Session');
  String _selectedSport = 'Tennis';
  String _selectedMethod = 'Americano';
  String _selectedFormat = 'Doubles';
  DateTime _selectedDate = DateTime.now();
  TimeOfDay _selectedTime = const TimeOfDay(hour: 8, minute: 0);

  // Mock list of available players
  final List<Map<String, dynamic>> _availablePlayers = [
    {'id': 1, 'name': 'Aldi', 'club': 'Sportif Tennis Club', 'selected': true},
    {'id': 2, 'name': 'Budi', 'club': 'Smansa Tennis', 'selected': true},
    {'id': 3, 'name': 'Caca', 'club': 'Individual', 'selected': true},
    {'id': 4, 'name': 'Dina', 'club': 'Individual', 'selected': true},
    {'id': 5, 'name': 'Eka', 'club': 'Individual', 'selected': true},
    {'id': 6, 'name': 'Fajar', 'club': 'Individual', 'selected': true},
    {'id': 7, 'name': 'Gilang', 'club': 'Individual', 'selected': true},
    {'id': 8, 'name': 'Hadi', 'club': 'Individual', 'selected': true},
    {'id': 9, 'name': 'Indra', 'club': 'Individual', 'selected': false},
    {'id': 10, 'name': 'Joko', 'club': 'Individual', 'selected': false},
  ];

  // Mock list of available courts
  final List<Map<String, dynamic>> _availableCourts = [
    {'id': 1, 'name': 'SiJi Tennis Court 1', 'sport': 'Tennis', 'selected': true},
    {'id': 2, 'name': 'SiJi Tennis Court 2', 'sport': 'Tennis', 'selected': true},
    {'id': 3, 'name': 'Lapang GOR SESKOAD', 'sport': 'Tennis', 'selected': false},
    {'id': 4, 'name': 'Lapang Tennis Bonang', 'sport': 'Tennis', 'selected': false},
    {'id': 5, 'name': 'Lapang Puri Dago', 'sport': 'Tennis', 'selected': false},
  ];

  final List<String> _sports = ['Tennis', 'Padel', 'Badminton', 'Pickleball'];
  final List<String> _methods = ['Americano', 'Mexicano'];
  final List<String> _formats = ['Singles', 'Doubles'];

  int get _selectedPlayersCount =>
      _availablePlayers.where((p) => p['selected'] == true).length;

  int get _selectedCourtsCount =>
      _availableCourts.where((c) => c['selected'] == true).length;

  @override
  void dispose() {
    _nameController.dispose();
    super.dispose();
  }

  void _showPlayerSelectionSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: context.surf,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Container(
              height: MediaQuery.of(context).size.height * 0.7,
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Pilih Pemain Sesi', style: AppTextStyles.cardTitle.copyWith(color: context.txtPrimary)),
                      IconButton(
                        icon: Icon(Icons.close_rounded, color: context.txtSecondary),
                        onPressed: () => Navigator.pop(context),
                      ),
                    ],
                  ),
                  Text(
                    '$_selectedPlayersCount pemain dipilih',
                    style: AppTextStyles.caption.copyWith(color: context.brandColor),
                  ),
                  const SizedBox(height: 12),
                  Divider(color: context.surfBorder),
                  Expanded(
                    child: ListView.separated(
                      itemCount: _availablePlayers.length,
                      separatorBuilder: (context, index) => Divider(height: 1, color: context.surfBorder),
                      itemBuilder: (context, index) {
                        final player = _availablePlayers[index];
                        final isSelected = player['selected'] as bool;

                        return CheckboxListTile(
                          activeColor: context.brandColor,
                          checkColor: Colors.black,
                          title: Text(player['name'], style: AppTextStyles.body.copyWith(color: context.txtPrimary)),
                          subtitle: Text(player['club'], style: AppTextStyles.caption.copyWith(color: context.txtSecondary)),
                          value: isSelected,
                          onChanged: (val) {
                            setModalState(() {
                              player['selected'] = val ?? false;
                            });
                            setState(() {});
                          },
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    child: Text('Simpan Pilihan ($_selectedPlayersCount Pemain)'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  void _showCourtSelectionSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: context.surf,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Container(
              height: MediaQuery.of(context).size.height * 0.6,
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Pilih Lapangan', style: AppTextStyles.cardTitle.copyWith(color: context.txtPrimary)),
                      IconButton(
                        icon: Icon(Icons.close_rounded, color: context.txtSecondary),
                        onPressed: () => Navigator.pop(context),
                      ),
                    ],
                  ),
                  Text(
                    '$_selectedCourtsCount lapangan dipilih',
                    style: AppTextStyles.caption.copyWith(color: context.brandColor),
                  ),
                  const SizedBox(height: 12),
                  Divider(color: context.surfBorder),
                  Expanded(
                    child: ListView.separated(
                      itemCount: _availableCourts.length,
                      separatorBuilder: (context, index) => Divider(height: 1, color: context.surfBorder),
                      itemBuilder: (context, index) {
                        final court = _availableCourts[index];
                        final isSelected = court['selected'] as bool;

                        return CheckboxListTile(
                          activeColor: context.brandColor,
                          checkColor: Colors.black,
                          title: Text(court['name'], style: AppTextStyles.body.copyWith(color: context.txtPrimary)),
                          subtitle: Text('Sport: ${court['sport']}', style: AppTextStyles.caption.copyWith(color: context.txtSecondary)),
                          value: isSelected,
                          onChanged: (val) {
                            setModalState(() {
                              court['selected'] = val ?? false;
                            });
                            setState(() {});
                          },
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    child: Text('Simpan Pilihan ($_selectedCourtsCount Lapangan)'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text('Buat Sesi Permainan', style: TextStyle(color: context.txtPrimary)),
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, size: 18, color: context.txtPrimary),
          onPressed: () => Navigator.maybePop(context),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // 1. Nama Sesi
            _buildSectionLabel(context, 'Informasi Sesi'),
            const SizedBox(height: 8),
            TextFormField(
              controller: _nameController,
              style: AppTextStyles.body.copyWith(color: context.txtPrimary),
              decoration: InputDecoration(
                hintText: 'Misal: Saturday Morning Match',
                prefixIcon: Icon(Icons.edit_outlined, color: context.txtSecondary, size: 20),
              ),
            ),
            const SizedBox(height: 20),

            // 2. Pilih Sport
            _buildSectionLabel(context, 'Pilih Olahraga'),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _sports.map((sport) {
                final isSelected = _selectedSport == sport;
                return _buildSelectableChip(
                  context: context,
                  label: sport,
                  isSelected: isSelected,
                  icon: Icons.sports_tennis_rounded,
                  onTap: () => setState(() => _selectedSport = sport),
                );
              }).toList(),
            ),
            const SizedBox(height: 20),

            // 3. Drawing Method & Format
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildSectionLabel(context, 'Metode Drawing'),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        children: _methods.map((method) {
                          final isSelected = _selectedMethod == method;
                          return _buildSelectableChip(
                            context: context,
                            label: method,
                            isSelected: isSelected,
                            onTap: () => setState(() => _selectedMethod = method),
                          );
                        }).toList(),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildSectionLabel(context, 'Format'),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        children: _formats.map((format) {
                          final isSelected = _selectedFormat == format;
                          return _buildSelectableChip(
                            context: context,
                            label: format,
                            isSelected: isSelected,
                            onTap: () => setState(() => _selectedFormat = format),
                          );
                        }).toList(),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // 4. Tanggal & Waktu
            _buildSectionLabel(context, 'Jadwal Pelaksanaan'),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _buildPickerCard(
                    context: context,
                    icon: Icons.calendar_today_rounded,
                    title: 'Tanggal',
                    value: '${_selectedDate.day}/${_selectedDate.month}/${_selectedDate.year}',
                    onTap: () async {
                      final picked = await showDatePicker(
                        context: context,
                        initialDate: _selectedDate,
                        firstDate: DateTime.now(),
                        lastDate: DateTime.now().add(const Duration(days: 365)),
                      );
                      if (picked != null) setState(() => _selectedDate = picked);
                    },
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _buildPickerCard(
                    context: context,
                    icon: Icons.access_time_rounded,
                    title: 'Waktu Mulai',
                    value: _selectedTime.format(context),
                    onTap: () async {
                      final picked = await showTimePicker(
                        context: context,
                        initialTime: _selectedTime,
                      );
                      if (picked != null) setState(() => _selectedTime = picked);
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // 5. Pemilihan Pemain Card
            _buildSelectionSummaryCard(
              context: context,
              title: 'Daftar Peserta Pemain',
              countText: '$_selectedPlayersCount Pemain Dipilih',
              icon: Icons.people_alt_rounded,
              subtitle: 'Kapasitas ideal: 8 pemain (2 court)',
              onTap: _showPlayerSelectionSheet,
            ),
            const SizedBox(height: 14),

            // 6. Pemilihan Lapangan Card
            _buildSelectionSummaryCard(
              context: context,
              title: 'Lapangan yang Digunakan',
              countText: '$_selectedCourtsCount Lapangan Dipilih',
              icon: Icons.stadium_rounded,
              subtitle: 'SiJi Tennis Court 1 & 2',
              onTap: _showCourtSelectionSheet,
            ),
            const SizedBox(height: 32),

            // 7. Tombol Action
            ElevatedButton(
              onPressed: () {
                if (widget.onGenerateDrawing != null) {
                  widget.onGenerateDrawing!();
                } else {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => DrawingResultPage(
                        sessionName: _nameController.text.isNotEmpty
                            ? _nameController.text
                            : 'Saturday Morning',
                        sportName: _selectedSport,
                        drawingMethod: _selectedMethod,
                      ),
                    ),
                  );
                }
              },
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text('NEXT: GENERATE DRAWING'),
                  SizedBox(width: 8),
                  Icon(Icons.arrow_forward_rounded, size: 18),
                ],
              ),
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionLabel(BuildContext context, String label) {
    return Text(
      label,
      style: AppTextStyles.caption.copyWith(
        color: context.txtPrimary,
        fontWeight: FontWeight.w600,
      ),
    );
  }

  Widget _buildSelectableChip({
    required BuildContext context,
    required String label,
    required bool isSelected,
    IconData? icon,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? context.brandColor.withValues(alpha: 0.15) : context.surf,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: isSelected ? context.brandColor : context.surfBorder,
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(icon, size: 16, color: isSelected ? context.brandColor : context.txtSecondary),
              const SizedBox(width: 6),
            ],
            Text(
              label,
              style: AppTextStyles.body.copyWith(
                fontSize: 13,
                fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
                color: isSelected ? context.brandColor : context.txtSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPickerCard({
    required BuildContext context,
    required IconData icon,
    required String title,
    required String value,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: context.surfBorder),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 16, color: context.brandColor),
                const SizedBox(width: 6),
                Text(title, style: AppTextStyles.caption.copyWith(color: context.txtSecondary)),
              ],
            ),
            const SizedBox(height: 6),
            Text(value, style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w600, color: context.txtPrimary)),
          ],
        ),
      ),
    );
  }

  Widget _buildSelectionSummaryCard({
    required BuildContext context,
    required String title,
    required String countText,
    required IconData icon,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: context.surfBorder),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: context.surfSec,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: context.brandColor, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: AppTextStyles.cardTitle.copyWith(fontSize: 14, color: context.txtPrimary)),
                  const SizedBox(height: 2),
                  Text(subtitle, style: AppTextStyles.caption.copyWith(color: context.txtSecondary)),
                  const SizedBox(height: 4),
                  Text(
                    countText,
                    style: AppTextStyles.caption.copyWith(
                      color: context.brandColor,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.arrow_forward_ios_rounded, size: 14, color: context.txtSecondary),
          ],
        ),
      ),
    );
  }
}
