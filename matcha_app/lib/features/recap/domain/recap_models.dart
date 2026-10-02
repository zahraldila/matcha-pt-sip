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

class SessionMatchRecapData {
  final int sessionId;
  final String sessionName;
  final String venueName;
  final String? courtName;
  final String sportName;
  final String scoringSystem;
  final bool isSets;
  final int totalRounds;
  final int totalPlayers;
  final List<SessionRoundRecapItem> rounds;
  final List<SessionPlayerStanding> standings;
  final SessionPersonalStat? myStats;
  final Map<int, Map<String, int>> kudosMap;
  final Map<int, Set<String>> userGivenKudos;

  const SessionMatchRecapData({
    required this.sessionId,
    required this.sessionName,
    required this.venueName,
    this.courtName,
    required this.sportName,
    required this.scoringSystem,
    this.isSets = false,
    required this.totalRounds,
    required this.totalPlayers,
    required this.rounds,
    required this.standings,
    this.myStats,
    this.kudosMap = const {},
    this.userGivenKudos = const {},
  });
}

class SessionRoundRecapItem {
  final int roundNumber;
  final List<SessionMatchItem> matches;

  const SessionRoundRecapItem({
    required this.roundNumber,
    required this.matches,
  });
}

class SessionMatchItem {
  final int matchId;
  final int roundNumber;
  final String courtName;
  final List<String> sideANames;
  final List<String> sideBNames;
  final int scoreA;
  final int scoreB;
  final String setDetails;
  final bool isSideAWinner;
  final bool isSideBWinner;
  final bool isDraw;

  const SessionMatchItem({
    required this.matchId,
    required this.roundNumber,
    required this.courtName,
    required this.sideANames,
    required this.sideBNames,
    required this.scoreA,
    required this.scoreB,
    required this.setDetails,
    required this.isSideAWinner,
    required this.isSideBWinner,
    required this.isDraw,
  });
}

class SessionPlayerStanding {
  final int rank;
  final int playerId;
  final String nama;
  final String level;
  final String? foto;
  final String gender;
  final int matchesPlayed;
  final int matchesWon;
  final int matchesLost;
  final int setsWon;
  final int setsLost;
  final int gamesWon;
  final int gamesLost;
  final int pointsFor;
  final int pointsAgainst;
  final int pointDiff;
  final int gameDiff;
  final int setDiff;
  final int scoreWon;
  final int gamesDiff;

  const SessionPlayerStanding({
    required this.rank,
    required this.playerId,
    required this.nama,
    required this.level,
    this.foto,
    this.gender = 'Male',
    required this.matchesPlayed,
    required this.matchesWon,
    required this.matchesLost,
    this.setsWon = 0,
    this.setsLost = 0,
    required this.gamesWon,
    this.gamesLost = 0,
    this.pointsFor = 0,
    this.pointsAgainst = 0,
    this.pointDiff = 0,
    this.gameDiff = 0,
    this.setDiff = 0,
    required this.scoreWon,
    required this.gamesDiff,
  });
}

class SessionPersonalStat {
  final String nama;
  final String level;
  final String? foto;
  final String sportName;
  final int totalPoints;
  final int winRatePercent;
  final String durationPlayed;
  final int wins;
  final int losses;

  const SessionPersonalStat({
    required this.nama,
    required this.level,
    this.foto,
    required this.sportName,
    required this.totalPoints,
    required this.winRatePercent,
    this.durationPlayed = '0m',
    this.wins = 0,
    this.losses = 0,
  });
}


