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
    final nameCtrl = TextEditingController(text: user.nama);
    final emailCtrl = TextEditingController(text: user.email);
    final phoneCtrl = TextEditingController(text: user.noHp ?? '');
    final passwordCtrl = TextEditingController();

    String rawRole = user.role.toLowerCase();
    String selectedRole = 'member';
    if (rawRole.contains('admin')) {
      selectedRole = 'admin';
    } else if (rawRole == 'venue_owner') {
      selectedRole = 'venue_owner';
    } else if (rawRole == 'host' || user.isHost) {
      selectedRole = 'host';
    } else {
      selectedRole = 'member';
    }

    bool obscurePassword = true;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) {
          return Dialog(
            backgroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Header
                  Row(
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          color: const Color(0xFFDCFCE7),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFF86EFAC)),
                        ),
                        child: const Icon(
                          Icons.person_outline_rounded,
                          size: 20,
                          color: Color(0xFF15803D),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: const [
                            Text(
                              'Edit Data Pengguna',
                              style: TextStyle(
                                fontSize: 14.5,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                            SizedBox(height: 1),
                            Text(
                              'Perbarui profil, role, atau akses akun pengguna.',
                              style: TextStyle(
                                fontSize: 11,
                                color: Color(0xFF64748B),
                              ),
                            ),
                          ],
                        ),
                      ),
                      InkWell(
                        onTap: () => Navigator.pop(context),
                        borderRadius: BorderRadius.circular(16),
                        child: Container(
                          padding: const EdgeInsets.all(4),
                          decoration: const BoxDecoration(
                            color: Color(0xFFF1F5F9),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.close_rounded,
                            size: 16,
                            color: Color(0xFF64748B),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  const Divider(height: 1, color: Color(0xFFE2E8F0)),
                  const SizedBox(height: 14),

                  // 1. Nama Lengkap *
                  RichText(
                    text: const TextSpan(
                      style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                      children: [
                        TextSpan(text: 'Nama Lengkap '),
                        TextSpan(text: '*', style: TextStyle(color: Color(0xFFEF4444))),
                      ],
                    ),
                  ),
                  const SizedBox(height: 5),
                  TextField(
                    controller: nameCtrl,
                    style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
                    decoration: InputDecoration(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // 2. Alamat Email *
                  RichText(
                    text: const TextSpan(
                      style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                      children: [
                        TextSpan(text: 'Alamat Email '),
                        TextSpan(text: '*', style: TextStyle(color: Color(0xFFEF4444))),
                      ],
                    ),
                  ),
                  const SizedBox(height: 5),
                  TextField(
                    controller: emailCtrl,
                    keyboardType: TextInputType.emailAddress,
                    style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
                    decoration: InputDecoration(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // 3. Nomor HP / WhatsApp
                  const Text(
                    'Nomor HP / WhatsApp',
                    style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                  ),
                  const SizedBox(height: 5),
                  TextField(
                    controller: phoneCtrl,
                    keyboardType: TextInputType.phone,
                    style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
                    decoration: InputDecoration(
                      hintText: '08xxxxxxxxxx',
                      hintStyle: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // 4. Role Akun *
                  RichText(
                    text: const TextSpan(
                      style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                      children: [
                        TextSpan(text: 'Role Akun '),
                        TextSpan(text: '*', style: TextStyle(color: Color(0xFFEF4444))),
                      ],
                    ),
                  ),
                  const SizedBox(height: 5),
                  DropdownButtonFormField<String>(
                    initialValue: selectedRole,
                    style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
                    decoration: InputDecoration(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                    ),
                    items: const [
                      DropdownMenuItem(value: 'member', child: Text('Member')),
                      DropdownMenuItem(value: 'host', child: Text('Host')),
                      DropdownMenuItem(value: 'venue_owner', child: Text('Venue Owner')),
                      DropdownMenuItem(value: 'admin', child: Text('Administrator')),
                    ],
                    onChanged: (val) {
                      if (val != null) {
                        setModalState(() {
                          selectedRole = val;
                        });
                      }
                    },
                  ),
                  const SizedBox(height: 12),

                  // 5. Password Baru (opsional)
                  RichText(
                    text: const TextSpan(
                      style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                      children: [
                        TextSpan(text: 'Password Baru '),
                        TextSpan(text: '(opsional)', style: TextStyle(fontWeight: FontWeight.normal, color: Color(0xFF94A3B8))),
                      ],
                    ),
                  ),
                  const SizedBox(height: 5),
                  TextField(
                    controller: passwordCtrl,
                    obscureText: obscurePassword,
                    style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
                    decoration: InputDecoration(
                      hintText: 'Kosongkan jika tidak ingin mengubah password.',
                      hintStyle: const TextStyle(fontSize: 11.5, color: Color(0xFF94A3B8)),
                      suffixIcon: IconButton(
                        icon: Icon(
                          obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                          size: 18,
                          color: const Color(0xFF94A3B8),
                        ),
                        onPressed: () => setModalState(() => obscurePassword = !obscurePassword),
                      ),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFCBD5E1))),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5)),
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Action Buttons (Batal & Simpan Perubahan)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      OutlinedButton(
                        onPressed: () => Navigator.pop(context),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: const Color(0xFF475569),
                          side: const BorderSide(color: Color(0xFFCBD5E1)),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                        ),
                        child: const Text(
                          'Batal',
                          style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                        ),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton(
                        onPressed: () async {
                          if (nameCtrl.text.trim().isEmpty || emailCtrl.text.trim().isEmpty) return;
                          final messenger = ScaffoldMessenger.of(context);
                          Navigator.pop(context);
                          setState(() => _isLoading = true);

                          try {
                            await _adminUserService.updateUser(
                              userId: user.userId,
                              nama: nameCtrl.text.trim(),
                              email: emailCtrl.text.trim(),
                              noHp: phoneCtrl.text.trim().isEmpty ? null : phoneCtrl.text.trim(),
                              role: selectedRole,
                              isHost: selectedRole == 'host',
                              password: passwordCtrl.text.trim().isNotEmpty ? passwordCtrl.text.trim() : null,
                              gender: user.gender,
                              usia: user.usia,
                              level: user.level,
                            );
                            if (!mounted) return;
                            messenger.showSnackBar(
                              SnackBar(
                                content: Text('Data akun "${nameCtrl.text.trim()}" berhasil diperbarui.'),
                                backgroundColor: AppColors.matchaDark,
                              ),
                            );
                            await _loadUsers();
                          } catch (e) {
                            if (!mounted) return;
                            setState(() => _isLoading = false);
                            messenger.showSnackBar(
                              SnackBar(
                                content: Text('Gagal memperbarui pengguna: $e'),
                                backgroundColor: Colors.redAccent,
                              ),
                            );
                          }
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.matchaDark,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: const [
                            Icon(Icons.check_rounded, size: 16, color: Color(0xFFA8E63A)),
                            SizedBox(width: 6),
                            Text(
                              'Simpan Perubahan',
                              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _handleDeleteUser(UserModel user) async {
    final currentUserId = widget.authController?.currentUser?.userId;
    if (currentUserId != null && user.userId == currentUserId) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Row(
            children: [
              Icon(Icons.shield_outlined, color: Colors.white, size: 18),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Aksi Ditolak: Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif login.',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5),
                ),
              ),
            ],
          ),
          backgroundColor: const Color(0xFFE11D48),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF1F2),
                      shape: BoxShape.circle,
                      border: Border.all(color: const Color(0xFFFECDD3)),
                    ),
                    child: const Icon(
                      Icons.delete_outline_rounded,
                      color: Color(0xFFE11D48),
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Hapus Akun Pengguna',
                          style: TextStyle(
                            fontSize: 14.5,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        const SizedBox(height: 4),
                        RichText(
                          text: TextSpan(
                            style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFF64748B),
                              height: 1.4,
                            ),
                            children: [
                              const TextSpan(text: 'Apakah Anda yakin ingin menghapus akun pengguna '),
                              TextSpan(
                                text: user.nama,
                                style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                              ),
                              const TextSpan(
                                text: '? Tindakan ini tidak dapat dibatalkan.',
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  OutlinedButton(
                    onPressed: () => Navigator.pop(ctx, false),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF475569),
                      side: const BorderSide(color: Color(0xFFCBD5E1)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    ),
                    child: const Text('Batal', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                  ),
                  const SizedBox(width: 8),
                  ElevatedButton.icon(
                    onPressed: () => Navigator.pop(ctx, true),
                    icon: const Icon(Icons.delete_rounded, size: 15),
                    label: const Text('Hapus Akun', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFE11D48),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      elevation: 0,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
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

    final messenger = ScaffoldMessenger.of(context);
    setState(() => _isLoading = true);

    try {
      await _adminUserService.deleteUser(user.userId, currentUserId: currentUserId);
      if (!mounted) return;
      messenger.showSnackBar(
        SnackBar(
          content: Text('Pengguna "${user.nama}" berhasil dihapus.'),
          backgroundColor: AppColors.matchaDark,
        ),
      );
      await _loadUsers();
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      messenger.showSnackBar(
        SnackBar(
          content: Text('Gagal menghapus pengguna: $e'),
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
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.matchaDark))
          : RefreshIndicator(
              onRefresh: _loadUsers,
              color: AppColors.matchaDark,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
                children: [
                  // 1. Header Banner Administrator Panel (matching Web)
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
    final currentUserId = widget.authController?.currentUser?.userId;
    final isCurrentUser = currentUserId != null && user.userId == currentUserId;
    final genderStr = user.gender != null && user.gender!.toLowerCase().startsWith('f')
        ? 'Perempuan'
        : 'Laki-laki';
    final ageStr = user.usia != null ? '${user.usia} thn' : null;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF063B00).withValues(alpha: 0.03),
            blurRadius: 12,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Row: Avatar, User Details, Role Badge
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Avatar with rounded-14 container & index chip
                Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: (user.foto != null && user.foto!.isNotEmpty)
                            ? Colors.transparent
                            : const Color(0xFF063B00),
                        border: Border.all(
                          color: const Color(0xFFE2E8F0),
                          width: 1.5,
                        ),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: (user.foto != null && user.foto!.isNotEmpty)
                          ? Image.network(
                              user.foto!,
                              width: 44,
                              height: 44,
                              fit: BoxFit.cover,
                              errorBuilder: (_, _, _) => _buildAvatarFallback(user),
                            )
                          : _buildAvatarFallback(user),
                    ),
                    Positioned(
                      top: -4,
                      left: -4,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                        decoration: BoxDecoration(
                          color: const Color(0xFF0F172A),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          '#$index',
                          style: const TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(width: 12),

                // Name & Email
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              user.nama,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFF0F172A),
                                letterSpacing: -0.2,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          if (isCurrentUser) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                              decoration: BoxDecoration(
                                color: const Color(0xFFDCFCE7),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: const Color(0xFF86EFAC)),
                              ),
                              child: const Text(
                                'Akun Anda',
                                style: TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF15803D),
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 3),
                      Row(
                        children: [
                          const Icon(Icons.mail_outline_rounded, size: 12, color: Color(0xFF94A3B8)),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              user.email,
                              style: const TextStyle(
                                fontSize: 11.5,
                                color: Color(0xFF64748B),
                                fontWeight: FontWeight.w500,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),

                // Role Badge
                _buildRoleBadge(user),
              ],
            ),
          ),

          // Divider
          const Divider(height: 1, thickness: 1, color: Color(0xFFF1F5F9)),

          // Bottom Row: Meta Chips & Action Buttons
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            child: Row(
              children: [
                // Info Chips
                Expanded(
                  child: Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      // Phone Chip
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.phone_outlined, size: 11, color: Color(0xFF64748B)),
                            const SizedBox(width: 4),
                            Text(
                              (user.noHp != null && user.noHp!.isNotEmpty) ? user.noHp! : 'No Phone',
                              style: const TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF475569),
                              ),
                            ),
                          ],
                        ),
                      ),

                      // Gender & Age Chip
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Text(
                          ageStr != null ? '$genderStr • $ageStr' : genderStr,
                          style: const TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF475569),
                          ),
                        ),
                      ),

                      // Skill Level Chip (Matcha Soft Lime style)
                      if (user.level != null && user.level!.isNotEmpty)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEBF8D8),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: const Color(0xFF063B00).withValues(alpha: 0.15),
                            ),
                          ),
                          child: Text(
                            'Level: ${user.level!}',
                            style: const TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF063B00),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),

                const SizedBox(width: 8),

                // Action Buttons: Edit & Delete
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Edit button
                    Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: () => _showEditUserModal(user),
                        borderRadius: BorderRadius.circular(8),
                        child: Container(
                          padding: const EdgeInsets.all(7),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF0FDF4),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: const Color(0xFFBBF7D0)),
                          ),
                          child: const Icon(
                            Icons.edit_outlined,
                            size: 15,
                            color: Color(0xFF15803D),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    // Delete button
                    Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: isCurrentUser
                            ? () {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: const Row(
                                      children: [
                                        Icon(Icons.shield_outlined, color: Colors.white, size: 18),
                                        SizedBox(width: 10),
                                        Expanded(
                                          child: Text(
                                            'Aksi Ditolak: Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif login.',
                                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12.5),
                                          ),
                                        ),
                                      ],
                                    ),
                                    backgroundColor: const Color(0xFFE11D48),
                                    behavior: SnackBarBehavior.floating,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                  ),
                                );
                              }
                            : () => _handleDeleteUser(user),
                        borderRadius: BorderRadius.circular(8),
                        child: Container(
                          padding: const EdgeInsets.all(7),
                          decoration: BoxDecoration(
                            color: isCurrentUser ? const Color(0xFFF1F5F9) : const Color(0xFFFFF1F2),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: isCurrentUser ? const Color(0xFFE2E8F0) : const Color(0xFFFECDD3),
                            ),
                          ),
                          child: Icon(
                            isCurrentUser ? Icons.lock_outline_rounded : Icons.delete_outline_rounded,
                            size: 15,
                            color: isCurrentUser ? const Color(0xFF94A3B8) : const Color(0xFFE11D48),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAvatarFallback(UserModel user) {
    return Container(
      width: 44,
      height: 44,
      decoration: const BoxDecoration(
        color: Color(0xFF063B00),
        shape: BoxShape.circle,
      ),
      alignment: Alignment.center,
      child: Text(
        user.nama.isNotEmpty ? user.nama[0].toUpperCase() : 'U',
        style: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w900,
          color: Colors.white,
        ),
      ),
    );
  }

  Widget _buildRoleBadge(UserModel user) {
    final role = user.role.toLowerCase();
    if (role == 'admin' || role == 'administrator' || role == 'superadmin') {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: const Color(0xFF0F172A),
          borderRadius: BorderRadius.circular(8),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.shield_rounded, size: 10, color: Color(0xFFA8E63A)),
            SizedBox(width: 3.5),
            Text(
              'Administrator',
              style: TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w800,
                color: Colors.white,
              ),
            ),
          ],
        ),
      );
    } else if (user.isHost || role == 'host') {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: const Color(0xFFFEF3C7),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: const Color(0xFFFDE68A)),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.bolt_rounded, size: 11, color: Color(0xFFD97706)),
            SizedBox(width: 3),
            Text(
              'Host Game',
              style: TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w800,
                color: Color(0xFF92400E),
              ),
            ),
          ],
        ),
      );
    } else if (role == 'venue_owner') {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: const Color(0xFFE0F2FE),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: const Color(0xFFBAE6FD)),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.storefront_rounded, size: 11, color: Color(0xFF0284C7)),
            SizedBox(width: 3),
            Text(
              'Venue Owner',
              style: TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w800,
                color: Color(0xFF0369A1),
              ),
            ),
          ],
        ),
      );
    } else {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: const Color(0xFFEBF8D8),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: const Color(0xFF063B00).withValues(alpha: 0.18)),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.sports_tennis_rounded, size: 11, color: Color(0xFF063B00)),
            SizedBox(width: 3),
            Text(
              'Member Pemain',
              style: TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w800,
                color: Color(0xFF063B00),
              ),
            ),
          ],
        ),
      );
    }
  }
}
