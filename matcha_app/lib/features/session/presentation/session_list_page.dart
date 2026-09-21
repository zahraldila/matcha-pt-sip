import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../data/session_service.dart';
import '../domain/session_model.dart';
import 'create_session_page.dart';

import '../../auth/presentation/controllers/auth_controller.dart';

class SessionListPage extends StatefulWidget {
  final AuthController? authController;
  final Function(int sessionId)? onSessionTap;

  const SessionListPage({
    super.key,
    this.authController,
    this.onSessionTap,
  });

  @override
  State<SessionListPage> createState() => _SessionListPageState();
}

class _SessionListPageState extends State<SessionListPage> with SingleTickerProviderStateMixin {
  final SessionService _sessionService = SessionService();
  late TabController _tabController;

  bool _isLoading = true;
  String? _errorMessage;
  List<SessionModel> _allSessions = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _loadSessions();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadSessions() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final sessions = await _sessionService.getSessions();
      if (!mounted) return;

      setState(() {
        _allSessions = sessions;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  List<SessionModel> get _liveSessions => _allSessions.where((s) {
        final st = s.statusSession.toLowerCase();
        return st == 'in progress' || st == 'live';
      }).toList();

  List<SessionModel> get _upcomingSessions => _allSessions.where((s) {
        final st = s.statusSession.toLowerCase();
        return st == 'open' || st == 'ready for drawing';
      }).toList();

  List<SessionModel> get _finishedSessions => _allSessions.where((s) {
        final st = s.statusSession.toLowerCase();
        return st == 'finished' || st == 'selesai';
      }).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: Text(
          'Jadwal Sesi Mabar',
          style: AppTextStyles.h2.copyWith(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF0F172A),
          ),
        ),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.matchaDark,
          unselectedLabelColor: const Color(0xFF64748B),
          indicatorColor: AppColors.matchaDark,
          indicatorWeight: 3,
          labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
          unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500, fontSize: 13),
          tabs: [
            Tab(text: 'Live (${_liveSessions.length})'),
            Tab(text: 'Mendatang (${_upcomingSessions.length})'),
            Tab(text: 'Riwayat (${_finishedSessions.length})'),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.matchaDark),
            )
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline_rounded, color: Colors.redAccent, size: 40),
                        const SizedBox(height: 12),
                        Text(
                          'Gagal memuat sesi mabar',
                          style: AppTextStyles.cardTitle.copyWith(color: const Color(0xFF0F172A)),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: AppTextStyles.caption.copyWith(color: const Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _loadSessions,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.matchaDark,
                            foregroundColor: Colors.white,
                          ),
                          child: const Text('Coba Lagi'),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadSessions,
                  color: AppColors.matchaDark,
                  child: TabBarView(
                    controller: _tabController,
                    children: [
                      // Tab 1: Live & In Progress
                      _buildSessionList(
                        sessions: _liveSessions,
                        emptyMessage: 'Tidak ada sesi mabar yang sedang live saat ini.',
                        emptyIcon: Icons.tv_off_rounded,
                      ),

                      // Tab 2: Mendatang / Open
                      _buildSessionList(
                        sessions: _upcomingSessions,
                        emptyMessage: 'Belum ada jadwal mabar mendatang yang terbuka.',
                        emptyIcon: Icons.event_available_rounded,
                      ),

                      // Tab 3: Riwayat Selesai
                      _buildSessionList(
                        sessions: _finishedSessions,
                        emptyMessage: 'Belum ada riwayat sesi mabar yang selesai.',
                        emptyIcon: Icons.history_rounded,
                      ),
                    ],
                  ),
                ),
      floatingActionButton: (widget.authController?.currentUser?.isHost == true)
          ? FloatingActionButton.extended(
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const CreateSessionPage()),
                );
              },
              backgroundColor: AppColors.matchaDark,
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add_rounded, color: Color(0xFFA8E63A)),
              label: const Text(
                'Buat Mabar',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
            )
          : null,
    );
  }

  Widget _buildSessionList({
    required List<SessionModel> sessions,
    required String emptyMessage,
    required IconData emptyIcon,
  }) {
    if (sessions.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(emptyIcon, size: 48, color: const Color(0xFFCBD5E1)),
              const SizedBox(height: 12),
              Text(
                emptyMessage,
                textAlign: TextAlign.center,
                style: AppTextStyles.caption.copyWith(
                  color: const Color(0xFF64748B),
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 90),
      physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
      itemCount: sessions.length,
      itemBuilder: (context, index) {
        final session = sessions[index];
        return _buildSessionCard(session);
      },
    );
  }

  Widget _buildSessionCard(SessionModel session) {
    final isLive = session.statusSession.toLowerCase() == 'in progress' ||
        session.statusSession.toLowerCase() == 'live';

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isLive ? AppColors.matchaDark.withValues(alpha: 0.3) : const Color(0xFFE2E8F0),
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
            if (widget.onSessionTap != null) {
              widget.onSessionTap!(session.sessionId);
            }
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
                      ),
                      child: Text(
                        session.sportName.toUpperCase(),
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: AppColors.matchaDark,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '${session.jenisPermainan} • ${session.scoringSystem}',
                      style: AppTextStyles.caption.copyWith(
                        color: const Color(0xFF64748B),
                        fontSize: 11,
                      ),
                    ),
                    const Spacer(),
                    if (isLive)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: Colors.redAccent.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: Colors.redAccent.withValues(alpha: 0.4),
                            width: 0.8,
                          ),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.circle, color: Colors.redAccent, size: 7),
                            SizedBox(width: 4),
                            Text(
                              'LIVE NOW',
                              style: TextStyle(
                                color: Colors.redAccent,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      )
                    else
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0FDF4),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          session.statusSession,
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF16A34A),
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  session.namaSession,
                  style: AppTextStyles.h3.copyWith(
                    color: const Color(0xFF0F172A),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    const Icon(Icons.location_on_outlined, size: 14, color: Color(0xFF94A3B8)),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        session.venueName,
                        style: AppTextStyles.caption.copyWith(
                          color: const Color(0xFF64748B),
                          fontSize: 12,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    const Icon(Icons.access_time_rounded, size: 14, color: Color(0xFF94A3B8)),
                    const SizedBox(width: 4),
                    Text(
                      session.waktuSession ?? 'Jadwal belum ditentukan',
                      style: AppTextStyles.caption.copyWith(
                        color: const Color(0xFF64748B),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Text(
                      '${session.currentPlayersCount}/${session.jumlahPemain} Slot Terisi',
                      style: AppTextStyles.caption.copyWith(
                        color: session.isFull ? Colors.redAccent : AppColors.matchaDark,
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
                      ),
                    ),
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: AppColors.matchaDark,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            'Buka Mabar',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                              fontSize: 12,
                            ),
                          ),
                          SizedBox(width: 4),
                          Icon(Icons.arrow_forward_rounded, size: 14, color: Colors.white),
                        ],
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