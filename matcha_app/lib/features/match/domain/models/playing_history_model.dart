class PlayingHistoryModel {
  final int? historyId;
  final int playerId;
  final int matchId;
  final int? sessionId;
  final int? courtId;
  final int? score;
  final int? partnerPlayerId;
  final int? opponentPlayerId;
  final int? jumlahPermainan;
  final DateTime? createdAt;

  const PlayingHistoryModel({
    this.historyId,
    required this.playerId,
    required this.matchId,
    this.sessionId,
    this.courtId,
    this.score,
    this.partnerPlayerId,
    this.opponentPlayerId,
    this.jumlahPermainan,
    this.createdAt,
  });

  PlayingHistoryModel copyWith({
    int? historyId,
    int? playerId,
    int? matchId,
    int? sessionId,
    int? courtId,
    int? score,
    int? partnerPlayerId,
    int? opponentPlayerId,
    int? jumlahPermainan,
    DateTime? createdAt,
  }) {
    return PlayingHistoryModel(
      historyId: historyId ?? this.historyId,
      playerId: playerId ?? this.playerId,
      matchId: matchId ?? this.matchId,
      sessionId: sessionId ?? this.sessionId,
      courtId: courtId ?? this.courtId,
      score: score ?? this.score,
      partnerPlayerId: partnerPlayerId ?? this.partnerPlayerId,
      opponentPlayerId: opponentPlayerId ?? this.opponentPlayerId,
      jumlahPermainan: jumlahPermainan ?? this.jumlahPermainan,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  factory PlayingHistoryModel.fromJson(Map<String, dynamic> json) {
    return PlayingHistoryModel(
      historyId: json['history_id'] != null
          ? (json['history_id'] is int
              ? json['history_id'] as int
              : int.tryParse(json['history_id'].toString()))
          : null,
      playerId: json['player_id'] is int
          ? json['player_id'] as int
          : int.parse(json['player_id'].toString()),
      matchId: json['match_id'] is int
          ? json['match_id'] as int
          : int.parse(json['match_id'].toString()),
      sessionId: json['session_id'] != null
          ? (json['session_id'] is int
              ? json['session_id'] as int
              : int.tryParse(json['session_id'].toString()))
          : null,
      courtId: json['court_id'] != null
          ? (json['court_id'] is int
              ? json['court_id'] as int
              : int.tryParse(json['court_id'].toString()))
          : null,
      score: json['score'] != null
          ? (json['score'] is int
              ? json['score'] as int
              : int.tryParse(json['score'].toString()))
          : null,
      partnerPlayerId: json['partner_player_id'] != null
          ? (json['partner_player_id'] is int
              ? json['partner_player_id'] as int
              : int.tryParse(json['partner_player_id'].toString()))
          : null,
      opponentPlayerId: json['opponent_player_id'] != null
          ? (json['opponent_player_id'] is int
              ? json['opponent_player_id'] as int
              : int.tryParse(json['opponent_player_id'].toString()))
          : null,
      jumlahPermainan: json['jumlah_permainan'] != null
          ? (json['jumlah_permainan'] is int
              ? json['jumlah_permainan'] as int
              : int.tryParse(json['jumlah_permainan'].toString()))
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (historyId != null) 'history_id': historyId,
      'player_id': playerId,
      'match_id': matchId,
      if (sessionId != null) 'session_id': sessionId,
      if (courtId != null) 'court_id': courtId,
      if (score != null) 'score': score,
      if (partnerPlayerId != null) 'partner_player_id': partnerPlayerId,
      if (opponentPlayerId != null) 'opponent_player_id': opponentPlayerId,
      if (jumlahPermainan != null) 'jumlah_permainan': jumlahPermainan,
      if (createdAt != null) 'created_at': createdAt!.toIso8601String(),
    };
  }
}
