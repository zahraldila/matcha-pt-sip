import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import 'create_session_page.dart';
import '../../auth/domain/models/user_model.dart';
import 'controllers/session_controller.dart';

class SessionListPage extends StatefulWidget {
  final bool isHost;
  final UserModel currentUser;
  final Function(String sessionId)? onSessionTap;

  const SessionListPage({
    super.key,
    required this.isHost,
    required this.currentUser,
    this.onSessionTap,
  });

  @override
  State<SessionListPage> createState() => _SessionListPageState();
}

class _SessionListPageState extends State<SessionListPage> {
  String _selectedFilter = 'Semua';

  late final SessionController _sessionController;

  final List<String> _filters = [
    'Semua',
    'Live',
    'Upcoming',
    'Finished',
  ];

  @override
  void initState() {
    super.initState();

    _sessionController = SessionController();
    _sessionController.addListener(_onControllerChanged);

    _loadSessions();
  }

  void _onControllerChanged() {
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _loadSessions() async {
    await _sessionController.loadSessions();
  }

  @override
  void dispose() {
    _sessionController.removeListener(_onControllerChanged);
    _sessionController.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> get _filteredSessions {
    final sessions = _sessionController.sessions;

    if (_selectedFilter == 'Semua') {
      return sessions;
    }

    return sessions.where((session) {
      final status = session['status_session']
          ?.toString()
          .toLowerCase();

      return status == _selectedFilter.toLowerCase();
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.bg,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: 20.0,
            vertical: 16.0,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Daftar Session',
                    style: AppTextStyles.pageTitle.copyWith(
                      fontSize: 22,
                      color: context.txtPrimary,
                    ),
                  ),
                  IconButton(
                    icon: Icon(
                      Icons.search_rounded,
                      color: context.txtPrimary,
                      size: 24,
                    ),
                    onPressed: () {},
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
                        selectedColor: context.brandColor,
                        backgroundColor: context.surf,
                        labelStyle: TextStyle(
                          color: isSelected
                              ? Colors.black
                              : context.txtSecondary,
                          fontWeight: isSelected
                              ? FontWeight.bold
                              : FontWeight.normal,
                          fontSize: 12,
                        ),
                        side: BorderSide(
                          color: isSelected
                              ? context.brandColor
                              : context.surfBorder,
                        ),
                        onSelected: (_) {
                          setState(() {
                            _selectedFilter = filter;
                          });
                        },
                      ),
                    );
                  }).toList(),
                ),
              ),
              const SizedBox(height: 16),

              // Sessions List
              Expanded(
                child: _buildSessionList(context),
              ),
            ],
          ),
        ),
      ),

      // Create Session Button
      floatingActionButton: widget.isHost
          ? FloatingActionButton.extended(
              backgroundColor: context.brandColor,
              foregroundColor: Colors.black,
              icon: const Icon(Icons.add_rounded),
              label: const Text(
                'Buat Session Baru',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                ),
              ),
              onPressed: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => CreateSessionPage(
                      currentUser: widget.currentUser,
                    ),
                  ),
                );

                // Refresh setelah kembali dari Create Session.
                if (mounted) {
                  await _loadSessions();
                }
              },
            )
          : null,
    );
  }

  // ============================================================
  // SESSION LIST
  // ============================================================

  Widget _buildSessionList(BuildContext context) {
    if (_sessionController.isLoading) {
      return const Center(
        child: CircularProgressIndicator(),
      );
    }

    if (_sessionController.errorMessage != null) {
      return _buildErrorState(context);
    }

    final sessions = _filteredSessions;

    if (sessions.isEmpty) {
      return _buildEmptyState(context);
    }

    return RefreshIndicator(
      onRefresh: _loadSessions,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: sessions.length,
        separatorBuilder: (context, index) {
          return const SizedBox(height: 12);
        },
        itemBuilder: (context, index) {
          final session = sessions[index];

          return _buildSessionCard(
            context,
            session,
          );
        },
      ),
    );
  }

  // ============================================================
  // ERROR STATE
  // ============================================================

  Widget _buildErrorState(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.error_outline_rounded,
              size: 48,
              color: context.txtSecondary,
            ),
            const SizedBox(height: 12),
            Text(
              'Gagal memuat session',
              style: AppTextStyles.cardTitle.copyWith(
                color: context.txtPrimary,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              _sessionController.errorMessage!,
              style: AppTextStyles.caption.copyWith(
                color: context.txtSecondary,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: _loadSessions,
              child: const Text('Coba Lagi'),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================

  Widget _buildEmptyState(BuildContext context) {
    final message = _selectedFilter == 'Semua'
        ? 'Belum ada session.'
        : 'Belum ada session $_selectedFilter.';

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.sports_tennis_rounded,
              size: 48,
              color: context.txtSecondary,
            ),
            const SizedBox(height: 12),
            Text(
              message,
              style: AppTextStyles.cardTitle.copyWith(
                color: context.txtPrimary,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // SESSION CARD
  // ============================================================

  Widget _buildSessionCard(
    BuildContext context,
    Map<String, dynamic> session,
  ) {
    final status = session['status_session']
            ?.toString()
            .toUpperCase() ??
        '';

    final isLive = status == 'LIVE';

    // Data sport dari relasi tb_sport.
    final sportData = session['tb_sport'];

    final sportName = sportData is Map
        ? sportData['nama_sport']?.toString() ?? '-'
        : '-';

    // Data player dari relasi tb_session_player.
    final playersData = session['tb_session_player'];

    final playerCount = playersData is List
        ? playersData.length
        : 0;

    // Data court dari relasi tb_session_court.
    final courtsData = session['tb_session_court'];

    final courtCount = courtsData is List
        ? courtsData.length
        : 0;

    // Waktu session.
    final waktuSession = session['waktu_session'] != null
        ? DateTime.tryParse(
            session['waktu_session'].toString(),
          )
        : null;

    return InkWell(
      onTap: () {
        if (widget.onSessionTap != null) {
          widget.onSessionTap!(
            session['session_id'].toString(),
          );
        }
      },
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isLive
                ? context.brandColor.withValues(alpha: 0.4)
                : context.surfBorder,
            width: isLive ? 1.5 : 1,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Session Name + Status
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    session['nama_session']?.toString() ?? '-',
                    style: AppTextStyles.cardTitle.copyWith(
                      fontSize: 16,
                      color: context.txtPrimary,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(width: 8),
                _buildStatusBadge(
                  context,
                  status,
                ),
              ],
            ),

            const SizedBox(height: 8),

            // Sport + Players + Courts
            Row(
              children: [
                Icon(
                  Icons.sports_tennis_rounded,
                  size: 14,
                  color: isLive
                      ? context.brandColor
                      : context.txtSecondary,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    '$sportName · '
                    '$playerCount Players · '
                    '$courtCount Courts',
                    style: AppTextStyles.caption.copyWith(
                      color: isLive
                          ? context.txtPrimary
                          : context.txtSecondary,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 6),

            // Session Time
            Row(
              children: [
                Icon(
                  Icons.access_time_rounded,
                  size: 14,
                  color: context.txtSecondary,
                ),
                const SizedBox(width: 6),
                Text(
                  _formatSessionDateTime(waktuSession),
                  style: AppTextStyles.caption.copyWith(
                    color: context.txtSecondary,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // STATUS BADGE
  // ============================================================

  Widget _buildStatusBadge(
    BuildContext context,
    String status,
  ) {
    Color badgeColor;
    Color textColor;

    switch (status) {
      case 'LIVE':
        badgeColor = context.brandColor.withValues(alpha: 0.15);
        textColor = context.brandColor;
        break;

      case 'UPCOMING':
        badgeColor = AppColors.warning.withValues(alpha: 0.15);
        textColor = AppColors.warning;
        break;

      case 'FINISHED':
        badgeColor = context.surfSec;
        textColor = context.txtSecondary;
        break;

      default:
        badgeColor = context.surfSec;
        textColor = context.txtSecondary;
    }

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: 8,
        vertical: 4,
      ),
      decoration: BoxDecoration(
        color: badgeColor,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        status,
        style: AppTextStyles.badge.copyWith(
          fontSize: 10,
          color: textColor,
        ),
      ),
    );
  }

  // ============================================================
  // DATE FORMATTER
  // ============================================================

  String _formatSessionDateTime(DateTime? date) {
    if (date == null) {
      return '-';
    }

    const monthNames = [
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

    final hour = date.hour.toString().padLeft(2, '0');
    final minute = date.minute.toString().padLeft(2, '0');

    return '${date.day} ${monthNames[date.month - 1]} '
        '${date.year} · $hour:$minute';
  }
}