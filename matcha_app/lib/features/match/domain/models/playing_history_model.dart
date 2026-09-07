class PlayingHistoryModel {
  final int? historyId;
  final int playerId;
  final int matchId;
  final int totalScore;
  final bool isWin;

  const PlayingHistoryModel({
    this.historyId,
    required this.playerId,
    required this.matchId,
    required this.totalScore,
    required this.isWin,
  });

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
      totalScore: json['total_score'] is int
          ? json['total_score'] as int
          : int.parse(json['total_score'].toString()),
      isWin: json['is_win'] is bool
          ? json['is_win'] as bool
          : (json['is_win'].toString().toLowerCase() == 'true' ||
              json['is_win'] == 1),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (historyId != null) 'history_id': historyId,
      'player_id': playerId,
      'match_id': matchId,
      'total_score': totalScore,
      'is_win': isWin,
    };
  }
}
