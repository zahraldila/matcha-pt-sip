import '../../drawing/presentation/drawing_result_page.dart';
import 'package:flutter/material.dart';

import 'package:supabase_flutter/supabase_flutter.dart';

import '../../../core/theme/app_colors.dart';

import '../../../core/theme/app_text_styles.dart';

import '../../auth/presentation/controllers/auth_controller.dart';

import '../../drawing/domain/matcha_drawing_engine.dart';

import '../../games/domain/game_wizard_model.dart';

import '../../games/presentation/game_final_recap_page.dart';
import '../../recap/presentation/session_match_recap_page.dart';

import '../data/match_service.dart';

import '../domain/services/scoring_engine.dart';



class MatchScoringPage extends StatefulWidget {

  final GameWizardConfig? config;

  final List<DrawingRound>? rounds;

  final AuthController? authController;

  final dynamic sessionId;

  final MatchService? matchService;
  final bool? allowFallbackRounds;

  final bool? isHost;

  final int? hostUserId;
  final int? initialRoundIndex;



  const MatchScoringPage({

    super.key,

    this.config,

    this.rounds,

    this.authController,

    this.sessionId,

    this.matchService,
    this.allowFallbackRounds,

    this.isHost,

    this.hostUserId,
    this.initialRoundIndex,

  });



  @override

  State<MatchScoringPage> createState() => MatchScoringPageState();

}



class _PendingScoreEvent {

  final String eventId;

  final int matchId;

  final int version;

  final ScoringMatchState snapshot;

  final DateTime createdAt;



  _PendingScoreEvent({

    required this.eventId,

    required this.matchId,

    required this.version,

    required this.snapshot,

    required this.createdAt,

  });

}



enum HostAccessStatus {

  verifying,

  host,

  spectator,

  error,

}



class MatchScoringPageState extends State<MatchScoringPage> {

  late GameWizardConfig _config;

  late List<DrawingRound> _rounds;

  late ScoringSystemConfig _scoringConfig;

  late MatchService _matchService;

  int _viewedRoundIndex = 0;

  int _sessionActiveRoundIndex = 0;

  int _selectedCourtIndex = 0;

  DateTime? _lastClickTime;



  RealtimeChannel? _realtimeChannel;

  bool _isRealtimeConnected = false;

  bool _isLoading = false;

  bool _isSessionLoading = false;

  bool _hasSessionLoadError = false;
  String? _sessionLoadErrorMessage;
  bool _isSessionFinished = false;
  bool _isAdvancingRound = false;
  int? _drawingId;

  int? _sessionHostUserId;



  final Map<int, List<_PendingScoreEvent>> _pendingEventsByMatch = {};

  final Map<int, Future<void>> _saveChains = {};

  final Map<int, Map<String, dynamic>> _conflictSnapshots = {};

  final Map<int, String> _matchSaveStatus = {}; // 'saved', 'saving', 'conflict', 'error'



  HostAccessStatus get hostAccessStatus {

    final user = widget.authController?.currentUser;



    // 1. Kebijakan Admin: Admin selalu memiliki hak akses Host penuh

    if (user != null && user.isAdmin) {

      return HostAccessStatus.host;

    }



    final resolvedHostId = _sessionHostUserId ?? widget.hostUserId;

    final hasBackendSession = widget.sessionId != null;



    // 2. Jika ada sesi backend

    if (hasBackendSession) {

      // Jika host ID sudah diketahui dan ada user yang login

      if (resolvedHostId != null && user != null) {

        return user.userId == resolvedHostId

            ? HostAccessStatus.host

            : HostAccessStatus.spectator;

      }



      // Jika user tidak login dan host ID sudah diketahui

      if (resolvedHostId != null && user == null && widget.authController != null && !widget.authController!.isLoading) {

        return HostAccessStatus.spectator;

      }



      // Jika masih dalam proses loading data sesi / identitas user

      if ((_isSessionLoading && resolvedHostId == null) ||

          (widget.authController != null && widget.authController!.isLoading)) {

        return HostAccessStatus.verifying;

      }



      // Jika gagal memuat sesi dan tidak ada hostUserId fallback

      if (_hasSessionLoadError && resolvedHostId == null) {

        return HostAccessStatus.error;

      }

    }



    // 3. Fallback jika ada explicit widget.isHost (misal untuk testing / mock mode)

    if (widget.isHost != null) {

      return widget.isHost! ? HostAccessStatus.host : HostAccessStatus.spectator;

    }



    // 4. Verifikasi berdasarkan hostUserId jika diberikan tanpa sessionId

    if (resolvedHostId != null && user != null) {

      return user.userId == resolvedHostId

          ? HostAccessStatus.host

          : HostAccessStatus.spectator;

    }



    // 5. Standalone / Preview mandiri

    return HostAccessStatus.host;

  }



  bool get isHost => hostAccessStatus == HostAccessStatus.host;

  bool get isRealBackendSession =>
      (widget.sessionId != null && widget.matchService == null) ||
      widget.allowFallbackRounds == false;



  bool get _isCurrentViewedRoundActive => _viewedRoundIndex == _sessionActiveRoundIndex;

  bool get _isAllRoundsFinished {
    if (_isSessionFinished) return true;
    if (_rounds.isEmpty) return false;
    return _rounds.every(
      (r) => r.matches.isNotEmpty &&
          r.matches.every((m) => m.status == 'Completed' || m.status == 'Stopped'),
    );
  }

  int _resolveActiveRoundIndex(List<DrawingRound> rounds) {
    if (rounds.isEmpty) return 0;

    for (int i = 0; i < rounds.length; i++) {
      final matches = rounds[i].matches;
      final isFinished = matches.isNotEmpty &&
          matches.every((m) => m.status == 'Completed' || m.status == 'Stopped');

      if (!isFinished) {
        return i;
      }

      // Jika ronde i sudah selesai tapi ronde berikutnya belum dimulai (belum ada skor/permainan berjalan),
      // maka ronde aktif tetap ronde i (menunggu Host klik "Lanjut ke Ronde i+1").
      if (i + 1 < rounds.length) {
        final nextMatches = rounds[i + 1].matches;
        final nextStarted = nextMatches.any(
          (m) =>
              m.status == 'In Progress' ||
              m.status == 'Completed' ||
              m.scoreA > 0 ||
              m.scoreB > 0 ||
              (m.pointDisplayA != '0' && m.pointDisplayA.isNotEmpty) ||
              (m.pointDisplayB != '0' && m.pointDisplayB.isNotEmpty) ||
              (m.idxA > 0) ||
              (m.idxB > 0),
        );
        if (!nextStarted) {
          return i;
        }
      }
    }

    return rounds.length - 1;
  }



  @override

  void initState() {

    super.initState();

    _sessionHostUserId = widget.hostUserId;

    _isSessionLoading = widget.sessionId != null;

    widget.authController?.addListener(_onAuthChanged);



    _matchService = widget.matchService ?? MatchService();

    _config =

        widget.config ??

        GameWizardConfig(

          activityName: 'Match Padel Tournament',

          venueName: null,

          players: [

            const GamePlayerItem(

              id: '1',

              name: 'Aku',

              level: 'Beginner',

              isGuest: true,

            ),

            const GamePlayerItem(

              id: '2',

              name: 'Kamu',

              level: 'Beginner',

              isGuest: true,

            ),

            const GamePlayerItem(

              id: '3',

              name: 'Dia',

              level: 'Beginner',

              isGuest: true,

            ),

            const GamePlayerItem(

              id: '4',

              name: 'Kita',

              level: 'Beginner',

              isGuest: true,

            ),

          ],

        );



    _scoringConfig = ScoringEngine.detectScoringSystem(_config.scoringSystem);



    if (isRealBackendSession && widget.rounds == null) {
      _rounds = [];
    } else {
      _rounds =
          widget.rounds ??
          MatchaDrawingEngine.generateDrawing(
            players: _config.players,
            courtCount: _config.courtCount,
            gameType: _config.gameType,
            playMode: _config.playMode,
            roundCount: _config.totalRounds,
          );
    }



    final resolved = _resolveActiveRoundIndex(_rounds);
    _sessionActiveRoundIndex = widget.initialRoundIndex != null
        ? widget.initialRoundIndex!.clamp(0, _rounds.isEmpty ? 0 : _rounds.length - 1)
        : resolved;

    _viewedRoundIndex = _sessionActiveRoundIndex;



    if (widget.sessionId != null) {

      _loadSessionAndScores();

    }

  }



  @override

  void dispose() {

    widget.authController?.removeListener(_onAuthChanged);

    if (_realtimeChannel != null) {

      _matchService.unsubscribe(_realtimeChannel);

    }

    super.dispose();

  }



  void _onAuthChanged() {

    if (mounted) setState(() {});

  }



  Future<void> _loadSessionAndScores() async {

    if (widget.sessionId == null) return;

    setState(() {

      _isLoading = true;

      _isSessionLoading = true;

      _hasSessionLoadError = false;
      _sessionLoadErrorMessage = null;

    });

    try {

      // 1. Ambil session info

      final sessionData = await _matchService.getSession(widget.sessionId);

      if (sessionData != null) {

        final rawHostId = sessionData['host_user_id'] ?? sessionData['user_id'];

        if (rawHostId != null) {

          final parsed = rawHostId is int ? rawHostId : int.tryParse(rawHostId.toString());

          if (parsed != null) {

            _sessionHostUserId = parsed;

          }

        }



        final scoringSysStr = sessionData['scoring_system']?.toString() ??

            sessionData['jenis_permainan']?.toString();

        if (scoringSysStr != null && scoringSysStr.isNotEmpty) {

          _config.scoringSystem = scoringSysStr;

          _scoringConfig = ScoringEngine.detectScoringSystem(scoringSysStr);

        }

        if (sessionData['nama_session'] != null) {
          _config.activityName = sessionData['nama_session'].toString();
        }
        if (sessionData['lokasi'] != null || sessionData['venue_name'] != null) {
          _config.venueName = (sessionData['lokasi'] ?? sessionData['venue_name']).toString();
        }
        if (sessionData['sport'] != null) {
          _config.sport = sessionData['sport'].toString();
        }
        final rawPlayMode = sessionData['jenis_permainan']?.toString() ?? sessionData['play_mode']?.toString();
        if (rawPlayMode != null && rawPlayMode.isNotEmpty) {
          _config.playMode = rawPlayMode;
        }
        final rawGameType = sessionData['format_pertandingan']?.toString() ?? sessionData['game_type']?.toString();
        if (rawGameType != null && rawGameType.isNotEmpty) {
          _config.gameType = rawGameType;
        }
        if (sessionData['total_round'] != null) {
          final tr = int.tryParse(sessionData['total_round'].toString());
          if (tr != null) _config.customTotalRounds = tr;
        }
        if (sessionData['total_court'] != null || sessionData['court_count'] != null) {
          final tc = int.tryParse((sessionData['total_court'] ?? sessionData['court_count']).toString());
          if (tc != null) _config.courtCount = tc;
        }
        final sStatus = (sessionData['status_session'] ?? '').toString().toLowerCase();
        if (sStatus == 'finished' || sStatus == 'completed') {
          _isSessionFinished = true;
        }

      } else {

        _hasSessionLoadError = true;

      }

      // 1.5 Ambil daftar pemain sesi aktual dari tb_session_player
      final sessionPlayers = await _matchService.getSessionPlayers(widget.sessionId);
      if (sessionPlayers.isNotEmpty) {
        _config.players = sessionPlayers;
      }

      // 2. Ambil matches & scores riil dari backend

      final dbMatches = await _matchService.getMatchesForSession(
        widget.sessionId,
        courtCount: _config.courtCount > 0 ? _config.courtCount : null,
      );

      if (dbMatches.isNotEmpty) {
        final rawFirst = dbMatches.first['rawMatch'] as Map<String, dynamic>?;
        _drawingId = rawFirst?['drawing_id'] as int?;

        final Map<int, List<DrawingMatch>> roundMap = {};

        for (final m in dbMatches) {

          final rawMatch = m['rawMatch'] as Map<String, dynamic>?;

          final nomorMatch = m['nomorMatch'] is int
              ? m['nomorMatch'] as int
              : (int.tryParse(m['nomorMatch']?.toString() ?? '') ?? 1);
          final effectiveCourtCount = _config.courtCount > 0 ? _config.courtCount : 1;
          final rNum = (m['roundNumber'] ?? rawMatch?['round_number'] ?? (((nomorMatch - 1) ~/ effectiveCourtCount) + 1)) as int;

          final courtNum = (m['courtNumber'] as int?) ?? (((nomorMatch - 1) % effectiveCourtCount) + 1);

          List<GamePlayerItem> teamAPlayers = [];
          if (m['teamAPlayers'] != null && (m['teamAPlayers'] as List).isNotEmpty) {
            teamAPlayers = (m['teamAPlayers'] as List).map((p) {
              final map = p as Map<String, dynamic>;
              final pId = map['playerId'] ?? map['id'];
              final name = map['name']?.toString() ?? 'Player A';
              final resolvedInt = MatchService.resolvePlayerId(
                GamePlayerItem(id: pId?.toString() ?? name, name: name),
                _config.players,
              );
              return GamePlayerItem(
                id: resolvedInt != null ? resolvedInt.toString() : (pId != null ? pId.toString() : name),
                playerId: resolvedInt,
                name: name,
                avatarUrl: map['avatarUrl']?.toString(),
                level: map['level']?.toString() ?? 'Beginner',
              );
            }).toList();
          } else if (m['sideA'] != null &&
              m['sideA'].toString().trim().isNotEmpty &&
              !m['sideA'].toString().toLowerCase().startsWith('side ') &&
              !m['sideA'].toString().toLowerCase().startsWith('team ')) {
            final sideANames = (m['sideA']).toString().split(' · ');
            teamAPlayers = sideANames.map((n) {
              final name = n.trim();
              final resolvedInt = MatchService.resolvePlayerId(
                GamePlayerItem(id: name, name: name),
                _config.players,
              );
              return GamePlayerItem(
                id: resolvedInt != null ? resolvedInt.toString() : name,
                playerId: resolvedInt,
                name: name,
              );
            }).toList();
          } else if (!isRealBackendSession) {
            final sideANames = (m['sideA'] ?? 'Team A').toString().split(' · ');
            teamAPlayers = sideANames.map((n) {
              final name = n.trim();
              final resolvedInt = MatchService.resolvePlayerId(
                GamePlayerItem(id: name, name: name),
                _config.players,
              );
              return GamePlayerItem(
                id: resolvedInt != null ? resolvedInt.toString() : name,
                playerId: resolvedInt,
                name: name,
              );
            }).toList();
          }

          List<GamePlayerItem> teamBPlayers = [];
          if (m['teamBPlayers'] != null && (m['teamBPlayers'] as List).isNotEmpty) {
            teamBPlayers = (m['teamBPlayers'] as List).map((p) {
              final map = p as Map<String, dynamic>;
              final pId = map['playerId'] ?? map['id'];
              final name = map['name']?.toString() ?? 'Player B';
              final resolvedInt = MatchService.resolvePlayerId(
                GamePlayerItem(id: pId?.toString() ?? name, name: name),
                _config.players,
              );
              return GamePlayerItem(
                id: resolvedInt != null ? resolvedInt.toString() : (pId != null ? pId.toString() : name),
                playerId: resolvedInt,
                name: name,
                avatarUrl: map['avatarUrl']?.toString(),
                level: map['level']?.toString() ?? 'Beginner',
              );
            }).toList();
          } else if (m['sideB'] != null &&
              m['sideB'].toString().trim().isNotEmpty &&
              !m['sideB'].toString().toLowerCase().startsWith('side ') &&
              !m['sideB'].toString().toLowerCase().startsWith('team ')) {
            final sideBNames = (m['sideB']).toString().split(' · ');
            teamBPlayers = sideBNames.map((n) {
              final name = n.trim();
              final resolvedInt = MatchService.resolvePlayerId(
                GamePlayerItem(id: name, name: name),
                _config.players,
              );
              return GamePlayerItem(
                id: resolvedInt != null ? resolvedInt.toString() : name,
                playerId: resolvedInt,
                name: name,
              );
            }).toList();
          } else if (!isRealBackendSession) {
            final sideBNames = (m['sideB'] ?? 'Team B').toString().split(' · ');
            teamBPlayers = sideBNames.map((n) {
              final name = n.trim();
              final resolvedInt = MatchService.resolvePlayerId(
                GamePlayerItem(id: name, name: name),
                _config.players,
              );
              return GamePlayerItem(
                id: resolvedInt != null ? resolvedInt.toString() : name,
                playerId: resolvedInt,
                name: name,
              );
            }).toList();
          }

          // Jika pada sesi backend riil susunan pemain gagal dimuat, jangan samarkan sebagai Side A/B
          if (isRealBackendSession && (teamAPlayers.isEmpty || teamBPlayers.isEmpty)) {
            _hasSessionLoadError = true;
            _sessionLoadErrorMessage = 'Gagal memuat susunan pemain pertandingan. Silakan coba lagi.';
            return;
          }



          final restored = ScoringEngine.restoreMatchState(

            gamesA: (m['scoreA'] ?? 0) as int,

            gamesB: (m['scoreB'] ?? 0) as int,

            rawPointA: m['pointScoreA']?.toString(),

            rawPointB: m['pointScoreB']?.toString(),

            setScoreA: m['setScoreA'] as int?,

            setScoreB: m['setScoreB'] as int?,

            matchStatus: m['status']?.toString(),

            statusScore: m['statusScore']?.toString(),

            winnerTeam: m['winnerTeam']?.toString(),

            version: (m['version'] ?? 0) as int,

            lastEventId: m['lastEventId']?.toString(),

            setNumber: rNum,

          );



          final rawStatus = (m['status'] ?? '').toString();
          final isExplicitStopped = rawStatus.toLowerCase() == 'stopped' ||
              (m['statusScore']?.toString().toLowerCase() == 'stopped');
          final isExplicitInProgress = rawStatus == 'In Progress' || rawStatus == 'Playing';
          final hasStartedScoring = restored.gamesA > 0 ||
              restored.gamesB > 0 ||
              restored.idxA > 0 ||
              restored.idxB > 0 ||
              (restored.pointDisplayA != '0' && restored.pointDisplayA.isNotEmpty) ||
              (restored.pointDisplayB != '0' && restored.pointDisplayB.isNotEmpty);

          final effectiveStatus = isExplicitStopped
              ? 'Stopped'
              : (restored.isCompleted
                  ? 'Completed'
                  : (isExplicitInProgress || hasStartedScoring
                      ? 'In Progress'
                      : (rawStatus.isNotEmpty ? rawStatus : 'Scheduled')));

          final drawingMatch = DrawingMatch(

            courtNumber: courtNum,

            teamA: teamAPlayers,

            teamB: teamBPlayers,

            status: effectiveStatus,

            scoreA: restored.gamesA,

            scoreB: restored.gamesB,

            gamesWonA: restored.gamesA,

            gamesWonB: restored.gamesB,

            idxA: restored.idxA,

            idxB: restored.idxB,

            pointDisplayA: restored.pointDisplayA,

            pointDisplayB: restored.pointDisplayB,

            isDeuce: restored.isDeuce,

            advantage: restored.advantage,

            setsA: restored.setsA,

            setsB: restored.setsB,

            winnerTeam: restored.winnerTeam,

            matchId: m['matchId'] as int?,

            version: restored.version,

            lastEventId: restored.lastEventId,

          );



          roundMap.putIfAbsent(rNum, () => []).add(drawingMatch);

        }



        if (roundMap.isNotEmpty) {

          final sortedRounds = roundMap.entries.map((entry) {

            return DrawingRound(

              roundNumber: entry.key,

              matches: entry.value,

            );

          }).toList();

          sortedRounds.sort((a, b) => a.roundNumber.compareTo(b.roundNumber));

          _rounds = sortedRounds;

          final resolved = _resolveActiveRoundIndex(_rounds);
          _sessionActiveRoundIndex = widget.initialRoundIndex != null
              ? widget.initialRoundIndex!.clamp(0, _rounds.length - 1)
              : resolved;
          _viewedRoundIndex = _sessionActiveRoundIndex;

        }

      } else {
        final savedDrawing = await _matchService.loadSavedDrawing(
          sessionId: widget.sessionId,
          courtCount: _config.courtCount > 0 ? _config.courtCount : null,
        );
        if (widget.sessionId != null && _drawingId == null) {
          _drawingId = await _matchService.getLatestDrawingId(widget.sessionId);
        }
        if (savedDrawing != null && savedDrawing.isNotEmpty) {
          _rounds = savedDrawing;
          final resolved = _resolveActiveRoundIndex(_rounds);
          _sessionActiveRoundIndex = widget.initialRoundIndex != null
              ? widget.initialRoundIndex!.clamp(0, _rounds.length - 1)
              : resolved;
          _viewedRoundIndex = _sessionActiveRoundIndex;
        } else if (isRealBackendSession && widget.rounds == null) {
          _rounds = [];
        }
      }

      // Diagnostic logging
      for (final r in _rounds) {
        for (final m in r.matches) {
          final aIds = m.teamA.map((p) => '${p.id}:${p.name}').join(',');
          final bIds = m.teamB.map((p) => '${p.id}:${p.name}').join(',');
          // ignore: avoid_print
          print('[MATCHA_DIAG] [Scoring] sessionId=${widget.sessionId} round=${r.roundNumber} court=${m.courtNumber} matchId=${m.matchId} teamA=[$aIds] teamB=[$bIds]');
        }
      }




      // 3. Daftarkan Supabase Realtime channel

      _subscribeRealtime();

    } catch (_) {

      _hasSessionLoadError = true;

    } finally {

      if (mounted) {

        setState(() {

          _isLoading = false;

          _isSessionLoading = false;

        });

      }

    }

  }



  void _subscribeRealtime() {
    if (widget.sessionId == null) return;

    if (_realtimeChannel != null) {
      _matchService.unsubscribe(_realtimeChannel);
      _realtimeChannel = null;
    }

    try {
      _realtimeChannel = _matchService.subscribeLiveSession(
        sessionId: widget.sessionId,
        onDataChanged: _onRealtimeScoreChanged,
        onRoundAdvanced: (payload) {
          if (!mounted) return;
          debugPrint('[MATCHA_DEBUG] [RoundAdvance] Diterima payload: $payload (sessionId=${widget.sessionId}, drawingId=$_drawingId, activeRoundIndex=$_sessionActiveRoundIndex)');
          int? nextRoundNum;
          final raw = payload['next_round_num'] ??
              payload['next_round'] ??
              payload['active_round'] ??
              payload['session_active_round'];
          if (raw is int) {
            nextRoundNum = raw;
          } else if (raw != null) {
            final digits = RegExp(r'\d+').firstMatch(raw.toString())?.group(0);
            if (digits != null) {
              nextRoundNum = int.tryParse(digits);
            }
          }

          if (nextRoundNum == null || nextRoundNum <= 0) {
            debugPrint('[MATCHA_DEBUG] [RoundAdvance] Ditolak: Gagal parse nomor ronde dari raw=$raw (payload=$payload)');
            return;
          }

          final nextIdx = nextRoundNum - 1;
          if (nextIdx <= _sessionActiveRoundIndex) {
            debugPrint('[MATCHA_DEBUG] [RoundAdvance] Ditolak: Event lama/duplikat (target nextIdx=$nextIdx <= active=$_sessionActiveRoundIndex, sessionId=${widget.sessionId}, drawingId=$_drawingId)');
            return;
          }

          if (nextIdx >= _rounds.length) {
            debugPrint('[MATCHA_DEBUG] [RoundAdvance] Ditolak: Target nextIdx=$nextIdx melampaui total rounds (${_rounds.length}, sessionId=${widget.sessionId}, drawingId=$_drawingId)');
            return;
          }

          debugPrint('[MATCHA_DEBUG] [RoundAdvance] Diterapkan: Transisi ronde $_sessionActiveRoundIndex -> $nextIdx (Ronde $nextRoundNum, sessionId=${widget.sessionId}, drawingId=$_drawingId)');
          final wasViewingHistorical = _viewedRoundIndex < _sessionActiveRoundIndex;
          setState(() {
            _sessionActiveRoundIndex = nextIdx;
            // Spectator Mode Protection: Jika spectator/player sedang melihat ronde lama, jangan paksa navigasi
            if (!wasViewingHistorical || isHost) {
              _viewedRoundIndex = nextIdx;
              _selectedCourtIndex = 0;
            }
            for (final m in _rounds[nextIdx].matches) {
              if (m.status == 'Scheduled') {
                m.status = 'In Progress';
              }
            }
          });

          // Player / Spectator Auto-Transition to DrawingResultPage:
          if (!isHost && !wasViewingHistorical && mounted) {
            final isTeamFormat = _config.gameType.toLowerCase().contains('team');
            final unitLabel = isTeamFormat ? 'Set' : 'Ronde';
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('🏆 Host telah memulai $unitLabel $nextRoundNum! Membuka jadwal & drawing...'),
                backgroundColor: AppColors.matchaDark,
                behavior: SnackBarBehavior.floating,
                duration: const Duration(seconds: 3),
              ),
            );
            Navigator.pushReplacement(
              context,
              MaterialPageRoute(
                builder: (_) => DrawingResultPage(
                  sessionId: widget.sessionId,
                  config: _config,
                  initialRounds: _rounds,
                  initialRoundIndex: nextIdx,
                  authController: widget.authController,
                  matchService: _matchService,
                  isHost: false,
                  hostUserId: widget.hostUserId,
                ),
              ),
            );
          }
        },
        onSessionFinished: (payload) {
          if (!mounted) return;
          setState(() {
            _isSessionFinished = true;
          });
        },
      );
      _isRealtimeConnected = true;
    } catch (_) {
      _isRealtimeConnected = false;
    }
  }



  Future<void> _onRealtimeScoreChanged() async {

    if (widget.sessionId == null || !mounted) return;

    try {
      if (_rounds.isEmpty) {
        await _loadSessionAndScores();
        return;
      }

      final dbMatches = await _matchService.getMatchesForSession(
        widget.sessionId,
        courtCount: _config.courtCount > 0 ? _config.courtCount : null,
      );

      if (dbMatches.isEmpty) return;



      setState(() {

        for (final m in dbMatches) {

          final matchId = m['matchId'] as int?;

          if (matchId == null) continue;



          for (final round in _rounds) {

            for (final localMatch in round.matches) {

              if (localMatch.matchId == matchId) {

                final incomingVersion = (m['version'] ?? 0) as int;

                final incomingLastEventId = m['lastEventId']?.toString();

                final pendingList = _pendingEventsByMatch[matchId] ?? [];



                if (pendingList.isNotEmpty) {

                  // Check if incoming payload is an Acknowledgement for our pending event

                  final ackIdx = pendingList.indexWhere(

                    (e) => e.eventId == incomingLastEventId,

                  );

                  if (ackIdx >= 0) {

                    // Ack matched! Remove this and any prior superseded events

                    pendingList.removeRange(0, ackIdx + 1);

                    if (pendingList.isEmpty) {

                      _matchSaveStatus[matchId] = 'saved';

                    }

                    localMatch.version = incomingVersion;

                    continue;

                  }



                  // Not our ack. Check for conflict or newer authoritative server version

                  if (incomingVersion >= localMatch.version) {

                    _conflictSnapshots[matchId] = m;

                    _matchSaveStatus[matchId] = 'conflict';

                    continue;

                  }

                  // incomingVersion < localMatch.version: Stale snapshot, ignore

                  continue;

                }



                // No pending events: apply authoritative snapshot if monotonic

                if (incomingVersion >= localMatch.version) {

                  final restored = ScoringEngine.restoreMatchState(

                    gamesA: (m['scoreA'] ?? 0) as int,

                    gamesB: (m['scoreB'] ?? 0) as int,

                    rawPointA: m['pointScoreA']?.toString(),

                    rawPointB: m['pointScoreB']?.toString(),

                    setScoreA: m['setScoreA'] as int?,

                    setScoreB: m['setScoreB'] as int?,

                    matchStatus: m['status']?.toString(),

                    statusScore: m['statusScore']?.toString(),

                    winnerTeam: m['winnerTeam']?.toString(),

                    version: incomingVersion,

                    lastEventId: incomingLastEventId,

                    setNumber: round.roundNumber,

                  );



                  localMatch.scoreA = restored.gamesA;

                  localMatch.scoreB = restored.gamesB;

                  localMatch.gamesWonA = restored.gamesA;

                  localMatch.gamesWonB = restored.gamesB;

                  localMatch.idxA = restored.idxA;

                  localMatch.idxB = restored.idxB;

                  localMatch.pointDisplayA = restored.pointDisplayA;

                  localMatch.pointDisplayB = restored.pointDisplayB;

                  localMatch.isDeuce = restored.isDeuce;

                  localMatch.advantage = restored.advantage;

                  localMatch.setsA = restored.setsA;

                  localMatch.setsB = restored.setsB;

                  final rawStatus = (m['status'] ?? '').toString();
                  final isExplicitStopped = rawStatus.toLowerCase() == 'stopped' ||
                      m['statusScore']?.toString().toLowerCase() == 'stopped' ||
                      localMatch.isStopped ||
                      localMatch.status.toLowerCase() == 'stopped';
                  final isExplicitInProgress = rawStatus == 'In Progress' || rawStatus == 'Playing';
                  final hasStartedScoring = restored.gamesA > 0 ||
                      restored.gamesB > 0 ||
                      restored.idxA > 0 ||
                      restored.idxB > 0 ||
                      (restored.pointDisplayA != '0' && restored.pointDisplayA.isNotEmpty) ||
                      (restored.pointDisplayB != '0' && restored.pointDisplayB.isNotEmpty);

                  if (isExplicitStopped) {
                    localMatch.status = 'Stopped';
                  } else if (restored.isCompleted) {
                    localMatch.status = 'Completed';
                  } else if (isExplicitInProgress || hasStartedScoring) {
                    localMatch.status = 'In Progress';
                  } else if (rawStatus.isNotEmpty) {
                    localMatch.status = rawStatus;
                  }

                  localMatch.winnerTeam = restored.winnerTeam;

                  localMatch.version = restored.version;

                  localMatch.lastEventId = restored.lastEventId;

                  // Pertahankan susunan pemain yang sudah valid, jangan pernah menimpa dengan kosong
                  final incTeamA = m['teamAPlayers'] as List?;
                  if (incTeamA != null && incTeamA.isNotEmpty) {
                    final isCurrentPlaceholder = localMatch.teamA.isEmpty ||
                        localMatch.teamA.every((p) =>
                            p.name.trim().isEmpty ||
                            p.name == 'Side A' ||
                            p.name == 'Side B' ||
                            p.name == 'Team A' ||
                            p.name == 'Team B');
                    if (isCurrentPlaceholder) {
                      localMatch.teamA.clear();
                      localMatch.teamA.addAll(incTeamA.map((p) {
                        final map = p as Map<String, dynamic>;
                        final pId = map['playerId'] ?? map['id'];
                        return GamePlayerItem(
                          id: pId != null ? pId.toString() : (map['name']?.toString() ?? ''),
                          name: map['name']?.toString() ?? 'Player A',
                          avatarUrl: map['avatarUrl']?.toString(),
                          level: map['level']?.toString() ?? 'Beginner',
                        );
                      }));
                    }
                  }

                  final incTeamB = m['teamBPlayers'] as List?;
                  if (incTeamB != null && incTeamB.isNotEmpty) {
                    final isCurrentPlaceholder = localMatch.teamB.isEmpty ||
                        localMatch.teamB.every((p) =>
                            p.name.trim().isEmpty ||
                            p.name == 'Side A' ||
                            p.name == 'Side B' ||
                            p.name == 'Team A' ||
                            p.name == 'Team B');
                    if (isCurrentPlaceholder) {
                      localMatch.teamB.clear();
                      localMatch.teamB.addAll(incTeamB.map((p) {
                        final map = p as Map<String, dynamic>;
                        final pId = map['playerId'] ?? map['id'];
                        return GamePlayerItem(
                          id: pId != null ? pId.toString() : (map['name']?.toString() ?? ''),
                          name: map['name']?.toString() ?? 'Player B',
                          avatarUrl: map['avatarUrl']?.toString(),
                          level: map['level']?.toString() ?? 'Beginner',
                        );
                      }));
                    }
                  }

                  _matchSaveStatus[matchId] = 'saved';

                  _conflictSnapshots.remove(matchId);

                }

              }

            }

          }

        }

        // Rekonsiliasi persistensi resmi:
        // Jika Player sempat melewatkan event realtime round_advanced,
        // cek apakah tb_match dari backend sudah menunjukkan bahwa Host memajukan ke ronde berikutnya.
        final persistentActiveRound = _resolveActiveRoundIndex(_rounds);
        if (persistentActiveRound > _sessionActiveRoundIndex) {
          debugPrint('[MATCHA_DEBUG] [Reconciliation] Terdeteksi ronde persisten lebih baru: $_sessionActiveRoundIndex -> $persistentActiveRound (sessionId=${widget.sessionId}, drawingId=$_drawingId)');
          _sessionActiveRoundIndex = persistentActiveRound;
          _viewedRoundIndex = persistentActiveRound;
          _selectedCourtIndex = 0;
          for (final m in _rounds[persistentActiveRound].matches) {
            if (m.status == 'Scheduled') {
              m.status = 'In Progress';
            }
          }
        }
      });

    } catch (_) {}

  }



  void _addPointTeamA(DrawingMatch match) {

    if (!isHost) return;

    if (!_isCurrentViewedRoundActive) return;

    if (match.isTerminal || match.isStopped || match.status.toLowerCase() == 'stopped' || match.status == 'Completed') return;



    // Micro-Debounce (300ms)

    final now = DateTime.now();

    if (_lastClickTime != null &&

        now.difference(_lastClickTime!).inMilliseconds < 300) {

      return;

    }

    _lastClickTime = now;



    final eventId = 'evt_mob_${now.millisecondsSinceEpoch}_A';



    final currentState = ScoringMatchState(

      gamesA: match.scoreA,

      gamesB: match.scoreB,

      idxA: match.idxA,

      idxB: match.idxB,

      pointDisplayA: match.pointDisplayA,

      pointDisplayB: match.pointDisplayB,

      isDeuce: match.isDeuce,

      advantage: match.advantage,

      setNumber: _sessionActiveRoundIndex + 1,

      setsA: match.setsA,

      setsB: match.setsB,

      status: match.status == 'Completed' ? 'completed' : 'in_progress',

      winnerTeam: match.winnerTeam,

      version: match.version,

      lastEventId: match.lastEventId,

    );



    final nextState = ScoringEngine.applyPointDelta(

      currentState: currentState,

      teamWon: 'A',

      scoringSystem: _scoringConfig,

      eventId: eventId,

    );



    setState(() {

      match.scoreA = nextState.gamesA;

      match.scoreB = nextState.gamesB;

      match.gamesWonA = nextState.gamesA;

      match.gamesWonB = nextState.gamesB;

      match.idxA = nextState.idxA;

      match.idxB = nextState.idxB;

      match.pointDisplayA = nextState.pointDisplayA;

      match.pointDisplayB = nextState.pointDisplayB;

      match.isDeuce = nextState.isDeuce;

      match.advantage = nextState.advantage;

      match.setsA = nextState.setsA;

      match.setsB = nextState.setsB;

      match.winnerTeam = nextState.winnerTeam;

      match.version = nextState.version;

      match.lastEventId = eventId;

      match.status = nextState.isCompleted ? 'Completed' : 'In Progress';

    });



    _enqueueScoreSave(match, nextState, eventId);

  }



  void _addPointTeamB(DrawingMatch match) {

    if (!isHost) return;

    if (!_isCurrentViewedRoundActive) return;

    if (match.isTerminal || match.isStopped || match.status.toLowerCase() == 'stopped' || match.status == 'Completed') return;



    // Micro-Debounce (300ms)

    final now = DateTime.now();

    if (_lastClickTime != null &&

        now.difference(_lastClickTime!).inMilliseconds < 300) {

      return;

    }

    _lastClickTime = now;



    final eventId = 'evt_mob_${now.millisecondsSinceEpoch}_B';



    final currentState = ScoringMatchState(

      gamesA: match.scoreA,

      gamesB: match.scoreB,

      idxA: match.idxA,

      idxB: match.idxB,

      pointDisplayA: match.pointDisplayA,

      pointDisplayB: match.pointDisplayB,

      isDeuce: match.isDeuce,

      advantage: match.advantage,

      setNumber: _sessionActiveRoundIndex + 1,

      setsA: match.setsA,

      setsB: match.setsB,

      status: match.status == 'Completed' ? 'completed' : 'in_progress',

      winnerTeam: match.winnerTeam,

      version: match.version,

      lastEventId: match.lastEventId,

    );



    final nextState = ScoringEngine.applyPointDelta(

      currentState: currentState,

      teamWon: 'B',

      scoringSystem: _scoringConfig,

      eventId: eventId,

    );



    setState(() {

      match.scoreA = nextState.gamesA;

      match.scoreB = nextState.gamesB;

      match.gamesWonA = nextState.gamesA;

      match.gamesWonB = nextState.gamesB;

      match.idxA = nextState.idxA;

      match.idxB = nextState.idxB;

      match.pointDisplayA = nextState.pointDisplayA;

      match.pointDisplayB = nextState.pointDisplayB;

      match.isDeuce = nextState.isDeuce;

      match.advantage = nextState.advantage;

      match.setsA = nextState.setsA;

      match.setsB = nextState.setsB;

      match.winnerTeam = nextState.winnerTeam;

      match.version = nextState.version;

      match.lastEventId = eventId;

      match.status = nextState.isCompleted ? 'Completed' : 'In Progress';

    });



    _enqueueScoreSave(match, nextState, eventId);

  }


  void _enqueueScoreSave(

    DrawingMatch match,

    ScoringMatchState state,

    String eventId,

  ) {

    final matchId = match.matchId;

    if (widget.sessionId == null || matchId == null) return;

    if (match.isTerminal || match.isStopped || match.status.toLowerCase() == 'stopped' || match.status == 'Completed') return;



    final event = _PendingScoreEvent(

      eventId: eventId,

      matchId: matchId,

      version: state.version,

      snapshot: state,

      createdAt: DateTime.now(),

    );



    _pendingEventsByMatch.putIfAbsent(matchId, () => []).add(event);

    setState(() {

      _matchSaveStatus[matchId] = 'saving';

    });



    // Chain saves sequentially per matchId

    _saveChains[matchId] = (_saveChains[matchId] ?? Future.value()).then((_) async {

      // Do not proceed with next save if this match has an unresolved conflict

      if (_conflictSnapshots.containsKey(matchId)) {

        return;

      }

      await _executePersistEvent(match, event);

    }).catchError((err) {

      if (mounted) {

        setState(() {

          _matchSaveStatus[matchId] = 'error';

        });

      }

    });

  }



  Future<void> _executePersistEvent(

    DrawingMatch match,

    _PendingScoreEvent event,

  ) async {

    final state = event.snapshot;

    final matchId = event.matchId;



    try {

      await _matchService.saveMatchScore(

        matchId: matchId,

        sessionId: widget.sessionId,

        setNumber: state.setNumber,

        scoreA: state.gamesA,

        scoreB: state.gamesB,

        gameScoreA: state.gamesA,

        gameScoreB: state.gamesB,

        pointScoreA: state.pointDisplayA,

        pointScoreB: state.pointDisplayB,

        setScoreA: state.setsA,

        setScoreB: state.setsB,

        scoringSystem: _scoringConfig.label,

        statusScore: state.isCompleted ? 'Final' : 'In Progress',

        winnerTeam: state.winnerTeam,

        version: state.version,

        lastEventId: event.eventId,

        matchKey: 'round_${state.setNumber}_court_${match.courtNumber}',

      );



      if (mounted) {

        setState(() {

          _pendingEventsByMatch[matchId]?.removeWhere((e) => e.eventId == event.eventId);

          if ((_pendingEventsByMatch[matchId] ?? []).isEmpty) {

            _matchSaveStatus[matchId] = 'saved';

          }

        });

      }

    } catch (e) {

      if (mounted) {

        setState(() {

          _matchSaveStatus[matchId] = 'error';

        });

      }

      rethrow;

    }

  }



  void _resolveConflictByAcceptingServer(int matchId) {

    final serverData = _conflictSnapshots.remove(matchId);

    _pendingEventsByMatch[matchId]?.clear();

    if (serverData == null) return;



    setState(() {

      for (final round in _rounds) {

        for (final localMatch in round.matches) {

          if (localMatch.matchId == matchId) {

            final restored = ScoringEngine.restoreMatchState(

              gamesA: (serverData['scoreA'] ?? 0) as int,

              gamesB: (serverData['scoreB'] ?? 0) as int,

              rawPointA: serverData['pointScoreA']?.toString(),

              rawPointB: serverData['pointScoreB']?.toString(),

              setScoreA: serverData['setScoreA'] as int?,

              setScoreB: serverData['setScoreB'] as int?,

              matchStatus: serverData['status']?.toString(),

              statusScore: serverData['statusScore']?.toString(),

              winnerTeam: serverData['winnerTeam']?.toString(),

              version: (serverData['version'] ?? 0) as int,

              lastEventId: serverData['lastEventId']?.toString(),

              setNumber: round.roundNumber,

            );



            localMatch.scoreA = restored.gamesA;

            localMatch.scoreB = restored.gamesB;

            localMatch.gamesWonA = restored.gamesA;

            localMatch.gamesWonB = restored.gamesB;

            localMatch.idxA = restored.idxA;

            localMatch.idxB = restored.idxB;

            localMatch.pointDisplayA = restored.pointDisplayA;

            localMatch.pointDisplayB = restored.pointDisplayB;

            localMatch.isDeuce = restored.isDeuce;

            localMatch.advantage = restored.advantage;

            localMatch.setsA = restored.setsA;

            localMatch.setsB = restored.setsB;

            localMatch.status = restored.isCompleted ? 'Completed' : 'In Progress';

            localMatch.winnerTeam = restored.winnerTeam;

            localMatch.version = restored.version;

            localMatch.lastEventId = restored.lastEventId;

            _matchSaveStatus[matchId] = 'saved';

          }

        }

      }

    });

  }



  Future<void> _refetchAndRecover(int matchId) async {

    _conflictSnapshots.remove(matchId);

    _pendingEventsByMatch[matchId]?.clear();

    _saveChains[matchId] = Future.value();

    await _onRealtimeScoreChanged();

  }



  @visibleForTesting

  void simulateConflictForTesting({

    required int matchId,

    required Map<String, dynamic> serverSnapshot,

  }) {

    setState(() {

      _conflictSnapshots[matchId] = serverSnapshot;

    });

  }



  void _selectRound(int idx) {
    if (idx < 0 || idx >= _rounds.length) return;
    if (idx > _sessionActiveRoundIndex) return;
    setState(() {
      _viewedRoundIndex = idx;
      _selectedCourtIndex = 0;
    });
  }

  Future<void> _advanceToNextRound() async {
    if (!isHost) return;
    if (_isAdvancingRound) return;
    if (_sessionActiveRoundIndex >= _rounds.length - 1) return;

    // GAP 3: Validasi kecukupan pemain eligible (exclude withdrawn)
    final isSingle = _config.playMode.toLowerCase() == 'single';
    final minRequired = isSingle ? 2 : 4;
    final eligiblePlayers = _config.players.where((p) => !p.isWithdrawn).toList();

    if (_config.players.isNotEmpty && eligiblePlayers.length < minRequired) {
      await showDialog(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: Row(
            children: const [
              Icon(Icons.warning_amber_rounded, color: Color(0xFFDC2626)),
              SizedBox(width: 8),
              Text('Pemain Tidak Mencukupi', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
            ],
          ),
          content: Text(
            'Jumlah pemain aktif tersisa (${eligiblePlayers.length} pemain) kurang dari batas minimum ($minRequired pemain untuk ${_config.playMode}) karena pemain mengundurkan diri. Sesi pertandingan diselesaikan lebih awal.',
            style: const TextStyle(fontSize: 12),
          ),
          actions: [
            ElevatedButton(
              onPressed: () {
                Navigator.pop(ctx);
                _finishSessionAndShowRecap();
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.matchaDark,
                foregroundColor: Colors.white,
              ),
              child: const Text('Selesaikan Sesi & Buka Podium'),
            ),
          ],
        ),
      );
      return;
    }

    _isAdvancingRound = true;
    try {
      final currentRoundNum = _sessionActiveRoundIndex + 1;
      final nextRoundNum = _sessionActiveRoundIndex + 2;
      final nextIdx = _sessionActiveRoundIndex + 1;

      debugPrint('[MATCHA_DEBUG] [HostAdvance] Memulai transisi: sessionId=${widget.sessionId}, drawingId=$_drawingId, Ronde $currentRoundNum -> Ronde $nextRoundNum (index $_sessionActiveRoundIndex -> $nextIdx)');

      if (widget.sessionId != null) {
        final nextMatches = _rounds[nextIdx].matches;
        final matchIds = nextMatches.map((m) => m.matchId).whereType<int>().toList();
        
        // 1. Simpan transisi ronde ke tb_match secara resmi
        await _matchService.advanceRoundMatches(
          sessionId: widget.sessionId,
          roundNumber: nextRoundNum,
          matchIds: matchIds,
        );

        // 2. Broadcast event round_advanced via realtime channel aktif
        await _matchService.broadcastRoundAdvanced(
          sessionId: widget.sessionId,
          currentRoundNum: currentRoundNum,
          nextRoundNum: nextRoundNum,
          activeChannel: _realtimeChannel,
        );
      }

      // 3. Perbarui state Host setelah persistensi dan broadcast selesai
      setState(() {
        _sessionActiveRoundIndex = nextIdx;
        _viewedRoundIndex = _sessionActiveRoundIndex;
        _selectedCourtIndex = 0;
        for (final m in _rounds[_sessionActiveRoundIndex].matches) {
          if (m.status == 'Scheduled') {
            m.status = 'In Progress';
          }
        }
      });

      debugPrint('[MATCHA_DEBUG] [HostAdvance] Berhasil transisi: Host sekarang di Ronde $nextRoundNum (index $_sessionActiveRoundIndex, sessionId=${widget.sessionId}, drawingId=$_drawingId)');

      // Arahkan Host ke DrawingResultPage untuk menampilkan drawing ronde berikutnya
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => DrawingResultPage(
              sessionId: widget.sessionId,
              config: _config,
              initialRounds: _rounds,
              initialRoundIndex: nextIdx,
              authController: widget.authController,
              matchService: _matchService,
              isHost: true,
              hostUserId: widget.hostUserId,
            ),
          ),
        );
      }
    } catch (e) {
      debugPrint('[MATCHA_ERROR] [HostAdvance] Gagal transisi ronde: $e');
    } finally {
      _isAdvancingRound = false;
    }
  }

  // --- GAP 5: Stop Match Action & Dialog ---
  Future<void> _showStopMatchDialog(DrawingMatch match) async {
    if (!isHost) return;
    String stopCondition = 'keep_in_session'; // 'keep_in_session' or 'withdraw_players'
    final reasonControllerA = TextEditingController();
    final reasonFocusNodeA = FocusNode();
    String? reasonFieldErrorA;
    bool pausePlayersToggle = false;
    final Set<String> pausedPlayerIds = {};
    final pauseReasonController = TextEditingController();
    String? selectionError;

    final Set<String> withdrawnPlayerIds = {};
    final Map<String, TextEditingController> playerReasonControllers = {};
    final Map<String, FocusNode> playerReasonFocusNodes = {};
    final Map<String, String?> playerReasonErrors = {};

    final allMatchPlayers = [...match.teamA, ...match.teamB];
    for (final p in allMatchPlayers) {
      playerReasonControllers[p.id] = TextEditingController();
      playerReasonFocusNodes[p.id] = FocusNode();
    }
    String? validationError;

    final result = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDlgState) {
            return AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              titlePadding: const EdgeInsets.fromLTRB(20, 18, 16, 10),
              contentPadding: const EdgeInsets.symmetric(horizontal: 20),
              title: Row(
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFFECACA)),
                    ),
                    child: const Icon(Icons.front_hand_rounded, color: Color(0xFFDC2626), size: 20),
                  ),
                  const SizedBox(width: 10),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Akhiri Pertandingan (End Match)',
                          style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                        ),
                        Text(
                          'Hentikan match aktif ini tanpa menyelesaikan seluruh sesi',
                          style: TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(ctx, false),
                    icon: const Icon(Icons.close_rounded, size: 18, color: Color(0xFF64748B)),
                    constraints: const BoxConstraints(),
                    padding: const EdgeInsets.all(4),
                  ),
                ],
              ),
              content: SizedBox(
                width: 500,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Match Snapshot Box
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Column(
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Row(
                                  children: [
                                    Container(
                                      width: 6,
                                      height: 6,
                                      decoration: const BoxDecoration(
                                        color: Color(0xFF16A34A),
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    Text(
                                      'Ronde ${_viewedRoundIndex + 1} • Court ${match.courtNumber}',
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF334155)),
                                    ),
                                  ],
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2.5),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(8),
                                    border: Border.all(color: const Color(0xFFFDE68A)),
                                  ),
                                  child: Text(
                                    'Skor Terakhir: ${match.scoreA} — ${match.scoreB} Games (Point: ${match.pointDisplayA} : ${match.pointDisplayB})',
                                    style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                                  ),
                                ),
                              ],
                            ),
                            const Divider(height: 14),
                            Row(
                              children: [
                                Expanded(
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 8),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: const Color(0xFFCBD5E1)),
                                    ),
                                    child: Text(
                                      'Tim A: ${match.teamANames}',
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF1E293B)),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ),
                                const Padding(
                                  padding: EdgeInsets.symmetric(horizontal: 8),
                                  child: Text('VS', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: Color(0xFF94A3B8))),
                                ),
                                Expanded(
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 8),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: const Color(0xFFCBD5E1)),
                                    ),
                                    child: Text(
                                      'Tim B: ${match.teamBNames}',
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF1E293B)),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),

                      // Condition Selector (2 Options)
                      const Text('Pilih Kondisi Penghentian *', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
                      const SizedBox(height: 6),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Condition 1
                          Expanded(
                            child: InkWell(
                              onTap: () {
                                setDlgState(() {
                                  stopCondition = 'keep_in_session';
                                  validationError = null;
                                  selectionError = null;
                                  reasonFieldErrorA = null;
                                  playerReasonErrors.clear();
                                });
                              },
                              borderRadius: BorderRadius.circular(12),
                              child: Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: stopCondition == 'keep_in_session' ? const Color(0xFFECFDF5) : Colors.white,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: stopCondition == 'keep_in_session' ? const Color(0xFF059669) : const Color(0xFFE2E8F0),
                                    width: stopCondition == 'keep_in_session' ? 1.5 : 1,
                                  ),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Icon(
                                          stopCondition == 'keep_in_session' ? Icons.radio_button_checked : Icons.radio_button_off,
                                          size: 16,
                                          color: stopCondition == 'keep_in_session' ? const Color(0xFF059669) : const Color(0xFF94A3B8),
                                        ),
                                        const SizedBox(width: 6),
                                        const Expanded(
                                          child: Text(
                                            '1. Tetap di Sesi (Pemain Lanjut)',
                                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 10.5, color: Color(0xFF0F172A)),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 4),
                                    const Text(
                                      'Match berhenti, semua pemain lanjut ke ronde berikutnya atau istirahat sejenak ke bench.',
                                      style: TextStyle(fontSize: 9.5, color: Color(0xFF64748B), height: 1.2),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          // Condition 2
                          Expanded(
                            child: InkWell(
                              onTap: () {
                                setDlgState(() {
                                  stopCondition = 'withdraw_players';
                                  validationError = null;
                                  selectionError = null;
                                  reasonFieldErrorA = null;
                                  playerReasonErrors.clear();
                                });
                              },
                              borderRadius: BorderRadius.circular(12),
                              child: Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: stopCondition == 'withdraw_players' ? const Color(0xFFFEF2F2) : Colors.white,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: stopCondition == 'withdraw_players' ? const Color(0xFFDC2626) : const Color(0xFFE2E8F0),
                                    width: stopCondition == 'withdraw_players' ? 1.5 : 1,
                                  ),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Icon(
                                          stopCondition == 'withdraw_players' ? Icons.radio_button_checked : Icons.radio_button_off,
                                          size: 16,
                                          color: stopCondition == 'withdraw_players' ? const Color(0xFFDC2626) : const Color(0xFF94A3B8),
                                        ),
                                        const SizedBox(width: 6),
                                        const Expanded(
                                          child: Text(
                                            '2. Keluar Sesi (Permanen)',
                                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 10.5, color: Color(0xFF0F172A)),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 4),
                                    const Text(
                                      'Pemain cedera berat atau harus pulang. Ditarik permanen dari seluruh sisa ronde.',
                                      style: TextStyle(fontSize: 9.5, color: Color(0xFF64748B), height: 1.2),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      // Condition 1 Inputs
                      if (stopCondition == 'keep_in_session') ...[
                        Row(
                          children: [
                            const Text('Alasan Penghentian *', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
                            if (reasonFieldErrorA != null) ...[
                              const SizedBox(width: 6),
                              Text('($reasonFieldErrorA)', style: const TextStyle(fontSize: 10, color: Color(0xFFDC2626), fontWeight: FontWeight.w600)),
                            ],
                          ],
                        ),
                        const SizedBox(height: 4),
                        TextField(
                          controller: reasonControllerA,
                          focusNode: reasonFocusNodeA,
                          onChanged: (val) => setDlgState(() {
                            validationError = null;
                            if (val.trim().isNotEmpty) reasonFieldErrorA = null;
                          }),
                          decoration: InputDecoration(
                            hintText: 'Contoh: Waktu sewa lapangan habis, court basah karena hujan, dll.',
                            hintStyle: const TextStyle(fontSize: 10.5, color: Color(0xFF94A3B8)),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                            enabledBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                              borderSide: BorderSide(
                                color: reasonFieldErrorA != null ? const Color(0xFFDC2626) : const Color(0xFFCBD5E1),
                                width: reasonFieldErrorA != null ? 1.5 : 1.0,
                              ),
                            ),
                            focusedBorder: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                              borderSide: BorderSide(
                                color: reasonFieldErrorA != null ? const Color(0xFFDC2626) : AppColors.matchaDark,
                                width: 1.5,
                              ),
                            ),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                            isDense: true,
                            errorText: reasonFieldErrorA,
                            errorStyle: const TextStyle(fontSize: 10, color: Color(0xFFDC2626), fontWeight: FontWeight.w600),
                          ),
                        ),
                        const SizedBox(height: 10),
                        // Bench option box
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFFBEB),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFFFDE68A)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: Checkbox(
                                      value: pausePlayersToggle,
                                      activeColor: const Color(0xFFD97706),
                                      onChanged: (v) {
                                        setDlgState(() {
                                          pausePlayersToggle = v ?? false;
                                          if (!pausePlayersToggle) pausedPlayerIds.clear();
                                          validationError = null;
                                          selectionError = null;
                                        });
                                      },
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  const Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          'Istirahatkan pemain tertentu sementara ke Bangku Cadangan (Bench)',
                                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                                        ),
                                        SizedBox(height: 2),
                                        Text(
                                          'Centang opsi ini jika ada pemain yang butuh istirahat sejenak (ke toilet / kram ringan).',
                                          style: TextStyle(fontSize: 9.5, color: Color(0xFFB45309)),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              if (pausePlayersToggle) ...[
                                const Divider(height: 14),
                                const Text(
                                  'Pilih pemain yang butuh jeda istirahat (pemain masuk cadangan sampai Host menekan tombol Siap Main):',
                                  style: TextStyle(fontSize: 10, color: Color(0xFF78350F), fontWeight: FontWeight.w500),
                                ),
                                const SizedBox(height: 6),
                                ...allMatchPlayers.map((p) {
                                  final checked = pausedPlayerIds.contains(p.id);
                                  final isTeamA = match.teamA.any((x) => x.id == p.id || x.name.trim().toLowerCase() == p.name.trim().toLowerCase());
                                  return Container(
                                    margin: const EdgeInsets.only(bottom: 4),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: checked ? const Color(0xFFD97706) : const Color(0xFFCBD5E1)),
                                    ),
                                    child: InkWell(
                                      onTap: () {
                                        setDlgState(() {
                                          if (checked) {
                                            pausedPlayerIds.remove(p.id);
                                          } else {
                                            pausedPlayerIds.add(p.id);
                                          }
                                          validationError = null;
                                          selectionError = null;
                                        });
                                      },
                                      borderRadius: BorderRadius.circular(8),
                                      child: Padding(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                        child: Row(
                                          children: [
                                            SizedBox(
                                              width: 20,
                                              height: 20,
                                              child: Checkbox(
                                                value: checked,
                                                activeColor: const Color(0xFFD97706),
                                                onChanged: (val) {
                                                  setDlgState(() {
                                                    if (val == true) {
                                                      pausedPlayerIds.add(p.id);
                                                    } else {
                                                      pausedPlayerIds.remove(p.id);
                                                    }
                                                    validationError = null;
                                                    selectionError = null;
                                                  });
                                                },
                                              ),
                                            ),
                                            const SizedBox(width: 8),
                                            Expanded(
                                              child: Text(
                                                p.name,
                                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
                                              ),
                                            ),
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                              decoration: BoxDecoration(
                                                color: isTeamA ? const Color(0xFFEFF6FF) : const Color(0xFFFEF3C7),
                                                borderRadius: BorderRadius.circular(4),
                                                border: Border.all(color: isTeamA ? const Color(0xFFBFDBFE) : const Color(0xFFFDE68A)),
                                              ),
                                              child: Text(
                                                isTeamA ? 'Tim A' : 'Tim B',
                                                style: TextStyle(
                                                  fontSize: 9,
                                                  fontWeight: FontWeight.bold,
                                                  color: isTeamA ? const Color(0xFF1D4ED8) : const Color(0xFFB45309),
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  );
                                }),
                                if (selectionError != null) ...[
                                  const SizedBox(height: 4),
                                  Container(
                                    padding: const EdgeInsets.all(6),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFFEF2F2),
                                      borderRadius: BorderRadius.circular(6),
                                      border: Border.all(color: const Color(0xFFFECACA)),
                                    ),
                                    child: Row(
                                      children: [
                                        const Icon(Icons.error_outline, size: 12, color: Color(0xFFDC2626)),
                                        const SizedBox(width: 4),
                                        Expanded(
                                          child: Text(
                                            selectionError!,
                                            style: const TextStyle(fontSize: 9.5, color: Color(0xFF991B1B), fontWeight: FontWeight.bold),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 6),
                                TextField(
                                  controller: pauseReasonController,
                                  decoration: InputDecoration(
                                    labelText: 'Keterangan Istirahat (Opsional)',
                                    labelStyle: const TextStyle(fontSize: 10, color: Color(0xFF78350F)),
                                    hintText: 'Contoh: Ke toilet sebentar, kram ringan, istirahat sejenak',
                                    hintStyle: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                                    contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                    isDense: true,
                                    fillColor: Colors.white,
                                    filled: true,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],

                      // Condition 2 Inputs
                      if (stopCondition == 'withdraw_players') ...[
                        const Text('Pilih Pemain yang Keluar dari Sesi (Permanen) *', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
                        const SizedBox(height: 2),
                        const Text('Pilih satu atau lebih peserta match ini yang tidak dapat melanjutkan sesi:', style: TextStyle(fontSize: 10, color: Color(0xFF64748B))),
                        if (selectionError != null) ...[
                          const SizedBox(height: 4),
                          Container(
                            padding: const EdgeInsets.all(6),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFEF2F2),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: const Color(0xFFFECACA)),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.error_outline, size: 12, color: Color(0xFFDC2626)),
                                const SizedBox(width: 4),
                                Expanded(
                                  child: Text(
                                    selectionError!,
                                    style: const TextStyle(fontSize: 9.5, color: Color(0xFF991B1B), fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                        const SizedBox(height: 6),
                        ...allMatchPlayers.map((p) {
                          final checked = withdrawnPlayerIds.contains(p.id);
                          final isTeamA = match.teamA.any((x) => x.id == p.id || x.name.trim().toLowerCase() == p.name.trim().toLowerCase());
                          final pError = playerReasonErrors[p.id];
                          return Container(
                            margin: const EdgeInsets.only(bottom: 6),
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: pError != null
                                  ? const Color(0xFFFFF1F2)
                                  : (checked ? const Color(0xFFFEF2F2) : const Color(0xFFF8FAFC)),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                color: pError != null
                                    ? const Color(0xFFDC2626)
                                    : (checked ? const Color(0xFFFCA5A5) : const Color(0xFFE2E8F0)),
                                width: pError != null ? 1.5 : 1.0,
                              ),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: Checkbox(
                                        value: checked,
                                        activeColor: const Color(0xFFDC2626),
                                        onChanged: (val) {
                                          setDlgState(() {
                                            if (val == true) {
                                              withdrawnPlayerIds.add(p.id);
                                            } else {
                                              withdrawnPlayerIds.remove(p.id);
                                              playerReasonErrors[p.id] = null;
                                            }
                                            validationError = null;
                                            selectionError = null;
                                          });
                                        },
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Text(
                                        p.name,
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF0F172A)),
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: isTeamA ? const Color(0xFFEFF6FF) : const Color(0xFFFEF3C7),
                                        borderRadius: BorderRadius.circular(4),
                                        border: Border.all(color: isTeamA ? const Color(0xFFBFDBFE) : const Color(0xFFFDE68A)),
                                      ),
                                      child: Text(
                                        isTeamA ? 'Tim A' : 'Tim B',
                                        style: TextStyle(
                                          fontSize: 9,
                                          fontWeight: FontWeight.bold,
                                          color: isTeamA ? const Color(0xFF1D4ED8) : const Color(0xFFB45309),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                if (checked) ...[
                                  const SizedBox(height: 6),
                                  Padding(
                                    padding: const EdgeInsets.only(left: 28, right: 4, bottom: 2),
                                    child: TextField(
                                      controller: playerReasonControllers[p.id],
                                      focusNode: playerReasonFocusNodes[p.id],
                                      onChanged: (val) => setDlgState(() {
                                        validationError = null;
                                        if (val.trim().isNotEmpty) {
                                          playerReasonErrors[p.id] = null;
                                        }
                                      }),
                                      decoration: InputDecoration(
                                        labelText: 'Alasan/kendala untuk ${p.name} *',
                                        labelStyle: TextStyle(
                                          fontSize: 10,
                                          color: pError != null ? const Color(0xFFDC2626) : const Color(0xFF991B1B),
                                          fontWeight: FontWeight.bold,
                                        ),
                                        hintText: 'Contoh: Cedera lutut, urusan mendadak...',
                                        hintStyle: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(6)),
                                        contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                                        isDense: true,
                                        fillColor: Colors.white,
                                        filled: true,
                                        errorText: pError,
                                        errorStyle: const TextStyle(fontSize: 9.5, color: Color(0xFFDC2626), fontWeight: FontWeight.w600),
                                      ),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          );
                        }),
                      ],
                      const SizedBox(height: 10),

                      // Impact Summary Box
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFFBEB),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFFDE68A)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Row(
                              children: [
                                Icon(Icons.info_outline_rounded, size: 14, color: Color(0xFFD97706)),
                                SizedBox(width: 6),
                                Text(
                                  'Dampak terhadap Pertandingan & Jadwal:',
                                  style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              '• Match court ini akan ditandai Dihentikan dengan skor parsial terakhir yang sah (${match.scoreA} - ${match.scoreB}).\n'
                              '• ${stopCondition == 'keep_in_session' ? (pausePlayersToggle && pausedPlayerIds.isNotEmpty ? "Pemain yang butuh istirahat: (${allMatchPlayers.where((p) => pausedPlayerIds.contains(p.id)).map((p) => p.name).join(', ')}) akan otomatis ditempatkan di Bangku Cadangan (Bench) pada ronde berikutnya." : "Drawing dan seluruh pemain tetap berpartisipasi pada ronde mendatang.") : (withdrawnPlayerIds.isNotEmpty ? "Pemain yang keluar: (${allMatchPlayers.where((p) => withdrawnPlayerIds.contains(p.id)).map((p) => p.name).join(', ')}) akan ditandai tidak aktif untuk sesi ini (riwayat ronde lampau tetap tersimpan)." : "Pemain yang keluar: (Belum ada pemain dipilih) akan ditandai tidak aktif untuk sesi ini.")}\n'
                              '• Pemain lain tetap aktif bertanding seperti biasa di lapangan.\n'
                              '• Partner dan lawan yang tidak keluar tetap aktif di sesi ini.',
                              style: const TextStyle(fontSize: 9.5, color: Color(0xFF78350F), height: 1.35),
                            ),
                          ],
                        ),
                      ),

                      if (validationError != null) ...[
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEF2F2),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: const Color(0xFFFECACA)),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.error_outline, size: 14, color: Color(0xFFDC2626)),
                              const SizedBox(width: 6),
                              Expanded(
                                child: Text(
                                  validationError!,
                                  style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: Color(0xFF991B1B)),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              actionsPadding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx, false),
                  child: const Text('Batal', style: TextStyle(color: Color(0xFF64748B), fontWeight: FontWeight.bold)),
                ),
                ElevatedButton.icon(
                  onPressed: () {
                    if (stopCondition == 'keep_in_session') {
                      if (reasonControllerA.text.trim().isEmpty) {
                        setDlgState(() {
                          reasonFieldErrorA = 'Alasan penghentian pertandingan wajib diisi.';
                          validationError = 'Silakan isi alasan penghentian pertandingan pada kolom di atas.';
                        });
                        reasonFocusNodeA.requestFocus();
                        return;
                      }
                      if (pausePlayersToggle && pausedPlayerIds.isEmpty) {
                        setDlgState(() {
                          selectionError = 'Pilih minimal satu pemain yang diistirahatkan sementara.';
                          validationError = 'Pilih minimal satu pemain yang diistirahatkan sementara.';
                        });
                        return;
                      }
                    } else if (stopCondition == 'withdraw_players') {
                      if (withdrawnPlayerIds.isEmpty) {
                        setDlgState(() {
                          selectionError = 'Pilih minimal satu pemain yang ditarik keluar dari sesi.';
                          validationError = 'Pilih minimal satu pemain yang keluar dari sesi.';
                        });
                        return;
                      }
                      bool hasError = false;
                      for (final pId in withdrawnPlayerIds) {
                        if ((playerReasonControllers[pId]?.text.trim() ?? '').isEmpty) {
                          final pName = allMatchPlayers.firstWhere((p) => p.id == pId, orElse: () => allMatchPlayers.first).name;
                          playerReasonErrors[pId] = 'Wajib mengisi alasan keluar untuk $pName.';
                          if (!hasError) {
                            playerReasonFocusNodes[pId]?.requestFocus();
                          }
                          hasError = true;
                        }
                      }
                      if (hasError) {
                        setDlgState(() {
                          validationError = 'Wajib mengisi alasan keluar untuk seluruh pemain yang dipilih.';
                        });
                        return;
                      }
                    }
                    Navigator.pop(ctx, true);
                  },
                  icon: const Icon(Icons.check_rounded, size: 16),
                  label: const Text('Konfirmasi Akhiri Pertandingan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFDC2626),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  ),
                ),
              ],
            );
          },
        );
      },
    );

    if (result == true) {
      String finalReason;
      final resolvedPausedIds = <int>[];
      final resolvedWithdrawnIds = <int>[];
      final resolvedPlayerReasons = <int, String>{};

      if (stopCondition == 'keep_in_session') {
        final rA = reasonControllerA.text.trim();
        final pNote = pauseReasonController.text.trim();
        finalReason = pausePlayersToggle && pNote.isNotEmpty ? '$rA (Istirahat: $pNote)' : rA;
        if (pausePlayersToggle) {
          for (final pidStr in pausedPlayerIds) {
            final pObj = allMatchPlayers.firstWhere((item) => item.id == pidStr);
            final pInt = MatchService.resolvePlayerId(pObj, _config.players) ?? pObj.playerId ?? int.tryParse(pObj.id);
            if (pInt != null) {
              resolvedPausedIds.add(pInt);
              if (pNote.isNotEmpty) resolvedPlayerReasons[pInt] = pNote;
            }
          }
        }
      } else {
        final withdrawnDetails = <String>[];
        for (final pidStr in withdrawnPlayerIds) {
          final pObj = allMatchPlayers.firstWhere((item) => item.id == pidStr);
          final pReason = playerReasonControllers[pidStr]?.text.trim() ?? 'Keluar dari sesi';
          withdrawnDetails.add('${pObj.name}: $pReason');
          final pInt = MatchService.resolvePlayerId(pObj, _config.players) ?? pObj.playerId ?? int.tryParse(pObj.id);
          if (pInt != null) {
            resolvedWithdrawnIds.add(pInt);
            resolvedPlayerReasons[pInt] = pReason;
          }
        }
        finalReason = 'Pemain keluar: ${withdrawnDetails.join('; ')}';
      }

      setState(() {
        match.status = 'Stopped';
        match.stopType = stopCondition == 'keep_in_session'
            ? (resolvedPausedIds.isNotEmpty ? 'temporary_pause' : 'keep_in_session')
            : 'withdraw_players';
        match.stopReason = finalReason;
        match.winnerTeam = null;

        // Update local players roster
        if (resolvedWithdrawnIds.isNotEmpty) {
          for (final wId in resolvedWithdrawnIds) {
            final idx = _config.players.indexWhere((p) => (MatchService.resolvePlayerId(p, _config.players) ?? p.playerId ?? int.tryParse(p.id)) == wId);
            if (idx != -1) {
              final p = _config.players[idx];
              _config.players[idx] = GamePlayerItem(
                id: p.id,
                name: p.name,
                gender: p.gender,
                level: p.level,
                isGuest: p.isGuest,
                avatarUrl: p.avatarUrl,
                userId: p.userId,
                playerId: p.playerId,
                status: 'withdrawn',
                withdrawnReason: resolvedPlayerReasons[wId] ?? finalReason,
                withdrawnAt: DateTime.now(),
              );
            }
          }
        }

        if (resolvedPausedIds.isNotEmpty) {
          for (final pId in resolvedPausedIds) {
            final idx = _config.players.indexWhere((p) => (MatchService.resolvePlayerId(p, _config.players) ?? p.playerId ?? int.tryParse(p.id)) == pId);
            if (idx != -1) {
              final p = _config.players[idx];
              _config.players[idx] = GamePlayerItem(
                id: p.id,
                name: p.name,
                gender: p.gender,
                level: p.level,
                isGuest: p.isGuest,
                avatarUrl: p.avatarUrl,
                userId: p.userId,
                playerId: p.playerId,
                status: 'resting',
                withdrawnReason: resolvedPlayerReasons[pId] ?? finalReason,
              );
            }
          }
        }
      });

      if (match.matchId != null) {
        try {
          await _matchService.stopMatch(
            matchId: match.matchId!,
            sessionId: widget.sessionId,
            reason: finalReason,
            stopType: stopCondition == 'keep_in_session'
                ? (resolvedPausedIds.isNotEmpty ? 'temporary_pause' : 'keep_in_session')
                : 'withdraw_players',
            stoppedByUserId: widget.authController?.currentUser?.userId,
            hasilPertandingan: 'Dihentikan: ${match.scoreA} - ${match.scoreB} ($finalReason)',
            pausedPlayerIds: resolvedPausedIds.isNotEmpty ? resolvedPausedIds : null,
            withdrawnPlayerIds: resolvedWithdrawnIds.isNotEmpty ? resolvedWithdrawnIds : null,
            playerReasons: resolvedPlayerReasons.isNotEmpty ? resolvedPlayerReasons : null,
            scoreA: match.scoreA,
            scoreB: match.scoreB,
            pointDisplayA: match.pointDisplayA,
            pointDisplayB: match.pointDisplayB,
            roundNumber: _viewedRoundIndex + 1,
            scoringSystem: _scoringConfig.label,
          );
        } catch (e) {
          debugPrint('[Scoring] Error stopping match: $e');
        }
      }

      // Refetch session players to ensure full sync with Supabase
      if (widget.sessionId != null) {
        try {
          final refreshed = await _matchService.getSessionPlayers(widget.sessionId);
          if (refreshed.isNotEmpty && mounted) {
            setState(() {
              _config.players = refreshed;
            });
          }
        } catch (_) {}
      }

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Pertandingan Court ${match.courtNumber} berhasil dihentikan.'),
            backgroundColor: const Color(0xFFDC2626),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    }
    reasonControllerA.dispose();
    reasonFocusNodeA.dispose();
    pauseReasonController.dispose();
    for (final c in playerReasonControllers.values) {
      c.dispose();
    }
    for (final fn in playerReasonFocusNodes.values) {
      fn.dispose();
    }
  }

  // --- GAP 2 & GAP 4: Player Status Management Modal & Schedule Adjustment ---
  Future<void> _showPlayerStatusManagementModal() async {
    if (!isHost) return;
    final allPlayers = _config.players;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Container(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(ctx).size.height * 0.85,
              ),
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: const Color(0xFFCBD5E1),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Kesiapan & Status Pemain', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
                          SizedBox(height: 2),
                          Text('Kelola pemain istirahat, cedera, atau keluar', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                        ],
                      ),
                      IconButton(
                        icon: const Icon(Icons.close_rounded, size: 20),
                        onPressed: () => Navigator.pop(ctx),
                      ),
                    ],
                  ),
                  const Divider(height: 20),
                  Expanded(
                    child: ListView.separated(
                      itemCount: allPlayers.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 8),
                      itemBuilder: (_, idx) {
                        final p = allPlayers[idx];
                        DrawingMatch? activeMatchForPlayer;
                        if (_rounds.isNotEmpty) {
                          final activeRound = _rounds[_sessionActiveRoundIndex.clamp(0, _rounds.length - 1)];
                          for (final m in activeRound.matches) {
                            if (!m.isTerminal && (m.teamA.any((x) => x.id == p.id) || m.teamB.any((x) => x.id == p.id))) {
                              activeMatchForPlayer = m;
                              break;
                            }
                          }
                        }

                        Color badgeBg;
                        Color badgeFg;
                        String badgeText;
                        if (p.isWithdrawn) {
                          badgeBg = const Color(0xFFFEE2E2);
                          badgeFg = const Color(0xFF991B1B);
                          badgeText = 'Withdrawn (Keluar)';
                        } else if (activeMatchForPlayer != null) {
                          badgeBg = const Color(0xFFEFF6FF);
                          badgeFg = const Color(0xFF1D4ED8);
                          badgeText = 'Sedang Main (Court ${activeMatchForPlayer.courtNumber})';
                        } else if (p.isResting) {
                          badgeBg = const Color(0xFFFEF3C7);
                          badgeFg = const Color(0xFF92400E);
                          badgeText = 'Resting (Istirahat)';
                        } else {
                          badgeBg = const Color(0xFFDCFCE7);
                          badgeFg = const Color(0xFF15803D);
                          badgeText = 'Active (Siap)';
                        }

                        return Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  CircleAvatar(
                                    radius: 16,
                                    backgroundColor: AppColors.matchaDark,
                                    child: Text(
                                      p.name.isNotEmpty ? p.name[0].toUpperCase() : 'P',
                                      style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(p.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A))),
                                        Text('${p.level} • ${p.gender}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                                      ],
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                    decoration: BoxDecoration(color: badgeBg, borderRadius: BorderRadius.circular(6)),
                                    child: Text(badgeText, style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: badgeFg)),
                                  ),
                                ],
                              ),
                              if (isHost) ...[
                                const SizedBox(height: 8),
                                if (activeMatchForPlayer != null)
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFFEF3C7),
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: const Color(0xFFFDE68A)),
                                    ),
                                    child: Row(
                                      children: [
                                        const Icon(Icons.info_outline, size: 14, color: Color(0xFFD97706)),
                                        const SizedBox(width: 6),
                                        Expanded(
                                          child: Text(
                                            'Pemain sedang bertanding di Court ${activeMatchForPlayer.courtNumber}. Gunakan "Hentikan Match" pada Court ${activeMatchForPlayer.courtNumber} jika pemain berhalangan.',
                                            style: const TextStyle(fontSize: 10, color: Color(0xFF92400E), height: 1.2),
                                          ),
                                        ),
                                      ],
                                    ),
                                  )
                                else
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.end,
                                    children: [
                                      if (!p.isWithdrawn && !p.isResting)
                                        TextButton.icon(
                                          onPressed: () => _updatePlayerStatusAction(p, 'pause', setSheetState),
                                          icon: const Icon(Icons.pause_circle_outline, size: 14, color: Color(0xFFD97706)),
                                          label: const Text('Istirahatkan', style: TextStyle(fontSize: 11, color: Color(0xFFD97706))),
                                        ),
                                      if (p.isResting)
                                        TextButton.icon(
                                          onPressed: () => _updatePlayerStatusAction(p, 'reactivate', setSheetState),
                                          icon: const Icon(Icons.play_circle_outline, size: 14, color: Color(0xFF16A34A)),
                                          label: const Text('Siap Main', style: TextStyle(fontSize: 11, color: Color(0xFF16A34A))),
                                        ),
                                      if (!p.isWithdrawn)
                                        TextButton.icon(
                                          onPressed: () => _updatePlayerStatusAction(p, 'withdraw', setSheetState),
                                          icon: const Icon(Icons.exit_to_app_rounded, size: 14, color: Color(0xFFDC2626)),
                                          label: const Text('Keluarkan', style: TextStyle(fontSize: 11, color: Color(0xFFDC2626))),
                                        ),
                                    ],
                                  ),
                              ],
                            ],
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _updatePlayerStatusAction(GamePlayerItem p, String action, StateSetter setSheetState) async {
    if (!isHost) return;
    String? reason;
    if (action == 'pause' || action == 'withdraw') {
      final reasonController = TextEditingController();
      final ok = await showDialog<bool>(
        context: context,
        builder: (dlgCtx) => AlertDialog(
          title: Text(action == 'pause' ? 'Istirahatkan ${p.name}' : 'Keluarkan ${p.name}'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                action == 'pause'
                    ? 'Pemain akan diistirahatkan ke bangku cadangan pada ronde berikutnya.'
                    : 'Pemain akan dikeluarkan secara permanen dari sesi dan tidak dapat bermain lagi.',
                style: const TextStyle(fontSize: 12),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: reasonController,
                decoration: const InputDecoration(
                  labelText: 'Alasan (Wajib)',
                  hintText: 'Contoh: Kram, Cedera pergelangan, Keperluan pribadi',
                ),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dlgCtx, false), child: const Text('Batal')),
            ElevatedButton(
              onPressed: () {
                if (reasonController.text.trim().isEmpty) return;
                Navigator.pop(dlgCtx, true);
              },
              child: const Text('Lanjutkan'),
            ),
          ],
        ),
      );
      if (ok != true) return;
      reason = reasonController.text.trim();
    }

    try {
      final pId = MatchService.resolvePlayerId(p) ?? p.playerId ?? int.tryParse(p.id) ?? 0;
      Map<String, dynamic> res = {};
      if (widget.sessionId != null && pId > 0) {
        try {
          res = await _matchService.managePlayerStatus(
            sessionId: widget.sessionId,
            playerId: pId,
            action: action,
            reason: reason,
          );
          if (res['is_playing'] == true) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(res['message']?.toString() ?? 'Pemain sedang bertanding di Court ${res['court']}. Silakan gunakan End Match pada court terkait.'),
                  backgroundColor: const Color(0xFFD97706),
                ),
              );
            }
            return;
          }
        } catch (e) {
          debugPrint('[Scoring] managePlayerStatus backend call: $e');
        }
      }

      final newStatus = res['status']?.toString() ?? (action == 'pause' ? 'resting' : (action == 'withdraw' ? 'withdrawn' : 'active'));
      final idx = _config.players.indexWhere((item) => item.id == p.id);
      if (idx != -1) {
        _config.players[idx] = GamePlayerItem(
          id: p.id,
          name: p.name,
          gender: p.gender,
          level: p.level,
          isGuest: p.isGuest,
          avatarUrl: p.avatarUrl,
          userId: p.userId,
          playerId: p.playerId,
          status: newStatus,
          withdrawnReason: reason,
          withdrawnAt: action == 'withdraw' ? DateTime.now() : null,
        );
      }
      setSheetState(() {});
      setState(() {});

      if (mounted) {
        final successMsg = action == 'reactivate'
            ? '${p.name} kini siap main kembali.'
            : (action == 'pause'
                ? '${p.name} berhasil diistirahatkan ke bangku cadangan.'
                : '${p.name} berhasil dikeluarkan dari sesi.');
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(successMsg),
            backgroundColor: action == 'reactivate' ? const Color(0xFF15803D) : const Color(0xFF0F172A),
            duration: const Duration(seconds: 2),
          ),
        );
      }

      if (res['needs_schedule_adjustment'] == true) {
        _showScheduleAdjustmentDialog();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal mengubah status pemain: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<void> _showScheduleAdjustmentDialog() async {
    if (!mounted) return;
    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: const [
            Icon(Icons.tune_rounded, color: AppColors.matchaDark),
            SizedBox(width: 8),
            Text('Penyesuaian Jadwal', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
          ],
        ),
        content: const Text(
          'Terdapat perubahan status pemain (istirahat / keluar). Jadwal pertandingan ronde berikutnya memerlukan penyesuaian rotasi agar tim tetap seimbang.',
          style: TextStyle(fontSize: 12),
        ),
        actions: [
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              try {
                await _matchService.confirmAdjustedSchedule(sessionId: widget.sessionId);
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Jadwal pertandingan berhasil dikonfirmasi dan disesuaikan.'),
                      backgroundColor: Color(0xFF15803D),
                    ),
                  );
                }
              } catch (_) {}
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.matchaDark,
              foregroundColor: Colors.white,
            ),
            child: const Text('Konfirmasi & Terapkan Jadwal'),
          ),
        ],
      ),
    );
  }



  void _openRecapPage() {
    final sId = widget.sessionId != null
        ? (widget.sessionId is int
            ? widget.sessionId as int
            : int.tryParse('${widget.sessionId}'))
        : null;

    if (sId != null && sId > 0) {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => SessionMatchRecapPage(
            sessionId: sId,
            authController: widget.authController,
          ),
        ),
      );
    } else {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => GameFinalRecapPage(
            config: _config,
            rounds: _rounds,
            authController: widget.authController,
          ),
        ),
      );
    }
  }

  Future<void> _finishSessionAndShowRecap() async {
    if (!isHost) return;

    final sId = widget.sessionId != null
        ? (widget.sessionId is int
            ? widget.sessionId as int
            : int.tryParse('${widget.sessionId}'))
        : null;

    if (sId != null && sId > 0) {
      try {
        await _matchService.finishSession(sId);
      } catch (e) {
        debugPrint('[Scoring] Error finishing session: $e');
      }
      if (!mounted) return;
      setState(() {
        _isSessionFinished = true;
      });
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => SessionMatchRecapPage(
            sessionId: sId,
            authController: widget.authController,
          ),
        ),
      );
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => GameFinalRecapPage(
            config: _config,
            rounds: _rounds,
            authController: widget.authController,
          ),
        ),
      );
    }
  }



  @override

  Widget build(BuildContext context) {

    if (_rounds.isEmpty) {
      if (_isSessionLoading) {
        return Scaffold(
          backgroundColor: const Color(0xFFF8FAFC),
          appBar: AppBar(
            backgroundColor: Colors.white,
            elevation: 0,
            leading: IconButton(
              icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
              onPressed: () => Navigator.pop(context),
            ),
            title: Text(
              'Live Match Scoring',
              style: AppTextStyles.h2.copyWith(fontSize: 15, fontWeight: FontWeight.w800, color: const Color(0xFF0F172A)),
            ),
          ),
          body: const Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CircularProgressIndicator(color: Color(0xFF059669)),
                SizedBox(height: 16),
                Text(
                  'Memuat jadwal dan skor sesi...',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF475569)),
                ),
              ],
            ),
          ),
        );
      }

      if (_hasSessionLoadError) {
        return Scaffold(
          backgroundColor: const Color(0xFFF8FAFC),
          appBar: AppBar(
            backgroundColor: Colors.white,
            elevation: 0,
            leading: IconButton(
              icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
              onPressed: () => Navigator.pop(context),
            ),
            title: Text(
              'Live Match Scoring',
              style: AppTextStyles.h2.copyWith(fontSize: 15, fontWeight: FontWeight.w800, color: const Color(0xFF0F172A)),
            ),
          ),
          body: Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline_rounded, size: 48, color: Color(0xFFDC2626)),
                  const SizedBox(height: 16),
                  const Text(
                    'Gagal Memuat Sesi Pertandingan',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Color(0xFF0F172A)),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _sessionLoadErrorMessage ??
                        'Tidak dapat mengambil data sesi dari server. Silakan periksa koneksi internet Anda.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                  ),
                  const SizedBox(height: 24),
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF0F172A),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    onPressed: _loadSessionAndScores,
                    icon: const Icon(Icons.refresh_rounded, size: 18),
                    label: const Text('Coba Lagi'),
                  ),
                ],
              ),
            ),
          ),
        );
      }

      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          backgroundColor: Colors.white,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
            onPressed: () => Navigator.pop(context),
          ),
          title: Text(
            'Live Match Scoring',
            style: AppTextStyles.h2.copyWith(fontSize: 15, fontWeight: FontWeight.w800, color: const Color(0xFF0F172A)),
          ),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.hourglass_empty_rounded, size: 56, color: Color(0xFF059669)),
                const SizedBox(height: 16),
                const Text(
                  'Drawing Belum Dikunci',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 8),
                Text(
                  isHost
                      ? 'Sebagai Host, Anda belum mengunci hasil drawing pertandingan. Buka halaman drawing untuk mengacak tim dan mengunci pertandingan.'
                      : 'Host sesi belum mengunci hasil drawing tim. Papan skor akan otomatis diperbarui begitu Host memulai pertandingan.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 13, color: Color(0xFF64748B), height: 1.4),
                ),
                const SizedBox(height: 24),
                if (widget.sessionId != null)
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF059669),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => DrawingResultPage(
                            sessionId: widget.sessionId,
                            authController: widget.authController,
                            hostUserId: _sessionHostUserId,
                            isHost: isHost,
                            config: _config,
                          ),
                        ),
                      );
                    },
                    icon: const Icon(Icons.groups_rounded, size: 18),
                    label: Text(isHost ? 'Buka & Kunci Drawing Tim' : 'Lihat Drawing Tim'),
                  ),
              ],
            ),
          ),
        ),
      );
    }



    if (_viewedRoundIndex > _sessionActiveRoundIndex) {
      _viewedRoundIndex = _sessionActiveRoundIndex;
    }

    final currentRound =

        _rounds[_viewedRoundIndex.clamp(0, _rounds.length - 1)];



    return Scaffold(

      backgroundColor: const Color(0xFFF8FAFC),

      appBar: AppBar(

        backgroundColor: Colors.white,

        elevation: 0,

        leading: IconButton(

          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),

          onPressed: () => Navigator.pop(context),

        ),

        title: Column(

          children: [

            Text(

              'Live Match Scoring',

              style: AppTextStyles.h2.copyWith(

                fontSize: 15,

                fontWeight: FontWeight.w800,

                color: const Color(0xFF0F172A),

              ),

            ),

            const SizedBox(height: 2),

            Text(

              hostAccessStatus == HostAccessStatus.host

                  ? 'Mode Host'

                  : (hostAccessStatus == HostAccessStatus.verifying

                      ? 'Memverifikasi Peran...'

                      : (hostAccessStatus == HostAccessStatus.error

                          ? 'Verifikasi Host Gagal'

                          : 'Mode Penonton')),

              style: AppTextStyles.caption.copyWith(

                fontSize: 11,

                fontWeight: FontWeight.w600,

                color: hostAccessStatus == HostAccessStatus.host

                    ? const Color(0xFF059669)

                    : (hostAccessStatus == HostAccessStatus.error

                        ? const Color(0xFFDC2626)

                        : const Color(0xFF64748B)),

              ),

            ),

          ],

        ),

        centerTitle: true,

        actions: [
          Container(

            margin: const EdgeInsets.only(right: 14),

            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),

            decoration: BoxDecoration(

              color: _isRealtimeConnected

                  ? const Color(0xFFECFDF5)

                  : const Color(0xFFF1F5F9),

              borderRadius: BorderRadius.circular(10),

              border: Border.all(

                color: _isRealtimeConnected

                    ? const Color(0xFFA7F3D0)

                    : const Color(0xFFE2E8F0),

              ),

            ),

            child: Row(

              mainAxisSize: MainAxisSize.min,

              children: [

                Icon(

                  Icons.circle,

                  color: _isRealtimeConnected

                      ? const Color(0xFF10B981)

                      : const Color(0xFF94A3B8),

                  size: 8,

                ),

                const SizedBox(width: 4),

                Text(

                  _isRealtimeConnected ? 'Realtime Aktif' : 'Status koneksi belum terverifikasi',

                  style: TextStyle(

                    fontWeight: FontWeight.w800,

                    fontSize: 10,

                    color: _isRealtimeConnected

                        ? const Color(0xFF065F46)

                        : const Color(0xFF64748B),

                  ),

                ),

              ],

            ),

          ),

        ],

      ),

      body: ListView(

        padding: const EdgeInsets.all(20),

        children: [

          if (_isLoading)

            const Padding(

              padding: EdgeInsets.only(bottom: 12),

              child: LinearProgressIndicator(

                color: AppColors.matchaDark,

                backgroundColor: Color(0xFFE2E8F0),

              ),

            ),



          // Banner Status

          Container(

            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),

            decoration: BoxDecoration(

              color: hostAccessStatus == HostAccessStatus.host

                  ? const Color(0xFFECFDF5)

                  : (hostAccessStatus == HostAccessStatus.verifying

                      ? const Color(0xFFEFF6FF)

                      : (hostAccessStatus == HostAccessStatus.error

                          ? const Color(0xFFFEF2F2)

                          : const Color(0xFFF1F5F9))),

              borderRadius: BorderRadius.circular(12),

              border: Border.all(

                color: hostAccessStatus == HostAccessStatus.host

                    ? const Color(0xFFA7F3D0)

                    : (hostAccessStatus == HostAccessStatus.verifying

                        ? const Color(0xFFBFDBFE)

                        : (hostAccessStatus == HostAccessStatus.error

                            ? const Color(0xFFFECACA)

                            : const Color(0xFFCBD5E1))),

              ),

            ),

            child: Row(

              children: [

                if (hostAccessStatus == HostAccessStatus.verifying) ...[

                  const SizedBox(

                    width: 14,

                    height: 14,

                    child: CircularProgressIndicator(

                      strokeWidth: 2,

                      color: Color(0xFF2563EB),

                    ),

                  ),

                ] else ...[

                  Icon(

                    hostAccessStatus == HostAccessStatus.host

                        ? Icons.check_circle_rounded

                        : (hostAccessStatus == HostAccessStatus.error

                            ? Icons.error_outline_rounded

                            : Icons.visibility_outlined),

                    color: hostAccessStatus == HostAccessStatus.host

                        ? const Color(0xFF059669)

                        : (hostAccessStatus == HostAccessStatus.error

                            ? const Color(0xFFDC2626)

                            : const Color(0xFF64748B)),

                    size: 16,

                  ),

                ],

                const SizedBox(width: 8),

                Expanded(

                  child: Text(

                    hostAccessStatus == HostAccessStatus.host

                        ? 'Mode Host: Akses penuh untuk input skor dan kelola match'

                        : (hostAccessStatus == HostAccessStatus.verifying

                            ? 'Memverifikasi hak akses host sesi...'

                            : (hostAccessStatus == HostAccessStatus.error

                                ? 'Gagal memverifikasi identitas host sesi. Kontrol skor dikunci.'

                                : 'Mode Penonton: Skor diperbarui otomatis via Realtime')),

                    style: TextStyle(

                      fontSize: 12,

                      fontWeight: FontWeight.bold,

                      color: hostAccessStatus == HostAccessStatus.host

                          ? const Color(0xFF065F46)

                          : (hostAccessStatus == HostAccessStatus.verifying

                              ? const Color(0xFF1E40AF)

                              : (hostAccessStatus == HostAccessStatus.error

                                  ? const Color(0xFF991B1B)

                                  : const Color(0xFF475569))),

                    ),

                  ),

                ),

              ],

            ),

          ),

          const SizedBox(height: 14),



          // Round Selector Tabs

          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Pilih Ronde: (${_rounds.length} Ronde)',
                style: AppTextStyles.caption.copyWith(
                  fontWeight: FontWeight.bold,
                  color: const Color(0xFF334155),
                ),
              ),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  'Format: ${_config.gameType} (${_config.playMode == "Double" ? "2v2" : "1v1"})',
                  overflow: TextOverflow.ellipsis,
                  style: AppTextStyles.caption.copyWith(
                    fontSize: 10,
                    color: const Color(0xFF94A3B8),
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 8),

          SingleChildScrollView(

            scrollDirection: Axis.horizontal,

            child: Row(
              children: List.generate(_rounds.length, (idx) {
                final isSelected = _viewedRoundIndex == idx;
                final isActiveRound = _sessionActiveRoundIndex == idx;
                final isLocked = idx > _sessionActiveRoundIndex;
                final r = _rounds[idx];
                final isFinished = r.matches.every(
                  (m) => m.status == 'Completed',
                );

                return Container(
                  margin: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    key: ValueKey('round_tab_$idx'),
                    label: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text('Ronde ${r.roundNumber}'),
                        if (isLocked) ...[
                          const SizedBox(width: 5),
                          const Icon(
                            Icons.lock_rounded,
                            size: 13,
                            color: Color(0xFF94A3B8),
                          ),
                        ] else if (isActiveRound) ...[
                          const SizedBox(width: 5),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                            decoration: BoxDecoration(
                              color: isSelected ? Colors.white : AppColors.matchaDark,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              'AKTIF',
                              style: TextStyle(
                                fontSize: 8,
                                fontWeight: FontWeight.w900,
                                color: isSelected ? AppColors.matchaDark : Colors.white,
                              ),
                            ),
                          ),
                        ] else if (isFinished) ...[
                          const SizedBox(width: 4),
                          Icon(
                            Icons.check,
                            size: 12,
                            color: isSelected ? Colors.white : const Color(0xFF10B981),
                          ),
                        ],
                      ],
                    ),
                    selected: isSelected && !isLocked,
                    selectedColor: AppColors.matchaDark,
                    disabledColor: const Color(0xFFF1F5F9),
                    backgroundColor: isLocked ? const Color(0xFFF8FAFC) : Colors.white,
                    labelStyle: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: isLocked
                          ? const Color(0xFF94A3B8)
                          : (isSelected
                              ? Colors.white
                              : const Color(0xFF475569)),
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                      side: BorderSide(
                        color: isLocked
                            ? const Color(0xFFCBD5E1)
                            : (isSelected
                                ? AppColors.matchaDark
                                : const Color(0xFFE2E8F0)),
                      ),
                    ),
                    onSelected: isLocked
                        ? null
                        : (val) {
                            if (val) {
                              _selectRound(idx);
                            }
                          },
                  ),
                );
              }),
            ),

          ),



          // Inactive / Historical Round Notification Banner
          if (!_isCurrentViewedRoundActive) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFCBD5E1)),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.history_rounded,
                    color: Color(0xFF475569),
                    size: 18,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Mode Tinjau Riwayat Ronde ${currentRound.roundNumber}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF334155),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Tombol skor dikunci. Ronde Aktif: Ronde ${_sessionActiveRoundIndex + 1}.',
                          style: const TextStyle(
                            fontSize: 11,
                            color: Color(0xFF64748B),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  ElevatedButton(
                    onPressed: () {
                      setState(() {
                        _viewedRoundIndex = _sessionActiveRoundIndex;
                        _selectedCourtIndex = 0;
                      });
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      elevation: 0,
                    ),
                    child: Text(
                      'Ronde ${_sessionActiveRoundIndex + 1} (Live)',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ),
          ],

          const SizedBox(height: 16),



          // Court Tabs (jika ronde memiliki lebih dari 1 court)

          if (currentRound.matches.length > 1) ...[

            Row(

              children: [

                const Icon(Icons.sports_tennis_rounded, size: 14, color: AppColors.matchaDark),

                const SizedBox(width: 6),

                Text(

                  'Pilih Lapangan (${currentRound.matches.length} Court):',

                  style: AppTextStyles.caption.copyWith(

                    fontWeight: FontWeight.bold,

                    color: const Color(0xFF334155),

                  ),

                ),

              ],

            ),

            const SizedBox(height: 6),

            SingleChildScrollView(

              scrollDirection: Axis.horizontal,

              child: Row(

                children: List.generate(currentRound.matches.length, (cIdx) {

                  final isCourtSelected = _selectedCourtIndex == cIdx;

                  final cm = currentRound.matches[cIdx];

                  final isCourtDone = cm.status == 'Completed';

                  return Container(

                    margin: const EdgeInsets.only(right: 8),

                    child: ChoiceChip(

                      label: Text(

                        isCourtDone

                            ? 'Court ${cm.courtNumber} ✓ (${cm.scoreA} - ${cm.scoreB})'

                            : 'Court ${cm.courtNumber} (${cm.scoreA} - ${cm.scoreB})',

                      ),

                      selected: isCourtSelected,

                      selectedColor: const Color(0xFF063B00),

                      backgroundColor: Colors.white,

                      labelStyle: TextStyle(

                        fontSize: 11,

                        fontWeight: FontWeight.bold,

                        color: isCourtSelected ? Colors.white : const Color(0xFF475569),

                      ),

                      shape: RoundedRectangleBorder(

                        borderRadius: BorderRadius.circular(10),

                        side: BorderSide(

                          color: isCourtSelected

                              ? const Color(0xFF063B00)

                              : const Color(0xFFE2E8F0),

                        ),

                      ),

                      onSelected: (val) {

                        if (val) setState(() => _selectedCourtIndex = cIdx);

                      },

                    ),

                  );

                }),

              ),

            ),

            const SizedBox(height: 14),

          ],



          // Matches in this round

          if (currentRound.matches.length > 1)

            _buildScoreboardCard(

              currentRound.matches[

                _selectedCourtIndex.clamp(0, currentRound.matches.length - 1)

              ],

            )

          else

            ...currentRound.matches.map((m) => _buildScoreboardCard(m)),



          const SizedBox(height: 12),

          // 1. Status Pertandingan Ronde (Always visible like Web)
          _buildRoundStatusCard(currentRound),

          // 2. Status Pemain & Rotasi Sesi (Always visible like Web)
          _buildPlayerReadinessAndRotationCard(currentRound),



          // All Rounds Finished Banner & Final Recap Trigger

          if (_isAllRoundsFinished) ...[

            const SizedBox(height: 16),

            Container(

              padding: const EdgeInsets.all(16),

              decoration: BoxDecoration(

                color: Colors.white,

                borderRadius: BorderRadius.circular(18),

                border: Border.all(color: const Color(0xFF22C55E)),

                boxShadow: [

                  BoxShadow(

                    color: const Color(0xFF22C55E).withValues(alpha: 0.1),

                    blurRadius: 10,

                    offset: const Offset(0, 3),

                  ),

                ],

              ),

              child: Column(

                children: [

                  Row(

                    children: [

                      Container(

                        padding: const EdgeInsets.all(8),

                        decoration: BoxDecoration(

                          color: const Color(0xFFDCFCE7),

                          borderRadius: BorderRadius.circular(10),

                        ),

                        child: const Icon(

                          Icons.sports_score_rounded,

                          color: Color(0xFF15803D),

                          size: 24,

                        ),

                      ),

                      const SizedBox(width: 12),

                      Expanded(

                        child: Column(

                          crossAxisAlignment: CrossAxisAlignment.start,

                          children: [

                            const Text(

                              'Seluruh Pertandingan Ronde Selesai! 🏁',

                              style: TextStyle(

                                fontWeight: FontWeight.w800,

                                fontSize: 13,

                                color: Color(0xFF0F172A),

                              ),

                            ),

                            const SizedBox(height: 2),

                            Text(

                              isHost

                                  ? 'Semua court telah mencatat skor akhir. Silakan lanjut ke hasil akhir & podium.'

                                  : (_isSessionFinished

                                      ? 'Sesi pertandingan telah selesai. Buka rekap dan podium juara.'

                                      : 'Semua court telah selesai. Buka rekap hasil pertandingan atau tunggu Host.'),

                              style: const TextStyle(

                                fontSize: 11,

                                color: Color(0xFF64748B),

                              ),

                            ),

                          ],

                        ),

                      ),

                    ],

                  ),

                  if (isHost) ...[

                    const SizedBox(height: 14),

                    SizedBox(

                      width: double.infinity,

                      height: 44,

                      child: ElevatedButton.icon(

                        onPressed: _finishSessionAndShowRecap,

                        icon: const Icon(Icons.emoji_events_rounded, size: 18),

                        label: const Text(

                          'Selesaikan Sesi & Lihat Juara',

                          style: TextStyle(

                            fontWeight: FontWeight.bold,

                            fontSize: 13,

                          ),

                        ),

                        style: ElevatedButton.styleFrom(

                          backgroundColor: AppColors.matchaDark,

                          foregroundColor: Colors.white,

                          shape: RoundedRectangleBorder(

                            borderRadius: BorderRadius.circular(12),

                          ),

                          elevation: 0,

                        ),

                      ),

                    ),

                  ] else ...[

                    const SizedBox(height: 14),

                    SizedBox(

                      width: double.infinity,

                      height: 44,

                      child: ElevatedButton.icon(

                        onPressed: _openRecapPage,

                        icon: const Icon(Icons.emoji_events_rounded, size: 18),

                        label: const Text(

                          'Lihat Rekap & Podium',

                          style: TextStyle(

                            fontWeight: FontWeight.bold,

                            fontSize: 13,

                          ),

                        ),

                        style: ElevatedButton.styleFrom(

                          backgroundColor: AppColors.matchaDark,

                          foregroundColor: Colors.white,

                          shape: RoundedRectangleBorder(

                            borderRadius: BorderRadius.circular(12),

                          ),

                          elevation: 0,

                        ),

                      ),

                    ),

                  ],

                ],

              ),

            ),

          ],

        ],

      ),

    );

  }


  Widget _buildTeamScorePanel({

    required DrawingMatch match,

    required bool isTeamA,

    required bool canInput,

    required bool isCompleted,

  }) {

    final label = isTeamA ? 'TEAM A' : 'TEAM B';

    final names = isTeamA ? match.teamANames : match.teamBNames;

    final pointDisplay = isTeamA ? match.pointDisplayA : match.pointDisplayB;

    final gamesWon = isTeamA ? match.scoreA : match.scoreB;

    final labelBgColor = isTeamA ? const Color(0xFFEFF6FF) : const Color(0xFFFEF3C7);

    final labelTxtColor = isTeamA ? const Color(0xFF2563EB) : const Color(0xFFB45309);

    final isStopped = match.isStopped || match.status.toLowerCase() == 'stopped';

    final buttonLabel = isStopped
        ? 'Match Dihentikan (${match.scoreA} - ${match.scoreB})'
        : (isTeamA ? '+ Tambah Poin Team A' : '+ Tambah Poin Team B');

    final VoidCallback onAddPoint = isTeamA ? () => _addPointTeamA(match) : () => _addPointTeamB(match);



    return Container(

      padding: const EdgeInsets.all(12),

      decoration: BoxDecoration(

        color: const Color(0xFFF8FAFC),

        borderRadius: BorderRadius.circular(14),

        border: Border.all(

          color: (match.isDeuce && match.advantage == (isTeamA ? 'A' : 'B'))

              ? AppColors.matchaDark

              : const Color(0xFFE2E8F0),

          width: (match.isDeuce && match.advantage == (isTeamA ? 'A' : 'B')) ? 1.5 : 1.0,

        ),

      ),

      child: Column(

        mainAxisAlignment: MainAxisAlignment.spaceBetween,

        children: [

          Column(

            children: [

              Container(

                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),

                decoration: BoxDecoration(

                  color: labelBgColor,

                  borderRadius: BorderRadius.circular(6),

                ),

                child: Text(

                  label,

                  style: TextStyle(

                    fontWeight: FontWeight.w800,

                    fontSize: 10,

                    color: labelTxtColor,

                  ),

                ),

              ),

              const SizedBox(height: 6),

              Text(

                names,

                textAlign: TextAlign.center,

                maxLines: 2,

                overflow: TextOverflow.ellipsis,

                style: const TextStyle(

                  fontWeight: FontWeight.bold,

                  fontSize: 12,

                  color: Color(0xFF0F172A),

                  height: 1.2,

                ),

              ),

              const SizedBox(height: 10),

              const Text(

                'CURRENT POINT (TENNIS)',

                textAlign: TextAlign.center,

                style: TextStyle(

                  fontSize: 9,

                  fontWeight: FontWeight.w800,

                  color: Color(0xFF94A3B8),

                  letterSpacing: 0.3,

                ),

              ),

              const SizedBox(height: 2),

              Text(

                pointDisplay,

                textAlign: TextAlign.center,

                style: const TextStyle(

                  fontSize: 40,

                  fontWeight: FontWeight.w900,

                  color: Color(0xFF0F172A),

                  height: 1.1,

                ),

              ),

              const SizedBox(height: 4),

              Text(

                'Games Won: $gamesWon Game',

                textAlign: TextAlign.center,

                style: const TextStyle(

                  fontSize: 11,

                  fontWeight: FontWeight.w600,

                  color: Color(0xFF64748B),

                ),

              ),

            ],

          ),

          const SizedBox(height: 12),

          if (hostAccessStatus == HostAccessStatus.verifying)

            Container(

              width: double.infinity,

              padding: const EdgeInsets.symmetric(vertical: 8),

              decoration: BoxDecoration(

                color: Colors.white,

                borderRadius: BorderRadius.circular(10),

                border: Border.all(color: const Color(0xFFE2E8F0)),

              ),

              child: const Row(

                mainAxisAlignment: MainAxisAlignment.center,

                children: [

                  SizedBox(

                    width: 12,

                    height: 12,

                    child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF94A3B8)),

                  ),

                  SizedBox(width: 6),

                  Flexible(

                    child: Text(

                      'Memverifikasi...',

                      overflow: TextOverflow.ellipsis,

                      style: TextStyle(

                        fontSize: 10,

                        fontWeight: FontWeight.bold,

                        color: Color(0xFF64748B),

                      ),

                    ),

                  ),

                ],

              ),

            )

          else if (hostAccessStatus == HostAccessStatus.error)

            Container(

              width: double.infinity,

              padding: const EdgeInsets.symmetric(vertical: 8),

              decoration: BoxDecoration(

                color: const Color(0xFFFEF2F2),

                borderRadius: BorderRadius.circular(10),

                border: Border.all(color: const Color(0xFFFECACA)),

              ),

              child: const Row(

                mainAxisAlignment: MainAxisAlignment.center,

                children: [

                  Icon(Icons.lock_outline_rounded, size: 12, color: Color(0xFFDC2626)),

                  SizedBox(width: 4),

                  Flexible(

                    child: Text(

                      'Verifikasi Gagal',

                      overflow: TextOverflow.ellipsis,

                      style: TextStyle(

                        fontSize: 10,

                        fontWeight: FontWeight.bold,

                        color: Color(0xFFDC2626),

                      ),

                    ),

                  ),

                ],

              ),

            )

          else if (!isHost)

            Container(

              width: double.infinity,

              padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),

              decoration: BoxDecoration(

                color: Colors.white,

                borderRadius: BorderRadius.circular(10),

                border: Border.all(color: const Color(0xFFE2E8F0)),

              ),

              child: const Row(

                mainAxisAlignment: MainAxisAlignment.center,

                children: [

                  Icon(Icons.visibility_outlined, size: 12, color: Color(0xFF94A3B8)),

                  SizedBox(width: 4),

                  Flexible(

                    child: Text(

                      'Mode Penonton (Read-Only)',

                      textAlign: TextAlign.center,

                      overflow: TextOverflow.ellipsis,

                      style: TextStyle(

                        fontSize: 9.5,

                        fontWeight: FontWeight.bold,

                        color: Color(0xFF64748B),

                      ),

                    ),

                  ),

                ],

              ),

            )

          else

            SizedBox(

              width: double.infinity,

              height: 44,

              child: ElevatedButton(

                onPressed: canInput ? onAddPoint : null,

                style: ElevatedButton.styleFrom(

                  backgroundColor: AppColors.matchaDark,

                  disabledBackgroundColor: const Color(0xFFF1F5F9),

                  foregroundColor: Colors.white,

                  disabledForegroundColor: const Color(0xFF94A3B8),

                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),

                  shape: RoundedRectangleBorder(

                    borderRadius: BorderRadius.circular(10),

                  ),

                  elevation: 0,

                ),

                child: Text(

                  isCompleted

                      ? '🔒 Skor Terkunci (Selesai)'

                      : (!_isCurrentViewedRoundActive

                          ? '🔒 Ronde Tidak Aktif'

                          : buttonLabel),

                  textAlign: TextAlign.center,

                  maxLines: 2,

                  style: const TextStyle(

                    fontWeight: FontWeight.bold,

                    fontSize: 11,

                    height: 1.1,

                  ),

                ),

              ),

            ),

        ],

      ),

    );

  }



  Widget _buildScoreboardCard(DrawingMatch match) {

    final isCompleted = match.status == 'Completed';

    final isStopped = match.isStopped || match.status.toLowerCase() == 'stopped';

    final isTerminal = isCompleted || isStopped;

    final matchId = match.matchId;

    final hasConflict = matchId != null && _conflictSnapshots.containsKey(matchId);

    final pendingCount = matchId != null ? (_pendingEventsByMatch[matchId]?.length ?? 0) : 0;

    final saveStatus = matchId != null ? _matchSaveStatus[matchId] : null;

    final canInput = isHost && _isCurrentViewedRoundActive && !isTerminal;

    final currentRound = _rounds[_viewedRoundIndex.clamp(0, _rounds.length - 1)];



    return Container(

      margin: const EdgeInsets.only(bottom: 16),

      decoration: BoxDecoration(

        color: Colors.white,

        borderRadius: BorderRadius.circular(18),

        border: Border.all(

          color: hasConflict

              ? const Color(0xFFF87171)

              : const Color(0xFFE2E8F0),

          width: hasConflict ? 1.5 : 1.0,

        ),

        boxShadow: [

          BoxShadow(

            color: Colors.black.withValues(alpha: 0.02),

            blurRadius: 8,

            offset: const Offset(0, 2),

          ),

        ],

      ),

      child: Column(

        crossAxisAlignment: CrossAxisAlignment.start,

        children: [

          // 1. Informasi Match Header (Venue, Court, Status, Badges)

          Padding(

            padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),

            child: Column(

              crossAxisAlignment: CrossAxisAlignment.start,

              children: [

                Row(

                  mainAxisAlignment: MainAxisAlignment.spaceBetween,

                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [

                    Expanded(

                      child: Column(

                        crossAxisAlignment: CrossAxisAlignment.start,

                        children: [

                          Text(

                            (_config.venueName != null && _config.venueName!.trim().isNotEmpty)

                                ? '${_config.venueName!.trim()} • Court ${match.courtNumber}'

                                : 'Court ${match.courtNumber}',

                            style: const TextStyle(

                              fontWeight: FontWeight.w800,

                              fontSize: 14,

                              color: Color(0xFF0F172A),

                            ),

                          ),

                          const SizedBox(height: 2),

                          Text(

                            'Ronde ${currentRound.roundNumber} • ${_config.gameType.isNotEmpty ? _config.gameType : "Americano"} (${_config.playMode == "Double" ? "2v2" : "1v1"})',

                            style: const TextStyle(

                              fontSize: 11,

                              fontWeight: FontWeight.w600,

                              color: Color(0xFF64748B),

                            ),

                          ),

                        ],

                      ),

                    ),

                    const SizedBox(width: 8),

                    // Priority Badges: Conflict / Error > Menyimpan > Match Status

                    Wrap(

                      spacing: 6,

                      runSpacing: 4,

                      alignment: WrapAlignment.end,

                      children: [

                        if (hasConflict) ...[

                          Container(

                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),

                            decoration: BoxDecoration(

                              color: const Color(0xFFFEF2F2),

                              borderRadius: BorderRadius.circular(6),

                              border: Border.all(color: const Color(0xFFFCA5A5)),

                            ),

                            child: const Text(

                              '⚠️ Konflik',

                              style: TextStyle(

                                fontSize: 9.5,

                                fontWeight: FontWeight.bold,

                                color: Color(0xFFDC2626),

                              ),

                            ),

                          ),

                        ] else if (saveStatus == 'error') ...[

                          Container(

                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),

                            decoration: BoxDecoration(

                              color: const Color(0xFFFEE2E2),

                              borderRadius: BorderRadius.circular(6),

                              border: Border.all(color: const Color(0xFFFCA5A5)),

                            ),

                            child: const Text(

                              '⚠️ Gagal Simpan',

                              style: TextStyle(

                                fontSize: 9.5,

                                fontWeight: FontWeight.bold,

                                color: Color(0xFFDC2626),

                              ),

                            ),

                          ),

                        ] else if (pendingCount > 0) ...[

                          Container(

                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),

                            decoration: BoxDecoration(

                              color: const Color(0xFFFEF3C7),

                              borderRadius: BorderRadius.circular(6),

                              border: Border.all(color: const Color(0xFFFDE68A)),

                            ),

                            child: Row(

                              mainAxisSize: MainAxisSize.min,

                              children: [

                                const SizedBox(

                                  width: 8,

                                  height: 8,

                                  child: CircularProgressIndicator(

                                    strokeWidth: 1.5,

                                    color: Color(0xFFB45309),

                                  ),

                                ),

                                const SizedBox(width: 4),

                                Text(

                                  'Menyimpan ($pendingCount)',

                                  style: const TextStyle(

                                    fontSize: 9.5,

                                    fontWeight: FontWeight.bold,

                                    color: Color(0xFFB45309),

                                  ),

                                ),

                              ],

                            ),

                          ),

                        ],

                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: match.isStopped
                                ? const Color(0xFFFEE2E2)
                                : (isCompleted
                                    ? const Color(0xFFF1F5F9)
                                    : const Color(0xFFDCFCE7)),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(
                              color: match.isStopped
                                  ? const Color(0xFFFCA5A5)
                                  : (isCompleted
                                      ? const Color(0xFFE2E8F0)
                                      : const Color(0xFFBBF7D0)),
                            ),
                          ),
                          child: Text(
                            match.isStopped
                                ? 'Dihentikan (${match.scoreA} - ${match.scoreB})'
                                : (isCompleted
                                    ? (match.winnerTeam != null
                                        ? 'Selesai (${match.winnerTeam} Win)'
                                        : 'Selesai')
                                    : 'Sedang Berlangsung'),
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              color: match.isStopped
                                  ? const Color(0xFFDC2626)
                                  : (isCompleted
                                      ? const Color(0xFF64748B)
                                      : const Color(0xFF15803D)),
                            ),
                          ),
                        ),
                        if (isHost && _isCurrentViewedRoundActive && !isCompleted && !match.isStopped) ...[
                          const SizedBox(width: 4),
                          InkWell(
                            onTap: () => _showStopMatchDialog(match),
                            borderRadius: BorderRadius.circular(6),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFEF2F2),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: const Color(0xFFFECACA)),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.stop_circle_outlined, size: 11, color: Color(0xFFDC2626)),
                                  SizedBox(width: 3),
                                  Text(
                                    'Hentikan',
                                    style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: Color(0xFFDC2626)),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],

                      ],

                    ),

                  ],

                ),

                const SizedBox(height: 8),

                // Sport & Scoring Format Chips

                Wrap(

                  spacing: 6,

                  runSpacing: 4,

                  children: [

                    Container(

                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),

                      decoration: BoxDecoration(

                        color: AppColors.matchaSoftLime,

                        borderRadius: BorderRadius.circular(6),

                      ),

                      child: Text(

                        _config.sport.isNotEmpty ? _config.sport : 'Padel',

                        style: const TextStyle(

                          fontSize: 10,

                          fontWeight: FontWeight.bold,

                          color: AppColors.matchaDark,

                        ),

                      ),

                    ),

                    Container(

                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),

                      decoration: BoxDecoration(

                        color: const Color(0xFFF1F5F9),

                        borderRadius: BorderRadius.circular(6),

                        border: Border.all(color: const Color(0xFFE2E8F0)),

                      ),

                      child: Text(

                        '${_scoringConfig.label} • ${_scoringConfig.isSets ? "Target ${_scoringConfig.targetGames} Games/Set" : "Target ${_scoringConfig.targetGames} Games"}',

                        style: const TextStyle(

                          fontSize: 10,

                          fontWeight: FontWeight.w600,

                          color: Color(0xFF475569),

                        ),

                      ),

                    ),

                  ],

                ),

              ],

            ),

          ),



          // 2. Conflict Banner jika ada

          if (hasConflict)

            Container(

              margin: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),

              padding: const EdgeInsets.all(10),

              decoration: BoxDecoration(

                color: const Color(0xFFFEF2F2),

                borderRadius: BorderRadius.circular(10),

                border: Border.all(color: const Color(0xFFFCA5A5)),

              ),

              child: Column(

                crossAxisAlignment: CrossAxisAlignment.start,

                children: [

                  const Row(

                    children: [

                      Icon(Icons.warning_amber_rounded, color: Color(0xFFDC2626), size: 16),

                      SizedBox(width: 6),

                      Text(

                        'Konflik Skor Terdeteksi!',

                        style: TextStyle(

                          fontSize: 12,

                          fontWeight: FontWeight.bold,

                          color: Color(0xFF991B1B),

                        ),

                      ),

                    ],

                  ),

                  const SizedBox(height: 4),

                  const Text(

                    'Versi skor server berbeda dengan antrean lokal. Pilih resolusi rekonsiliasi:',

                    style: TextStyle(fontSize: 11, color: Color(0xFF7F1D1D)),

                  ),

                  const SizedBox(height: 8),

                  Row(

                    children: [

                      ElevatedButton(

                        onPressed: () => _resolveConflictByAcceptingServer(matchId),

                        style: ElevatedButton.styleFrom(

                          backgroundColor: const Color(0xFFDC2626),

                          foregroundColor: Colors.white,

                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),

                          textStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),

                          elevation: 0,

                        ),

                        child: const Text('Terima Server'),

                      ),

                      const SizedBox(width: 8),

                      OutlinedButton(

                        onPressed: () => _refetchAndRecover(matchId),

                        style: OutlinedButton.styleFrom(

                          foregroundColor: const Color(0xFF991B1B),

                          side: const BorderSide(color: Color(0xFFF87171)),

                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),

                          textStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),

                        ),

                        child: const Text('Muat Ulang Data'),

                      ),

                    ],

                  ),

                ],

              ),

            ),



          // 3. Ringkasan Round Score (Selalu tampil, bahkan untuk 1 court)

          Container(

            width: double.infinity,

            margin: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),

            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 14),

            decoration: BoxDecoration(

              color: const Color(0xFFF8FAFC),

              borderRadius: BorderRadius.circular(12),

              border: Border.all(color: const Color(0xFFE2E8F0)),

            ),

            child: Column(

              children: [

                const Text(

                  'Round score',

                  style: TextStyle(

                    fontSize: 11,

                    fontWeight: FontWeight.w700,

                    color: Color(0xFF64748B),

                    letterSpacing: 0.5,

                  ),

                ),

                const SizedBox(height: 2),

                Text(

                  '${match.scoreA} – ${match.scoreB}',

                  style: const TextStyle(

                    fontSize: 30,

                    fontWeight: FontWeight.w900,

                    color: Color(0xFF0F172A),

                    letterSpacing: 2,

                  ),

                ),

                const SizedBox(height: 1),

                const Text(

                  'Skor game',

                  style: TextStyle(

                    fontSize: 10.5,

                    fontWeight: FontWeight.w600,

                    color: Color(0xFF94A3B8),

                  ),

                ),

              ],

            ),

          ),



          // 4. Deuce / Advantage Banner

          if (match.isDeuce && !isCompleted)

            Container(

              width: double.infinity,

              margin: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),

              padding: const EdgeInsets.symmetric(vertical: 6),

              decoration: BoxDecoration(

                color: const Color(0xFFFEF3C7),

                borderRadius: BorderRadius.circular(8),

                border: Border.all(color: const Color(0xFFFDE68A)),

              ),

              child: Text(

                match.advantage != null

                    ? '⚡ ADVANTAGE ${match.advantage == "A" ? "TEAM A" : "TEAM B"} ⚡'

                    : '⚡ DEUCE (40 - 40) ⚡',

                textAlign: TextAlign.center,

                style: const TextStyle(

                  fontSize: 11,

                  fontWeight: FontWeight.w900,

                  color: Color(0xFFB45309),

                  letterSpacing: 0.5,

                ),

              ),

            ),



          // 5. Scoreboard Tim Berdampingan (Side-by-Side Team Panels)

          Padding(

            padding: const EdgeInsets.all(14),

            child: IntrinsicHeight(

              child: Row(

                crossAxisAlignment: CrossAxisAlignment.stretch,

                children: [

                  Expanded(

                    child: _buildTeamScorePanel(

                      match: match,

                      isTeamA: true,

                      canInput: canInput,

                      isCompleted: isCompleted && !isStopped,

                    ),

                  ),

                  Container(

                    margin: const EdgeInsets.symmetric(horizontal: 8),

                    child: Column(

                      mainAxisAlignment: MainAxisAlignment.center,

                      children: [

                        Container(

                          width: 1,

                          height: 36,

                          color: const Color(0xFFE2E8F0),

                        ),

                        Container(

                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),

                          decoration: BoxDecoration(

                            color: const Color(0xFFF1F5F9),

                            borderRadius: BorderRadius.circular(8),

                            border: Border.all(color: const Color(0xFFCBD5E1)),

                          ),

                          child: const Text(

                            'VS',

                            style: TextStyle(

                              fontSize: 9,

                              fontWeight: FontWeight.w900,

                              color: Color(0xFF64748B),

                            ),

                          ),

                        ),

                        Container(

                          width: 1,

                          height: 36,

                          color: const Color(0xFFE2E8F0),

                        ),

                      ],

                    ),

                  ),

                  Expanded(

                    child: _buildTeamScorePanel(

                      match: match,

                      isTeamA: false,

                      canInput: canInput,

                      isCompleted: isCompleted && !isStopped,

                    ),

                  ),

                ],

              ),

            ),

          ),

          // 6. Action Bar: Akhiri Pertandingan & Sinkron Realtime (Matches Web)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                if (isHost && _isCurrentViewedRoundActive && !isCompleted && !isStopped)
                  InkWell(
                    onTap: () => _showStopMatchDialog(match),
                    borderRadius: BorderRadius.circular(10),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEF2F2),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFFECACA)),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.stop_rounded, size: 14, color: Color(0xFFDC2626)),
                          SizedBox(width: 5),
                          Text(
                            'Akhiri Pertandingan',
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFFDC2626),
                            ),
                          ),
                        ],
                      ),
                    ),
                  )
                else
                  const SizedBox.shrink(),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: _isRealtimeConnected ? const Color(0xFF16A34A) : const Color(0xFFF59E0B),
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 4),
                    const Text(
                      'Sinkron realtime: 0.4s',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w500,
                        color: Color(0xFF94A3B8),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],

      ),

    );

  }

  /// Card Status Pertandingan Ronde (Selalu tampil di bawah scoreboard, identik dengan Web)
  Widget _buildRoundStatusCard(DrawingRound currentRound) {
    final uncompletedMatches = currentRound.matches.where((m) => !m.isTerminal).toList();
    final isAllCompleted = currentRound.matches.isNotEmpty && uncompletedMatches.isEmpty;
    final hasNextRound = _sessionActiveRoundIndex + 1 < _rounds.length;

    String descText;
    if (isAllCompleted) {
      if (isHost) {
        descText = 'Semua court telah menyelesaikan pertandingan. Silakan lanjut ke ${hasNextRound ? "ronde berikutnya" : "hasil akhir & podium"}.';
      } else {
        descText = 'Semua court telah menyelesaikan pertandingan. Menunggu Host ${hasNextRound ? "memulai ronde berikutnya" : "menyelesaikan sesi"}...';
      }
    } else {
      final uncompletedCourts = uncompletedMatches.map((m) => 'Court ${m.courtNumber}').join(', ');
      descText = uncompletedCourts.isNotEmpty
          ? 'Menunggu $uncompletedCourts menyelesaikan pertandingan...'
          : 'Pertandingan sedang berlangsung pada seluruh court...';
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: const Color(0xFFDCFCE7),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFBBF7D0)),
                ),
                child: const Icon(Icons.sports_score_rounded, color: Color(0xFF15803D), size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: isAllCompleted ? const Color(0xFF16A34A) : const Color(0xFFF59E0B),
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            isAllCompleted
                                ? 'Seluruh Pertandingan Ronde ${currentRound.roundNumber} Selesai!'
                                : 'Status Pertandingan Ronde ${currentRound.roundNumber}',
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF0F172A),
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      descText,
                      style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (isAllCompleted && isHost && _isCurrentViewedRoundActive) ...[
            const SizedBox(height: 12),
            if (hasNextRound)
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _isAdvancingRound ? null : _advanceToNextRound,
                  icon: const Icon(Icons.arrow_forward_rounded, size: 16),
                  label: Text('Lanjut ke Ronde ${_sessionActiveRoundIndex + 2} (Lihat Drawing Lapangan)'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.matchaDark,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                ),
              )
            else
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _finishSessionAndShowRecap,
                  icon: const Icon(Icons.emoji_events_rounded, size: 16),
                  label: const Text('Selesaikan Sesi & Lihat Juara'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.matchaDark,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }

  /// Card Status Pemain & Rotasi Sesi (Selalu tampil di bawah status ronde, identik dengan Web)
  Widget _buildPlayerReadinessAndRotationCard(DrawingRound currentRound) {
    final allRoster = _config.players.isNotEmpty ? _config.players : <GamePlayerItem>[];
    final activeCount = allRoster.where((p) => p.isActive).length;
    final restingCount = allRoster.where((p) => p.isResting).length;
    final withdrawnCount = allRoster.where((p) => p.isWithdrawn).length;

    // Pemain aktif bertanding pada ronde ini di seluruh court
    final roundActivePlayerCleanNames = <String>{};
    final roundActivePlayerIds = <int>{};
    for (final m in currentRound.matches) {
      for (final p in [...m.teamA, ...m.teamB]) {
        roundActivePlayerCleanNames.add(p.name.trim().toLowerCase());
        final pInt = MatchService.resolvePlayerId(p, allRoster) ?? p.playerId ?? int.tryParse(p.id);
        if (pInt != null) roundActivePlayerIds.add(pInt);
      }
    }

    // Bangku Cadangan (Bench):
    // 1. Pemain sesi yang tidak keluar & tidak sedang bertanding di lapangan ronde ini
    // 2. Pemain resting / paused
    final benchPlayers = <GamePlayerItem>[];
    final benchNames = <String>{};

    for (final p in allRoster) {
      if (p.isWithdrawn) continue;
      final cleanName = p.name.trim().toLowerCase();
      final pInt = MatchService.resolvePlayerId(p, allRoster) ?? p.playerId ?? int.tryParse(p.id);
      final isPlaying = roundActivePlayerCleanNames.contains(cleanName) ||
          (pInt != null && roundActivePlayerIds.contains(pInt));

      if (!isPlaying || p.isResting) {
        if (!benchNames.contains(cleanName)) {
          benchNames.add(cleanName);
          benchPlayers.add(p);
        }
      }
    }

    for (final rp in currentRound.restingPlayers) {
      final cleanName = rp.name.trim().toLowerCase();
      if (!benchNames.contains(cleanName)) {
        benchNames.add(cleanName);
        benchPlayers.add(rp);
      }
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: const Color(0xFFDCFCE7),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFBBF7D0)),
                ),
                child: const Icon(Icons.people_alt_rounded, color: Color(0xFF15803D), size: 20),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        const Text(
                          'Status Pemain & Rotasi Sesi',
                          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                          decoration: BoxDecoration(
                            color: const Color(0xFFDCFCE7),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: const Color(0xFFBBF7D0)),
                          ),
                          child: Text(
                            '$activeCount Siap / Aktif',
                            style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: Color(0xFF15803D)),
                          ),
                        ),
                        if (restingCount > 0)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFEF3C7),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(color: const Color(0xFFFDE68A)),
                            ),
                            child: Text(
                              '$restingCount Istirahat',
                              style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                            ),
                          ),
                        if (withdrawnCount > 0)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFEE2E2),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(color: const Color(0xFFFECACA)),
                            ),
                            child: Text(
                              '$withdrawnCount Keluar',
                              style: const TextStyle(fontSize: 9.5, fontWeight: FontWeight.bold, color: Color(0xFF991B1B)),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Status partisipasi aktual seluruh peserta dan alokasi rotasi ronde ini',
                      style: TextStyle(fontSize: 10.5, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
              if (isHost)
                IconButton(
                  icon: const Icon(Icons.tune_rounded, size: 18, color: Color(0xFF64748B)),
                  tooltip: 'Kelola Kesiapan Pemain',
                  onPressed: _showPlayerStatusManagementModal,
                  constraints: const BoxConstraints(),
                  padding: const EdgeInsets.all(4),
                ),
            ],
          ),
          const SizedBox(height: 12),

          // Partisipasi Pemain Sesi
          Row(
            children: [
              Container(width: 6, height: 6, decoration: const BoxDecoration(color: Color(0xFF16A34A), shape: BoxShape.circle)),
              const SizedBox(width: 6),
              const Text(
                'Partisipasi Pemain Sesi',
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: allRoster.map((p) {
              final isWithdrawn = p.isWithdrawn;
              final isResting = p.isResting;

              Color dotColor;
              Color badgeBg;
              Color badgeFg;
              String badgeLabel;

              if (isWithdrawn) {
                dotColor = const Color(0xFFDC2626);
                badgeBg = const Color(0xFFFEE2E2);
                badgeFg = const Color(0xFF991B1B);
                badgeLabel = 'Keluar';
              } else if (isResting) {
                dotColor = const Color(0xFFD97706);
                badgeBg = const Color(0xFFFEF3C7);
                badgeFg = const Color(0xFF92400E);
                badgeLabel = 'Istirahat';
              } else {
                dotColor = const Color(0xFF16A34A);
                badgeBg = const Color(0xFFDCFCE7);
                badgeFg = const Color(0xFF15803D);
                badgeLabel = 'Siap';
              }

              return Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: isWithdrawn ? const Color(0xFFFFF1F2) : (isResting ? const Color(0xFFFFFBEB) : Colors.white),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: isWithdrawn
                        ? const Color(0xFFFECACA)
                        : (isResting ? const Color(0xFFFDE68A) : const Color(0xFFE2E8F0)),
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(width: 6, height: 6, decoration: BoxDecoration(color: dotColor, shape: BoxShape.circle)),
                    const SizedBox(width: 5),
                    Text(
                      p.name,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: isWithdrawn ? const Color(0xFF94A3B8) : const Color(0xFF1E293B),
                        decoration: isWithdrawn ? TextDecoration.lineThrough : null,
                      ),
                    ),
                    const SizedBox(width: 5),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                      decoration: BoxDecoration(
                        color: badgeBg,
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        badgeLabel,
                        style: TextStyle(fontSize: 8.5, fontWeight: FontWeight.bold, color: badgeFg),
                      ),
                    ),
                  ],
                ),
              );
            }).toList(),
          ),

          // Bangku Istirahat (Bench) Section
          if (benchPlayers.isNotEmpty) ...[
            const SizedBox(height: 12),
            const Divider(height: 1, color: Color(0xFFF1F5F9)),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    const Icon(Icons.coffee_rounded, size: 14, color: Color(0xFFD97706)),
                    const SizedBox(width: 5),
                    const Text(
                      'Bangku Istirahat (Bench) Ronde Ini',
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                    ),
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEF3C7),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFFDE68A)),
                      ),
                      child: Text(
                        '${benchPlayers.length} Pemain',
                        style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                      ),
                    ),
                  ],
                ),
                const Text(
                  'Rotasi bermain ronde berikutnya',
                  style: TextStyle(fontSize: 9.5, color: Color(0xFF94A3B8), fontWeight: FontWeight.w500),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: benchPlayers.map((p) {
                final isResting = p.isResting;
                return Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: isResting ? const Color(0xFFFFFBEB) : Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: isResting ? const Color(0xFFFDE68A) : const Color(0xFFE2E8F0),
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 6,
                        height: 6,
                        decoration: BoxDecoration(
                          color: isResting ? const Color(0xFFD97706) : const Color(0xFFF59E0B),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 5),
                      Text(
                        p.name,
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF1E293B)),
                      ),
                      const SizedBox(width: 5),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                        decoration: BoxDecoration(
                          color: isResting ? const Color(0xFFFEF3C7) : const Color(0xFFFEF9C3),
                          borderRadius: BorderRadius.circular(4),
                          border: Border.all(
                            color: isResting ? const Color(0xFFFDE68A) : const Color(0xFFFEF08A),
                          ),
                        ),
                        child: Text(
                          isResting ? 'Istirahat' : 'Bench',
                          style: TextStyle(
                            fontSize: 8.5,
                            fontWeight: FontWeight.bold,
                            color: isResting ? const Color(0xFF92400E) : const Color(0xFF854D0E),
                          ),
                        ),
                      ),
                    ],
                  ),
                );
              }).toList(),
            ),
          ],
        ],
      ),
    );
  }

}

