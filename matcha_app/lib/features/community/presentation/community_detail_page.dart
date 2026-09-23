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
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => LoginPage(authController: widget.authController),
        ),
      );
      return;
    }

    setState(() => _isActionLoading = true);
    final messenger = ScaffoldMessenger.of(context);

    try {
      if (_community.isMember) {
        await _dataSource.leaveCommunity(
          communityId: _community.communityId,
          userId: user.userId,
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
          userId: user.userId,
          nama: user.nama,
          noHp: user.noHp,
          email: user.email,
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
          content: Text('Gagal mengubah keanggotaan: $e'),
          backgroundColor: Colors.redAccent,
        ),
      );
    } finally {
      if (mounted) setState(() => _isActionLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: const Text(
          'Detail Komunitas',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            color: Color(0xFF0F172A),
          ),
        ),
        centerTitle: true,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 40),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // 1. Featured Header Banner & Logo Card
                  Container(
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(24),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.03),
                          blurRadius: 10,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Cover Photo
                        Stack(
                          children: [
                            SizedBox(
                              height: 140,
                              width: double.infinity,
                              child: Image.network(
                                _community.displayImage,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) => Container(
                                  color: const Color(0xFF063B00),
                                  child: const Center(
                                    child: Icon(Icons.sports_tennis_rounded, color: Color(0xFFA8E63A), size: 36),
                                  ),
                                ),
                              ),
                            ),
                            Positioned(
                              top: 12,
                              left: 12,
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                decoration: BoxDecoration(
                                  color: AppColors.matchaSoftLime,
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                                ),
                                child: Text(
                                  _community.sport,
                                  style: const TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                    color: AppColors.matchaDark,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        Padding(
                          padding: const EdgeInsets.all(18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _community.namaCommunity,
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF0F172A),
                                ),
                              ),
                              if (_community.tagline != null && _community.tagline!.isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  _community.tagline!,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontStyle: FontStyle.italic,
                                    color: Color(0xFF64748B),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 12),
                              Row(
                                children: [
                                  const Icon(Icons.people_alt_rounded, size: 14, color: AppColors.matchaDark),
                                  const SizedBox(width: 4),
                                  Text(
                                    '${_community.memberCount} Anggota',
                                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                  ),
                                  const SizedBox(width: 12),
                                  const Icon(Icons.location_on_rounded, size: 14, color: Color(0xFF64748B)),
                                  const SizedBox(width: 4),
                                  Expanded(
                                    child: Text(
                                      _community.kotaHomebase ?? 'Bandung',
                                      style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 16),
                              SizedBox(
                                width: double.infinity,
                                height: 42,
                                child: ElevatedButton(
                                  onPressed: _isActionLoading ? null : _handleToggleMembership,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: _community.isMember ? const Color(0xFFF1F5F9) : AppColors.matchaDark,
                                    foregroundColor: _community.isMember ? const Color(0xFF475569) : Colors.white,
                                    elevation: 0,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                  ),
                                  child: _isActionLoading
                                      ? const SizedBox(
                                          width: 18,
                                          height: 18,
                                          child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.matchaDark),
                                        )
                                      : Row(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          children: [
                                            Icon(
                                              _community.isMember ? Icons.check_circle_rounded : Icons.group_add_rounded,
                                              size: 16,
                                              color: _community.isMember ? const Color(0xFF16A34A) : Colors.white,
                                            ),
                                            const SizedBox(width: 8),
                                            Text(
                                              _community.isMember ? 'Tergabung (Anggota)' : 'Gabung Komunitas',
                                              style: TextStyle(
                                                fontWeight: FontWeight.bold,
                                                fontSize: 13,
                                                color: _community.isMember ? const Color(0xFF16A34A) : Colors.white,
                                              ),
                                            ),
                                          ],
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

                  // 2. Deskripsi & Info Box
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Tentang Komunitas',
                          style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          _community.deskripsi ?? 'Belum ada deskripsi untuk komunitas ini.',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF475569), height: 1.5),
                        ),
                        const SizedBox(height: 16),
                        const Divider(height: 1, color: Color(0xFFF1F5F9)),
                        const SizedBox(height: 14),
                        _buildInfoRow(Icons.person_pin_rounded, 'Pengelola / Admin', _community.adminName),
                        const SizedBox(height: 10),
                        _buildInfoRow(Icons.calendar_month_rounded, 'Jadwal Rutin', _community.jadwalRutin ?? 'Setiap Pekan'),
                        const SizedBox(height: 10),
                        _buildInfoRow(Icons.stadium_rounded, 'Homebase Venue', _community.homebaseVenue ?? 'Venue Mitra Matcha'),
                        const SizedBox(height: 10),
                        _buildInfoRow(Icons.military_tech_rounded, 'Target Level', _community.targetLevel ?? 'All Levels'),
                      ],
                    ),
                  ),

                  const SizedBox(height: 16),

                  // 3. Daftar Anggota / Players
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Daftar Anggota',
                              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Text(
                                '${_members.length} Orang',
                                style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        if (_members.isEmpty)
                          const Text('Belum ada anggota terdaftar.', style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8)))
                        else
                          ListView.separated(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: _members.length,
                            separatorBuilder: (_, i) => const Divider(height: 12, color: Color(0xFFF8FAFC)),
                            itemBuilder: (context, index) {
                              final m = _members[index];
                              final name = m['nama'] as String? ?? 'Pemain';
                              final level = m['level'] as String? ?? 'Beginner';

                              return Row(
                                children: [
                                  CircleAvatar(
                                    radius: 16,
                                    backgroundColor: AppColors.matchaSoftLime,
                                    child: Text(
                                      name.isNotEmpty ? name[0].toUpperCase() : 'P',
                                      style: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.bold,
                                        color: AppColors.matchaDark,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          name,
                                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                        ),
                                        Text(
                                          'Level: $level',
                                          style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              );
                            },
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, size: 16, color: AppColors.matchaDark),
        const SizedBox(width: 8),
        Text(
          '$label: ',
          style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}