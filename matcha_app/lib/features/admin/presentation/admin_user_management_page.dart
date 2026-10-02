import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../auth/domain/models/user_model.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../data/admin_user_service.dart';

class AdminUserManagementPage extends StatefulWidget {
  final AuthController? authController;

  const AdminUserManagementPage({super.key, this.authController});

  @override
  State<AdminUserManagementPage> createState() => _AdminUserManagementPageState();
}

class _AdminUserManagementPageState extends State<AdminUserManagementPage> {
  final AdminUserService _adminUserService = AdminUserService();
  final TextEditingController _searchController = TextEditingController();

  List<UserModel> _allUsers = [];
  bool _isLoading = true;
  String _selectedRoleFilter = 'all'; // 'all', 'member', 'host', 'venue_owner', 'admin'
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _loadUsers();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadUsers() async {
    setState(() => _isLoading = true);
    try {
      final users = await _adminUserService.getAllUsers();
      if (!mounted) return;
      setState(() {
        _allUsers = users;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal memuat pengguna: $e'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  List<UserModel> get _filteredUsers {
    return _allUsers.where((u) {
      // Role Filter
      if (_selectedRoleFilter != 'all') {
        if (_selectedRoleFilter == 'host') {
          if (!u.isHost && u.role.toLowerCase() != 'host') return false;
        } else if (u.role.toLowerCase() != _selectedRoleFilter.toLowerCase()) {
          return false;
        }
      }

      // Search Query
      if (_searchQuery.isNotEmpty) {
        final q = _searchQuery.toLowerCase();
        final matchName = u.nama.toLowerCase().contains(q);
        final matchEmail = u.email.toLowerCase().contains(q);
        final matchPhone = u.noHp?.toLowerCase().contains(q) ?? false;
        if (!matchName && !matchEmail && !matchPhone) return false;
      }

      return true;
    }).toList();
  }

  int _countByRole(String roleKey) {
    if (roleKey == 'all') return _allUsers.length;
    if (roleKey == 'host') {
      return _allUsers.where((u) => u.isHost || u.role.toLowerCase() == 'host').length;
    }
    return _allUsers.where((u) => u.role.toLowerCase() == roleKey.toLowerCase()).length;
  }

  void _showEditUserModal(UserModel user) {
    String selectedRole = user.role.toLowerCase();
    bool isHostVal = user.isHost;
    String selectedStatus = user.statusUser;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) {
          return Container(
            padding: EdgeInsets.fromLTRB(20, 16, 20, MediaQuery.of(context).viewInsets.bottom + 24),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: const Color(0xFFCBD5E1),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Edit Peran & Status Pengguna',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          user.nama,
                          style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                    IconButton(
                      icon: const Icon(Icons.close_rounded, color: Color(0xFF64748B)),
                      onPressed: () => Navigator.pop(context),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                const SizedBox(height: 14),

                // Pilih Role
                const Text(
                  'Role Pengguna',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 6),
                DropdownButtonFormField<String>(
                  initialValue: selectedRole,
                  decoration: InputDecoration(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'member', child: Text('Member (Pemain Biasa)', style: TextStyle(fontSize: 13))),
                    DropdownMenuItem(value: 'host', child: Text('Host (Pembuat Sesi/Mabar)', style: TextStyle(fontSize: 13))),
                    DropdownMenuItem(value: 'venue_owner', child: Text('Venue Owner (Pemilik Lapangan)', style: TextStyle(fontSize: 13))),
                    DropdownMenuItem(value: 'admin', child: Text('Administrator (Akses Global)', style: TextStyle(fontSize: 13))),
                  ],
                  onChanged: (val) {
                    if (val != null) {
                      setModalState(() {
                        selectedRole = val;
                        if (val == 'host') isHostVal = true;
                      });
                    }
                  },
                ),
                const SizedBox(height: 14),

                // Mode Host Switch
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Izin Buka Sesi Mabar (Mode Host)',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                    ),
                    Switch(
                      value: isHostVal,
                      activeTrackColor: AppColors.matchaDark,
                      onChanged: (val) {
                        setModalState(() => isHostVal = val);
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 10),

                // Status User
                const Text(
                  'Status Akun',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 6),
                DropdownButtonFormField<String>(
                  initialValue: selectedStatus,
                  decoration: InputDecoration(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'Active', child: Text('Active (Akun Aktif)', style: TextStyle(fontSize: 13))),
                    DropdownMenuItem(value: 'Inactive', child: Text('Inactive (Dinonaktifkan)', style: TextStyle(fontSize: 13, color: Colors.red))),
                  ],
                  onChanged: (val) {
                    if (val != null) {
                      setModalState(() => selectedStatus = val);
                    }
                  },
                ),
                const SizedBox(height: 20),

                // Save button
                SizedBox(
                  width: double.infinity,
                  height: 44,
                  child: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.matchaDark,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: () async {
                      final messenger = ScaffoldMessenger.of(context);
                      Navigator.pop(context);
                      try {
                        await _adminUserService.updateUserRoleAndStatus(
                          userId: user.userId,
                          role: selectedRole,
                          isHost: isHostVal,
                          statusUser: selectedStatus,
                        );
                        if (!mounted) return;
                        messenger.showSnackBar(
                          SnackBar(
                            content: Text('Peran akun "${user.nama}" berhasil diperbarui.'),
                            backgroundColor: AppColors.matchaDark,
                          ),
                        );
                        _loadUsers();
                      } catch (e) {
                        if (!mounted) return;
                        messenger.showSnackBar(
                          SnackBar(
                            content: Text('Gagal memperbarui pengguna: $e'),
                            backgroundColor: Colors.redAccent,
                          ),
                        );
                      }
                    },
                    child: const Text('Simpan Perubahan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Future<void> _handleToggleUserStatus(UserModel user) async {
    final isCurrentlyActive = user.statusUser.toLowerCase() != 'inactive';
    final actionText = isCurrentlyActive ? 'Nonaktifkan' : 'Aktifkan Kembali';

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text('$actionText Akun?', style: const TextStyle(fontWeight: FontWeight.bold)),
        content: Text(
          isCurrentlyActive
              ? 'Akun "${user.nama}" (${user.email}) akan dinonaktifkan. Pengguna tidak dapat login, namun histori pertandingan dan skor tetap aman di database.'
              : 'Aktifkan kembali akun "${user.nama}" agar pengguna dapat login kembali ke Matcha.',
          style: const TextStyle(fontSize: 13, color: Color(0xFF475569)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: isCurrentlyActive ? const Color(0xFFDC2626) : AppColors.matchaDark,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(actionText),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      if (isCurrentlyActive) {
        await _adminUserService.deactivateUser(user.userId);
      } else {
        await _adminUserService.activateUser(user.userId);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Status akun "${user.nama}" berhasil diubah menjadi ${isCurrentlyActive ? "Inactive" : "Active"}.'),
          backgroundColor: AppColors.matchaDark,
        ),
      );
      _loadUsers();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gagal mengubah status akun: $e'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _filteredUsers;

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
          'Manajemen Pengguna',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Color(0xFF64748B)),
            onPressed: _loadUsers,
            tooltip: 'Segarkan data',
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : RefreshIndicator(
              onRefresh: _loadUsers,
              color: AppColors.matchaDark,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
                children: [
                  // 1. Header Banner Administrator Panel
                  _buildHeaderBanner(),
                  const SizedBox(height: 16),

                  // 2. Role Filter Tabs (Pills)
                  _buildRoleFilterPills(),
                  const SizedBox(height: 14),

                  // 3. Search Bar
                  _buildSearchBar(),
                  const SizedBox(height: 12),

                  // 4. Counter info
                  Text(
                    'Menampilkan ${filtered.length} pengguna',
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF64748B)),
                  ),
                  const SizedBox(height: 10),

                  // 5. User List
                  if (filtered.isEmpty)
                    Center(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 40),
                        child: Column(
                          children: const [
                            Icon(Icons.person_off_outlined, size: 48, color: Color(0xFFCBD5E1)),
                            SizedBox(height: 8),
                            Text(
                              'Tidak ada pengguna yang cocok',
                              style: TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                      ),
                    )
                  else
                    ...List.generate(filtered.length, (idx) {
                      final user = filtered[idx];
                      return _buildUserCard(user, idx + 1);
                    }),
                ],
              ),
            ),
    );
  }

  Widget _buildHeaderBanner() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            color: const Color(0xFFDCFCE7),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFF86EFAC)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 6,
                height: 6,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: Color(0xFF16A34A),
                ),
              ),
              const SizedBox(width: 6),
              const Text(
                'Administrator Panel',
                style: TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF15803D),
                  letterSpacing: 0.3,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Manajemen Pengguna',
          style: TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.w900,
            color: Color(0xFF0F172A),
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Daftar seluruh akun pengguna terdaftar di aplikasi Matcha beserta pengelolaan data dan peran.',
          style: TextStyle(fontSize: 12.5, color: Color(0xFF64748B), height: 1.4),
        ),
      ],
    );
  }

  Widget _buildRoleFilterPills() {
    final filters = [
      {'key': 'all', 'label': 'Semua Role', 'count': _countByRole('all'), 'icon': Icons.people_alt_rounded},
      {'key': 'member', 'label': 'Member', 'count': _countByRole('member'), 'icon': Icons.person_outline_rounded},
      {'key': 'host', 'label': 'Host', 'count': _countByRole('host'), 'icon': Icons.bolt_rounded},
      {'key': 'venue_owner', 'label': 'Venue Owner', 'count': _countByRole('venue_owner'), 'icon': Icons.storefront_rounded},
      {'key': 'admin', 'label': 'Admin', 'count': _countByRole('admin'), 'icon': Icons.shield_outlined},
    ];

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      child: Row(
        children: filters.map((f) {
          final isSelected = _selectedRoleFilter == f['key'];
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: InkWell(
              onTap: () {
                setState(() => _selectedRoleFilter = f['key'] as String);
              },
              borderRadius: BorderRadius.circular(20),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: isSelected ? AppColors.matchaDark : Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                  ),
                  boxShadow: isSelected
                      ? [
                          BoxShadow(
                            color: AppColors.matchaDark.withValues(alpha: 0.15),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ]
                      : null,
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      f['icon'] as IconData,
                      size: 14,
                      color: isSelected ? const Color(0xFFA8E63A) : const Color(0xFF64748B),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      f['label'] as String,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                        color: isSelected ? Colors.white : const Color(0xFF334155),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: isSelected ? Colors.white.withValues(alpha: 0.2) : const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        '${f['count']}',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          color: isSelected ? Colors.white : const Color(0xFF64748B),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildSearchBar() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: TextField(
        controller: _searchController,
        onChanged: (val) => setState(() => _searchQuery = val.trim()),
        style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
        decoration: InputDecoration(
          hintText: 'Cari nama, email, no. hp...',
          hintStyle: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
          prefixIcon: const Icon(Icons.search_rounded, color: Color(0xFF64748B), size: 20),
          suffixIcon: _searchQuery.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.clear_rounded, color: Color(0xFF94A3B8), size: 18),
                  onPressed: () {
                    _searchController.clear();
                    setState(() => _searchQuery = '');
                  },
                )
              : null,
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(vertical: 12, horizontal: 12),
        ),
      ),
    );
  }

  Widget _buildUserCard(UserModel user, int index) {
    final isInactive = user.statusUser.toLowerCase() == 'inactive';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isInactive ? const Color(0xFFFECACA) : const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Index number
          Text(
            '$index',
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)),
          ),
          const SizedBox(width: 10),

          // Avatar
          ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: user.foto != null && user.foto!.isNotEmpty
                ? Image.network(
                    user.foto!,
                    width: 40,
                    height: 40,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => _buildAvatarFallback(user),
                  )
                : _buildAvatarFallback(user),
          ),
          const SizedBox(width: 12),

          // Details
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        user.nama,
                        style: TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w800,
                          color: isInactive ? const Color(0xFF94A3B8) : const Color(0xFF0F172A),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    _buildRoleBadge(user),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  user.email,
                  style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Text(
                      user.noHp != null && user.noHp!.isNotEmpty ? user.noHp! : 'Tanpa No. HP',
                      style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
                    ),
                    const Text(' • ', style: TextStyle(color: Color(0xFFCBD5E1))),
                    Text(
                      '${user.gender != null && user.gender!.toLowerCase().startsWith('m') ? "L" : "P"} / ${user.usia ?? 25} thn',
                      style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                    ),
                    if (isInactive) ...[
                      const SizedBox(width: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(4),
                          border: Border.all(color: const Color(0xFFFECACA)),
                        ),
                        child: const Text('Inactive', style: TextStyle(fontSize: 9, color: Color(0xFFDC2626), fontWeight: FontWeight.bold)),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),

          // Action buttons (Edit & Delete)
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              IconButton(
                icon: const Icon(Icons.edit_outlined, size: 18, color: Color(0xFF64748B)),
                visualDensity: VisualDensity.compact,
                onPressed: () => _showEditUserModal(user),
                tooltip: 'Edit Peran',
              ),
              IconButton(
                icon: Icon(
                  isInactive ? Icons.restart_alt_rounded : Icons.delete_outline_rounded,
                  size: 18,
                  color: isInactive ? const Color(0xFF15803D) : const Color(0xFFDC2626),
                ),
                visualDensity: VisualDensity.compact,
                onPressed: () => _handleToggleUserStatus(user),
                tooltip: isInactive ? 'Aktifkan Kembali' : 'Nonaktifkan Akun',
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildAvatarFallback(UserModel user) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: AppColors.matchaDark,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Center(
        child: Text(
          user.nama.isNotEmpty ? user.nama[0].toUpperCase() : 'U',
          style: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: Color(0xFFA8E63A),
          ),
        ),
      ),
    );
  }

  Widget _buildRoleBadge(UserModel user) {
    final role = user.role.toLowerCase();
    if (role == 'admin') {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        decoration: BoxDecoration(
          color: const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFF0F172A), width: 1.2),
        ),
        child: const Text(
          'Administrator',
          style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
        ),
      );
    } else if (user.isHost || role == 'host') {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        decoration: BoxDecoration(
          color: const Color(0xFFFEF3C7),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFFCD34D)),
        ),
        child: const Text(
          'Host',
          style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w800, color: Color(0xFFB45309)),
        ),
      );
    } else if (role == 'venue_owner') {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        decoration: BoxDecoration(
          color: const Color(0xFFEFF6FF),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFBFDBFE)),
        ),
        child: const Text(
          'Venue Owner',
          style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w800, color: Color(0xFF1D4ED8)),
        ),
      );
    } else {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        decoration: BoxDecoration(
          color: const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFCBD5E1)),
        ),
        child: const Text(
          'Member',
          style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w800, color: Color(0xFF475569)),
        ),
      );
    }
  }
}
