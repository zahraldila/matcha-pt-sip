import 'package:flutter/material.dart';

/// Model Sesi Mabar
class MatchaSession {
  final String id;
  final String title;
  final String sport; // 'tennis', 'padel', 'badminton'
  final String venueName;
  final String location;
  final String date;
  final String time;
  final int maxParticipants;
  final List<MatchaPlayer> participants;
  final String status; // 'live', 'upcoming', 'finished'
  final String matchFormat; // 'Americano Double (2 vs 2)', 'Americano Single (1 vs 1)', 'Team Americano'
  final int pricePerPerson;
  final String hostName;
  final String? bannerUrl;

  MatchaSession({
    required this.id,
    required this.title,
    required this.sport,
    required this.venueName,
    required this.location,
    required this.date,
    required this.time,
    required this.maxParticipants,
    required this.participants,
    required this.status,
    required this.matchFormat,
    required this.pricePerPerson,
    required this.hostName,
    this.bannerUrl,
  });

  bool get isFull => participants.length >= maxParticipants;
  int get availableSlots => maxParticipants - participants.length;

  MatchaSession copyWith({
    String? id,
    String? title,
    String? sport,
    String? venueName,
    String? location,
    String? date,
    String? time,
    int? maxParticipants,
    List<MatchaPlayer>? participants,
    String? status,
    String? matchFormat,
    int? pricePerPerson,
    String? hostName,
    String? bannerUrl,
  }) {
    return MatchaSession(
      id: id ?? this.id,
      title: title ?? this.title,
      sport: sport ?? this.sport,
      venueName: venueName ?? this.venueName,
      location: location ?? this.location,
      date: date ?? this.date,
      time: time ?? this.time,
      maxParticipants: maxParticipants ?? this.maxParticipants,
      participants: participants ?? this.participants,
      status: status ?? this.status,
      matchFormat: matchFormat ?? this.matchFormat,
      pricePerPerson: pricePerPerson ?? this.pricePerPerson,
      hostName: hostName ?? this.hostName,
      bannerUrl: bannerUrl ?? this.bannerUrl,
    );
  }
}

/// Model Pemain / Member
class MatchaPlayer {
  final String id;
  final String name;
  final String avatarUrl;
  final String tier; // 'Beginner', 'Intermediate', 'Advanced', 'Pro'
  final int winRate;
  final int totalMatches;
  final int kudosCount;
  final bool isHost;

  MatchaPlayer({
    required this.id,
    required this.name,
    required this.avatarUrl,
    required this.tier,
    required this.winRate,
    required this.totalMatches,
    required this.kudosCount,
    this.isHost = false,
  });
}

/// Model Pertandingan Drawing / Court Match
class CourtMatch {
  final int courtNumber;
  final String courtName;
  final List<MatchaPlayer> teamA;
  final List<MatchaPlayer> teamB;
  int teamAScore;
  int teamBScore;
  String status; // 'waiting', 'live', 'completed'

  CourtMatch({
    required this.courtNumber,
    required this.courtName,
    required this.teamA,
    required this.teamB,
    this.teamAScore = 0,
    this.teamBScore = 0,
    this.status = 'waiting',
  });
}

/// Model Komunitas
class MatchaCommunity {
  final String id;
  final String name;
  final String sport;
  final String location;
  final String description;
  final String logoUrl;
  final int memberCount;
  final String regularSchedule;
  bool isJoined;

  MatchaCommunity({
    required this.id,
    required this.name,
    required this.sport,
    required this.location,
    required this.description,
    required this.logoUrl,
    required this.memberCount,
    required this.regularSchedule,
    this.isJoined = false,
  });
}

/// Central Mock Data Service (Reactive In-Memory Store)
class MockDataService extends ChangeNotifier {
  static final MockDataService _instance = MockDataService._internal();
  factory MockDataService() => _instance;

  MockDataService._internal() {
    _initDefaultData();
  }

  // --- Current Logged In User ---
  late MatchaPlayer _currentUser;
  MatchaPlayer get currentUser => _currentUser;

  bool _isHostMode = true;
  bool get isHostMode => _isHostMode;

  void toggleHostMode() {
    _isHostMode = !_isHostMode;
    notifyListeners();
  }

  // --- Sessions List ---
  List<MatchaSession> _sessions = [];
  List<MatchaSession> get sessions => _sessions;

  MatchaSession? get activeLiveSession =>
      _sessions.where((s) => s.status == 'live').firstOrNull;

  List<MatchaSession> get upcomingSessions =>
      _sessions.where((s) => s.status == 'upcoming').toList();

  List<MatchaSession> get finishedSessions =>
      _sessions.where((s) => s.status == 'finished').toList();

  // --- Court Matches / Drawing State ---
  List<CourtMatch> _currentDrawingMatches = [];
  List<CourtMatch> get currentDrawingMatches => _currentDrawingMatches;
  bool _isDrawingLocked = false;
  bool get isDrawingLocked => _isDrawingLocked;

  // --- Live Match Scoring State ---
  int _currentSet = 1;
  int get currentSet => _currentSet;
  int _teamAPoints = 18;
  int get teamAPoints => _teamAPoints;
  int _teamBPoints = 16;
  int get teamBPoints => _teamBPoints;
  final List<List<int>> _setHistory = [
    [21, 19], // Set 1 finished
  ];
  List<List<int>> get setHistory => _setHistory;

  String _currentServer = 'teamA'; // 'teamA' or 'teamB'
  String get currentServer => _currentServer;

  // History for Undo
  final List<Map<String, dynamic>> _scoreHistory = [];

  // --- Kudos Reaction State for Match Recap ---
  final Map<String, int> _kudosCounts = {
    'smash': 24, // 🔥 Smash King
    'speed': 18, // ⚡ Speedy Player
    'respect': 35, // 👏 Good Game / Respect
  };
  Map<String, int> get kudosCounts => _kudosCounts;

  final Set<String> _userGivenKudos = {};
  Set<String> get userGivenKudos => _userGivenKudos;

  // --- Communities ---
  List<MatchaCommunity> _communities = [];
  List<MatchaCommunity> get communities => _communities;

  // --- Directory of Players ---
  List<MatchaPlayer> _allPlayers = [];
  List<MatchaPlayer> get allPlayers => _allPlayers;

  // --- Initializer ---
  void _initDefaultData() {
    _currentUser = MatchaPlayer(
      id: 'usr_marcel',
      name: 'Marcel Santoso',
      avatarUrl: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150',
      tier: 'Advanced',
      winRate: 74,
      totalMatches: 48,
      kudosCount: 142,
      isHost: true,
    );

    _allPlayers = [
      _currentUser,
      MatchaPlayer(
        id: 'usr_budi',
        name: 'Budi Pratama',
        avatarUrl: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150',
        tier: 'Advanced',
        winRate: 68,
        totalMatches: 36,
        kudosCount: 88,
      ),
      MatchaPlayer(
        id: 'usr_dimas',
        name: 'Dimas Anggara',
        avatarUrl: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150',
        tier: 'Intermediate',
        winRate: 60,
        totalMatches: 24,
        kudosCount: 52,
      ),
      MatchaPlayer(
        id: 'usr_kevin',
        name: 'Kevin Sanjaya',
        avatarUrl: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=150',
        tier: 'Pro',
        winRate: 85,
        totalMatches: 92,
        kudosCount: 310,
      ),
      MatchaPlayer(
        id: 'usr_siti',
        name: 'Siti Rahma',
        avatarUrl: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150',
        tier: 'Intermediate',
        winRate: 55,
        totalMatches: 19,
        kudosCount: 41,
      ),
      MatchaPlayer(
        id: 'usr_reza',
        name: 'Reza Rahardian',
        avatarUrl: 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150',
        tier: 'Beginner',
        winRate: 42,
        totalMatches: 12,
        kudosCount: 20,
      ),
      MatchaPlayer(
        id: 'usr_joko',
        name: 'Joko Widodo',
        avatarUrl: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150',
        tier: 'Advanced',
        winRate: 71,
        totalMatches: 54,
        kudosCount: 115,
      ),
      MatchaPlayer(
        id: 'usr_maya',
        name: 'Maya Putri',
        avatarUrl: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150',
        tier: 'Intermediate',
        winRate: 58,
        totalMatches: 29,
        kudosCount: 63,
      ),
    ];

    _sessions = [
      MatchaSession(
        id: 'ses_01',
        title: 'Mabar Padel Saturday Night Fever 🎾',
        sport: 'padel',
        venueName: 'SCBD Padel Club Arena',
        location: 'Jakarta Selatan',
        date: 'Hari Ini, 20 Sep 2026',
        time: '19:00 - 21:00 WIB',
        maxParticipants: 8,
        participants: [_currentUser, _allPlayers[1], _allPlayers[2], _allPlayers[3], _allPlayers[4], _allPlayers[5]],
        status: 'live',
        matchFormat: 'Americano Double (2 vs 2)',
        pricePerPerson: 120000,
        hostName: 'Marcel Santoso',
      ),
      MatchaSession(
        id: 'ses_02',
        title: 'Sunday Morning Tennis Rally 🏆',
        sport: 'tennis',
        venueName: 'Gelora Tennis Center',
        location: 'Jakarta Pusat',
        date: 'Besok, 21 Sep 2026',
        time: '07:00 - 09:00 WIB',
        maxParticipants: 6,
        participants: [_allPlayers[1], _allPlayers[3], _allPlayers[6]],
        status: 'upcoming',
        matchFormat: 'Americano Single (1 vs 1)',
        pricePerPerson: 85000,
        hostName: 'Budi Pratama',
      ),
      MatchaSession(
        id: 'ses_03',
        title: 'Badminton Smash & Fun Session 🏸',
        sport: 'badminton',
        venueName: 'Gorilla Badminton Hall',
        location: 'Bandung',
        date: 'Rabu, 24 Sep 2026',
        time: '18:30 - 21:00 WIB',
        maxParticipants: 12,
        participants: [_allPlayers[2], _allPlayers[4], _allPlayers[5], _allPlayers[7]],
        status: 'upcoming',
        matchFormat: 'Team Americano',
        pricePerPerson: 50000,
        hostName: 'Dimas Anggara',
      ),
      MatchaSession(
        id: 'ses_04',
        title: 'Friday Sunset Padel Open',
        sport: 'padel',
        venueName: 'Sunset Padel Court Kemang',
        location: 'Jakarta Selatan',
        date: '18 Sep 2026',
        time: '17:00 - 19:00 WIB',
        maxParticipants: 8,
        participants: [_currentUser, _allPlayers[1], _allPlayers[2], _allPlayers[3]],
        status: 'finished',
        matchFormat: 'Americano Double (2 vs 2)',
        pricePerPerson: 100000,
        hostName: 'Marcel Santoso',
      ),
    ];

    _generateDrawingMatches();

    _communities = [
      MatchaCommunity(
        id: 'com_01',
        name: 'Jakarta Padel Society 🏓',
        sport: 'padel',
        location: 'Jakarta Selatan & Pusat',
        description: 'Komunitas pegiat padel terbesar di Jabodetabek. Rutin mengadakan mabar weekend dan turnamen mini bulanan.',
        logoUrl: 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?w=150',
        memberCount: 342,
        regularSchedule: 'Sabtu & Minggu 18:00 WIB',
        isJoined: true,
      ),
      MatchaCommunity(
        id: 'com_02',
        name: 'Tennis Enthusiast Club (TEC) 🎾',
        sport: 'tennis',
        location: 'Jakarta Pusat',
        description: 'Tempat kumpul para pecinta tenis segala level. Dari beginner hingga tournament players.',
        logoUrl: 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?w=150',
        memberCount: 520,
        regularSchedule: 'Selasa & Kamis 19:00 WIB',
        isJoined: false,
      ),
      MatchaCommunity(
        id: 'com_03',
        name: 'Bandung Smash Lovers 🏸',
        sport: 'badminton',
        location: 'Bandung',
        description: 'Komunitas badminton aktif di Bandung Raya dengan sesi mabar fun & friendly competitive.',
        logoUrl: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=150',
        memberCount: 198,
        regularSchedule: 'Rabu & Jumat 19:00 WIB',
        isJoined: true,
      ),
    ];
  }

  // --- Drawing Management ---
  void _generateDrawingMatches() {
    _currentDrawingMatches = [
      CourtMatch(
        courtNumber: 1,
        courtName: 'Court 1 (Center)',
        teamA: [_currentUser, _allPlayers[1]],
        teamB: [_allPlayers[2], _allPlayers[3]],
        teamAScore: 18,
        teamBScore: 16,
        status: 'live',
      ),
      CourtMatch(
        courtNumber: 2,
        courtName: 'Court 2 (North)',
        teamA: [_allPlayers[4], _allPlayers[5]],
        teamB: [_allPlayers[6], _allPlayers[7]],
        teamAScore: 12,
        teamBScore: 15,
        status: 'live',
      ),
    ];
  }

  void shuffleDrawing() {
    final shuffled = List<MatchaPlayer>.from(_allPlayers)..shuffle();
    _currentDrawingMatches = [
      CourtMatch(
        courtNumber: 1,
        courtName: 'Court 1 (Center)',
        teamA: [shuffled[0], shuffled[1]],
        teamB: [shuffled[2], shuffled[3]],
        teamAScore: 0,
        teamBScore: 0,
        status: 'waiting',
      ),
      CourtMatch(
        courtNumber: 2,
        courtName: 'Court 2 (North)',
        teamA: [shuffled[4], shuffled[5]],
        teamB: [shuffled[6], shuffled[7]],
        teamAScore: 0,
        teamBScore: 0,
        status: 'waiting',
      ),
    ];
    _isDrawingLocked = false;
    notifyListeners();
  }

  void toggleLockDrawing() {
    _isDrawingLocked = !_isDrawingLocked;
    notifyListeners();
  }

  // --- Session Management ---
  void joinSession(String sessionId) {
    final index = _sessions.indexWhere((s) => s.id == sessionId);
    if (index != -1) {
      final session = _sessions[index];
      final isAlreadyJoined = session.participants.any((p) => p.id == _currentUser.id);

      List<MatchaPlayer> updatedParticipants = List.from(session.participants);
      if (isAlreadyJoined) {
        updatedParticipants.removeWhere((p) => p.id == _currentUser.id);
      } else {
        if (!session.isFull) {
          updatedParticipants.add(_currentUser);
        }
      }

      _sessions[index] = session.copyWith(participants: updatedParticipants);
      notifyListeners();
    }
  }

  void createSession(MatchaSession newSession) {
    _sessions.insert(0, newSession);
    notifyListeners();
  }

  // --- Live Scoring Actions ---
  void addPointTeamA() {
    _scoreHistory.add({
      'teamA': _teamAPoints,
      'teamB': _teamBPoints,
      'server': _currentServer,
    });
    _teamAPoints++;
    _currentDrawingMatches[0].teamAScore = _teamAPoints;
    notifyListeners();
  }

  void addPointTeamB() {
    _scoreHistory.add({
      'teamA': _teamAPoints,
      'teamB': _teamBPoints,
      'server': _currentServer,
    });
    _teamBPoints++;
    _currentDrawingMatches[0].teamBScore = _teamBPoints;
    notifyListeners();
  }

  void undoLastPoint() {
    if (_scoreHistory.isNotEmpty) {
      final last = _scoreHistory.removeLast();
      _teamAPoints = last['teamA'];
      _teamBPoints = last['teamB'];
      _currentServer = last['server'];
      _currentDrawingMatches[0].teamAScore = _teamAPoints;
      _currentDrawingMatches[0].teamBScore = _teamBPoints;
      notifyListeners();
    }
  }

  void switchServer() {
    _currentServer = _currentServer == 'teamA' ? 'teamB' : 'teamA';
    notifyListeners();
  }

  void finishCurrentSet() {
    _setHistory.add([_teamAPoints, _teamBPoints]);
    _currentSet++;
    _teamAPoints = 0;
    _teamBPoints = 0;
    _scoreHistory.clear();
    _currentDrawingMatches[0].teamAScore = 0;
    _currentDrawingMatches[0].teamBScore = 0;
    notifyListeners();
  }

  void resetMatchScores() {
    _currentSet = 1;
    _teamAPoints = 0;
    _teamBPoints = 0;
    _setHistory.clear();
    _scoreHistory.clear();
    _currentDrawingMatches[0].teamAScore = 0;
    _currentDrawingMatches[0].teamBScore = 0;
    notifyListeners();
  }

  // --- Kudos Reaction ---
  void toggleKudos(String type) {
    if (_userGivenKudos.contains(type)) {
      _userGivenKudos.remove(type);
      _kudosCounts[type] = (_kudosCounts[type] ?? 1) - 1;
    } else {
      _userGivenKudos.add(type);
      _kudosCounts[type] = (_kudosCounts[type] ?? 0) + 1;
    }
    notifyListeners();
  }

  // --- Community Actions ---
  void toggleJoinCommunity(String communityId) {
    final index = _communities.indexWhere((c) => c.id == communityId);
    if (index != -1) {
      final com = _communities[index];
      com.isJoined = !com.isJoined;
      notifyListeners();
    }
  }
}
