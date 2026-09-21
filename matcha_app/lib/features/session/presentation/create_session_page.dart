import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';

class CreateSessionPage extends StatefulWidget {
  const CreateSessionPage({super.key});

  @override
  State<CreateSessionPage> createState() => _CreateSessionPageState();
}

class _CreateSessionPageState extends State<CreateSessionPage> {
  final MockDataService _dataService = MockDataService();

  final _titleController = TextEditingController(text: 'Mabar Fun Tennis & Padel');
  String _selectedSport = 'padel';
  String _selectedVenue = 'SCBD Padel Club Arena';
  String _selectedFormat = 'Americano Double (2 vs 2)';
  final String _selectedCity = 'Jakarta Selatan';
  int _maxParticipants = 8;
  int _pricePerPerson = 100000;
  final String _selectedDate = 'Besok, 21 Sep 2026';
  final String _selectedTime = '19:00 - 21:00 WIB';

  @override
  void dispose() {
    _titleController.dispose();
    super.dispose();
  }

  void _submitCreateSession() {
    if (_titleController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Silakan masukkan judul sesi mabar'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final newSession = MatchaSession(
      id: 'ses_${DateTime.now().millisecondsSinceEpoch}',
      title: _titleController.text.trim(),
      sport: _selectedSport,
      venueName: _selectedVenue,
      location: _selectedCity,
      date: _selectedDate,
      time: _selectedTime,
      maxParticipants: _maxParticipants,
      participants: [_dataService.currentUser],
      status: 'upcoming',
      matchFormat: _selectedFormat,
      pricePerPerson: _pricePerPerson,
      hostName: _dataService.currentUser.name,
    );

    _dataService.createSession(newSession);

    Navigator.pop(context);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Sesi mabar "${newSession.title}" berhasil dibuat! 🎉'),
        backgroundColor: AppColors.matchaDark,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Buat Sesi Mabar Baru',
          style: AppTextStyles.h2.copyWith(fontSize: 16, color: context.txtPrimary),
        ),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // 1. Pilih Cabang Olahraga
            _buildLabel('1. Pilih Cabang Olahraga'),
            const SizedBox(height: 8),
            Row(
              children: [
                _buildSportOption('padel', '🏓 Padel'),
                const SizedBox(width: 10),
                _buildSportOption('tennis', '🎾 Tennis'),
                const SizedBox(width: 10),
                _buildSportOption('badminton', '🏸 Badminton'),
              ],
            ),

            const SizedBox(height: 18),

            // 2. Judul Sesi
            _buildLabel('2. Judul Sesi Mabar'),
            const SizedBox(height: 8),
            TextField(
              controller: _titleController,
              decoration: InputDecoration(
                filled: true,
                fillColor: context.surf,
                hintText: 'Contoh: Saturday Night Padel Rally',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: context.surfBorder),
                ),
              ),
            ),

            const SizedBox(height: 18),

            // 3. Format Pertandingan
            _buildLabel('3. Format Permainan'),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: context.surf,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: context.surfBorder),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _selectedFormat,
                  isExpanded: true,
                  dropdownColor: context.surf,
                  items: const [
                    DropdownMenuItem(
                      value: 'Americano Double (2 vs 2)',
                      child: Text('Americano Double (2 vs 2)'),
                    ),
                    DropdownMenuItem(
                      value: 'Americano Single (1 vs 1)',
                      child: Text('Americano Single (1 vs 1)'),
                    ),
                    DropdownMenuItem(
                      value: 'Team Americano',
                      child: Text('Team Americano (Fixed Teams)'),
                    ),
                  ],
                  onChanged: (val) {
                    if (val != null) setState(() => _selectedFormat = val);
                  },
                ),
              ),
            ),

            const SizedBox(height: 18),

            // 4. Pilih Venue & Lokasi
            _buildLabel('4. Venue / Lapangan'),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: context.surf,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: context.surfBorder),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _selectedVenue,
                  isExpanded: true,
                  dropdownColor: context.surf,
                  items: const [
                    DropdownMenuItem(
                      value: 'SCBD Padel Club Arena',
                      child: Text('SCBD Padel Club Arena (Jakarta Selatan)'),
                    ),
                    DropdownMenuItem(
                      value: 'Gelora Tennis Center',
                      child: Text('Gelora Tennis Center (Jakarta Pusat)'),
                    ),
                    DropdownMenuItem(
                      value: 'Gorilla Badminton Hall',
                      child: Text('Gorilla Badminton Hall (Bandung)'),
                    ),
                    DropdownMenuItem(
                      value: 'Sunset Padel Court Kemang',
                      child: Text('Sunset Padel Court Kemang (Jakarta Selatan)'),
                    ),
                  ],
                  onChanged: (val) {
                    if (val != null) setState(() => _selectedVenue = val);
                  },
                ),
              ),
            ),

            const SizedBox(height: 18),

            // 5. Kuota Pemain & Biaya Patungan
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildLabel('5. Kuota Pemain'),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: context.surf,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: context.surfBorder),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.remove_circle_outline, size: 20),
                              onPressed: () {
                                if (_maxParticipants > 4) {
                                  setState(() => _maxParticipants -= 2);
                                }
                              },
                            ),
                            Text(
                              '$_maxParticipants Orang',
                              style: const TextStyle(fontWeight: FontWeight.bold),
                            ),
                            IconButton(
                              icon: const Icon(Icons.add_circle_outline, size: 20),
                              onPressed: () {
                                if (_maxParticipants < 24) {
                                  setState(() => _maxParticipants += 2);
                                }
                              },
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildLabel('6. Biaya/Orang'),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: context.surf,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: context.surfBorder),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.remove_circle_outline, size: 20),
                              onPressed: () {
                                if (_pricePerPerson > 25000) {
                                  setState(() => _pricePerPerson -= 25000);
                                }
                              },
                            ),
                            Text(
                              '${(_pricePerPerson / 1000).toStringAsFixed(0)}k',
                              style: const TextStyle(fontWeight: FontWeight.bold),
                            ),
                            IconButton(
                              icon: const Icon(Icons.add_circle_outline, size: 20),
                              onPressed: () {
                                setState(() => _pricePerPerson += 25000);
                              },
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),

            const SizedBox(height: 28),

            // Submit Button
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: _submitCreateSession,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.matchaDark,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: const Text(
                  'Buat & Publikasikan Jadwal',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLabel(String text) {
    return Text(
      text,
      style: AppTextStyles.bodyMedium.copyWith(
        fontWeight: FontWeight.bold,
        color: context.txtPrimary,
      ),
    );
  }

  Widget _buildSportOption(String key, String label) {
    final isSelected = _selectedSport == key;
    return Expanded(
      child: GestureDetector(
        onTap: () => setState(() => _selectedSport = key),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: isSelected
                ? AppColors.matchaSoftLime
                : context.surf,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: isSelected ? const Color(0xFF063B00).withValues(alpha: 0.3) : context.surfBorder,
              width: 1,
            ),
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
              color: isSelected ? AppColors.matchaDark : context.txtSecondary,
            ),
          ),
        ),
      ),
    );
  }
}