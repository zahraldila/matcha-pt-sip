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
        backgroundColor: context.surf,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: context.surfBorder),
        ),
        title: Text('Selesaikan Pertandingan?', style: AppTextStyles.cardTitle.copyWith(color: context.txtPrimary)),
        content: Text(
          'Hasil akhir: ${widget.sideA} ($_scoreA) vs ${widget.sideB} ($_scoreB)\n\nSkor akan disimpan ke riwayat dan siap untuk Re-Drawing ronde selanjutnya.',
          style: AppTextStyles.bodySecondary.copyWith(color: context.txtSecondary),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('Batal', style: AppTextStyles.caption.copyWith(color: context.txtSecondary)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: context.brandColor,
              foregroundColor: Colors.black,
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
                      color: context.txtPrimary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  backgroundColor: context.surf,
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
      backgroundColor: context.bg,
      appBar: AppBar(
        title: Text('Input Score', style: TextStyle(color: context.txtPrimary)),
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, size: 18, color: context.txtPrimary),
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
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        'Skor pertandingan berhasil disimpan!',
                        style: AppTextStyles.body.copyWith(
                          color: context.txtPrimary,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      backgroundColor: context.surf,
                      behavior: SnackBarBehavior.floating,
                    ),
                  );
                },
                child: const Text('SIMPAN SCORE'),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  foregroundColor: _isFinished ? context.txtSecondary : AppColors.warning,
                  side: BorderSide(
                    color: _isFinished
                        ? context.surfBorder
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
                style: AppTextStyles.cardTitle.copyWith(color: context.brandColor, fontSize: 14),
              ),
              const SizedBox(height: 4),
              Text(
                'Ronde 1 · Match 1 · ${widget.sessionName}',
                style: AppTextStyles.caption.copyWith(color: context.txtSecondary),
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
              _isFinished ? 'FINISHED' : 'IN PROGRESS',
              style: AppTextStyles.badge.copyWith(
                fontSize: 10,
                color: _isFinished ? context.txtSecondary : AppColors.inProgressBadge,
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
        return Expanded(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4.0),
            child: InkWell(
              onTap: () => setState(() => _selectedSet = setNum),
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: isSelected ? context.brandColor.withValues(alpha: 0.15) : context.surf,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: isSelected ? context.brandColor : context.surfBorder,
                    width: isSelected ? 1.5 : 1,
                  ),
                ),
                child: Text(
                  'Set $setNum',
                  textAlign: TextAlign.center,
                  style: AppTextStyles.caption.copyWith(
                    fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    color: isSelected ? context.brandColor : context.txtSecondary,
                  ),
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
            score: _scoreA,
            onIncrement: () => setState(() => _scoreA++),
            onDecrement: () => setState(() => _scoreA > 0 ? _scoreA-- : 0),
          ),

          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16.0),
            child: Divider(color: context.surfBorder),
          ),

          // Team B Row
          _buildTeamScoreRow(
            context: context,
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
                style: AppTextStyles.cardTitle.copyWith(fontSize: 16, color: context.txtPrimary),
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 2),
              Text('Pemain', style: AppTextStyles.caption.copyWith(color: context.txtSecondary)),
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
              iconColor: context.txtPrimary,
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
                  color: context.brandColor,
                  fontSize: 34,
                ),
              ),
            ),
            const SizedBox(width: 14),

            // Plus Button
            _buildScoreButton(
              context: context,
              icon: Icons.add_rounded,
              color: context.brandColor,
              iconColor: Colors.black,
              onTap: onIncrement,
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
