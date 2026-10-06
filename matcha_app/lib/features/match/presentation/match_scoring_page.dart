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



  bool get _isCurrentActiveRoundFinished {

    if (_rounds.isEmpty) return false;

    final activeRound = _rounds[_sessionActiveRoundIndex.clamp(0, _rounds.length - 1)];

    return activeRound.matches.isNotEmpty && activeRound.matches.every((m) => m.status == 'Completed');

  }



  bool get _isAllRoundsFinished {

    if (_isSessionFinished) return true;

    if (_rounds.isEmpty) return false;

    return _rounds.every(

      (r) => r.matches.isNotEmpty && r.matches.every((m) => m.status == 'Completed'),

    );

  }



  int _resolveActiveRoundIndex(List<DrawingRound> rounds) {
    if (rounds.isEmpty) return 0;

    for (int i = 0; i < rounds.length; i++) {
      final matches = rounds[i].matches;
      final isFinished = matches.isNotEmpty && matches.every((m) => m.status == 'Completed');

      if (!isFinished) {
        return i;
      }

      // Jika ronde i sudah selesai tapi ronde berikutnya belum dimulai (belum ada poin/match jalan),
      // maka ronde aktif tetap ronde i (menunggu Host klik "Lanjut ke Ronde i+1").
      if (i + 1 < rounds.length) {
        final nextMatches = rounds[i + 1].matches;
        final nextStarted = nextMatches.any(
          (m) => m.status == 'In Progress' || m.status == 'Completed' || m.scoreA > 0 || m.scoreB > 0,
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



    _sessionActiveRoundIndex = _resolveActiveRoundIndex(_rounds);

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



      // 2. Ambil matches & scores riil dari backend

      final dbMatches = await _matchService.getMatchesForSession(widget.sessionId);

      if (dbMatches.isNotEmpty) {

        final Map<int, List<DrawingMatch>> roundMap = {};

        for (final m in dbMatches) {

          final rawMatch = m['rawMatch'] as Map<String, dynamic>?;

          final rNum = (m['roundNumber'] ?? rawMatch?['round_number'] ?? 1) as int;

          final courtNum = (m['courtNumber'] ?? rawMatch?['court_id'] ?? m['nomorMatch'] ?? 1) as int;



          List<GamePlayerItem> teamAPlayers = [];
          if (m['teamAPlayers'] != null && (m['teamAPlayers'] as List).isNotEmpty) {
            teamAPlayers = (m['teamAPlayers'] as List).map((p) {
              final map = p as Map<String, dynamic>;
              final pId = map['playerId'] ?? map['id'];
              return GamePlayerItem(
                id: pId != null ? pId.toString() : (map['name']?.toString() ?? ''),
                name: map['name']?.toString() ?? 'Player A',
                avatarUrl: map['avatarUrl']?.toString(),
                level: map['level']?.toString() ?? 'Beginner',
              );
            }).toList();
          } else if (m['sideA'] != null &&
              m['sideA'].toString().trim().isNotEmpty &&
              !m['sideA'].toString().toLowerCase().startsWith('side ') &&
              !m['sideA'].toString().toLowerCase().startsWith('team ')) {
            final sideANames = (m['sideA']).toString().split(' · ');
            teamAPlayers = sideANames.map((n) => GamePlayerItem(id: n, name: n)).toList();
          } else if (!isRealBackendSession) {
            final sideANames = (m['sideA'] ?? 'Team A').toString().split(' · ');
            teamAPlayers = sideANames.map((n) => GamePlayerItem(id: n, name: n)).toList();
          }

          List<GamePlayerItem> teamBPlayers = [];
          if (m['teamBPlayers'] != null && (m['teamBPlayers'] as List).isNotEmpty) {
            teamBPlayers = (m['teamBPlayers'] as List).map((p) {
              final map = p as Map<String, dynamic>;
              final pId = map['playerId'] ?? map['id'];
              return GamePlayerItem(
                id: pId != null ? pId.toString() : (map['name']?.toString() ?? ''),
                name: map['name']?.toString() ?? 'Player B',
                avatarUrl: map['avatarUrl']?.toString(),
                level: map['level']?.toString() ?? 'Beginner',
              );
            }).toList();
          } else if (m['sideB'] != null &&
              m['sideB'].toString().trim().isNotEmpty &&
              !m['sideB'].toString().toLowerCase().startsWith('side ') &&
              !m['sideB'].toString().toLowerCase().startsWith('team ')) {
            final sideBNames = (m['sideB']).toString().split(' · ');
            teamBPlayers = sideBNames.map((n) => GamePlayerItem(id: n, name: n)).toList();
          } else if (!isRealBackendSession) {
            final sideBNames = (m['sideB'] ?? 'Team B').toString().split(' · ');
            teamBPlayers = sideBNames.map((n) => GamePlayerItem(id: n, name: n)).toList();
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



          final drawingMatch = DrawingMatch(

            courtNumber: courtNum,

            teamA: teamAPlayers,

            teamB: teamBPlayers,

            status: restored.isCompleted ? 'Completed' : 'In Progress',

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

          _sessionActiveRoundIndex = _resolveActiveRoundIndex(_rounds);

          _viewedRoundIndex = _sessionActiveRoundIndex;

        }

      } else {
        final savedDrawing = await _matchService.loadSavedDrawing(sessionId: widget.sessionId);
        if (savedDrawing != null && savedDrawing.isNotEmpty) {
          _rounds = savedDrawing;
          _sessionActiveRoundIndex = _resolveActiveRoundIndex(_rounds);
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

    try {

      _realtimeChannel = _matchService.subscribeLiveSession(

        sessionId: widget.sessionId,

        onDataChanged: _onRealtimeScoreChanged,

        onRoundAdvanced: (payload) {
          if (!mounted) return;
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
          if (nextRoundNum != null && nextRoundNum > 0) {
            final nextIdx = nextRoundNum - 1;
            if (nextIdx < _rounds.length && nextIdx > _sessionActiveRoundIndex) {
              setState(() {
                _sessionActiveRoundIndex = nextIdx;
                _viewedRoundIndex = nextIdx;
                _selectedCourtIndex = 0;
              });
            }
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

      final dbMatches = await _matchService.getMatchesForSession(widget.sessionId);

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

                  localMatch.status = restored.isCompleted ? 'Completed' : 'In Progress';

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



        // Periksa apakah ronde berikutnya sudah memiliki pertandingan yang sedang/telah berjalan
        for (int r = _rounds.length - 1; r > _sessionActiveRoundIndex; r--) {
          final hasStartedMatch = _rounds[r].matches.any(
            (m) => m.status == 'Completed' || m.scoreA > 0 || m.scoreB > 0,
          );
          if (hasStartedMatch) {
            _sessionActiveRoundIndex = r;
            break;
          }
        }
      });

    } catch (_) {}

  }



  void _addPointTeamA(DrawingMatch match) {

    if (!isHost) return;

    if (!_isCurrentViewedRoundActive) return;

    if (match.status == 'Completed') return;



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

    if (match.status == 'Completed') return;



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



  void _walkoverMatch(DrawingMatch match, bool winForTeamA) {

    if (!isHost) return;

    if (!_isCurrentViewedRoundActive) return;

    if (match.status == 'Completed') return;



    final eventId = 'evt_mob_walkover_${DateTime.now().millisecondsSinceEpoch}';

    final target = _scoringConfig.targetGames;



    final currentState = ScoringMatchState(

      gamesA: match.scoreA,

      gamesB: match.scoreB,

      setNumber: _sessionActiveRoundIndex + 1,

      version: match.version,

    );



    final nextState = ScoringEngine.applyCompletion(

      currentState: currentState,

      scoringSystem: _scoringConfig,

      explicitWinner: winForTeamA ? 'Team A' : 'Team B',

      incomingGamesA: winForTeamA ? target : match.scoreA,

      incomingGamesB: !winForTeamA ? target : match.scoreB,

      eventId: eventId,

    );



    setState(() {

      match.scoreA = nextState.gamesA;

      match.scoreB = nextState.gamesB;

      match.gamesWonA = nextState.gamesA;

      match.gamesWonB = nextState.gamesB;

      match.idxA = 0;

      match.idxB = 0;

      match.pointDisplayA = nextState.pointDisplayA;

      match.pointDisplayB = nextState.pointDisplayB;

      match.isDeuce = false;

      match.advantage = null;

      match.setsA = nextState.setsA;

      match.setsB = nextState.setsB;

      match.winnerTeam = nextState.winnerTeam;

      match.version = nextState.version;

      match.lastEventId = eventId;

      match.status = 'Completed';

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



  void _advanceToNextRound() {

    if (!isHost) return;

    if (_sessionActiveRoundIndex >= _rounds.length - 1) return;

    final curr = _sessionActiveRoundIndex + 1;

    final next = _sessionActiveRoundIndex + 2;



    setState(() {
      _sessionActiveRoundIndex++;
      _viewedRoundIndex = _sessionActiveRoundIndex;
      _selectedCourtIndex = 0;
      for (final m in _rounds[_sessionActiveRoundIndex].matches) {
        if (m.status == 'Scheduled') {
          m.status = 'In Progress';
        }
      }
    });

    if (widget.sessionId != null) {
      final nextMatches = _rounds[_sessionActiveRoundIndex].matches;
      final matchIds = nextMatches.map((m) => m.matchId).whereType<int>().toList();
      _matchService.advanceRoundMatches(
        sessionId: widget.sessionId,
        roundNumber: next,
        matchIds: matchIds,
      );
      _matchService.broadcastRoundAdvanced(
        sessionId: widget.sessionId,
        currentRoundNum: curr,
        nextRoundNum: next,
      );
    }
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
          IconButton(
            icon: const Icon(Icons.leaderboard_rounded, color: Color(0xFF0F172A), size: 22),
            tooltip: 'Klasemen & Rekap',
            onPressed: _openRecapPage,
          ),
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

                final r = _rounds[idx];

                final isFinished = r.matches.every(

                  (m) => m.status == 'Completed',

                );



                return Container(

                  margin: const EdgeInsets.only(right: 8),

                  child: ChoiceChip(

                    label: Row(

                      mainAxisSize: MainAxisSize.min,

                      children: [

                        Text('Ronde ${r.roundNumber}'),

                        if (isActiveRound) ...[

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

                          const Icon(

                            Icons.check,

                            size: 12,

                            color: Colors.white,

                          ),

                        ],

                      ],

                    ),

                    selected: isSelected,

                    selectedColor: AppColors.matchaDark,

                    backgroundColor: Colors.white,

                    labelStyle: TextStyle(

                      fontSize: 12,

                      fontWeight: FontWeight.bold,

                      color: isSelected

                          ? Colors.white

                          : const Color(0xFF475569),

                    ),

                    shape: RoundedRectangleBorder(

                      borderRadius: BorderRadius.circular(10),

                      side: BorderSide(

                        color: isSelected

                            ? AppColors.matchaDark

                            : const Color(0xFFE2E8F0),

                      ),

                    ),

                    onSelected: (val) {

                      if (val) {

                        setState(() {

                          _viewedRoundIndex = idx;

                          _selectedCourtIndex = 0;

                        });

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

                    child: Text(

                      'Mode Tinjau Riwayat Ronde ${currentRound.roundNumber} • Ronde Aktif saat ini: Ronde ${_sessionActiveRoundIndex + 1}. Tombol skor dikunci.',

                      style: const TextStyle(

                        fontSize: 12,

                        fontWeight: FontWeight.bold,

                        color: Color(0xFF334155),

                      ),

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



          // Round Finished Notification Banner

          if (_isCurrentActiveRoundFinished && !_isAllRoundsFinished)

            Container(

              margin: const EdgeInsets.only(top: 8),

              padding: const EdgeInsets.all(14),

              decoration: BoxDecoration(

                color: const Color(0xFFFEF3C7),

                borderRadius: BorderRadius.circular(14),

                border: Border.all(color: const Color(0xFFFDE68A)),

              ),

              child: Column(

                crossAxisAlignment: CrossAxisAlignment.start,

                children: [

                  Row(

                    children: [

                      const Icon(

                        Icons.emoji_events_rounded,

                        color: Color(0xFFD97706),

                        size: 20,

                      ),

                      const SizedBox(width: 10),

                      Expanded(

                        child: Column(

                          crossAxisAlignment: CrossAxisAlignment.start,

                          children: [

                            Text(

                              'Ronde ${_sessionActiveRoundIndex + 1} Selesai',

                              style: const TextStyle(

                                fontWeight: FontWeight.w800,

                                fontSize: 13,

                                color: Color(0xFF92400E),

                              ),

                            ),

                            const SizedBox(height: 2),

                            Text(

                              isHost

                                  ? 'Host dapat melanjutkan ke ronde berikutnya atau menyelesaikan sesi.'

                                  : 'Menunggu Host untuk melanjutkan ke ronde berikutnya.',

                              style: const TextStyle(

                                fontSize: 11,

                                color: Color(0xFFB45309),

                              ),

                            ),

                          ],

                        ),

                      ),

                    ],

                  ),

                  if (isHost) ...[

                    const SizedBox(height: 12),

                    Row(

                      children: [

                        Expanded(

                          child: ElevatedButton.icon(

                            onPressed: _advanceToNextRound,

                            icon: const Icon(Icons.arrow_forward_rounded, size: 16),

                            label: Text(

                              'Lanjut ke Ronde ${_sessionActiveRoundIndex + 2}',

                              style: const TextStyle(

                                fontWeight: FontWeight.bold,

                                fontSize: 12,

                              ),

                            ),

                            style: ElevatedButton.styleFrom(

                              backgroundColor: AppColors.matchaDark,

                              foregroundColor: Colors.white,

                              shape: RoundedRectangleBorder(

                                borderRadius: BorderRadius.circular(10),

                              ),

                              elevation: 0,

                            ),

                          ),

                        ),

                        const SizedBox(width: 8),

                        OutlinedButton(

                          onPressed: _finishSessionAndShowRecap,

                          style: OutlinedButton.styleFrom(

                            foregroundColor: const Color(0xFF92400E),

                            side: const BorderSide(color: Color(0xFFD97706)),

                            shape: RoundedRectangleBorder(

                              borderRadius: BorderRadius.circular(10),

                            ),

                          ),

                          child: const Text(

                            'Selesaikan Sesi',

                            style: TextStyle(

                              fontWeight: FontWeight.bold,

                              fontSize: 12,

                            ),

                          ),

                        ),

                      ],

                    ),

                  ],

                ],

              ),

            ),



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



  void _showWalkoverConfirmation(DrawingMatch match) {

    showDialog(

      context: context,

      builder: (ctx) => AlertDialog(

        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),

        title: const Row(

          children: [

            Icon(Icons.warning_amber_rounded, color: Color(0xFFDC2626), size: 22),

            SizedBox(width: 8),

            Text(

              'Konfirmasi Walkover',

              style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),

            ),

          ],

        ),

        content: Column(

          mainAxisSize: MainAxisSize.min,

          crossAxisAlignment: CrossAxisAlignment.start,

          children: [

            const Text(

              'Pertandingan akan diakhiri seketika. Pilih tim pemenang walkover:',

              style: TextStyle(fontSize: 12, color: Color(0xFF475569)),

            ),

            const SizedBox(height: 14),

            SizedBox(

              width: double.infinity,

              child: ElevatedButton(

                onPressed: () {

                  Navigator.pop(ctx);

                  _walkoverMatch(match, true);

                },

                style: ElevatedButton.styleFrom(

                  backgroundColor: const Color(0xFF2563EB),

                  foregroundColor: Colors.white,

                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),

                  elevation: 0,

                  padding: const EdgeInsets.symmetric(vertical: 10),

                ),

                child: Text(

                  'Team A Menang (WO)\n(${match.teamANames})',

                  textAlign: TextAlign.center,

                  style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),

                ),

              ),

            ),

            const SizedBox(height: 8),

            SizedBox(

              width: double.infinity,

              child: ElevatedButton(

                onPressed: () {

                  Navigator.pop(ctx);

                  _walkoverMatch(match, false);

                },

                style: ElevatedButton.styleFrom(

                  backgroundColor: const Color(0xFFD97706),

                  foregroundColor: Colors.white,

                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),

                  elevation: 0,

                  padding: const EdgeInsets.symmetric(vertical: 10),

                ),

                child: Text(

                  'Team B Menang (WO)\n(${match.teamBNames})',

                  textAlign: TextAlign.center,

                  style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),

                ),

              ),

            ),

          ],

        ),

        actions: [

          TextButton(

            onPressed: () => Navigator.pop(ctx),

            child: const Text('Batal', style: TextStyle(color: Color(0xFF64748B))),

          ),

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

    final buttonLabel = isTeamA ? '+ Tambah Poin Team A' : '+ Tambah Poin Team B';

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

    final matchId = match.matchId;

    final hasConflict = matchId != null && _conflictSnapshots.containsKey(matchId);

    final pendingCount = matchId != null ? (_pendingEventsByMatch[matchId]?.length ?? 0) : 0;

    final saveStatus = matchId != null ? _matchSaveStatus[matchId] : null;

    final canInput = isHost && _isCurrentViewedRoundActive && !isCompleted;

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

                            color: isCompleted

                                ? const Color(0xFFF1F5F9)

                                : const Color(0xFFDCFCE7),

                            borderRadius: BorderRadius.circular(6),

                            border: Border.all(

                              color: isCompleted

                                  ? const Color(0xFFE2E8F0)

                                  : const Color(0xFFBBF7D0),

                            ),

                          ),

                          child: Text(

                            isCompleted

                                ? (match.winnerTeam != null

                                    ? 'Selesai (${match.winnerTeam} Win)'

                                    : 'Selesai')

                                : 'Sedang Berlangsung',

                            style: TextStyle(

                              fontSize: 10,

                              fontWeight: FontWeight.bold,

                              color: isCompleted

                                  ? const Color(0xFF64748B)

                                  : const Color(0xFF15803D),

                            ),

                          ),

                        ),

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

                      isCompleted: isCompleted,

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

                      isCompleted: isCompleted,

                    ),

                  ),

                ],

              ),

            ),

          ),



          // 6. Walkover Action Button (Host, Ronde Aktif, Match In Progress)

          if (isHost && _isCurrentViewedRoundActive && !isCompleted) ...[

            const Divider(height: 1, color: Color(0xFFE2E8F0)),

            Padding(

              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),

              child: Center(

                child: TextButton.icon(

                  onPressed: () => _showWalkoverConfirmation(match),

                  icon: const Icon(

                    Icons.flag_outlined,

                    size: 15,

                    color: Color(0xFFDC2626),

                  ),

                  label: const Text(

                    'Deklarasi Walkover (WO)',

                    style: TextStyle(

                      fontSize: 11,

                      fontWeight: FontWeight.bold,

                      color: Color(0xFFDC2626),

                    ),

                  ),

                ),

              ),

            ),

          ],

        ],

      ),

    );

  }

}

