import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_text_styles.dart';

class SessionFilterOptions {
  final String status; // 'all', 'upcoming', 'live', 'finished'
  final bool onlyAvailableSlots;
  final String timeFilter; // 'all', 'today', 'tomorrow', 'this_week'

  const SessionFilterOptions({
    this.status = 'all',
    this.onlyAvailableSlots = false,
    this.timeFilter = 'all',
  });

  bool get hasActiveFilter =>
      status != 'all' || onlyAvailableSlots || timeFilter != 'all';

  int get activeCount {
    int count = 0;
    if (status != 'all') count++;
    if (onlyAvailableSlots) count++;
    if (timeFilter != 'all') count++;
    return count;
  }

  SessionFilterOptions copyWith({
    String? status,
    bool? onlyAvailableSlots,
    String? timeFilter,
  }) {
    return SessionFilterOptions(
      status: status ?? this.status,
      onlyAvailableSlots: onlyAvailableSlots ?? this.onlyAvailableSlots,
      timeFilter: timeFilter ?? this.timeFilter,
    );
  }
}

class SessionFilterBottomSheet extends StatefulWidget {
  final SessionFilterOptions initialOptions;
  final Function(SessionFilterOptions) onApply;

  const SessionFilterBottomSheet({
    super.key,
    required this.initialOptions,
    required this.onApply,
  });

  static Future<SessionFilterOptions?> show({
    required BuildContext context,
    required SessionFilterOptions currentOptions,
    required Function(SessionFilterOptions) onApply,
  }) {
    return showModalBottomSheet<SessionFilterOptions>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => SessionFilterBottomSheet(
        initialOptions: currentOptions,
        onApply: onApply,
      ),
    );
  }

  @override
  State<SessionFilterBottomSheet> createState() => _SessionFilterBottomSheetState();
}

class _SessionFilterBottomSheetState extends State<SessionFilterBottomSheet> {
  late String _tempStatus;
  late bool _tempOnlyAvailableSlots;
  late String _tempTimeFilter;

  @override
  void initState() {
    super.initState();
    _tempStatus = widget.initialOptions.status;
    _tempOnlyAvailableSlots = widget.initialOptions.onlyAvailableSlots;
    _tempTimeFilter = widget.initialOptions.timeFilter;
  }

  void _reset() {
    setState(() {
      _tempStatus = 'all';
      _tempOnlyAvailableSlots = false;
      _tempTimeFilter = 'all';
    });
  }

  void _apply() {
    final result = SessionFilterOptions(
      status: _tempStatus,
      onlyAvailableSlots: _tempOnlyAvailableSlots,
      timeFilter: _tempTimeFilter,
    );
    widget.onApply(result);
    Navigator.pop(context, result);
  }

  @override
  Widget build(BuildContext context) {
    final hasChanges = _tempStatus != 'all' ||
        _tempOnlyAvailableSlots ||
        _tempTimeFilter != 'all';

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Handle Bar
            Center(
              child: Container(
                margin: const EdgeInsets.only(top: 12, bottom: 8),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFCBD5E1),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),

            // Header Title & Reset Button
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(
                          color: AppColors.matchaSoftLime,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Icon(
                          Icons.tune_rounded,
                          color: AppColors.matchaDark,
                          size: 18,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Text(
                        'Filter Sesi Mabar',
                        style: AppTextStyles.h2.copyWith(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF0F172A),
                        ),
                      ),
                    ],
                  ),
                  if (hasChanges)
                    TextButton(
                      onPressed: _reset,
                      style: TextButton.styleFrom(
                        foregroundColor: const Color(0xFFE11D48),
                        visualDensity: VisualDensity.compact,
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                      ),
                      child: const Text(
                        'Atur Ulang',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const Divider(height: 1, color: Color(0xFFF1F5F9)),

            Flexible(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Section 1: Status Pertandingan
                    _buildSectionHeader(
                      title: 'Status Pertandingan',
                      subtitle: 'Pilih status progres sesi mabar',
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _buildChoiceChip(
                          label: 'Semua Status',
                          icon: Icons.apps_rounded,
                          isSelected: _tempStatus == 'all',
                          onTap: () => setState(() => _tempStatus = 'all'),
                        ),
                        _buildChoiceChip(
                          label: 'Belum Mulai',
                          icon: Icons.schedule_rounded,
                          isSelected: _tempStatus == 'upcoming',
                          onTap: () => setState(() => _tempStatus = 'upcoming'),
                        ),
                        _buildChoiceChip(
                          label: 'Sedang Main (LIVE)',
                          icon: Icons.fiber_manual_record_rounded,
                          isSelected: _tempStatus == 'live',
                          onTap: () => setState(() => _tempStatus = 'live'),
                        ),
                        _buildChoiceChip(
                          label: 'Selesai',
                          icon: Icons.check_circle_outline_rounded,
                          isSelected: _tempStatus == 'finished',
                          onTap: () => setState(() => _tempStatus = 'finished'),
                        ),
                      ],
                    ),

                    const SizedBox(height: 20),
                    const Divider(height: 1, color: Color(0xFFF1F5F9)),
                    const SizedBox(height: 16),

                    // Section 2: Ketersediaan Kuota (Slot)
                    _buildSectionHeader(
                      title: 'Ketersediaan Slot',
                      subtitle: 'Saring sesi yang masih membuka kuota pemain',
                    ),
                    const SizedBox(height: 10),
                    InkWell(
                      onTap: () {
                        setState(() {
                          _tempOnlyAvailableSlots = !_tempOnlyAvailableSlots;
                        });
                      },
                      borderRadius: BorderRadius.circular(14),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        decoration: BoxDecoration(
                          color: _tempOnlyAvailableSlots
                              ? const Color(0xFFF0FDF4)
                              : const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: _tempOnlyAvailableSlots
                                ? const Color(0xFF86EFAC)
                                : const Color(0xFFE2E8F0),
                          ),
                        ),
                        child: Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(6),
                              decoration: BoxDecoration(
                                color: _tempOnlyAvailableSlots
                                    ? const Color(0xFFDCFCE7)
                                    : Colors.white,
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                Icons.group_add_rounded,
                                size: 18,
                                color: _tempOnlyAvailableSlots
                                    ? const Color(0xFF16A34A)
                                    : const Color(0xFF64748B),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Hanya yang masih ada slot kosong',
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                      color: _tempOnlyAvailableSlots
                                          ? const Color(0xFF0F172A)
                                          : const Color(0xFF334155),
                                    ),
                                  ),
                                  Text(
                                    'Sembunyikan sesi mabar yang sudah penuh',
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: Colors.grey.shade600,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            Switch(
                              value: _tempOnlyAvailableSlots,
                              onChanged: (val) {
                                setState(() => _tempOnlyAvailableSlots = val);
                              },
                              activeThumbColor: AppColors.matchaDark,
                              activeTrackColor: const Color(0xFFBBF7D0),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 20),
                    const Divider(height: 1, color: Color(0xFFF1F5F9)),
                    const SizedBox(height: 16),

                    // Section 3: Waktu Pertandingan
                    _buildSectionHeader(
                      title: 'Waktu Pertandingan',
                      subtitle: 'Cari berdasarkan jadwal pelaksanaan',
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _buildChoiceChip(
                          label: 'Semua Tanggal',
                          icon: Icons.calendar_today_rounded,
                          isSelected: _tempTimeFilter == 'all',
                          onTap: () => setState(() => _tempTimeFilter = 'all'),
                        ),
                        _buildChoiceChip(
                          label: 'Hari Ini',
                          icon: Icons.today_rounded,
                          isSelected: _tempTimeFilter == 'today',
                          onTap: () => setState(() => _tempTimeFilter = 'today'),
                        ),
                        _buildChoiceChip(
                          label: 'Besok',
                          icon: Icons.event_rounded,
                          isSelected: _tempTimeFilter == 'tomorrow',
                          onTap: () => setState(() => _tempTimeFilter = 'tomorrow'),
                        ),
                        _buildChoiceChip(
                          label: '7 Hari Ke Depan',
                          icon: Icons.date_range_rounded,
                          isSelected: _tempTimeFilter == 'this_week',
                          onTap: () => setState(() => _tempTimeFilter = 'this_week'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            // Bottom Action Bar
            Container(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(
                  top: BorderSide(color: Color(0xFFF1F5F9)),
                ),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(context),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF475569),
                        side: const BorderSide(color: Color(0xFFCBD5E1)),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                      child: const Text(
                        'Tutup',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: ElevatedButton(
                      onPressed: _apply,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.matchaDark,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                      child: const Text(
                        'Terapkan Filter',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionHeader({
    required String title,
    required String subtitle,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w800,
            color: Color(0xFF0F172A),
          ),
        ),
        const SizedBox(height: 2),
        Text(
          subtitle,
          style: const TextStyle(
            fontSize: 11.5,
            color: Color(0xFF64748B),
          ),
        ),
      ],
    );
  }

  Widget _buildChoiceChip({
    required String label,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
    Color? activeColor,
    Color? activeBg,
    Color? activeBorder,
  }) {
    final effectiveActiveBg = activeBg ?? AppColors.matchaDark;
    final effectiveActiveBorder = activeBorder ?? AppColors.matchaDark;

    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? effectiveActiveBg : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? effectiveActiveBorder : const Color(0xFFCBD5E1),
            width: isSelected ? 1.5 : 1.0,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: effectiveActiveBg.withValues(alpha: 0.15),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 14,
              color: isSelected
                  ? (activeColor ?? const Color(0xFFA8E63A))
                  : const Color(0xFF64748B),
            ),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                color: isSelected
                    ? (activeColor ?? Colors.white)
                    : const Color(0xFF334155),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
