import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../data/court_remote_data_source.dart';
import '../domain/court_model.dart';

class CourtDetailPage extends StatefulWidget {
  final int courtId;

  const CourtDetailPage({
    super.key,
    required this.courtId,
  });

  @override
  State<CourtDetailPage> createState() => _CourtDetailPageState();
}

class _CourtDetailPageState extends State<CourtDetailPage> {
  final CourtRemoteDataSource _dataSource =
      CourtRemoteDataSource();

  bool _isLoading = true;
  String? _errorMessage;
  CourtModel? _court;

  @override
  void initState() {
    super.initState();
    _loadCourt();
  }

  Future<void> _loadCourt() async {
    try {
      final court = await _dataSource.getCourtById(
        widget.courtId,
      );

      if (!mounted) return;

      setState(() {
        _court = court;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _errorMessage = 'Gagal memuat detail court.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.bg,
      appBar: AppBar(
        backgroundColor: context.bg,
        elevation: 0,
        leading: IconButton(
          icon: Icon(
            Icons.arrow_back_rounded,
            color: context.txtPrimary,
          ),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Court',
          style: AppTextStyles.pageTitle.copyWith(
            fontSize: 20,
            color: context.txtPrimary,
          ),
        ),
      ),
      body: _buildBody(context),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_isLoading) {
      return Center(
        child: CircularProgressIndicator(
          color: context.brandColor,
        ),
      );
    }

    if (_errorMessage != null || _court == null) {
      return Center(
        child: Text(
          _errorMessage ?? 'Data court tidak ditemukan.',
          style: AppTextStyles.bodySecondary.copyWith(
            color: context.txtSecondary,
          ),
        ),
      );
    }

    final court = _court!;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildImage(context, court.imageUrl),
          const SizedBox(height: 20),

          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  court.namaCourt,
                  style: AppTextStyles.pageTitle.copyWith(
                    fontSize: 22,
                    color: context.txtPrimary,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              _buildStatusBadge(
                context,
                court.statusKetersediaan,
              ),
            ],
          ),

          const SizedBox(height: 24),

          Text(
            'Lokasi',
            style: AppTextStyles.sectionTitle.copyWith(
              fontSize: 17,
              color: context.txtPrimary,
            ),
          ),

          const SizedBox(height: 8),

          Text(
            court.lokasi?.isNotEmpty == true
                ? court.lokasi!
                : 'Lokasi court belum tersedia.',
            style: AppTextStyles.bodySecondary.copyWith(
              fontSize: 14,
              height: 1.6,
              color: context.txtSecondary,
            ),
          ),

          const SizedBox(height: 24),

          Text(
            'Deskripsi',
            style: AppTextStyles.sectionTitle.copyWith(
              fontSize: 17,
              color: context.txtPrimary,
            ),
          ),

          const SizedBox(height: 8),

          Text(
            court.deskripsi?.isNotEmpty == true
                ? court.deskripsi!
                : 'Belum ada deskripsi court.',
            style: AppTextStyles.bodySecondary.copyWith(
              fontSize: 14,
              height: 1.6,
              color: context.txtSecondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildImage(
    BuildContext context,
    String? imageUrl,
  ) {
    if (imageUrl == null || imageUrl.isEmpty) {
      return Container(
        height: 210,
        width: double.infinity,
        decoration: BoxDecoration(
          color: context.surfSec,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Icon(
          Icons.sports_tennis_rounded,
          size: 64,
          color: context.txtSecondary,
        ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: Image.network(
        imageUrl,
        height: 210,
        width: double.infinity,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) {
          return Container(
            height: 210,
            width: double.infinity,
            color: context.surfSec,
            child: Icon(
              Icons.image_not_supported_outlined,
              size: 52,
              color: context.txtSecondary,
            ),
          );
        },
      ),
    );
  }

  Widget _buildStatusBadge(
    BuildContext context,
    String? status,
  ) {
    final statusText = status?.isNotEmpty == true
        ? status!
        : 'Tidak tersedia';

    final isAvailable =
        statusText.toLowerCase() == 'tersedia';

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: 9,
        vertical: 5,
      ),
      decoration: BoxDecoration(
        color: isAvailable
            ? context.brandColor.withValues(alpha: 0.15)
            : context.surfSec,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        statusText.toUpperCase(),
        style: AppTextStyles.badge.copyWith(
          fontSize: 10,
          color: isAvailable
              ? context.brandColor
              : context.txtSecondary,
        ),
      ),
    );
  }
}