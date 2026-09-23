class CommunityModel {
  final int communityId;
  final String namaCommunity;
  final String? deskripsi;
  final String? logo;
  final String sport;
  final String? tagline;
  final String? kotaHomebase;
  final String? targetLevel;
  final String statusKeanggotaan;
  final String? jadwalRutin;
  final String? homebaseVenue;
  final List<String> benefits;
  final int? createdBy;
  final String adminName;
  final int memberCount;
  final bool isMember;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  CommunityModel({
    required this.communityId,
    required this.namaCommunity,
    this.deskripsi,
    this.logo,
    required this.sport,
    this.tagline,
    this.kotaHomebase,
    this.targetLevel,
    required this.statusKeanggotaan,
    this.jadwalRutin,
    this.homebaseVenue,
    this.benefits = const [],
    this.createdBy,
    required this.adminName,
    required this.memberCount,
    this.isMember = false,
    this.createdAt,
    this.updatedAt,
  });

  factory CommunityModel.fromMap(Map<String, dynamic> map, {int? currentUserId}) {
    // 1. Sport resolution
    final rawSport = (map['sport'] as String?)?.toLowerCase().trim() ?? 'padel';
    String sportResolved = 'Padel';
    if (rawSport == 'tennis') {
      sportResolved = 'Tennis';
    } else if (rawSport == 'all_racquet' ||
        rawSport == 'both' ||
        rawSport == 'all racquet' ||
        rawSport == 'padel & tennis') {
      sportResolved = 'Padel & Tennis';
    }

    // 2. Admin name resolution
    String adminNameResolved = 'Admin Komunitas';
    if (map['tb_user'] != null && map['tb_user'] is Map) {
      final userMap = map['tb_user'] as Map<String, dynamic>;
      if (userMap['nama'] != null && (userMap['nama'] as String).isNotEmpty) {
        adminNameResolved = userMap['nama'] as String;
      }
    }

    // 3. Members count and isMember resolution
    int count = 0;
    bool memberFlag = false;
    if (map['tb_player'] != null && map['tb_player'] is List) {
      final playerList = map['tb_player'] as List;
      count = playerList.length;
      if (currentUserId != null) {
        memberFlag = playerList.any((p) =>
            p is Map && p['user_id'] != null && p['user_id'] == currentUserId);
      }
    }

    // 4. Benefits array resolution
    List<String> benefitsList = [];
    if (map['benefits'] != null) {
      if (map['benefits'] is List) {
        benefitsList = (map['benefits'] as List).map((b) => b.toString()).toList();
      } else if (map['benefits'] is String) {
        benefitsList = (map['benefits'] as String)
            .split(',')
            .map((b) => b.trim())
            .where((b) => b.isNotEmpty)
            .toList();
      }
    }

    return CommunityModel(
      communityId: map['community_id'] as int,
      namaCommunity: (map['nama_community'] as String?) ?? 'Komunitas Matcha',
      deskripsi: map['deskripsi'] as String?,
      logo: map['logo'] as String?,
      sport: sportResolved,
      tagline: map['tagline'] as String?,
      kotaHomebase: (map['kota_homebase'] as String?) ?? 'Bandung',
      targetLevel: (map['target_level'] as String?) ?? 'All Levels',
      statusKeanggotaan: (map['status_keanggotaan'] as String?) ?? 'Open',
      jadwalRutin: map['jadwal_rutin'] as String?,
      homebaseVenue: map['homebase_venue'] as String?,
      benefits: benefitsList,
      createdBy: map['created_by'] as int?,
      adminName: adminNameResolved,
      memberCount: (map['member_count'] as int?) ?? count,
      isMember: memberFlag,
      createdAt: map['created_at'] != null ? DateTime.tryParse(map['created_at'].toString()) : null,
      updatedAt: map['updated_at'] != null ? DateTime.tryParse(map['updated_at'].toString()) : null,
    );
  }

  String get displayImage {
    if (logo != null && logo!.trim().isNotEmpty) {
      final clean = logo!.trim();
      if (clean.startsWith('http://') || clean.startsWith('https://')) {
        return clean;
      }
      return 'https://xkyneehswdqkdgzodwdc.supabase.co/storage/v1/object/public/community-logos/$clean';
    }

    // Curated high quality tennis/padel images based on sport
    if (sport == 'Tennis') {
      return 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80';
    } else if (sport == 'Padel & Tennis') {
      return 'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=800&q=80';
    }
    return 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=800&q=80';
  }
}