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
  final AuthController? authController;
  final bool? isHost;
  final dynamic hostUserId;
  final MatchService? matchService;
  const DrawingResultPage({
    super.key,
    this.sessionId,
    this.config,
    this.initialRounds,
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
  bool _hasAutoNavigatedToScoring = false;
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
      final savedRounds = await _matchService.loadSavedDrawing(
        sessionId: sessId,
        registeredPlayers: _config.players,
      );
      final formatInfo = await _matchService.getActiveDrawingFormat(sessId);
      final activeFormatName = formatInfo?['nama_format'] as String?;
      final activeFormatId = formatInfo?['match_format_id'] as int?;

      if (!mounted) return;
      setState(() {
        _sessionData = session;
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
          _rounds = savedRounds;
          _config.courtCount = savedRounds
              .expand((round) => round.matches)
              .fold<int>(
                1,
                (highest, match) =>
                    match.courtNumber > highest ? match.courtNumber : highest,
              );
        } else if (_rounds.isNotEmpty && _isHostUser) {
          // Keep existing host rounds
        } else if (widget.initialRounds != null && widget.initialRounds!.isNotEmpty && _isHostUser) {
          _rounds = List.from(widget.initialRounds!);
        } else if (_isHostUser && _rounds.isEmpty) {
          _generateInitialDrawing();
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
  Future<void> _shuffleDrawing() async {
    if (!_isHostUser) return;
    if (_isLoading) return;
    if (_isLocked) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Drawing sudah dikunci dan tidak dapat diacak ulang.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
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
        if (mounted) {
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
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Jadwal dan rotasi pemain berhasil diacak ulang! 🔀'),
            behavior: SnackBarBehavior.floating,
          ),
        );
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
                  _isLocked ? Icons.sports_tennis_rounded : Icons.hourglass_empty_rounded,
                  size: 52,
                  color: _isLocked ? AppColors.matchaDark : const Color(0xFF94A3B8),
                ),
                const SizedBox(height: 14),
                Text(
                  _isLocked ? 'Pertandingan Sedang Berlangsung' : 'Drawing Belum Tersedia',
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 8),
                Text(
                  _isLocked
                      ? 'Jadwal dan sesi pertandingan telah dimulai oleh Host.'
                      : (_isHostUser
                          ? 'Silakan acak susunan tim untuk memulai pertandingan.'
                          : 'Host sedang mempersiapkan drawing pertandingan untuk sesi ini.'),
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 20),
                if (_isLocked)
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
                // Round Selector Pills
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: List.generate(_rounds.length, (idx) {
                      final isSelected = _selectedRoundIndex == idx;
                      final r = _rounds[idx];
                      return Container(
                        margin: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text(
                            'Ronde ${r.roundNumber} (${r.matches.length} Match)',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: isSelected ? Colors.white : const Color(0xFF475569),
                            ),
                          ),
                          selected: isSelected,
                          selectedColor: AppColors.matchaDark,
                          backgroundColor: Colors.white,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                            side: BorderSide(
                              color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
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
                // Bangku Cadangan / Istirahat (Bench)
                if (currentRound.restingPlayers.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFFBEB),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFFDE68A)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.chair_outlined, size: 18, color: Color(0xFFD97706)),
                            const SizedBox(width: 8),
                            Text(
                              'BANGKU ISTIRAHAT (BENCH)',
                              style: AppTextStyles.caption.copyWith(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                color: const Color(0xFFB45309),
                              ),
                            ),
                            const Spacer(),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFEF3C7),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                '${currentRound.restingPlayers.length} Pemain',
                                style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFFB45309)),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          runSpacing: 6,
                          children: currentRound.restingPlayers.map((p) {
                            return Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: const Color(0xFFFDE68A)),
                              ),
                              child: Text(
                                p.name,
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                              ),
                            );
                          }).toList(),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                // Rincian Roster Pertandingan Card
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'ROSTER PERTANDINGAN',
                            style: AppTextStyles.caption.copyWith(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: const Color(0xFF0F172A),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEFF6FF),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              _config.gameType,
                              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      ...currentRound.matches.map((m) {
                        return Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Court ${m.courtNumber} (Sesi Game)',
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                              ),
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      'Team A: ${m.teamANames}',
                                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                    ),
                                  ),
                                  const Text('vs', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'Team B: ${m.teamBNames}',
                                      textAlign: TextAlign.right,
                                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        );
                      }),
                    ],
                  ),
                ),
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
            child: SizedBox(
              width: double.infinity,
              height: 48,
              child: _isHostUser
                  ? ElevatedButton(
                      onPressed: _isStartingScoring ? null : _startLiveScoring,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.matchaDark,
                        foregroundColor: Colors.white,
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
                                  _isLocked ? 'Buka Live Scoring' : 'Kunci Tim & Mulai Scoring Live',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                ),
                                const SizedBox(width: 8),
                                const Icon(Icons.arrow_forward_rounded, size: 18),
                              ],
                            ),
                    )
                  : (_isLocked
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
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: match.teamA.map((p) => _buildPlayerBadge(p)).toList(),
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
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: match.teamB.map((p) => _buildPlayerBadge(p)).toList(),
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
}
