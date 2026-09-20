import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';

class PlayerListPage extends StatefulWidget {
  final bool isHost;

  const PlayerListPage({super.key, this.isHost = false});

  @override
  State<PlayerListPage> createState() => _PlayerListPageState();
}

class _PlayerListPageState extends State<PlayerListPage> {
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
    final players = _dataService.allPlayers;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Direktori Pemain & Member',
          style: AppTextStyles.h2.copyWith(fontSize: 18, color: context.txtPrimary),
        ),
      ),
      body: ListView.builder(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 80),
        physics: const BouncingScrollPhysics(),
        itemCount: players.length,
        itemBuilder: (context, index) {
          final player = players[index];
          return Container(
            margin: const EdgeInsets.only(bottom: 12),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: context.surf,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: context.surfBorder),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 22,
                  backgroundImage: NetworkImage(player.avatarUrl),
                  backgroundColor: context.surfBorder,
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(
                            player.name,
                            style: AppTextStyles.bodyMedium.copyWith(
                              fontWeight: FontWeight.bold,
                              color: context.txtPrimary,
                              fontSize: 14,
                            ),
                          ),
                          if (player.isHost) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                              decoration: BoxDecoration(
                                color: AppColors.primary.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                'HOST',
                                style: TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.bold,
                                  color: context.brandColor,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Tier ${player.tier} • Winrate ${player.winRate}% • ${player.totalMatches} Matches',
                        style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: context.surfSec,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text('🔥', style: TextStyle(fontSize: 12)),
                      const SizedBox(width: 4),
                      Text(
                        '${player.kudosCount}',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: context.txtPrimary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
