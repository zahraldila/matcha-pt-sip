import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import 'create_session_page.dart';

class SessionListPage extends StatefulWidget {
  final bool isHost;
  final Function(String sessionId)? onSessionTap;

  const SessionListPage({
    super.key,
    required this.isHost,
    this.onSessionTap,
  });

  @override
  State<SessionListPage> createState() => _SessionListPageState();
}

class _SessionListPageState extends State<SessionListPage> {
  String _selectedFilter = 'Semua';

  final List<String> _filters = ['Semua', 'Live', 'Upcoming', 'Finished'];

  final List<Map<String, dynamic>> _mockSessions = [
    {
      'id': 'sess-001',
      'title': 'Saturday Morning',
      'sport': 'Tennis',
      'players': 8,
      'courts': 2,
      'time': 'Hari ini, 08:00',
      'status': 'LIVE',
    },
    {
      'id': 'sess-002',
      'title': 'Friday Night Play',
      'sport': 'Padel',
      'players': 6,
      'courts': 1,
      'time': '7 Sep 2026 · 18:00',
      'status': 'UPCOMING',
    },
    {
      'id': 'sess-003',
      'title': 'Badminton Community',
      'sport': 'Badminton',
      'players': 12,
      'courts': 3,
      'time': '8 Sep 2026 · 19:00',
      'status': 'UPCOMING',
    },
    {
      'id': 'sess-004',
      'title': 'Pickleball Fun',
      'sport': 'Pickleball',
      'players': 8,
      'courts': 2,
      'time': '9 Sep 2026 · 16:00',
      'status': 'UPCOMING',
    },
    {
      'id': 'sess-005',
      'title': 'Tennis Weekend',
      'sport': 'Tennis',
      'players': 12,
      'courts': 3,
      'time': '10 Sep 2026 · 07:00',
      'status': 'FINISHED',
    },
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Daftar Session', style: AppTextStyles.pageTitle.copyWith(fontSize: 22)),
                  IconButton(
                    icon: const Icon(Icons.search_rounded, color: AppColors.textPrimary, size: 24),
                    onPressed: () {
                      // Action search sesi
                    },
                  ),
                ],
              ),
              const SizedBox(height: 14),

              // Filter Chips
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: _filters.map((filter) {
                    final isSelected = _selectedFilter == filter;
                    return Padding(
                      padding: const EdgeInsets.only(right: 8.0),
                      child: ChoiceChip(
                        label: Text(filter),
                        selected: isSelected,
                        selectedColor: AppColors.primary,
                        backgroundColor: AppColors.surface,
                        labelStyle: TextStyle(
                          color: isSelected ? AppColors.textOnPrimary : AppColors.textSecondary,
                          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                          fontSize: 12,
                        ),
                        side: BorderSide(
                          color: isSelected ? AppColors.primary : AppColors.surfaceBorder,
                        ),
                        onSelected: (_) => setState(() => _selectedFilter = filter),
                      ),
                    );
                  }).toList(),
                ),
              ),
              const SizedBox(height: 16),

              // Sessions List
              Expanded(
                child: ListView.separated(
                  itemCount: _mockSessions.length,
                  separatorBuilder: (context, index) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final session = _mockSessions[index];
                    return _buildSessionCard(session);
                  },
                ),
              ),
            ],
          ),
        ),
      ),
      floatingActionButton: widget.isHost
          ? FloatingActionButton.extended(
              backgroundColor: AppColors.primary,
              foregroundColor: AppColors.textOnPrimary,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Buat Session Baru', style: TextStyle(fontWeight: FontWeight.bold)),
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (context) => const CreateSessionPage()),
                );
              },
            )
          : null,
    );
  }

  Widget _buildSessionCard(Map<String, dynamic> session) {
    final isLive = session['status'] == 'LIVE';

    return InkWell(
      onTap: () {
        if (widget.onSessionTap != null) {
          widget.onSessionTap!(session['id']);
        }
      },
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isLive ? AppColors.primary.withValues(alpha: 0.4) : AppColors.surfaceBorder,
            width: isLive ? 1.5 : 1,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  session['title'],
                  style: AppTextStyles.cardTitle.copyWith(fontSize: 16),
                ),
                _buildStatusBadge(session['status']),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(
                  Icons.sports_tennis_rounded,
                  size: 14,
                  color: isLive ? AppColors.primary : AppColors.textSecondary,
                ),
                const SizedBox(width: 6),
                Text(
                  '${session['sport']} · ${session['players']} Players · ${session['courts']} Courts',
                  style: AppTextStyles.caption.copyWith(
                    color: isLive ? AppColors.textPrimary : AppColors.textSecondary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                const Icon(Icons.access_time_rounded, size: 14, color: AppColors.textSecondary),
                const SizedBox(width: 6),
                Text(
                  session['time'],
                  style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusBadge(String status) {
    Color badgeColor;
    Color textColor;

    switch (status) {
      case 'LIVE':
        badgeColor = AppColors.primary.withValues(alpha: 0.15);
        textColor = AppColors.primary;
        break;
      case 'UPCOMING':
        badgeColor = AppColors.warning.withValues(alpha: 0.15);
        textColor = AppColors.warning;
        break;
      default:
        badgeColor = AppColors.surfaceSecondary;
        textColor = AppColors.textSecondary;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: badgeColor,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        status,
        style: AppTextStyles.badge.copyWith(fontSize: 10, color: textColor),
      ),
    );
  }
}
