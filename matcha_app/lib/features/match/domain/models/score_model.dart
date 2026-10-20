class ScoreModel {
  final int? scoreId;
  final int matchId;
  final int? setNumber;
  final int? gameNumber;
  final String? pointScoreA;
  final String? pointScoreB;
  final int? gameScoreA;
  final int? gameScoreB;
  final int? setScoreA;
  final int? setScoreB;
  final int scoreSideA;
  final int scoreSideB;
  final String? scoringSystem;
  final String? statusScore;
  final int? version;
  final String? lastEventId;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  const ScoreModel({
    this.scoreId,
    required this.matchId,
    this.setNumber,
    this.gameNumber = 1,
    this.pointScoreA,
    this.pointScoreB,
    this.gameScoreA,
    this.gameScoreB,
    this.setScoreA,
    this.setScoreB,
    required this.scoreSideA,
    required this.scoreSideB,
    this.scoringSystem,
    this.statusScore,
    this.version = 0,
    this.lastEventId,
    this.createdAt,
    this.updatedAt,
  });

  factory ScoreModel.fromJson(Map<String, dynamic> json) {
    final rawSetNumber = json['set_number'];
    final int? parsedSetNumber = rawSetNumber is int
        ? rawSetNumber
        : (rawSetNumber != null ? int.tryParse(rawSetNumber.toString()) : null);

    final rawGameNumber = json['game_number'];
    final int? parsedGameNumber = rawGameNumber is int
        ? rawGameNumber
        : (rawGameNumber != null ? int.tryParse(rawGameNumber.toString()) : 1);

    final rawScoreA = json['score_side_a'] ?? json['game_score_a'];
    final int parsedScoreA = rawScoreA is int
        ? rawScoreA
        : (rawScoreA != null ? int.tryParse(rawScoreA.toString()) ?? 0 : 0);

    final rawScoreB = json['score_side_b'] ?? json['game_score_b'];
    final int parsedScoreB = rawScoreB is int
        ? rawScoreB
        : (rawScoreB != null ? int.tryParse(rawScoreB.toString()) ?? 0 : 0);

    final rawGameA = json['game_score_a'];
    final int? parsedGameA = rawGameA is int
        ? rawGameA
        : (rawGameA != null ? int.tryParse(rawGameA.toString()) : parsedScoreA);

    final rawGameB = json['game_score_b'];
    final int? parsedGameB = rawGameB is int
        ? rawGameB
        : (rawGameB != null ? int.tryParse(rawGameB.toString()) : parsedScoreB);

    final rawSetA = json['set_score_a'];
    final int? parsedSetA = rawSetA is int
        ? rawSetA
        : (rawSetA != null ? int.tryParse(rawSetA.toString()) : null);

    final rawSetB = json['set_score_b'];
    final int? parsedSetB = rawSetB is int
        ? rawSetB
        : (rawSetB != null ? int.tryParse(rawSetB.toString()) : null);

    final rawVersion = json['version'];
    final int? parsedVersion = rawVersion is int
        ? rawVersion
        : (rawVersion != null ? int.tryParse(rawVersion.toString()) : 0);

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
      gameNumber: parsedGameNumber,
      pointScoreA: json['point_score_a']?.toString(),
      pointScoreB: json['point_score_b']?.toString(),
      gameScoreA: parsedGameA,
      gameScoreB: parsedGameB,
      setScoreA: parsedSetA,
      setScoreB: parsedSetB,
      scoreSideA: parsedScoreA,
      scoreSideB: parsedScoreB,
      scoringSystem: json['scoring_system']?.toString(),
      statusScore: json['status_score']?.toString(),
      version: parsedVersion,
      lastEventId: json['last_event_id']?.toString(),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      updatedAt: json['updated_at'] != null
          ? DateTime.tryParse(json['updated_at'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (scoreId != null) 'score_id': scoreId,
      'match_id': matchId,
      if (setNumber != null) 'set_number': setNumber,
      if (gameNumber != null) 'game_number': gameNumber,
      if (pointScoreA != null) 'point_score_a': pointScoreA,
      if (pointScoreB != null) 'point_score_b': pointScoreB,
      if (gameScoreA != null) 'game_score_a': gameScoreA,
      if (gameScoreB != null) 'game_score_b': gameScoreB,
      if (setScoreA != null) 'set_score_a': setScoreA,
      if (setScoreB != null) 'set_score_b': setScoreB,
      'score_side_a': scoreSideA,
      'score_side_b': scoreSideB,
      if (scoringSystem != null) 'scoring_system': scoringSystem,
      if (statusScore != null) 'status_score': statusScore,
      if (version != null) 'version': version,
      if (lastEventId != null) 'last_event_id': lastEventId,
      if (createdAt != null) 'created_at': createdAt!.toIso8601String(),
      if (updatedAt != null) 'updated_at': updatedAt!.toIso8601String(),
    };
  }
}
