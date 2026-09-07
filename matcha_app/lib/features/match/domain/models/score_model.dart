class ScoreModel {
  final int? scoreId;
  final int matchId;
  final int? setNumber;
  final int scoreSideA;
  final int scoreSideB;
  final DateTime? createdAt;

  const ScoreModel({
    this.scoreId,
    required this.matchId,
    this.setNumber,
    required this.scoreSideA,
    required this.scoreSideB,
    this.createdAt,
  });

  factory ScoreModel.fromJson(Map<String, dynamic> json) {
    final rawSetNumber = json['set_number'];
    final int? parsedSetNumber = rawSetNumber is int
        ? rawSetNumber
        : (rawSetNumber != null ? int.tryParse(rawSetNumber.toString()) : null);

    final rawScoreA = json['score_side_a'];
    final int parsedScoreA = rawScoreA is int
        ? rawScoreA
        : (rawScoreA != null ? int.tryParse(rawScoreA.toString()) ?? 0 : 0);

    final rawScoreB = json['score_side_b'];
    final int parsedScoreB = rawScoreB is int
        ? rawScoreB
        : (rawScoreB != null ? int.tryParse(rawScoreB.toString()) ?? 0 : 0);

    return ScoreModel(
      scoreId: json['score_id'] != null
          ? (json['score_id'] is int
              ? json['score_id'] as int
              : int.tryParse(json['score_id'].toString()))
          : null,
      matchId: json['match_id'] is int
          ? json['match_id'] as int
          : int.parse(json['match_id'].toString()),
      setNumber: parsedSetNumber,
      scoreSideA: parsedScoreA,
      scoreSideB: parsedScoreB,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (scoreId != null) 'score_id': scoreId,
      'match_id': matchId,
      if (setNumber != null) 'set_number': setNumber,
      'score_side_a': scoreSideA,
      'score_side_b': scoreSideB,
      if (createdAt != null) 'created_at': createdAt!.toIso8601String(),
    };
  }
}
