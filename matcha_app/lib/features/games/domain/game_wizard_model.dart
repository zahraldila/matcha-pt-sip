class GamePlayerItem {
  final String id;
  final String name;
  final String gender; // 'Laki-laki' or 'Perempuan'
  final String level; // 'Beginner', 'Intermediate', 'Advanced'
  final bool isGuest;
  final String? avatarUrl;
  final int? userId;
  final int? playerId;

  const GamePlayerItem({
    required this.id,
    required this.name,
    this.gender = 'Laki-laki',
    this.level = 'Beginner',
    this.isGuest = false,
    this.avatarUrl,
    this.userId,
    this.playerId,
  });

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'name': name,
      'gender': gender,
      'level': level,
      'is_guest': isGuest,
      'avatar_url': avatarUrl,
      'user_id': userId,
      'player_id': playerId,
    };
  }

  factory GamePlayerItem.fromMap(Map<String, dynamic> map) {
    return GamePlayerItem(
      id: map['id']?.toString() ?? UniqueKey().toString(),
      name: map['name'] ?? map['nama'] ?? 'Player',
      gender: map['gender'] ?? 'Laki-laki',
      level: map['level'] ?? 'Beginner',
      isGuest: map['is_guest'] == true,
      avatarUrl: map['avatar_url'] ?? map['foto'],
      userId: map['user_id'] is int ? map['user_id'] : int.tryParse(map['user_id']?.toString() ?? ''),
      playerId: map['player_id'] is int ? map['player_id'] : int.tryParse(map['player_id']?.toString() ?? ''),
    );
  }
}

class UniqueKey {
  static int _counter = 0;
  @override
  String toString() => 'player_${DateTime.now().millisecondsSinceEpoch}_${++_counter}';
}

class GameWizardConfig {
  String sport; // 'Padel' or 'Tennis'
  String gameType; // 'Americano', 'Team Americano', 'Mexicano', etc.
  String activityName;
  int courtCount;
  int? venueId;
  String? venueName;
  String scoringSystem; // 'Total of 3 Poin', 'Total of 4 Poin', ..., 'First to 21 Poin (Tuntas)'
  String leaderboardRankedBy; // 'Point' or 'Win'
  String playMode; // 'Double' or 'Single'
  List<GamePlayerItem> players;
  int? sessionId;

  GameWizardConfig({
    this.sport = '',
    this.gameType = '',
    this.activityName = '',
    this.courtCount = 1,
    this.venueId,
    this.venueName,
    this.scoringSystem = 'Total of 3 Poin',
    this.leaderboardRankedBy = 'Point',
    this.playMode = 'Double',
    List<GamePlayerItem>? players,
    this.sessionId,
  }) : players = players ?? [];

  int? customTotalRounds;

  int get maxTargetPoints {
    if (scoringSystem.contains('32')) return 32;
    if (scoringSystem.contains('21')) return 21;
    if (scoringSystem.contains('15')) return 15;
    if (scoringSystem.contains('11')) return 11;
    if (scoringSystem.contains('8')) return 8;
    if (scoringSystem.contains('7')) return 7;
    if (scoringSystem.contains('6')) return 6;
    if (scoringSystem.contains('5')) return 5;
    if (scoringSystem.contains('4')) return 4;
    if (scoringSystem.contains('3')) return 3;
    return 3;
  }

  int get totalRounds {
    if (customTotalRounds != null && customTotalRounds! > 0) {
      return customTotalRounds!;
    }
    final clean = scoringSystem.toLowerCase();
    // Sistem Langsung Tuntas (First to X) adalah 1 ronde match langsung
    if (clean.contains('first to')) {
      return 1;
    }
    if (clean.contains('total of 7')) return 7;
    if (clean.contains('total of 6')) return 6;
    if (clean.contains('total of 5')) return 5;
    if (clean.contains('total of 4')) return 4;
    if (clean.contains('total of 3')) return 3;
    if (clean.contains('total of 8')) return 8;

    if (playMode.toLowerCase() == 'single' && players.length == 2) return 1;
    if (players.length <= 4) return 3;
    return (players.length - 1).clamp(3, 7);
  }
}
