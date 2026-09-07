class MatchPairingModel {
  final int? matchId;
  final int? courtId;
  final String courtName;
  final int roundNumber;
  final List<String> sideA; // e.g. ['Aldi', 'Budi']
  final List<String> sideB; // e.g. ['Caca', 'Dina']
  final List<int>? sideAPlayerIds;
  final List<int>? sideBPlayerIds;
  final String statusMatch; // PENDING, PLAYING, FINISHED
  final int scoreA;
  final int scoreB;

  MatchPairingModel({
    this.matchId,
    this.courtId,
    required this.courtName,
    this.roundNumber = 1,
    required this.sideA,
    required this.sideB,
    this.sideAPlayerIds,
    this.sideBPlayerIds,
    this.statusMatch = 'PLAYING',
    this.scoreA = 0,
    this.scoreB = 0,
  });

  String get sideADisplay => sideA.join(' · ');
  String get sideBDisplay => sideB.join(' · ');

  factory MatchPairingModel.fromJson(Map<String, dynamic> json) {
    return MatchPairingModel(
      matchId: json['match_id'] as int?,
      courtId: json['court_id'] as int?,
      courtName: json['court_name'] as String? ?? 'Court 1',
      roundNumber: json['round_number'] as int? ?? 1,
      sideA: (json['side_a'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      sideB: (json['side_b'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [],
      statusMatch: json['status_match'] as String? ?? 'PLAYING',
      scoreA: json['score_a'] as int? ?? 0,
      scoreB: json['score_b'] as int? ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (matchId != null) 'match_id': matchId,
      'court_id': courtId,
      'round_number': roundNumber,
      'side_a_player1': sideAPlayerIds != null && sideAPlayerIds!.isNotEmpty ? sideAPlayerIds![0] : null,
      'side_a_player2': sideAPlayerIds != null && sideAPlayerIds!.length > 1 ? sideAPlayerIds![1] : null,
      'side_b_player1': sideBPlayerIds != null && sideBPlayerIds!.isNotEmpty ? sideBPlayerIds![0] : null,
      'side_b_player2': sideBPlayerIds != null && sideBPlayerIds!.length > 1 ? sideBPlayerIds![1] : null,
      'status_match': statusMatch,
    };
  }

  MatchPairingModel copyWith({
    int? matchId,
    int? courtId,
    String? courtName,
    int? roundNumber,
    List<String>? sideA,
    List<String>? sideB,
    List<int>? sideAPlayerIds,
    List<int>? sideBPlayerIds,
    String? statusMatch,
    int? scoreA,
    int? scoreB,
  }) {
    return MatchPairingModel(
      matchId: matchId ?? this.matchId,
      courtId: courtId ?? this.courtId,
      courtName: courtName ?? this.courtName,
      roundNumber: roundNumber ?? this.roundNumber,
      sideA: sideA ?? this.sideA,
      sideB: sideB ?? this.sideB,
      sideAPlayerIds: sideAPlayerIds ?? this.sideAPlayerIds,
      sideBPlayerIds: sideBPlayerIds ?? this.sideBPlayerIds,
      statusMatch: statusMatch ?? this.statusMatch,
      scoreA: scoreA ?? this.scoreA,
      scoreB: scoreB ?? this.scoreB,
    );
  }
}
