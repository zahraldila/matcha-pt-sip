import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../data/datasource/match_remote_data_source.dart';
import '../domain/models/match_model.dart';

class MatchScoringPage extends StatefulWidget {
  final int? matchId;
  final int? nomorMatch;
  final dynamic sessionId;
  final String sessionName;
  final String courtName;
  final String sideA;
  final String sideB;
  final int initialScoreA;
  final int initialScoreB;
  final String? statusMatch;
  final MatchModel? matchData;
  final MatchRemoteDataSource? dataSource;

  const MatchScoringPage({
    super.key,
    this.matchId,
    this.nomorMatch,
    this.sessionId,
    this.sessionName = 'Saturday Morning',
    this.courtName = 'Court 1 — SiJi Tennis Court',
    this.sideA = 'Aldi · Budi',
    this.sideB = 'Caca · Dina',
    this.initialScoreA = 0,
    this.initialScoreB = 0,
    this.statusMatch,
    this.matchData,
    this.dataSource,
  });

  @override
  State<MatchScoringPage> createState() => _MatchScoringPageState();
}

class _MatchScoringPageState extends State<MatchScoringPage> {
  late final MatchRemoteDataSource? _dataSource;

  int _selectedSet = 1;
  final Map<int, int> _scoresA = {1: 0, 2: 0, 3: 0};
  final Map<int, int> _scoresB = {1: 0, 2: 0, 3: 0};

  bool _isLoadingInitial = false;
  bool _isSavingScore = false;
  bool _isFinishing = false;
  bool _isFinished = false;

  int get _currentScoreA => _scoresA[_selectedSet] ?? 0;
  int get _currentScoreB => _scoresB[_selectedSet] ?? 0;

  @override
  void initState() {
    super.initState();
    try {
      _dataSource = widget.dataSource ?? MatchRemoteDataSource();
    } catch (_) {
      _dataSource = null;
    }
    if (widget.initialScoreA > 0) {
      _scoresA[1] = widget.initialScoreA;
    }
    if (widget.initialScoreB > 0) {
      _scoresB[1] = widget.initialScoreB;
    }
    final normalized = (widget.statusMatch ?? '').toString().toLowerCase();
    if (normalized == 'finished') {
      _isFinished = true;
    }
    _loadMatchAndScoreData();
  }

  /// Memuat data score yang sudah tersimpan di tb_score dan status pada tb_match
  Future<void> _loadMatchAndScoreData() async {
    final matchId = widget.matchId;
    final dataSource = _dataSource;
    if (matchId == null || dataSource == null) {
      return;
    }

    setState(() => _isLoadingInitial = true);
    try {
      // 1. Cek status match
      final match = await dataSource.getMatchById(matchId);
      if (match != null && match.isFinished) {
        _isFinished = true;
      }

      // 2. Ambil skor yang sudah ada di tb_score
      final scores = await dataSource.getScoresByMatchId(matchId);
      for (final s in scores) {
        final setNum = s.setNumber;
        if (setNum != null && setNum >= 1 && setNum <= 3) {
          _scoresA[setNum] = s.scoreSideA;
          _scoresB[setNum] = s.scoreSideB;
        }
      }
    } catch (_) {
      // Jika terjadi error koneksi awal, gunakan default in-memory
    } finally {
      if (mounted) {
        setState(() => _isLoadingInitial = false);
      }
    }
  }

  /// Mengubah skor Side A
  void _changeScoreA(int delta) {
    if (_isFinished) return;
    final current = _scoresA[_selectedSet] ?? 0;
    final updated = current + delta;
    if (updated >= 0) {
      setState(() {
        _scoresA[_selectedSet] = updated;
      });
    }
  }

  /// Mengubah skor Side B
  void _changeScoreB(int delta) {
    if (_isFinished) return;
    final current = _scoresB[_selectedSet] ?? 0;
    final updated = current + delta;
    if (updated >= 0) {
      setState(() {
        _scoresB[_selectedSet] = updated;
      });
    }
  }

  /// Menyimpan atau memperbarui skor set aktif ke tb_score
  Future<void> _handleSaveScore() async {
    if (_isSavingScore || _isFinishing) return;

    final matchId = widget.matchId;
    if (matchId == null) {
      _showFeedbackSnackBar(
        message:
            'Tidak dapat menyimpan: ID Pertandingan (match_id) belum tersedia dari sistem.',
        isError: true,
      );
      return;
    }

    final scoreA = _currentScoreA;
    final scoreB = _currentScoreB;

    if (scoreA < 0 || scoreB < 0) {
      _showFeedbackSnackBar(
        message: 'Skor tidak boleh bernilai negatif.',
        isError: true,
      );
      return;
    }

    setState(() => _isSavingScore = true);

    try {
      final dataSource = _dataSource;
      if (dataSource != null) {
        await dataSource.saveOrUpdateScore(
          matchId: matchId,
          setNumber: _selectedSet,
          scoreSideA: scoreA,
          scoreSideB: scoreB,
        );
      }

      if (mounted) {
        _showFeedbackSnackBar(
          message: 'Skor Set $_selectedSet berhasil disimpan ke database!',
          isError: false,
        );
      }
    } catch (e) {
      if (mounted) {
        final errorMsg = e.toString().replaceFirst('Exception: ', '');
        _showFeedbackSnackBar(
          message: errorMsg,
          isError: true,
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isSavingScore = false);
      }
    }
  }

  /// Menghitung total skor agregat semua set
  int get _totalScoreA => _scoresA.values.fold(0, (sum, val) => sum + val);
  int get _totalScoreB => _scoresB.values.fold(0, (sum, val) => sum + val);

  /// Menampilkan dialog konfirmasi penyelesaian match
  void _finishMatch() {
    if (_isFinished || _isFinishing) return;
    final surfColor = context.surf;

    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: surfColor,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: context.surfBorder),
        ),
        title: Text(
          'Selesaikan Pertandingan?',
          style: AppTextStyles.cardTitle.copyWith(color: context.txtPrimary),
        ),
        content: Text(
          'Hasil akhir agregat: ${widget.sideA} ($_totalScoreA) vs ${widget.sideB} ($_totalScoreB)\n\nStatus pertandingan akan diperbarui menjadi Finished.',
          style:
              AppTextStyles.bodySecondary.copyWith(color: context.txtSecondary),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(
              'Batal',
              style: AppTextStyles.caption.copyWith(color: context.txtSecondary),
            ),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: context.brandColor,
              foregroundColor: Colors.black,
            ),
            onPressed: () {
              Navigator.pop(dialogContext);
              _processFinishMatch();
            },
            child: const Text('Ya, Selesaikan Match'),
          ),
        ],
      ),
    );
  }

  /// Mengeksekusi proses penyelesaian match pada tb_match
  Future<void> _processFinishMatch() async {
    if (_isFinishing) return;

    if (_isFinished) {
      _showFeedbackSnackBar(
        message: 'Pertandingan ini sudah selesai.',
        isError: false,
      );
      return;
    }

    final matchId = widget.matchId;
    if (matchId == null) {
      _showFeedbackSnackBar(
        message:
            'Tidak dapat menyelesaikan: ID Pertandingan (match_id) belum tersedia dari sistem.',
        isError: true,
      );
      return;
    }

    setState(() => _isFinishing = true);

    try {
      final dataSource = _dataSource;
      if (dataSource != null) {
        // Cek status terkini dari database untuk mencegah double-finish
        final currentMatch = await dataSource.getMatchById(matchId);
        if (currentMatch != null && currentMatch.isFinished) {
          if (mounted) {
            setState(() => _isFinished = true);
            _showFeedbackSnackBar(
              message: 'Pertandingan ini sudah selesai.',
              isError: false,
            );
          }
          return;
        }

        // 1. Simpan skor set yang sedang aktif terlebih dahulu ke tb_score
        await dataSource.saveOrUpdateScore(
          matchId: matchId,
          setNumber: _selectedSet,
          scoreSideA: _currentScoreA,
          scoreSideB: _currentScoreB,
        );

        // 2. Hitung hasil pertandingan berdasarkan skor per set yang tersimpan di tb_score
        final scores = await dataSource.getScoresByMatchId(matchId);
        int totalScoreA = 0;
        int totalScoreB = 0;
        for (final s in scores) {
          totalScoreA += s.scoreSideA;
          totalScoreB += s.scoreSideB;
        }

        final String hasilPertandingan = totalScoreA > totalScoreB
            ? 'Side A Win'
            : (totalScoreB > totalScoreA ? 'Side B Win' : 'Draw');

        // 3. Selesaikan match pada tb_match (status_match = 'Finished', waktu_selesai, hasil_pertandingan)
        await dataSource.finishMatch(
          matchId: matchId,
          hasilPertandingan: hasilPertandingan,
        );
      }

      if (mounted) {
        setState(() {
          _isFinished = true;
        });

        _showFeedbackSnackBar(
          message: 'Match berhasil diselesaikan.',
          isError: false,
        );
      }
    } catch (e) {
      if (mounted) {
        final errorMsg = e.toString().replaceFirst('Exception: ', '');
        _showFeedbackSnackBar(
          message: 'Gagal menyelesaikan pertandingan: $errorMsg',
          isError: true,
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isFinishing = false);
      }
    }
  }

  /// Helper untuk menampilkan snackbar feedback konsisten
  void _showFeedbackSnackBar({
    required String message,
    required bool isError,
  }) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          message,
          style: AppTextStyles.body.copyWith(
            color: context.txtPrimary,
            fontWeight: FontWeight.w600,
          ),
        ),
        backgroundColor: context.surf,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(
            color: isError
                ? AppColors.error.withValues(alpha: 0.5)
                : context.brandColor.withValues(alpha: 0.5),
            width: 1,
          ),
        ),
        duration: Duration(seconds: isError ? 4 : 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text('Input Score', style: TextStyle(color: context.txtPrimary)),
        leading: IconButton(
          icon: Icon(
            Icons.arrow_back_ios_new_rounded,
            size: 18,
            color: context.txtPrimary,
          ),
          onPressed: () => Navigator.maybePop(context),
        ),
      ),
      body: SafeArea(
        child: _isLoadingInitial
            ? Center(
                child: CircularProgressIndicator(
                  color: context.brandColor,
                  strokeWidth: 2.5,
                ),
              )
            : SingleChildScrollView(
                padding: const EdgeInsets.symmetric(
                  horizontal: 20.0,
                  vertical: 16.0,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // 1. Court & Round Info
                    _buildMatchHeader(context),
                    const SizedBox(height: 24),

                    // 2. Set Selector Tabs
                    _buildSetSelector(context),
                    const SizedBox(height: 24),

                    // 3. Big Score Board (Side A vs Side B)
                    _buildScoreBoard(context),
                    const SizedBox(height: 32),

                    // 4. Action Buttons
                    ElevatedButton(
                      onPressed: (_isSavingScore || _isFinishing || _isFinished)
                          ? null
                          : _handleSaveScore,
                      child: _isSavingScore
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.5,
                                valueColor:
                                    AlwaysStoppedAnimation<Color>(Colors.black),
                              ),
                            )
                          : const Text('SIMPAN SCORE'),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: _isFinished
                            ? context.txtSecondary
                            : AppColors.warning,
                        side: BorderSide(
                          color: _isFinished
                              ? context.surfBorder
                              : AppColors.warning.withValues(alpha: 0.5),
                        ),
                      ),
                      onPressed: (_isFinished || _isFinishing || _isSavingScore)
                          ? null
                          : _finishMatch,
                      icon: _isFinishing
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(
                                  AppColors.warning,
                                ),
                              ),
                            )
                          : Icon(
                              _isFinished
                                  ? Icons.check_circle_rounded
                                  : Icons.flag_rounded,
                              size: 18,
                            ),
                      label: Text(
                        _isFinishing
                            ? 'MEMPROSES...'
                            : (_isFinished
                                ? 'PERTANDINGAN SELESAI'
                                : 'SELESAIKAN MATCH'),
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _buildMatchHeader(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.surfBorder),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                widget.courtName,
                style: AppTextStyles.cardTitle.copyWith(
                  color: context.brandColor,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                widget.nomorMatch != null
                    ? 'Match #${widget.nomorMatch} · ${widget.sessionName}'
                    : 'Match · ${widget.sessionName}',
                style:
                    AppTextStyles.caption.copyWith(color: context.txtSecondary),
              ),
            ],
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: _isFinished
                  ? context.surfSec
                  : AppColors.inProgressBadge.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              _isFinished ? 'Finished' : 'In Progress',
              style: AppTextStyles.badge.copyWith(
                fontSize: 10,
                color: _isFinished
                    ? context.txtSecondary
                    : AppColors.inProgressBadge,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSetSelector(BuildContext context) {
    return Row(
      children: [1, 2, 3].map((setNum) {
        final isSelected = _selectedSet == setNum;
        final setScoreA = _scoresA[setNum] ?? 0;
        final setScoreB = _scoresB[setNum] ?? 0;
        final hasScore = setScoreA > 0 || setScoreB > 0;

        return Expanded(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4.0),
            child: InkWell(
              onTap: () => setState(() => _selectedSet = setNum),
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: isSelected
                      ? context.brandColor.withValues(alpha: 0.15)
                      : context.surf,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: isSelected ? context.brandColor : context.surfBorder,
                    width: isSelected ? 1.5 : 1,
                  ),
                ),
                child: Column(
                  children: [
                    Text(
                      'Set $setNum',
                      textAlign: TextAlign.center,
                      style: AppTextStyles.caption.copyWith(
                        fontWeight:
                            isSelected ? FontWeight.bold : FontWeight.normal,
                        color: isSelected
                            ? context.brandColor
                            : context.txtSecondary,
                      ),
                    ),
                    if (hasScore) ...[
                      const SizedBox(height: 2),
                      Text(
                        '$setScoreA - $setScoreB',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          color: isSelected
                              ? context.brandColor
                              : context.txtSecondary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildScoreBoard(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: context.surf,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: context.surfBorder),
      ),
      child: Column(
        children: [
          // Team A Row
          _buildTeamScoreRow(
            context: context,
            teamName: widget.sideA,
            score: _currentScoreA,
            onIncrement: () => _changeScoreA(1),
            onDecrement: () => _changeScoreA(-1),
          ),

          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16.0),
            child: Divider(color: context.surfBorder),
          ),

          // Team B Row
          _buildTeamScoreRow(
            context: context,
            teamName: widget.sideB,
            score: _currentScoreB,
            onIncrement: () => _changeScoreB(1),
            onDecrement: () => _changeScoreB(-1),
          ),
        ],
      ),
    );
  }

  Widget _buildTeamScoreRow({
    required BuildContext context,
    required String teamName,
    required int score,
    required VoidCallback onIncrement,
    required VoidCallback onDecrement,
  }) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                teamName,
                style: AppTextStyles.cardTitle.copyWith(
                  fontSize: 16,
                  color: context.txtPrimary,
                ),
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 2),
              Text(
                'Pemain',
                style: AppTextStyles.caption.copyWith(
                  color: context.txtSecondary,
                ),
              ),
            ],
          ),
        ),
        Row(
          children: [
            // Minus Button
            _buildScoreButton(
              context: context,
              icon: Icons.remove_rounded,
              color: context.surfSec,
              iconColor: _isFinished ? context.txtDisabled : context.txtPrimary,
              onTap: _isFinished ? () {} : onDecrement,
            ),
            const SizedBox(width: 14),

            // Big Score Number
            SizedBox(
              width: 44,
              child: Text(
                '$score',
                textAlign: TextAlign.center,
                style: AppTextStyles.scoreDisplay.copyWith(
                  color:
                      _isFinished ? context.txtSecondary : context.brandColor,
                  fontSize: 34,
                ),
              ),
            ),
            const SizedBox(width: 14),

            // Plus Button
            _buildScoreButton(
              context: context,
              icon: Icons.add_rounded,
              color: _isFinished ? context.surfSec : context.brandColor,
              iconColor: _isFinished ? context.txtDisabled : Colors.black,
              onTap: _isFinished ? () {} : onIncrement,
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildScoreButton({
    required BuildContext context,
    required IconData icon,
    required Color color,
    required Color iconColor,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: context.surfBorder),
        ),
        child: Icon(icon, size: 20, color: iconColor),
      ),
    );
  }
}
