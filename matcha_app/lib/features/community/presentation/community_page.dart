import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/offline_state_widget.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../data/community_remote_data_source.dart';
import '../domain/community_model.dart';
import 'community_detail_page.dart';
import 'create_community_page.dart';

class CommunityPage extends StatefulWidget {
  final AuthController? authController;

  const CommunityPage({super.key, this.authController});

  @override
  State<CommunityPage> createState() => _CommunityPageState();
}

class _CommunityPageState extends State<CommunityPage> {
  final CommunityRemoteDataSource _dataSource = CommunityRemoteDataSource();
  final TextEditingController _searchController = TextEditingController();

  List<CommunityModel> _allCommunities = [];
  bool _isLoading = true;
  String? _errorMessage;

  bool _isSelectionMode = false;
  final Set<int> _selectedCommunityIds = {};
  bool _isBulkDeleting = false;

  // Filters
  String _activeTab = 'all'; // 'all' or 'joined'
  String _selectedSport = 'all'; // 'all', 'Tennis', 'Padel'

  @override
  void initState() {
    super.initState();
    _loadCommunities();
    widget.authController?.addListener(_onAuthChanged);
  }

  @override
  void dispose() {
    widget.authController?.removeListener(_onAuthChanged);
    _searchController.dispose();
    super.dispose();
  }

  void _onAuthChanged() {
    if (mounted) _loadCommunities();
  }

  bool _canDeleteCommunity(CommunityModel c) {
    final user = widget.authController?.currentUser;
    if (user == null) return false;
    if (user.role.toLowerCase().contains('admin') || user.isAdmin) return true;
    if (c.createdBy != null && c.createdBy == user.userId) return true;
    if (c.adminName.trim().toLowerCase() == user.nama.trim().toLowerCase()) return true;
    return false;
  }

  bool get _canEnterSelectionMode {
    final user = widget.authController?.currentUser;
    if (user == null) return false;
    return user.role.toLowerCase().contains('admin') || user.isAdmin;
  }

  void _toggleSelectionMode() {
    if (!_isSelectionMode && !_canEnterSelectionMode) return;
    setState(() {
      _isSelectionMode = !_isSelectionMode;
      _selectedCommunityIds.clear();
    });
  }

  void _toggleSelectCommunity(int id) {
    setState(() {
      if (_selectedCommunityIds.contains(id)) {
        _selectedCommunityIds.remove(id);
      } else {
        _selectedCommunityIds.add(id);
      }
    });
  }

  void _toggleSelectAll() {
    final deletableIds = _filteredCommunities.where(_canDeleteCommunity).map((c) => c.communityId).toSet();
    setState(() {
      if (_selectedCommunityIds.length == deletableIds.length) {
        _selectedCommunityIds.clear();
      } else {
        _selectedCommunityIds.addAll(deletableIds);
      }
    });
  }

  Future<void> _handleBulkDelete() async {
    if (_selectedCommunityIds.isEmpty) return;

    final count = _selectedCommunityIds.length;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        child: Padding(
          padding: const EdgeInsets.all(22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFE4E6),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFFDA4AF)),
                ),
                child: const Icon(
                  Icons.delete_sweep_rounded,
                  color: Color(0xFFE11D48),
                  size: 26,
                ),
              ),
              const SizedBox(height: 14),
              Text(
                'Hapus $count Komunitas?',
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                'Sebanyak $count komunitas yang dipilih akan dihapus secara permanen dari database. Tindakan ini tidak dapat dibatalkan.',
                style: const TextStyle(
                  fontSize: 12.5,
                  color: Color(0xFF64748B),
                  height: 1.45,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(ctx, false),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF475569),
                        side: const BorderSide(color: Color(0xFFCBD5E1)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        padding: const EdgeInsets.symmetric(vertical: 11),
                      ),
                      child: const Text('Batal', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => Navigator.pop(ctx, true),
                      icon: const Icon(Icons.delete_forever_rounded, size: 16),
                      label: const Text('Hapus', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFE11D48),
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        padding: const EdgeInsets.symmetric(vertical: 11),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _isBulkDeleting = true);
    final messenger = ScaffoldMessenger.of(context);

    int successCount = 0;
    final idsToDelete = _selectedCommunityIds.toList();

    for (final id in idsToDelete) {
      try {
        await _dataSource.deleteCommunity(id);
        successCount++;
      } catch (e) {
        debugPrint('Error deleting community $id: $e');
      }
    }

    if (!mounted) return;
    setState(() {
      _isBulkDeleting = false;
      _isSelectionMode = false;
      _selectedCommunityIds.clear();
    });

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          successCount == count
              ? 'Berhasil menghapus $count komunitas.'
              : (successCount > 0
                  ? 'Berhasil menghapus $successCount dari $count komunitas.'
                  : 'Gagal menghapus komunitas terpilih.'),
        ),
        backgroundColor: successCount > 0 ? AppColors.matchaDark : const Color(0xFFE11D48),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );

    await _loadCommunities();
  }

  Widget _buildBulkActionBar(int deletableCount) {
    final selectedCount = _selectedCommunityIds.length;
    final isAllSelected = deletableCount > 0 && selectedCount == deletableCount;

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
        border: const Border(
          top: BorderSide(color: Color(0xFFE2E8F0)),
        ),
      ),
      child: SafeArea(
        top: false,
        child: Row(
          children: [
            // Cancel / Undo Selection Button
            InkWell(
              onTap: _toggleSelectionMode,
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFCBD5E1)),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.close_rounded, size: 15, color: Color(0xFF475569)),
                    SizedBox(width: 4),
                    Text(
                      'Batal',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF475569),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 6),

            // Select All Checkbox
            InkWell(
              onTap: _toggleSelectAll,
              borderRadius: BorderRadius.circular(10),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      isAllSelected ? Icons.check_box_rounded : Icons.check_box_outline_blank_rounded,
                      size: 17,
                      color: isAllSelected ? const Color(0xFF063B00) : const Color(0xFF64748B),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      isAllSelected ? 'Batal Semua' : 'Pilih Semua',
                      style: const TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF334155),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 6),

            // Counter Badge
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 7),
              decoration: BoxDecoration(
                color: const Color(0xFFEBF8D8),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFF86EFAC)),
              ),
              child: Text(
                '$selectedCount',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF063B00),
                ),
              ),
            ),
            const Spacer(),

            // Delete Button
            ElevatedButton.icon(
              onPressed: (selectedCount == 0 || _isBulkDeleting) ? null : _handleBulkDelete,
              icon: _isBulkDeleting
                  ? const SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.delete_sweep_rounded, size: 15),
              label: Text(
                'Hapus ($selectedCount)',
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFE11D48),
                foregroundColor: Colors.white,
                disabledBackgroundColor: const Color(0xFFFDA4AF).withValues(alpha: 0.5),
                disabledForegroundColor: Colors.white70,
                elevation: 0,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _loadCommunities() async {
    try {
      final user = widget.authController?.currentUser;
      final list = await _dataSource.getCommunities(currentUserId: user?.userId);
      list.sort((a, b) => a.namaCommunity.toLowerCase().compareTo(b.namaCommunity.toLowerCase()));
      if (!mounted) return;
      setState(() {
        _allCommunities = list;
        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  List<CommunityModel> get _filteredCommunities {
    final query = _searchController.text.trim().toLowerCase();
    final list = _allCommunities.where((c) {
      // Tab filter
      if (_activeTab == 'joined' && !c.isMember) return false;

      // Sport filter
      if (_selectedSport != 'all') {
        final cSport = c.sport.toLowerCase();
        final sSport = _selectedSport.toLowerCase();
        if (!cSport.contains(sSport)) return false;
      }

      // Search query
      if (query.isNotEmpty) {
        final nameMatch = c.namaCommunity.toLowerCase().contains(query);
        final cityMatch = (c.kotaHomebase ?? '').toLowerCase().contains(query);
        final adminMatch = c.adminName.toLowerCase().contains(query);
        final descMatch = (c.deskripsi ?? '').toLowerCase().contains(query);
        final taglineMatch = (c.tagline ?? '').toLowerCase().contains(query);
        if (!nameMatch && !cityMatch && !adminMatch && !descMatch && !taglineMatch) {
          return false;
        }
      }

      return true;
    }).toList();

    list.sort((a, b) => a.namaCommunity.toLowerCase().compareTo(b.namaCommunity.toLowerCase()));
    return list;
  }

  Future<void> _navigateToCreateCommunity() async {
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
    if (currentUser != null && currentUser.isAdmin) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Akses dibatasi: Akun Administrator tidak dapat membuat komunitas.'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final created = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => CreateCommunityPage(authController: widget.authController),
      ),
    );

    if (created == true) {
      _loadCommunities();
    }
  }

  @override
  Widget build(BuildContext context) {
    final isGuest = widget.authController?.currentUser == null;
    final filtered = _filteredCommunities;
    final totalCount = _allCommunities.length;
    final myCount = _allCommunities.where((c) => c.isMember).length;
    final deletableCount = filtered.where(_canDeleteCommunity).length;

    return PopScope(
      canPop: !_isSelectionMode,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop && _isSelectionMode) {
          _toggleSelectionMode();
        }
      },
      child: Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        bottomNavigationBar: _isSelectionMode ? _buildBulkActionBar(deletableCount) : null,
        body: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () {
            if (_isSelectionMode) {
              _toggleSelectionMode();
            }
          },
          child: RefreshIndicator(
            onRefresh: _loadCommunities,
            color: AppColors.matchaDark,
            child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Header Hero Badge & Selection Button
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.matchaSoftLime,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 6,
                          height: 6,
                          decoration: const BoxDecoration(
                            color: AppColors.matchaDark,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 6),
                        const Text(
                          'Klub & Ekosistem Olahraga',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: AppColors.matchaDark,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (_canEnterSelectionMode)
                    InkWell(
                      onTap: _toggleSelectionMode,
                      borderRadius: BorderRadius.circular(12),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                        decoration: BoxDecoration(
                          color: _isSelectionMode ? const Color(0xFFE11D48) : Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: _isSelectionMode ? const Color(0xFFE11D48) : const Color(0xFFCBD5E1),
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              _isSelectionMode ? Icons.close_rounded : Icons.checklist_rtl_rounded,
                              size: 14,
                              color: _isSelectionMode ? Colors.white : const Color(0xFF475569),
                            ),
                            const SizedBox(width: 4),
                            Text(
                              _isSelectionMode ? 'Batal' : 'Pilih',
                              style: TextStyle(
                                fontSize: 11.5,
                                fontWeight: FontWeight.bold,
                                color: _isSelectionMode ? Colors.white : const Color(0xFF475569),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),

              const SizedBox(height: 8),

              // Title & Subtitle
              const Text(
                'Komunitas Tennis & Padel',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Gabung bersama komunitas pecinta olahraga raket, perluas koneksi tanding, atau bangun komunitasmu sendiri.',
                style: TextStyle(
                  fontSize: 12,
                  color: Color(0xFF64748B),
                  height: 1.4,
                ),
              ),

              const SizedBox(height: 14),

              // 2. Button Buat Komunitas Baru (Khusus Member / Host / Guest)
              if (widget.authController?.currentUser?.isAdmin != true) ...[
                SizedBox(
                  width: double.infinity,
                  height: 44,
                  child: ElevatedButton.icon(
                    onPressed: _navigateToCreateCommunity,
                    icon: const Icon(Icons.add_rounded, size: 18, color: Color(0xFFA8E63A)),
                    label: const Text(
                      'Buat Komunitas Baru',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF063B00),
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
              ] else ...[
                const SizedBox(height: 6),
              ],

              // 3. Tab Filter (Semua Komunitas vs Komunitas Saya)
              Row(
                children: [
                  Expanded(
                    child: _buildMainTab(
                      key: 'all',
                      icon: Icons.groups_rounded,
                      label: 'Semua Komunitas',
                      count: totalCount,
                      isSelected: _activeTab == 'all',
                    ),
                  ),
                  if (!isGuest) ...[
                    const SizedBox(width: 10),
                    Expanded(
                      child: _buildMainTab(
                        key: 'joined',
                        icon: Icons.check_circle_rounded,
                        label: 'Komunitas Saya',
                        count: myCount,
                        isSelected: _activeTab == 'joined',
                      ),
                    ),
                  ],
                ],
              ),

              const SizedBox(height: 12),

              // 4. Search Input Bar
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.02),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: TextField(
                  controller: _searchController,
                  onChanged: (_) => setState(() {}),
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                  decoration: InputDecoration(
                    hintText: 'Cari nama klub, kota, admin...',
                    hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                    prefixIcon: const Icon(Icons.search_rounded, size: 20, color: Color(0xFF94A3B8)),
                    suffixIcon: _searchController.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear_rounded, size: 16, color: Color(0xFF94A3B8)),
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                            },
                          )
                        : null,
                    border: InputBorder.none,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  ),
                ),
              ),

              const SizedBox(height: 12),

              // 5. Sport Filter Pills Card
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.02),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    // Pills — scrollable
                    Expanded(
                      child: SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: [
                            _buildSportPill('all', 'Semua Cabang', null),
                            const SizedBox(width: 8),
                            _buildSportPill('Tennis', '🎾 Tennis', null),
                            const SizedBox(width: 8),
                            _buildSportPill('Padel', '🏓 Padel', null),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    // Counter — right aligned like web
                    Text(
                      '${filtered.length} komunitas',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF64748B),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // 6. Community Cards List
              if (_isLoading)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: CircularProgressIndicator(color: AppColors.matchaDark),
                  ),
                )
              else if (_errorMessage != null)
                OfflineStateWidget(
                  error: _errorMessage,
                  customTitle: 'Gagal Memuat Komunitas',
                  onRetry: _loadCommunities,
                )
              else if (filtered.isEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(32),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      Container(
                        width: 50,
                        height: 50,
                        decoration: const BoxDecoration(
                          color: Color(0xFFF1F5F9),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.search_off_rounded, size: 26, color: Color(0xFF94A3B8)),
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'Tidak Ada Komunitas Ditemukan',
                        style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Coba sesuaikan kata kunci pencarian atau cabang olahraga.',
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
                  itemCount: filtered.length,
                  separatorBuilder: (_, i) => const SizedBox(height: 16),
                  itemBuilder: (context, index) {
                    final com = filtered[index];
                    return _buildCommunityCard(com);
                  },
                ),
            ],
          ),
        ),
      ),
    ),
    ),
    );
  }

  Widget _buildMainTab({
    required String key,
    required IconData icon,
    required String label,
    required int count,
    required bool isSelected,
  }) {
    return InkWell(
      onTap: () => setState(() => _activeTab = key),
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF063B00) : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
          ),
          boxShadow: isSelected
              ? [BoxShadow(color: const Color(0xFF063B00).withValues(alpha: 0.15), blurRadius: 6, offset: const Offset(0, 2))]
              : null,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 15,
              color: isSelected ? const Color(0xFFA8E63A) : const Color(0xFF16A34A),
            ),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: isSelected ? Colors.white : const Color(0xFF0F172A),
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
              decoration: BoxDecoration(
                color: isSelected ? Colors.white.withValues(alpha: 0.2) : const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                '$count',
                style: TextStyle(
                  fontSize: 9,
                  fontWeight: FontWeight.w900,
                  color: isSelected ? Colors.white : const Color(0xFF475569),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSportPill(String key, String label, String? emoji) {
    final isSelected = _selectedSport == key;
    return InkWell(
      onTap: () => setState(() => _selectedSport = key),
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF063B00) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
          ),
          boxShadow: isSelected
              ? [BoxShadow(color: const Color(0xFF063B00).withValues(alpha: 0.15), blurRadius: 4, offset: const Offset(0, 2))]
              : null,
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.w600,
            color: isSelected ? Colors.white : const Color(0xFF475569),
          ),
        ),
      ),
    );
  }

  Widget _buildCommunityCard(CommunityModel com) {
    final canDelete = _canDeleteCommunity(com);
    final isSelected = _selectedCommunityIds.contains(com.communityId);

    void openDetail() {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CommunityDetailPage(
            community: com,
            authController: widget.authController,
          ),
        ),
      ).then((_) => _loadCommunities());
    }

    void handleCardTap() {
      if (_isSelectionMode) {
        if (canDelete) {
          _toggleSelectCommunity(com.communityId);
        }
      } else {
        openDetail();
      }
    }

    void handleCardLongPress() {
      if (_canEnterSelectionMode && !_isSelectionMode && canDelete) {
        setState(() {
          _isSelectionMode = true;
          _selectedCommunityIds.add(com.communityId);
        });
      }
    }

    Color statusBg = const Color(0xFFF1F5F9);
    Color statusColor = const Color(0xFF475569);
    final s = com.statusKeanggotaan.toLowerCase();
    if (s == 'open') {
      statusBg = const Color(0xFFF0FDF4);
      statusColor = const Color(0xFF16A34A);
    } else if (s == 'approval') {
      statusBg = const Color(0xFFFEF3C7);
      statusColor = const Color(0xFFD97706);
    }

    return Container(
      decoration: BoxDecoration(
        color: isSelected ? const Color(0xFFF0FDF4) : Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: isSelected
              ? const Color(0xFF063B00)
              : (_isSelectionMode && canDelete ? const Color(0xFF86EFAC) : const Color(0xFFE2E8F0)),
          width: isSelected ? 2.0 : 1.0,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: handleCardTap,
          onLongPress: handleCardLongPress,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Image Header with Sport Badge & Checkbox
              Stack(
                children: [
                  SizedBox(
                    height: 176,
                    width: double.infinity,
                    child: Image.network(
                      com.displayImage,
                      fit: BoxFit.cover,
                      errorBuilder: (_, _, _) => Container(
                        color: const Color(0xFF063B00),
                        child: const Center(
                          child: Icon(Icons.sports_tennis_rounded, color: Color(0xFFA8E63A), size: 36),
                        ),
                      ),
                    ),
                  ),
                  // Checkbox when in selection mode
                  if (_isSelectionMode)
                    Positioned(
                      top: 10,
                      left: 10,
                      child: InkWell(
                        onTap: canDelete ? () => _toggleSelectCommunity(com.communityId) : null,
                        borderRadius: BorderRadius.circular(8),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 150),
                          width: 26,
                          height: 26,
                          decoration: BoxDecoration(
                            color: isSelected
                                ? const Color(0xFF063B00)
                                : (canDelete
                                    ? Colors.white.withValues(alpha: 0.95)
                                    : const Color(0xFFF1F5F9).withValues(alpha: 0.95)),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: isSelected
                                  ? const Color(0xFF063B00)
                                  : (canDelete ? const Color(0xFF94A3B8) : const Color(0xFFCBD5E1)),
                              width: 1.5,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.15),
                                blurRadius: 4,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: isSelected
                              ? const Icon(Icons.check_rounded, size: 18, color: Color(0xFFA8E63A))
                              : (!canDelete
                                  ? const Icon(Icons.lock_outline_rounded, size: 14, color: Color(0xFF94A3B8))
                                  : null),
                        ),
                      ),
                    ),
                  // Sport badge (top left)
                  Positioned(
                    top: 10,
                    left: _isSelectionMode ? 44 : 10,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppColors.matchaSoftLime,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: AppColors.matchaDark.withValues(alpha: 0.2)),
                      ),
                      child: Text(
                        com.sport,
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: AppColors.matchaDark,
                        ),
                      ),
                    ),
                  ),
                  // Member badge (top right) — like web
                  if (com.isMember)
                    Positioned(
                      top: 10,
                      right: 10,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFF15803D).withValues(alpha: 0.95),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: const Color(0xFF4ADE80).withValues(alpha: 0.4)),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.check_circle_rounded, size: 10, color: Color(0xFFA8E63A)),
                            SizedBox(width: 4),
                            Text('Anggota', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.white)),
                          ],
                        ),
                      ),
                    ),
                ],
              ),

              // 2. Body Details
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      com.namaCommunity,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                        color: Color(0xFF0F172A),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        const Icon(Icons.people_alt_rounded, size: 13, color: Color(0xFF64748B)),
                        const SizedBox(width: 4),
                        Text(
                          '${com.memberCount} Anggota',
                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                        ),
                        const SizedBox(width: 10),
                        const Icon(Icons.location_on_rounded, size: 13, color: Color(0xFF64748B)),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            com.kotaHomebase ?? 'Bandung',
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    if (com.deskripsi != null && com.deskripsi!.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        com.deskripsi!,
                        style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), height: 1.3),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                    const SizedBox(height: 12),
                    const Divider(height: 1, color: Color(0xFFF1F5F9)),
                    const SizedBox(height: 10),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Admin: ${com.adminName}',
                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: statusBg,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            com.statusKeanggotaan,
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.bold,
                              color: statusColor,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      height: 44,
                      child: ElevatedButton(
                        onPressed: openDetail,
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          backgroundColor: const Color(0xFF063B00),
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        child: const Text(
                          'Detail Komunitas',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}