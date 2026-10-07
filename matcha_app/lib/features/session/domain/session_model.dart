class SessionPlayerModel {
  final int playerId;
  final int? userId;
  final String nama;
  final String? level;
  final String? gender;
  final int? usia;
  final String? foto;

  SessionPlayerModel({
    required this.playerId,
    this.userId,
    required this.nama,
    this.level,
    this.gender,
    this.usia,
    this.foto,
  });

  bool get isMember => userId != null && userId! > 0;

  factory SessionPlayerModel.fromMap(Map<String, dynamic> map) {
    final player = map['tb_player'] is Map ? map['tb_player'] as Map<String, dynamic> : map;
    final rawPlayerId = player['player_id'] ?? map['player_id'];
    int pId = 0;
    if (rawPlayerId is num) {
      pId = rawPlayerId.toInt();
    } else if (rawPlayerId is String) {
      pId = int.tryParse(rawPlayerId) ?? 0;
    }

    int? uId;
    final rawUserId = player['user_id'];
    if (rawUserId is num) {
      uId = rawUserId.toInt();
    } else if (rawUserId is String) {
      uId = int.tryParse(rawUserId);
    }

    int? usiaVal;
    final rawUsia = player['usia'];
    if (rawUsia is num) {
      usiaVal = rawUsia.toInt();
    } else if (rawUsia is String) {
      usiaVal = int.tryParse(rawUsia);
    }

    final playerFoto = player['foto']?.toString().trim();

    final userData = player['tb_user'];
    final userFoto = userData is Map
        ? userData['foto']?.toString().trim()
        : null;

    final String? fotoUrl =
        playerFoto != null && playerFoto.isNotEmpty
            ? playerFoto
            : userFoto != null && userFoto.isNotEmpty
                ? userFoto
                : null;

    return SessionPlayerModel(
      playerId: pId,
      userId: uId,
      nama: (player['nama'] ?? player['nama_player'] ?? 'Pemain').toString(),
      level: player['level']?.toString() ?? 'Beginner',
      gender: player['gender']?.toString() ?? 'Male',
      usia: usiaVal ?? 25,
      foto: fotoUrl,
    );
  }
}

class SessionModel {
  final int sessionId;
  final int hostUserId;
  final int sportId;
  final int venueId;
  final String namaSession;
  final String scoringSystem;
  final String? waktuSession;
  final DateTime? datetime;
  final String statusSession;
  final int jumlahPemain;
  final String jenisPermainan;
  final String sportName;
  final String venueName;
  final String? venueAddress;
  final String? venueCity;
  final String? venueFoto;
  final int? venueOwnerUserId;
  final String? courtName;
  final String hostName;
  final String hostLevel;
  final String? hostAvatar;
  final List<SessionPlayerModel> registeredPlayers;
  final String? shareToken;

  SessionModel({
    required this.sessionId,
    required this.hostUserId,
    required this.sportId,
    required this.venueId,
    required this.namaSession,
    required this.scoringSystem,
    this.waktuSession,
    this.datetime,
    required this.statusSession,
    required this.jumlahPemain,
    required this.jenisPermainan,
    required this.sportName,
    required this.venueName,
    this.venueAddress,
    this.venueCity,
    this.venueFoto,
    this.venueOwnerUserId,
    this.courtName,
    this.hostName = 'Host Mabar',
    this.hostLevel = 'Intermediate',
    this.hostAvatar,
    this.registeredPlayers = const [],
    this.shareToken,
  });

  String? get shareUrl => shareToken != null && shareToken!.trim().isNotEmpty
      ? 'https://matcha.siproduktif.com/games/share/${shareToken!.trim()}'
      : null;

  SessionModel copyWith({
    int? sessionId,
    int? hostUserId,
    int? sportId,
    int? venueId,
    String? namaSession,
    String? scoringSystem,
    String? waktuSession,
    DateTime? datetime,
    String? statusSession,
    int? jumlahPemain,
    String? jenisPermainan,
    String? sportName,
    String? venueName,
    String? venueAddress,
    String? venueCity,
    String? venueFoto,
    int? venueOwnerUserId,
    String? courtName,
    String? hostName,
    String? hostLevel,
    String? hostAvatar,
    List<SessionPlayerModel>? registeredPlayers,
    String? shareToken,
  }) {
    return SessionModel(
      sessionId: sessionId ?? this.sessionId,
      hostUserId: hostUserId ?? this.hostUserId,
      sportId: sportId ?? this.sportId,
      venueId: venueId ?? this.venueId,
      namaSession: namaSession ?? this.namaSession,
      scoringSystem: scoringSystem ?? this.scoringSystem,
      waktuSession: waktuSession ?? this.waktuSession,
      datetime: datetime ?? this.datetime,
      statusSession: statusSession ?? this.statusSession,
      jumlahPemain: jumlahPemain ?? this.jumlahPemain,
      jenisPermainan: jenisPermainan ?? this.jenisPermainan,
      sportName: sportName ?? this.sportName,
      venueName: venueName ?? this.venueName,
      venueAddress: venueAddress ?? this.venueAddress,
      venueCity: venueCity ?? this.venueCity,
      venueFoto: venueFoto ?? this.venueFoto,
      venueOwnerUserId: venueOwnerUserId ?? this.venueOwnerUserId,
      courtName: courtName ?? this.courtName,
      hostName: hostName ?? this.hostName,
      hostLevel: hostLevel ?? this.hostLevel,
      hostAvatar: hostAvatar ?? this.hostAvatar,
      registeredPlayers: registeredPlayers ?? this.registeredPlayers,
      shareToken: shareToken ?? this.shareToken,
    );
  }

  static int _toInt(dynamic val, {int defaultVal = 0}) {
    if (val == null) return defaultVal;
    if (val is num) return val.toInt();
    if (val is String) return int.tryParse(val) ?? defaultVal;
    return defaultVal;
  }

  factory SessionModel.fromMap(Map<String, dynamic> map) {
    // Parse sport
    String sName = 'Padel';
    if (map['tb_sport'] is Map && map['tb_sport']['nama_sport'] != null) {
      sName = map['tb_sport']['nama_sport'].toString();
    }

    // Parse venue
    String vName = 'Venue Lapangan';
    String? vAddr;
    String? vCity;
    String? vFoto;
    int? vOwnerId;
    if (map['tb_venue'] is Map) {
      final v = map['tb_venue'] as Map<String, dynamic>;
      vName = (v['nama_venue'] ?? 'Venue Lapangan').toString();
      vAddr = v['alamat']?.toString();
      vCity = v['kota']?.toString();
      vFoto = v['foto']?.toString();
      if (v['owner_user_id'] != null) {
        vOwnerId = _toInt(v['owner_user_id']);
      }
    }

    // Parse court
    String? cName;
    if (map['tb_session_court'] is List && (map['tb_session_court'] as List).isNotEmpty) {
      final firstCourtRel = (map['tb_session_court'] as List).first;
      if (firstCourtRel is Map && firstCourtRel['tb_court'] is Map) {
        cName = firstCourtRel['tb_court']['nama_court']?.toString();
      }
    }

    // Parse host user
    String hName = 'Host Mabar';
    String hLevel = 'Intermediate';
    String? hAvatar;
    if (map['tb_user'] is Map) {
      final u = map['tb_user'] as Map<String, dynamic>;
      hName = (u['nama'] ?? 'Host Mabar').toString();
      hLevel = (u['level'] ?? 'Intermediate').toString();
      hAvatar = u['foto']?.toString();
    }

    // Parse players
    List<SessionPlayerModel> players = [];
    if (map['tb_session_player'] is List) {
      for (final item in map['tb_session_player']) {
        if (item is Map) {
          players.add(SessionPlayerModel.fromMap(Map<String, dynamic>.from(item)));
        }
      }
    }

    final hostId = _toInt(map['host_user_id'] ?? map['user_id']);
    if (hName == 'Host Mabar' && hostId > 0) {
      final hostPlayer = players.where((p) => p.userId != null && p.userId == hostId).firstOrNull;
      if (hostPlayer != null) {
        hName = hostPlayer.nama;
        hLevel = hostPlayer.level ?? 'Intermediate';
        hAvatar = hostPlayer.foto;
      }
    }

    return SessionModel(
      sessionId: _toInt(map['session_id']),
      hostUserId: _toInt(map['host_user_id'] ?? map['user_id']),
      sportId: _toInt(map['sport_id'], defaultVal: 1),
      venueId: _toInt(map['venue_id']),
      namaSession: (map['nama_session'] ?? 'Sesi Mabar').toString(),
      scoringSystem: (map['scoring_system'] ?? map['drawing_method'] ?? 'Total of 3').toString(),
      waktuSession: map['waktu_session']?.toString(),
      datetime: map['datetime'] != null
          ? DateTime.tryParse(map['datetime'].toString())
          : (map['waktu_session'] != null ? DateTime.tryParse(map['waktu_session'].toString()) : null),
      statusSession: (map['status_session'] ?? 'Open').toString(),
      jumlahPemain: _toInt(map['jumlah_pemain'], defaultVal: 6),
      jenisPermainan: (map['jenis_permainan'] ?? 'Double').toString(),
      sportName: sName,
      venueName: vName,
      venueAddress: vAddr,
      venueCity: vCity,
      venueFoto: vFoto,
      venueOwnerUserId: vOwnerId,
      courtName: cName ?? 'Court 1',
      hostName: hName,
      hostLevel: hLevel,
      hostAvatar: hAvatar,
      registeredPlayers: players,
      shareToken: map['share_token']?.toString(),
    );
  }

  int get currentPlayersCount => registeredPlayers.length;
  bool get isFull => currentPlayersCount >= jumlahPemain;
  int get availableSlots => (jumlahPemain - currentPlayersCount).clamp(0, jumlahPemain);
}
