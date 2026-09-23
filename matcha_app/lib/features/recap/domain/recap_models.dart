class HostRecapData {
  final int totalSessions;
  final int totalPlayers;
  final int completedSessions;
  final String favoriteVenue;
  final List<HostedSessionItem> sessions;

  const HostRecapData({
    required this.totalSessions,
    required this.totalPlayers,
    required this.completedSessions,
    required this.favoriteVenue,
    required this.sessions,
  });
}

class HostedSessionItem {
  final int sessionId;
  final String title;
  final String sport;
  final String venue;
  final String court;
  final String date;
  final String time;
  final int quota;
  final int joinedCount;
  final String status;
  final String scoringSystem;
  final List<HostedSessionPlayer> players;

  const HostedSessionItem({
    required this.sessionId,
    required this.title,
    required this.sport,
    required this.venue,
    required this.court,
    required this.date,
    required this.time,
    required this.quota,
    required this.joinedCount,
    required this.status,
    required this.scoringSystem,
    required this.players,
  });
}

class HostedSessionPlayer {
  final int? playerId;
  final String name;
  final String gender;
  final String level;
  final String? foto;

  const HostedSessionPlayer({
    this.playerId,
    required this.name,
    this.gender = 'Male',
    this.level = 'Intermediate',
    this.foto,
  });
}

class PlayerCareerRecapData {
  final String playerName;
  final String username;
  final String role;
  final String level;
  final String avatar;
  final int totalMatches;
  final int wins;
  final int losses;
  final String winRate;
  final String totalHours;
  final String streak;
  final bool hasMatches;
  final List<PlayerMatchHistoryItem> recentMatches;
  final List<HeadToHeadItem> headToHead;

  const PlayerCareerRecapData({
    required this.playerName,
    required this.username,
    required this.role,
    required this.level,
    required this.avatar,
    required this.totalMatches,
    required this.wins,
    required this.losses,
    required this.winRate,
    required this.totalHours,
    required this.streak,
    required this.hasMatches,
    required this.recentMatches,
    required this.headToHead,
  });
}

class PlayerMatchHistoryItem {
  final String sport;
  final String venue;
  final String result; // 'WIN', 'LOSE', 'DRAW'
  final String score;
  final String partner;
  final List<String> opponents;
  final String matchDate;
  final int timestamp;

  const PlayerMatchHistoryItem({
    required this.sport,
    required this.venue,
    required this.result,
    required this.score,
    required this.partner,
    required this.opponents,
    required this.matchDate,
    required this.timestamp,
  });
}

class HeadToHeadItem {
  final String opponent;
  final int win;
  final int lose;
  final int played;
  final double winRatePercent;

  const HeadToHeadItem({
    required this.opponent,
    required this.win,
    required this.lose,
    required this.played,
    required this.winRatePercent,
  });
}
