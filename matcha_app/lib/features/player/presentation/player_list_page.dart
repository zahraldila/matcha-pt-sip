import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';

class PlayerListPage extends StatefulWidget {
  final bool isHost;

  const PlayerListPage({super.key, required this.isHost});

  @override
  State<PlayerListPage> createState() => _PlayerListPageState();
}

class _PlayerListPageState extends State<PlayerListPage> {
  final _searchController = TextEditingController();
  String _selectedFilter = 'Semua';

  final List<String> _filters = ['Semua', 'Active', 'Sportif Club', 'Smansa Tennis', 'Viborazer'];

  final List<Map<String, dynamic>> _players = [
    {
      'id': 1,
      'name': 'Aldi',
      'nik': 'NIK-001',
      'community': 'Sportif Tennis Club',
      'phone': '0812-3456-7890',
      'status': 'ACTIVE',
      'matches': 14,
      'win': 9,
      'lose': 5,
    },
    {
      'id': 2,
      'name': 'Budi',
      'nik': 'NIK-002',
      'community': 'Smansa Tennis',
      'phone': '0812-9876-5432',
      'status': 'ACTIVE',
      'matches': 12,
      'win': 8,
      'lose': 4,
    },
    {
      'id': 3,
      'name': 'Caca',
      'nik': 'NIK-003',
      'community': 'Individual',
      'phone': '0813-1122-3344',
      'status': 'ACTIVE',
      'matches': 10,
      'win': 6,
      'lose': 4,
    },
    {
      'id': 4,
      'name': 'Dina',
      'nik': 'NIK-004',
      'community': 'Individual',
      'phone': '0813-5566-7788',
      'status': 'ACTIVE',
      'matches': 10,
      'win': 5,
      'lose': 5,
    },
    {
      'id': 5,
      'name': 'Eka',
      'nik': 'NIK-005',
      'community': 'Viborazer Padel',
      'phone': '0815-9988-7766',
      'status': 'ACTIVE',
      'matches': 8,
      'win': 5,
      'lose': 3,
    },
    {
      'id': 6,
      'name': 'Fajar',
      'nik': 'NIK-006',
      'community': 'Viborazer Padel',
      'phone': '0817-4433-2211',
      'status': 'ACTIVE',
      'matches': 8,
      'win': 4,
      'lose': 4,
    },
    {
      'id': 7,
      'name': 'Gilang',
      'nik': 'NIK-007',
      'community': 'Sportif Tennis Club',
      'phone': '0818-7766-5544',
      'status': 'ACTIVE',
      'matches': 6,
      'win': 3,
      'lose': 3,
    },
    {
      'id': 8,
      'name': 'Hadi',
      'nik': 'NIK-008',
      'community': 'Smansa Tennis',
      'phone': '0819-0011-2233',
      'status': 'ACTIVE',
      'matches': 6,
      'win': 2,
      'lose': 4,
    },
  ];

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _showAddPlayerDialog() {
    final nameCtrl = TextEditingController();
    final nikCtrl = TextEditingController(text: 'NIK-${DateTime.now().millisecondsSinceEpoch.toString().substring(8)}');
    String selectedClub = 'Individual';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 20,
            bottom: MediaQuery.of(context).viewInsets.bottom + 20,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Tambah Pemain / Member Baru', style: AppTextStyles.cardTitle),
                  IconButton(
                    icon: const Icon(Icons.close_rounded, color: AppColors.textSecondary),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Text('Nama Lengkap', style: AppTextStyles.caption.copyWith(color: AppColors.textPrimary, fontWeight: FontWeight.w600)),
              const SizedBox(height: 6),
              TextField(
                controller: nameCtrl,
                style: AppTextStyles.body,
                decoration: const InputDecoration(hintText: 'Misal: Rian Ardianto'),
              ),
              const SizedBox(height: 14),
              Text('Nomor Identitas Pemain (NIK)', style: AppTextStyles.caption.copyWith(color: AppColors.textPrimary, fontWeight: FontWeight.w600)),
              const SizedBox(height: 6),
              TextField(
                controller: nikCtrl,
                style: AppTextStyles.body,
                decoration: const InputDecoration(hintText: 'NIK-009'),
              ),
              const SizedBox(height: 14),
              Text('Komunitas / Klub', style: AppTextStyles.caption.copyWith(color: AppColors.textPrimary, fontWeight: FontWeight.w600)),
              const SizedBox(height: 6),
              DropdownButtonFormField<String>(
                initialValue: selectedClub,
                dropdownColor: AppColors.surfaceSecondary,
                items: ['Individual', 'Smansa Tennis', 'Sportif Tennis Club', 'Viborazer Padel']
                    .map((club) => DropdownMenuItem(value: club, child: Text(club, style: AppTextStyles.body)))
                    .toList(),
                onChanged: (val) {
                  if (val != null) selectedClub = val;
                },
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: () {
                  if (nameCtrl.text.trim().isNotEmpty) {
                    setState(() {
                      _players.insert(0, {
                        'id': _players.length + 1,
                        'name': nameCtrl.text.trim(),
                        'nik': nikCtrl.text.trim(),
                        'community': selectedClub,
                        'phone': '0812-0000-1111',
                        'status': 'ACTIVE',
                        'matches': 0,
                        'win': 0,
                        'lose': 0,
                      });
                    });
                    Navigator.pop(context);
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(
                          'Pemain "${nameCtrl.text.trim()}" berhasil ditambahkan ke daftar! 🎾',
                          style: AppTextStyles.body.copyWith(
                            color: AppColors.textPrimary,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        backgroundColor: AppColors.surfaceSecondary,
                        behavior: SnackBarBehavior.floating,
                      ),
                    );
                  }
                },
                child: const Text('Simpan Member Baru'),
              ),
            ],
          ),
        );
      },
    );
  }

  void _showPlayerDetailSheet(Map<String, dynamic> player) {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 26,
                    backgroundColor: AppColors.primary.withValues(alpha: 0.2),
                    child: Text(
                      player['name'][0],
                      style: AppTextStyles.pageTitle.copyWith(color: AppColors.primary, fontSize: 20),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(player['name'], style: AppTextStyles.cardTitle.copyWith(fontSize: 18)),
                        const SizedBox(height: 2),
                        Text('NIK: ${player['nik']}', style: AppTextStyles.caption.copyWith(color: AppColors.textSecondary)),
                        const SizedBox(height: 4),
                        Text(
                          player['community'],
                          style: AppTextStyles.caption.copyWith(color: AppColors.primary, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      player['status'],
                      style: AppTextStyles.badge.copyWith(color: AppColors.primary, fontSize: 10),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              const Divider(),
              const SizedBox(height: 12),

              // Statistics Card
              Text('STATISTIK PERMAINAN', style: AppTextStyles.badge.copyWith(color: AppColors.textSecondary, letterSpacing: 1.5)),
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSecondary,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.surfaceBorder),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _buildStatItem('Total Main', '${player['matches']}'),
                    _buildStatDivider(),
                    _buildStatItem('Menang', '${player['win']}', color: AppColors.primary),
                    _buildStatDivider(),
                    _buildStatItem('Kalah', '${player['lose']}', color: AppColors.error),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              if (widget.isHost) ...[
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text('Edit Data'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.error,
                          side: BorderSide(color: AppColors.error.withValues(alpha: 0.5)),
                        ),
                        onPressed: () {
                          Navigator.pop(context);
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(
                                'Status pemain "${player['name']}" dinonaktifkan.',
                                style: AppTextStyles.body.copyWith(
                                  color: AppColors.textPrimary,
                                ),
                              ),
                              backgroundColor: AppColors.surfaceSecondary,
                              behavior: SnackBarBehavior.floating,
                            ),
                          );
                        },
                        child: const Text('Nonaktifkan'),
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 10),
            ],
          ),
        );
      },
    );
  }

  Widget _buildStatItem(String label, String value, {Color? color}) {
    return Column(
      children: [
        Text(value, style: AppTextStyles.pageTitle.copyWith(fontSize: 18, color: color ?? AppColors.textPrimary)),
        const SizedBox(height: 2),
        Text(label, style: AppTextStyles.caption.copyWith(fontSize: 11)),
      ],
    );
  }

  Widget _buildStatDivider() {
    return Container(width: 1, height: 28, color: AppColors.surfaceBorder);
  }

  @override
  Widget build(BuildContext context) {
    final filteredPlayers = _players.where((p) {
      if (_selectedFilter == 'Semua') return true;
      if (_selectedFilter == 'Active') return p['status'] == 'ACTIVE';
      return p['community'].toString().contains(_selectedFilter);
    }).toList();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Daftar Pemain', style: AppTextStyles.pageTitle.copyWith(fontSize: 22)),
                  Text(
                    '${_players.length} Pemain',
                    style: AppTextStyles.caption.copyWith(color: AppColors.primary, fontWeight: FontWeight.w600),
                  ),
                ],
              ),
              const SizedBox(height: 14),

              // Search Bar
              TextField(
                controller: _searchController,
                style: AppTextStyles.body,
                decoration: const InputDecoration(
                  hintText: 'Cari nama pemain / NIK...',
                  prefixIcon: Icon(Icons.search_rounded, color: AppColors.textSecondary, size: 20),
                ),
              ),
              const SizedBox(height: 12),

              // Filter Chips
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: _filters.map((filter) {
                    final isSelected = _selectedFilter == filter;
                    return Padding(
                      padding: const EdgeInsets.only(right: 8.0),
                      child: ChoiceChip(
                        label: Text(filter),
                        selected: isSelected,
                        selectedColor: AppColors.primary,
                        backgroundColor: AppColors.surface,
                        labelStyle: TextStyle(
                          color: isSelected ? AppColors.textOnPrimary : AppColors.textSecondary,
                          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                          fontSize: 12,
                        ),
                        side: BorderSide(
                          color: isSelected ? AppColors.primary : AppColors.surfaceBorder,
                        ),
                        onSelected: (_) => setState(() => _selectedFilter = filter),
                      ),
                    );
                  }).toList(),
                ),
              ),
              const SizedBox(height: 16),

              // Players List
              Expanded(
                child: ListView.separated(
                  itemCount: filteredPlayers.length,
                  separatorBuilder: (context, index) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final player = filteredPlayers[index];
                    return _buildPlayerCard(player);
                  },
                ),
              ),
            ],
          ),
        ),
      ),
      floatingActionButton: widget.isHost
          ? FloatingActionButton.extended(
              backgroundColor: AppColors.primary,
              foregroundColor: AppColors.textOnPrimary,
              icon: const Icon(Icons.person_add_rounded),
              label: const Text('Tambah Player', style: TextStyle(fontWeight: FontWeight.bold)),
              onPressed: _showAddPlayerDialog,
            )
          : null,
    );
  }

  Widget _buildPlayerCard(Map<String, dynamic> player) {
    return InkWell(
      onTap: () => _showPlayerDetailSheet(player),
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.surfaceBorder),
        ),
        child: Row(
          children: [
            CircleAvatar(
              radius: 20,
              backgroundColor: AppColors.surfaceSecondary,
              child: Text(
                player['name'][0],
                style: AppTextStyles.body.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.primary,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(player['name'], style: AppTextStyles.cardTitle.copyWith(fontSize: 15)),
                  const SizedBox(height: 2),
                  Text(player['community'], style: AppTextStyles.caption),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 6,
                    height: 6,
                    decoration: const BoxDecoration(
                      color: AppColors.primary,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 4),
                  Text(
                    'Active',
                    style: AppTextStyles.badge.copyWith(color: AppColors.primary, fontSize: 10),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
