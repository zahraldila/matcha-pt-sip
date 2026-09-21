import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import 'community_detail_page.dart';

class CommunityPage extends StatefulWidget {
  const CommunityPage({super.key});

  @override
  State<CommunityPage> createState() => _CommunityPageState();
}

class _CommunityPageState extends State<CommunityPage> {
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
    final communities = _dataService.communities;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Komunitas Olahraga',
          style: AppTextStyles.h2.copyWith(fontSize: 18, color: context.txtPrimary),
        ),
      ),
      body: ListView.builder(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 80),
        physics: const BouncingScrollPhysics(),
        itemCount: communities.length,
        itemBuilder: (context, index) {
          final com = communities[index];
          return _buildCommunityCard(context, com);
        },
      ),
    );
  }

  Widget _buildCommunityCard(BuildContext context, MatchaCommunity com) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: context.surfBorder, width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => CommunityDetailPage(community: com),
              ),
            );
          },
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  radius: 24,
                  backgroundImage: NetworkImage(com.logoUrl),
                  backgroundColor: context.surfBorder,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: AppColors.matchaSoftLime,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              com.sport.toUpperCase(),
                              style: const TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.bold,
                                color: AppColors.matchaDark,
                              ),
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            com.location,
                            style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        com.name,
                        style: AppTextStyles.h3.copyWith(fontSize: 14, color: context.txtPrimary),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${com.memberCount} Anggota • ${com.regularSchedule}',
                        style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                ElevatedButton(
                  onPressed: () {
                    _dataService.toggleJoinCommunity(com.id);
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: com.isJoined
                        ? context.surfSec
                        : AppColors.matchaDark,
                    foregroundColor: com.isJoined
                        ? context.txtSecondary
                        : Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  child: Text(
                    com.isJoined ? 'Joined' : 'Join',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}