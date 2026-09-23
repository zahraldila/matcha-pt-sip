import 'dart:async';
import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../auth/presentation/controllers/auth_controller.dart';
import '../../court/data/venue_service.dart';
import '../../court/domain/venue_model.dart';
import '../../drawing/domain/matcha_drawing_engine.dart';
import '../../drawing/presentation/drawing_result_page.dart';
import '../domain/game_wizard_model.dart';

class CreateGameWizardPage extends StatefulWidget {
  final AuthController? authController;

  const CreateGameWizardPage({super.key, this.authController});

  @override
  State<CreateGameWizardPage> createState() => _CreateGameWizardPageState();
}

class _CreateGameWizardPageState extends State<CreateGameWizardPage> {
  int _currentStep = 1; // 1 to 4
  final GameWizardConfig _config = GameWizardConfig();
  final VenueService _venueService = VenueService();

  List<VenueModel> _venues = [];
  bool _isLoadingVenues = true;
  final TextEditingController _activityNameController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadVenues();
  }

  @override
  void dispose() {
    _activityNameController.dispose();
    super.dispose();
  }

  Future<void> _loadVenues() async {
    try {
      final venues = await _venueService.getVenues();
      if (mounted) {
        setState(() {
          _venues = venues;
          _isLoadingVenues = false;
          if (venues.isNotEmpty && _config.venueId == null) {
            _config.venueId = venues.first.venueId;
            _config.venueName = venues.first.namaVenue;
          }
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _isLoadingVenues = false);
      }
    }
  }

  void _addCurrentHost() {
    final user = widget.authController?.currentUser;
    if (user == null) return;

    // Check if already added
    if (_config.players.any((p) => p.userId == user.userId || p.name.toLowerCase() == user.nama.toLowerCase())) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Kamu sudah masuk ke dalam daftar pemain.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() {
      _config.players.insert(
        0,
        GamePlayerItem(
          id: 'user_${user.userId}',
          name: user.nama,
          gender: user.gender ?? 'Laki-laki',
          level: user.level ?? 'Beginner',
          isGuest: false,
          avatarUrl: user.foto,
          userId: user.userId,
        ),
      );
    });
  }

  void _openAddPlayerModal() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _AddPlayerBottomSheet(
        onAddPlayer: (player) {
          if (_config.players.any((p) => p.name.toLowerCase() == player.name.toLowerCase())) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Pemain "${player.name}" sudah ada di daftar.'),
                behavior: SnackBarBehavior.floating,
              ),
            );
            return;
          }
          setState(() {
            _config.players.add(player);
          });
        },
      ),
    );
  }

  void _onGenerateDrawing() {
    final minRequired = _config.playMode == 'Single' ? 2 : 4;
    if (_config.players.length < minRequired) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Minimal $minRequired pemain untuk membuat drawing ${_config.playMode}.'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final rounds = MatchaDrawingEngine.generateDrawing(
      players: _config.players,
      courtCount: _config.courtCount,
      gameType: _config.gameType,
      playMode: _config.playMode,
      roundCount: _config.totalRounds,
    );

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => DrawingResultPage(
          config: _config,
          initialRounds: rounds,
          authController: widget.authController,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () {
            if (_currentStep > 1) {
              setState(() => _currentStep--);
            } else {
              Navigator.pop(context);
            }
          },
        ),
        title: Column(
          children: [
            Text(
              'LANGKAH $_currentStep DARI 4',
              style: AppTextStyles.caption.copyWith(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.1,
                color: const Color(0xFF94A3B8),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              'Create new game',
              style: AppTextStyles.h2.copyWith(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF0F172A),
              ),
            ),
          ],
        ),
        centerTitle: true,
        actions: [
          // Step dots
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: Row(
              children: List.generate(4, (index) {
                final isSelected = index + 1 == _currentStep;
                return Container(
                  margin: const EdgeInsets.symmetric(horizontal: 2.5),
                  width: isSelected ? 8 : 6,
                  height: isSelected ? 8 : 6,
                  decoration: BoxDecoration(
                    color: isSelected ? AppColors.matchaDark : const Color(0xFFCBD5E1),
                    shape: BoxShape.circle,
                  ),
                );
              }),
            ),
          ),
        ],
      ),
      body: SafeArea(
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 250),
          child: _buildCurrentStepView(),
        ),
      ),
    );
  }

  Widget _buildCurrentStepView() {
    switch (_currentStep) {
      case 1:
        return _buildStep1SportSelection();
      case 2:
        return _buildStep2GameTypeSelection();
      case 3:
        return _buildStep3GameConfig();
      case 4:
        return _buildStep4PlayerRoster();
      default:
        return const SizedBox();
    }
  }

  // --- STEP 1: SPORT SELECTION ---
  Widget _buildStep1SportSelection() {
    return ListView(
      key: const ValueKey(1),
      padding: const EdgeInsets.all(20),
      children: [
        Text(
          'Select sport type',
          style: AppTextStyles.h2.copyWith(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF0F172A),
          ),
        ),
        const SizedBox(height: 14),
        _buildSportCard(
          title: 'Padel',
          subtitle: 'Match Padel Tournament & Americano format',
          icon: Icons.sports_kabaddi,
          isSelected: _config.sport == 'Padel',
          onTap: () {
            setState(() {
              _config.sport = 'Padel';
              _currentStep = 2;
            });
          },
        ),
        const SizedBox(height: 12),
        _buildSportCard(
          title: 'Tennis',
          subtitle: 'Tennis single, double, sets & game scoring',
          icon: Icons.sports_tennis,
          isSelected: _config.sport == 'Tennis',
          onTap: () {
            setState(() {
              _config.sport = 'Tennis';
              _currentStep = 2;
            });
          },
        ),
      ],
    );
  }

  Widget _buildSportCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
            width: isSelected ? 1.8 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.matchaSoftLime,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: AppColors.matchaDark, size: 24),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: AppTextStyles.h2.copyWith(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: AppTextStyles.caption.copyWith(
                      fontSize: 12,
                      color: const Color(0xFF64748B),
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFF94A3B8)),
          ],
        ),
      ),
    );
  }

  // --- STEP 2: GAME TYPE SELECTION ---
  Widget _buildStep2GameTypeSelection() {
    final gameTypes = [
      {
        'title': 'Americano',
        'badge': 'POPULAR',
        'badgeColor': const Color(0xFF22C55E),
        'desc': 'Semua pemain berpasangan secara bergantian (Round Robin)',
        'icon': Icons.swap_horiz_rounded,
      },
      {
        'title': 'Team Americano',
        'badge': 'FIXED TEAM',
        'badgeColor': const Color(0xFF3B82F6),
        'desc': 'Format pasangan tetap/tim tetap saling melawan seluruh tim lainnya',
        'icon': Icons.group_rounded,
      },
      {
        'title': 'Mexicano',
        'badge': 'SEDANG AKHIR',
        'badgeColor': const Color(0xFF64748B),
        'desc': 'Sistem berpasangan peringkat sementara agar pertandingan selalu imbang',
        'icon': Icons.trending_up_rounded,
      },
      {
        'title': 'Team Mexicano',
        'badge': 'SEDANG AKHIR',
        'badgeColor': const Color(0xFF64748B),
        'desc': 'Format Mexicano kompetitif dengan pasangan tim yang tetap',
        'icon': Icons.view_comfortable_rounded,
      },
      {
        'title': 'Mixicano',
        'badge': 'SEDANG AKHIR',
        'badgeColor': const Color(0xFF64748B),
        'desc': 'Sistem selalu memasangkan 1 pria & 1 wanita dalam tiap tim secara dinamis',
        'icon': Icons.favorite_border_rounded,
      },
      {
        'title': 'Mix Americano',
        'badge': 'SEDANG AKHIR',
        'badgeColor': const Color(0xFF64748B),
        'desc': 'Dilarang berpasangan sama, selalu dengan komposisi pria dan wanita seimbang',
        'icon': Icons.volunteer_activism_rounded,
      },
      {
        'title': 'King of the Court',
        'badge': 'SEDANG AKHIR',
        'badgeColor': const Color(0xFF64748B),
        'desc': 'Pemenang court naik ke court atas, tim kalah turun ke court bawah',
        'icon': Icons.emoji_events_outlined,
      },
    ];

    return ListView(
      key: const ValueKey(2),
      padding: const EdgeInsets.all(20),
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'Select game type (${_config.sport})',
              style: AppTextStyles.h2.copyWith(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: const Color(0xFF0F172A),
              ),
            ),
            Text(
              'Pilih format turnamen/mabar',
              style: AppTextStyles.caption.copyWith(
                fontSize: 11,
                color: const Color(0xFF94A3B8),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        ...gameTypes.map((item) {
          final title = item['title'] as String;
          final isSelected = _config.gameType == title;

          return Container(
            margin: const EdgeInsets.only(bottom: 12),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () {
                setState(() {
                  _config.gameType = title;
                  _currentStep = 3;
                });
              },
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
                    width: isSelected ? 1.8 : 1,
                  ),
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
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: AppColors.matchaSoftLime,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(item['icon'] as IconData, color: AppColors.matchaDark, size: 20),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text(
                                title,
                                style: AppTextStyles.h2.copyWith(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                  color: const Color(0xFF0F172A),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: (item['badgeColor'] as Color).withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  item['badge'] as String,
                                  style: TextStyle(
                                    fontSize: 9,
                                    fontWeight: FontWeight.w800,
                                    color: item['badgeColor'] as Color,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 3),
                          Text(
                            item['desc'] as String,
                            style: AppTextStyles.caption.copyWith(
                              fontSize: 11,
                              color: const Color(0xFF64748B),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: Color(0xFF94A3B8), size: 20),
                  ],
                ),
              ),
            ),
          );
        }),
      ],
    );
  }

  // --- STEP 3: GAME CONFIG ---
  Widget _buildStep3GameConfig() {
    return ListView(
      key: const ValueKey(3),
      padding: const EdgeInsets.all(20),
      children: [
        // Selected Format Banner
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Column(
            children: [
              Text(
                'SELECTED FORMAT',
                style: AppTextStyles.caption.copyWith(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.1,
                  color: const Color(0xFF94A3B8),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                _config.gameType,
                style: AppTextStyles.h2.copyWith(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                  color: const Color(0xFF0F172A),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),

        // Activity Name
        _buildSectionLabel('Activity Name'),
        const SizedBox(height: 6),
        TextField(
          controller: _activityNameController,
          onChanged: (val) => _config.activityName = val,
          decoration: InputDecoration(
            hintText: 'Contoh: ${_config.sport} Weekend Fun / Tenis ITB',
            hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: AppColors.matchaDark, width: 1.5),
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Numbers of Court
        _buildSectionLabel('Numbers of Court'),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<int>(
              value: _config.courtCount,
              isExpanded: true,
              items: [1, 2, 3, 4].map((count) {
                return DropdownMenuItem<int>(
                  value: count,
                  child: Text('$count Court', style: const TextStyle(fontSize: 13)),
                );
              }).toList(),
              onChanged: (val) {
                if (val != null) setState(() => _config.courtCount = val);
              },
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Venue
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            _buildSectionLabel('Venue'),
            Text(
              'Khusus Lapangan ${_config.sport}',
              style: AppTextStyles.caption.copyWith(fontSize: 11, color: const Color(0xFF94A3B8)),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: _isLoadingVenues
              ? const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Center(child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))),
                )
              : DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    value: _config.venueId,
                    isExpanded: true,
                    hint: const Text('Pilih Lapangan Venue...', style: TextStyle(fontSize: 13)),
                    items: _venues.map((venue) {
                      return DropdownMenuItem<int>(
                        value: venue.venueId,
                        child: Text(
                          venue.namaVenue,
                          style: const TextStyle(fontSize: 13),
                          overflow: TextOverflow.ellipsis,
                        ),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) {
                        setState(() {
                          _config.venueId = val;
                          _config.venueName = _venues.firstWhere((v) => v.venueId == val).namaVenue;
                        });
                      }
                    },
                  ),
                ),
        ),
        const SizedBox(height: 16),

        // Scoring System
        _buildSectionLabel('Scoring System'),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              value: _config.scoringSystem,
              isExpanded: true,
              items: ['Total of 3', 'Total of 7', 'First to 4', 'First to 6'].map((sys) {
                return DropdownMenuItem<String>(
                  value: sys,
                  child: Text(sys, style: const TextStyle(fontSize: 13)),
                );
              }).toList(),
              onChanged: (val) {
                if (val != null) setState(() => _config.scoringSystem = val);
              },
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Leaderboard Ranked by
        _buildSectionLabel('Leaderboard Ranked by'),
        const SizedBox(height: 6),
        Row(
          children: [
            Expanded(
              child: _buildSegmentButton(
                label: 'Point',
                icon: Icons.check_circle_outline_rounded,
                isSelected: _config.leaderboardRankedBy == 'Point',
                onTap: () => setState(() => _config.leaderboardRankedBy = 'Point'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _buildSegmentButton(
                label: 'Win',
                icon: Icons.emoji_events_outlined,
                isSelected: _config.leaderboardRankedBy == 'Win',
                onTap: () => setState(() => _config.leaderboardRankedBy = 'Win'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),

        // Jenis Permainan
        _buildSectionLabel('Jenis Permainan'),
        const SizedBox(height: 6),
        Row(
          children: [
            Expanded(
              child: _buildPlayModeCard(
                title: 'Double',
                subtitle: '2vs2',
                icon: Icons.people_alt_outlined,
                isSelected: _config.playMode == 'Double',
                onTap: () => setState(() => _config.playMode = 'Double'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _buildPlayModeCard(
                title: 'Single',
                subtitle: '1vs1',
                icon: Icons.person_outline_rounded,
                isSelected: _config.playMode == 'Single',
                onTap: () => setState(() => _config.playMode = 'Single'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 24),

        // Submit Button
        SizedBox(
          width: double.infinity,
          height: 48,
          child: ElevatedButton(
            onPressed: () {
              if (_activityNameController.text.trim().isEmpty) {
                _config.activityName = '${_config.sport} ${_config.gameType} Fun';
              }
              setState(() => _currentStep = 4);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.matchaDark,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              elevation: 0,
            ),
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('Lanjut & Atur Daftar Pemain', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                SizedBox(width: 8),
                Icon(Icons.arrow_forward_rounded, size: 18),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSectionLabel(String label) {
    return Text(
      label,
      style: AppTextStyles.caption.copyWith(
        fontSize: 12,
        fontWeight: FontWeight.w700,
        color: const Color(0xFF334155),
      ),
    );
  }

  Widget _buildSegmentButton({
    required String label,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.matchaDark : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 16, color: isSelected ? Colors.white : const Color(0xFF64748B)),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.bold,
                color: isSelected ? Colors.white : const Color(0xFF334155),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPlayModeCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.matchaDark : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? AppColors.matchaDark : const Color(0xFFE2E8F0),
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Column(
          children: [
            Icon(icon, size: 20, color: isSelected ? Colors.white : const Color(0xFF64748B)),
            const SizedBox(height: 4),
            Text(
              title,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.bold,
                color: isSelected ? Colors.white : const Color(0xFF0F172A),
              ),
            ),
            Text(
              subtitle,
              style: TextStyle(
                fontSize: 10,
                color: isSelected ? Colors.white.withValues(alpha: 0.8) : const Color(0xFF94A3B8),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // --- STEP 4: PLAYER ROSTER ---
  Widget _buildStep4PlayerRoster() {
    final minRequired = _config.playMode == 'Single' ? 2 : 4;
    final canGenerate = _config.players.length >= minRequired;

    return Column(
      key: const ValueKey(4),
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              // Summary card
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Text(
                            _config.activityName.isEmpty ? '${_config.sport} ${_config.gameType}' : _config.activityName,
                            style: AppTextStyles.h2.copyWith(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              color: const Color(0xFF0F172A),
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppColors.matchaSoftLime,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Text(
                            'Fixed Mode',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              color: AppColors.matchaDark,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${_config.gameType} • ${_config.playMode} (${_config.playMode == "Double" ? "2v2" : "1v1"}) • ${_config.courtCount} Court • ${_config.venueName ?? "Venue"}',
                      style: AppTextStyles.caption.copyWith(
                        fontSize: 11,
                        color: const Color(0xFF64748B),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),

              // Player List Header
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Player List ( ${_config.players.length} )',
                    style: AppTextStyles.h2.copyWith(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                  Text(
                    '*Minimal $minRequired pemain untuk generate drawing',
                    style: AppTextStyles.caption.copyWith(
                      fontSize: 10,
                      color: const Color(0xFF94A3B8),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),

              // Add Yourself & Add Player Buttons
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _addCurrentHost,
                      icon: const Icon(Icons.person_add_outlined, size: 16),
                      label: const Text('+ ADD YOURSELF', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.matchaDark,
                        side: const BorderSide(color: Color(0xFFCBD5E1)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: _openAddPlayerModal,
                      icon: const Icon(Icons.group_add_outlined, size: 16),
                      label: const Text('+ ADD PLAYER / GUEST', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.matchaSoftLime,
                        foregroundColor: AppColors.matchaDark,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // List of Added Players or Empty State
              if (_config.players.isEmpty)
                Container(
                  padding: const EdgeInsets.all(28),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    children: [
                      const Icon(Icons.sports_tennis_rounded, size: 40, color: Color(0xFFCBD5E1)),
                      const SizedBox(height: 10),
                      Text(
                        'Great moments are meant to be shared.',
                        style: AppTextStyles.caption.copyWith(
                          fontWeight: FontWeight.bold,
                          color: const Color(0xFF475569),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Tambahkan minimal $minRequired pemain untuk memulai/mengaktifkan drawing live.',
                        textAlign: TextAlign.center,
                        style: AppTextStyles.caption.copyWith(
                          fontSize: 11,
                          color: const Color(0xFF94A3B8),
                        ),
                      ),
                    ],
                  ),
                )
              else
                ...List.generate(_config.players.length, (index) {
                  final player = _config.players[index];
                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 24,
                          height: 24,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            '${index + 1}',
                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                player.name,
                                style: AppTextStyles.h2.copyWith(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                  color: const Color(0xFF0F172A),
                                ),
                              ),
                              Text(
                                '${player.gender} • ${player.isGuest ? "Guest" : "Member"}',
                                style: AppTextStyles.caption.copyWith(
                                  fontSize: 10,
                                  color: const Color(0xFF94A3B8),
                                ),
                              ),
                            ],
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: AppColors.matchaSoftLime,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            player.level,
                            style: const TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              color: AppColors.matchaDark,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton(
                          icon: const Icon(Icons.delete_outline_rounded, size: 18, color: Color(0xFFEF4444)),
                          onPressed: () {
                            setState(() {
                              _config.players.removeAt(index);
                            });
                          },
                        ),
                      ],
                    ),
                  );
                }),
            ],
          ),
        ),

        // Action Button
        Container(
          padding: const EdgeInsets.all(20),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: Color(0xFFE2E8F0))),
          ),
          child: SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton(
              onPressed: canGenerate ? _onGenerateDrawing : null,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.matchaDark,
                disabledBackgroundColor: const Color(0xFFCBD5E1),
                foregroundColor: Colors.white,
                disabledForegroundColor: const Color(0xFF94A3B8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                elevation: 0,
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.shuffle_rounded, size: 18),
                  const SizedBox(width: 8),
                  Text(
                    'Generate Drawing & Start Game (${_config.playMode} ${_config.playMode == "Double" ? "2v2" : "1v1"})',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

// --- ADD PLAYER MODAL (DUAL TAB) ---
class _AddPlayerBottomSheet extends StatefulWidget {
  final ValueChanged<GamePlayerItem> onAddPlayer;

  const _AddPlayerBottomSheet({required this.onAddPlayer});

  @override
  State<_AddPlayerBottomSheet> createState() => _AddPlayerBottomSheetState();
}

class _AddPlayerBottomSheetState extends State<_AddPlayerBottomSheet> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _manualNameController = TextEditingController();

  String _selectedGender = 'Laki-laki';
  String _selectedLevel = 'Beginner';

  List<Map<String, dynamic>> _searchResults = [];
  bool _isSearching = false;
  Timer? _debounceTimer;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _searchController.dispose();
    _manualNameController.dispose();
    _debounceTimer?.cancel();
    super.dispose();
  }

  void _onSearchChanged(String query) {
    _debounceTimer?.cancel();
    if (query.trim().isEmpty) {
      setState(() {
        _searchResults = [];
        _isSearching = false;
      });
      return;
    }

    _debounceTimer = Timer(const Duration(milliseconds: 300), () async {
      setState(() => _isSearching = true);
      try {
        final supabase = Supabase.instance.client;
        final res = await supabase
            .from('tb_user')
            .select('id, nama, jenis_kelamin, level, foto')
            .ilike('nama', '%${query.trim()}%')
            .limit(10);

        if (mounted) {
          setState(() {
            _searchResults = List<Map<String, dynamic>>.from(res);
            _isSearching = false;
          });
        }
      } catch (_) {
        if (mounted) setState(() => _isSearching = false);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
        top: 20,
        left: 20,
        right: 20,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Tambahkan Player',
                    style: AppTextStyles.h2.copyWith(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF0F172A),
                    ),
                  ),
                  Text(
                    'Tambahkan pemain ke sesi ini',
                    style: AppTextStyles.caption.copyWith(fontSize: 11, color: const Color(0xFF64748B)),
                  ),
                ],
              ),
              IconButton(
                icon: const Icon(Icons.close_rounded, size: 20, color: Color(0xFF64748B)),
                onPressed: () => Navigator.pop(context),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Dual Tab Selector
          Container(
            height: 38,
            decoration: BoxDecoration(
              color: const Color(0xFFF1F5F9),
              borderRadius: BorderRadius.circular(10),
            ),
            child: TabBar(
              controller: _tabController,
              indicator: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(8),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 4,
                  ),
                ],
              ),
              labelColor: AppColors.matchaDark,
              unselectedLabelColor: const Color(0xFF64748B),
              labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
              tabs: const [
                Tab(text: 'Dari Database'),
                Tab(text: 'Input Manual'),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Tab Content
          SizedBox(
            height: 280,
            child: TabBarView(
              controller: _tabController,
              children: [
                _buildDatabaseTab(),
                _buildManualTab(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // Tab 1: Database Search
  Widget _buildDatabaseTab() {
    return Column(
      children: [
        TextField(
          controller: _searchController,
          onChanged: _onSearchChanged,
          decoration: InputDecoration(
            hintText: 'Cari nama player...',
            hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
            prefixIcon: const Icon(Icons.search_rounded, size: 18, color: Color(0xFF64748B)),
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
          ),
        ),
        const SizedBox(height: 10),
        Expanded(
          child: _isSearching
              ? const Center(child: CircularProgressIndicator(strokeWidth: 2))
              : _searchResults.isEmpty
                  ? Center(
                      child: Text(
                        _searchController.text.isEmpty
                            ? 'Ketik nama player untuk mencari'
                            : 'Pemain tidak ditemukan',
                        style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                      ),
                    )
                  : ListView.builder(
                      itemCount: _searchResults.length,
                      itemBuilder: (ctx, idx) {
                        final u = _searchResults[idx];
                        final name = u['nama'] ?? 'User';
                        final gender = u['jenis_kelamin'] ?? 'Laki-laki';
                        final level = u['level'] ?? 'Beginner';
                        final userId = u['id'] is int ? u['id'] as int : int.tryParse(u['id'].toString());

                        return ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 0),
                          leading: CircleAvatar(
                            backgroundColor: AppColors.matchaSoftLime,
                            child: Text(
                              name.isNotEmpty ? name[0].toUpperCase() : 'U',
                              style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.matchaDark),
                            ),
                          ),
                          title: Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                          subtitle: Text('$gender • $level', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                          trailing: IconButton(
                            icon: const Icon(Icons.add_circle_outline_rounded, color: AppColors.matchaDark),
                            onPressed: () {
                              widget.onAddPlayer(
                                GamePlayerItem(
                                  id: 'user_$userId',
                                  name: name,
                                  gender: gender,
                                  level: level,
                                  isGuest: false,
                                  userId: userId,
                                  avatarUrl: u['foto'],
                                ),
                              );
                              Navigator.pop(context);
                            },
                          ),
                        );
                      },
                    ),
        ),
      ],
    );
  }

  // Tab 2: Manual Input
  Widget _buildManualTab() {
    return ListView(
      children: [
        const Text('Nama Player', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
        const SizedBox(height: 6),
        TextField(
          controller: _manualNameController,
          decoration: InputDecoration(
            hintText: 'Masukkan nama player...',
            hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
            filled: true,
            fillColor: const Color(0xFFF8FAFC),
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Gender', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        value: _selectedGender,
                        isExpanded: true,
                        items: ['Laki-laki', 'Perempuan'].map((g) {
                          return DropdownMenuItem(value: g, child: Text(g, style: const TextStyle(fontSize: 12)));
                        }).toList(),
                        onChanged: (val) {
                          if (val != null) setState(() => _selectedGender = val);
                        },
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Skill Level', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        value: _selectedLevel,
                        isExpanded: true,
                        items: ['Beginner', 'Intermediate', 'Advanced'].map((l) {
                          return DropdownMenuItem(value: l, child: Text(l, style: const TextStyle(fontSize: 12)));
                        }).toList(),
                        onChanged: (val) {
                          if (val != null) setState(() => _selectedLevel = val);
                        },
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: const Color(0xFFFFFBEB),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: const Color(0xFFFDE68A)),
          ),
          child: const Row(
            children: [
              Icon(Icons.info_outline_rounded, size: 16, color: Color(0xFFD97706)),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Guest Player: Pemain non-user tidak terdaftar di database dan akan ditambahkan sebagai guest.',
                  style: TextStyle(fontSize: 10, color: Color(0xFF92400E)),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          height: 42,
          child: ElevatedButton(
            onPressed: () {
              final name = _manualNameController.text.trim();
              if (name.isEmpty) return;

              widget.onAddPlayer(
                GamePlayerItem(
                  id: 'guest_${DateTime.now().millisecondsSinceEpoch}',
                  name: name,
                  gender: _selectedGender,
                  level: _selectedLevel,
                  isGuest: true,
                ),
              );
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.matchaDark,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              elevation: 0,
            ),
            child: const Text('+ Tambahkan Player', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          ),
        ),
      ],
    );
  }
}
