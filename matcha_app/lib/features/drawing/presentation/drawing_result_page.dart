import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../games/domain/game_wizard_model.dart';
import '../../match/data/match_service.dart';
import '../../match/presentation/match_scoring_page.dart';
import '../../session/data/session_service.dart';
import '../domain/matcha_drawing_engine.dart';
import '../../session/presentation/session_detail_page.dart';
class DrawingResultPage extends StatefulWidget {
  final dynamic sessionId;
  final GameWizardConfig? config;
  final List<DrawingRound>? initialRounds;
  final int? initialRoundIndex;
  final AuthController? authController;
  final bool? isHost;
  final dynamic hostUserId;
  final MatchService? matchService;
  const DrawingResultPage({
    super.key,
    this.sessionId,
    this.config,
    this.initialRounds,
    this.initialRoundIndex,
    this.authController,
    this.isHost,
    this.hostUserId,
    this.matchService,
  });
  @override
  State<DrawingResultPage> createState() => _DrawingResultPageState();
}
class _DrawingResultPageState extends State<DrawingResultPage> {
  late MatchService _matchService;
  late GameWizardConfig _config;
  List<DrawingRound> _rounds = [];
  int _selectedRoundIndex = 0;
  bool _isLoading = true;
  String? _errorMessage;
  bool _isLocked = false;
  bool _isSavingDrawing = false;
  bool _isStartingScoring = false;
  dynamic _sessionHostUserId;
  Map<String, dynamic>? _sessionData;
  RealtimeChannel? _realtimeChannel;
  dynamic get _effectiveSessionId => widget.sessionId ?? _config.sessionId;
  bool get _isHostUser {
    if (widget.isHost != null) return widget.isHost!;
    final currentUserId = widget.authController?.currentUser?.userId;
    if (widget.authController?.currentUser?.isAdmin == true) return true;
    final targetHostId = widget.hostUserId ?? _sessionHostUserId ?? _sessionData?['host_user_id'];
    if (targetHostId != null && currentUserId != null) {
      return targetHostId.toString() == currentUserId.toString();
    }
    if (widget.authController?.currentUser?.isHost == true) return true;
    // Jika tidak ada data auth atau session host, default ke true jika dipanggil dari wizard langsung tanpa session ID
    if (_effectiveSessionId == null && widget.initialRounds != null) return true;
    return false;
  }
  bool _isReturningToDetail = false;
  void _goToSessionDetail() {
    if (_isReturningToDetail ||
        _isSavingDrawing ||
        _isStartingScoring) {
      return;
    }
    final sessionId = int.tryParse(_effectiveSessionId.toString());
    if (sessionId == null) {
      Navigator.maybePop(context);
      return;
    }
    _isReturningToDetail = true;
    SessionService.notifySessionsChanged();
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => SessionDetailPage(
          sessionId: sessionId,
          authController: widget.authController,
        ),
      ),
      (route) => route.isFirst,
    );
  }
  int? _tryResolveMatchFormatId([String? type]) {
    final raw = (type ?? _config.gameType).trim();
    if (raw.isEmpty) return null;
    return MatchService.formatNameToId(raw);
  }

  int _resolveMatchFormatId() {
    final id = _tryResolveMatchFormatId();
    if (id == null) {
      throw Exception(
        'Format pertandingan tidak dikenali: ${_config.gameType}',
      );
    }
    return id;
  }
  @override
  void initState() {
    super.initState();
    _matchService = widget.matchService ?? MatchService();
    _sessionHostUserId = widget.hostUserId;
    if (widget.initialRoundIndex != null) {
      _selectedRoundIndex = widget.initialRoundIndex!;
    }
    _config = widget.config ??
        GameWizardConfig(
          sessionId: widget.sessionId,
          activityName: 'Match Padel Tournament',
          venueName: 'Barong Padel Arena & Club',
          players: [
            const GamePlayerItem(id: '1', name: 'Aku', level: 'Beginner', isGuest: true),
            const GamePlayerItem(id: '2', name: 'Kamu', level: 'Beginner', isGuest: true),
            const GamePlayerItem(id: '3', name: 'Dia', level: 'Beginner', isGuest: true),
            const GamePlayerItem(id: '4', name: 'Kita', level: 'Beginner', isGuest: true),
          ],
        );
    if (widget.initialRounds != null && widget.initialRounds!.isNotEmpty) {
      _rounds = List.from(widget.initialRounds!);
    }
    _initDataAndSubscription();
  }
  @override
  void didUpdateWidget(covariant DrawingResultPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.config != null) {
      if (widget.config!.gameType.trim().isNotEmpty) {
        _config.gameType = widget.config!.gameType.trim();
      }
      _config = widget.config!;
    }
  }

  @override
  void dispose() {
    _matchService.unsubscribe(_realtimeChannel);
    super.dispose();
  }
  Future<void> _initDataAndSubscription() async {
    final sessId = _effectiveSessionId;
    if (sessId != null) {
      _subscribeToRealtime(sessId);
      await _loadSessionAndDrawing(initial: true);
    } else {
      if (_rounds.isEmpty) {
        _generateInitialDrawing();
      }
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }
  void _subscribeToRealtime(dynamic sessionId) {
    _matchService.unsubscribe(_realtimeChannel);
    _realtimeChannel = _matchService.subscribeDrawingSession(
      sessionId: sessionId,
      onDrawingChanged: () {
        if (!mounted) return;
        _loadSessionAndDrawing(silent: true);
      },
      onDrawingLocked: () {
        if (!mounted) return;
        setState(() => _isLocked = true);
        _loadSessionAndDrawing(silent: true);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Host telah mengunci drawing dan memulai pertandingan! 🎾'),
            behavior: SnackBarBehavior.floating,
            duration: Duration(seconds: 3),
          ),
        );
      },
    );
  }
  void _generateInitialDrawing() {
    _rounds = MatchaDrawingEngine.generateDrawing(
      players: _config.players,
      courtCount: _config.courtCount,
      gameType: _config.gameType,
      playMode: _config.playMode,
      roundCount: _config.totalRounds,
    );
  }
  Future<void> _loadSessionAndDrawing({bool initial = false, bool silent = false}) async {
    final sessId = _effectiveSessionId;
    if (sessId == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }
    if (!silent && mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }
    try {
      final session = await _matchService.getSession(sessId);
      final locked = await _matchService.isSessionDrawingLocked(sessId);
      final sessionPlayers = await _matchService.getSessionPlayers(sessId);
      final savedRounds = await _matchService.loadSavedDrawing(
        sessionId: sessId,
        registeredPlayers: sessionPlayers.isNotEmpty ? sessionPlayers : _config.players,
      );
      final formatInfo = await _matchService.getActiveDrawingFormat(sessId);
      final activeFormatName = formatInfo?['nama_format'] as String?;
      final activeFormatId = formatInfo?['match_format_id'] as int?;

      if (!mounted) return;
      setState(() {
        _sessionData = session;
        if (sessionPlayers.isNotEmpty) {
          _config.players = sessionPlayers;
        }
        if (session != null && session['host_user_id'] != null) {
          _sessionHostUserId = session['host_user_id'];
        }
        _isLocked = locked;

        // Pemulihan format pertandingan:
        // - Drawing tersimpan memulihkan format dari drawing aktif yang sama dengan sumber match.
        // - Jangan menimpa format yang benar dengan nilai config kosong.
        // - Jangan memberikan fallback otomatis Americano atau Team Americano.
        if (savedRounds != null && savedRounds.isNotEmpty) {
          if (activeFormatName != null && activeFormatName.trim().isNotEmpty) {
            _config.gameType = activeFormatName.trim();
          } else if (activeFormatId != null) {
            final mapped = MatchService.formatIdToName(activeFormatId);
            if (mapped != null) {
              _config.gameType = mapped;
            }
          }
        } else {
          // Drawing baru atau belum tersimpan:
          // Gunakan format yang sudah dipilih di wizard jika ada; jika kosong, pulihkan dari drawing DB jika ada.
          if (_config.gameType.trim().isEmpty) {
            if (activeFormatName != null && activeFormatName.trim().isNotEmpty) {
              _config.gameType = activeFormatName.trim();
            } else if (activeFormatId != null) {
              final mapped = MatchService.formatIdToName(activeFormatId);
              if (mapped != null) {
                _config.gameType = mapped;
              }
            }
          }
        }

        if (savedRounds != null && savedRounds.isNotEmpty) {
          // Pertahankan status terminal/skor dari _rounds lokal jika savedRounds di DB masih Scheduled/In Progress
          if (_rounds.isNotEmpty && _rounds.length == savedRounds.length) {
            for (int r = 0; r < savedRounds.length; r++) {
              for (int m = 0; m < savedRounds[r].matches.length; m++) {
                if (m < _rounds[r].matches.length) {
                  final oldM = _rounds[r].matches[m];
                  final newM = savedRounds[r].matches[m];
                  if (oldM.isTerminal && !newM.isTerminal) {
                    newM.status = oldM.status;
                    newM.scoreA = oldM.scoreA;
                    newM.scoreB = oldM.scoreB;
                    newM.winnerTeam = oldM.winnerTeam;
                  }
                }
              }
            }
          }
          _rounds = savedRounds;
          _config.courtCount = savedRounds
              .expand((round) => round.matches)
              .fold<int>(
                1,
                (highest, match) =>
                    match.courtNumber > highest ? match.courtNumber : highest,
              );
        } else if (_rounds.isNotEmpty) {
          // Keep existing rounds
        } else if (widget.initialRounds != null && widget.initialRounds!.isNotEmpty) {
          _rounds = List.from(widget.initialRounds!);
        } else if (_isHostUser && _rounds.isEmpty) {
          if (session != null && session['tb_session_court'] is List) {
            final courtList = session['tb_session_court'] as List;
            if (courtList.isNotEmpty && _config.courtCount <= 1) {
              _config.courtCount = courtList.length;
            }
          }
          _generateInitialDrawing();
        }
        if (_rounds.isNotEmpty) {
          if (widget.initialRoundIndex != null) {
            _selectedRoundIndex = widget.initialRoundIndex!.clamp(0, _rounds.length - 1);
          } else if (_selectedRoundIndex == 0 && _activeUnlockedRoundIndex > 0) {
            _selectedRoundIndex = _activeUnlockedRoundIndex;
          }
        }
        _isLoading = false;
        _errorMessage = null;
      });
      // Jika Host dan DB belum memiliki drawing tersimpan, simpan preview awal ke database agar player bisa melihatnya secara realtime
      if (_isHostUser && (savedRounds == null || savedRounds.isEmpty) && _rounds.isNotEmpty) {
        final formatId = _tryResolveMatchFormatId();
        if (formatId != null) {
          try {
            final saved = await _matchService.saveDrawingMatches(
              sessionId: sessId,
              rounds: _rounds,
              allPlayers: _config.players,
              courtCount: _config.courtCount,
              matchStatus: 'Scheduled',
              matchFormatId: _resolveMatchFormatId(),
            );
            if (mounted && saved.isNotEmpty) {
              setState(() => _rounds = saved);
            }
            await _matchService.broadcastDrawingUpdate(sessId);
          } catch (_) {}
        }
      }
    } catch (e) {
      if (!mounted) return;
      if (!silent) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Gagal memuat jadwal drawing: $e';
        });
      }
    }
  }
  Future<void> _shuffleDrawing({bool showToast = true}) async {
    if (!_isHostUser) return;
    if (_isLoading) return;
    if (_isLocked) {
      if (showToast) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Drawing sudah dikunci dan tidak dapat diacak ulang.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
      return;
    }

    final formatId = _tryResolveMatchFormatId();
    if (formatId == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              _config.gameType.trim().isEmpty
                  ? 'Format pertandingan belum ditentukan. Tidak dapat mengacak ulang jadwal.'
                  : 'Format pertandingan tidak dikenali: "${_config.gameType}". Tidak dapat mengacak ulang jadwal.',
            ),
            backgroundColor: Colors.red.shade700,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
      return;
    }

    final oldRounds = _rounds;
    final newRounds = MatchaDrawingEngine.generateDrawing(
      players: _config.players,
      courtCount: _config.courtCount,
      gameType: _config.gameType,
      playMode: _config.playMode,
      roundCount: _config.totalRounds,
      shufflePlayers: true,
    );
    setState(() {
      _rounds = newRounds;
      _selectedRoundIndex = 0;
      _isSavingDrawing = true;
    });
    final sessId = _effectiveSessionId;
    if (sessId != null) {
      try {
        final saved = await _matchService.saveDrawingMatches(
          sessionId: sessId,
          rounds: newRounds,
          allPlayers: _config.players,
          courtCount: _config.courtCount,
          matchStatus: 'Scheduled',
          matchFormatId: _resolveMatchFormatId(),
        );
        if (mounted && saved.isNotEmpty) {
          setState(() => _rounds = saved);
        }
        await _matchService.broadcastDrawingUpdate(sessId);
        if (mounted && showToast) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Jadwal dan rotasi pemain berhasil diacak ulang! 🔀'),
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      } catch (e) {
        if (mounted) {
          setState(() => _rounds = oldRounds);
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Gagal menyimpan hasil acak ke server: $e'),
              backgroundColor: Colors.red.shade700,
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      } finally {
        if (mounted) setState(() => _isSavingDrawing = false);
      }
    } else {
      if (mounted) {
        setState(() => _isSavingDrawing = false);
        if (showToast) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Jadwal dan rotasi pemain berhasil diacak ulang! 🔀'),
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      }
    }
  }
  Future<void> _startLiveScoring() async {
    if (!_isHostUser) {
      _openLiveScoringReadOnly();
      return;
    }
    final sessId = _effectiveSessionId;
    if (sessId != null) {
      if (_isLocked) {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => MatchScoringPage(
              sessionId: sessId,
              config: _config,
              rounds: _rounds,
              initialRoundIndex: _selectedRoundIndex,
              authController: widget.authController,
              matchService: _matchService,
              isHost: true,
              hostUserId: _sessionHostUserId is int
                  ? _sessionHostUserId
                  : int.tryParse(_sessionHostUserId?.toString() ?? ''),
            ),
          ),
        );
        return;
      }
      final formatId = _tryResolveMatchFormatId();
      if (formatId == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              _config.gameType.trim().isEmpty
                  ? 'Format pertandingan belum ditentukan. Pertandingan tidak dapat dimulai.'
                  : 'Format pertandingan tidak dikenali: "${_config.gameType}". Pertandingan tidak dapat dimulai.',
            ),
            backgroundColor: Colors.red.shade700,
            behavior: SnackBarBehavior.floating,
          ),
        );
        return;
      }
      setState(() => _isStartingScoring = true);
      try {
        // Simpan final drawing dan update status session & matches ke In Progress
        final finalRounds = await _matchService.lockDrawingSession(
          sessId,
          rounds: _rounds,
          allPlayers: _config.players,
          matchFormatId: _resolveMatchFormatId(),
          courtCount: _config.courtCount,
        );
        if (!mounted) return;
        setState(() {
          _rounds = finalRounds;
          _isLocked = true;
          _isStartingScoring = false;
        });
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => MatchScoringPage(
              sessionId: sessId,
              config: _config,
              rounds: _rounds,
              initialRoundIndex: _selectedRoundIndex,
              authController: widget.authController,
              matchService: _matchService,
              isHost: true,
              hostUserId: _sessionHostUserId is int
                  ? _sessionHostUserId
                  : int.tryParse(_sessionHostUserId?.toString() ?? ''),
            ),
          ),
        );
      } catch (e) {
        if (!mounted) return;
        setState(() => _isStartingScoring = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Gagal mengunci drawing: $e. Pertandingan belum dimulai.'),
            backgroundColor: Colors.red.shade700,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } else {
      // Local flow without DB session
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => MatchScoringPage(
            config: _config,
            rounds: _rounds,
            initialRoundIndex: _selectedRoundIndex,
            authController: widget.authController,
            matchService: _matchService,
            isHost: true,
          ),
        ),
      );
    }
  }
  void _openLiveScoringReadOnly() {
    final sessId = _effectiveSessionId;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => MatchScoringPage(
          sessionId: sessId,
          config: _config,
          rounds: _rounds,
          initialRoundIndex: _selectedRoundIndex,
          authController: widget.authController,
          matchService: _matchService,
          isHost: false,
          hostUserId: _sessionHostUserId is int
              ? _sessionHostUserId
              : int.tryParse(_sessionHostUserId?.toString() ?? ''),
        ),
      ),
    );
  }
  Widget _buildDrawingContent(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          title: const Text('Drawing & Jadwal Pertandingan'),
          backgroundColor: Colors.white,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
            onPressed: _goToSessionDetail,
          ),
        ),
        body: const Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              CircularProgressIndicator(color: AppColors.matchaDark),
              SizedBox(height: 14),
              Text(
                'Memuat drawing pertandingan...',
                style: TextStyle(color: Color(0xFF64748B), fontWeight: FontWeight.bold, fontSize: 13),
              ),
            ],
          ),
        ),
      );
    }
    if (_errorMessage != null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          title: const Text('Drawing & Jadwal Pertandingan'),
          backgroundColor: Colors.white,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
            onPressed: _goToSessionDetail,
          ),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.error_outline_rounded, size: 48, color: Colors.red),
                const SizedBox(height: 12),
                Text(
                  _errorMessage!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 16),
                ElevatedButton.icon(
                  onPressed: () => _loadSessionAndDrawing(),
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Coba Lagi'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.matchaDark,
                    foregroundColor: Colors.white,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }
    if (_rounds.isEmpty) {
      final eligibleActive = _config.players.where((p) => !p.isWithdrawn && !p.isResting).toList();
      final isSingleMode = _config.playMode.toLowerCase() == 'single';
      final minReq = isSingleMode ? 2 : 4;
      final isInsufficient = eligibleActive.length < minReq;

      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          title: const Text('Drawing & Jadwal Pertandingan'),
          backgroundColor: Colors.white,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
            onPressed: _goToSessionDetail,
          ),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  isInsufficient
                      ? Icons.people_outline_rounded
                      : (_isLocked ? Icons.sports_tennis_rounded : Icons.hourglass_empty_rounded),
                  size: 52,
                  color: isInsufficient
                      ? const Color(0xFFD97706)
                      : (_isLocked ? AppColors.matchaDark : const Color(0xFF94A3B8)),
                ),
                const SizedBox(height: 14),
                Text(
                  isInsufficient
                      ? 'Jumlah Pemain Aktif Tidak Mencukupi'
                      : (_isLocked ? 'Pertandingan Sedang Berlangsung' : 'Drawing Belum Tersedia'),
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 8),
                Text(
                  isInsufficient
                      ? 'Tersisa ${eligibleActive.length} pemain aktif (minimal $minReq pemain untuk mode ${_config.playMode}). Pertandingan tidak dapat dimulai tanpa penambahan pemain atau pengaktifan pemain yang sedang istirahat.'
                      : (_isLocked
                          ? 'Jadwal dan sesi pertandingan telah dimulai oleh Host.'
                          : (_isHostUser
                              ? 'Silakan acak susunan tim untuk memulai pertandingan.'
                              : 'Host sedang mempersiapkan drawing pertandingan untuk sesi ini.')),
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 20),
                if (isInsufficient)
                  ElevatedButton.icon(
                    onPressed: _goToSessionDetail,
                    icon: const Icon(Icons.arrow_back_rounded),
                    label: const Text('Kembali ke Detail Sesi'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  )
                else if (_isLocked)
                  ElevatedButton.icon(
                    onPressed: _openLiveScoringReadOnly,
                    icon: const Icon(Icons.scoreboard_outlined),
                    label: const Text('Lihat Live Scoring'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  )
                else if (_isHostUser)
                  ElevatedButton.icon(
                    onPressed: (_isSavingDrawing || _isLocked || _isLoading) ? null : _shuffleDrawing,
                    icon: _isLoading
                        ? const SizedBox(
                            width: 14,
                            height: 14,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.shuffle_rounded),
                    label: Text(_isLoading ? 'Memuat Format...' : 'Buat Drawing Sekarang'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  )
                else
                  OutlinedButton.icon(
                    onPressed: () => _loadSessionAndDrawing(),
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Periksa Pembaruan Drawing'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.matchaDark,
                      side: const BorderSide(color: Color(0xFFCBD5E1)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
              ],
            ),
          ),
        ),
      );
    }
    final currentRound = _rounds[_selectedRoundIndex.clamp(0, _rounds.length - 1)];
    final hasPlayableMatches = currentRound.matches.isNotEmpty &&
        currentRound.matches.any((m) => m.teamA.isNotEmpty && m.teamB.isNotEmpty);
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: _goToSessionDetail,
        ),
        title: Column(
          children: [
            Text(
              'Drawing & Jadwal Pertandingan',
              style: AppTextStyles.h2.copyWith(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              '${_config.gameType} • ${_config.playMode} (${_config.playMode == "Double" ? "2 vs 2" : "1 vs 1"})',
              style: AppTextStyles.caption.copyWith(
                fontSize: 11,
                color: const Color(0xFF64748B),
              ),
            ),
          ],
        ),
        centerTitle: true,
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                // Header Card with Shuffle Button or Spectator Badge
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: AppColors.matchaDark,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.sports_tennis_rounded, color: Colors.white, size: 20),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _config.activityName.isEmpty ? '${_config.sport} Tournament' : _config.activityName,
                              style: AppTextStyles.h2.copyWith(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: const Color(0xFF0F172A),
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              _config.venueName ?? 'Barong Padel Arena & Club',
                              style: AppTextStyles.caption.copyWith(
                                fontSize: 11,
                                color: const Color(0xFF64748B),
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (_isHostUser)
                        OutlinedButton.icon(
                          onPressed: (_isSavingDrawing || _isLocked || _isLoading) ? null : _shuffleDrawing,
                          icon: _isSavingDrawing
                              ? const SizedBox(
                                  width: 12,
                                  height: 12,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : (_isLoading
                                  ? const SizedBox(
                                      width: 12,
                                      height: 12,
                                      child: CircularProgressIndicator(strokeWidth: 2),
                                    )
                                  : const Icon(Icons.shuffle_rounded, size: 14)),
                          label: Text(
                            _isLocked
                                ? 'Terkunci'
                                : (_isLoading ? 'Memuat...' : 'Acak Ulang'),
                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                          ),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: (_isLocked || _isLoading) ? const Color(0xFF94A3B8) : AppColors.matchaDark,
                            side: BorderSide(color: (_isLocked || _isLoading) ? const Color(0xFFE2E8F0) : const Color(0xFFCBD5E1)),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          ),
                        )
                      else
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: const Color(0xFFCBD5E1)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                _isLocked ? Icons.lock_outline_rounded : Icons.visibility_rounded,
                                size: 13,
                                color: const Color(0xFF475569),
                              ),
                              const SizedBox(width: 4),
                              const Text(
                                'Penonton',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFF475569),
                                ),
                              ),
                              const SizedBox(width: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                                decoration: BoxDecoration(
                                  color: _isLocked ? const Color(0xFFDCFCE7) : const Color(0xFFFEF3C7),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  _isLocked ? 'Live' : 'Preview',
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                    color: _isLocked ? const Color(0xFF166534) : const Color(0xFF92400E),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                // Round Selector Pills (Surprise Mechanism & Round Status)
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: List.generate(_rounds.length, (idx) {
                      final isSelected = _selectedRoundIndex == idx;
                      final r = _rounds[idx];
                      final isAccessible = _isRoundAccessible(idx);
                      final isCompleted = _isRoundCompleted(idx);
                      final isActive = idx == _activeUnlockedRoundIndex;

                      return Container(
                        margin: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                'Ronde ${r.roundNumber}',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                  color: isSelected
                                      ? Colors.white
                                      : (isAccessible ? const Color(0xFF475569) : const Color(0xFF94A3B8)),
                                ),
                              ),
                              const SizedBox(width: 5),
                              if (!isAccessible) ...[
                                Icon(
                                  Icons.lock_rounded,
                                  size: 13,
                                  color: isSelected ? Colors.white70 : const Color(0xFF94A3B8),
                                ),
                                const SizedBox(width: 3),
                                Text(
                                  'Terkunci',
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w600,
                                    color: isSelected ? Colors.white70 : const Color(0xFF94A3B8),
                                  ),
                                ),
                              ] else if (isCompleted) ...[
                                Icon(
                                  Icons.check_circle_rounded,
                                  size: 13,
                                  color: isSelected ? const Color(0xFFA8E63A) : const Color(0xFF16A34A),
                                ),
                                const SizedBox(width: 3),
                                Text(
                                  'Selesai',
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w600,
                                    color: isSelected ? Colors.white70 : const Color(0xFF16A34A),
                                  ),
                                ),
                              ] else if (isActive && _isLocked) ...[
                                Container(
                                  width: 6,
                                  height: 6,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFF22C55E),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                const SizedBox(width: 3),
                                Text(
                                  'Live',
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                    color: isSelected ? Colors.white70 : const Color(0xFF16A34A),
                                  ),
                                ),
                              ] else ...[
                                Text(
                                  '(${r.matches.length} Match)',
                                  style: TextStyle(
                                    fontSize: 10,
                                    color: isSelected ? Colors.white70 : const Color(0xFF64748B),
                                  ),
                                ),
                              ],
                            ],
                          ),
                          selected: isSelected,
                          selectedColor: AppColors.matchaDark,
                          backgroundColor: isAccessible ? Colors.white : const Color(0xFFF8FAFC),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                            side: BorderSide(
                              color: isSelected
                                  ? AppColors.matchaDark
                                  : (isAccessible ? const Color(0xFFE2E8F0) : const Color(0xFFCBD5E1)),
                            ),
                          ),
                          onSelected: (val) {
                            if (val) setState(() => _selectedRoundIndex = idx);
                          },
                        ),
                      );
                    }),
                  ),
                ),
                const SizedBox(height: 16),
                if (!_isRoundAccessible(_selectedRoundIndex))
                  _buildLockedRoundState(currentRound)
                else ...[
                  // Live Preview Lapangan Header
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: Color(0xFF22C55E),
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            'LIVE PREVIEW',
                            style: AppTextStyles.caption.copyWith(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.8,
                              color: const Color(0xFF0F172A),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(width: 8),
                      Flexible(
                        child: Text(
                          'RONDE ${currentRound.roundNumber} • ${currentRound.matches.length} Match',
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.caption.copyWith(
                            fontSize: 11,
                            color: const Color(0xFF64748B),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  // Visual Court Cards
                  ...currentRound.matches.map((match) => _buildVisualCourtCard(match)),
                  const SizedBox(height: 16),
                  _buildRosterSection(currentRound),
                ],
              ],
            ),
          ),
          // Bottom Action Button
          Container(
            padding: const EdgeInsets.all(20),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (!hasPlayableMatches)
                  Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFFBEB),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFFDE68A)),
                    ),
                    child: const Row(
                      children: [
                        Icon(Icons.info_outline_rounded, color: Color(0xFFD97706), size: 18),
                        SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Semua pemain berada di bangku cadangan / istirahat. Tekan tombol [Siap Main] pada pemain di Bangku Cadangan untuk memulai pertandingan.',
                            style: TextStyle(fontSize: 11, color: Color(0xFF92400E), height: 1.3, fontWeight: FontWeight.w500),
                          ),
                        ),
                      ],
                    ),
                  ),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: _isHostUser
                      ? ElevatedButton(
                          onPressed: (_isStartingScoring || !hasPlayableMatches) ? null : _startLiveScoring,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: hasPlayableMatches ? AppColors.matchaDark : const Color(0xFFCBD5E1),
                            disabledBackgroundColor: const Color(0xFFE2E8F0),
                            foregroundColor: Colors.white,
                            disabledForegroundColor: const Color(0xFF94A3B8),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                            elevation: 0,
                          ),
                          child: _isStartingScoring
                              ? const Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                                    ),
                                    SizedBox(width: 10),
                                    Text('Mengunci Drawing & Memulai...', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                  ],
                                )
                              : Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Text(
                                      !hasPlayableMatches
                                          ? 'Belum Ada Pemain Bertanding'
                                          : (_isLocked
                                              ? (_selectedRoundIndex > 0
                                                  ? 'Mulai Scoring Ronde ${_selectedRoundIndex + 1}'
                                                  : 'Buka Live Scoring')
                                              : 'Kunci Tim & Mulai Scoring Live'),
                                      style: TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 14,
                                        color: hasPlayableMatches ? Colors.white : const Color(0xFF94A3B8),
                                      ),
                                    ),
                                    if (hasPlayableMatches) ...[
                                      const SizedBox(width: 8),
                                      const Icon(Icons.arrow_forward_rounded, size: 18),
                                    ],
                                  ],
                                ),
                        )
                      : (_isLocked
                          ? (hasPlayableMatches
                              ? ElevatedButton(
                                  onPressed: _openLiveScoringReadOnly,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.matchaDark,
                                    foregroundColor: Colors.white,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                                    elevation: 0,
                                  ),
                                  child: const Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Icon(Icons.sports_tennis_rounded, size: 18),
                                      SizedBox(width: 8),
                                      Text(
                                        'Lihat Live Scoring',
                                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                      ),
                                      SizedBox(width: 6),
                                      Icon(Icons.arrow_forward_rounded, size: 18),
                                    ],
                                  ),
                                )
                              : Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFF1F5F9),
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(color: const Color(0xFFCBD5E1)),
                                  ),
                                  child: const Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Icon(Icons.hourglass_empty_rounded, size: 16, color: Color(0xFF64748B)),
                                      SizedBox(width: 8),
                                      Flexible(
                                        child: Text(
                                          'Pertandingan Ditunda (Menunggu Pemain Siap Main)',
                                          textAlign: TextAlign.center,
                                          style: TextStyle(
                                            fontWeight: FontWeight.w600,
                                            fontSize: 12.5,
                                            color: Color(0xFF475569),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ))
                          : Container(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: const Color(0xFFCBD5E1)),
                              ),
                              child: const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.hourglass_empty_rounded, size: 16, color: Color(0xFF64748B)),
                                  SizedBox(width: 8),
                                  Flexible(
                                    child: Text(
                                      'Menunggu Host Mengunci Drawing & Memulai Scoring',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(
                                        fontWeight: FontWeight.w600,
                                        fontSize: 12.5,
                                        color: Color(0xFF475569),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            )),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
  int get _effectiveSessionActiveRoundIndex {
    int active = 0;
    if (widget.initialRoundIndex != null) {
      active = widget.initialRoundIndex!;
    }
    if (_sessionData != null) {
      final raw = _sessionData!['active_round'] ?? _sessionData!['session_active_round'];
      if (raw is int && raw > 0) {
        if (raw - 1 > active) active = raw - 1;
      } else if (raw != null) {
        final d = RegExp(r'\d+').firstMatch(raw.toString())?.group(0);
        if (d != null) {
          final n = int.tryParse(d);
          if (n != null && n > 0 && n - 1 > active) {
            active = n - 1;
          }
        }
      }
    }
    // Scan ronde tertinggi dari match yang sudah In Progress / Playing / Completed / ada skor
    for (int i = 0; i < _rounds.length; i++) {
      final matches = _rounds[i].matches;
      final hasStarted = matches.any((m) =>
          m.status.toLowerCase() == 'in progress' ||
          m.status.toLowerCase() == 'playing' ||
          m.isTerminal ||
          m.scoreA > 0 ||
          m.scoreB > 0 ||
          (m.pointDisplayA != '0' && m.pointDisplayA.isNotEmpty) ||
          (m.pointDisplayB != '0' && m.pointDisplayB.isNotEmpty));
      if (hasStarted && i > active) {
        active = i;
      }
    }
    return _rounds.isEmpty ? active : active.clamp(0, _rounds.length - 1);
  }

  bool _isRoundCompleted(int index) {
    if (index < 0 || index >= _rounds.length) return false;
    final r = _rounds[index];
    if (r.matches.isEmpty) return false;

    // 1. Jika sesi sudah berada di ronde setelah ronde index, maka ronde index pasti sudah selesai
    if (index < _effectiveSessionActiveRoundIndex) return true;

    // 2. Jika ronde berikutnya sudah ada match yang dimulai (in progress / playing / ada skor)
    if (index + 1 < _rounds.length) {
      final nextMatches = _rounds[index + 1].matches;
      final nextStarted = nextMatches.any((m) =>
          m.status.toLowerCase() == 'in progress' ||
          m.status.toLowerCase() == 'playing' ||
          m.isTerminal ||
          m.scoreA > 0 ||
          m.scoreB > 0 ||
          (m.pointDisplayA != '0' && m.pointDisplayA.isNotEmpty) ||
          (m.pointDisplayB != '0' && m.pointDisplayB.isNotEmpty));
      if (nextStarted) return true;
    }

    // 3. Cek apakah seluruh match pada ronde ini sudah terminal
    return r.matches.every((m) => m.isTerminal);
  }

  bool _isRoundAccessible(int index) {
    if (index == 0) return true; // Ronde 1 selalu terbuka
    if (index < 0 || index >= _rounds.length) return false;

    // Ronde aktif sesi atau ronde sebelumnya selalu accessible
    if (index <= _effectiveSessionActiveRoundIndex) return true;
    if (widget.initialRoundIndex != null && index <= widget.initialRoundIndex!) return true;

    // Jika ronde ini sudah ada match yang berjalan atau selesai
    final r = _rounds[index];
    if (r.matches.any((m) =>
        m.status.toLowerCase() == 'in progress' ||
        m.status.toLowerCase() == 'playing' ||
        m.isTerminal ||
        m.scoreA > 0 ||
        m.scoreB > 0)) {
      return true;
    }

    // Ronde N terbuka secara bertahap setelah Ronde N-1 selesai dimainkan
    return _isRoundCompleted(index - 1);
  }

  int get _activeUnlockedRoundIndex {
    final serverActive = _effectiveSessionActiveRoundIndex;
    if (serverActive >= 0 && serverActive < _rounds.length) {
      if (!_isRoundCompleted(serverActive)) {
        return serverActive;
      }
    }
    for (int i = 0; i < _rounds.length; i++) {
      if (!_isRoundAccessible(i)) {
        return (i - 1).clamp(0, _rounds.length - 1);
      }
      if (!_isRoundCompleted(i)) {
        return i;
      }
    }
    return _rounds.isEmpty ? 0 : _rounds.length - 1;
  }

  Widget _buildLockedRoundState(DrawingRound round) {
    final activeRoundNum = _activeUnlockedRoundIndex + 1;
    final isTeamFormat = _config.gameType.toLowerCase().contains('team');
    final unitLabel = isTeamFormat ? 'Set' : 'Ronde';

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 8, bottom: 24),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 32),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              color: const Color(0xFFFFFBEB),
              shape: BoxShape.circle,
              border: Border.all(color: const Color(0xFFFDE68A), width: 1.5),
            ),
            child: const Icon(
              Icons.lock_rounded,
              color: Color(0xFFD97706),
              size: 28,
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: const Color(0xFFFEF3C7),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFFDE68A)),
            ),
            child: Text(
              '$unitLabel Terkunci',
              style: const TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w800,
                color: Color(0xFF92400E),
                letterSpacing: 0.5,
              ),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            '$unitLabel ${round.roundNumber} Belum Dimulai',
            style: AppTextStyles.h2.copyWith(
              fontSize: 17,
              fontWeight: FontWeight.w800,
              color: const Color(0xFF0F172A),
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Text(
              'Bagan drawing dan susunan pemain untuk $unitLabel ${round.roundNumber} akan terbuka secara otomatis setelah $unitLabel ${round.roundNumber - 1} selesai dimainkan.',
              textAlign: TextAlign.center,
              style: AppTextStyles.body.copyWith(
                fontSize: 12,
                color: const Color(0xFF64748B),
                height: 1.45,
              ),
            ),
          ),
          const SizedBox(height: 20),
          ElevatedButton.icon(
            onPressed: () {
              setState(() {
                _selectedRoundIndex = _activeUnlockedRoundIndex;
              });
            },
            icon: const Icon(Icons.arrow_back_rounded, size: 16),
            label: Text(
              'Buka $unitLabel $activeRoundNum (Aktif)',
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.matchaDark,
              foregroundColor: Colors.white,
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _effectiveSessionId == null &&
          !_isSavingDrawing &&
          !_isStartingScoring,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) {
          SessionService.notifySessionsChanged();
        } else {
          _goToSessionDetail();
        }
      },
      child: _buildDrawingContent(context),
    );
  }
  Widget _buildVisualCourtCard(DrawingMatch match) {
    final teamA = _visibleCourtPlayers(match.teamA);
    final teamB = _visibleCourtPlayers(match.teamB);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        children: [
          // Court Header
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Color(0xFF22C55E),
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'COURT ${match.courtNumber}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: Color(0xFF0F172A)),
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    match.status,
                    style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                  ),
                ),
              ],
            ),
          ),
          // Green Court Canvas Graphic
          Container(
            margin: const EdgeInsets.fromLTRB(14, 0, 14, 14),
            height: 180,
            decoration: BoxDecoration(
              color: const Color(0xFF1E4D36), // Deep green court
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.white.withValues(alpha: 0.2), width: 2),
            ),
            child: Stack(
              children: [
                // Court lines
                Center(
                  child: Container(
                    height: 2,
                    color: Colors.white.withValues(alpha: 0.6), // Net
                  ),
                ),
                Positioned.fill(
                  child: Align(
                    alignment: Alignment.center,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.3),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: const Text('NET', style: TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold)),
                    ),
                  ),
                ),
                // Team A (Top Half)
                Positioned(
                  top: 8,
                  left: 10,
                  right: 10,
                  child: Column(
                    children: [
                      const Text(
                        'TEAM A',
                        style: TextStyle(color: Color(0xFFA7F3D0), fontSize: 9, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 6),
                      if (teamA.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 8),
                          child: Text(
                            'Belum ada pemain',
                            style: TextStyle(color: Colors.white70, fontSize: 10.5, fontStyle: FontStyle.italic),
                          ),
                        )
                      else
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                          children: teamA.map((p) => _buildPlayerBadge(p)).toList(),
                        ),
                    ],
                  ),
                ),
                // Team B (Bottom Half)
                Positioned(
                  bottom: 8,
                  left: 10,
                  right: 10,
                  child: Column(
                    children: [
                      if (teamB.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 8),
                          child: Text(
                            'Belum ada pemain',
                            style: TextStyle(color: Colors.white70, fontSize: 10.5, fontStyle: FontStyle.italic),
                          ),
                        )
                      else
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                          children: teamB.map((p) => _buildPlayerBadge(p)).toList(),
                        ),
                      const SizedBox(height: 6),
                      const Text(
                        'TEAM B',
                        style: TextStyle(color: Color(0xFFA7F3D0), fontSize: 9, fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
  Widget _buildPlayerBadge(GamePlayerItem player) {
    final initials = player.name.trim().isNotEmpty
        ? player.name.trim().split(' ').map((s) => s.isNotEmpty ? s[0] : '').take(2).join().toUpperCase()
        : 'P';
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: const Color(0xFF38BDF8),
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 2),
          ),
          child: Center(
            child: Text(
              initials,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 11),
            ),
          ),
        ),
        const SizedBox(height: 2),
        SizedBox(
          width: 60,
          child: Text(
            player.name,
            textAlign: TextAlign.center,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
          ),
        ),
      ],
    );
  }

  GamePlayerItem _resolvePlayer(GamePlayerItem p) {
    final found = _config.players.where((item) =>
        item.id == p.id ||
        (p.playerId != null && item.playerId == p.playerId)).firstOrNull;
    return found ?? p;
  }

  List<GamePlayerItem> _visibleCourtPlayers(List<GamePlayerItem> players) {
    return players
        .map(_resolvePlayer)
        .where((player) => !player.isResting && !player.isWithdrawn)
        .toList();
  }

  Widget _buildRosterSection(DrawingRound currentRound) {
    final isRoundCompleted = _isRoundCompleted(_selectedRoundIndex);
    final canManage = _isHostUser && !isRoundCompleted;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Section Header: Roster Pertandingan
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 4),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  const Icon(Icons.group_outlined, size: 20, color: Color(0xFF063B00)),
                  const SizedBox(width: 8),
                  Text(
                    'ROSTER PERTANDINGAN',
                    style: AppTextStyles.caption.copyWith(
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 0.8,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFBFDBFE)),
                ),
                child: Text(
                  _config.gameType,
                  style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),

        // Court Cards
        ...currentRound.matches.map((m) => _buildCourtRosterCard(m, canManage)),

        const SizedBox(height: 12),

        // Bangku Cadangan / Istirahat (Bench) Card
        _buildBenchCard(currentRound, canManage),
      ],
    );
  }

  Widget _buildCourtRosterCard(DrawingMatch m, bool canManage) {
    final teamA = _visibleCourtPlayers(m.teamA);
    final teamB = _visibleCourtPlayers(m.teamB);

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
          // Court Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    decoration: const BoxDecoration(
                      color: Color(0xFF22C55E),
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'COURT ${m.courtNumber}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 0.5,
                      color: Color(0xFF063B00),
                    ),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2.5),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Text(
                  m.status,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF64748B),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Tim A
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: const [
              Text(
                'Tim A',
                style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
              ),
              Text(
                'Sisi Kiri',
                style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600, color: Color(0xFF94A3B8)),
              ),
            ],
          ),
          const SizedBox(height: 6),
          if (teamA.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 6),
              child: Center(
                child: Text(
                  'Belum ada pemain',
                  style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontStyle: FontStyle.italic),
                ),
              ),
            )
          else
            ...teamA.asMap().entries.map((entry) {
              final pIdx = entry.key;
              final p = entry.value;
              return _buildRosterPlayerRow(p, pIdx, canManage);
            }),

          const SizedBox(height: 10),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 10),

          // Tim B
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: const [
              Text(
                'Tim B',
                style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
              ),
              Text(
                'Sisi Kanan',
                style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600, color: Color(0xFF94A3B8)),
              ),
            ],
          ),
          const SizedBox(height: 6),
          if (teamB.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 6),
              child: Center(
                child: Text(
                  'Belum ada pemain',
                  style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontStyle: FontStyle.italic),
                ),
              ),
            )
          else
            ...teamB.asMap().entries.map((entry) {
              final pIdx = entry.key;
              final p = entry.value;
              return _buildRosterPlayerRow(p, pIdx, canManage);
            }),
        ],
      ),
    );
  }

  Widget _buildRosterPlayerRow(GamePlayerItem rawP, int pIdx, bool canManage) {
    final p = _resolvePlayer(rawP);
    final isPaused = p.isResting;
    final isWithdrawn = p.isWithdrawn;

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: isWithdrawn ? const Color(0xFFFFF1F2).withValues(alpha: 0.4) : Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
          color: isWithdrawn
              ? const Color(0xFFFECDD3)
              : (isPaused ? const Color(0xFFFDE68A) : const Color(0xFFE2E8F0)),
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Row(
              children: [
                Flexible(
                  child: Text(
                    '${pIdx + 1}. ${p.name}',
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                      color: isWithdrawn ? const Color(0xFF94A3B8) : const Color(0xFF0F172A),
                      decoration: isWithdrawn ? TextDecoration.lineThrough : null,
                    ),
                  ),
                ),
                if (isWithdrawn) ...[
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF1F2),
                      borderRadius: BorderRadius.circular(4),
                      border: Border.all(color: const Color(0xFFFECDD3)),
                    ),
                    child: const Text(
                      'Keluar',
                      style: TextStyle(
                        fontSize: 9,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF9F1239),
                      ),
                    ),
                  ),
                ] else if (isPaused) ...[
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFFBEB),
                      borderRadius: BorderRadius.circular(4),
                      border: Border.all(color: const Color(0xFFFDE68A)),
                    ),
                    child: const Text(
                      'Istirahat',
                      style: TextStyle(
                        fontSize: 9,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFFB45309),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 8),
          if (canManage) ...[
            if (isPaused) ...[
              // Tombol Siap Main jika pemain court sedang berstatus istirahat
              InkWell(
                onTap: () => _updatePlayerStatusAction(p, 'reactivate'),
                borderRadius: BorderRadius.circular(6),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFF16A34A),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.play_arrow_rounded, size: 12, color: Colors.white),
                      SizedBox(width: 3),
                      Text(
                        'Siap Main',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ] else if (!isWithdrawn) ...[
              // Tombol Istirahat
              InkWell(
                onTap: () => _updatePlayerStatusAction(p, 'pause'),
                borderRadius: BorderRadius.circular(6),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFFBEB),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: const Color(0xFFFDE68A)),
                  ),
                  child: const Text(
                    'Istirahat',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFFB45309),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 5),
              // Tombol Keluar
              InkWell(
                onTap: () => _updatePlayerStatusAction(p, 'withdraw'),
                borderRadius: BorderRadius.circular(6),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF1F2),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: const Color(0xFFFECDD3)),
                  ),
                  child: const Text(
                    'Keluar',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF9F1239),
                    ),
                  ),
                ),
              ),
            ],
            const SizedBox(width: 5),
          ],
          // Badge Player 1 / Player 2
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
            decoration: BoxDecoration(
              color: const Color(0xFFECFDF5),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: Text(
              'Player ${pIdx + 1}',
              style: const TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.bold,
                color: Color(0xFF065F46),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBenchCard(DrawingRound currentRound, bool canManage) {
    final restingCandidates = [
      ...currentRound.restingPlayers,
      ..._config.players.where((player) => player.isResting),
    ]
        .map(_resolvePlayer)
        .where((player) => !player.isWithdrawn)
        .toList();
    final resting = <GamePlayerItem>[];
    final seenPlayerKeys = <String>{};
    for (final player in restingCandidates) {
      final key = player.playerId != null
          ? 'player:${player.playerId}'
          : 'id:${player.id}';
      if (seenPlayerKeys.add(key)) resting.add(player);
    }
    final withdrawnPlayers = _config.players.where((player) => player.isWithdrawn).toList();

    return Container(
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
          // Bench Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Bangku Cadangan / Istirahat',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2.5),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFFDE68A)),
                ),
                child: Text(
                  '${resting.length} Pemain',
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFFB45309),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          if (resting.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Center(
                child: Text(
                  withdrawnPlayers.isEmpty
                      ? 'Semua pemain aktif bertanding di ronde ini.'
                      : 'Tidak ada pemain di bangku cadangan.',
                  style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
                ),
              ),
            )
          else
            ...resting.asMap().entries.map((entry) {
              final idx = entry.key;
              final p = entry.value;
              final isPaused = p.isResting;

              return Container(
                margin: const EdgeInsets.only(bottom: 6),
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                decoration: BoxDecoration(
                  color: isPaused ? const Color(0xFFFFFBEB) : Colors.white,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: isPaused ? const Color(0xFFFCD34D) : const Color(0xFFE2E8F0),
                  ),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${idx + 1}. ${p.name}',
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    // Badge Bench / Istirahat
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
                      decoration: BoxDecoration(
                        color: isPaused ? const Color(0xFFFEF3C7) : const Color(0xFFFFFBEB),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(
                          color: const Color(0xFFFDE68A),
                        ),
                      ),
                      child: Text(
                        isPaused ? 'Istirahat' : 'Bench',
                        style: TextStyle(
                          fontSize: 9.5,
                          fontWeight: FontWeight.bold,
                          color: isPaused ? const Color(0xFFB45309) : const Color(0xFFD97706),
                        ),
                      ),
                    ),
                    if (canManage) ...[
                      const SizedBox(width: 5),
                      if (isPaused) ...[
                        // Tombol Siap Main
                        InkWell(
                          onTap: () => _updatePlayerStatusAction(p, 'reactivate'),
                          borderRadius: BorderRadius.circular(6),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                            decoration: BoxDecoration(
                              color: const Color(0xFF16A34A),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.play_arrow_rounded, size: 12, color: Colors.white),
                                SizedBox(width: 3),
                                Text(
                                  'Siap Main',
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.white,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ] else ...[
                        // Tombol Istirahat
                        InkWell(
                          onTap: () => _updatePlayerStatusAction(p, 'pause'),
                          borderRadius: BorderRadius.circular(6),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFFBEB),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: const Color(0xFFFDE68A)),
                            ),
                            child: const Text(
                              'Istirahat',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFFB45309),
                              ),
                            ),
                          ),
                        ),
                      ],
                      const SizedBox(width: 5),
                      // Tombol Keluar
                      InkWell(
                        onTap: () => _updatePlayerStatusAction(p, 'withdraw'),
                        borderRadius: BorderRadius.circular(6),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFF1F2),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: const Color(0xFFFECDD3)),
                          ),
                          child: const Text(
                            'Keluar',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF9F1239),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              );
            }),
          if (withdrawnPlayers.isNotEmpty) ...[
            const SizedBox(height: 8),
            const Text(
              'Pemain Keluar',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w800,
                color: Color(0xFF9F1239),
              ),
            ),
            const SizedBox(height: 6),
            ...withdrawnPlayers.asMap().entries.map((entry) {
              final p = entry.value;
              return Container(
                margin: const EdgeInsets.only(bottom: 6),
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF1F2),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFFECDD3)),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${entry.key + 1}. ${p.name}',
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF94A3B8),
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEE2E2),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: const Color(0xFFFECACA)),
                      ),
                      child: const Text(
                        'Keluar',
                        style: TextStyle(
                          fontSize: 9.5,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF991B1B),
                        ),
                      ),
                    ),
                  ],
                ),
              );
            }),
          ],
        ],
      ),
    );
  }

  Future<String?> _showPlayerStatusConfirmationDialog(GamePlayerItem p, String action) async {
    final reasonController = TextEditingController();
    String? errorText;

    return showDialog<String>(
      context: context,
      barrierDismissible: true,
      builder: (dlgCtx) {
        return StatefulBuilder(
          builder: (context, setDlgState) {
            final isPause = action == 'pause';
            final isWithdraw = action == 'withdraw';

            final Color primaryColor = isPause
                ? const Color(0xFFD97706)
                : (isWithdraw ? const Color(0xFFDC2626) : const Color(0xFF16A34A));
            final Color iconBg = isPause
                ? const Color(0xFFFEF3C7)
                : (isWithdraw ? const Color(0xFFFFF1F2) : const Color(0xFFECFDF5));
            final Color iconBorder = isPause
                ? const Color(0xFFFDE68A)
                : (isWithdraw ? const Color(0xFFFECDD3) : const Color(0xFFA7F3D0));
            final IconData icon = isPause
                ? Icons.pause_rounded
                : (isWithdraw ? Icons.person_remove_rounded : Icons.play_arrow_rounded);

            final String title = isPause
                ? 'Istirahatkan Pemain Sementara'
                : (isWithdraw ? 'Keluarkan Pemain dari Sesi' : 'Aktifkan Pemain Kembali');

            final String confirmText = isPause
                ? 'Ya, Istirahatkan Pemain'
                : (isWithdraw ? 'Ya, Keluarkan Pemain' : 'Ya, Siap Main');

            return Dialog(
              backgroundColor: Colors.white,
              elevation: 8,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
              insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(22),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: iconBg,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: iconBorder),
                          ),
                          child: Icon(icon, size: 22, color: primaryColor),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                title,
                                style: const TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF0F172A),
                                ),
                              ),
                              const SizedBox(height: 4),
                              RichText(
                                text: TextSpan(
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Color(0xFF475569),
                                    height: 1.4,
                                  ),
                                  children: [
                                    TextSpan(
                                      text: isPause
                                          ? 'Istirahatkan sementara '
                                          : (isWithdraw ? 'Keluarkan ' : 'Aktifkan kembali '),
                                    ),
                                    TextSpan(
                                      text: p.name,
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                        color: Color(0xFF0F172A),
                                      ),
                                    ),
                                    TextSpan(
                                      text: isPause
                                          ? ' dari ronde mendatang? Pemain dapat diaktifkan kembali nanti saat sudah siap main.'
                                          : (isWithdraw
                                              ? ' secara permanen dari sesi ini? Pemain tidak akan dijadwalkan pada seluruh ronde mendatang.'
                                              : ' untuk dapat mengikuti drawing ronde berikutnya?'),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    if (isPause || isWithdraw) ...[
                      const SizedBox(height: 18),
                      RichText(
                        text: const TextSpan(
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF0F172A),
                          ),
                          children: [
                            TextSpan(text: 'Alasan '),
                            TextSpan(
                              text: '* (Wajib diisi)',
                              style: TextStyle(
                                color: Color(0xFFE11D48),
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 6),
                      TextField(
                        controller: reasonController,
                        style: const TextStyle(fontSize: 12.5, color: Color(0xFF0F172A)),
                        onChanged: (_) {
                          if (errorText != null) {
                            setDlgState(() => errorText = null);
                          }
                        },
                        decoration: InputDecoration(
                          hintText: isPause
                              ? 'Contoh: Cedera ringan, toilet break, urusan mendadak'
                              : 'Contoh: Pulang duluan, cedera berat, urusan mendadak',
                          hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                          filled: true,
                          fillColor: const Color(0xFFF8FAFC),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFCBD5E1)),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFCBD5E1)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: BorderSide(color: primaryColor, width: 1.5),
                          ),
                        ),
                      ),
                      const SizedBox(height: 4),
                      if (errorText != null)
                        Text(
                          errorText!,
                          style: const TextStyle(fontSize: 11, color: Color(0xFFE11D48), fontWeight: FontWeight.w600),
                        )
                      else
                        const Text(
                          'Alasan diperlukan untuk transparansi dan catatan sesi pertandingan.',
                          style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                        ),
                    ],
                    const SizedBox(height: 22),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        OutlinedButton(
                          onPressed: () => Navigator.pop(dlgCtx, null),
                          style: OutlinedButton.styleFrom(
                            side: const BorderSide(color: Color(0xFFE2E8F0)),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                          ),
                          child: const Text(
                            'Batal',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                          ),
                        ),
                        const SizedBox(width: 8),
                        ElevatedButton.icon(
                          onPressed: () {
                            if (isPause || isWithdraw) {
                              final text = reasonController.text.trim();
                              if (text.isEmpty) {
                                setDlgState(() {
                                  errorText = 'Alasan wajib diisi sebelum menyimpan perubahan.';
                                });
                                return;
                              }
                              Navigator.pop(dlgCtx, text);
                            } else {
                              Navigator.pop(dlgCtx, '');
                            }
                          },
                          icon: const Icon(Icons.check_rounded, size: 16, color: Colors.white),
                          label: Text(
                            confirmText,
                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.white),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: primaryColor,
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _updatePlayerStatusAction(GamePlayerItem p, String action, [StateSetter? setSheetState]) async {
    final reason = await _showPlayerStatusConfirmationDialog(p, action);
    if (reason == null) return;

    if (!_isHostUser) return;

    final isSingle = _config.playMode.toLowerCase() == 'single';
    final minRequired = isSingle ? 2 : 4;
    final remainingActivePlayers = _config.players.where((item) {
      final isTarget = item.id == p.id || (p.playerId != null && item.playerId == p.playerId);
      if (isTarget) return false;
      return !item.isWithdrawn && !item.isResting;
    }).toList();

    // 1. Validasi pemain kurang untuk action withdraw -> Konfirmasi penghentian sesi (Web Parity)
    if (action == 'withdraw' && remainingActivePlayers.length < minRequired) {
      if (!mounted) return;
      final confirmStop = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(
            children: [
              Icon(Icons.warning_amber_rounded, color: Color(0xFFD97706)),
              SizedBox(width: 8),
              Expanded(
                child: Text('Pertandingan Tidak Cukup Pemain', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
          content: Text(
            'Mengeluarkan ${p.name} akan menyisakan ${remainingActivePlayers.length} pemain aktif (minimal $minRequired pemain untuk format ${_config.gameType}).\n\nPertandingan tidak dapat dilanjutkan dan sesi pertandingan akan dihentikan.',
            style: const TextStyle(fontSize: 13, color: Color(0xFF334155), height: 1.4),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Batal', style: TextStyle(color: Color(0xFF64748B))),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFDC2626),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: const Text('Hentikan Pertandingan & Keluarkan'),
            ),
          ],
        ),
      );
      if (confirmStop != true) return;

      final pId = MatchService.resolvePlayerId(p) ?? p.playerId ?? int.tryParse(p.id) ?? 0;
      if (_effectiveSessionId != null && pId > 0) {
        try {
          await _matchService.managePlayerStatus(
            sessionId: _effectiveSessionId,
            playerId: pId,
            action: 'withdraw',
            reason: reason.isEmpty ? null : reason,
          );
        } catch (_) {}

        try {
          await _matchService.stopSessionDueToInsufficientPlayers(
            sessionId: _effectiveSessionId,
            reason: reason.isEmpty ? 'Pemain keluar: jumlah pemain aktif tidak mencukupi' : reason,
            stopPlayerId: pId,
          );
        } catch (_) {}
      }

      final idx = _config.players.indexWhere((item) => item.id == p.id);
      if (idx != -1) {
        _config.players[idx] = p.copyWith(
          status: 'withdrawn',
          withdrawnReason: reason.isEmpty ? null : reason,
          withdrawnAt: DateTime.now(),
        );
      }
      _rounds = [];

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${p.name} berhasil dikeluarkan. Pertandingan dihentikan karena jumlah pemain aktif tidak mencukupi.'),
            backgroundColor: const Color(0xFF15803D),
            behavior: SnackBarBehavior.floating,
          ),
        );
        _goToSessionDetail();
      }
      return;
    }

    try {
      final pId = MatchService.resolvePlayerId(p) ?? p.playerId ?? int.tryParse(p.id) ?? 0;
      if (_effectiveSessionId != null && pId > 0) {
        try {
          final res = await _matchService.managePlayerStatus(
            sessionId: _effectiveSessionId,
            playerId: pId,
            action: action,
            reason: reason.isEmpty ? null : reason,
          );
          if (res['is_playing'] == true) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(res['message']?.toString() ??
                      'Pemain sedang bertanding di Court ${res['court']}. Silakan gunakan End Match pada court terkait.'),
                  backgroundColor: const Color(0xFFD97706),
                ),
              );
            }
            return;
          }
        } catch (e) {
          debugPrint('[Drawing] managePlayerStatus backend call: $e');
        }
      }

      final newStatus = (action == 'pause' ? 'resting' : (action == 'withdraw' ? 'withdrawn' : 'active'));
      final idx = _config.players.indexWhere((item) =>
          item.id == p.id ||
          (p.playerId != null && item.playerId == p.playerId) ||
          (item.name.trim().isNotEmpty && item.name.trim().toLowerCase() == p.name.trim().toLowerCase()));
      if (idx != -1) {
        _config.players[idx] = p.copyWith(
          status: newStatus,
          withdrawnReason: reason.isEmpty ? null : reason,
          withdrawnAt: action == 'withdraw' ? DateTime.now() : null,
        );
      }

      // Drawing reconciliation:
      if (_rounds.isNotEmpty && _selectedRoundIndex < _rounds.length) {
        final curR = _rounds[_selectedRoundIndex];
        final updatedMatches = <DrawingMatch>[];
        final updatedResting = List<GamePlayerItem>.from(curR.restingPlayers);
        final remainingActives = _config.players.where((x) => !x.isResting && !x.isWithdrawn).toList();
        final isSingleMode = _config.playMode.toLowerCase() == 'single';
        final minReq = isSingleMode ? 2 : 4;
        final isInsufficient = remainingActives.length < minReq;

        if (action == 'pause' || action == 'withdraw') {
          for (final m in curR.matches) {
            final s = m.status.toLowerCase().trim();
            final isModifiable = s == 'scheduled' || s == 'waiting' || s.isEmpty;
            if (isModifiable) {
              final inA = m.teamA.indexWhere((item) => item.id == p.id || (p.playerId != null && item.playerId == p.playerId));
              final inB = m.teamB.indexWhere((item) => item.id == p.id || (p.playerId != null && item.playerId == p.playerId));
              if (inA != -1 || inB != -1) {
                if (isInsufficient) {
                  // Pemain aktif tidak mencukupi untuk match court ini (misal 1 pemain Single)
                  // Pindahkan match court menjadi kosong ("Belum ada pemain") dan pindahkan pemain lain ke bench
                  for (final otherP in [...m.teamA, ...m.teamB]) {
                    if (otherP.id != p.id && (p.playerId == null || otherP.playerId != p.playerId)) {
                      if (!updatedResting.any((x) => x.id == otherP.id || (otherP.playerId != null && x.playerId == otherP.playerId))) {
                        updatedResting.add(otherP.copyWith(status: 'active'));
                      }
                    }
                  }
                  updatedMatches.add(DrawingMatch(
                    courtNumber: m.courtNumber,
                    teamA: const [],
                    teamB: const [],
                    status: 'Scheduled',
                    matchId: m.matchId,
                  ));
                  if (m.matchId != null) {
                    await _matchService.cancelOrStopScheduledMatch(
                      matchId: m.matchId!,
                      reason: reason.isEmpty ? 'Pemain tidak tersedia tanpa pengganti' : reason,
                    );
                  }
                  if (action == 'pause') {
                    if (!updatedResting.any((x) => x.id == p.id || (p.playerId != null && x.playerId == p.playerId))) {
                      updatedResting.add(p.copyWith(
                        status: 'resting',
                        withdrawnReason: reason.isEmpty ? null : reason,
                      ));
                    }
                  }
                  continue;
                }

                final subIdx = updatedResting.indexWhere((item) => !item.isResting && !item.isWithdrawn);
                GamePlayerItem? subPlayer;
                if (subIdx != -1) {
                  subPlayer = updatedResting.removeAt(subIdx);
                }

                final newTeamA = List<GamePlayerItem>.from(m.teamA);
                final newTeamB = List<GamePlayerItem>.from(m.teamB);

                if (inA != -1 && subPlayer != null) {
                  newTeamA[inA] = subPlayer;
                } else if (inB != -1 && subPlayer != null) {
                  newTeamB[inB] = subPlayer;
                }

                final newStatusMatch = (subPlayer != null) ? m.status : 'Stopped';

                updatedMatches.add(DrawingMatch(
                  courtNumber: m.courtNumber,
                  teamA: subPlayer != null ? newTeamA : const [],
                  teamB: subPlayer != null ? newTeamB : const [],
                  status: newStatusMatch,
                  scoreA: m.scoreA,
                  scoreB: m.scoreB,
                  gamesWonA: m.gamesWonA,
                  gamesWonB: m.gamesWonB,
                  idxA: m.idxA,
                  idxB: m.idxB,
                  pointDisplayA: m.pointDisplayA,
                  pointDisplayB: m.pointDisplayB,
                  isDeuce: m.isDeuce,
                  advantage: m.advantage,
                  setsA: m.setsA,
                  setsB: m.setsB,
                  winnerTeam: m.winnerTeam,
                  matchId: m.matchId,
                  version: m.version,
                  lastEventId: m.lastEventId,
                  stopType: subPlayer == null ? 'withdraw_players' : m.stopType,
                  stopReason: subPlayer == null ? (reason.isEmpty ? null : reason) : m.stopReason,
                ));

                if (m.matchId != null) {
                  final oldPid = p.playerId ?? MatchService.resolvePlayerId(p, _config.players) ?? pId;
                  final newPid = subPlayer != null
                      ? (subPlayer.playerId ?? MatchService.resolvePlayerId(subPlayer, _config.players) ?? int.tryParse(subPlayer.id))
                      : null;
                  if (subPlayer != null && oldPid > 0 && newPid != null && newPid > 0) {
                    await _matchService.substituteMatchParticipant(
                      matchId: m.matchId!,
                      oldPlayerId: oldPid,
                      newPlayerId: newPid,
                      side: inA != -1 ? 'A' : 'B',
                    );
                  } else if (subPlayer == null) {
                    await _matchService.cancelOrStopScheduledMatch(
                      matchId: m.matchId!,
                      reason: reason.isEmpty ? 'Pemain tidak tersedia tanpa pengganti' : reason,
                    );
                  }
                }

                if (action == 'pause') {
                  if (!updatedResting.any((x) => x.id == p.id || (p.playerId != null && x.playerId == p.playerId))) {
                    updatedResting.add(p.copyWith(
                      status: 'resting',
                      withdrawnReason: reason.isEmpty ? null : reason,
                    ));
                  }
                }
                continue;
              }
            }
            updatedMatches.add(m);
          }

          final restIdx = updatedResting.indexWhere((item) => item.id == p.id || (p.playerId != null && item.playerId == p.playerId));
          if (restIdx != -1) {
            if (action == 'withdraw') {
              updatedResting.removeAt(restIdx);
            } else {
              final oldP = updatedResting[restIdx];
              updatedResting[restIdx] = oldP.copyWith(
                status: 'resting',
                withdrawnReason: reason.isEmpty ? null : reason,
              );
            }
          }
        } else if (action == 'reactivate') {
          final activeEligible = _config.players.where((x) => !x.isResting && !x.isWithdrawn).toList();
          final hadEmptyCourt = curR.matches.any((m) => m.teamA.isEmpty || m.teamB.isEmpty);

          if (hadEmptyCourt && activeEligible.length >= minReq) {
            for (final m in curR.matches) {
              if (m.teamA.isEmpty && m.teamB.isEmpty) {
                if (isSingleMode && activeEligible.length >= 2) {
                  final newTeamA = [activeEligible[0]];
                  final newTeamB = [activeEligible[1]];
                  updatedMatches.add(DrawingMatch(
                    courtNumber: m.courtNumber,
                    teamA: newTeamA,
                    teamB: newTeamB,
                    status: 'Scheduled',
                    matchId: m.matchId,
                  ));
                  if (m.matchId != null) {
                    await _matchService.setMatchParticipants(
                      matchId: m.matchId!,
                      teamA: newTeamA,
                      teamB: newTeamB,
                      allPlayers: _config.players,
                    );
                  }
                  final removedIds = {activeEligible[0].id, activeEligible[1].id};
                  final removedPIds = {activeEligible[0].playerId, activeEligible[1].playerId};
                  updatedResting.removeWhere((item) => removedIds.contains(item.id) || (item.playerId != null && removedPIds.contains(item.playerId)));
                } else if (!isSingleMode && activeEligible.length >= 4) {
                  final newTeamA = [activeEligible[0], activeEligible[1]];
                  final newTeamB = [activeEligible[2], activeEligible[3]];
                  updatedMatches.add(DrawingMatch(
                    courtNumber: m.courtNumber,
                    teamA: newTeamA,
                    teamB: newTeamB,
                    status: 'Scheduled',
                    matchId: m.matchId,
                  ));
                  if (m.matchId != null) {
                    await _matchService.setMatchParticipants(
                      matchId: m.matchId!,
                      teamA: newTeamA,
                      teamB: newTeamB,
                      allPlayers: _config.players,
                    );
                  }
                  final removedIds = {activeEligible[0].id, activeEligible[1].id, activeEligible[2].id, activeEligible[3].id};
                  final removedPIds = {activeEligible[0].playerId, activeEligible[1].playerId, activeEligible[2].playerId, activeEligible[3].playerId};
                  updatedResting.removeWhere((item) => removedIds.contains(item.id) || (item.playerId != null && removedPIds.contains(item.playerId)));
                } else {
                  updatedMatches.add(m);
                }
              } else {
                updatedMatches.add(m);
              }
            }
          } else {
            for (final m in curR.matches) {
              updatedMatches.add(m);
            }
            final restIdx = updatedResting.indexWhere((item) => item.id == p.id || (p.playerId != null && item.playerId == p.playerId));
            if (restIdx != -1) {
              final oldP = updatedResting[restIdx];
              updatedResting[restIdx] = oldP.copyWith(
                status: 'active',
              );
            }
          }
        }

        _rounds[_selectedRoundIndex] = DrawingRound(
          roundNumber: curR.roundNumber,
          matches: updatedMatches,
          restingPlayers: updatedResting,
        );

        // Sesuaikan juga ronde masa depan di state lokal:
        if (_rounds.length > _selectedRoundIndex + 1) {
          for (int fr = _selectedRoundIndex + 1; fr < _rounds.length; fr++) {
            final fRound = _rounds[fr];
            final fMatches = <DrawingMatch>[];
            final fResting = List<GamePlayerItem>.from(fRound.restingPlayers);
            for (final m in fRound.matches) {
              final inA = m.teamA.indexWhere((item) => item.id == p.id || (p.playerId != null && item.playerId == p.playerId));
              final inB = m.teamB.indexWhere((item) => item.id == p.id || (p.playerId != null && item.playerId == p.playerId));
              if (inA != -1 || inB != -1) {
                final subIdx = fResting.indexWhere((item) => !item.isResting && !item.isWithdrawn);
                GamePlayerItem? subPlayer;
                if (subIdx != -1) {
                  subPlayer = fResting.removeAt(subIdx);
                }
                final newTeamA = List<GamePlayerItem>.from(m.teamA);
                final newTeamB = List<GamePlayerItem>.from(m.teamB);
                if (inA != -1 && subPlayer != null) {
                  newTeamA[inA] = subPlayer;
                } else if (inB != -1 && subPlayer != null) {
                  newTeamB[inB] = subPlayer;
                }
                fMatches.add(DrawingMatch(
                  courtNumber: m.courtNumber,
                  teamA: subPlayer != null ? newTeamA : const [],
                  teamB: subPlayer != null ? newTeamB : const [],
                  status: subPlayer != null ? m.status : 'Stopped',
                  matchId: m.matchId,
                ));
                if (action == 'pause') {
                  if (!fResting.any((x) => x.id == p.id || (p.playerId != null && x.playerId == p.playerId))) {
                    fResting.add(p.copyWith(status: 'resting', withdrawnReason: reason.isEmpty ? null : reason));
                  }
                }
              } else {
                fMatches.add(m);
              }
            }
            _rounds[fr] = DrawingRound(
              roundNumber: fRound.roundNumber,
              matches: fMatches,
              restingPlayers: fResting,
            );
          }
        }

        // Sesuaikan juga ronde masa depan di Supabase:
        if (_effectiveSessionId != null && pId > 0) {
          await _matchService.adjustFutureScheduleOnPlayerStatusChange(
            sessionId: _effectiveSessionId,
            currentRoundNumber: _selectedRoundIndex + 1,
            targetPlayerId: pId,
            action: action,
            allPlayers: _config.players,
          );
        }

        if (_effectiveSessionId != null) {
          await _loadSessionAndDrawing(silent: true);
        }
      }

      if (mounted) {
        final msg = action == 'pause'
            ? '${p.name} berhasil diistirahatkan ke bangku cadangan.'
            : (action == 'withdraw'
                ? '${p.name} berhasil dikeluarkan dari sesi.'
                : '${p.name} berhasil diaktifkan kembali.');
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg),
            behavior: SnackBarBehavior.floating,
            backgroundColor: const Color(0xFF15803D),
          ),
        );
      }

      setSheetState?.call(() {});
      if (mounted) setState(() {});
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal mengubah status pemain: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }
}
