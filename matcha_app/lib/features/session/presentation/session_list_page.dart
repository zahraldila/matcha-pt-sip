import 'package:flutter/material.dart';
import '../../../core/data/mock_data_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import 'create_session_page.dart';
import 'session_detail_page.dart';

class SessionListPage extends StatefulWidget {
  final Function(String id)? onSessionTap;

  const SessionListPage({
    super.key,
    this.onSessionTap,
  });

  @override
  State<SessionListPage> createState() => _SessionListPageState();
}

class _SessionListPageState extends State<SessionListPage> with SingleTickerProviderStateMixin {
  final MockDataService _dataService = MockDataService();
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _dataService.addListener(_onDataChanged);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _dataService.removeListener(_onDataChanged);
    super.dispose();
  }

  void _onDataChanged() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final liveSession = _dataService.activeLiveSession;
    final upcomingSessions = _dataService.upcomingSessions;
    final finishedSessions = _dataService.finishedSessions;

    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Jadwal Sesi Mabar',
          style: AppTextStyles.h2.copyWith(fontSize: 18, color: context.txtPrimary),
        ),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.matchaDark,
          unselectedLabelColor: context.txtSecondary,
          indicatorColor: AppColors.matchaDark,
          indicatorWeight: 3,
          labelStyle: AppTextStyles.button.copyWith(fontSize: 13),
          tabs: const [
            Tab(text: 'Live / Hari Ini'),
            Tab(text: 'Mendatang'),
            Tab(text: 'Riwayat Selesai'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          // Tab 1: Live & Hari ini
          _buildSessionList(
            context,
            sessions: liveSession != null ? [liveSession] : [],
            emptyMessage: 'Tidak ada sesi mabar yang sedang aktif saat ini.',
          ),

          // Tab 2: Mendatang
          _buildSessionList(
            context,
            sessions: upcomingSessions,
            emptyMessage: 'Belum ada jadwal mabar mendatang.',
          ),

          // Tab 3: Riwayat
          _buildSessionList(
            context,
            sessions: finishedSessions,
            emptyMessage: 'Belum ada riwayat sesi mabar yang selesai.',
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const CreateSessionPage()),
          );
        },
        backgroundColor: AppColors.matchaDark,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Buat Mabar', style: TextStyle(fontWeight: FontWeight.bold)),
      ),
    );
  }

  Widget _buildSessionList(
    BuildContext context, {
    required List<MatchaSession> sessions,
    required String emptyMessage,
  }) {
    if (sessions.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.event_busy_rounded, size: 48, color: context.txtDisabled),
              const SizedBox(height: 12),
              Text(
                emptyMessage,
                textAlign: TextAlign.center,
                style: AppTextStyles.bodyMedium.copyWith(color: context.txtSecondary),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 80),
      physics: const BouncingScrollPhysics(),
      itemCount: sessions.length,
      itemBuilder: (context, index) {
        final session = sessions[index];
        return _buildSessionCard(context, session);
      },
    );
  }

  Widget _buildSessionCard(BuildContext context, MatchaSession session) {
    final user = _dataService.currentUser;
    final isJoined = session.participants.any((p) => p.id == user.id);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: session.status == 'live'
              ? AppColors.matchaDark.withValues(alpha: 0.3)
              : context.surfBorder,
          width: 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
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
                builder: (_) => SessionDetailPage(session: session),
              ),
            );
          },
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: AppColors.matchaSoftLime,
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.2)),
                      ),
                      child: Text(
                        session.sport.toUpperCase(),
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: AppColors.matchaDark,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      session.matchFormat,
                      style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                    ),
                    const Spacer(),
                    if (session.status == 'live')
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: Colors.redAccent.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(color: Colors.redAccent.withValues(alpha: 0.4), width: 0.8),
                        ),
                        child: const Text(
                          'LIVE NOW',
                          style: TextStyle(
                            color: Colors.redAccent,
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      )
                    else
                      Text(
                        'Rp ${(session.pricePerPerson / 1000).toStringAsFixed(0)}k/org',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: AppColors.matchaDark,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  session.title,
                  style: AppTextStyles.h3.copyWith(color: context.txtPrimary, fontSize: 15),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    Icon(Icons.location_on_outlined, size: 14, color: context.txtSecondary),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        '${session.venueName}, ${session.location}',
                        style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.access_time_rounded, size: 14, color: context.txtSecondary),
                    const SizedBox(width: 4),
                    Text(
                      '${session.date} • ${session.time}',
                      style: AppTextStyles.caption.copyWith(color: context.txtSecondary, fontSize: 11),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Text(
                      '${session.participants.length}/${session.maxParticipants} Kuota Terisi',
                      style: AppTextStyles.caption.copyWith(
                        color: session.isFull ? Colors.redAccent : AppColors.matchaDark,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const Spacer(),
                    ElevatedButton(
                      onPressed: () {
                        _dataService.joinSession(session.id);
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              isJoined
                                  ? 'Batal bergabung dari ${session.title}'
                                  : 'Berhasil bergabung ke ${session.title}! 🎉',
                            ),
                            behavior: SnackBarBehavior.floating,
                          ),
                        );
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: isJoined
                            ? context.surfSec
                            : AppColors.matchaDark,
                        foregroundColor: isJoined
                            ? context.txtSecondary
                            : Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      child: Text(
                        isJoined ? 'Batal' : 'Join',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}