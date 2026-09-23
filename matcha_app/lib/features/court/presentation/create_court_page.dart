import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../data/venue_service.dart';
import '../domain/venue_model.dart';

class CreateCourtPage extends StatefulWidget {
  final VenueModel venue;
  final AuthController? authController;

  const CreateCourtPage({
    super.key,
    required this.venue,
    this.authController,
  });

  @override
  State<CreateCourtPage> createState() => _CreateCourtPageState();
}

class _CourtFormItem {
  final TextEditingController nameController;
  final TextEditingController priceController;
  int sportId; // 1: Padel, 2: Tennis
  String arenaType; // 'Indoor', 'Outdoor'

  _CourtFormItem({
    required String initialName,
    this.sportId = 1,
    this.arenaType = 'Indoor',
    String initialPrice = '150000',
  })  : nameController = TextEditingController(text: initialName),
        priceController = TextEditingController(text: initialPrice);

  void dispose() {
    nameController.dispose();
    priceController.dispose();
  }
}

class _CreateCourtPageState extends State<CreateCourtPage> {
  final VenueService _venueService = VenueService();
  final _formKey = GlobalKey<FormState>();

  final List<_CourtFormItem> _courtItems = [];
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    // Default 1 court
    final nextNumber = widget.venue.courts.length + 1;
    _courtItems.add(_CourtFormItem(
      initialName: 'Court $nextNumber',
      sportId: widget.venue.sportName.toLowerCase().contains('tennis') ? 2 : 1,
    ));
  }

  @override
  void dispose() {
    for (final item in _courtItems) {
      item.dispose();
    }
    super.dispose();
  }

  void _addCourtItem() {
    setState(() {
      final nextNumber = widget.venue.courts.length + _courtItems.length + 1;
      _courtItems.add(_CourtFormItem(
        initialName: 'Court $nextNumber',
        sportId: _courtItems.isNotEmpty ? _courtItems.last.sportId : 1,
        arenaType: _courtItems.isNotEmpty ? _courtItems.last.arenaType : 'Indoor',
      ));
    });
  }

  void _removeCourtItem(int index) {
    if (_courtItems.length <= 1) return;
    setState(() {
      final removed = _courtItems.removeAt(index);
      removed.dispose();
    });
  }

  Future<void> _submitCourts() async {
    if (!_formKey.currentState!.validate()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Mohon lengkapi data lapangan dengan benar.'),
          backgroundColor: Colors.redAccent,
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      final List<Map<String, dynamic>> courtsPayload = _courtItems.map((c) {
        final priceRaw = c.priceController.text.replaceAll(RegExp(r'[^0-9]'), '');
        final price = double.tryParse(priceRaw) ?? 0.0;
        return {
          'nama_court': c.nameController.text.trim(),
          'sport_id': c.sportId,
          'tipe_court': c.arenaType,
          'harga_per_jam': price,
        };
      }).toList();

      await _venueService.addCourtsToVenue(
        venueId: widget.venue.venueId,
        courts: courtsPayload,
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${_courtItems.length} lapangan berhasil ditambahkan ke ${widget.venue.namaVenue}! 🎉'),
          backgroundColor: AppColors.matchaDark,
        ),
      );

      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal menyimpan lapangan: $e'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final totalCount = widget.venue.courts.length + _courtItems.length;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 1,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: const Color(0xFFF1F5F9),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.arrow_back_ios_new_rounded, size: 16, color: Color(0xFF0F172A)),
          ),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Daftarkan Lapangan',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
        ),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header Card
              _buildHeaderCard(totalCount),

              const SizedBox(height: 18),

              // Dynamic List of Courts
              ListView.separated(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: _courtItems.length,
                separatorBuilder: (_, i) => const SizedBox(height: 14),
                itemBuilder: (context, index) {
                  return _buildCourtCard(index, _courtItems[index]);
                },
              ),

              const SizedBox(height: 14),

              // Button Tambah Lapangan Lain
              InkWell(
                onTap: _addCourtItem,
                borderRadius: BorderRadius.circular(16),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFF93C5FD), style: BorderStyle.solid),
                  ),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.add_rounded, size: 18, color: Color(0xFF2563EB)),
                      SizedBox(width: 6),
                      Text(
                        '+ Tambah Lapangan Lain',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF2563EB)),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 24),

              // Action Buttons
              Row(
                children: [
                  Expanded(
                    flex: 2,
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(context),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF475569),
                        side: const BorderSide(color: Color(0xFFCBD5E1)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      child: const Text('Lewati', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 4,
                    child: ElevatedButton.icon(
                      onPressed: _isSaving ? null : _submitCourts,
                      icon: _isSaving
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Icon(Icons.check_circle_outline_rounded, size: 18, color: Color(0xFFA8E63A)),
                      label: Text(
                        _isSaving ? 'Menyimpan...' : 'Simpan Semua Lapangan (${_courtItems.length})',
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.matchaDark,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        padding: const EdgeInsets.symmetric(vertical: 14),
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

  Widget _buildHeaderCard(int totalCount) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: AppColors.matchaSoftLime,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Text(
                      'VENUE OWNER PORTAL',
                      style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: AppColors.matchaDark),
                    ),
                  ),
                  const SizedBox(width: 6),
                  const Text('•', style: TextStyle(color: Color(0xFF94A3B8))),
                  const SizedBox(width: 6),
                  const Text('Konfigurasi Lapangan', style: TextStyle(fontSize: 10, color: Color(0xFF64748B), fontWeight: FontWeight.bold)),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: AppColors.matchaSoftLime,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.sports_tennis_rounded, size: 12, color: AppColors.matchaDark),
                    const SizedBox(width: 4),
                    Text(
                      'Total $totalCount Lapangan',
                      style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: AppColors.matchaDark),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          const Text(
            'Daftarkan Lapangan / Court',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
          ),
          const SizedBox(height: 4),
          RichText(
            text: TextSpan(
              text: 'Lengkapi rincian lapangan untuk venue ',
              style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
              children: [
                TextSpan(
                  text: widget.venue.namaVenue,
                  style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                const TextSpan(text: '.'),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCourtCard(int index, _CourtFormItem item) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Row Header: Badge number + Delete Trash icon
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    width: 22,
                    height: 22,
                    decoration: BoxDecoration(
                      color: AppColors.matchaDark,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      '${index + 1}',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'LAPANGAN #${index + 1}',
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), letterSpacing: 0.5),
                  ),
                ],
              ),
              if (_courtItems.length > 1)
                IconButton(
                  onPressed: () => _removeCourtItem(index),
                  icon: const Icon(Icons.delete_outline_rounded, size: 18, color: Colors.redAccent),
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(),
                ),
            ],
          ),

          const SizedBox(height: 14),

          // Nama Court
          _buildFieldLabel('Nama Court / Lapangan', isRequired: true),
          const SizedBox(height: 6),
          TextFormField(
            controller: item.nameController,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            decoration: _inputDecoration('Contoh: Court 1'),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Nama court wajib diisi' : null,
          ),

          const SizedBox(height: 12),

          // Sport and Arena Type Row
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildFieldLabel('Cabang Olahraga'),
                    const SizedBox(height: 6),
                    DropdownButtonFormField<int>(
                      initialValue: item.sportId,
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                      decoration: _inputDecoration(''),
                      items: const [
                        DropdownMenuItem(value: 1, child: Text('Padel')),
                        DropdownMenuItem(value: 2, child: Text('Tennis')),
                      ],
                      onChanged: (v) {
                        if (v != null) setState(() => item.sportId = v);
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildFieldLabel('Tipe Arena'),
                    const SizedBox(height: 6),
                    DropdownButtonFormField<String>(
                      initialValue: item.arenaType,
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                      decoration: _inputDecoration(''),
                      items: const [
                        DropdownMenuItem(value: 'Indoor', child: Text('Indoor')),
                        DropdownMenuItem(value: 'Outdoor', child: Text('Outdoor')),
                      ],
                      onChanged: (v) {
                        if (v != null) setState(() => item.arenaType = v);
                      },
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          // Harga / Jam
          _buildFieldLabel('Harga / Jam (Rp)', isRequired: true),
          const SizedBox(height: 6),
          TextFormField(
            controller: item.priceController,
            keyboardType: TextInputType.number,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            decoration: _inputDecoration('Contoh: 150000', prefix: 'Rp '),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Harga wajib diisi' : null,
          ),
        ],
      ),
    );
  }

  Widget _buildFieldLabel(String label, {bool isRequired = false}) {
    return RichText(
      text: TextSpan(
        text: label,
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF334155)),
        children: [
          if (isRequired)
            const TextSpan(
              text: ' *',
              style: TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold),
            ),
        ],
      ),
    );
  }

  InputDecoration _inputDecoration(String hint, {String? prefix}) {
    return InputDecoration(
      hintText: hint,
      prefixText: prefix,
      prefixStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
      hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
    );
  }
}
