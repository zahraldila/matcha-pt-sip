class ScoreModel {
  final int? scoreId;
  final int matchId;
  final int setNumber;
  final int scoreSideA;
  final int scoreSideB;
  final DateTime? createdAt;

  const ScoreModel({
    this.scoreId,
    required this.matchId,
    required this.setNumber,
    required this.scoreSideA,
    required this.scoreSideB,
    this.createdAt,
  });

  factory ScoreModel.fromJson(Map<String, dynamic> json) {
    return ScoreModel(
      scoreId: json['score_id'] != null
          ? (json['score_id'] is int
              ? json['score_id'] as int
              : int.tryParse(json['score_id'].toString()))
          : null,
      matchId: json['match_id'] is int
          ? json['match_id'] as int
          : int.parse(json['match_id'].toString()),
      setNumber: json['set_number'] is int
          ? json['set_number'] as int
          : int.parse(json['set_number'].toString()),
      scoreSideA: json['score_side_a'] is int
          ? json['score_side_a'] as int
          : int.parse(json['score_side_a'].toString()),
      scoreSideB: json['score_side_b'] is int
          ? json['score_side_b'] as int
          : int.parse(json['score_side_b'].toString()),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (scoreId != null) 'score_id': scoreId,
      'match_id': matchId,
      'set_number': setNumber,
      'score_side_a': scoreSideA,
      'score_side_b': scoreSideB,
      if (createdAt != null) 'created_at': createdAt!.toIso8601String(),
    };
  }
}
