import 'match_pairing_model.dart';

class DrawingRoundModel {
  final int? drawingId;
  final int? sessionId;
  final String sessionName;
  final String sportName;
  final int roundNumber;
  final String drawingMethod; // 'Americano' | 'Mexicano'
  final String format; // 'Doubles' | 'Singles'
  final List<MatchPairingModel> matches;
  final List<String> waitingPlayers;
  final List<int>? waitingPlayerIds;
  final String statusDrawing; // 'ACTIVE' | 'COMPLETED'
  final DateTime? createdAt;

  DrawingRoundModel({
    this.drawingId,
    this.sessionId,
    required this.sessionName,
    required this.sportName,
    this.roundNumber = 1,
    this.drawingMethod = 'Americano',
    this.format = 'Doubles',
    required this.matches,
    this.waitingPlayers = const [],
    this.waitingPlayerIds,
    this.statusDrawing = 'ACTIVE',
    this.createdAt,
  });

  int get totalActiveCourts => matches.length;
  int get totalActivePlayers => matches.fold<int>(
        0,
        (sum, match) => sum + match.sideA.length + match.sideB.length,
      );

  factory DrawingRoundModel.fromJson(Map<String, dynamic> json) {
    final matchesJson = json['matches'] as List<dynamic>? ?? [];
    final waitingJson = json['waiting_players'] as List<dynamic>? ?? [];

    return DrawingRoundModel(
      drawingId: json['drawing_id'] as int?,
      sessionId: json['session_id'] as int?,
      sessionName: json['session_name'] as String? ?? 'Saturday Morning',
      sportName: json['sport_name'] as String? ?? 'Tennis',
      roundNumber: json['round_number'] as int? ?? 1,
      drawingMethod: json['drawing_method'] as String? ?? 'Americano',
      format: json['format'] as String? ?? 'Doubles',
      matches: matchesJson.map((e) => MatchPairingModel.fromJson(e as Map<String, dynamic>)).toList(),
      waitingPlayers: waitingJson.map((e) => e.toString()).toList(),
      statusDrawing: json['status_drawing'] as String? ?? 'ACTIVE',
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (drawingId != null) 'drawing_id': drawingId,
      'session_id': sessionId,
      'round_number': roundNumber,
      'drawing_method': drawingMethod,
      'status_drawing': statusDrawing,
    };
  }

  DrawingRoundModel copyWith({
    int? drawingId,
    int? sessionId,
    String? sessionName,
    String? sportName,
    int? roundNumber,
    String? drawingMethod,
    String? format,
    List<MatchPairingModel>? matches,
    List<String>? waitingPlayers,
    List<int>? waitingPlayerIds,
    String? statusDrawing,
    DateTime? createdAt,
  }) {
    return DrawingRoundModel(
      drawingId: drawingId ?? this.drawingId,
      sessionId: sessionId ?? this.sessionId,
      sessionName: sessionName ?? this.sessionName,
      sportName: sportName ?? this.sportName,
      roundNumber: roundNumber ?? this.roundNumber,
      drawingMethod: drawingMethod ?? this.drawingMethod,
      format: format ?? this.format,
      matches: matches ?? this.matches,
      waitingPlayers: waitingPlayers ?? this.waitingPlayers,
      waitingPlayerIds: waitingPlayerIds ?? this.waitingPlayerIds,
      statusDrawing: statusDrawing ?? this.statusDrawing,
      createdAt: createdAt ?? this.createdAt,
    );
  }
}
