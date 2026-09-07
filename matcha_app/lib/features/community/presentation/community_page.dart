import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../data/community_remote_data_source.dart';
import '../domain/community_model.dart';
import 'community_detail_page.dart';

class CommunityPage extends StatefulWidget {
  const CommunityPage({super.key});

  @override
  State<CommunityPage> createState() => _CommunityPageState();
}

class _CommunityPageState extends State<CommunityPage> {
  final CommunityRemoteDataSource _dataSource =
      CommunityRemoteDataSource();

  bool _isLoading = true;
  String? _errorMessage;
  List<CommunityModel> _communities = [];

  @override
  void initState() {
    super.initState();
    _loadCommunities();
  }

  Future<void> _loadCommunities() async {
    try {
      final communities = await _dataSource.getActiveCommunities();

      if (!mounted) return;

      setState(() {
        _communities = communities;
        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _errorMessage = 'Gagal memuat data community.';
      });
    }
  }

  Future<void> _refresh() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    await _loadCommunities();
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

    if (_errorMessage != null) {
      return _buildErrorState(context);
    }

    if (_communities.isEmpty) {
      return _buildEmptyState(context);
    }

    return RefreshIndicator(
      onRefresh: _refresh,
      color: context.brandColor,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: _communities.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final community = _communities[index];

          return _buildCommunityCard(
            context,
            community,
          );
        },
      ),
    );
  }

  Widget _buildCommunityCard(
    BuildContext context,
    CommunityModel community,
  ) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => CommunityDetailPage(
              communityId: community.communityId,
            ),
          ),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: context.surfBorder,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildCommunityImage(
              context,
              community.imageUrl,
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          community.namaCommunity,
                          style: AppTextStyles.cardTitle.copyWith(
                            fontSize: 16,
                            color: context.txtPrimary,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          community.deskripsi?.isNotEmpty == true
                              ? community.deskripsi!
                              : 'Belum ada deskripsi community.',
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.caption.copyWith(
                            fontSize: 12,
                            height: 1.4,
                            color: context.txtSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  Icon(
                    Icons.chevron_right_rounded,
                    color: context.txtSecondary,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCommunityImage(
    BuildContext context,
    String? imageUrl,
  ) {
    if (imageUrl == null || imageUrl.isEmpty) {
      return Container(
        height: 150,
        width: double.infinity,
        color: context.surfSec,
        child: Icon(
          Icons.diversity_3_rounded,
          size: 48,
          color: context.txtSecondary,
        ),
      );
    }

    return Image.network(
      imageUrl,
      height: 150,
      width: double.infinity,
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) {
        return Container(
          height: 150,
          width: double.infinity,
          color: context.surfSec,
          child: Icon(
            Icons.image_not_supported_outlined,
            size: 40,
            color: context.txtSecondary,
          ),
        );
      },
    );
  }

  Widget _buildEmptyState(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.diversity_3_outlined,
              size: 52,
              color: context.txtSecondary,
            ),
            const SizedBox(height: 12),
            Text(
              'Belum Ada Community',
              style: AppTextStyles.sectionTitle.copyWith(
                color: context.txtPrimary,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Belum ada community aktif yang tersedia.',
              textAlign: TextAlign.center,
              style: AppTextStyles.bodySecondary.copyWith(
                color: context.txtSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildErrorState(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.error_outline_rounded,
              size: 48,
              color: context.txtSecondary,
            ),
            const SizedBox(height: 12),
            Text(
              _errorMessage!,
              textAlign: TextAlign.center,
              style: AppTextStyles.bodySecondary.copyWith(
                color: context.txtSecondary,
              ),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadCommunities,
              child: const Text('COBA LAGI'),
            ),
          ],
        ),
      ),
    );
  }
}