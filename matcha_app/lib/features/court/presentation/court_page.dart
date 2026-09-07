import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../data/court_remote_data_source.dart';
import '../domain/court_model.dart';
import 'court_detail_page.dart';

class CourtPage extends StatefulWidget {
  const CourtPage({super.key});

  @override
  State<CourtPage> createState() => _CourtPageState();
}

class _CourtPageState extends State<CourtPage> {
  final CourtRemoteDataSource _dataSource =
      CourtRemoteDataSource();

  bool _isLoading = true;
  String? _errorMessage;
  List<CourtModel> _courts = [];

  @override
  void initState() {
    super.initState();
    _loadCourts();
  }

  Future<void> _loadCourts() async {
    try {
      final courts = await _dataSource.getActiveCourts();

      if (!mounted) return;

      setState(() {
        _courts = courts;
        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoading = false;
        _errorMessage = 'Gagal memuat data court.';
      });
    }
  }

  Future<void> _refresh() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    await _loadCourts();
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

    if (_errorMessage != null) {
      return _buildErrorState(context);
    }

    if (_courts.isEmpty) {
      return _buildEmptyState(context);
    }

    return RefreshIndicator(
      onRefresh: _refresh,
      color: context.brandColor,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        itemCount: _courts.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final court = _courts[index];

          return _buildCourtCard(
            context,
            court,
          );
        },
      ),
    );
  }

  Widget _buildCourtCard(
    BuildContext context,
    CourtModel court,
  ) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => CourtDetailPage(
              courtId: court.courtId,
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
            _buildCourtImage(
              context,
              court.imageUrl,
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
                          court.namaCourt,
                          style: AppTextStyles.cardTitle.copyWith(
                            fontSize: 16,
                            color: context.txtPrimary,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          court.lokasi?.isNotEmpty == true
                              ? court.lokasi!
                              : 'Lokasi court belum tersedia.',
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

  Widget _buildCourtImage(
    BuildContext context,
    String? imageUrl,
  ) {
    if (imageUrl == null || imageUrl.isEmpty) {
      return Container(
        height: 150,
        width: double.infinity,
        color: context.surfSec,
        child: Icon(
          Icons.sports_tennis_rounded,
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
              Icons.sports_tennis_outlined,
              size: 52,
              color: context.txtSecondary,
            ),
            const SizedBox(height: 12),
            Text(
              'Belum Ada Court',
              style: AppTextStyles.sectionTitle.copyWith(
                color: context.txtPrimary,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Belum ada court aktif yang tersedia.',
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
              onPressed: _loadCourts,
              child: const Text('COBA LAGI'),
            ),
          ],
        ),
      ),
    );
  }
}