import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../auth/presentation/login_page.dart';
import '../data/community_remote_data_source.dart';
import '../domain/community_model.dart';
import 'community_detail_page.dart';

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

  Future<void> _loadCommunities() async {
    try {
      final user = widget.authController?.currentUser;
      final list = await _dataSource.getCommunities(currentUserId: user?.userId);
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
    return _allCommunities.where((c) {
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
  }

  void _showCreateCommunityModal() {
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

    final nameCtrl = TextEditingController();
    final descCtrl = TextEditingController();
    final cityCtrl = TextEditingController(text: 'Bandung');
    final taglineCtrl = TextEditingController();
    String selectedSport = 'all_racquet';
    String membershipStatus = 'Open';
    bool isSubmitting = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (modalCtx) {
        return StatefulBuilder(
          builder: (ctx, setModalState) {
            return Padding(
              padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
              child: Container(
                padding: const EdgeInsets.fromLTRB(22, 20, 22, 28),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(8),
                                decoration: BoxDecoration(
                                  color: AppColors.matchaSoftLime,
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: const Icon(
                                  Icons.groups_rounded,
                                  size: 20,
                                  color: AppColors.matchaDark,
                                ),
                              ),
                              const SizedBox(width: 10),
                              const Text(
                                'Buat Komunitas Baru',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF0F172A),
                                ),
                              ),
                            ],
                          ),
                          IconButton(
                            onPressed: () => Navigator.pop(modalCtx),
                            icon: const Icon(Icons.close_rounded, color: Color(0xFF94A3B8), size: 20),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      _buildFieldLabel('Nama Komunitas / Klub *'),
                      const SizedBox(height: 6),
                      TextField(
                        controller: nameCtrl,
                        decoration: _buildInputDecoration('Contoh: Bandung Padel Society'),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _buildFieldLabel('Cabang Olahraga'),
                                const SizedBox(height: 6),
                                DropdownButtonFormField<String>(
                                  initialValue: selectedSport,
                                  decoration: _buildInputDecoration(''),
                                  items: const [
                                    DropdownMenuItem(value: 'all_racquet', child: Text('Padel & Tennis')),
                                    DropdownMenuItem(value: 'padel', child: Text('Padel Only')),
                                    DropdownMenuItem(value: 'tennis', child: Text('Tennis Only')),
                                  ],
                                  onChanged: (v) {
                                    if (v != null) setModalState(() => selectedSport = v);
                                  },
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _buildFieldLabel('Kota / Homebase'),
                                const SizedBox(height: 6),
                                TextField(
                                  controller: cityCtrl,
                                  decoration: _buildInputDecoration('Bandung'),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _buildFieldLabel('Tagline / Slogan'),
                      const SizedBox(height: 6),
                      TextField(
                        controller: taglineCtrl,
                        decoration: _buildInputDecoration('Contoh: Komunitas Mabar Seru Setiap Weekend'),
                      ),
                      const SizedBox(height: 12),
                      _buildFieldLabel('Deskripsi Komunitas *'),
                      const SizedBox(height: 6),
                      TextField(
                        controller: descCtrl,
                        maxLines: 3,
                        decoration: _buildInputDecoration('Jelaskan tujuan dan aktivitas komunitasmu...'),
                      ),
                      const SizedBox(height: 20),
                      SizedBox(
                        width: double.infinity,
                        height: 46,
                        child: ElevatedButton(
                          onPressed: isSubmitting
                              ? null
                              : () async {
                                  if (nameCtrl.text.trim().isEmpty || descCtrl.text.trim().isEmpty) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(
                                        content: Text('Nama dan deskripsi komunitas wajib diisi'),
                                        backgroundColor: Colors.orange,
                                      ),
                                    );
                                    return;
                                  }

                                  setModalState(() => isSubmitting = true);
                                  try {
                                    final newCom = await _dataSource.createCommunity(
                                      namaCommunity: nameCtrl.text.trim(),
                                      deskripsi: descCtrl.text.trim(),
                                      sport: selectedSport,
                                      tagline: taglineCtrl.text.trim(),
                                      kotaHomebase: cityCtrl.text.trim(),
                                      statusKeanggotaan: membershipStatus,
                                      createdBy: user.userId,
                                      creatorName: user.nama,
                                    );

                                    if (!modalCtx.mounted) return;
                                    Navigator.pop(modalCtx);
                                    await _loadCommunities();

                                    if (!mounted) return;
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text('Komunitas "${newCom.namaCommunity}" berhasil dibuat! 🎉'),
                                        backgroundColor: AppColors.matchaDark,
                                      ),
                                    );
                                  } catch (e) {
                                    setModalState(() => isSubmitting = false);
                                    if (!mounted) return;
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text('Gagal membuat komunitas: $e'),
                                        backgroundColor: Colors.redAccent,
                                      ),
                                    );
                                  }
                                },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.matchaDark,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          ),
                          child: isSubmitting
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                )
                              : const Text('Buat & Publikasikan Komunitas', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildFieldLabel(String label) {
    return Text(
      label,
      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
    );
  }

  InputDecoration _buildInputDecoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
      filled: true,
      fillColor: const Color(0xFFF8FAFC),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _filteredCommunities;
    final totalCount = _allCommunities.length;
    final myCount = _allCommunities.where((c) => c.isMember).length;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: RefreshIndicator(
        onRefresh: _loadCommunities,
        color: AppColors.matchaDark,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Header Hero Badge
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

              // 2. Button Buat Komunitas Baru
              SizedBox(
                width: double.infinity,
                height: 44,
                child: ElevatedButton.icon(
                  onPressed: _showCreateCommunityModal,
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
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          _buildSportPill('all', 'Semua Cabang', null),
                          const SizedBox(width: 8),
                          _buildSportPill('Tennis', 'Tennis', '🎾'),
                          const SizedBox(width: 8),
                          _buildSportPill('Padel', 'Padel', '🏓'),
                        ],
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'Menampilkan ${filtered.length} komunitas',
                      style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
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
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.red.shade200),
                  ),
                  child: Column(
                    children: [
                      const Icon(Icons.error_outline_rounded, size: 32, color: Colors.redAccent),
                      const SizedBox(height: 8),
                      Text(
                        'Gagal memuat komunitas: $_errorMessage',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 12, color: Colors.redAccent),
                      ),
                      const SizedBox(height: 12),
                      ElevatedButton(
                        onPressed: _loadCommunities,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.matchaDark,
                          foregroundColor: Colors.white,
                        ),
                        child: const Text('Coba Lagi'),
                      ),
                    ],
                  ),
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
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF063B00) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected ? const Color(0xFF063B00) : const Color(0xFFE2E8F0),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (emoji != null) ...[
              Text(emoji, style: const TextStyle(fontSize: 10)),
              const SizedBox(width: 4),
            ],
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.w600,
                color: isSelected ? Colors.white : const Color(0xFF475569),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCommunityCard(CommunityModel com) {
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
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
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
          // 1. Image Header with Sport Badge
          Stack(
            children: [
              SizedBox(
                height: 140,
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
              Positioned(
                top: 10,
                left: 10,
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
                  height: 38,
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => CommunityDetailPage(
                            community: com,
                            authController: widget.authController,
                          ),
                        ),
                      ).then((_) => _loadCommunities());
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF063B00),
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text(
                      'Detail Komunitas',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}