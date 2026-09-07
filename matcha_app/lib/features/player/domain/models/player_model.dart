class PlayerModel {
  final int playerId;
  final String namaPlayer;
  final String? nik;
  final String statusMember;
  final String? communityName;
  final int totalMatches;
  final int totalWins;
  final int totalLosses;

  PlayerModel({
    required this.playerId,
    required this.namaPlayer,
    this.nik,
    this.statusMember = 'active',
    this.communityName,
    this.totalMatches = 0,
    this.totalWins = 0,
    this.totalLosses = 0,
  });

  bool get isActive => statusMember.toLowerCase() == 'active';

  double get winRate {
    if (totalMatches == 0) return 0.0;
    return (totalWins / totalMatches) * 100;
  }

  factory PlayerModel.fromJson(Map<String, dynamic> json) {
    return PlayerModel(
      playerId: json['player_id'] as int? ?? 0,
      namaPlayer: json['nama_player'] as String? ?? 'Unnamed Player',
      nik: json['nik'] as String?,
      statusMember: json['status_member'] as String? ?? 'active',
      communityName: json['community_name'] as String? ?? 'Individual',
      totalMatches: json['total_matches'] as int? ?? 0,
      totalWins: json['total_wins'] as int? ?? 0,
      totalLosses: json['total_losses'] as int? ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'nama_player': namaPlayer,
      'nik': nik,
      'status_member': statusMember,
    };
  }

  PlayerModel copyWith({
    int? playerId,
    String? namaPlayer,
    String? nik,
    String? statusMember,
    String? communityName,
    int? totalMatches,
    int? totalWins,
    int? totalLosses,
  }) {
    return PlayerModel(
      playerId: playerId ?? this.playerId,
      namaPlayer: namaPlayer ?? this.namaPlayer,
      nik: nik ?? this.nik,
      statusMember: statusMember ?? this.statusMember,
      communityName: communityName ?? this.communityName,
      totalMatches: totalMatches ?? this.totalMatches,
      totalWins: totalWins ?? this.totalWins,
      totalLosses: totalLosses ?? this.totalLosses,
    );
  }
}
