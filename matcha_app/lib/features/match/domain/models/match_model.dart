class MatchModel {
  final int matchId;
  final int? drawingId;
  final int? courtId;
  final int? nomorMatch;
  final int? roundNumber;
  final int? sideAPlayer1;
  final int? sideAPlayer2;
  final int? sideBPlayer1;
  final int? sideBPlayer2;
  final String statusMatch;
  final String? hasilPertandingan;
  final String? winnerTeam;
  final int? version;
  final String? lastEventId;
  final DateTime? waktuSelesai;

  const MatchModel({
    required this.matchId,
    this.drawingId,
    this.courtId,
    this.nomorMatch,
    this.roundNumber,
    this.sideAPlayer1,
    this.sideAPlayer2,
    this.sideBPlayer1,
    this.sideBPlayer2,
    this.statusMatch = 'in_progress',
    this.hasilPertandingan,
    this.winnerTeam,
    this.version = 0,
    this.lastEventId,
    this.waktuSelesai,
  });

  bool get isFinished =>
      statusMatch.toLowerCase() == 'finished' ||
      statusMatch.toLowerCase() == 'completed' ||
      statusMatch.toLowerCase() == 'done';

  factory MatchModel.fromJson(Map<String, dynamic> json) {
    final rawVersion = json['version'];
    final int? parsedVersion = rawVersion is int
        ? rawVersion
        : (rawVersion != null ? int.tryParse(rawVersion.toString()) : 0);

    final rawNomorMatch = json['nomor_match'];
    final int? parsedNomorMatch = rawNomorMatch is int
        ? rawNomorMatch
        : (rawNomorMatch != null ? int.tryParse(rawNomorMatch.toString()) : null);

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
      nomorMatch: parsedNomorMatch,
      roundNumber: json['round_number'] != null
          ? int.tryParse(json['round_number'].toString())
          : parsedNomorMatch,
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
      hasilPertandingan: json['hasil_pertandingan'] as String?,
      winnerTeam: json['winner_team'] as String?,
      version: parsedVersion,
      lastEventId: json['last_event_id'] as String?,
      waktuSelesai: json['waktu_selesai'] != null
          ? DateTime.tryParse(json['waktu_selesai'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'match_id': matchId,
      if (drawingId != null) 'drawing_id': drawingId,
      if (courtId != null) 'court_id': courtId,
      if (nomorMatch != null) 'nomor_match': nomorMatch,
      if (sideAPlayer1 != null) 'side_a_player1': sideAPlayer1,
      if (sideAPlayer2 != null) 'side_a_player2': sideAPlayer2,
      if (sideBPlayer1 != null) 'side_b_player1': sideBPlayer1,
      if (sideBPlayer2 != null) 'side_b_player2': sideBPlayer2,
      'status_match': statusMatch,
      if (hasilPertandingan != null) 'hasil_pertandingan': hasilPertandingan,
      if (winnerTeam != null) 'winner_team': winnerTeam,
      if (version != null) 'version': version,
      if (lastEventId != null) 'last_event_id': lastEventId,
      if (waktuSelesai != null) 'waktu_selesai': waktuSelesai!.toIso8601String(),
    };
  }
}
