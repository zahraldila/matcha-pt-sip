import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../drawing/presentation/drawing_result_page.dart';

class SessionDetailPage extends StatefulWidget {
  final MatchaSession session;

  const SessionDetailPage({
    super.key,
    required this.session,
  });

  @override
  State<SessionDetailPage> createState() => _SessionDetailPageState();
}

class _SessionDetailPageState extends State<SessionDetailPage> {
  final MockDataService _dataService = MockDataService();

  @override
  void initState() {
    super.initState();
    _dataService.addListener(_onDataChanged);
  }

  @override
  void dispose() {
    _dataService.removeListener(_onDataChanged);
    super.dispose();
  }

  void _onDataChanged() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    // Cari latest state sesi dari data service
    final session = _dataService.sessions.firstWhere(
      (s) => s.id == widget.session.id,
      orElse: () => widget.session,
    );
    final user = _dataService.currentUser;
    final isJoined = session.participants.any((p) => p.id == user.id);

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Detail Sesi Mabar',
          style: AppTextStyles.h2.copyWith(fontSize: 16, color: context.txtPrimary),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.share_outlined),
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Tautan jadwal mabar disalin! Siap dibagikan ke WhatsApp 📲'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // --- Header Banner Card ---
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: context.isDarkMode
                            ? [const Color(0xFF16280B), context.surf]
                            : [const Color(0xFFEBF7D4), context.surf],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(24),
                      border: Border.all(color: context.surfBorder, width: 1),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: AppColors.primary.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Text(
                                session.sport.toUpperCase(),
                                style: TextStyle(
                                  color: context.brandColor,
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                            const Spacer(),
                            Text(
                              session.status == 'live' ? '🔴 SEDANG BERLANGSUNG' : '🟢 KUOTA TERBUKA',
                              style: TextStyle(
                                color: session.status == 'live' ? Colors.redAccent : context.brandColor,
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Text(
                          session.title,
                          style: AppTextStyles.h1.copyWith(
                            fontSize: 20,
                            color: context.txtPrimary,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Diselenggarakan oleh ${session.hostName}',
                          style: AppTextStyles.caption.copyWith(
                            color: context.txtSecondary,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // --- Info Grid ---
                  _buildSectionTitle(context, 'Informasi Jadwal & Lokasi'),
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: context.surf,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: context.surfBorder, width: 1),
                    ),
                    child: Column(
                      children: [
                        _buildInfoRow(
                          context,
                          icon: Icons.calendar_today_rounded,
                          label: 'Tanggal & Jam',
                          value: '${session.date} • ${session.time}',
                        ),
                        Divider(color: context.surfBorder, height: 20),
                        _buildInfoRow(
                          context,
                          icon: Icons.location_on_rounded,
                          label: 'Venue / Lapangan',
                          value: '${session.venueName}\n${session.location}',
                        ),
                        Divider(color: context.surfBorder, height: 20),
                        _buildInfoRow(
                          context,
                          icon: Icons.sports_tennis_rounded,
                          label: 'Format Pertandingan',
                          value: session.matchFormat,
                        ),
                        Divider(color: context.surfBorder, height: 20),
                        _buildInfoRow(
                          context,
                          icon: Icons.payments_outlined,
                          label: 'Biaya Patungan',
                          value: 'Rp ${(session.pricePerPerson / 1000).toStringAsFixed(0)}.000 / orang',
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),

                  // --- Drawing Action Banner ---
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: context.surfSec,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: context.surfBorder, width: 1),
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: Colors.amberAccent.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: const Icon(Icons.shuffle_rounded, color: Colors.amberAccent, size: 24),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Bagan & Drawing Tim',
                                style: AppTextStyles.bodyMedium.copyWith(
                                  fontWeight: FontWeight.bold,
                                  color: context.txtPrimary,
                                ),
                              ),
                              Text(
                                'Lihat pembagian court & pasangan main',
                                style: AppTextStyles.caption.copyWith(
                                  color: context.txtSecondary,
                                  fontSize: 11,
                                ),
                              ),
                            ],
                          ),
                        ),
                        ElevatedButton(
                          onPressed: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const DrawingResultPage(),
                              ),
                            );
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: context.brandColor,
                            foregroundColor: Colors.black,
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          child: const Text('Lihat Tim', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),

                  // --- Daftar Peserta / Roster ---
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _buildSectionTitle(context, 'Daftar Pemain Bergabung'),
                      Text(
                        '${session.participants.length}/${session.maxParticipants} Kuota',
                        style: TextStyle(
                          color: session.isFull ? Colors.redAccent : context.brandColor,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  ListView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: session.participants.length,
                    itemBuilder: (context, index) {
                      final player = session.participants[index];
                      return Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: context.surf,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: context.surfBorder, width: 1),
                        ),
                        child: Row(
                          children: [
                            Text(
                              '${index + 1}.',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: context.txtSecondary,
                                fontSize: 12,
                              ),
                            ),
                            const SizedBox(width: 10),
                            CircleAvatar(
                              radius: 16,
                              backgroundImage: NetworkImage(player.avatarUrl),
                              backgroundColor: context.surfBorder,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    player.name,
                                    style: AppTextStyles.bodyMedium.copyWith(
                                      fontWeight: FontWeight.bold,
                                      color: context.txtPrimary,
                                      fontSize: 13,
                                    ),
                                  ),
                                  Text(
                                    'Tier ${player.tier} • Winrate ${player.winRate}%',
                                    style: AppTextStyles.caption.copyWith(
                                      color: context.txtSecondary,
                                      fontSize: 11,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (player.id == user.id)
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: AppColors.primary.withValues(alpha: 0.2),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text(
                                  'Kamu',
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                    color: context.brandColor,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
          ),

          // --- Bottom Sticky Action Bar ---
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: context.surf,
              border: Border(top: BorderSide(color: context.surfBorder, width: 1)),
            ),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: () {
                    _dataService.joinSession(session.id);
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(
                          isJoined
                              ? 'Batal bergabung dari sesi ini'
                              : 'Berhasil bergabung ke sesi ${session.title}! 🎉',
                        ),
                        behavior: SnackBarBehavior.floating,
                      ),
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: isJoined
                        ? Colors.redAccent.withValues(alpha: 0.15)
                        : (context.isDarkMode ? AppColors.primary : const Color(0xFF063B00)),
                    foregroundColor: isJoined
                        ? Colors.redAccent
                        : (context.isDarkMode ? Colors.black : Colors.white),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  child: Text(
                    isJoined ? 'Batal Bergabung (Keluar Sesi)' : 'Gabung Sesi Sekarang',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionTitle(BuildContext context, String title) {
    return Text(
      title,
      style: AppTextStyles.h2.copyWith(fontSize: 15, color: context.txtPrimary),
    );
  }

  Widget _buildInfoRow(
    BuildContext context, {
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: context.brandColor),
        const SizedBox(width: 12),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
            ),
            const SizedBox(height: 2),
            Text(
              value,
              style: AppTextStyles.bodyMedium.copyWith(
                fontWeight: FontWeight.bold,
                color: context.txtPrimary,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ],
    );
  }
}
