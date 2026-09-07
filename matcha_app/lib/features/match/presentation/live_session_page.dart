import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import '../data/match_service.dart';
import 'match_scoring_page.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

class LiveSessionPage extends StatefulWidget {
  final dynamic sessionId;
  final String sessionName;
  final String sportName;
  final bool isHost;

  const LiveSessionPage({
    super.key,
    this.sessionId,
    this.sessionName = 'Saturday Morning',
    this.sportName = 'Tennis',
    this.isHost = true,
  });

  @override
  State<LiveSessionPage> createState() => _LiveSessionPageState();
}

class _LiveSessionPageState extends State<LiveSessionPage> {
  final MatchService _matchService = MatchService();
  RealtimeChannel? _realtimeChannel;

  bool _isLoading = true;
  String? _errorMessage;
  dynamic _activeSessionId;
  String _sessionTitle = '';
  String _sportTitle = '';
  List<Map<String, dynamic>> _liveCourts = [];
  List<String> _waitingPlayers = [];

  @override
  void initState() {
    super.initState();
    _activeSessionId = widget.sessionId;
    _sessionTitle = widget.sessionName;
    _sportTitle = widget.sportName;
    _initDataAndRealtime();
  }

  Future<void> _initDataAndRealtime() async {
    await _loadSessionData();
    if (mounted && _activeSessionId != null) {
      _subscribeRealtime();
    }
  }

  Future<void> _loadSessionData() async {
    try {
      final session = await _matchService.getSession(_activeSessionId);
      if (session != null) {
        _activeSessionId = session['session_id'];
        _sessionTitle = session['nama_session'] ?? _sessionTitle;
      }

      if (_activeSessionId != null) {
        final matches = await _matchService.getMatchesForSession(
          _activeSessionId,
        );
        final waiting = await _matchService.getWaitingPlayers(_activeSessionId);
        if (mounted) {
          setState(() {
            _liveCourts = matches;
            _waitingPlayers = waiting;
            _isLoading = false;
            _errorMessage = null;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _isLoading = false;
            _liveCourts = [];
            _waitingPlayers = [];
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = e.toString();
        });
      }
    }
  }

  void _subscribeRealtime() {
    _matchService.unsubscribe(_realtimeChannel);
    _realtimeChannel = _matchService.subscribeLiveSession(
      sessionId: _activeSessionId,
      onDataChanged: () {
        if (mounted) {
          _refreshDataSilently();
        }
      },
    );
  }

  Future<void> _refreshDataSilently() async {
    if (_activeSessionId == null) return;
    try {
      final matches = await _matchService.getMatchesForSession(
        _activeSessionId,
      );
      final waiting = await _matchService.getWaitingPlayers(_activeSessionId);
      if (mounted) {
        setState(() {
          _liveCourts = matches;
          _waitingPlayers = waiting;
        });
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _matchService.unsubscribe(_realtimeChannel);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text(
          'Live Session',
          style: TextStyle(color: context.txtPrimary),
        ),
        leading: IconButton(
          icon: Icon(
            Icons.arrow_back_ios_new_rounded,
            size: 18,
            color: context.txtPrimary,
          ),
          onPressed: () => Navigator.maybePop(context),
        ),
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 16),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: context.brandColor.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: context.brandColor,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  'LIVE',
                  style: AppTextStyles.badge.copyWith(
                    color: context.brandColor,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      body: _isLoading
          ? Center(child: CircularProgressIndicator(color: context.brandColor))
          : _errorMessage != null && _liveCourts.isEmpty
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24.0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.error_outline_rounded,
                      size: 40,
                      color: Colors.redAccent,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Terjadi Kesalahan',
                      style: AppTextStyles.cardTitle.copyWith(
                        color: context.txtPrimary,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      _errorMessage!,
                      textAlign: TextAlign.center,
                      style: AppTextStyles.caption.copyWith(
                        color: context.txtSecondary,
                      ),
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: () {
                        setState(() => _isLoading = true);
                        _loadSessionData();
                      },
                      child: const Text('Coba Lagi'),
                    ),
                  ],
                ),
              ),
            )
          : SafeArea(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(
                  horizontal: 20.0,
                  vertical: 16.0,
                ),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // 1. Session Header Info
                    _buildSessionHeader(context),
                    const SizedBox(height: 20),

                    // 2. Section: Sedang Bertanding di Lapangan
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'PERTANDINGAN AKTIF',
                          style: AppTextStyles.badge.copyWith(
                            color: context.txtSecondary,
                            letterSpacing: 1.5,
                          ),
                        ),
                        Text(
                          '${_liveCourts.length} Lapangan Berjalan',
                          style: AppTextStyles.caption.copyWith(
                            color: context.brandColor,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // 3. Live Court Score Cards
                    if (_liveCourts.isEmpty)
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          color: context.surf,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: context.surfBorder),
                        ),
                        child: Column(
                          children: [
                            Icon(
                              Icons.sports_tennis_rounded,
                              size: 36,
                              color: context.txtSecondary,
                            ),
                            const SizedBox(height: 12),
                            Text(
                              'Belum Ada Pertandingan Berjalan',
                              style: AppTextStyles.cardTitle.copyWith(
                                color: context.txtPrimary,
                                fontSize: 15,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Pertandingan untuk sesi ini belum dimulai atau telah selesai.',
                              textAlign: TextAlign.center,
                              style: AppTextStyles.caption.copyWith(
                                color: context.txtSecondary,
                              ),
                            ),
                          ],
                        ),
                      )
                    else
                      ..._liveCourts.map(
                        (court) => Padding(
                          padding: const EdgeInsets.only(bottom: 14.0),
                          child: _buildCourtScoreCard(context, court),
                        ),
                      ),

                    const SizedBox(height: 10),

                    // 4. Waiting Players Section
                    _buildWaitingSection(context),
                    const SizedBox(height: 20),

                    // 5. Next Drawing / Next Turn Preview Box
                    _buildNextDrawingCard(context),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildSessionHeader(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            _sessionTitle.isNotEmpty ? _sessionTitle : widget.sessionName,
            style: AppTextStyles.sectionTitle.copyWith(
              fontSize: 18,
              color: context.txtPrimary,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              Icon(
                Icons.sports_tennis_rounded,
                size: 14,
                color: context.brandColor,
              ),
              const SizedBox(width: 6),
              Text(
                '${_sportTitle.isNotEmpty ? _sportTitle : widget.sportName} · ${_liveCourts.length} Courts Aktif',
                style: AppTextStyles.caption.copyWith(
                  color: context.txtSecondary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildCourtScoreCard(
    BuildContext context,
    Map<String, dynamic> court,
  ) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Court Name & Status
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                court['courtName'],
                style: AppTextStyles.cardTitle.copyWith(
                  color: context.brandColor,
                  fontSize: 14,
                ),
              ),
              _buildStatusBadge(
                context,
                court['status']?.toString() ?? 'In Progress',
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Live Score Display
          Container(
            padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
            decoration: BoxDecoration(
              color: context.surfSec,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: context.surfBorder),
            ),
            child: Row(
              children: [
                // Team A
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        court['sideA'],
                        style: AppTextStyles.body.copyWith(
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          color: context.txtPrimary,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Side A',
                        style: AppTextStyles.caption.copyWith(
                          fontSize: 10,
                          color: context.txtSecondary,
                        ),
                      ),
                    ],
                  ),
                ),

                // Live Score Board Digit
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: context.surf,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: context.brandColor.withValues(alpha: 0.3),
                    ),
                  ),
                  child: Text(
                    '${court['scoreA']} — ${court['scoreB']}',
                    style: AppTextStyles.scoreDisplay.copyWith(
                      color: context.brandColor,
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),

                // Team B
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        court['sideB'],
                        textAlign: TextAlign.end,
                        style: AppTextStyles.body.copyWith(
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          color: context.txtPrimary,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Side B',
                        style: AppTextStyles.caption.copyWith(
                          fontSize: 10,
                          color: context.txtSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Host Quick Action to Input Score
          if (widget.isHost) ...[
            const SizedBox(height: 12),
            InkWell(
              onTap: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => MatchScoringPage(
                      matchId: court['matchId'] as int?,
                      sessionId: _activeSessionId,
                      sessionName: _sessionTitle.isNotEmpty
                          ? _sessionTitle
                          : widget.sessionName,
                      courtName: court['courtName'],
                      sideA: court['sideA'],
                      sideB: court['sideB'],
                      initialScoreA: court['scoreA'] as int? ?? 0,
                      initialScoreB: court['scoreB'] as int? ?? 0,
                      statusMatch: court['status']?.toString(),
                    ),
                  ),
                );
                _refreshDataSilently();
              },
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 8),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: context.brandColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: context.brandColor.withValues(alpha: 0.25),
                  ),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.edit_note_rounded,
                      size: 16,
                      color: context.brandColor,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'Input / Update Skor Pertandingan',
                      style: AppTextStyles.caption.copyWith(
                        color: context.brandColor,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildStatusBadge(BuildContext context, String rawStatus) {
    final status = rawStatus.trim().toLowerCase();
    Color color;
    String label;

    if (status == 'finished') {
      color = context.txtSecondary;
      label = 'Finished';
    } else if (status == 'waiting') {
      color = AppColors.warning;
      label = 'Waiting';
    } else if (status == 'in progress' || status == 'playing') {
      color = AppColors.inProgressBadge;
      label = 'In Progress';
    } else {
      color = AppColors.inProgressBadge;
      label = rawStatus;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        label,
        style: AppTextStyles.badge.copyWith(color: color, fontSize: 10),
      ),
    );
  }

  Widget _buildWaitingSection(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.pause_circle_outline_rounded,
                size: 18,
                color: AppColors.warning,
              ),
              const SizedBox(width: 8),
              Text(
                'WAITING PLAYERS (${_waitingPlayers.length})',
                style: AppTextStyles.badge.copyWith(
                  color: AppColors.warning,
                  letterSpacing: 1,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (_waitingPlayers.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4.0),
              child: Text(
                'Tidak ada pemain dalam antrean.',
                style: AppTextStyles.caption.copyWith(
                  color: context.txtSecondary,
                ),
              ),
            )
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _waitingPlayers.map((name) {
                return Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: context.surfSec,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: context.surfBorder),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.person_outline_rounded,
                        size: 14,
                        color: context.txtSecondary,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        name,
                        style: AppTextStyles.body.copyWith(
                          fontSize: 13,
                          color: context.txtPrimary,
                        ),
                      ),
                    ],
                  ),
                );
              }).toList(),
            ),
        ],
      ),
    );
  }

  Widget _buildNextDrawingCard(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: context.brandColor.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              Icons.shuffle_rounded,
              color: context.brandColor,
              size: 24,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Next Turn / Re-Drawing',
                  style: AppTextStyles.cardTitle.copyWith(
                    fontSize: 14,
                    color: context.txtPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Susunan ronde berikutnya siap dibuat setelah semua match selesai.',
                  style: AppTextStyles.caption.copyWith(
                    fontSize: 11,
                    color: context.txtSecondary,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          OutlinedButton(
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              minimumSize: const Size(60, 34),
            ),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => DrawingResultPage(
                    sessionName: widget.sessionName,
                    sportName: widget.sportName,
                  ),
                ),
              );
            },
            child: Text(
              'Lihat',
              style: TextStyle(fontSize: 12, color: context.txtPrimary),
            ),
          ),
        ],
      ),
    );
  }
}
