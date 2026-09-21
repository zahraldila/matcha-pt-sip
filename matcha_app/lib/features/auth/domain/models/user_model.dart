class UserModel {
  final int userId;
  final String nama;
  final String email;
  final String? noHp;
  final String? password;
  final String role; // 'member' or 'venue_owner' or 'Host'
  final bool isHost;
  final String statusUser;
  final String? foto;
  final int? playerId;
  final String? level; // 'Newbie', 'Beginner', 'Intermediate', 'Advanced'
  final String? gender;
  final int? usia;
  final int? communityId;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  const UserModel({
    required this.userId,
    required this.nama,
    required this.email,
    this.noHp,
    this.password,
    this.role = 'member',
    this.isHost = false,
    this.statusUser = 'Active',
    this.foto,
    this.playerId,
    this.level,
    this.gender,
    this.usia,
    this.communityId,
    this.createdAt,
    this.updatedAt,
  });

  UserModel copyWith({
    int? userId,
    String? nama,
    String? email,
    String? noHp,
    String? password,
    String? role,
    bool? isHost,
    String? statusUser,
    String? foto,
    int? playerId,
    String? level,
    String? gender,
    int? usia,
    int? communityId,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return UserModel(
      userId: userId ?? this.userId,
      nama: nama ?? this.nama,
      email: email ?? this.email,
      noHp: noHp ?? this.noHp,
      password: password ?? this.password,
      role: role ?? this.role,
      isHost: isHost ?? this.isHost,
      statusUser: statusUser ?? this.statusUser,
      foto: foto ?? this.foto,
      playerId: playerId ?? this.playerId,
      level: level ?? this.level,
      gender: gender ?? this.gender,
      usia: usia ?? this.usia,
      communityId: communityId ?? this.communityId,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  factory UserModel.fromJson(Map<String, dynamic> json, {Map<String, dynamic>? playerJson}) {
    final isHostVal = json['is_host'] == true ||
        json['is_host'] == 1 ||
        json['is_host']?.toString().toLowerCase() == 'true' ||
        json['role']?.toString().toLowerCase() == 'host';

    int? parsedPlayerId;
    if (playerJson != null && playerJson['player_id'] != null) {
      parsedPlayerId = playerJson['player_id'] is int
          ? playerJson['player_id'] as int
          : int.tryParse(playerJson['player_id'].toString());
    }

    int? parsedUsia;
    if (playerJson != null && playerJson['usia'] != null) {
      parsedUsia = playerJson['usia'] is int
          ? playerJson['usia'] as int
          : int.tryParse(playerJson['usia'].toString());
    }

    int? parsedCommunityId;
    if (playerJson != null && playerJson['community_id'] != null) {
      parsedCommunityId = playerJson['community_id'] is int
          ? playerJson['community_id'] as int
          : int.tryParse(playerJson['community_id'].toString());
    }

    return UserModel(
      userId: json['user_id'] is int
          ? json['user_id'] as int
          : int.parse(json['user_id'].toString()),
      nama: json['nama'] as String? ?? '',
      email: json['email'] as String? ?? '',
      noHp: json['no_hp'] as String?,
      password: json['password'] as String?,
      role: json['role'] as String? ?? 'member',
      isHost: isHostVal,
      statusUser: json['status_user'] as String? ?? 'Active',
      foto: json['foto'] as String?,
      playerId: parsedPlayerId,
      level: playerJson?['level'] as String? ?? 'Intermediate',
      gender: playerJson?['gender'] as String?,
      usia: parsedUsia,
      communityId: parsedCommunityId,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      updatedAt: json['updated_at'] != null
          ? DateTime.tryParse(json['updated_at'].toString())
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'user_id': userId,
      'nama': nama,
      'email': email,
      if (noHp != null) 'no_hp': noHp,
      if (password != null) 'password': password,
      'role': role,
      'is_host': isHost,
      'status_user': statusUser,
      if (foto != null) 'foto': foto,
      if (createdAt != null) 'created_at': createdAt!.toIso8601String(),
      if (updatedAt != null) 'updated_at': updatedAt!.toIso8601String(),
    };
  }
}
