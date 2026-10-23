class GamePlayerItem {
  final String id;
  final String name;
  final String gender; // 'Laki-laki' or 'Perempuan'
  final String level; // 'Beginner', 'Intermediate', 'Advanced'
  final bool isGuest;
  final String? avatarUrl;
  final int? userId;
  final int? playerId;
  final String status; // 'active', 'resting', 'withdrawn'
  final String? withdrawnReason;
  final DateTime? withdrawnAt;

  const GamePlayerItem({
    required this.id,
    required this.name,
    this.gender = 'Laki-laki',
    this.level = 'Beginner',
    this.isGuest = false,
    this.avatarUrl,
    this.userId,
    this.playerId,
    this.status = 'active',
    this.withdrawnReason,
    this.withdrawnAt,
  });

  bool get isActive => status.toLowerCase() == 'active';
  bool get isResting => status.toLowerCase() == 'resting';
  bool get isWithdrawn => status.toLowerCase() == 'withdrawn';

  String get displayName {
    if (name.trim().isNotEmpty) return name.trim();
    if (playerId != null) return 'Pemain $playerId';
    if (id.trim().isNotEmpty && int.tryParse(id) != null) return 'Pemain $id';
    return 'Pemain';
  }

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
      'status': status,
      'withdrawn_reason': withdrawnReason,
      'withdrawn_at': withdrawnAt?.toIso8601String(),
    };
  }

  factory GamePlayerItem.fromMap(Map<String, dynamic> map) {
    final rawName = map['name']?.toString().trim();
    final rawNama = map['nama']?.toString().trim();
    final parsedPId = map['player_id'] is int
        ? map['player_id'] as int
        : int.tryParse(map['player_id']?.toString() ?? '');
    final resolvedName = (rawName != null && rawName.isNotEmpty)
        ? rawName
        : ((rawNama != null && rawNama.isNotEmpty)
            ? rawNama
            : (parsedPId != null ? 'Pemain $parsedPId' : 'Player'));

    return GamePlayerItem(
      id: map['id']?.toString() ?? UniqueKey().toString(),
      name: resolvedName,
      gender: map['gender'] ?? 'Laki-laki',
      level: map['level'] ?? 'Beginner',
      isGuest: map['is_guest'] == true,
      avatarUrl: map['avatar_url'] ?? map['foto'],
      userId: map['user_id'] is int ? map['user_id'] : int.tryParse(map['user_id']?.toString() ?? ''),
      playerId: parsedPId,
      status: map['status']?.toString() ?? 'active',
      withdrawnReason: map['withdrawn_reason']?.toString(),
      withdrawnAt: map['withdrawn_at'] != null ? DateTime.tryParse(map['withdrawn_at'].toString()) : null,
    );
  }

  GamePlayerItem copyWith({
    String? id,
    String? name,
    String? gender,
    String? level,
    bool? isGuest,
    String? avatarUrl,
    int? userId,
    int? playerId,
    String? status,
    String? withdrawnReason,
    DateTime? withdrawnAt,
  }) {
    return GamePlayerItem(
      id: id ?? this.id,
      name: name ?? this.name,
      gender: gender ?? this.gender,
      level: level ?? this.level,
      isGuest: isGuest ?? this.isGuest,
      avatarUrl: avatarUrl ?? this.avatarUrl,
      userId: userId ?? this.userId,
      playerId: playerId ?? this.playerId,
      status: status ?? this.status,
      withdrawnReason: withdrawnReason ?? this.withdrawnReason,
      withdrawnAt: withdrawnAt ?? this.withdrawnAt,
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
  int? hostUserId;

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
    this.hostUserId,
  }) : players = players ?? [];

  int? customTotalRounds;

  int get maxTargetPoints {
    final firstToRegex = RegExp(r'first\s*to\s*(\d+)', caseSensitive: false);
    final mFirst = firstToRegex.firstMatch(scoringSystem);
    if (mFirst != null) return int.tryParse(mFirst.group(1) ?? '') ?? 8;

    final totalOfRegex = RegExp(r'total\s*of\s*(\d+)', caseSensitive: false);
    final mTotal = totalOfRegex.firstMatch(scoringSystem);
    if (mTotal != null) return int.tryParse(mTotal.group(1) ?? '') ?? 3;

    return 8;
  }

  int get totalRounds {
    if (customTotalRounds != null && customTotalRounds! > 0) {
      return customTotalRounds!;
    }
    
    // 1. Total of N: Jumlah set/ronde yang dimainkan adalah N (sama persis dengan Laravel ScoringService)
    final totalOfRegex = RegExp(r'total\s*of\s*(\d+)', caseSensitive: false);
    final totalOfMatch = totalOfRegex.firstMatch(scoringSystem);
    if (totalOfMatch != null) {
      final n = int.tryParse(totalOfMatch.group(1) ?? '');
      if (n != null && n > 0) return n;
    }

    final isSingle = playMode.toLowerCase() == 'single';
    final isTeam = gameType.toLowerCase().contains('team');
    final activeCount = players.where((p) => !p.isWithdrawn).length;

    // Follow Web ScoringService::determineSessionRoundCount
    if (isSingle) {
      if (activeCount <= 2) return 3;
      final completeRotation = (activeCount % 2 == 0) ? (activeCount - 1) : activeCount;
      return completeRotation.clamp(3, 7);
    }

    if (isTeam) {
      final teams = (activeCount ~/ 2).clamp(2, 8);
      if (teams <= 2) return 3;
      final completeRotation = (teams % 2 == 0) ? (teams - 1) : teams;
      return completeRotation.clamp(3, 5);
    }

    // Americano Double
    if (activeCount <= 4) return 3;
    final completeRotation = (activeCount % 2 == 0) ? (activeCount - 1) : activeCount;
    return completeRotation.clamp(3, 7);
  }
}
