import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';

class MatchScoringPage extends StatefulWidget {
  final String sessionName;
  final String courtName;
  final String sideA;
  final String sideB;

  const MatchScoringPage({
    super.key,
    this.sessionName = 'Saturday Morning',
    this.courtName = 'Court 1 — SiJi Tennis Court',
    this.sideA = 'Aldi · Budi',
    this.sideB = 'Caca · Dina',
  });

  @override
  State<MatchScoringPage> createState() => _MatchScoringPageState();
}

class _MatchScoringPageState extends State<MatchScoringPage> {
  int _scoreA = 6;
  int _scoreB = 4;
  int _selectedSet = 1;
  bool _isFinished = false;

  void _finishMatch() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppColors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: AppColors.surfaceBorder),
        ),
        title: Text('Selesaikan Pertandingan?', style: AppTextStyles.cardTitle),
        content: Text(
          'Hasil akhir: ${widget.sideA} ($_scoreA) vs ${widget.sideB} ($_scoreB)\n\nSkor akan disimpan ke riwayat dan siap untuk Re-Drawing ronde selanjutnya.',
          style: AppTextStyles.bodySecondary,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('Batal', style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: AppColors.textOnPrimary,
            ),
            onPressed: () {
              Navigator.pop(context);
              setState(() {
                _isFinished = true;
              });
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(
                    'Pertandingan selesai! Riwayat bermain telah diperbarui. Siap Re-Drawing! 🎾',
                    style: AppTextStyles.body.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  backgroundColor: AppColors.surfaceSecondary,
                  behavior: SnackBarBehavior.floating,
                ),
              );
            },
            child: const Text('Ya, Selesaikan Match'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Input Score'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () => Navigator.maybePop(context),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Court & Round Info
              _buildMatchHeader(),
              const SizedBox(height: 24),

              // 2. Set Selector Tabs
              _buildSetSelector(),
              const SizedBox(height: 24),

              // 3. Big Score Board (Side A vs Side B)
              _buildScoreBoard(),
              const SizedBox(height: 32),

              // 4. Action Buttons
              ElevatedButton(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        'Skor pertandingan berhasil disimpan!',
                        style: AppTextStyles.body.copyWith(
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      backgroundColor: AppColors.surfaceSecondary,
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                },
                child: const Text('SIMPAN SCORE'),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  foregroundColor: _isFinished ? AppColors.textSecondary : AppColors.warning,
                  side: BorderSide(
                    color: _isFinished
                        ? AppColors.surfaceBorder
                        : AppColors.warning.withValues(alpha: 0.5),
                  ),
                ),
                onPressed: _isFinished ? null : _finishMatch,
                icon: Icon(
                  _isFinished ? Icons.check_circle_rounded : Icons.flag_rounded,
                  size: 18,
                ),
                label: Text(_isFinished ? 'PERTANDINGAN SELESAI' : 'SELESAIKAN MATCH'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMatchHeader() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.surfaceBorder),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                widget.courtName,
                style: AppTextStyles.cardTitle.copyWith(color: AppColors.primary, fontSize: 14),
              ),
              const SizedBox(height: 4),
              Text(
                'Ronde 1 · Match 1 · ${widget.sessionName}',
                style: AppTextStyles.caption,
              ),
            ],
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: _isFinished
                  ? AppColors.surfaceSecondary
                  : AppColors.inProgressBadge.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              _isFinished ? 'FINISHED' : 'IN PROGRESS',
              style: AppTextStyles.badge.copyWith(
                fontSize: 10,
                color: _isFinished ? AppColors.textSecondary : AppColors.inProgressBadge,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSetSelector() {
    return Row(
      children: [1, 2, 3].map((setNum) {
        final isSelected = _selectedSet == setNum;
        return Expanded(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4.0),
            child: InkWell(
              onTap: () => setState(() => _selectedSet = setNum),
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: isSelected ? AppColors.primary.withValues(alpha: 0.15) : AppColors.surface,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: isSelected ? AppColors.primary : AppColors.surfaceBorder,
                    width: isSelected ? 1.5 : 1,
                  ),
                ),
                child: Text(
                  'Set $setNum',
                  textAlign: TextAlign.center,
                  style: AppTextStyles.caption.copyWith(
                    fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    color: isSelected ? AppColors.primary : AppColors.textSecondary,
                  ),
                ),
              ),
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildScoreBoard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.surfaceBorder),
      ),
      child: Column(
        children: [
          // Team A Row
          _buildTeamScoreRow(
            teamName: widget.sideA,
            score: _scoreA,
            onIncrement: () => setState(() => _scoreA++),
            onDecrement: () => setState(() => _scoreA > 0 ? _scoreA-- : 0),
          ),

          const Padding(
            padding: EdgeInsets.symmetric(vertical: 16.0),
            child: Divider(),
          ),

          // Team B Row
          _buildTeamScoreRow(
            teamName: widget.sideB,
            score: _scoreB,
            onIncrement: () => setState(() => _scoreB++),
            onDecrement: () => setState(() => _scoreB > 0 ? _scoreB-- : 0),
          ),
        ],
      ),
    );
  }

  Widget _buildTeamScoreRow({
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
                style: AppTextStyles.cardTitle.copyWith(fontSize: 16),
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 2),
              Text('Pemain', style: AppTextStyles.caption),
            ],
          ),
        ),
        Row(
          children: [
            // Minus Button
            _buildScoreButton(
              icon: Icons.remove_rounded,
              color: AppColors.surfaceSecondary,
              iconColor: AppColors.textPrimary,
              onTap: onDecrement,
            ),
            const SizedBox(width: 14),

            // Big Score Number
            SizedBox(
              width: 44,
              child: Text(
                '$score',
                textAlign: TextAlign.center,
                style: AppTextStyles.scoreDisplay.copyWith(
                  color: AppColors.primary,
                  fontSize: 34,
                ),
              ),
            ),
            const SizedBox(width: 14),

            // Plus Button
            _buildScoreButton(
              icon: Icons.add_rounded,
              color: AppColors.primary,
              iconColor: AppColors.textOnPrimary,
              onTap: onIncrement,
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildScoreButton({
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
          border: Border.all(color: AppColors.surfaceBorder),
        ),
        child: Icon(icon, size: 20, color: iconColor),
      ),
    );
  }
}
