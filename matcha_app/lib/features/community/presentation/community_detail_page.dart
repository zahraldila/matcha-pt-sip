import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../data/community_remote_data_source.dart';
import '../domain/community_model.dart';

class CommunityDetailPage extends StatefulWidget {
  final int communityId;

  const CommunityDetailPage({
    super.key,
    required this.communityId,
  });

  @override
  State<CommunityDetailPage> createState() =>
      _CommunityDetailPageState();
}

class _CommunityDetailPageState extends State<CommunityDetailPage> {
  final CommunityRemoteDataSource _dataSource =
      CommunityRemoteDataSource();

  bool _isLoading = true;
  String? _errorMessage;
  CommunityModel? _community;

  @override
  void initState() {
    super.initState();
    _loadCommunity();
  }

  Future<void> _loadCommunity() async {
    try {
      final community = await _dataSource.getCommunityById(
        widget.communityId,
      );

      if (!mounted) return;

      setState(() {
        _community = community;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _errorMessage = 'Gagal memuat detail community.';
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
          'Community',
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

    if (_errorMessage != null || _community == null) {
      return Center(
        child: Text(
          _errorMessage ?? 'Data community tidak ditemukan.',
          style: AppTextStyles.bodySecondary.copyWith(
            color: context.txtSecondary,
          ),
        ),
      );
    }

    final community = _community!;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildImage(context, community.imageUrl),
          const SizedBox(height: 20),

          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  community.namaCommunity,
                  style: AppTextStyles.pageTitle.copyWith(
                    fontSize: 22,
                    color: context.txtPrimary,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              _buildStatusBadge(
                context,
                community.status,
              ),
            ],
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
            community.deskripsi?.isNotEmpty == true
                ? community.deskripsi!
                : 'Belum ada deskripsi community.',
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
          Icons.diversity_3_rounded,
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
    String status,
  ) {
    final isActive = status.toLowerCase() == 'active';

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: 9,
        vertical: 5,
      ),
      decoration: BoxDecoration(
        color: isActive
            ? context.brandColor.withValues(alpha: 0.15)
            : context.surfSec,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        status.toUpperCase(),
        style: AppTextStyles.badge.copyWith(
          fontSize: 10,
          color: isActive
              ? context.brandColor
              : context.txtSecondary,
        ),
      ),
    );
  }
}