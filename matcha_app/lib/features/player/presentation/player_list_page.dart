import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../domain/models/player_model.dart';
import 'controllers/player_controller.dart';

class PlayerListPage extends StatefulWidget {
  final bool isHost;
  final PlayerController? controller;

  const PlayerListPage({
    super.key,
    required this.isHost,
    this.controller,
  });

  @override
  State<PlayerListPage> createState() => _PlayerListPageState();
}

class _PlayerListPageState extends State<PlayerListPage> {
  late final PlayerController _controller;
  final _searchController = TextEditingController();
  String _selectedFilter = 'Semua';

  final List<String> _filters = ['Semua', 'Active', 'Inactive'];

  @override
  void initState() {
    super.initState();
    _controller = widget.controller ?? PlayerController();
    _controller.fetchPlayers();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _showAddPlayerDialog() {
    final nameCtrl = TextEditingController();
    final nikCtrl = TextEditingController(
      text: 'NIK-${DateTime.now().millisecondsSinceEpoch.toString().substring(8)}',
    );
    String selectedStatus = 'active';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: context.surf,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
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
                  Text(
                    'Tambah Pemain / Member Baru',
                    style: AppTextStyles.cardTitle.copyWith(color: context.txtPrimary),
                  ),
                  IconButton(
                    icon: Icon(Icons.close_rounded, color: context.txtSecondary),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Text(
                'Nama Lengkap',
                style: AppTextStyles.caption.copyWith(color: context.txtPrimary, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 6),
              TextField(
                controller: nameCtrl,
                style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                decoration: const InputDecoration(hintText: 'Misal: Rian Ardianto'),
              ),
              const SizedBox(height: 14),
              Text(
                'Nomor Identitas Pemain (NIK)',
                style: AppTextStyles.caption.copyWith(color: context.txtPrimary, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 6),
              TextField(
                controller: nikCtrl,
                style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                decoration: const InputDecoration(hintText: 'NIK-009'),
              ),
              const SizedBox(height: 14),
              Text(
                'Status Keanggotaan',
                style: AppTextStyles.caption.copyWith(color: context.txtPrimary, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 6),
              DropdownButtonFormField<String>(
                initialValue: selectedStatus,
                dropdownColor: context.surf,
                items: const [
                  DropdownMenuItem(value: 'active', child: Text('Active Member')),
                  DropdownMenuItem(value: 'inactive', child: Text('Inactive / Guest')),
                ],
                onChanged: (val) {
                  if (val != null) selectedStatus = val;
                },
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: () async {
                  if (nameCtrl.text.trim().isNotEmpty) {
                    final nav = Navigator.of(context);
                    final sm = ScaffoldMessenger.of(context);
                    final name = nameCtrl.text.trim();
                    final txtColor = context.txtPrimary;
                    final surfColor = context.surf;

                    await _controller.addPlayer(
                      namaPlayer: name,
                      nik: nikCtrl.text.trim(),
                      statusMember: selectedStatus,
                    );

                    nav.pop();
                    sm.showSnackBar(
                      SnackBar(
                        content: Text(
                          'Pemain "$name" berhasil ditambahkan ke database! 🎾',
                          style: AppTextStyles.body.copyWith(
                            color: txtColor,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        backgroundColor: surfColor,
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

  void _showPlayerDetailSheet(PlayerModel player) {
    showModalBottomSheet(
      context: context,
      backgroundColor: context.surf,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
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
                    backgroundColor: context.brandColor.withValues(alpha: 0.2),
                    child: Text(
                      player.namaPlayer.isNotEmpty ? player.namaPlayer[0].toUpperCase() : 'P',
                      style: AppTextStyles.pageTitle.copyWith(color: context.brandColor, fontSize: 20),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          player.namaPlayer,
                          style: AppTextStyles.cardTitle.copyWith(fontSize: 18, color: context.txtPrimary),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'NIK: ${player.nik ?? '-'}',
                          style: AppTextStyles.caption.copyWith(color: context.txtSecondary),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          player.communityName ?? 'Komunitas MATCHA',
                          style: AppTextStyles.caption.copyWith(color: context.brandColor, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: player.isActive
                          ? context.brandColor.withValues(alpha: 0.15)
                          : AppColors.error.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      player.statusMember.toUpperCase(),
                      style: AppTextStyles.badge.copyWith(
                        color: player.isActive ? context.brandColor : AppColors.error,
                        fontSize: 10,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Divider(color: context.surfBorder),
              const SizedBox(height: 12),

              // Statistics Card
              Text(
                'STATISTIK PERMAINAN',
                style: AppTextStyles.badge.copyWith(color: context.txtSecondary, letterSpacing: 1.5),
              ),
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: context.surfSec,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: context.surfBorder),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _buildStatItem(context, 'Total Main', '${player.totalMatches}'),
                    _buildStatDivider(context),
                    _buildStatItem(context, 'Menang', '${player.totalWins}', color: context.brandColor),
                    _buildStatDivider(context),
                    _buildStatItem(context, 'Kalah', '${player.totalLosses}', color: AppColors.error),
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
                        child: Text('Tutup', style: TextStyle(color: context.txtPrimary)),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: player.isActive ? AppColors.error : context.brandColor,
                          side: BorderSide(
                            color: (player.isActive ? AppColors.error : context.brandColor).withValues(alpha: 0.5),
                          ),
                        ),
                        onPressed: () async {
                          final newStatus = player.isActive ? 'inactive' : 'active';
                          await _controller.updatePlayerStatus(player.playerId, newStatus);
                          if (ctx.mounted) Navigator.pop(ctx);
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  'Status "${player.namaPlayer}" diubah menjadi $newStatus.',
                                  style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                                ),
                                backgroundColor: context.surf,
                                behavior: SnackBarBehavior.floating,
                              ),
                            );
                          }
                        },
                        child: Text(player.isActive ? 'Nonaktifkan' : 'Aktifkan'),
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

  Widget _buildStatItem(BuildContext context, String label, String value, {Color? color}) {
    return Column(
      children: [
        Text(value, style: AppTextStyles.pageTitle.copyWith(fontSize: 18, color: color ?? context.txtPrimary)),
        const SizedBox(height: 2),
        Text(label, style: AppTextStyles.caption.copyWith(fontSize: 11, color: context.txtSecondary)),
      ],
    );
  }

  Widget _buildStatDivider(BuildContext context) {
    return Container(width: 1, height: 28, color: context.surfBorder);
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: _controller,
      builder: (context, _) {
        final allPlayers = _controller.players;
        final filteredPlayers = allPlayers.where((p) {
          final matchesSearch = _searchController.text.isEmpty ||
              p.namaPlayer.toLowerCase().contains(_searchController.text.toLowerCase()) ||
              (p.nik != null && p.nik!.toLowerCase().contains(_searchController.text.toLowerCase()));

          if (!matchesSearch) return false;
          if (_selectedFilter == 'Semua') return true;
          if (_selectedFilter == 'Active') return p.isActive;
          if (_selectedFilter == 'Inactive') return !p.isActive;
          return true;
        }).toList();

        return Scaffold(
          backgroundColor: context.bg,
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
                      Text(
                        'Daftar Pemain',
                        style: AppTextStyles.pageTitle.copyWith(fontSize: 22, color: context.txtPrimary),
                      ),
                      Text(
                        '${filteredPlayers.length} Pemain',
                        style: AppTextStyles.caption.copyWith(color: context.brandColor, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),

                  // Search Bar
                  TextField(
                    controller: _searchController,
                    onChanged: (_) => setState(() {}),
                    style: AppTextStyles.body.copyWith(color: context.txtPrimary),
                    decoration: InputDecoration(
                      hintText: 'Cari nama pemain / NIK...',
                      prefixIcon: Icon(Icons.search_rounded, color: context.txtSecondary, size: 20),
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
                            selectedColor: context.brandColor,
                            backgroundColor: context.surf,
                            labelStyle: TextStyle(
                              color: isSelected ? Colors.black : context.txtSecondary,
                              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                              fontSize: 12,
                            ),
                            side: BorderSide(
                              color: isSelected ? context.brandColor : context.surfBorder,
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
                    child: _controller.isLoading
                        ? Center(child: CircularProgressIndicator(color: context.brandColor))
                        : filteredPlayers.isEmpty
                            ? Center(
                                child: Text(
                                  'Tidak ada pemain ditemukan',
                                  style: AppTextStyles.bodySecondary.copyWith(color: context.txtSecondary),
                                ),
                              )
                            : ListView.separated(
                                itemCount: filteredPlayers.length,
                                separatorBuilder: (context, index) => const SizedBox(height: 10),
                                itemBuilder: (context, index) {
                                  final player = filteredPlayers[index];
                                  return _buildPlayerCard(context, player);
                                },
                              ),
                  ),
                ],
              ),
            ),
          ),
          floatingActionButton: widget.isHost
              ? FloatingActionButton.extended(
                  backgroundColor: context.brandColor,
                  foregroundColor: Colors.black,
                  icon: const Icon(Icons.person_add_rounded),
                  label: const Text('Tambah Player', style: TextStyle(fontWeight: FontWeight.bold)),
                  onPressed: _showAddPlayerDialog,
                )
              : null,
        );
      },
    );
  }

  Widget _buildPlayerCard(BuildContext context, PlayerModel player) {
    return InkWell(
      onTap: () => _showPlayerDetailSheet(player),
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: context.surf,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: context.surfBorder),
        ),
        child: Row(
          children: [
            CircleAvatar(
              radius: 20,
              backgroundColor: context.surfSec,
              child: Text(
                player.namaPlayer.isNotEmpty ? player.namaPlayer[0].toUpperCase() : 'P',
                style: AppTextStyles.body.copyWith(
                  fontWeight: FontWeight.bold,
                  color: context.brandColor,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    player.namaPlayer,
                    style: AppTextStyles.cardTitle.copyWith(fontSize: 15, color: context.txtPrimary),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    player.communityName ?? 'Individual',
                    style: AppTextStyles.caption.copyWith(color: context.txtSecondary),
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: player.isActive
                    ? context.brandColor.withValues(alpha: 0.12)
                    : AppColors.error.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 6,
                    height: 6,
                    decoration: BoxDecoration(
                      color: player.isActive ? context.brandColor : AppColors.error,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 4),
                  Text(
                    player.statusMember.toUpperCase(),
                    style: AppTextStyles.badge.copyWith(
                      color: player.isActive ? context.brandColor : AppColors.error,
                      fontSize: 10,
                    ),
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
