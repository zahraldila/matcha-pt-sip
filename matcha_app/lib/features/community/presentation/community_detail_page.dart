import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../data/community_remote_data_source.dart';
import '../domain/community_model.dart';

class CommunityDetailPage extends StatefulWidget {
  final CommunityModel community;
  final AuthController? authController;

  const CommunityDetailPage({
    super.key,
    required this.community,
    this.authController,
  });

  @override
  State<CommunityDetailPage> createState() => _CommunityDetailPageState();
}

class _CommunityDetailPageState extends State<CommunityDetailPage> {
  final CommunityRemoteDataSource _dataSource = CommunityRemoteDataSource();

  late CommunityModel _community;
  List<Map<String, dynamic>> _members = [];
  bool _isLoading = false;
  bool _isActionLoading = false;

  @override
  void initState() {
    super.initState();
    _community = widget.community;
    _loadDetail();
  }

  Future<void> _loadDetail() async {
    setState(() => _isLoading = true);
    try {
      final user = widget.authController?.currentUser;
      final result = await _dataSource.getCommunityDetail(
        _community.communityId,
        currentUserId: user?.userId,
      );

      if (!mounted) return;
      setState(() {
        _community = result['community'] as CommunityModel;
        _members = result['members'] as List<Map<String, dynamic>>;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
    }
  }

  Future<void> _handleToggleMembership() async {
    final user = widget.authController?.currentUser;
    if (user == null) {
      final loggedIn = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => LoginPage(authController: widget.authController ?? AuthController()),
        ),
      );
      if (loggedIn != true || !mounted) return;
    }

    final currentUser = widget.authController?.currentUser;
    if (currentUser == null) return;

    setState(() => _isActionLoading = true);
    final messenger = ScaffoldMessenger.of(context);

    try {
      if (_community.isMember) {
        await _dataSource.leaveCommunity(
          communityId: _community.communityId,
          userId: currentUser.userId,
        );
        messenger.showSnackBar(
          SnackBar(
            content: Text('Anda telah meninggalkan komunitas "${_community.namaCommunity}".'),
            backgroundColor: const Color(0xFF64748B),
          ),
        );
      } else {
        await _dataSource.joinCommunity(
          communityId: _community.communityId,
          userId: currentUser.userId,
          nama: currentUser.nama,
          noHp: currentUser.noHp,
          email: currentUser.email,
        );
        messenger.showSnackBar(
          SnackBar(
            content: Text('Selamat! Anda telah bergabung ke komunitas "${_community.namaCommunity}" 🎉'),
            backgroundColor: AppColors.matchaDark,
          ),
        );
      }

      await _loadDetail();
    } catch (e) {
      messenger.showSnackBar(
        SnackBar(
          content: Text('Gagal memperbarui status keanggotaan: $e'),
          backgroundColor: Colors.redAccent,
        ),
      );
    } finally {
      if (mounted) setState(() => _isActionLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.authController?.currentUser;
    final isLoggedIn = user != null;
    final isMember = _community.isMember;
    final memberCount = _members.isNotEmpty ? _members.length : _community.memberCount;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 1,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: const Color(0xFFF1F5F9),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.arrow_back_ios_new_rounded, size: 16, color: Color(0xFF0F172A)),
          ),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Detail Komunitas',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
        ),
        centerTitle: true,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : RefreshIndicator(
              onRefresh: _loadDetail,
              color: AppColors.matchaDark,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // 1. Back link & Sport category badge
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: AppColors.matchaSoftLime,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                              ),
                              child: Text(
                                _community.sport.toUpperCase(),
                                style: const TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w900,
                                  color: AppColors.matchaDark,
                                  letterSpacing: 0.5,
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            const Text('•', style: TextStyle(color: Color(0xFF94A3B8))),
                            const SizedBox(width: 8),
                            const Text(
                              'Komunitas Detail',
                              style: TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                            ),
                          ],
                        ),

                        // Active member badge right
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.03),
                                blurRadius: 6,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Container(
                                width: 26,
                                height: 26,
                                decoration: BoxDecoration(
                                  gradient: const LinearGradient(
                                    colors: [AppColors.matchaDark, Color(0xFF064E3B)],
                                  ),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Icon(Icons.groups_rounded, size: 15, color: Color(0xFFA8E63A)),
                              ),
                              const SizedBox(width: 8),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('ANGGOTA AKTIF', style: TextStyle(fontSize: 8, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                                  Text(
                                    '$memberCount Member',
                                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: AppColors.matchaDark),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 14),

                    // Community Header (Logo + Name)
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(18),
                          child: Container(
                            width: 60,
                            height: 60,
                            decoration: BoxDecoration(
                              color: const Color(0xFFE2E8F0),
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: Image.network(
                              _community.displayImage,
                              fit: BoxFit.cover,
                              errorBuilder: (_, error, stackTrace) => const Center(
                                child: Icon(Icons.groups_rounded, size: 30, color: Color(0xFF94A3B8)),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _community.namaCommunity,
                                style: const TextStyle(
                                  fontSize: 22,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF0F172A),
                                  letterSpacing: -0.5,
                                ),
                              ),
                              if (_community.tagline != null && _community.tagline!.isNotEmpty) ...[
                                const SizedBox(height: 2),
                                Text(
                                  _community.tagline!,
                                  style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 20),

                    // CARD 1: TENTANG KOMUNITAS
                    _buildAboutCard(),

                    const SizedBox(height: 16),

                    // CARD 2: DAFTAR ANGGOTA KOMUNITAS
                    _buildMembersCard(),

                    const SizedBox(height: 16),

                    // CARD 3: STATUS KEANGGOTAAN / SIAP BERGABUNG?
                    _buildMembershipCard(isLoggedIn, isMember),

                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
    );
  }

  // --- CARD 1: TENTANG KOMUNITAS ---
  Widget _buildAboutCard() {
    final sportIcon = _community.sport == 'Tennis'
        ? Icons.sports_baseball_rounded
        : _community.sport == 'Padel'
            ? Icons.sports_tennis_rounded
            : Icons.layers_rounded;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.04),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 26,
                height: 26,
                decoration: BoxDecoration(
                  color: AppColors.matchaDark,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.notes_rounded, size: 15, color: Colors.white),
              ),
              const SizedBox(width: 10),
              const Text(
                'TENTANG KOMUNITAS',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), letterSpacing: 0.3),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            _community.deskripsi != null && _community.deskripsi!.isNotEmpty
                ? _community.deskripsi!
                : 'Tidak ada deskripsi komunitas.',
            style: const TextStyle(fontSize: 12, color: Color(0xFF334155), height: 1.5),
          ),
          const SizedBox(height: 16),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 16),

          // Details grid
          Column(
            children: [
              // Sport Row
              Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: AppColors.matchaSoftLime,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(sportIcon, size: 18, color: AppColors.matchaDark),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('CABANG OLAHRAGA UTAMA', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                        const SizedBox(height: 2),
                        Text(
                          _community.sport,
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 12),

              // Schedule Row
              Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: AppColors.matchaSoftLime,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.calendar_month_rounded, size: 18, color: AppColors.matchaDark),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('JADWAL RUTIN MABAR', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: Color(0xFF94A3B8))),
                        const SizedBox(height: 2),
                        Text(
                          _community.jadwalRutin != null && _community.jadwalRutin!.isNotEmpty
                              ? _community.jadwalRutin!
                              : 'Rutin Setiap Pekan',
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  // --- CARD 2: DAFTAR ANGGOTA KOMUNITAS ---
  Widget _buildMembersCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.04),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 26,
                height: 26,
                decoration: BoxDecoration(
                  color: AppColors.matchaDark,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.groups_rounded, size: 15, color: Colors.white),
              ),
              const SizedBox(width: 10),
              const Text(
                'DAFTAR ANGGOTA KOMUNITAS',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), letterSpacing: 0.3),
              ),
            ],
          ),
          const SizedBox(height: 16),

          if (_members.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0), style: BorderStyle.solid),
              ),
              child: const Column(
                children: [
                  Icon(Icons.person_off_rounded, size: 28, color: Color(0xFF94A3B8)),
                  SizedBox(height: 8),
                  Text(
                    'Belum ada anggota bergabung di komunitas ini.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                  ),
                ],
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _members.length,
              separatorBuilder: (_, i) => const SizedBox(height: 10),
              itemBuilder: (context, index) {
                final m = _members[index];
                final name = m['nama'] as String? ?? 'Pemain';
                final initial = name.isNotEmpty ? name[0].toUpperCase() : 'P';
                final level = m['level'] as String?;
                final rating = m['rating'];

                return Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [AppColors.matchaDark, Color(0xFF064E3B)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          initial,
                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w900, color: Colors.white),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              name,
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                            ),
                            if (level != null || rating != null) ...[
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  if (level != null && level.isNotEmpty) ...[
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: AppColors.matchaSoftLime,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        'Level: $level',
                                        style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                  ],
                                  if (rating != null) ...[
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFFEF3C7),
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          const Icon(Icons.star_rounded, size: 10, color: Color(0xFFB45309)),
                                          const SizedBox(width: 2),
                                          Text(
                                            '$rating',
                                            style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
        ],
      ),
    );
  }

  // --- CARD 3: STATUS KEANGGOTAAN / SIAP BERGABUNG ---
  Widget _buildMembershipCard(bool isLoggedIn, bool isMember) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.04),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (isLoggedIn && isMember) ...[
            const Text(
              'STATUS KEANGGOTAAN',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF64748B), letterSpacing: 0.5),
            ),
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                color: const Color(0xFFDCFCE7),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFF86EFAC)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.check_circle_rounded, size: 18, color: Color(0xFF15803D)),
                  SizedBox(width: 8),
                  Text(
                    'Anda adalah Anggota',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF166534)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: OutlinedButton.icon(
                onPressed: _isActionLoading ? null : _handleToggleMembership,
                icon: _isActionLoading
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.redAccent),
                      )
                    : const Icon(Icons.logout_rounded, size: 16, color: Color(0xFFB91C1C)),
                label: Text(
                  _isActionLoading ? 'Memproses...' : 'Keluar dari Komunitas',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFFB91C1C)),
                ),
                style: OutlinedButton.styleFrom(
                  backgroundColor: const Color(0xFFFEF2F2),
                  side: const BorderSide(color: Color(0xFFFECACA)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
              ),
            ),
          ] else if (isLoggedIn && !isMember) ...[
            const Text(
              'SIAP BERGABUNG?',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF64748B), letterSpacing: 0.5),
            ),
            const SizedBox(height: 4),
            const Text(
              'Bergabunglah dengan komunitas ini untuk mengikuti sesi mabar dan turnamen!',
              style: TextStyle(fontSize: 12, color: Color(0xFF334155), height: 1.4),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: ElevatedButton.icon(
                onPressed: _isActionLoading ? null : _handleToggleMembership,
                icon: _isActionLoading
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.person_add_rounded, size: 18, color: Color(0xFFA8E63A)),
                label: Text(
                  _isActionLoading ? 'Memproses...' : 'Bergabung Sekarang',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.matchaDark,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
              ),
            ),
          ] else ...[
            const Text(
              'AKSES KOMUNITAS',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF64748B), letterSpacing: 0.5),
            ),
            const SizedBox(height: 4),
            const Text(
              'Silakan login terlebih dahulu untuk bergabung dengan komunitas ini.',
              style: TextStyle(fontSize: 12, color: Color(0xFF334155), height: 1.4),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: ElevatedButton.icon(
                onPressed: _handleToggleMembership,
                icon: const Icon(Icons.login_rounded, size: 18, color: Color(0xFFA8E63A)),
                label: const Text('Login Terlebih Dahulu', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.matchaDark,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
              ),
            ),
          ],

          const SizedBox(height: 14),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 12),

          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.info_outline_rounded, size: 16, color: AppColors.matchaDark),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'Sebagai anggota, Anda dapat mengikuti sesi mabar, turnamen, dan melihat leaderboard komunitas.',
                  style: TextStyle(fontSize: 11, color: Color(0xFF64748B), height: 1.4),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}