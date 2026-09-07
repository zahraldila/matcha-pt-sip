class PlayerModel {
  final int playerId;
  final String namaPlayer;
  final String nik;
  final String statusMember;
  final int totalMatches;
  final int wins;
  final int losses;
  final double winRate;

  const PlayerModel({
    required this.playerId,
    required this.namaPlayer,
    required this.nik,
    required this.statusMember,
    this.totalMatches = 0,
    this.wins = 0,
    this.losses = 0,
    this.winRate = 0.0,
  });

  factory PlayerModel.fromJson(Map<String, dynamic> json) {
    return PlayerModel(
      playerId: json['player_id'] is int
          ? json['player_id'] as int
          : int.parse(json['player_id'].toString()),
      namaPlayer: json['nama_player'] as String? ?? '',
      nik: json['nik'] as String? ?? '',
      statusMember: json['status_member'] as String? ?? 'active',
      totalMatches: json['total_matches'] as int? ?? 0,
      wins: json['wins'] as int? ?? 0,
      losses: json['losses'] as int? ?? 0,
      winRate: _calculateWinRate(
        json['wins'] as int? ?? 0,
        json['total_matches'] as int? ?? 0,
      ),
    );
  }

  static double _calculateWinRate(int wins, int total) {
    if (total == 0) return 0.0;
    return (wins / total) * 100;
  }

  Map<String, dynamic> toJson() {
    return {
      'player_id': playerId,
      'nama_player': namaPlayer,
      'nik': nik,
      'status_member': statusMember,
      'total_matches': totalMatches,
      'wins': wins,
      'losses': losses,
      'win_rate': winRate,
    };
  }

  @override
  String toString() {
    return 'PlayerModel(playerId: $playerId, namaPlayer: $namaPlayer, statusMember: $statusMember, wins: $wins/$totalMatches)';
  }
}
