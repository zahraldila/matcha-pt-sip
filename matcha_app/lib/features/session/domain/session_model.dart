class SessionPlayerModel {
  final int playerId;
  final String nama;
  final String? level;
  final String? gender;
  final String? foto;

  SessionPlayerModel({
    required this.playerId,
    required this.nama,
    this.level,
    this.gender,
    this.foto,
  });

  factory SessionPlayerModel.fromMap(Map<String, dynamic> map) {
    final player = map['tb_player'] is Map ? map['tb_player'] as Map<String, dynamic> : map;
    final rawPlayerId = player['player_id'] ?? map['player_id'];
    int pId = 0;
    if (rawPlayerId is num) {
      pId = rawPlayerId.toInt();
    } else if (rawPlayerId is String) {
      pId = int.tryParse(rawPlayerId) ?? 0;
    }

    return SessionPlayerModel(
      playerId: pId,
      nama: (player['nama'] ?? player['nama_player'] ?? 'Pemain').toString(),
      level: player['level']?.toString(),
      gender: player['gender']?.toString(),
      foto: player['foto']?.toString(),
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
  final List<SessionPlayerModel> registeredPlayers;

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
    this.registeredPlayers = const [],
  });

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
    if (map['tb_venue'] is Map) {
      final v = map['tb_venue'] as Map<String, dynamic>;
      vName = (v['nama_venue'] ?? 'Venue Lapangan').toString();
      vAddr = v['alamat']?.toString();
      vCity = v['kota']?.toString();
      vFoto = v['foto']?.toString();
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
      registeredPlayers: players,
    );
  }

  int get currentPlayersCount => registeredPlayers.length;
  bool get isFull => currentPlayersCount >= jumlahPemain;
  int get availableSlots => (jumlahPemain - currentPlayersCount).clamp(0, jumlahPemain);
}
