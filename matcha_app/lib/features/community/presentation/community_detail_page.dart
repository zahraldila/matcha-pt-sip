import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';

class CommunityDetailPage extends StatefulWidget {
  final MatchaCommunity community;

  const CommunityDetailPage({
    super.key,
    required this.community,
  });

  @override
  State<CommunityDetailPage> createState() => _CommunityDetailPageState();
}

class _CommunityDetailPageState extends State<CommunityDetailPage> {
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
    final com = _dataService.communities.firstWhere(
      (c) => c.id == widget.community.id,
      orElse: () => widget.community,
    );
    final isJoined = com.isJoined;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Detail Komunitas',
          style: AppTextStyles.h2.copyWith(fontSize: 16, color: context.txtPrimary),
        ),
        centerTitle: true,
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
                  // Logo & Header Card
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: context.surf,
                      borderRadius: BorderRadius.circular(24),
                      border: Border.all(color: context.surfBorder),
                    ),
                    child: Column(
                      children: [
                        CircleAvatar(
                          radius: 36,
                          backgroundImage: NetworkImage(com.logoUrl),
                          backgroundColor: context.surfBorder,
                        ),
                        const SizedBox(height: 14),
                        Text(
                          com.name,
                          style: AppTextStyles.h1.copyWith(fontSize: 18, color: context.txtPrimary),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${com.sport.toUpperCase()} • ${com.location}',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: context.brandColor,
                          ),
                        ),
                        const SizedBox(height: 14),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            _buildBadgePill(context, '${com.memberCount} Anggota', Icons.people_outline_rounded),
                            const SizedBox(width: 8),
                            _buildBadgePill(context, 'Komunitas Aktif', Icons.verified_rounded),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Deskripsi
                  Text(
                    'Tentang Komunitas',
                    style: AppTextStyles.h2.copyWith(fontSize: 15, color: context.txtPrimary),
                  ),
                  const SizedBox(height: 8),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: context.surf,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: context.surfBorder),
                    ),
                    child: Text(
                      com.description,
                      style: AppTextStyles.bodyMedium.copyWith(color: context.txtPrimary, height: 1.5),
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Jadwal Mabar Rutin
                  Text(
                    'Jadwal Mabar Rutin',
                    style: AppTextStyles.h2.copyWith(fontSize: 15, color: context.txtPrimary),
                  ),
                  const SizedBox(height: 8),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: context.surfSec,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: context.surfBorder),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.calendar_month_rounded, color: context.brandColor, size: 24),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                com.regularSchedule,
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  color: context.txtPrimary,
                                  fontSize: 13,
                                ),
                              ),
                              Text(
                                'Jadwal mabar dibuka 3 hari sebelumnya di aplikasi',
                                style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Bottom Join / Leave Button
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: context.surf,
              border: Border(top: BorderSide(color: context.surfBorder, width: 1)),
            ),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: () {
                    _dataService.toggleJoinCommunity(com.id);
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(
                          isJoined
                              ? 'Keluar dari komunitas ${com.name}'
                              : 'Berhasil bergabung dengan ${com.name}! 🎉',
                        ),
                        behavior: SnackBarBehavior.floating,
                      ),
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: isJoined
                        ? Colors.redAccent.withValues(alpha: 0.15)
                        : context.brandColor,
                    foregroundColor: isJoined ? Colors.redAccent : Colors.black,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  child: Text(
                    isJoined ? 'Keluar dari Komunitas' : 'Gabung Komunitas Ini',
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

  Widget _buildBadgePill(BuildContext context, String text, IconData icon) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: context.surfSec,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: context.surfBorder),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: context.txtSecondary),
          const SizedBox(width: 4),
          Text(text, style: TextStyle(fontSize: 11, color: context.txtSecondary, fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}