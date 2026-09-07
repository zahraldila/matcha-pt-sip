class MatchModel {
  final int matchId;
  final int? drawingId;
  final int? courtId;
  final int? roundNumber;
  final int? sideAPlayer1;
  final int? sideAPlayer2;
  final int? sideBPlayer1;
  final int? sideBPlayer2;
  final String statusMatch;

  const MatchModel({
    required this.matchId,
    this.drawingId,
    this.courtId,
    this.roundNumber,
    this.sideAPlayer1,
    this.sideAPlayer2,
    this.sideBPlayer1,
    this.sideBPlayer2,
    this.statusMatch = 'in_progress',
  });

  bool get isFinished =>
      statusMatch.toLowerCase() == 'finished' ||
      statusMatch.toLowerCase() == 'completed' ||
      statusMatch.toLowerCase() == 'done';

  factory MatchModel.fromJson(Map<String, dynamic> json) {
    return MatchModel(
      matchId: json['match_id'] is int
          ? json['match_id'] as int
          : int.parse(json['match_id'].toString()),
      drawingId: json['drawing_id'] != null
          ? int.tryParse(json['drawing_id'].toString())
          : null,
      courtId: json['court_id'] != null
          ? int.tryParse(json['court_id'].toString())
          : null,
      roundNumber: json['round_number'] != null
          ? int.tryParse(json['round_number'].toString())
          : null,
      sideAPlayer1: json['side_a_player1'] != null
          ? int.tryParse(json['side_a_player1'].toString())
          : null,
      sideAPlayer2: json['side_a_player2'] != null
          ? int.tryParse(json['side_a_player2'].toString())
          : null,
      sideBPlayer1: json['side_b_player1'] != null
          ? int.tryParse(json['side_b_player1'].toString())
          : null,
      sideBPlayer2: json['side_b_player2'] != null
          ? int.tryParse(json['side_b_player2'].toString())
          : null,
      statusMatch: json['status_match'] as String? ?? 'in_progress',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'match_id': matchId,
      if (drawingId != null) 'drawing_id': drawingId,
      if (courtId != null) 'court_id': courtId,
      if (roundNumber != null) 'round_number': roundNumber,
      if (sideAPlayer1 != null) 'side_a_player1': sideAPlayer1,
      if (sideAPlayer2 != null) 'side_a_player2': sideAPlayer2,
      if (sideBPlayer1 != null) 'side_b_player1': sideBPlayer1,
      if (sideBPlayer2 != null) 'side_b_player2': sideBPlayer2,
      'status_match': statusMatch,
    };
  }
}
